<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

apply_security_headers();
header('Content-Type: application/json; charset=UTF-8');
$requestId = request_id();
$limit = rate_limit_check('api_getnotificationcount', 120, 60);
if (!$limit['allowed']) {
    security_audit_log('rate_limit_blocked', ['module' => 'api_getnotificationcount', 'retry_after' => $limit['retry_after']]);
    json_error('Too many requests', 429, $requestId);
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'request_id' => $requestId]);
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

// นับ notifications ที่ยังไม่ได้อ่านของ user นี้
$stmt = $db->prepare("
    SELECT COUNT(*) as cnt 
    FROM notification_recipients nr
    WHERE nr.user_id = ?
    AND nr.is_read = 0
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

echo json_encode(['count' => (int)$row['cnt'], 'request_id' => $requestId]);
?>
