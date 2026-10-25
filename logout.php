<?php
require dirname(__DIR__) . '/inc/bootstrap.php';

// Signing out changes state, so it only works as a verified POST. A stray link or image on another site cannot trigger it.
if (!is_post()) {
    redirect('index.php');
}
csrf_verify();
if (is_logged_in()) {
    audit('user', current_user_id(), 'logout');
}
session_destroy_completely();
redirect('index.php');
