<?php
/**
 * Notification API Test
 * Run: php tests/notification_api_test.php
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Notification API Test ===\n\n";

$db = getDB();

// 1. Check tables exist
$tables = ['notifications', 'notification_recipients'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '{$table}'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table '{$table}' exists\n";
    } else {
        echo "✗ Table '{$table}' MISSING - run database/migrate.php\n";
        exit(1);
    }
}

// 2. Check table columns
$expected = [
    'notifications' => ['notif_id', 'type', 'ticket_id', 'comment_id', 'message', 'triggered_by', 'created_at'],
    'notification_recipients' => ['notif_id', 'user_id', 'is_read', 'read_at']
];

foreach ($expected as $table => $columns) {
    $result = $db->query("SHOW COLUMNS FROM `{$table}`");
    $existing = [];
    while ($row = $result->fetch_assoc()) {
        $existing[] = $row['Field'];
    }
    $missing = array_diff($columns, $existing);
    if (empty($missing)) {
        echo "✓ Table '{$table}' has all required columns\n";
    } else {
        echo "✗ Table '{$table}' missing columns: " . implode(', ', $missing) . "\n";
        exit(1);
    }
}

// 3. Test notifyNewTicket function
require_once __DIR__ . '/../includes/functions_notification.php';

// Find a test user (admin/it_support)
$userResult = $db->query("SELECT user_id FROM users WHERE role IN ('admin', 'it_support') LIMIT 1");
if (!$userResult || $userResult->num_rows === 0) {
    echo "⚠ No admin/it_support user found - skipping insert test\n";
} else {
    $adminUser = $userResult->fetch_assoc()['user_id'];
    
    // Find or create a test ticket
    $ticketResult = $db->query("SELECT ticket_id, ticket_number, title FROM tickets LIMIT 1");
    if (!$ticketResult || $ticketResult->num_rows === 0) {
        echo "⚠ No tickets found - skipping insert test\n";
    } else {
        $ticket = $ticketResult->fetch_assoc();
        $success = notifyNewTicket($db, $ticket['ticket_id'], $ticket['ticket_number'], $ticket['title'], $adminUser);
        if ($success) {
            echo "✓ notifyNewTicket() inserted notification successfully\n";
            $notifId = $db->insert_id;
            
            // Verify recipient was created
            $recipResult = $db->query("SELECT COUNT(*) as count FROM notification_recipients WHERE notif_id = {$notifId}");
            $recipCount = $recipResult->fetch_assoc()['count'];
            if ($recipCount > 0) {
                echo "✓ Notification recipients created: {$recipCount}\n";
            } else {
                echo "✗ No notification recipients created\n";
            }
            
            // Cleanup test notification
            $db->query("DELETE FROM notification_recipients WHERE notif_id = {$notifId}");
            $db->query("DELETE FROM notifications WHERE notif_id = {$notifId}");
            echo "✓ Test notification cleaned up\n";
        } else {
            echo "✗ notifyNewTicket() failed\n";
        }
    }
}

echo "\n=== All tests passed ===\n";

