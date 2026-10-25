<?php
$items = [
    'dashboard'    => ['admin_dashboard.php',   'Dashboard',    'home'],
    'homes'        => ['view_holidayhomes.php', 'Holiday homes', 'sun'],
    'reservations' => ['view_reservations.php', 'Reservations', 'calendar'],
    'users'        => ['view_users.php',        'Guests',       'users'],
    'audit'        => ['audit_log.php',         'Audit log',    'list'],
    'profile'      => ['admin_profile.php',     'Security',     'shield'],
];
?>
<aside class="sidebar" id="sidebar">
    <a class="side-brand" href="<?= e(url('admin/admin_dashboard.php')) ?>">
        <?= brand_mark(28) ?>
        <span>Happy<em>Holiday</em>Home<small>Admin</small></span>
    </a>
    <nav aria-label="Admin">
        <?php foreach ($items as $key => [$file, $label, $ico]) : ?>
            <a href="<?= e(url('admin/' . $file)) ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>><?= icon($ico, 18) ?> <?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="side-foot">
        <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">View public site</a>
        <form method="post" action="<?= e(url('admin/admin_logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit"><?= icon('logout', 16) ?> Sign out</button>
        </form>
    </div>
</aside>
