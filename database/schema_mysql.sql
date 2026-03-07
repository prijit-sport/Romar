-- ========================================
-- Romar Dormitory Management System
-- MySQL Database Schema
-- ========================================

-- ========================================
-- Table: users
-- ========================================
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100),
    `role` ENUM('admin', 'staff', 'user', 'it_support') DEFAULT 'user',
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME,
    PRIMARY KEY (`user_id`),
    INDEX `idx_username` (`username`),
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: meetings_rooms (ห้องประชุม)
-- ========================================
CREATE TABLE IF NOT EXISTS `meeting_rooms` (
    `room_id` INT(11) NOT NULL AUTO_INCREMENT,
    `room_name` VARCHAR(100) NOT NULL,
    `capacity` INT(11),
    `location` VARCHAR(255),
    `amenities` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`room_id`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: bookings (การจองห้องประชุม)
-- ========================================
CREATE TABLE IF NOT EXISTS `bookings` (
    `booking_id` INT(11) NOT NULL AUTO_INCREMENT,
    `room_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `booking_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `purpose` TEXT,
    `attendees` INT(11),
    `status` ENUM('pending', 'approved', 'cancelled', 'completed') DEFAULT 'pending',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`booking_id`),
    INDEX `idx_room_id` (`room_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_booking_date` (`booking_date`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`room_id`) REFERENCES `meeting_rooms`(`room_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: conversations (บันทึกการสนทนา)
-- ========================================
CREATE TABLE IF NOT EXISTS `conversations` (
    `conversation_id` INT(11) NOT NULL AUTO_INCREMENT,
    `subject` VARCHAR(255) NOT NULL,
    `conversation_with` VARCHAR(100),
    `conversation_type` ENUM('phone', 'email', 'in_person', 'other'),
    `notes` TEXT,
    `recorded_by` INT(11) NOT NULL,
    `conversation_date` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`conversation_id`),
    INDEX `idx_recorded_by` (`recorded_by`),
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: announcements (ประกาศข่าวสาร)
-- ========================================
CREATE TABLE IF NOT EXISTS `announcements` (
    `announcement_id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `priority` ENUM('normal', 'important', 'urgent') DEFAULT 'normal',
    `published_by` INT(11) NOT NULL,
    `publish_date` DATETIME NOT NULL,
    `expire_date` DATETIME,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`announcement_id`),
    INDEX `idx_published_by` (`published_by`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_expire_date` (`expire_date`),
    FOREIGN KEY (`published_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: documents (เอกสาร)
-- ========================================
CREATE TABLE IF NOT EXISTS `documents` (
    `document_id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `document_name` VARCHAR(255) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` INT(11),
    `file_type` VARCHAR(50),
    `category` VARCHAR(100),
    `description` TEXT,
    `uploaded_by` INT(11) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`document_id`),
    INDEX `idx_uploaded_by` (`uploaded_by`),
    INDEX `idx_category` (`category`),
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: activity_logs (บันทึกการใช้งาน)
-- ========================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `log_id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11),
    `action` VARCHAR(100) NOT NULL,
    `module` VARCHAR(50),
    `description` TEXT,
    `ip_address` VARCHAR(45),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_module` (`module`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: tickets (IT Ticket System)
-- ========================================
CREATE TABLE IF NOT EXISTS `tickets` (
    `ticket_id` INT(11) NOT NULL AUTO_INCREMENT,
    `ticket_number` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `category` ENUM('hardware', 'software', 'network', 'account', 'printer', 'email', 'other') NOT NULL DEFAULT 'other',
    `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    `urgency` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    `impact` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    `status` ENUM('new', 'assigned', 'in_progress', 'pending', 'resolved', 'closed', 'cancelled') NOT NULL DEFAULT 'new',
    `resolution` TEXT,
    `location` VARCHAR(255),
    `created_by` INT(11) NOT NULL,
    `assigned_to` INT(11),
    `assigned_group` VARCHAR(100),
    `asset_id` INT(11),
    `sla_due_date` DATETIME,
    `response_time` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `resolved_at` DATETIME,
    `closed_at` DATETIME,
    PRIMARY KEY (`ticket_id`),
    INDEX `idx_ticket_number` (`ticket_number`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_assigned_to` (`assigned_to`),
    INDEX `idx_asset_id` (`asset_id`),
    INDEX `idx_sla_due_date` (`sla_due_date`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_comments
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_comments` (
    `comment_id` INT(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `comment` TEXT NOT NULL,
    `is_internal` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`comment_id`),
    INDEX `idx_ticket_id` (`ticket_id`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_attachments
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_attachments` (
    `attachment_id` INT(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` INT(11) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500),
    `file_size` INT(11) NOT NULL,
    `mime_type` VARCHAR(100),
    `uploaded_by` INT(11) NOT NULL,
    `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`attachment_id`),
    INDEX `idx_ticket_id` (`ticket_id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_time_tracking
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_time_tracking` (
    `tracking_id` INT(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `hours_spent` DECIMAL(10,2) NOT NULL,
    `work_description` TEXT NOT NULL,
    `logged_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tracking_id`),
    INDEX `idx_ticket_id` (`ticket_id`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_timeline
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_timeline` (
    `timeline_id` INT(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` INT(11) NOT NULL,
    `user_id` INT(11),
    `action_type` ENUM('create', 'update', 'comment', 'assign', 'status_change', 'priority_change', 'link', 'time_log', 'attachment') NOT NULL,
    `description` TEXT NOT NULL,
    `old_value` VARCHAR(255),
    `new_value` VARCHAR(255),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`timeline_id`),
    INDEX `idx_ticket_id` (`ticket_id`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_relations
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_relations` (
    `relation_id` INT(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` INT(11) NOT NULL,
    `related_ticket_id` INT(11) NOT NULL,
    `relation_type` ENUM('related', 'duplicate', 'parent', 'child', 'blocks', 'blocked_by') NOT NULL DEFAULT 'related',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`relation_id`),
    INDEX `idx_ticket_id` (`ticket_id`),
    INDEX `idx_related_ticket_id` (`related_ticket_id`),
    FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
    FOREIGN KEY (`related_ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: assets
-- ========================================
CREATE TABLE IF NOT EXISTS `assets` (
    `asset_id` INT(11) NOT NULL AUTO_INCREMENT,
    `asset_tag` VARCHAR(50) NOT NULL UNIQUE,
    `asset_name` VARCHAR(255) NOT NULL,
    `asset_type` ENUM('computer', 'laptop', 'monitor', 'printer', 'phone', 'tablet', 'server', 'network_device', 'other') NOT NULL,
    `brand` VARCHAR(100),
    `model` VARCHAR(100),
    `serial_number` VARCHAR(100),
    `inventory_number` VARCHAR(100),
    `status` ENUM('active', 'inactive', 'maintenance', 'retired', 'borrowed') DEFAULT 'active',
    `assigned_to` INT(11),
    `tech_in_charge` INT(11),
    `location` VARCHAR(255),
    `purchase_date` DATE,
    `warranty_expiry` DATE,
    `notes` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`asset_id`),
    INDEX `idx_asset_tag` (`asset_tag`),
    INDEX `idx_assigned_to` (`assigned_to`),
    INDEX `idx_status` (`status`),
    INDEX `idx_asset_type` (`asset_type`),
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    FOREIGN KEY (`tech_in_charge`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: asset_repairs
-- ========================================
CREATE TABLE IF NOT EXISTS `asset_repairs` (
    `repair_id` INT(11) NOT NULL AUTO_INCREMENT,
    `asset_id` INT(11) NOT NULL,
    `repair_date` DATE NOT NULL,
    `description` TEXT NOT NULL,
    `repair_cost` DECIMAL(10,2) DEFAULT 0,
    `warranty_claim` TINYINT(1) DEFAULT 0,
    `vendor` VARCHAR(255),
    `created_by` INT(11) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`repair_id`),
    INDEX `idx_asset_id` (`asset_id`),
    INDEX `idx_repair_date` (`repair_date`),
    FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: asset_borrows
-- ========================================
CREATE TABLE IF NOT EXISTS `asset_borrows` (
    `borrow_id` INT(11) NOT NULL AUTO_INCREMENT,
    `asset_id` INT(11) NOT NULL,
    `borrower_id` INT(11) NOT NULL,
    `borrow_date` DATE NOT NULL,
    `return_date` DATE,
    `due_date` DATE,
    `status` ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    `purpose` TEXT,
    `approved_by` INT(11),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`borrow_id`),
    INDEX `idx_asset_id` (`asset_id`),
    INDEX `idx_borrower_id` (`borrower_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE,
    FOREIGN KEY (`borrower_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: asset_transfers
-- ========================================
CREATE TABLE IF NOT EXISTS `asset_transfers` (
    `transfer_id` INT(11) NOT NULL AUTO_INCREMENT,
    `asset_id` INT(11) NOT NULL,
    `from_user_id` INT(11),
    `to_user_id` INT(11) NOT NULL,
    `transferred_by` INT(11) NOT NULL,
    `transfer_date` DATETIME NOT NULL,
    `reason` TEXT,
    PRIMARY KEY (`transfer_id`),
    INDEX `idx_asset_id` (`asset_id`),
    INDEX `idx_from_user` (`from_user_id`),
    INDEX `idx_to_user` (`to_user_id`),
    FOREIGN KEY (`asset_id`) REFERENCES `assets`(`asset_id`) ON DELETE CASCADE,
    FOREIGN KEY (`from_user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
    FOREIGN KEY (`to_user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`transferred_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: sla_rules
-- ========================================
CREATE TABLE IF NOT EXISTS `sla_rules` (
    `sla_id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL,
    `impact` ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    `response_time_hours` INT(11) NOT NULL,
    `resolution_time_hours` INT(11) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`sla_id`),
    UNIQUE KEY `unique_priority_impact` (`priority`, `impact`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: knowledge_base
-- ========================================
CREATE TABLE IF NOT EXISTS `knowledge_base` (
    `kb_id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `content` TEXT NOT NULL,
    `solution` TEXT,
    `tags` VARCHAR(500),
    `views` INT(11) DEFAULT 0,
    `helpful_count` INT(11) DEFAULT 0,
    `created_by` INT(11) NOT NULL,
    `is_published` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`kb_id`),
    INDEX `idx_category` (`category`),
    INDEX `idx_published` (`is_published`),
    FULLTEXT KEY `ft_search` (`title`, `content`, `tags`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: knowledge_base_categories
-- ========================================
CREATE TABLE IF NOT EXISTS `kbcategories` (
    `category_id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `display_order` INT(11) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    PRIMARY KEY (`category_id`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: system_settings
-- ========================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_id` INT(11) NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `description` VARCHAR(255),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_id`),
    INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: notifications
-- ========================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    `link` VARCHAR(255),
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`notification_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Default Data
-- ========================================

-- Insert default meeting rooms
INSERT INTO `meeting_rooms` (`room_name`, `capacity`, `location`, `amenities`, `is_active`) VALUES 
('ห้องประชุมใหญ่', 30, 'ชั้น 2', 'Projector, Whiteboard, Wi-Fi, Air-Con', 1),
('ห้องประชุมเล็ก', 10, 'ชั้น 2', 'TV Screen, Whiteboard, Wi-Fi', 1),
('ห้องอบรม', 50, 'ชั้น 3', 'Projector, Sound System, Air-Con', 1);

-- Insert default SLA rules
INSERT INTO `sla_rules` (`name`, `priority`, `impact`, `response_time_hours`, `resolution_time_hours`) VALUES
('Critical-Urgent', 'urgent', 'critical', 1, 2),
('Critical-High', 'urgent', 'high', 1, 4),
('Critical-Medium', 'urgent', 'medium', 2, 8),
('Critical-Low', 'urgent', 'low', 2, 16),
('High-Critical', 'high', 'critical', 2, 4),
('High-High', 'high', 'high', 2, 8),
('High-Medium', 'high', 'medium', 4, 16),
('High-Low', 'high', 'low', 4, 24),
('Normal-Critical', 'normal', 'critical', 4, 8),
('Normal-High', 'normal', 'high', 4, 16),
('Normal-Medium', 'normal', 'medium', 8, 24),
('Normal-Low', 'normal', 'low', 8, 48),
('Low-Critical', 'low', 'critical', 8, 16),
('Low-High', 'low', 'high', 8, 24),
('Low-Medium', 'low', 'medium', 16, 48),
('Low-Low', 'low', 'low', 24, 72);

-- Insert default knowledge base categories
INSERT INTO `kbcategories` (`name`, `description`, `display_order`, `is_active`) VALUES
('Hardware', 'ปัญหาเกี่ยวกับฮาร์ดแวร์', 1, 1),
('Software', 'ปัญหาเกี่ยวกับซอฟต์แวร์', 2, 1),
('Network', 'ปัญหาเกี่ยวกับเครือข่าย', 3, 1),
('Account', 'ปัญหาเกี่ยวกับบัญชีผู้ใช้', 4, 1),
('Other', 'ปัญหาอื่นๆ', 5, 1);

-- Insert sample assets
INSERT INTO `assets` (`asset_tag`, `asset_name`, `asset_type`, `brand`, `model`, `status`, `location`) VALUES
('PC-001', 'Desktop Computer - Accounting', 'computer', 'Dell', 'OptiPlex 7090', 'active', 'Building A - 2F - Room 201'),
('PC-002', 'Desktop Computer - HR', 'computer', 'HP', 'ProDesk 600 G6', 'active', 'Building A - 3F - Room 305'),
('LP-001', 'Laptop - Sales Manager', 'laptop', 'Lenovo', 'ThinkPad X1 Carbon', 'active', 'Mobile'),
('PR-001', 'Laser Printer', 'printer', 'HP', 'LaserJet Pro M404', 'active', 'Building A - 2F - Pantry'),
('SV-001', 'File Server', 'server', 'Dell', 'PowerEdge R740', 'active', 'Server Room');

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('site_name', 'ระบบจัดการโรมาร์', 'ชื่อเว็บไซต์'),
('site_name_en', 'Romar Dormitory', 'ชื่อเว็บไซต์ภาษาอังกฤษ'),
('items_per_page', '20', 'จำนวนรายการต่อหน้า'),
('session_timeout', '60', 'Session timeout เป็นนาที'),
('max_file_size', '10485760', 'ขนาดไฟล์สูงสุด (bytes)'),
('allowed_file_types', 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif', 'ประเภทไฟล์ที่อนุญาต');

-- ========================================
-- Create Views
-- ========================================

-- View: Active Tickets with SLA Status
CREATE OR REPLACE VIEW `v_active_tickets` AS
SELECT 
    t.ticket_id,
    t.ticket_number,
    t.title,
    t.status,
    t.priority,
    t.urgency,
    t.impact,
    t.category,
    creator.full_name AS requester,
    assignee.full_name AS assigned_to,
    t.created_at,
    t.sla_due_date,
    CASE 
        WHEN t.sla_due_date < NOW() AND t.status NOT IN ('resolved', 'closed') THEN 'Overdue'
        WHEN TIMESTAMPDIFF(HOUR, NOW(), t.sla_due_date) < 2 AND t.status NOT IN ('resolved', 'closed') THEN 'Critical'
        ELSE 'On Track'
    END AS sla_status
FROM tickets t
LEFT JOIN users creator ON t.created_by = creator.user_id
LEFT JOIN users assignee ON t.assigned_to = assignee.user_id
WHERE t.status NOT IN ('closed', 'cancelled');

-- View: Dashboard Statistics
CREATE OR REPLACE VIEW `v_dashboard_stats` AS
SELECT 
    (SELECT COUNT(*) FROM tickets) AS total_tickets,
    (SELECT COUNT(*) FROM tickets WHERE status = 'new') AS open_tickets,
    (SELECT COUNT(*) FROM tickets WHERE status = 'in_progress') AS in_progress_tickets,
    (SELECT COUNT(*) FROM tickets WHERE status = 'resolved') AS resolved_tickets,
    (SELECT COUNT(*) FROM announcements WHERE is_active = 1) AS active_announcements,
    (SELECT COUNT(*) FROM users WHERE is_active = 1) AS total_users,
    (SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE() AND status = 'approved') AS today_bookings,
    (SELECT COUNT(*) FROM documents) AS total_documents,
    (SELECT COUNT(*) FROM assets WHERE status = 'active') AS active_assets;

-- ========================================
-- Done!
-- ========================================

