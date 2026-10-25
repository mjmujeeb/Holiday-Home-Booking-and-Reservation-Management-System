<?php
/* Site header. A page sets $pageTitle (and optionally $bodyClass, $metaDescription) before including this. */
$pageTitle       = $pageTitle ?? config('app_name');
$bodyClass       = $bodyClass ?? '';
$metaDescription = $metaDescription ?? 'Hand-picked holiday homes with honest photos and straightforward booking.';
$fullTitle       = $pageTitle === config('app_name') ? $pageTitle : $pageTitle . ' · ' . config('app_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="theme-color" content="#1f4a3b">
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/main.css')) ?>">
    <script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="wrap header-row">
        <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="<?= e(config('app_name')) ?> home">
            <?= brand_mark() ?>
            <span class="brand-word">Happy<em>Holiday</em>Home</span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Menu">
            <?= icon('menu', 22) ?>
        </button>

        <nav id="site-nav" class="site-nav" aria-label="Main">
            <ul>
                <li><a href="<?= e(url('index.php')) ?>"<?= nav_active(['index.php']) ?>>Home</a></li>
                <li><a href="<?= e(url('users/search.php')) ?>"<?= nav_active(['search.php']) ?>>Stays</a></li>
                <li><a href="<?= e(url('users/about.php')) ?>"<?= nav_active(['about.php']) ?>>About</a></li>
                <li><a href="<?= e(url('users/contact.php')) ?>"<?= nav_active(['contact.php']) ?>>Contact</a></li>
            </ul>
            <div class="nav-account">
                <?php if (is_logged_in()) : ?>
                    <details class="account-menu">
                        <summary><?= icon('user', 18) ?><span><?= e(explode(' ', (string) $_SESSION['uname'])[0]) ?></span></summary>
                        <div class="account-pop">
                            <a href="<?= e(url('users/reservations.php')) ?>">My reservations</a>
                            <a href="<?= e(url('users/profile.php')) ?>">Profile &amp; password</a>
                            <form method="post" action="<?= e(url('users/logout.php')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit">Sign out</button>
                            </form>
                        </div>
                    </details>
                <?php else : ?>
                    <a class="link-quiet" href="<?= e(url('users/login.php')) ?>">Log in</a>
                    <a class="btn btn-sm" href="<?= e(url('users/signup.php')) ?>">Create account</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<main id="main">
