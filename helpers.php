<?php
/* View and request helpers. Nothing in here touches the database. */

function config(string $path, mixed $default = null): mixed
{
    $value = $GLOBALS['hhh_config'] ?? [];
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escape for HTML output. Use on EVERYTHING that came from a user or the database. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/** The connecting address. Forwarded headers are NOT trusted because anyone can forge them. */
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = config('base_path');
    if (is_string($configured)) {
        return $base = rtrim($configured, '/');
    }
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $root    = realpath(HHH_ROOT) ?: HHH_ROOT;
    if ($docRoot !== '' && str_starts_with($root, $docRoot)) {
        $rel = str_replace('\\', '/', substr($root, strlen($docRoot)));
        return $base = rtrim($rel, '/');
    }
    return $base = '';
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** URL of a static file with a cache-busting version. */
function asset(string $path): string
{
    $file = HHH_ROOT . '/' . ltrim($path, '/');
    $v    = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return url($path) . '?v=' . $v;
}

function redirect(string $path, int $code = 303): never
{
    header('Location: ' . url($path), true, $code);
    exit;
}

/** Only allow "next" targets that point to one of our own pages. Blocks open redirects. */
function safe_next(mixed $next): ?string
{
    if (!is_string($next) || $next === '' || strlen($next) > 300) {
        return null;
    }
    if (preg_match('~^(index\.php|users/[a-z_]+\.php)(\?[A-Za-z0-9_=&%.\-]{0,240})?$~', $next)) {
        return $next;
    }
    return null;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Read a trimmed single-line string from POST. Control characters are removed. */
function post_str(string $key, int $max = 255): string
{
    $v = $_POST[$key] ?? '';
    return clean_str(is_string($v) ? $v : '', $max);
}

function get_str(string $key, int $max = 255): string
{
    $v = $_GET[$key] ?? '';
    return clean_str(is_string($v) ? $v : '', $max);
}

function clean_str(string $v, int $max = 255): string
{
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    $v = trim($v);
    return mb_substr($v, 0, $max);
}

/** Multi-line text (descriptions). Keeps newlines. */
function post_text(string $key, int $max = 3000): string
{
    $v = $_POST[$key] ?? '';
    $v = is_string($v) ? $v : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
}

function post_int(string $key): int
{
    $v = $_POST[$key] ?? '';
    return is_string($v) && ctype_digit($v) ? (int) $v : 0;
}

function get_int(string $key): int
{
    $v = $_GET[$key] ?? '';
    return is_string($v) && ctype_digit($v) ? (int) $v : 0;
}

// ---- dates and money -------------------------------------------------------

function today(): DateTimeImmutable
{
    return new DateTimeImmutable('today');
}

/** Strict Y-m-d parser. Returns null for anything else, including 2024-02-31. */
function parse_date(string $value): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$d || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
        return null;
    }
    return $d;
}

function nights_between(DateTimeImmutable $in, DateTimeImmutable $out): int
{
    return (int) $in->diff($out)->format('%r%a');
}

function money(float|int|string $amount, bool $decimals = false): string
{
    return config('currency', '₹') . number_format((float) $amount, $decimals ? 2 : 0);
}

function pretty_date(string $ymd): string
{
    $d = parse_date($ymd);
    return $d ? $d->format('j M Y') : $ymd;
}

function excerpt(string $text, int $len = 140): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if (mb_strlen($text) <= $len) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $len - 1), " ,.;:") . '…';
}

// ---- images ----------------------------------------------------------------

function home_image_url(string $file): string
{
    // Only a bare file name is ever stored, so no path tricks can come out of the database.
    $file = basename($file);
    return url('assets/img/holiday-homes/' . rawurlencode($file));
}

// ---- icons (inline SVG, so no icon font or CDN is needed) ------------------

function icon(string $name, int $size = 18): string
{
    static $paths = [
        'pin'      => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 1 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
        'star'     => '<path d="m12 3.5 2.6 5.4 5.9.8-4.3 4.1 1 5.9L12 16.9 6.8 19.7l1-5.9L3.5 9.7l5.9-.8z"/>',
        'arrow'    => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="1.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'moon'     => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
        'user'     => '<circle cx="12" cy="8" r="3.7"/><path d="M4.5 20c.9-3.8 3.7-5.5 7.5-5.5s6.6 1.7 7.5 5.5"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'    => '<path d="m6 6 12 12M18 6 6 18"/>',
        'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'alert'    => '<path d="M12 4 3 19.5h18zM12 10v4.5M12 17.2v.1"/>',
        'lock'     => '<rect x="5" y="10.5" width="14" height="9.5" rx="1.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/>',
        'home'     => '<path d="M4 11 12 4l8 7v8.5H4z"/><path d="M10 19.5v-5h4v5"/>',
        'users'    => '<circle cx="9" cy="8.5" r="3.2"/><path d="M3 19.5c.7-3.2 3-4.7 6-4.7s5.300 1.500 6 4.700M16 5.600a3.100 3.100 0 0 1 0 5.800M17.500 15c2 .5 3.200 2 3.500 4.500"/>',
        'shield'   => '<path d="M12 3.500 5 6v5.500c0 4.300 3 7.300 7 9 4-1.700 7-4.700 7-9V6z"/><path d="m9 12 2.200 2.200L15 10"/>',
        'list'     => '<path d="M8 6.500h12M8 12h12M8 17.500h12M4 6.500h.01M4 12h.01M4 17.500h.01"/>',
        'logout'   => '<path d="M14 4.500h4.500a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H14M10 8l-4 4 4 4M6 12h10"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" '
         . 'aria-hidden="true" focusable="false">' . $p . '</svg>';
}

function rating_html(float|string $rating): string
{
    return '<span class="rating" title="Rated ' . e(number_format((float) $rating, 1)) . ' out of 5">'
         . icon('star', 15) . '<span>' . e(number_format((float) $rating, 1)) . '</span></span>';
}

// ---- flash messages --------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $message];
}

function render_flashes(): void
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    foreach ($items as $f) {
        $type = in_array($f['type'], ['success', 'error', 'info'], true) ? $f['type'] : 'info';
        echo '<div class="alert alert-' . $type . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">'
           . e($f['msg']) . '</div>';
    }
}

/** Friendly error page for 403 / 404 / 405 / 419 / 429 / 500. */
function abort_page(int $code, string $title, string $message): never
{
    http_response_code($code);
    $pageTitle = $title;
    $bodyClass = 'page-error';
    $errorCode = $code;
    $errorTitle = $title;
    $errorMessage = $message;
    if (defined('HHH_AREA') && HHH_AREA === 'admin') {
        echo '<!doctype html><meta charset="utf-8"><title>' . e($title) . '</title>'
           . '<link rel="stylesheet" href="' . e(asset('admin/assets/css/admin.css')) . '">'
           . '<body class="admin-error"><main><p class="code">' . (int) $code . '</p><h1>' . e($title) . '</h1><p>'
           . e($message) . '</p><p><a class="btn" href="' . e(url('admin/admin_dashboard.php')) . '">Back to dashboard</a></p></main>';
        exit;
    }
    include HHH_ROOT . '/inc/header.php';
    include HHH_ROOT . '/inc/error_body.php';
    include HHH_ROOT . '/inc/footer.php';
    exit;
}

// ---- layout helpers --------------------------------------------------------

function nav_active(array $files): string
{
    return in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), $files, true) ? ' aria-current="page"' : '';
}

function brand_mark(int $size = 34): string
{
    return '<svg class="brand-mark" width="' . $size . '" height="' . $size . '" viewBox="0 0 40 40" fill="none" aria-hidden="true">'
         . '<circle cx="26" cy="13" r="6.5" class="bm-sun"/>'
         . '<path d="M4.5 21 20 8l15.5 13" class="bm-line"/>'
         . '<path d="M9 18.500V34h22V18.500" class="bm-line"/>'
         . '<path d="M17 34v-8.500h6V34" class="bm-line"/></svg>';
}
