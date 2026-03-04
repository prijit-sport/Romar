<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

apply_security_headers();
header('Content-Type: application/json; charset=UTF-8');
$requestId = request_id();
$limit = rate_limit_check('api_marknotificationread', 60, 60);
if (!$limit['allowed']) {
    security_audit_log('rate_limit_blocked', ['module' => 'api_marknotificationread', 'retry_after' => $limit['retry_after']]);
    json_error('Too many requests', 429, $requestId);
}

if (!isset($_SESSION['user_id'])) {
    security_audit_log('access_denied', ['module' => 'api_marknotificationread', 'reason' => 'unauthorized']);
    json_error('Unauthorized', 401, $requestId);
    exit;
}

$db = getDB();
$userId = (int) $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    $input = [];
}

if (isset($input['notif_id'])) {
    $notifId = (int) $input['notif_id'];

    $stmt = $db->prepare("
        UPDATE notification_recipients
        SET is_read = 1, read_at = NOW()
        WHERE notif_id = ? AND user_id = ?
    ");
    $stmt->bind_param('ii', $notifId, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'request_id' => $requestId]);
    } else {
        error_log('marknotificationread.php single update failed: ' . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Update failed', 'request_id' => $requestId]);
    }
    exit;
}

if (!empty($input['mark_all_read'])) {
    $stmt = $db->prepare("
        UPDATE notification_recipients
        SET is_read = 1, read_at = NOW()
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param('i', $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'affected' => $stmt->affected_rows, 'request_id' => $requestId]);
    } else {
        error_log('marknotificationread.php mark all failed: ' . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Update failed', 'request_id' => $requestId]);
    }
    exit;
}

json_error('Invalid request', 400, $requestId);
