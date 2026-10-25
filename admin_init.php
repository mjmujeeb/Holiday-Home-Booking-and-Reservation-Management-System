<?php
/* Every admin page starts here. Admin has its own session cookie, scoped to /admin.
 * Pages that must be reachable while signed out define HHH_PUBLIC_ADMIN first. */
define('HHH_AREA', 'admin');
if (defined('HHH_ADMIN_CAPTCHA')) {
    define('HHH_CAPTCHA', true);
}
require dirname(__DIR__, 2) . '/inc/bootstrap.php';
if (!defined('HHH_PUBLIC_ADMIN')) {
    require_admin();
}

/** For handlers that change data: POST only, CSRF verified. */
function admin_post_only(): void
{
    require_post();
}
