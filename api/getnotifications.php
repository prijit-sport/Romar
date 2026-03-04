<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

apply_security_headers();
header('Content-Type: application/json; charset=UTF-8');
$requestId = request_id();
$limit = rate_limit_check('api_getnotifications', 60, 60);
if (!$limit['allowed']) {
    security_audit_log('rate_limit_blocked', ['module' => 'api_getnotifications', 'retry_after' => $limit['retry_after']]);
    json_error('Too many requests', 429, $requestId);
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['notifications' => [], 'unread_count' => 0, 'request_id' => $requestId]);
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

// ดึง notifications ทั้งหมดของ user นี้ (ทั้งอ่านแล้วและยังไม่อ่าน)
// แสดงแค่ 20 รายการล่าสุด ใน 7 วัน
$stmt = $db->prepare("
    SELECT 
        n.notif_id,
        n.type,
        n.ticket_id,
        n.comment_id,
        n.message,
        n.created_at,
        t.ticket_number,
        t.title        AS ticket_title,
        t.status       AS ticket_status,
        t.priority     AS ticket_priority,
        u.full_name    AS triggered_by_name,
        u.username     AS triggered_by_username,
        nr.is_read
    FROM notifications n
    INNER JOIN notification_recipients nr 
        ON n.notif_id = nr.notif_id AND nr.user_id = ?
    LEFT JOIN tickets t  ON n.ticket_id  = t.ticket_id
    LEFT JOIN users  u  ON n.triggered_by = u.user_id
    WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY n.created_at DESC
    LIMIT 20
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$notifications  = [];
$unreadCount    = 0;

while ($row = $result->fetch_assoc()) {
    if (!$row['is_read']) $unreadCount++;

    // คำนวณเวลา relative
    $diff = time() - strtotime($row['created_at']);
    if      ($diff < 60)    $timeAgo = 'เมื่อสักครู่';
    elseif  ($diff < 3600)  $timeAgo = floor($diff / 60)   . ' นาทีที่แล้ว';
    elseif  ($diff < 86400) $timeAgo = floor($diff / 3600)  . ' ชั่วโมงที่แล้ว';
    else                    $timeAgo = floor($diff / 86400) . ' วันที่แล้ว';

    $notifications[] = [
        'notif_id'             => (int)$row['notif_id'],
        'type'                 => $row['type'],          // new_ticket | new_comment
        'ticket_id'            => (int)$row['ticket_id'],
        'comment_id'           => $row['comment_id'] ? (int)$row['comment_id'] : null,
        'message'              => $row['message'],
        'ticket_number'        => $row['ticket_number'],
        'ticket_title'         => $row['ticket_title'],
        'ticket_status'        => $row['ticket_status'],
        'ticket_priority'      => $row['ticket_priority'],
        'triggered_by_name'    => $row['triggered_by_name'] ?? $row['triggered_by_username'],
        'created_at'           => $row['created_at'],
        'time_ago'             => $timeAgo,
        'is_read'              => (bool)$row['is_read'],
    ];
}

echo json_encode([
    'notifications' => $notifications,
    'unread_count'  => $unreadCount,
    'request_id'    => $requestId,
]);
