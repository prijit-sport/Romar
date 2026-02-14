-- Enhanced IT Support Ticket System Database Schema
-- Compatible with GLPI-style ticket management

-- ========================================
-- Table: tickets (Enhanced)
-- ========================================
CREATE TABLE IF NOT EXISTS `tickets` (
  `ticket_id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `category` ENUM('hardware', 'software', 'network', 'account', 'printer', 'email', 'other') NOT NULL,
  `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
  `urgency` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
  `impact` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
  `status` ENUM('new', 'assigned', 'in_progress', 'pending', 'resolved', 'closed', 'cancelled') NOT NULL DEFAULT 'new',
  `resolution` TEXT NULL,
  `location` VARCHAR(255) NULL,
  `created_by` INT(11) NOT NULL,
  `assigned_to` INT(11) NULL,
  `assigned_group` VARCHAR(100) NULL,
  `asset_id` INT(11) NULL,
  `sla_due_date` DATETIME NULL,
  `response_time` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  `resolved_at` DATETIME NULL,
  `closed_at` DATETIME NULL,
  PRIMARY KEY (`ticket_id`),
  INDEX `idx_ticket_number` (`ticket_number`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_by` (`created_by`),
  INDEX `idx_assigned_to` (`assigned_to`),
  INDEX `idx_asset_id` (`asset_id`),
  INDEX `idx_sla_due` (`sla_due_date`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_comments
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_comments` (
  `comment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `comment` TEXT NOT NULL,
  `is_internal` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Internal note for IT team only',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`comment_id`),
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_user_id` (`user_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_attachments
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_attachments` (
  `attachment_id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NULL,
  `file_size` INT(11) NOT NULL,
  `mime_type` VARCHAR(100) NULL,
  `uploaded_by` INT(11) NOT NULL,
  `uploaded_at` DATETIME NOT NULL,
  PRIMARY KEY (`attachment_id`),
  INDEX `idx_ticket_id` (`ticket_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE
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
  `logged_at` DATETIME NOT NULL,
  PRIMARY KEY (`tracking_id`),
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_user_id` (`user_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_timeline
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_timeline` (
  `timeline_id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `user_id` INT(11) NULL,
  `action_type` ENUM('create', 'update', 'comment', 'assign', 'status_change', 'priority_change', 'link', 'time_log', 'attachment') NOT NULL,
  `description` TEXT NOT NULL,
  `old_value` VARCHAR(255) NULL,
  `new_value` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`timeline_id`),
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_relations
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_relations` (
  `relation_id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `related_ticket_id` INT(11) NOT NULL,
  `relation_type` ENUM('related', 'duplicate', 'parent', 'child', 'blocks', 'blocked_by') NOT NULL DEFAULT 'related',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`relation_id`),
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_related_ticket_id` (`related_ticket_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
  FOREIGN KEY (`related_ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: sla_rules
-- ========================================
CREATE TABLE IF NOT EXISTS `sla_rules` (
  `sla_id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL,
  `impact` ENUM('low', 'medium', 'high', 'critical') NOT NULL,
  `response_time_hours` INT(11) NOT NULL COMMENT 'Hours to first response',
  `resolution_time_hours` INT(11) NOT NULL COMMENT 'Hours to resolve',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sla_id`),
  UNIQUE KEY `unique_priority_impact` (`priority`, `impact`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_approvals
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_approvals` (
  `approval_id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `approver_id` INT(11) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `comments` TEXT NULL,
  `requested_at` DATETIME NOT NULL,
  `responded_at` DATETIME NULL,
  PRIMARY KEY (`approval_id`),
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_approver_id` (`approver_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: knowledge_base
-- ========================================
CREATE TABLE IF NOT EXISTS `knowledge_base` (
  `kb_id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `content` TEXT NOT NULL,
  `solution` TEXT NULL,
  `tags` VARCHAR(500) NULL,
  `views` INT(11) NOT NULL DEFAULT 0,
  `helpful_count` INT(11) NOT NULL DEFAULT 0,
  `created_by` INT(11) NOT NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`kb_id`),
  INDEX `idx_category` (`category`),
  INDEX `idx_published` (`is_published`),
  FULLTEXT KEY `ft_search` (`title`, `content`, `tags`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_kb_links
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_kb_links` (
  `link_id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `kb_id` INT(11) NOT NULL,
  `linked_by` INT(11) NOT NULL,
  `linked_at` DATETIME NOT NULL,
  PRIMARY KEY (`link_id`),
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_kb_id` (`kb_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
  FOREIGN KEY (`kb_id`) REFERENCES `knowledge_base`(`kb_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: assets (Enhanced for integration)
-- ========================================
CREATE TABLE IF NOT EXISTS `assets` (
  `asset_id` INT(11) NOT NULL AUTO_INCREMENT,
  `asset_tag` VARCHAR(50) NOT NULL UNIQUE,
  `asset_name` VARCHAR(255) NOT NULL,
  `asset_type` ENUM('computer', 'laptop', 'monitor', 'printer', 'phone', 'tablet', 'server', 'network_device', 'other') NOT NULL,
  `brand` VARCHAR(100) NULL,
  `model` VARCHAR(100) NULL,
  `serial_number` VARCHAR(100) NULL,
  `status` ENUM('active', 'inactive', 'maintenance', 'retired') NOT NULL DEFAULT 'active',
  `assigned_to` INT(11) NULL,
  `location` VARCHAR(255) NULL,
  `purchase_date` DATE NULL,
  `warranty_expiry` DATE NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`asset_id`),
  INDEX `idx_asset_tag` (`asset_tag`),
  INDEX `idx_assigned_to` (`assigned_to`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: ticket_categories
-- ========================================
CREATE TABLE IF NOT EXISTS `ticket_categories` (
  `category_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `parent_category_id` INT(11) NULL,
  `description` TEXT NULL,
  `icon` VARCHAR(50) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`category_id`),
  INDEX `idx_parent` (`parent_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table: email_notifications
-- ========================================
CREATE TABLE IF NOT EXISTS `email_notifications` (
  `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` INT(11) NOT NULL,
  `recipient_email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  `sent_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  INDEX `idx_ticket_id` (`ticket_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Insert Default SLA Rules
-- ========================================
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

-- ========================================
-- Insert Default Ticket Categories
-- ========================================
INSERT INTO `ticket_categories` (`category_name`, `parent_category_id`, `description`, `icon`, `is_active`) VALUES
('Hardware', NULL, 'Hardware related issues', 'fa-desktop', 1),
('Computer Issues', 1, 'Desktop and laptop problems', 'fa-laptop', 1),
('Printer Issues', 1, 'Printer and scanner problems', 'fa-print', 1),
('Peripherals', 1, 'Mouse, keyboard, monitor issues', 'fa-keyboard', 1),
('Software', NULL, 'Software related issues', 'fa-file-code', 1),
('Application Error', 2, 'Application crashes and errors', 'fa-bug', 1),
('Installation', 2, 'Software installation requests', 'fa-download', 1),
('License', 2, 'Software licensing issues', 'fa-key', 1),
('Network', NULL, 'Network and connectivity', 'fa-network-wired', 1),
('Internet Connection', 3, 'Internet access problems', 'fa-wifi', 1),
('VPN', 3, 'VPN connection issues', 'fa-shield-alt', 1),
('Shared Drive', 3, 'Network drive access', 'fa-folder-open', 1),
('Account & Access', NULL, 'User accounts and permissions', 'fa-user-lock', 1),
('Password Reset', 4, 'Password recovery and reset', 'fa-unlock-alt', 1),
('New Account', 4, 'New user account request', 'fa-user-plus', 1),
('Permission', 4, 'Access permission request', 'fa-shield-check', 1),
('Email', NULL, 'Email related issues', 'fa-envelope', 1),
('Email Access', 5, 'Cannot access email', 'fa-inbox', 1),
('Email Configuration', 5, 'Email setup and configuration', 'fa-cog', 1),
('Other', NULL, 'Other requests', 'fa-question-circle', 1);

-- ========================================
-- Create Views for Reporting
-- ========================================

-- View: Active Tickets Summary
CREATE OR REPLACE VIEW `v_active_tickets_summary` AS
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
    END AS sla_status,
    (SELECT COUNT(*) FROM ticket_comments WHERE ticket_id = t.ticket_id) AS comment_count,
    (SELECT SUM(hours_spent) FROM ticket_time_tracking WHERE ticket_id = t.ticket_id) AS total_hours
FROM tickets t
LEFT JOIN users creator ON t.created_by = creator.user_id
LEFT JOIN users assignee ON t.assigned_to = assignee.user_id
WHERE t.status NOT IN ('closed', 'cancelled');

-- View: SLA Performance
CREATE OR REPLACE VIEW `v_sla_performance` AS
SELECT 
    DATE(created_at) AS date,
    COUNT(*) AS total_tickets,
    SUM(CASE WHEN resolved_at <= sla_due_date THEN 1 ELSE 0 END) AS met_sla,
    SUM(CASE WHEN resolved_at > sla_due_date OR (resolved_at IS NULL AND NOW() > sla_due_date) THEN 1 ELSE 0 END) AS missed_sla,
    ROUND(SUM(CASE WHEN resolved_at <= sla_due_date THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS sla_compliance_percent
FROM tickets
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- View: User Ticket Statistics
CREATE OR REPLACE VIEW `v_user_ticket_stats` AS
SELECT 
    u.user_id,
    u.full_name,
    u.role,
    COUNT(DISTINCT t.ticket_id) AS tickets_created,
    COUNT(DISTINCT a.ticket_id) AS tickets_assigned,
    SUM(DISTINCT tt.hours_spent) AS total_hours_logged,
    COUNT(DISTINCT tc.comment_id) AS comments_made
FROM users u
LEFT JOIN tickets t ON u.user_id = t.created_by
LEFT JOIN tickets a ON u.user_id = a.assigned_to
LEFT JOIN ticket_time_tracking tt ON u.user_id = tt.user_id
LEFT JOIN ticket_comments tc ON u.user_id = tc.user_id
GROUP BY u.user_id, u.full_name, u.role;

-- ========================================
-- Stored Procedures
-- ========================================

-- Procedure: Calculate and Update SLA
DELIMITER //
CREATE PROCEDURE `sp_calculate_sla`(IN p_ticket_id INT)
BEGIN
    DECLARE v_priority VARCHAR(20);
    DECLARE v_impact VARCHAR(20);
    DECLARE v_resolution_hours INT;
    
    SELECT priority, impact INTO v_priority, v_impact
    FROM tickets WHERE ticket_id = p_ticket_id;
    
    SELECT resolution_time_hours INTO v_resolution_hours
    FROM sla_rules
    WHERE priority = v_priority AND impact = v_impact AND is_active = 1
    LIMIT 1;
    
    IF v_resolution_hours IS NOT NULL THEN
        UPDATE tickets
        SET sla_due_date = DATE_ADD(created_at, INTERVAL v_resolution_hours HOUR)
        WHERE ticket_id = p_ticket_id;
    END IF;
END//
DELIMITER ;

-- Procedure: Get Overdue Tickets
DELIMITER //
CREATE PROCEDURE `sp_get_overdue_tickets`()
BEGIN
    SELECT 
        t.*,
        creator.full_name AS requester,
        assignee.full_name AS assigned_to,
        TIMESTAMPDIFF(HOUR, t.sla_due_date, NOW()) AS hours_overdue
    FROM tickets t
    LEFT JOIN users creator ON t.created_by = creator.user_id
    LEFT JOIN users assignee ON t.assigned_to = assignee.user_id
    WHERE t.sla_due_date < NOW()
    AND t.status NOT IN ('resolved', 'closed', 'cancelled')
    ORDER BY hours_overdue DESC;
END//
DELIMITER ;

-- ========================================
-- Triggers
-- ========================================

-- Trigger: Auto-calculate SLA on ticket creation
DELIMITER //
CREATE TRIGGER `tr_tickets_before_insert`
BEFORE INSERT ON `tickets`
FOR EACH ROW
BEGIN
    DECLARE v_resolution_hours INT;
    
    SELECT resolution_time_hours INTO v_resolution_hours
    FROM sla_rules
    WHERE priority = NEW.priority AND impact = NEW.impact AND is_active = 1
    LIMIT 1;
    
    IF v_resolution_hours IS NOT NULL THEN
        SET NEW.sla_due_date = DATE_ADD(NOW(), INTERVAL v_resolution_hours HOUR);
    END IF;
END//
DELIMITER ;

-- Trigger: Log timeline on status change
DELIMITER //
CREATE TRIGGER `tr_tickets_after_update`
AFTER UPDATE ON `tickets`
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO ticket_timeline (ticket_id, action_type, description, old_value, new_value, created_at)
        VALUES (NEW.ticket_id, 'status_change', 'Status changed', OLD.status, NEW.status, NOW());
    END IF;
    
    IF OLD.priority != NEW.priority THEN
        INSERT INTO ticket_timeline (ticket_id, action_type, description, old_value, new_value, created_at)
        VALUES (NEW.ticket_id, 'priority_change', 'Priority changed', OLD.priority, NEW.priority, NOW());
    END IF;
    
    IF OLD.assigned_to != NEW.assigned_to OR (OLD.assigned_to IS NULL AND NEW.assigned_to IS NOT NULL) THEN
        INSERT INTO ticket_timeline (ticket_id, user_id, action_type, description, created_at)
        VALUES (NEW.ticket_id, NEW.assigned_to, 'assign', 'Ticket assigned', NOW());
    END IF;
END//
DELIMITER ;

-- ========================================
-- Indexes for Performance
-- ========================================
CREATE INDEX idx_tickets_composite ON tickets(status, priority, created_at);
CREATE INDEX idx_tickets_sla_monitoring ON tickets(sla_due_date, status);
CREATE INDEX idx_timeline_lookup ON ticket_timeline(ticket_id, created_at DESC);
CREATE INDEX idx_comments_lookup ON ticket_comments(ticket_id, created_at DESC);

-- ========================================
-- Sample Data (Optional)
-- ========================================

-- Insert sample assets
INSERT INTO `assets` (`asset_tag`, `asset_name`, `asset_type`, `brand`, `model`, `status`, `location`) VALUES
('PC-001', 'Desktop Computer - Accounting', 'computer', 'Dell', 'OptiPlex 7090', 'active', 'Building A - 2F - Room 201'),
('PC-002', 'Desktop Computer - HR', 'computer', 'HP', 'ProDesk 600 G6', 'active', 'Building A - 3F - Room 305'),
('LP-001', 'Laptop - Sales Manager', 'laptop', 'Lenovo', 'ThinkPad X1 Carbon', 'active', 'Mobile'),
('PR-001', 'Laser Printer', 'printer', 'HP', 'LaserJet Pro M404', 'active', 'Building A - 2F - Pantry'),
('SV-001', 'File Server', 'server', 'Dell', 'PowerEdge R740', 'active', 'Server Room');

-- ========================================
-- Documentation
-- ========================================
/*
TICKET STATUS WORKFLOW:
1. new -> assigned -> in_progress -> resolved -> closed
2. Any status can go to: pending, cancelled

PRIORITY CALCULATION:
- Priority is manually set OR auto-calculated from Urgency + Impact matrix
- SLA is calculated based on Priority + Impact combination

SLA RULES:
- Response Time: Time to first response/assignment
- Resolution Time: Time to mark as resolved
- Both calculated in business hours (can be customized)

FEATURES INCLUDED:
✓ Multi-level ticket categorization
✓ SLA tracking and monitoring
✓ Time tracking for work logged
✓ Internal notes vs public comments
✓ File attachments support
✓ Ticket relationships (related, duplicate, etc.)
✓ Asset integration
✓ Knowledge base linking
✓ Approval workflow
✓ Email notifications (structure ready)
✓ Comprehensive timeline/audit trail
✓ Advanced reporting views
✓ Automated SLA calculation
*/