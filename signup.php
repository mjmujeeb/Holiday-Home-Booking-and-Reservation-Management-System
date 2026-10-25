<?php
require dirname(__DIR__) . '/inc/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];

if (is_post()) {
    csrf_verify();
    $old['name']  = post_str('name', 80);
    $old['email'] = mb_strtolower(post_str('email', 190));
    $old['phone'] = preg_replace('/\D/', '', post_str('phone', 20)) ?? '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';

    if (!preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'\\-]{1,79}$/u", $old['name'])) {
        $errors['name'] = 'Please enter your full name (letters only).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) {
        $errors['email'] = 'That email address does not look right.';
    }
    if (!preg_match('/^\d{10}$/', $old['phone'])) {
        $errors['phone'] = 'Enter a 10-digit mobile number.';
    }
    $pwProblems = password_problems($password, [$old['name'], strstr($old['email'], '@', true) ?: '']);
    if ($pwProblems) {
        $errors['password'] = implode(' ', $pwProblems);
    } elseif (!hash_equals($password, $confirm)) {
        $errors['confirm'] = 'The two passwords are not the same.';
    }

    if (!$errors) {
        if (($wait = throttle_wait('signup', client_ip(), 6, 3600)) > 0) {
            $errors['form'] = 'Too many sign-up attempts from this connection. Please try again in ' . wait_text($wait) . '.';
        } else {
            throttle_hit('signup', client_ip());
            $taken = db_one('SELECT user_id FROM users WHERE email = ? OR phone = ?', 'ss', [$old['email'], $old['phone']]);

            if ($taken) {
                // Do not reveal that the account exists. The visitor sees the same next screen,
                // and the real owner gets an email explaining what happened.
                send_mail($old['email'], 'Someone tried to register with your email',
                    "Hello,\n\nSomeone just tried to create a " . config('app_name') . " account using this email address or phone number, "
                    . "but an account already exists.\n\nIf that was you, simply log in: " . (is_https() ? 'https' : 'http') . '://'
                    . ($_SERVER['HTTP_HOST'] ?? '') . url('users/login.php') . "\n\nIf it was not you, you can ignore this message. Your account is safe.\n");
                $_SESSION['pending_email'] = $old['email'];
                redirect('users/verify_otp.php');
            }

            $otp = (string) random_int(100000, 999999);
            db_run('DELETE FROM pending_signups WHERE email = ?', 's', [$old['email']]);
            db_run('INSERT INTO pending_signups (name, email, phone, password_hash, otp_hash, last_sent_at, expires_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW() + INTERVAL 10 MINUTE)',
                'sssss', [$old['name'], $old['email'], $old['phone'], password_make($password), otp_hash($otp, $old['email'])]);

            $sent = send_mail($old['email'], 'Your ' . config('app_name') . ' verification code',
                "Hello " . $old['name'] . ",\n\nYour verification code is: $otp\n\nIt expires in 10 minutes. "
                . "If you did not ask for it, ignore this email. Nobody from " . config('app_name') . " will ever ask you for this code.\n");
            if ($sent) {
                $_SESSION['pending_email'] = $old['email'];
                redirect('users/verify_otp.php');
            }
            db_run('DELETE FROM pending_signups WHERE email = ?', 's', [$old['email']]);
            $errors['form'] = 'We could not send the verification email. Please check the address and try again shortly.';
        }
    }
}


$pageTitle = 'Create an account';
$bodyClass = 'page-auth';
include HHH_ROOT . '/inc/header.php';
?>

<section class="auth auth-reverse">
    <div class="auth-media auth-media-tall">
        <img src="<?= e(url('assets/img/auth-signup.jpg')) ?>" alt="Close-up of green succulent rosettes" width="800" height="1422">
    </div>
    <div class="auth-panel">
        <div class="auth-inner">
            <p class="eyebrow">Join us</p>
            <h1>Create your account</h1>
            <?php if (isset($errors['form'])) : ?><div class="alert alert-error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>

            <form method="post" action="" class="form" novalidate>
                <?= csrf_field() ?>
                <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="<?= e($old['name']) ?>" maxlength="80" autocomplete="name" required>
                    <?php if (isset($errors['name'])) : ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>
                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= e($old['email']) ?>" maxlength="190" autocomplete="email" required>
                    <?php if (isset($errors['email'])) : ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
                </div>
                <div class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>">
                    <label for="phone">Mobile number</label>
                    <input id="phone" name="phone" type="tel" inputmode="numeric" value="<?= e($old['phone']) ?>" maxlength="10" pattern="[0-9]{10}" autocomplete="tel-national" placeholder="10 digits" required>
                    <?php if (isset($errors['phone'])) : ?><p class="field-error"><?= e($errors['phone']) ?></p><?php endif; ?>
                </div>
                <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" maxlength="200" autocomplete="new-password" required data-strength="#pw-meter">
                    <div id="pw-meter" class="pw-meter" aria-live="polite"><span class="pw-bar"></span><span class="pw-text">At least 10 characters, with letters and numbers.</span></div>
                    <?php if (isset($errors['password'])) : ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
                </div>
                <div class="field<?= isset($errors['confirm']) ? ' has-error' : '' ?>">
                    <label for="confirm">Confirm password</label>
                    <input id="confirm" name="confirm" type="password" maxlength="200" autocomplete="new-password" required>
                    <?php if (isset($errors['confirm'])) : ?><p class="field-error"><?= e($errors['confirm']) ?></p><?php endif; ?>
                </div>
                <button class="btn btn-block" type="submit">Send my verification code</button>
                <p class="hint">We will email a six-digit code to confirm the address is yours.</p>
            </form>

            <p class="auth-alt">Already registered? <a href="<?= e(url('users/login.php')) ?>">Log in</a></p>
        </div>
    </div>
</section>

<?php include HHH_ROOT . '/inc/footer.php'; ?>
