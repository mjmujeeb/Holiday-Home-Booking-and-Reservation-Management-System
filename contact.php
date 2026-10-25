<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
$pageTitle = 'Contact';
$bodyClass = 'page-contact';
include HHH_ROOT . '/inc/header.php';
?>

<section class="page-head">
    <div class="wrap">
        <p class="eyebrow">Contact</p>
        <h1>We would love to hear from you.</h1>
    </div>
</section>

<section class="section-tight">
    <div class="wrap contact-grid">
        <div class="prose prose-lg">
            <p>Questions about a home, a booking or your account? Write or call and a real person will reply.</p>
            <p>If you are writing about an existing booking, please include its reference (it looks like <strong>HHH-00012</strong> and is shown on My reservations).</p>
        </div>
        <dl class="contact-card">
            <div><dt>Email</dt><dd><a href="mailto:<?= e(config('contact.email')) ?>"><?= e(config('contact.email')) ?></a></dd></div>
            <div><dt>Phone</dt><dd><?= e(config('contact.phone')) ?></dd></div>
            <div><dt>Address</dt><dd><?= e(config('contact.address')) ?></dd></div>
        </dl>
    </div>
    <div class="wrap">
        <div class="notice-box">
            <?= icon('shield', 22) ?>
            <p><strong>Stay safe.</strong> <?= e(config('app_name')) ?> will never ask for your password or an email verification code, whether by email, phone or message. If someone does, it is not us.</p>
        </div>
    </div>
</section>

<?php include HHH_ROOT . '/inc/footer.php'; ?>
