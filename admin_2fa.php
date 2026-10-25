<?php
define('HHH_PUBLIC_ADMIN', true);
require __DIR__ . '/inc/admin_init.php';

$aid = $_SESSION['pending_admin'] ?? null;
// The password step must have happened in the last 5 minutes.
if (!$aid || time() - (int) ($_SESSION['pending_admin_at'] ?? 0) > 300) {
    unset($_SESSION['pending_admin'], $_SESSION['pending_admin_at']);
    redirect('admin/admin_login.php');
}
$admin = db_one('SELECT * FROM admins WHERE admin_id = ? AND totp_enabled = 1', 'i', [(int) $aid]);
if (!$admin) {
    redirect('admin/admin_login.php');
}

$error = '';
if (is_post()) {
    csrf_verify();
    $ident = 'a' . (int) $aid;
    if (($wait = throttle_wait('admin2fa', $ident, 5, 600)) > 0) {
        $error = 'Too many wrong codes. Try again in ' . wait_text($wait) . '.';
    } else {
        $secret = decrypt_secret((string) $admin['totp_secret']);
        $step   = $secret ? totp_verify($secret, post_str('code', 10), $admin['totp_last_step'] !== null ? (int) $admin['totp_last_step'] : null) : null;
        if ($step !== null) {
            db_run('UPDATE admins SET totp_last_step = ? WHERE admin_id = ?', 'ii', [$step, $aid]);   // a code works once
            throttle_clear('admin2fa', $ident);
            admin_sign_in($admin);
            redirect('admin/admin_dashboard.php');
        }
        throttle_hit('admin2fa', $ident);
        audit('system', (int) $aid, 'admin_2fa_failed');
        $error = 'That code is not correct.';
    }
}

$pageTitle = 'Two-step check';
$bare = true;
include __DIR__ . '/inc/admin_header.php';
?>
<main class="login-card">
    <div class="login-brand"><?= brand_mark(40) ?><p>Happy<em>Holiday</em>Home</p></div>
    <h1>Enter your code</h1>
    <p class="muted">Open your authenticator app and type the 6-digit code for <?= e(config('app_name')) ?>.</p>
    <?php if ($error) : ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label for="code">6-digit code</label>
            <input id="code" class="otp-input" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus></div>
        <button class="btn btn-primary btn-block" type="submit">Verify</button>
    </form>
    <p class="login-note"><a href="<?= e(url('admin/admin_login.php')) ?>">Start over</a></p>
</main>
<?php include __DIR__ . '/inc/admin_footer.php'; ?>
