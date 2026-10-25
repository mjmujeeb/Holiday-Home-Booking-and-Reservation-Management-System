<?php
require __DIR__ . '/inc/admin_init.php';

$s = db_one("SELECT
    (SELECT COUNT(*) FROM users) AS users,
    (SELECT COUNT(*) FROM holiday_homes) AS homes,
    (SELECT COUNT(*) FROM holiday_homes WHERE status = 'available') AS listed,
    (SELECT COUNT(*) FROM reservations) AS reservations,
    (SELECT COUNT(*) FROM reservations WHERE check_in_date >= CURDATE()) AS upcoming,
    (SELECT COALESCE(SUM(total_price), 0) FROM reservations WHERE check_in_date >= CURDATE()) AS booked_value");

$recent = db_all(
    'SELECT r.reservation_id, r.check_in_date, r.check_out_date, r.total_price, u.name AS guest, h.name AS home
       FROM reservations r JOIN users u ON u.user_id = r.user_id JOIN holiday_homes h ON h.home_id = r.home_id
      ORDER BY r.created_at DESC, r.reservation_id DESC LIMIT 6');
$events = db_all('SELECT actor_type, action, detail, created_at FROM audit_log ORDER BY id DESC LIMIT 6');
$admin  = db_one('SELECT totp_enabled FROM admins WHERE admin_id = ?', 'i', [(int) $_SESSION['aid']]);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/inc/admin_header.php';
?>
<?php if (!$admin || !(int) $admin['totp_enabled']) : ?>
    <div class="alert alert-info">Your account is not protected by two-step sign-in yet. <a href="<?= e(url('admin/admin_profile.php')) ?>">Turn it on</a>.</div>
<?php endif; ?>

<p class="greeting">Good <?= (int) date('G') < 12 ? 'morning' : ((int) date('G') < 18 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', (string) $_SESSION['aname'])[0]) ?>.</p>

<div class="stats">
    <div class="stat"><span>Guests</span><strong><?= (int) $s['users'] ?></strong></div>
    <div class="stat"><span>Homes listed</span><strong><?= (int) $s['listed'] ?><small> / <?= (int) $s['homes'] ?></small></strong></div>
    <div class="stat"><span>Upcoming stays</span><strong><?= (int) $s['upcoming'] ?><small> of <?= (int) $s['reservations'] ?></small></strong></div>
    <div class="stat"><span>Upcoming value</span><strong><?= e(money($s['booked_value'])) ?></strong></div>
</div>

<div class="two-col">
    <section class="card">
        <div class="card-head"><h2>Latest reservations</h2><a href="<?= e(url('admin/view_reservations.php')) ?>">View all</a></div>
        <?php if (!$recent) : ?><p class="muted pad">Nothing booked yet.</p><?php else : ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Guest</th><th>Home</th><th>Stay</th><th class="num">Total</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r) : ?>
                <tr><td><?= e($r['guest']) ?></td><td><?= e($r['home']) ?></td>
                    <td><?= e(pretty_date($r['check_in_date'])) ?> → <?= e(pretty_date($r['check_out_date'])) ?></td>
                    <td class="num"><?= e(money($r['total_price'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
    <section class="card">
        <div class="card-head"><h2>Recent activity</h2><a href="<?= e(url('admin/audit_log.php')) ?>">Full log</a></div>
        <ul class="feed">
            <?php foreach ($events as $ev) : ?>
                <li><span class="tag tag-<?= e($ev['actor_type']) ?>"><?= e($ev['actor_type']) ?></span>
                    <span><?= e(str_replace('_', ' ', $ev['action'])) ?><?php if ($ev['detail'] !== '') : ?> <em class="muted"><?= e(excerpt($ev['detail'], 50)) ?></em><?php endif; ?></span>
                    <time><?= e(date('j M, H:i', strtotime($ev['created_at']))) ?></time></li>
            <?php endforeach; ?>
            <?php if (!$events) : ?><li class="muted">No activity yet.</li><?php endif; ?>
        </ul>
    </section>
</div>
<?php include __DIR__ . '/inc/admin_footer.php'; ?>
