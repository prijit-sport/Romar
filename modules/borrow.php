<?php
/**
 * Standalone Borrow Modal Handler for Assets
 * Used from assetsdetail.php - per BORROW_FEATURE_UPDATE.md
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin only']);
    exit;
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF']);
        exit;
    }

    $asset_id = (int)$_POST['asset_id'];
    $borrower_id = (int)$_POST['borrower_id'];
    $borrow_date = $_POST['borrow_date'];
    $expected_return = $_POST['expected_return'] ?? null;
    $location = sanitize($_POST['location']);
    $purpose = sanitize($_POST['purpose']);
    $condition_out = sanitize($_POST['condition_out']);

    $stmt = $db->prepare("INSERT INTO asset_borrows (asset_id, borrower_id, borrow_date, expected_return, borrow_location, purpose, condition_out, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'borrowed', NOW())");
    $stmt->bind_param('iisssss', $asset_id, $borrower_id, $borrow_date, $expected_return, $location, $purpose, $condition_out);

    if ($stmt->execute()) {
        // Update asset status
        $upd = $db->prepare("UPDATE assets SET status = 'borrowed' WHERE asset_id = ?");
        $upd->bind_param('i', $asset_id);
        $upd->execute();

        echo json_encode(['success' => true, 'message' => 'Borrow recorded']);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'POST only']);
}
?>

