<?php
/* Everything security related lives here, so it is easy to review in one place. */

// ---------------------------------------------------------------------------
// Application key (signing + encryption)
// ---------------------------------------------------------------------------
function app_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $raw = (string) config('app_key', '');
    if (str_starts_with($raw, 'base64:')) {
        $raw = base64_decode(substr($raw, 7), true) ?: '';
    }
    if (strlen($raw) < 32) {
        error_log('HappyHolidayHome: app_key in inc/config.php is missing or shorter than 32 bytes.');
        http_response_code(500);
        exit('The site is not configured yet. See README.md, step 3.');
    }
    return $key = $raw;
}

function derive_key(string $purpose): string
{
    return hash_hmac('sha256', $purpose, app_key(), true);
}

/** AES-256-GCM. Output is base64( iv | tag | ciphertext ). */
function encrypt_secret(string $plain): string
{
    $iv  = random_bytes(12);
    $tag = '';
    $enc = openssl_encrypt($plain, 'aes-256-gcm', derive_key('totp-secret'), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $enc);
}

function decrypt_secret(string $blob): ?string
{
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', derive_key('totp-secret'),
        OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? null : $plain;
}

// ---------------------------------------------------------------------------
// Sessions
// ---------------------------------------------------------------------------
function session_area(): string
{
    return defined('HHH_AREA') ? HHH_AREA : 'site';
}

function secure_session_start(): void
{
    $area  = session_area();
    $https = is_https();

    ini_set('session.use_strict_mode', '1');     // refuse session ids the server did not issue
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    ini_set('session.gc_maxlifetime', '28800');

    // Visitors and admins get different cookies. An admin cookie is only sent to /admin.
    if ($area === 'admin') {
        session_name('hhh_admin');
        $path = rtrim(base_path(), '/') . '/admin/';
    } else {
        session_name($https && base_path() === '' ? '__Host-hhh_sid' : 'hhh_sid');
        $path = '/';
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $path,
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    $now      = time();
    $idle     = $area === 'admin' ? 20 * 60 : 30 * 60;   // idle timeout
    $absolute = $area === 'admin' ? 4 * 3600 : 8 * 3600; // hard cap
    $agent    = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

    if (isset($_SESSION['_born'])) {
        $expired = ($now - ($_SESSION['_seen'] ?? 0) > $idle)
                || ($now - $_SESSION['_born'] > $absolute)
                || !hash_equals($_SESSION['_agent'] ?? '', $agent);
        if ($expired) {
            $wasLoggedIn = isset($_SESSION['uid']) || isset($_SESSION['aid']);
            session_unset();
            session_regenerate_id(true);
            $_SESSION['_born'] = $now;
            $_SESSION['_agent'] = $agent;
            if ($wasLoggedIn) {
                $_SESSION['_flash'][] = ['type' => 'info', 'msg' => 'You were signed out for your security. Please sign in again.'];
            }
        }
    } else {
        session_regenerate_id(true);
        $_SESSION['_born']  = $now;
        $_SESSION['_agent'] = $agent;
    }
    $_SESSION['_seen'] = $now;
}

/** Call right after any login or privilege change to defeat session fixation. */
function session_rotate(): void
{
    session_regenerate_id(true);
    $_SESSION['_born']  = time();
    $_SESSION['_seen']  = time();
    $_SESSION['_agent'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    unset($_SESSION['_csrf']);
}

function session_destroy_completely(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict',
        ]);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------
// HTTP security headers
// ---------------------------------------------------------------------------
function send_security_headers(): void
{
    $captcha = defined('HHH_CAPTCHA') && HHH_CAPTCHA && captcha_enabled();

    $script = "'self'" . ($captcha ? ' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/' : '');
    $style  = "'self'" . ($captcha ? " 'unsafe-inline'" : '');   // the reCAPTCHA widget injects inline styles
    $frame  = $captcha ? 'https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/' : "'none'";

    header("Content-Security-Policy: default-src 'self'; script-src $script; style-src $style; "
         . "img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src $frame; "
         . "object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Cache-Control: no-store, max-age=0');
    header('X-Robots-Tag: ' . (session_area() === 'admin' ? 'noindex, nofollow' : 'index, follow'));
    header_remove('X-Powered-By');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. */
function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $sent)) {
        audit('system', null, 'csrf_rejected', substr($_SERVER['REQUEST_URI'] ?? '', 0, 200));
        abort_page(419, 'This page expired', 'For your safety the form could not be verified. Go back, refresh the page and try again.');
    }
}

function require_post(): void
{
    if (!is_post()) {
        header('Allow: POST');
        abort_page(405, 'Method not allowed', 'This address only accepts form submissions.');
    }
    csrf_verify();
}

// ---------------------------------------------------------------------------
// Passwords
// ---------------------------------------------------------------------------
function password_make(string $plain): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($plain, PASSWORD_ARGON2ID);
    }
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Constant-effort check. When the account does not exist we still hash against a dummy
 * value, so response time does not reveal whether an email is registered.
 */
function password_check(string $plain, ?string $hash): bool
{
    static $dummy = '$2y$12$fAq7eEmNbaRD45mMzUHYV.2As2QgbkpsOdxtxD/hR2Ecau95db3Em';
    if ($hash === null) {
        password_verify($plain, $dummy);
        return false;
    }
    return password_verify($plain, $hash);
}

function password_upgrade_needed(string $hash): bool
{
    return defined('PASSWORD_ARGON2ID')
        ? password_needs_rehash($hash, PASSWORD_ARGON2ID)
        : password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
}

/** @return string[] human readable problems; empty array means the password is acceptable */
function password_problems(string $pw, array $related = [], int $min = 10): array
{
    $errors = [];
    if (mb_strlen($pw) < $min) {
        $errors[] = "Use at least $min characters.";
    }
    if (strlen($pw) > 200) {
        $errors[] = 'Use at most 200 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        $errors[] = 'Include both letters and numbers.';
    }
    $lower = mb_strtolower($pw);
    foreach ($related as $r) {
        $r = mb_strtolower(trim((string) $r));
        if (mb_strlen($r) >= 4 && str_contains($lower, $r)) {
            $errors[] = 'Do not include your name or email in your password.';
            break;
        }
    }
    static $common = ['password', 'password1', 'password123', 'qwertyuiop', '1234567890', '12345678910',
        'iloveyou123', 'welcome123', 'admin12345', 'letmein1234', 'abc1234567', 'happyholidayhome'];
    if (in_array($lower, $common, true) || preg_match('/^(.)\1+$/u', $pw)) {
        $errors[] = 'That password is far too common.';
    }
    return $errors;
}

/** Email codes are stored as an HMAC bound to the address, never in readable form. */
function otp_hash(string $otp, string $email): string
{
    return hash_hmac('sha256', $otp . '|' . $email, app_key());
}

// ---------------------------------------------------------------------------
// Brute-force throttling
// ---------------------------------------------------------------------------
function throttle_ident(string $ident): string
{
    return hash_hmac('sha256', mb_strtolower($ident), app_key());
}

/** @return int seconds the caller must wait, or 0 when allowed */
function throttle_wait(string $scope, string $ident, int $max, int $windowSeconds): int
{
    if (random_int(1, 100) === 1) {
        db_run('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 1 DAY');
    }
    $h = throttle_ident($ident);
    $r = db_one(
        'SELECT COUNT(*) AS c, TIMESTAMPDIFF(SECOND, MIN(created_at), NOW()) AS age
           FROM login_attempts WHERE scope = ? AND ident = ? AND created_at > NOW() - INTERVAL ? SECOND',
        'ssi', [$scope, $h, $windowSeconds]
    );
    if ($r && (int) $r['c'] >= $max) {
        return max(1, $windowSeconds - (int) $r['age']);
    }
    // A single address spraying many accounts is limited too.
    $r = db_one(
        'SELECT COUNT(*) AS c, TIMESTAMPDIFF(SECOND, MIN(created_at), NOW()) AS age
           FROM login_attempts WHERE scope = ? AND ip = ? AND created_at > NOW() - INTERVAL ? SECOND',
        'ssi', [$scope, client_ip(), $windowSeconds]
    );
    if ($r && (int) $r['c'] >= $max * 4) {
        return max(1, $windowSeconds - (int) $r['age']);
    }
    return 0;
}

function throttle_hit(string $scope, string $ident): void
{
    db_run('INSERT INTO login_attempts (scope, ident, ip) VALUES (?, ?, ?)', 'sss',
        [$scope, throttle_ident($ident), client_ip()]);
}

function throttle_clear(string $scope, string $ident): void
{
    db_run('DELETE FROM login_attempts WHERE scope = ? AND ident = ?', 'ss', [$scope, throttle_ident($ident)]);
}

function wait_text(int $seconds): string
{
    $m = (int) ceil($seconds / 60);
    return $m <= 1 ? 'about a minute' : "$m minutes";
}

// ---------------------------------------------------------------------------
// Audit log
// ---------------------------------------------------------------------------
function audit(string $actorType, ?int $actorId, string $action, string $detail = ''): void
{
    try {
        db_run('INSERT INTO audit_log (actor_type, actor_id, action, detail, ip) VALUES (?, ?, ?, ?, ?)',
            'sisss', [$actorType, $actorId, substr($action, 0, 60), mb_substr($detail, 0, 255), client_ip()]);
    } catch (Throwable $e) {
        error_log('audit failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// reCAPTCHA (optional)
// ---------------------------------------------------------------------------
function captcha_enabled(): bool
{
    return config('recaptcha.site_key', '') !== '' && config('recaptcha.secret', '') !== '';
}

function captcha_widget(): string
{
    if (!captcha_enabled()) {
        return '';
    }
    return '<script src="https://www.google.com/recaptcha/api.js" async defer></script>'
         . '<div class="g-recaptcha" data-sitekey="' . e(config('recaptcha.site_key')) . '"></div>';
}

function captcha_passed(): bool
{
    if (!captcha_enabled()) {
        return true;
    }
    $token = $_POST['g-recaptcha-response'] ?? '';
    if (!is_string($token) || $token === '' || strlen($token) > 4096) {
        return false;
    }
    $body = http_build_query(['secret' => config('recaptcha.secret'), 'response' => $token, 'remoteip' => client_ip()]);
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 5,
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $body,
    ]]);
    $res = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    $json = $res ? json_decode($res, true) : null;
    return is_array($json) && ($json['success'] ?? false) === true;
}

// ---------------------------------------------------------------------------
// Visitor login state
// ---------------------------------------------------------------------------
function current_user_id(): ?int
{
    return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
}

function is_logged_in(): bool
{
    return current_user_id() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $rel  = base_path() !== '' && str_starts_with($uri, base_path()) ? substr($uri, strlen(base_path())) : $uri;
        $next = safe_next(ltrim($rel, '/'));
        redirect('users/login.php' . ($next ? '?next=' . rawurlencode($next) : ''));
    }
}

function user_sign_in(array $user): void
{
    session_rotate();
    $_SESSION['uid']   = (int) $user['user_id'];
    $_SESSION['uname'] = $user['name'];
    db_run('UPDATE users SET last_login_at = NOW() WHERE user_id = ?', 'i', [$user['user_id']]);
    audit('user', (int) $user['user_id'], 'login');
}

// ---------------------------------------------------------------------------
// Admin login state
// ---------------------------------------------------------------------------
function is_admin(): bool
{
    return isset($_SESSION['aid']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('admin/admin_login.php');
    }
}

function admin_sign_in(array $admin): void
{
    session_rotate();
    unset($_SESSION['pending_admin'], $_SESSION['pending_admin_at']);
    $_SESSION['aid']   = (int) $admin['admin_id'];
    $_SESSION['aname'] = $admin['name'];
    db_run('UPDATE admins SET last_login_at = NOW() WHERE admin_id = ?', 'i', [$admin['admin_id']]);
    audit('admin', (int) $admin['admin_id'], 'login');
}

// ---------------------------------------------------------------------------
// TOTP (RFC 6238) for the optional admin 2-step sign-in. Works with Google
// Authenticator, Microsoft Authenticator, Authy, 1Password, Aegis, etc.
// ---------------------------------------------------------------------------
function base32_encode(string $bin): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $ch) {
        $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode(string $text): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $text))) as $ch) {
        $bits .= str_pad(decbin(strpos($alphabet, $ch)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_new_secret(): string
{
    return base32_encode(random_bytes(20));
}

function totp_code(string $base32Secret, int $step): string
{
    $key  = base32_decode($base32Secret);
    $hash = hash_hmac('sha1', pack('J', $step), $key, true);
    $off  = ord($hash[19]) & 0x0F;
    $num  = ((ord($hash[$off]) & 0x7F) << 24) | (ord($hash[$off + 1]) << 16)
          | (ord($hash[$off + 2]) << 8) | ord($hash[$off + 3]);
    return str_pad((string) ($num % 1000000), 6, '0', STR_PAD_LEFT);
}

/** @return int|null the matched time step (store it to block replays) or null */
function totp_verify(string $base32Secret, string $code, ?int $lastUsedStep): ?int
{
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return null;
    }
    $now = (int) floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        $step = $now + $i;
        if ($lastUsedStep !== null && $step <= $lastUsedStep) {
            continue;
        }
        if (hash_equals(totp_code($base32Secret, $step), $code)) {
            return $step;
        }
    }
    return null;
}

function totp_uri(string $secret, string $account): string
{
    $issuer = (string) config('app_name', 'HappyHolidayHome');
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
         . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}
