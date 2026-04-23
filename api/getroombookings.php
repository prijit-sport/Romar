<?php
/**
 * API: Get Room Bookings for FullCalendar
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$db = getDB();

$stmt = $db->prepare("
    SELECT b.*, r.room_name, u.full_name 
    FROM bookings b 
    JOIN meeting_rooms r ON b.room_id = r.room_id 
    JOIN users u ON b.user_id = u.user_id 
    WHERE b.booking_date >= CURDATE() - INTERVAL 30 DAY
    ORDER BY b.booking_date, b.start_time
");

$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($bookings);
?>

