<?php
require __DIR__ . '/inc/admin_init.php';

$rows = db_all(
    'SELECT r.reservation_id, r.check_in_date, r.check_out_date, r.total_price, r.home_id,
            u.name AS guest, u.email, h.name AS home, h.price
       FROM reservations r JOIN users u ON u.user_id = r.user_id JOIN holiday_homes h ON h.home_id = r.home_id
      ORDER BY r.check_in_date DESC');

$pageTitle = 'Reservations';
$activeNav = 'reservations';
include __DIR__ . '/inc/admin_header.php';
?>
<div class="toolbar"><p class="muted"><?= count($rows) ?> reservations. Totals are recalculated from the nightly price whenever dates change.</p></div>
<section class="card">
<?php if (!$rows) : ?><p class="muted pad">No reservations yet.</p><?php else : ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Ref</th><th>Guest</th><th>Home</th><th>Check-in</th><th>Check-out</th><th class="num">Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r) : ?>
            <tr>
                <td class="mono">HHH-<?= str_pad((string) (int) $r['reservation_id'], 5, '0', STR_PAD_LEFT) ?></td>
                <td><strong><?= e($r['guest']) ?></strong><br><span class="muted"><?= e($r['email']) ?></span></td>
                <td><?= e($r['home']) ?></td>
                <td><?= e(pretty_date($r['check_in_date'])) ?></td>
                <td><?= e(pretty_date($r['check_out_date'])) ?></td>
                <td class="num"><?= e(money($r['total_price'], true)) ?></td>
                <td class="row-actions">
                    <button class="btn btn-sm" type="button" data-open="dlg-res-<?= (int) $r['reservation_id'] ?>">Change dates</button>
                    <form method="post" action="<?= e(url('admin/delete_reservation.php')) ?>" data-confirm="Delete this reservation? The guest will not be notified automatically.">
                        <?= csrf_field() ?><input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
<?php endif; ?>
</section>

<?php foreach ($rows as $r) : ?>
<dialog id="dlg-res-<?= (int) $r['reservation_id'] ?>" class="dialog dialog-sm">
    <form method="post" action="<?= e(url('admin/update_reservation.php')) ?>">
        <header><h2>Change dates</h2><button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('close', 18) ?></button></header>
        <div class="dialog-body">
            <?= csrf_field() ?><input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
            <p class="muted"><?= e($r['guest']) ?> at <?= e($r['home']) ?></p>
            <div class="field"><label for="in-<?= (int) $r['reservation_id'] ?>">Check-in</label>
                <input id="in-<?= (int) $r['reservation_id'] ?>" name="check_in" type="date" value="<?= e($r['check_in_date']) ?>" required></div>
            <div class="field"><label for="out-<?= (int) $r['reservation_id'] ?>">Check-out</label>
                <input id="out-<?= (int) $r['reservation_id'] ?>" name="check_out" type="date" value="<?= e($r['check_out_date']) ?>" required></div>
        </div>
        <footer><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save</button></footer>
    </form>
</dialog>
<?php endforeach; ?>
<?php include __DIR__ . '/inc/admin_footer.php'; ?>
