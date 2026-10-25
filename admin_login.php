<?php
define('HHH_PUBLIC_ADMIN', true);
define('HHH_ADMIN_CAPTCHA', true);
require __DIR__ . '/inc/admin_init.php';

if (is_admin()) {
    redirect('admin/admin_dashboard.php');
}

$error = '';
$email = '';

if (is_post()) {
    csrf_verify();
    $email    = mb_strtolower(post_str('email', 190));
    $password = is_string($_POST['password'] ?? null) ? substr($_POST['password'], 0, 200) : '';

    if (($wait = throttle_wait('admin', $email, 5, 900)) > 0) {
        $error = 'Too many unsuccessful attempts. Try again in ' . wait_text($wait) . '.';
    } elseif (!captcha_passed()) {
        $error = 'Please complete the CAPTCHA and try again.';
    } else {
        $admin = $email !== '' ? db_one('SELECT * FROM admins WHERE email = ?', 's', [$email]) : null;
        if (password_check($password, $admin['password_hash'] ?? null)) {
            throttle_clear('admin', $email);
            if (password_upgrade_needed($admin['password_hash'])) {
                db_run('UPDATE admins SET password_hash = ? WHERE admin_id = ?', 'si', [password_make($password), $admin['admin_id']]);
            }
            if ((int) $admin['totp_enabled'] === 1) {
                // Password is right, second step still required. Nothing privileged is granted yet.
                session_rotate();
                $_SESSION['pending_admin']    = (int) $admin['admin_id'];
                $_SESSION['pending_admin_at'] = time();
                redirect('admin/admin_2fa.php');
            }
            admin_sign_in($admin);
            redirect('admin/admin_dashboard.php');
        }
        throttle_hit('admin', $email);
        audit('system', null, 'admin_login_failed', 'admin form');
        $error = 'That email and password do not match.';
    }
}

$pageTitle = 'Sign in';
$bare = true;
include __DIR__ . '/inc/admin_header.php';
?>
<main class="login-card">
    <div class="login-brand"><?= brand_mark(40) ?><p>Happy<em>Holiday</em>Home</p></div>
    <h1>Staff sign-in</h1>
    <?php render_flashes(); ?>
    <?php if ($error) : ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= e($email) ?>" maxlength="190" autocomplete="username" required autofocus></div>
        <div class="field"><label for="password">Password</label>
            <input id="password" name="password" type="password" maxlength="200" autocomplete="current-password" required></div>
        <?= captcha_widget() ?>
        <button class="btn btn-primary btn-block" type="submit">Continue</button>
    </form>
    <p class="login-note">Authorised staff only. Sign-in attempts are logged.</p>
</main>
<?php include __DIR__ . '/inc/admin_footer.php'; ?>
