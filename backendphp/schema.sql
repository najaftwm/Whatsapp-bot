-- schema.sql
-- Create database (if you import into existing DB, ensure the DB name matches config)
CREATE DATABASE IF NOT EXISTS `whatsapp_app` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- users table (admin seed)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- contacts
CREATE TABLE IF NOT EXISTS `contacts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) DEFAULT NULL,
  `phone_number` VARCHAR(50) NOT NULL UNIQUE,
  `last_message` TEXT DEFAULT NULL,
  `last_seen` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`last_seen`),
  INDEX (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- messages
CREATE TABLE IF NOT EXISTS `messages` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `contact_id` INT UNSIGNED NOT NULL,
  `sender_type` ENUM('company','customer') NOT NULL,
  `message_text` TEXT NOT NULL,
  `metadata` JSON DEFAULT NULL,
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE CASCADE,
  INDEX (`contact_id`),
  INDEX (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- seed admin user (password: "admin123" hashed with password_hash())
INSERT INTO `users` (`name`, `email`, `password_hash`, `created_at`)
VALUES ('Admin', 'admin@example.com', '$2y$10$UDhmSeY3Hf84mPz6v/.oCeXTaKJZTp5WeB8njPqXBqvaPHYdeqrdO', NOW());

-- sample contact & messages
INSERT INTO `contacts` (`name`, `phone_number`, `last_message`, `last_seen`, `created_at`) VALUES
('Aaditi Surve', '+919653375080', 'Hello, I need help', NOW(), NOW()),
('Anwar basha', '+919987464015', 'Thanks!', NOW(), NOW());

INSERT INTO `messages` (`contact_id`, `sender_type`, `message_text`, `metadata`, `timestamp`) VALUES
(1, 'customer', 'Hello, I need help', NULL, NOW() - INTERVAL 2 HOUR),
(1, 'company', 'Hi Aaditi, how can we help?', NULL, NOW() - INTERVAL 1 HOUR),
(2, 'customer', 'Thanks!', NULL, NOW() - INTERVAL 30 MINUTE);
