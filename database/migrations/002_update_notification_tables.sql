-- Migration 002: Update notification tables for ticket notifications
-- Issue: Old notifications table schema did not match PHP code expectations.
-- Action: Safely migrate old table to backup, create new notifications + notification_recipients tables.

-- Step 1: Only rename if old table exists AND still has old schema (notification_id column)
SET @old_table_exists = (SELECT COUNT(*) FROM information_schema.tables 
                         WHERE table_schema = DATABASE() AND table_name = 'notifications');
SET @has_old_schema = (SELECT COUNT(*) FROM information_schema.columns 
                       WHERE table_schema = DATABASE() AND table_name = 'notifications' 
                       AND column_name = 'notification_id');

SET @sql = IF(@old_table_exists = 1 AND @has_old_schema = 1, 
              'RENAME TABLE `notifications` TO `notifications_old`', 
              'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Drop new tables if they already exist (for idempotent re-runs)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `notification_recipients`;
DROP TABLE IF EXISTS `notifications`;
SET FOREIGN_KEY_CHECKS = 1;

-- Step 3: Create new notifications table (matches includes/functions_notification.php)
CREATE TABLE IF NOT EXISTS `notifications` (
    `notif_id` INT(11) NOT NULL AUTO_INCREMENT,
    `type` ENUM('new_ticket', 'new_comment') NOT NULL DEFAULT 'new_ticket',
    `ticket_id` INT(11) NULL,
    `comment_id` INT(11) NULL,
    `message` TEXT NOT NULL,
    `triggered_by` INT(11) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`notif_id`),
    INDEX `idx_ticket_id` (`ticket_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
    FOREIGN KEY (`triggered_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 4: Create notification_recipients table (matches api/getnotifications.php)
CREATE TABLE IF NOT EXISTS `notification_recipients` (
    `notif_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` DATETIME NULL,
    PRIMARY KEY (`notif_id`, `user_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_read` (`is_read`),
    FOREIGN KEY (`notif_id`) REFERENCES `notifications`(`notif_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

