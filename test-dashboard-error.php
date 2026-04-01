<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    
    $db = getDB();
    $userId = 1;
    
    // Test the exact query from dashboard.php
    $notifStmt = $db->prepare("
        SELECT 
            n.notif_id,
            n.type,
            n.ticket_id,
            n.message,
            n.created_at,
            t.ticket_number,
            t.title         AS ticket_title,
            t.status        AS ticket_status,
            t.priority      AS ticket_priority,
            u.full_name     AS triggered_by_name,
            nr.is_read
        FROM notifications n
        INNER JOIN notification_recipients nr 
            ON n.notif_id = nr.notif_id AND nr.user_id = ?
        LEFT JOIN tickets t ON n.ticket_id  = t.ticket_id
        LEFT JOIN users   u ON n.triggered_by = u.user_id
        WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY n.created_at DESC
        LIMIT 20
    ");
    
    if (!$notifStmt) {
        echo "Prepare error: " . $db->error;
        exit;
    }
    
    $notifStmt->bind_param('i', $userId);
    if (!$notifStmt->execute()) {
        echo "Execute error: " . $notifStmt->error;
        exit;
    }
    
    $notificationsRaw = $notifStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo "Raw notifications count: " . count($notificationsRaw) . "\n";
    
    // Process notifications to add calculated time_ago field
    $notifications = [];
    foreach ($notificationsRaw as $notif) {
        $createdAt = $notif['created_at'];
        if (!$createdAt) {
            echo "Warning: created_at is null\n";
            continue;
        }
        
        $diff = time() - strtotime($createdAt);
        if      ($diff < 60)    $notif['time_ago'] = 'เมื่อสักครู่';
        elseif  ($diff < 3600)  $notif['time_ago'] = floor($diff / 60)   . ' นาทีที่แล้ว';
        elseif  ($diff < 86400) $notif['time_ago'] = floor($diff / 3600)  . ' ชั่วโมงที่แล้ว';
        else                    $notif['time_ago'] = floor($diff / 86400) . ' วันที่แล้ว';
        
        $notifications[] = $notif;
    }
    
    echo "Processed notifications count: " . count($notifications) . "\n";
    echo "Success!\n";
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
