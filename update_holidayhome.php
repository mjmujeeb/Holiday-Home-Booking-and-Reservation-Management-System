<?php
require __DIR__ . '/inc/admin_init.php';
require __DIR__ . '/inc/home_form.php';
admin_post_only();

$id   = post_int('home_id');
$home = $id ? db_one('SELECT home_id, image FROM holiday_homes WHERE home_id = ?', 'i', [$id]) : null;
if (!$home) {
    flash('error', 'That home no longer exists.');
    redirect('admin/view_holidayhomes.php');
}

[$d, $errors] = read_home_form();
$newImage = '';
$hasUpload = isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
if (!$errors && $hasUpload) {
    try {
        $newImage = save_home_image($_FILES['image']);
    } catch (RuntimeException $e) {
        $errors[] = $e->getMessage();
    }
}
if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('admin/view_holidayhomes.php');
}

$image = $newImage !== '' ? $newImage : $home['image'];
db_run('UPDATE holiday_homes SET name = ?, location = ?, description = ?, rating = ?, price = ?, image = ?, status = ? WHERE home_id = ?',
    'sssssssi', [$d['name'], $d['location'], $d['description'], $d['rating'], $d['price'], $image, $d['status'], $id]);
if ($newImage !== '') {
    delete_home_image($home['image']);   // only removes files this app generated
}
audit('admin', (int) $_SESSION['aid'], 'home_updated', "#$id " . $d['name']);
flash('success', 'Changes to “' . $d['name'] . '” are saved.');
redirect('admin/view_holidayhomes.php');
