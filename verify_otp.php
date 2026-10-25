<?php
require dirname(__DIR__) . '/inc/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}
$email = $_SESSION['pending_email'] ?? '';
if (!is_string($email) || $email === '') {
    redirect('users/signup.php');
}


$error = '';
$info  = '';

if (is_post()) {
    csrf_verify();
    $action = post_str('action', 10);

    if ($action === 'resend') {
        $p = db_one('SELECT * FROM pending_signups WHERE email = ? AND expires_at > NOW()', 's', [$email]);
        if (!$p) {
            $info = 'If an account can be created for that address, a fresh code is on its way.';
        } elseif ((int) $p['resends'] >= 3) {
            $error = 'You have used all your resends. Please start again.';
        } elseif (time() - strtotime($p['last_sent_at']) < 60) {
            $error = 'Please wait a minute before asking for another code.';
        } else {
            $otp = (string) random_int(100000, 999999);
            db_run('UPDATE pending_signups SET otp_hash = ?, attempts = 0, resends = resends + 1,
                           last_sent_at = NOW(), expires_at = NOW() + INTERVAL 10 MINUTE WHERE pending_id = ?',
                'si', [otp_hash($otp, $email), $p['pending_id']]);
            send_mail($email, 'Your ' . config('app_name') . ' verification code',
                "Your new verification code is: $otp\n\nIt expires in 10 minutes.\n");
            $info = 'A new code has been sent.';
        }
    } else {
        $code = preg_replace('/\D/', '', post_str('otp', 12)) ?? '';

        if (($wait = throttle_wait('otp', $email, 8, 900)) > 0) {
            $error = 'Too many attempts. Please try again in ' . wait_text($wait) . '.';
        } else {
            $p = db_one('SELECT * FROM pending_signups WHERE email = ? AND expires_at > NOW()', 's', [$email]);
            $good = $p && (int) $p['attempts'] < 5 && strlen($code) === 6
                 && hash_equals($p['otp_hash'], otp_hash($code, $email));

            if ($good) {
                try {
                    $newId = db_transaction(function () use ($p) {
                        db_run('INSERT INTO users (name, email, phone, password_hash) VALUES (?, ?, ?, ?)',
                            'ssss', [$p['name'], $p['email'], $p['phone'], $p['password_hash']]);
                        $id = db_last_id();
                        db_run('DELETE FROM pending_signups WHERE pending_id = ?', 'i', [$p['pending_id']]);
                        return $id;
                    });
                    throttle_clear('otp', $email);
                    audit('user', $newId, 'account_created');
                    unset($_SESSION['pending_email']);
                    flash('success', 'Your email is verified and your account is ready. Please log in.');
                    redirect('users/login.php');
                } catch (mysqli_sql_exception $ex) {
                    if ($ex->getCode() !== 1062) {
                        throw $ex;
                    }
                    unset($_SESSION['pending_email']);
                    flash('info', 'An account with those details already exists. Please log in.');
                    redirect('users/login.php');
                }
            }
            throttle_hit('otp', $email);
            if ($p) {
                db_run('UPDATE pending_signups SET attempts = attempts + 1 WHERE pending_id = ?', 'i', [$p['pending_id']]);
                if ((int) $p['attempts'] + 1 >= 5) {
                    db_run('DELETE FROM pending_signups WHERE pending_id = ?', 'i', [$p['pending_id']]);
                    unset($_SESSION['pending_email']);
                    flash('error', 'Too many wrong codes, so we cancelled this sign-up. Please start again.');
                    redirect('users/signup.php');
                }
            }
            $error = 'That code is not right, or it has expired.';
        }
    }
}

// j***@gmail.com
[$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
$masked = mb_substr($local, 0, 1) . str_repeat('•', max(2, min(6, mb_strlen($local) - 1))) . '@' . $domain;

$pageTitle = 'Verify your email';
$bodyClass = 'page-auth';
include HHH_ROOT . '/inc/header.php';
?>

<section class="auth">
    <div class="auth-media">
        <img src="<?= e(url('assets/img/auth-login.jpg')) ?>" alt="A villa and its pool glowing at dusk" width="1000" height="667">
    </div>
    <div class="auth-panel">
        <div class="auth-inner">
            <p class="eyebrow">One last step</p>
            <h1>Check your email</h1>
            <p class="lede-sm">We sent a six-digit code to <strong><?= e($masked) ?></strong>. It stays valid for ten minutes.</p>

            <?php if ($error) : ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <?php if ($info) : ?><div class="alert alert-info" role="status"><?= e($info) ?></div><?php endif; ?>
            <?php if (config('env') === 'development') : ?>
                <div class="alert alert-info">Development mode: no email was sent. Your code is in <code>storage/logs/mail.log</code>.</div>
            <?php endif; ?>

            <form method="post" action="" class="form" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="otp">Verification code</label>
                    <input id="otp" class="otp-input" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                           autocomplete="one-time-code" placeholder="••••••" required autofocus>
                </div>
                <button class="btn btn-block" type="submit">Verify and create account</button>
            </form>

            <form method="post" action="" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resend">
                <button class="link-button" type="submit">Send me a new code</button>
                <span class="sep">·</span>
                <a href="<?= e(url('users/signup.php')) ?>">Use a different email</a>
            </form>
        </div>
    </div>
</section>

<?php include HHH_ROOT . '/inc/footer.php'; ?>
