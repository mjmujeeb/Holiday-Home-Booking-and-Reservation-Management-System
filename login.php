<?php
define('HHH_CAPTCHA', true);
require dirname(__DIR__) . '/inc/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$next  = safe_next($_POST['next'] ?? $_GET['next'] ?? null);
$error = '';
$email = '';

if (is_post()) {
    csrf_verify();
    $email    = mb_strtolower(post_str('email', 190));
    $password = is_string($_POST['password'] ?? null) ? substr($_POST['password'], 0, 200) : '';

    if (($wait = throttle_wait('login', $email, 5, 900)) > 0) {
        $error = 'Too many unsuccessful attempts. Please try again in ' . wait_text($wait) . '.';
    } elseif (!captcha_passed()) {
        $error = 'Please tick the “I’m not a robot” box and try again.';
    } else {
        $user = $email !== '' ? db_one('SELECT user_id, name, password_hash FROM users WHERE email = ?', 's', [$email]) : null;
        if (password_check($password, $user['password_hash'] ?? null)) {
            throttle_clear('login', $email);
            if (password_upgrade_needed($user['password_hash'])) {
                db_run('UPDATE users SET password_hash = ? WHERE user_id = ?', 'si', [password_make($password), $user['user_id']]);
            }
            user_sign_in($user);
            redirect($next ?: 'index.php');
        }
        throttle_hit('login', $email);
        audit('system', null, 'login_failed', 'user form');
        // One message for both "no such email" and "wrong password".
        $error = 'That email and password do not match.';
    }
}

$pageTitle = 'Log in';
$bodyClass = 'page-auth';
include HHH_ROOT . '/inc/header.php';
?>

<section class="auth">
    <div class="auth-media">
        <img src="<?= e(url('assets/img/auth-login.jpg')) ?>" alt="A villa and its pool glowing at dusk" width="1000" height="667">
    </div>
    <div class="auth-panel">
        <div class="auth-inner">
            <p class="eyebrow">Welcome back</p>
            <h1>Log in</h1>
            <?php render_flashes(); ?>
            <?php if ($error) : ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="" class="form" novalidate>
                <?= csrf_field() ?>
                <?php if ($next) : ?><input type="hidden" name="next" value="<?= e($next) ?>"><?php endif; ?>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= e($email) ?>" maxlength="190" autocomplete="username" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" maxlength="200" autocomplete="current-password" required>
                </div>
                <?= captcha_widget() ?>
                <button class="btn btn-block" type="submit">Log in</button>
            </form>

            <p class="auth-alt">New to <?= e(config('app_name')) ?>? <a href="<?= e(url('users/signup.php')) ?>">Create an account</a></p>
        </div>
    </div>
</section>

<?php include HHH_ROOT . '/inc/footer.php'; ?>
