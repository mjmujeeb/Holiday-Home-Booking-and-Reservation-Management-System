<?php
require __DIR__ . '/inc/admin_init.php';
admin_post_only();

$id   = post_int('home_id');
$home = $id ? db_one('SELECT home_id, name, image FROM holiday_homes WHERE home_id = ?', 'i', [$id]) : null;
if (!$home) {
    flash('error', 'That home no longer exists.');
    redirect('admin/view_holidayhomes.php');
}
$count = (int) db_value('SELECT COUNT(*) FROM reservations WHERE home_id = ?', 'i', [$id]);
if ($count > 0) {
    flash('error', "“{$home['name']}” has $count reservation(s) and cannot be deleted. Set it to Unlisted instead.");
    redirect('admin/view_holidayhomes.php');
}
db_run('DELETE FROM holiday_homes WHERE home_id = ?', 'i', [$id]);
delete_home_image($home['image']);
audit('admin', (int) $_SESSION['aid'], 'home_deleted', "#$id {$home['name']}");
flash('success', '“' . $home['name'] . '” was deleted.');
redirect('admin/view_holidayhomes.php');
