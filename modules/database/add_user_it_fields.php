<?php
/**
 * Migration: Add IT fields to users table
 * Run once to add phone, department, position to users
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

$db = getDB();
$migration_version = '20260304_add_user_it_fields';

try {
    // Check if already run
    $check = $db->prepare("SHOW COLUMNS FROM users LIKE 'department'");
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo "✓ Migration already applied (department exists)\n";
        exit(0);
    }

    // Add phone
    $sql1 = "ALTER TABLE users ADD COLUMN `phone` VARCHAR(20) NULL AFTER `email`";
    $db->query($sql1);
    echo "✓ Added phone column\n";

    // Add department
    $sql2 = "ALTER TABLE users ADD COLUMN `department` VARCHAR(100) NULL AFTER `phone`";
    $db->query($sql2);
    echo "✓ Added department column\n";

    // Add position
    $sql3 = "ALTER TABLE users ADD COLUMN `position` VARCHAR(100) NULL AFTER `department`";
    $db->query($sql3);
    echo "✓ Added position column\n";

    // Add indexes
    $db->query("CREATE INDEX idx_phone ON users(phone)");
    echo "✓ Added phone index\n";
    $db->query("CREATE INDEX idx_department ON users(department)");
    echo "✓ Added department index\n";

    echo "✅ Migration completed successfully!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

