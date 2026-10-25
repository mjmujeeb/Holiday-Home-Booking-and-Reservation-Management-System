<?php
define('HHH_PUBLIC_ADMIN', true);
require __DIR__ . '/inc/admin_init.php';
if (!is_post()) {
    redirect('admin/admin_login.php');
}
csrf_verify();
if (is_admin()) {
    audit('admin', (int) $_SESSION['aid'], 'logout');
}
session_destroy_completely();
redirect('admin/admin_login.php');
