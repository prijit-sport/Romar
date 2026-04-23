-- Migration: Update asset_borrows table to support enhanced borrowing features
-- Date: 2026-03-31
-- Description: Add borrow_location column and other missing fields for tracking borrowing information

-- Add borrow_location column if it doesn't exist
ALTER TABLE `asset_borrows` 
ADD COLUMN `borrow_location` VARCHAR(255) NULL COMMENT 'สถานที่ที่ยืมไป' AFTER `purpose`;

-- Add other missing columns if they don't exist (using conditional approach)
-- These should handle the case where columns might already be added

ALTER TABLE `asset_borrows` 
ADD COLUMN IF NOT EXISTS `expected_return` DATE NULL COMMENT 'กำหนดวันคืน' AFTER `borrow_date`;

ALTER TABLE `asset_borrows` 
ADD COLUMN IF NOT EXISTS `actual_return` DATE NULL COMMENT 'วันที่คืนจริง' AFTER `borrow_date`;

ALTER TABLE `asset_borrows` 
ADD COLUMN IF NOT EXISTS `condition_out` ENUM('good', 'fair', 'poor') DEFAULT 'good' COMMENT 'สภาพตอนยืม' AFTER `purpose`;

ALTER TABLE `asset_borrows` 
ADD COLUMN IF NOT EXISTS `condition_in` ENUM('good', 'fair', 'poor', 'damaged') NULL COMMENT 'สภาพตอนคืน' AFTER `condition_out`;

-- Add index for faster queries on location
ALTER TABLE `asset_borrows` 
ADD INDEX IF NOT EXISTS `idx_borrow_location` (`borrow_location`);

-- Migration completed
-- Note: This migration adds support for tracking where borrowed items are located
