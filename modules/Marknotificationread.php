<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db     = getDB();
$userId = $_SESSION['user_id'];
$input  = json_decode(file_get_contents('php://input'), true);

// ── กรณี 1: อ่าน notification เดียว ─────────────────────────────
if (isset($input['notif_id'])) {
    $notifId = (int)$input['notif_id'];

    $stmt = $db->prepare("
        UPDATE notification_recipients 
        SET is_read = 1, read_at = NOW()
        WHERE notif_id = ? AND user_id = ?
    ");
    $stmt->bind_param('ii', $notifId, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
}

// ── กรณี 2: อ่านทั้งหมด ──────────────────────────────────────────
elseif (isset($input['mark_all_read']) && $input['mark_all_read'] === true) {
    $stmt = $db->prepare("
        UPDATE notification_recipients 
        SET is_read = 1, read_at = NOW()
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param('i', $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'affected' => $stmt->affected_rows]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}