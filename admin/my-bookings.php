<?php
session_start();
require_once '../config/database.php';

// ตรวจสอบการ login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$db = getDb();
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// จัดการยกเลิกการจอง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $booking_id = $_POST['booking_id'];
    
    // ตรวจสอบว่าเป็นการจองของผู้ใช้คนนี้
    $check_stmt = $db->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?");
    $check_stmt->bindValue(1, $booking_id, SQLITE3_INTEGER);
    $check_stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
    $result = $check_stmt->execute();
    $booking = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($booking && $booking['status'] !== 'cancelled') {
        // อัปเดตสถานะเป็นยกเลิก
        $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled', updated_at = datetime('now') WHERE booking_id = ?");
        $stmt->bindValue(1, $booking_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            // Log activity
            $log = $db->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'cancel_booking', ?, datetime('now'))");
            $log->bindValue(1, $user_id, SQLITE3_INTEGER);
            $log->bindValue(2, "ยกเลิกการจองห้องประชุม ID: {$booking_id}", SQLITE3_TEXT);
            $log->execute();
            
            Database::checkpoint();
            
            $success_message = "ยกเลิกการจองสำเร็จ";
        } else {
            $error_message = "ไม่สามารถยกเลิกการจองได้";
        }
    } else {
        $error_message = "ไม่พบการจองหรือไม่สามารถยกเลิกได้";
    }
}

// ดึงรายการจองทั้งหมดของผู้ใช้
$bookings_result = $db->prepare("
    SELECT b.*, r.room_name, r.location, r.capacity 
    FROM bookings b
    JOIN meeting_rooms r ON b.room_id = r.room_id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC, b.start_time DESC
");
$bookings_result->bindValue(1, $user_id, SQLITE3_INTEGER);
$bookings_result = $bookings_result->execute();

$bookings = [];
while ($booking = $bookings_result->fetchArray(SQLITE3_ASSOC)) {
    $bookings[] = $booking;
}

// แยกตามสถานะ
$pending_bookings = array_filter($bookings, function($b) { return $b['status'] === 'pending'; });
$approved_bookings = array_filter($bookings, function($b) { return $b['status'] === 'approved'; });
$cancelled_bookings = array_filter($bookings, function($b) { return $b['status'] === 'cancelled'; });
$rejected_bookings = array_filter($bookings, function($b) { return $b['status'] === 'rejected'; });

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการจองของฉัน - Romar Dormitory Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 2em;
            font-weight: 700;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9em;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-value.pending { color: #f39c12; }
        .stat-value.approved { color: #27ae60; }
        .stat-value.cancelled { color: #95a5a6; }
        .stat-value.rejected { color: #e74c3c; }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.95em;
        }

        .bookings-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.5em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .booking-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 5px solid;
        }

        .booking-card.pending { border-left-color: #f39c12; }
        .booking-card.approved { border-left-color: #27ae60; }
        .booking-card.cancelled { border-left-color: #95a5a6; }
        .booking-card.rejected { border-left-color: #e74c3c; }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .booking-room {
            font-size: 1.3em;
            font-weight: 700;
            color: #2c3e50;
        }

        .booking-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            color: white;
        }

        .booking-status.pending { background: #f39c12; }
        .booking-status.approved { background: #27ae60; }
        .booking-status.cancelled { background: #95a5a6; }
        .booking-status.rejected { background: #e74c3c; }

        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .booking-detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-label {
            font-size: 0.85em;
            color: #7f8c8d;
            font-weight: 600;
        }

        .detail-value {
            font-size: 1em;
            color: #2c3e50;
        }

        .booking-purpose {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .purpose-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }

        .empty-state-icon {
            font-size: 4em;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .booking-header {
                flex-direction: column;
                gap: 10px;
            }

            .booking-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📋 รายการจองของฉัน</h1>
            <div>
                <a href="room-booking.php" class="btn btn-primary">📅 จองห้องใหม่</a>
                <a href="dashboard.php" class="btn btn-secondary">← กลับหน้าหลัก</a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value pending"><?php echo count($pending_bookings); ?></div>
                <div class="stat-label">⏳ รออนุมัติ</div>
            </div>
            <div class="stat-card">
                <div class="stat-value approved"><?php echo count($approved_bookings); ?></div>
                <div class="stat-label">✅ อนุมัติแล้ว</div>
            </div>
            <div class="stat-card">
                <div class="stat-value cancelled"><?php echo count($cancelled_bookings); ?></div>
                <div class="stat-label">❌ ยกเลิกแล้ว</div>
            </div>
            <div class="stat-card">
                <div class="stat-value rejected"><?php echo count($rejected_bookings); ?></div>
                <div class="stat-label">🚫 ไม่อนุมัติ</div>
            </div>
        </div>

        <!-- Pending Bookings -->
        <?php if (count($pending_bookings) > 0): ?>
            <div class="bookings-section">
                <div class="section-title">
                    <span>⏳</span>
                    <span>รออนุมัติ (<?php echo count($pending_bookings); ?>)</span>
                </div>

                <?php foreach ($pending_bookings as $booking): ?>
                    <div class="booking-card pending">
                        <div class="booking-header">
                            <div class="booking-room"><?php echo htmlspecialchars($booking['room_name']); ?></div>
                            <div class="booking-status pending">รออนุมัติ</div>
                        </div>

                        <div class="booking-details">
                            <div class="booking-detail-item">
                                <div class="detail-label">📅 วันที่</div>
                                <div class="detail-value"><?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></div>
                            </div>
                            <div class="booking-detail-item">
                                <div class="detail-label">⏰ เวลา</div>
                                <div class="detail-value"><?php echo substr($booking['start_time'], 0, 5); ?> - <?php echo substr($booking['end_time'], 0, 5); ?></div>
                            </div>
                            <div class="booking-detail-item">
                                <div class="detail-label">👥 จำนวนคน</div>
                                <div class="detail-value"><?php echo $booking['num_attendees']; ?> คน</div>
                            </div>
                            <div class="booking-detail-item">
                                <div class="detail-label">📍 สถานที่</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['location']); ?></div>
                            </div>
                        </div>

                        <div class="booking-purpose">
                            <div class="purpose-title">วัตถุประสงค์:</div>
                            <div><?php echo htmlspecialchars($booking['purpose']); ?></div>
                        </div>

                        <form method="POST" style="margin-top: 15px;" onsubmit="return confirm('ต้องการยกเลิกการจองนี้ใช่หรือไม่?')">
                            <input type="hidden" name="action" value="cancel_booking">
                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">❌ ยกเลิกการจอง</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Approved Bookings -->
        <?php if (count($approved_bookings) > 0): ?>
            <div class="bookings-section">
                <div class="section-title">
                    <span>✅</span>
                    <span>อนุมัติแล้ว (<?php echo count($approved_bookings); ?>)</span>
                </div>

                <?php foreach ($approved_bookings as $booking): ?>
                    <div class="booking-card approved">
                        <div class="booking-header">
                            <div class="booking-room"><?php echo htmlspecialchars($booking['room_name']); ?></div>
                            <div class="booking-status approved">อนุมัติแล้ว</div>
                        </div>

                        <div class="booking-details">
                            <div class="booking-detail-item">
                                <div class="detail-label">📅 วันที่</div>
                                <div class="detail-value"><?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></div>
                            </div>
                            <div class="booking-detail-item">
                                <div class="detail-label">⏰ เวลา</div>
                                <div class="detail-value"><?php echo substr($booking['start_time'], 0, 5); ?> - <?php echo substr($booking['end_time'], 0, 5); ?></div>
                            </div>
                            <div class="booking-detail-item">
                                <div class="detail-label">👥 จำนวนคน</div>
                                <div class="detail-value"><?php echo $booking['num_attendees']; ?> คน</div>
                            </div>
                            <div class="booking-detail-item">
                                <div class="detail-label">📍 สถานที่</div>
                                <div class="detail-value"><?php echo htmlspecialchars($booking['location']); ?></div>
                            </div>
                        </div>

                        <div class="booking-purpose">
                            <div class="purpose-title">วัตถุประสงค์:</div>
                            <div><?php echo htmlspecialchars($booking['purpose']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Empty State -->
        <?php if (empty($bookings)): ?>
            <div class="bookings-section">
                <div class="empty-state">
                    <div class="empty-state-icon">📅</div>
                    <h2>ยังไม่มีการจองห้องประชุม</h2>
                    <p style="margin-top: 10px;">เริ่มจองห้องประชุมตอนนี้เลย!</p>
                    <a href="room-booking.php" class="btn btn-primary" style="margin-top: 20px;">📅 จองห้องประชุม</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>