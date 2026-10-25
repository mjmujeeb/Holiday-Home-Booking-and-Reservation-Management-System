<?php
$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';
$bare      = $bare ?? false;   // login pages have no sidebar
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> · Admin · <?= e(config('app_name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
    <script src="<?= e(asset('admin/assets/js/theme.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset('admin/assets/css/admin.css')) ?>">
    <script src="<?= e(asset('admin/assets/js/admin.js')) ?>" defer></script>
</head>
<body class="<?= $bare ? 'admin-bare' : 'admin-app' ?>">
<?php if (!$bare) : ?>
<div class="shell">
    <?php include __DIR__ . '/admin_side_bar.php'; ?>
    <div class="shell-main">
        <header class="topbar">
            <button class="icon-btn side-toggle" type="button" aria-label="Menu" aria-expanded="false"><?= icon('menu', 20) ?></button>
            <h1 class="topbar-title"><?= e($pageTitle) ?></h1>
            <div class="topbar-right">
                <button class="icon-btn" type="button" data-theme-toggle aria-label="Switch light or dark theme"><?= icon('moon', 18) ?></button>
                <span class="whoami"><?= icon('user', 16) ?> <?= e($_SESSION['aname'] ?? '') ?></span>
            </div>
        </header>
        <main class="content">
            <?php render_flashes(); ?>
<?php endif; ?>
