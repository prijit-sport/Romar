<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
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

echo json_encode(['count' => (int)$row['cnt']]);
?>