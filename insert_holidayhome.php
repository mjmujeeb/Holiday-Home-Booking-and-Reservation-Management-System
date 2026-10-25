<?php
require __DIR__ . '/inc/admin_init.php';
require __DIR__ . '/inc/home_form.php';
admin_post_only();

[$d, $errors] = read_home_form();
$file = $_FILES['image'] ?? null;
$image = '';
if (!$errors) {
    try {
        $image = save_home_image($file ?: []);
    } catch (RuntimeException $e) {
        $errors[] = $e->getMessage();
    }
}
if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('admin/view_holidayhomes.php');
}

db_run('INSERT INTO holiday_homes (name, location, description, rating, price, image, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
    'sssssss', [$d['name'], $d['location'], $d['description'], $d['rating'], $d['price'], $image, $d['status']]);
audit('admin', (int) $_SESSION['aid'], 'home_created', '#' . db_last_id() . ' ' . $d['name']);
flash('success', '“' . $d['name'] . '” has been added.');
redirect('admin/view_holidayhomes.php');
