<?php
/*
 * HappyHolidayHome configuration.
 *
 * 1. Copy this file to  inc/config.php
 * 2. Fill in your own values. inc/config.php is git-ignored and blocked from the web.
 *
 * Never commit real passwords or keys.
 */
return [
    // 'production' = real emails are sent.
    // 'development' = emails (including sign-up codes) are written to storage/logs/mail.log instead.
    'env'      => 'development',

    'app_name' => 'HappyHolidayHome',
    'timezone' => 'Asia/Kolkata',
    'currency' => '₹',

    // URL folder the site lives in. Use '' when it runs at the root of a domain,
    // '/HappyHolidayHome' for XAMPP (http://localhost/HappyHolidayHome), or null to auto-detect.
    'base_path' => null,

    // Redirect every request to https:// and send HSTS. Turn on as soon as you have a certificate.
    'force_https' => false,

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'hhh_db',
        'user' => 'hhh_app',      // see the bottom of database_schema/hhh_db.sql
        'pass' => 'change-me',
    ],

    // 32 random bytes, base64 encoded. Used to sign codes and encrypt 2-step secrets.
    // Generate one with:  php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
    // Changing it later invalidates pending sign-up codes and admin 2-step setups.
    'app_key' => '',

    // Outgoing email for sign-up codes (Gmail users: create an "app password").
    'mail' => [
        'host'       => 'smtp.gmail.com',
        'port'       => 465,
        'encryption' => 'ssl',            // 'ssl' (465) or 'tls' (587)
        'user'       => '',
        'pass'       => '',
        'from'       => '',
        'from_name'  => 'HappyHolidayHome',
    ],

    // Optional Google reCAPTCHA v2 on the login pages. Leave blank to switch it off.
    'recaptcha' => [
        'site_key' => '',
        'secret'   => '',
    ],

    // Shown on the Contact page and in the footer.
    'contact' => [
        'email'   => 'hello@example.com',
        'phone'   => '+91 00000 00000',
        'address' => 'Your street, Your city',
    ],

    // Footer credit. Set 'name' to '' to hide it.
    'author' => [
        'name'     => 'Adhiraj Saha',
        'github'   => 'https://github.com/adhirajcs',
        'linkedin' => 'https://www.linkedin.com/in/adhirajsaha',
    ],
];
