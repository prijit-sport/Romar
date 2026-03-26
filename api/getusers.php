<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Bypass login check for testing
// if (!isLoggedIn()) {
//     http_response_code(401);
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit;
// }


$db = getDB();

$stmt = $db->prepare("SELECT user_id, full_name, username, role FROM users WHERE is_active = 1 ORDER BY full_name");
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($users);
?>

