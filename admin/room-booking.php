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

// จัดการการจอง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_room') {
    $room_id = $_POST['room_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $num_attendees = $_POST['num_attendees'];
    $purpose = trim($_POST['purpose']);
    $notes = trim($_POST['notes'] ?? '');
    
    // ตรวจสอบความพร้อมของห้อง (standard overlap: existing.start < new.end AND existing.end > new.start)
    $check_stmt = $db->prepare("
        SELECT COUNT(*) as count FROM bookings 
        WHERE room_id = ? 
        AND booking_date = ? 
        AND status != 'cancelled'
        AND start_time < ? 
        AND end_time > ?
    ");
    $check_stmt->bind_param("isss", $room_id, $booking_date, $end_time, $start_time);
    $check_stmt->execute();
    $row = $check_stmt->get_result()->fetch_assoc();
    
    if ($row['count'] > 0) {
        $error_message = "ห้องนี้ถูกจองในช่วงเวลาดังกล่าวแล้ว กรุณาเลือกเวลาอื่น";
    } else {
        // บันทึกการจอง
        $stmt = $db->prepare("
            INSERT INTO bookings (room_id, user_id, booking_date, start_time, end_time, num_attendees, purpose, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("iisssiss", $room_id, $user_id, $booking_date, $start_time, $end_time, $num_attendees, $purpose, $notes);
        
        if ($stmt->execute()) {
            // Log activity
            $log = $db->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'book_room', ?, NOW())");
            $log_message = "จองห้องประชุมวันที่ {$booking_date} เวลา {$start_time}-{$end_time}";
            $log->bind_param("is", $user_id, $log_message);
            $log->execute();
            
            $success_message = "จองห้องประชุมสำเร็จ! รอการอนุมัติจากผู้ดูแลระบบ";
        } else {
            $error_message = "ไม่สามารถจองห้องได้ กรุณาลองใหม่อีกครั้ง";
        }
    }
}

// ดึงข้อมูลห้องประชุมทั้งหมด
$rooms_result = $db->query("SELECT * FROM meeting_rooms WHERE is_active = 1 ORDER BY room_name");
$rooms = [];
while ($room = $rooms_result->fetch_assoc()) {
    $rooms[] = $room;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จองห้องประชุม - Romar Dormitory Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg,  #065f159c 100%);
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
            box-shadow: 0 8px 32px rgb(0, 0, 0);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #000000;
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
            background: linear-gradient(135deg, #000000 0%, #10ce30 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgb(0, 0, 0);
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

        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .room-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 32px rgb(0, 0, 0);
            transition: all 0.3s ease;
        }

        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgb(255, 255, 255);
        }

        .room-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .room-name {
            font-size: 1.5em;
            font-weight: 700;
            color: #000000;
        }

        .room-capacity {
            background: linear-gradient(135deg, #000000 0%, #10ce30 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
        }

        .room-details {
            margin: 15px 0;
        }

        .room-detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            color: #000000;
        }

        .room-detail-item span:first-child {
            font-weight: 600;
            min-width: 80px;
        }

        .facilities {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .facilities-title {
            font-weight: 600;
            color: #000000;
            margin-bottom: 10px;
        }

        .facilities-list {
            color: #000000;
            line-height: 1.8;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5em;
            font-weight: 700;
            color: #000000;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 2em;
            cursor: pointer;
            color: #000000;
        }

        .modal-close:hover {
            color: #ff0000;
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #000000;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #000000;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .rooms-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📅 จองห้องประชุม</h1>
            <div>
                <a href="my-bookings.php" class="btn btn-primary">📋 รายการจองของฉัน</a>
                <a href="dashboard.php" class="btn btn-secondary">← กลับหน้าหลัก</a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Rooms Grid -->
        <div class="rooms-grid">
            <?php foreach ($rooms as $room): ?>
                <div class="room-card">
                    <div class="room-header">
                        <div class="room-name"><?php echo htmlspecialchars($room['room_name']); ?></div>
                        <div class="room-capacity">👥 <?php echo $room['capacity']; ?> คน</div>
                    </div>

                    <div class="room-details">
                        <div class="room-detail-item">
                            <span>📍 สถานที่:</span>
                            <span><?php echo htmlspecialchars($room['location']); ?></span>
                        </div>
                    </div>

                    <?php if (isset($room['facilities']) && !empty($room['facilities'])): ?>
                        <div class="facilities">
                            <div class="facilities-title">🎯 สิ่งอำนวยความสะดวก:</div>
                            <div class="facilities-list"><?php echo htmlspecialchars($room['facilities']); ?></div>
                        </div>
                    <?php endif; ?>

                    <button class="btn btn-primary" style="width: 100%; margin-top: 15px;" 
                            onclick="openBookingModal(<?php echo $room['room_id']; ?>, '<?php echo htmlspecialchars($room['room_name']); ?>', <?php echo $room['capacity']; ?>)">
                        📅 จองห้องนี้
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">📅 จองห้องประชุม</div>
                <button class="modal-close" onclick="closeBookingModal()">&times;</button>
            </div>

            <div class="modal-body">
                <form method="POST" onsubmit="return validateBooking()">
                    <input type="hidden" name="action" value="book_room">
                    <input type="hidden" name="room_id" id="modal_room_id">

                    <div class="form-group">
                        <label>ห้องที่เลือก:</label>
                        <input type="text" id="modal_room_name" readonly style="background: #f8f9fa;">
                    </div>

                    <div class="form-group">
                        <label>วันที่จอง: *</label>
                        <input type="date" name="booking_date" id="booking_date" required 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>เวลาเริ่ม: *</label>
                            <input type="time" name="start_time" id="start_time" required>
                        </div>

                        <div class="form-group">
                            <label>เวลาสิ้นสุด: *</label>
                            <input type="time" name="end_time" id="end_time" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>จำนวนผู้เข้าร่วม: *</label>
                        <input type="number" name="num_attendees" id="num_attendees" required min="1">
                        <small style="color: #7f8c8d;">ความจุสูงสุด: <span id="max_capacity"></span> คน</small>
                    </div>

                    <div class="form-group">
                        <label>วัตถุประสงค์: *</label>
                        <textarea name="purpose" required rows="3" 
                                  placeholder="ระบุวัตถุประสงค์ในการใช้ห้องประชุม..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>หมายเหตุ:</label>
                        <textarea name="notes" rows="2" 
                                  placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)..."></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeBookingModal()" 
                                style="flex: 1;">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">✅ ยืนยันการจอง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let maxCapacity = 0;

        function openBookingModal(roomId, roomName, capacity) {
            document.getElementById('modal_room_id').value = roomId;
            document.getElementById('modal_room_name').value = roomName;
            document.getElementById('max_capacity').textContent = capacity;
            document.getElementById('num_attendees').max = capacity;
            maxCapacity = capacity;

            document.getElementById('bookingModal').classList.add('active');
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.remove('active');
        }

        function validateBooking() {
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            const numAttendees = parseInt(document.getElementById('num_attendees').value);

            if (startTime >= endTime) {
                alert('เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม');
                return false;
            }

            if (numAttendees > maxCapacity) {
                alert('จำนวนผู้เข้าร่วมเกินความจุของห้อง');
                return false;
            }

            return true;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('bookingModal');
            if (event.target === modal) {
                closeBookingModal();
            }
        }
    </script>
</body>
</html>