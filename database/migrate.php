<?php
/**
 * Database Migration Runner
 * ข้อมูลที่ใช้ในการอัปเดตฐานข้อมูล
 */

require_once __DIR__ . '/../config/database.php';




$db = getDB();

// Migration: 001_update_asset_borrows_table.sql
$migrations = [
    '001_update_asset_borrows_table' => [
        "ALTER TABLE `asset_borrows` ADD COLUMN `borrow_location` VARCHAR(255) NULL COMMENT 'สถานที่ที่ยืมไป' AFTER `purpose`",
        "ALTER TABLE `asset_borrows` ADD COLUMN `expected_return` DATE NULL COMMENT 'กำหนดวันคืน' AFTER `borrow_date`",
        "ALTER TABLE `asset_borrows` ADD COLUMN `actual_return` DATE NULL COMMENT 'วันที่คืนจริง' AFTER `borrow_date`",
        "ALTER TABLE `asset_borrows` ADD COLUMN `condition_out` ENUM('good', 'fair', 'poor') DEFAULT 'good' COMMENT 'สภาพตอนยืม' AFTER `purpose`",
        "ALTER TABLE `asset_borrows` ADD COLUMN `condition_in` ENUM('good', 'fair', 'poor', 'damaged') NULL COMMENT 'สภาพตอนคืน' AFTER `condition_out`",
    ]
];

echo "Starting database migrations...\n";
$successCount = 0;
$errorCount = 0;

foreach ($migrations as $name => $queries) {
    echo "\n--- Running Migration: $name ---\n";
    
    foreach ($queries as $query) {
        try {
            if ($db->query($query)) {
                echo "✓ " . substr($query, 0, 80) . "...\n";
                $successCount++;
            } else {
                // Check if error is about column already existing
                if (strpos($db->error, 'Duplicate column') !== false) {
                    echo "⊙ " . substr($query, 0, 80) . "... (already exists, skipping)\n";
                    $successCount++;
                } else {
                    echo "✗ " . substr($query, 0, 80) . "...\n";
                    echo "  Error: " . $db->error . "\n";
                    $errorCount++;
                }
            }
        } catch (Exception $e) {
            echo "✗ " . substr($query, 0, 80) . "...\n";
            echo "  Error: " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
}

echo "\n--- Migration Summary ---\n";
echo "Successful: $successCount\n";
echo "Errors: $errorCount\n";

if ($errorCount === 0) {
    echo "\n✓ All migrations completed successfully!\n";
} else {
    echo "\n✗ Some migrations failed. Please check the errors above.\n";
}

$db->close();
?>
