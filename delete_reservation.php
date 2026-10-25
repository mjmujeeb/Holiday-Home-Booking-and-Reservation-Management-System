<?php
require __DIR__ . '/inc/admin_init.php';
admin_post_only();

$id = post_int('reservation_id');
$n  = $id ? db_run('DELETE FROM reservations WHERE reservation_id = ?', 'i', [$id]) : 0;
if ($n === 1) {
    audit('admin', (int) $_SESSION['aid'], 'reservation_deleted', "#$id");
    flash('success', 'Reservation deleted.');
} else {
    flash('error', 'That reservation no longer exists.');
}
redirect('admin/view_reservations.php');
