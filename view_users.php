<?php
require __DIR__ . '/inc/admin_init.php';

$users = db_all('SELECT u.user_id, u.name, u.email, u.phone, u.created_at, u.last_login_at,
                        (SELECT COUNT(*) FROM reservations r WHERE r.user_id = u.user_id) AS trips
                   FROM users u ORDER BY u.created_at DESC');   // password hashes are never selected

$pageTitle = 'Guests';
$activeNav = 'users';
include __DIR__ . '/inc/admin_header.php';
?>
<div class="toolbar"><p class="muted"><?= count($users) ?> registered guests. Accounts are created by guests themselves after email verification.</p></div>
<section class="card">
<?php if (!$users) : ?><p class="muted pad">No guests have registered yet.</p><?php else : ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Joined</th><th>Last sign-in</th><th class="num">Trips</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u) : ?>
            <tr><td class="mono"><?= (int) $u['user_id'] ?></td><td><strong><?= e($u['name']) ?></strong></td><td><?= e($u['email']) ?></td>
                <td><?= e($u['phone']) ?></td><td><?= e(date('j M Y', strtotime($u['created_at']))) ?></td>
                <td><?= $u['last_login_at'] ? e(date('j M Y, H:i', strtotime($u['last_login_at']))) : '<span class="muted">never</span>' ?></td>
                <td class="num"><?= (int) $u['trips'] ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
<?php endif; ?>
</section>
<?php include __DIR__ . '/inc/admin_footer.php'; ?>
