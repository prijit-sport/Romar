<?php
/**
 * Run notification table migration
 */
require_once __DIR__ . '/../config/database.php';

echo "=== Running Notification Migration ===\n";

$db = getDB();
$sql = file_get_contents(__DIR__ . '/../database/migrations/002_update_notification_tables.sql');

// Remove comments
$lines = explode("\n", $sql);
$clean = [];
foreach ($lines as $line) {
    $trimmed = trim($line);
    if (empty($trimmed) || strpos($trimmed, '--') === 0) continue;
    $clean[] = $line;
}
$sql = implode("\n", $clean);

// Split by semicolon but handle DELIMITER blocks
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $stmt) {
    if (empty($stmt)) continue;
    try {
        if ($db->query($stmt)) {
            echo "OK: " . substr(str_replace(["\n", "\r"], " ", $stmt), 0, 60) . "...\n";
        } else {
            echo "SKIP: " . substr(str_replace(["\n", "\r"], " ", $stmt), 0, 60) . "... (" . $db->error . ")\n";
        }
    } catch (Exception $e) {
        echo "SKIP: " . substr(str_replace(["\n", "\r"], " ", $stmt), 0, 60) . "... (" . $e->getMessage() . ")\n";
    }
}

echo "\nMigration complete.\n";

