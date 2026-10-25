<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require_login();

if (!is_post()) {
    redirect('users/reservations.php');
}
csrf_verify();

$uid = (int) current_user_id();
$rid = post_int('reservation_id');

// Ownership and "not started yet" are enforced inside the DELETE itself,
// so there is no gap between checking and acting, and no way to cancel someone else's booking.
$deleted = $rid > 0
    ? db_run('DELETE FROM reservations WHERE reservation_id = ? AND user_id = ? AND check_in_date > CURDATE()', 'ii', [$rid, $uid])
    : 0;

if ($deleted === 1) {
    audit('user', $uid, 'reservation_cancelled', "reservation #$rid");
    flash('success', 'Your booking has been cancelled.');
} else {
    flash('error', 'That booking could not be cancelled. It may have already started or no longer exist.');
}
redirect('users/reservations.php');
