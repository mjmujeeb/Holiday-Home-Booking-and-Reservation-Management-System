<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_login();

$uid  = (int) current_user_id();
$user = db_one('SELECT user_id, name, email, phone, password_hash, created_at, last_login_at FROM users WHERE user_id = ?', 'i', [$uid]);
if (!$user) {
    session_destroy_completely();
    redirect('users/login.php');
}

$errors  = [];
$success = '';

if (is_post()) {
    csrf_verify();
    $current = is_string($_POST['current'] ?? null) ? $_POST['current'] : '';
    $new     = is_string($_POST['new'] ?? null) ? $_POST['new'] : '';
    $confirm = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';

    if (($wait = throttle_wait('pwchange', 'u' . $uid, 5, 900)) > 0) {
        $errors[] = 'Too many attempts. Please try again in ' . wait_text($wait) . '.';
    } elseif (!password_check($current, $user['password_hash'])) {
        throttle_hit('pwchange', 'u' . $uid);
        $errors[] = 'Your current password is not correct.';
    } else {
        $errors = password_problems($new, [$user['name'], strstr($user['email'], '@', true) ?: '']);
        if (!$errors && hash_equals($current, $new)) {
            $errors[] = 'Your new password must be different from the current one.';
        }
        if (!$errors && !hash_equals($new, $confirm)) {
            $errors[] = 'The two new passwords are not the same.';
        }
        if (!$errors) {
            db_run('UPDATE users SET password_hash = ? WHERE user_id = ?', 'si', [password_make($new), $uid]);
            throttle_clear('pwchange', 'u' . $uid);
            session_rotate();                      // fresh session id after a credential change
            audit('user', $uid, 'password_changed');
            $success = 'Your password has been changed.';
        }
    }
}

$pageTitle = 'Profile';
$bodyClass = 'page-profile';
include HHH_ROOT . '/inc/header.php';
?>

<section class="page-head">
    <div class="wrap">
        <p class="eyebrow">Account</p>
        <h1>Hello, <?= e(explode(' ', $user['name'])[0]) ?></h1>
    </div>
</section>

<section class="section-tight">
    <div class="wrap profile-grid">
        <div class="panel">
            <h2>Your details</h2>
            <dl class="detail-list">
                <div><dt>Name</dt><dd><?= e($user['name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
                <div><dt>Mobile</dt><dd><?= e($user['phone']) ?></dd></div>
                <div><dt>Member since</dt><dd><?= e(date('j F Y', strtotime($user['created_at']))) ?></dd></div>
                <?php if ($user['last_login_at']) : ?>
                    <div><dt>Last sign-in</dt><dd><?= e(date('j M Y, H:i', strtotime($user['last_login_at']))) ?></dd></div>
                <?php endif; ?>
            </dl>
            <p class="hint">To change your name, email or number, write to <a href="mailto:<?= e(config('contact.email')) ?>"><?= e(config('contact.email')) ?></a> so we can confirm it is really you.</p>
        </div>

        <div class="panel">
            <h2>Change password</h2>
            <?php if ($success) : ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
            <?php foreach ($errors as $err) : ?><div class="alert alert-error" role="alert"><?= e($err) ?></div><?php endforeach; ?>

            <form method="post" action="" class="form" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="current">Current password</label>
                    <input id="current" name="current" type="password" maxlength="200" autocomplete="current-password" required>
                </div>
                <div class="field">
                    <label for="new">New password</label>
                    <input id="new" name="new" type="password" maxlength="200" autocomplete="new-password" required data-strength="#pw-meter">
                    <div id="pw-meter" class="pw-meter" aria-live="polite"><span class="pw-bar"></span><span class="pw-text">At least 10 characters, with letters and numbers.</span></div>
                </div>
                <div class="field">
                    <label for="confirm">Confirm new password</label>
                    <input id="confirm" name="confirm" type="password" maxlength="200" autocomplete="new-password" required>
                </div>
                <button class="btn" type="submit">Update password</button>
            </form>
        </div>
    </div>
</section>

<?php include HHH_ROOT . '/inc/footer.php'; ?>
