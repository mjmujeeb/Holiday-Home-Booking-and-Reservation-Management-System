-- HappyHolidayHome database schema
-- Import this file with phpMyAdmin (Import tab) or:  mysql -u root -p < hhh_db.sql
--
-- It creates the database, all tables and 8 sample holiday homes.
-- It deliberately creates NO users and NO admin account: no default passwords.
-- Create your first admin from the command line:  php database_schema/create_admin.php create

CREATE DATABASE IF NOT EXISTS `hhh_db`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hhh_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_log`, `login_attempts`, `pending_signups`,
                     `reservations`, `holiday_homes`, `admins`, `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- Customers
-- ---------------------------------------------------------------------------
CREATE TABLE `users` (
  `user_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(80)  NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `phone`         VARCHAR(15)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,            -- Argon2id / bcrypt via password_hash()
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME     NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Administrators (separate table, separate session, optional TOTP 2-step login)
-- ---------------------------------------------------------------------------
CREATE TABLE `admins` (
  `admin_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(80)  NOT NULL,
  `email`          VARCHAR(190) NOT NULL,
  `password_hash`  VARCHAR(255) NOT NULL,
  `totp_secret`    VARCHAR(255) NULL,               -- AES-256-GCM encrypted with the app key
  `totp_enabled`   TINYINT(1)   NOT NULL DEFAULT 0,
  `totp_last_step` BIGINT       NULL,               -- blocks re-use of a code
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at`  DATETIME     NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Holiday homes
-- ---------------------------------------------------------------------------
CREATE TABLE `holiday_homes` (
  `home_id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `location`    VARCHAR(120) NOT NULL,
  `description` TEXT         NOT NULL,
  `rating`      DECIMAL(2,1) NOT NULL DEFAULT 0.0,
  `price`       DECIMAL(10,2) NOT NULL,             -- per night
  `image`       VARCHAR(120) NOT NULL,              -- file name only, inside assets/img/holiday-homes/
  `status`      ENUM('available','not_available') NOT NULL DEFAULT 'available',  -- listed / unlisted
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`home_id`),
  KEY `idx_homes_status_location` (`status`, `location`),
  CONSTRAINT `chk_home_rating` CHECK (`rating` BETWEEN 0 AND 5),
  CONSTRAINT `chk_home_price`  CHECK (`price` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Reservations
-- ---------------------------------------------------------------------------
CREATE TABLE `reservations` (
  `reservation_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `home_id`        INT UNSIGNED NOT NULL,
  `check_in_date`  DATE         NOT NULL,
  `check_out_date` DATE         NOT NULL,
  `total_price`    DECIMAL(12,2) NOT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reservation_id`),
  KEY `idx_res_home_dates` (`home_id`, `check_in_date`, `check_out_date`),
  KEY `idx_res_user` (`user_id`),
  CONSTRAINT `fk_res_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_res_home` FOREIGN KEY (`home_id`) REFERENCES `holiday_homes` (`home_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_res_dates` CHECK (`check_out_date` > `check_in_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Sign-ups waiting for their email code. Nothing sensitive ever travels in a URL.
-- ---------------------------------------------------------------------------
CREATE TABLE `pending_signups` (
  `pending_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(190) NOT NULL,
  `name`          VARCHAR(80)  NOT NULL,
  `phone`         VARCHAR(15)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `otp_hash`      CHAR(64)     NOT NULL,            -- HMAC of the code, never the code itself
  `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `resends`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `last_sent_at`  DATETIME     NOT NULL,
  `expires_at`    DATETIME     NOT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pending_id`),
  UNIQUE KEY `uq_pending_email` (`email`),
  KEY `idx_pending_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Brute-force throttling (identifiers are stored as HMACs, not raw emails)
-- ---------------------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope`      VARCHAR(24) NOT NULL,
  `ident`      CHAR(64)    NOT NULL,
  `ip`         VARCHAR(45) NOT NULL,
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_att_ident` (`scope`, `ident`, `created_at`),
  KEY `idx_att_ip` (`scope`, `ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Audit trail of sensitive actions
-- ---------------------------------------------------------------------------
CREATE TABLE `audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_type` ENUM('admin','user','system') NOT NULL,
  `actor_id`   INT UNSIGNED NULL,
  `action`     VARCHAR(60)  NOT NULL,
  `detail`     VARCHAR(255) NOT NULL DEFAULT '',
  `ip`         VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Sample holiday homes
-- ---------------------------------------------------------------------------
INSERT INTO `holiday_homes` (`name`, `location`, `description`, `rating`, `price`, `image`, `status`) VALUES
('Luxury villa', 'Florida, USA',
 'A sprawling 15,000 square foot mansion just over five miles from Walt Disney World, with views across the Jack Nicklaus golf course. It is packed with amenities, and there is one surprise tucked inside that may keep you indoors.',
 4.0, 500.00, 'luxury-villa.jpg', 'available'),
('Downtown garden apartment', 'Buena Vista, Colorado, USA',
 'A modern apartment that is ideal for a weekend away, with its own garden and seating area. It sleeps up to four and has every comfort you would expect, plus a secret inside that suits the active holidaymaker.',
 4.2, 400.00, 'garden-apartment.jpg', 'available'),
('Luxury villa II', 'Florida, USA',
 'Hidden deep in this house is the ultimate in entertainment: a two-lane bowling alley with a comfortable lounge and TV screens, so everyone can keep track of who is winning. Competitive families will not want to leave.',
 4.3, 300.00, 'luxury-villa-ii.jpg', 'available'),
('City escape', 'Florida, USA',
 'Designed as a break from busy city life. Guests step into a magical indoor forest with a log stool and a swinging chair in the living room. The woodland comes with modern comforts, including a smart TV and air conditioning.',
 3.9, 360.00, 'city-escape.jpg', 'available'),
('Domus Civita', 'Lazio, Italy',
 'The living room and kitchen on the first floor keep their original features: a basalt stone fireplace, terracotta floors and wooden beams dating from the 14th century. The villa sleeps six in three bedrooms surrounded by arched doorways and exposed tufa rock.',
 3.7, 470.00, 'domus-civita.jpg', 'available'),
('Artist''s house', 'Florida, USA',
 'Filled with retro, mid-century modern furniture. The informal dining room has a striking black and white colour scheme, a six-seater table, and a dividing screen that separates it from the living room.',
 3.5, 500.00, 'artists-house.jpg', 'available'),
('Rural getaway', 'North Tipperary, Ireland',
 'An Irish bolthole with a slice of local history. In sleepy North Tipperary, it is said to be one of the most famous and unusual self-catering holiday homes in the country. Step inside to see why.',
 3.6, 360.00, 'rural-getaway.jpg', 'available'),
('The Rancho', 'Costa Rica',
 'A tropical retreat on nearly four acres of lush land, surrounded by fruit trees and coffee farms. The perfect place to get away from it all and taste the real Costa Rica.',
 4.7, 497.00, 'the-rancho.jpg', 'available');

-- ---------------------------------------------------------------------------
-- RECOMMENDED: run the site with a least-privilege database account, not root.
-- Uncomment, change the password, and put the same values in inc/config.php.
-- ---------------------------------------------------------------------------
-- CREATE USER 'hhh_app'@'localhost' IDENTIFIED BY 'use-a-long-random-password-here';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `hhh_db`.* TO 'hhh_app'@'localhost';
-- FLUSH PRIVILEGES;
