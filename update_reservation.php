<?php
require __DIR__ . '/inc/admin_init.php';
admin_post_only();

$id = post_int('reservation_id');
$in = parse_date(post_str('check_in', 10));
$out = parse_date(post_str('check_out', 10));

if (!$id || !$in || !$out || $out <= $in || nights_between($in, $out) > 60) {
    flash('error', 'Enter valid dates: check-out after check-in, 60 nights at most.');
    redirect('admin/view_reservations.php');
}

try {
    db_transaction(function () use ($id, $in, $out) {
        $r = db_one('SELECT r.home_id, h.price FROM reservations r JOIN holiday_homes h ON h.home_id = r.home_id
                      WHERE r.reservation_id = ? FOR UPDATE', 'i', [$id]);
        if (!$r) {
            throw new DomainException('That reservation no longer exists.');
        }
        $clash = (int) db_value('SELECT COUNT(*) FROM reservations WHERE home_id = ? AND reservation_id <> ?
                                  AND check_in_date < ? AND check_out_date > ?',
            'iiss', [$r['home_id'], $id, $out->format('Y-m-d'), $in->format('Y-m-d')]);
        if ($clash) {
            throw new DomainException('Those dates overlap another booking for the same home.');
        }
        $total = sprintf('%.2f', nights_between($in, $out) * (float) $r['price']);
        db_run('UPDATE reservations SET check_in_date = ?, check_out_date = ?, total_price = ? WHERE reservation_id = ?',
            'sssi', [$in->format('Y-m-d'), $out->format('Y-m-d'), $total, $id]);
    });
    audit('admin', (int) $_SESSION['aid'], 'reservation_updated', "#$id {$in->format('Y-m-d')} to {$out->format('Y-m-d')}");
    flash('success', 'Reservation updated and the total recalculated.');
} catch (DomainException $e) {
    flash('error', $e->getMessage());
}
redirect('admin/view_reservations.php');
