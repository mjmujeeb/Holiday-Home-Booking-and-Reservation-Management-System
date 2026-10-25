<?php
require __DIR__ . '/inc/admin_init.php';

$aid   = (int) $_SESSION['aid'];
$admin = db_one('SELECT * FROM admins WHERE admin_id = ?', 'i', [$aid]);
if (!$admin) {
    session_destroy_completely();
    redirect('admin/admin_login.php');
}

$msgs = ['pw' => [], 'tfa' => []];
$ok   = ['pw' => '', 'tfa' => ''];

if (is_post()) {
    csrf_verify();
    $do  = post_str('do', 12);
    $cur = is_string($_POST['current'] ?? null) ? $_POST['current'] : '';

    if (($wait = throttle_wait('adminpw', 'a' . $aid, 5, 900)) > 0) {
        $msgs[$do === 'password' ? 'pw' : 'tfa'][] = 'Too many attempts. Try again in ' . wait_text($wait) . '.';
    } elseif (!password_check($cur, $admin['password_hash'])) {
        throttle_hit('adminpw', 'a' . $aid);
        $msgs[$do === 'password' ? 'pw' : 'tfa'][] = 'Your current password is not correct.';
    } elseif ($do === 'password') {
        $new = is_string($_POST['new'] ?? null) ? $_POST['new'] : '';
        $conf = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';
        $msgs['pw'] = password_problems($new, [$admin['name'], strstr($admin['email'], '@', true) ?: ''], 12);
        if (!$msgs['pw'] && hash_equals($cur, $new)) { $msgs['pw'][] = 'Choose a password different from the current one.'; }
        if (!$msgs['pw'] && !hash_equals($new, $conf)) { $msgs['pw'][] = 'The two new passwords are not the same.'; }
        if (!$msgs['pw']) {
            db_run('UPDATE admins SET password_hash = ? WHERE admin_id = ?', 'si', [password_make($new), $aid]);
            session_rotate();
            audit('admin', $aid, 'password_changed');
            $ok['pw'] = 'Password changed.';
        }
    } elseif ($do === 'start2fa') {
        $_SESSION['tfa_setup'] = totp_new_secret();
    } elseif ($do === 'confirm2fa') {
        $secret = $_SESSION['tfa_setup'] ?? '';
        $step = $secret ? totp_verify($secret, post_str('code', 10), null) : null;
        if ($step === null) {
            $msgs['tfa'][] = 'That code did not match. Check the time on your phone and try the newest code.';
        } else {
            db_run('UPDATE admins SET totp_secret = ?, totp_enabled = 1, totp_last_step = ? WHERE admin_id = ?',
                'sii', [encrypt_secret($secret), $step, $aid]);
            unset($_SESSION['tfa_setup']);
            audit('admin', $aid, '2fa_enabled');
            $ok['tfa'] = 'Two-step sign-in is now on.';
        }
    } elseif ($do === 'disable2fa') {
        db_run('UPDATE admins SET totp_secret = NULL, totp_enabled = 0, totp_last_step = NULL WHERE admin_id = ?', 'i', [$aid]);
        audit('admin', $aid, '2fa_disabled');
        $ok['tfa'] = 'Two-step sign-in is off.';
    }
    $admin = db_one('SELECT * FROM admins WHERE admin_id = ?', 'i', [$aid]);
}
$setupSecret = (!(int) $admin['totp_enabled']) ? ($_SESSION['tfa_setup'] ?? '') : '';

$pageTitle = 'Security';
$activeNav = 'profile';
include __DIR__ . '/inc/admin_header.php';
?>
<div class="two-col">
<section class="card pad">
    <h2>Account</h2>
    <dl class="detail-list">
        <div><dt>Name</dt><dd><?= e($admin['name']) ?></dd></div>
        <div><dt>Email</dt><dd><?= e($admin['email']) ?></dd></div>
        <div><dt>Last sign-in</dt><dd><?= $admin['last_login_at'] ? e(date('j M Y, H:i', strtotime($admin['last_login_at']))) : '—' ?></dd></div>
    </dl>
    <h2 class="mt">Change password</h2>
    <?php if ($ok['pw']) : ?><div class="alert alert-success"><?= e($ok['pw']) ?></div><?php endif; ?>
    <?php foreach ($msgs['pw'] as $m) : ?><div class="alert alert-error"><?= e($m) ?></div><?php endforeach; ?>
    <form method="post" action="" novalidate>
        <?= csrf_field() ?><input type="hidden" name="do" value="password">
        <div class="field"><label for="cur">Current password</label><input id="cur" name="current" type="password" maxlength="200" autocomplete="current-password" required></div>
        <div class="field"><label for="new">New password (12+ characters)</label><input id="new" name="new" type="password" maxlength="200" autocomplete="new-password" required></div>
        <div class="field"><label for="conf">Confirm new password</label><input id="conf" name="confirm" type="password" maxlength="200" autocomplete="new-password" required></div>
        <button class="btn btn-primary" type="submit">Update password</button>
    </form>
</section>

<section class="card pad">
    <h2>Two-step sign-in</h2>
    <?php if ($ok['tfa']) : ?><div class="alert alert-success"><?= e($ok['tfa']) ?></div><?php endif; ?>
    <?php foreach ($msgs['tfa'] as $m) : ?><div class="alert alert-error"><?= e($m) ?></div><?php endforeach; ?>

    <?php if ((int) $admin['totp_enabled']) : ?>
        <p><span class="badge badge-ok">On</span> A code from your authenticator app is required after your password.</p>
        <form method="post" action="" data-confirm="Turn off two-step sign-in? Your account will be less protected." novalidate>
            <?= csrf_field() ?><input type="hidden" name="do" value="disable2fa">
            <div class="field"><label for="cur2">Confirm with your password</label><input id="cur2" name="current" type="password" maxlength="200" autocomplete="current-password" required></div>
            <button class="btn btn-danger" type="submit">Turn off</button>
        </form>
    <?php elseif ($setupSecret) : ?>
        <p>1. In an authenticator app (Google Authenticator, Microsoft Authenticator, Authy, 1Password…) choose <em>Add account → Enter a setup key</em>.</p>
        <div class="secret-box">
            <span class="muted">Setup key</span>
            <code class="secret"><?= e(implode(' ', str_split($setupSecret, 4))) ?></code>
            <span class="muted">Account: <?= e($admin['email']) ?> · Time-based · 6 digits</span>
        </div>
        <p>2. Type the 6-digit code the app shows to finish.</p>
        <form method="post" action="" novalidate>
            <?= csrf_field() ?><input type="hidden" name="do" value="confirm2fa">
            <div class="field"><label for="cur3">Your password</label><input id="cur3" name="current" type="password" maxlength="200" autocomplete="current-password" required></div>
            <div class="field"><label for="code">Code from the app</label><input id="code" class="otp-input" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></div>
            <button class="btn btn-primary" type="submit">Turn on</button>
        </form>
    <?php else : ?>
        <p><span class="badge badge-off">Off</span> Add a second lock to this account. Even if your password leaks, nobody can sign in without your phone.</p>
        <form method="post" action="" novalidate>
            <?= csrf_field() ?><input type="hidden" name="do" value="start2fa">
            <div class="field"><label for="cur4">Confirm with your password</label><input id="cur4" name="current" type="password" maxlength="200" autocomplete="current-password" required></div>
            <button class="btn btn-primary" type="submit">Set up two-step sign-in</button>
        </form>
    <?php endif; ?>
    <p class="hint">Lost your phone? Run <code>php database_schema/create_admin.php disable-2fa you@example.com</code> on the server.</p>
</section>
</div>
<?php include __DIR__ . '/inc/admin_footer.php'; ?>
