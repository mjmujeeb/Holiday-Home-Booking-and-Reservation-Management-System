# HappyHolidayHome

A holiday-home reservation website built with **PHP, MySQL/MariaDB, HTML, CSS and a little JavaScript**.
Visitors search homes by place and dates, create a verified account, book, and manage their reservations.
Staff manage homes, guests and bookings from a separate admin panel.

This is a ground-up rebuild of the original project: same idea and same features, with a new design and a much stricter approach to security.

## Features

**Guests**
- Search by destination and dates; only homes that are genuinely free are shown
- Home detail page with live price summary and the dates already taken
- Sign-up with a 6-digit email code, login, profile and password change
- My reservations with references (`HHH-00012`) and cancellation before check-in

**Admin panel** (`/admin`)
- Dashboard with live figures and recent activity
- Add, edit, list/unlist and delete homes (safe photo uploads)
- View guests, edit or delete reservations, full audit log
- Optional two-step sign-in with any authenticator app

## Requirements

PHP 8.1 or newer (with `mysqli`, `mbstring`, `openssl`, `fileinfo`; `gd` recommended) and MySQL 8 / MariaDB 10.4+, on Apache 2.4. XAMPP works.

## Setup (XAMPP)

1. **Copy the folder** to `C:\xampp\htdocs\HappyHolidayHome` and start Apache and MySQL.
2. **Create the database.** Open phpMyAdmin → *Import* → choose `database_schema/hhh_db.sql`.
   Then create a limited database user (the commented block at the bottom of that file) rather than using `root`.
3. **Configure.** Copy `inc/config.sample.php` to `inc/config.php` and fill it in:
   - `db` – the user and password from step 2
   - `app_key` – run `php -r "echo 'base64:'.base64_encode(random_bytes(32));"` and paste the result
   - `base_path` – `'/HappyHolidayHome'` under XAMPP (or `null` to auto-detect)
   - `mail` – SMTP details for the sign-up codes (Gmail needs an *app password*)
   - leave `env` as `'development'` while testing: no email is sent and sign-up codes appear in `storage/logs/mail.log`. Switch to `'production'` to send real email.
4. **Create your first admin** (there are no default accounts and no public admin sign-up):
   ```
   C:\xampp\php\php.exe database_schema\create_admin.php create
   ```
5. Open `http://localhost/HappyHolidayHome/`. Admin sign-in is at `/admin/admin_login.php`. Turn on two-step sign-in under **Security**.

Other admin commands: `reset-password <email>` and `disable-2fa <email>` (for a lost phone).

## Security overview

| Area | What is done |
| --- | --- |
| SQL injection | Every query is a prepared statement; search text has `%` and `_` escaped |
| Passwords | Argon2id (bcrypt fallback), automatic re-hash, strength rules, constant-effort login so timing does not reveal which emails exist |
| Brute force | Per-account and per-IP throttling for login, admin login, 2-step codes, email codes, password changes, sign-ups and bookings |
| Sign-up | Code is stored only as an HMAC, expires in 10 min, locks after 5 wrong tries, resend cooldown. No data in URLs. Existing emails are not revealed |
| Sessions | HttpOnly, SameSite=Strict, `Secure` on HTTPS, ID regenerated on login and password change, idle + absolute timeouts, separate admin cookie limited to `/admin/` |
| CSRF | Token on every form; all state changes (logout, cancel, delete) are POST-only |
| XSS | All output escaped; strict Content-Security-Policy with no inline scripts or styles and no third-party CDNs |
| Admin | Separate table and session, optional TOTP (secret encrypted at rest, codes cannot be replayed), audit log, no public registration |
| Uploads | Real type read from the bytes, image decoded and re-encoded (strips hidden code and EXIF), random file names, 5 MB cap, script execution disabled in the upload folder |
| Booking integrity | Totals computed on the server; availability re-checked inside a transaction with a row lock, so two people cannot book the same nights |
| Headers | CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, COOP/CORP, HSTS on HTTPS, `no-store` caching |
| Files | `.htaccess` blocks config, logs, SQL and includes; errors go to `storage/logs`, never to the browser |

**Before going live:** use HTTPS and set `force_https` to `true`; set `env` to `'production'`; use a least-privilege DB user; keep `inc/config.php` out of version control (already in `.gitignore`).
Optional reCAPTCHA v2 on the login pages is switched on by adding keys in `config.php`.

## Project layout

```
index.php            home page
users/               search, reserve, login, signup, verify_otp, reservations, profile, about, contact …
admin/               dashboard, homes, reservations, guests, audit log, security, login + 2-step
inc/                 bootstrap, security, db, helpers, mail, uploads, layout partials, config
assets/              css, js, images
database_schema/     hhh_db.sql and create_admin.php (command line)
PHPMailer/           only the three files needed for SMTP
storage/logs/        error and development mail logs (git-ignored)
```

## What changed from the original

- **Removed:** open admin sign-up page, hard-coded admin and user accounts, default database credentials, 60+ PHPMailer language files, POP3/OAuth code, Bootstrap and Font Awesome CDNs, duplicate and unused images, Spanish README, GitHub funding file
- **Rebuilt:** every page, one shared stylesheet per area instead of one per page, new layout partials
- **Fixed:** "booking one date range marks the whole home unavailable for everyone" – availability is now checked per date range
- **Content tidy-up:** each home now has its real location (Colorado, Italy, Ireland, Costa Rica) instead of "Florida" for all, and "Luxury villa2" became "Luxury villa II". The original logo is kept in `assets/img/brand/`.

Licensed under Apache 2.0 (see `LICENSE`). PHPMailer is LGPL (see `PHPMailer/LICENSE`).
