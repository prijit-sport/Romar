<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

// ตรวจสอบการ login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$user = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF']);
$success_message = '';
$error_message = '';
csrf_token();

// จัดการการจอง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_room') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid CSRF token.';
    } else {
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
}
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
    <title>จองห้องประชุม - Romar</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/admin-theme.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script src="../assets/js/room-calendar.js"></script>

    <style>
        .room-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.25s ease;
            border: 1px solid var(--border-faint);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .room-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.18);
        }

        .room-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 1rem;
        }

        .room-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .room-capacity {
            background: linear-gradient(135deg, var(--blue), var(--navy));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 1.25rem;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .room-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .room-detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .room-detail-item span:first-child {
            font-weight: 600;
            min-width: 80px;
        }

        .facilities {
            margin: 0.5rem 0;
            padding: 1rem;
            background: rgba(59, 130, 246, 0.08);
            border-radius: var(--radius-md);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .facilities-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .facilities-list {
            color: var(--text-muted);
            line-height: 1.6;
            font-size: 0.9rem;
        }

        .content-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
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
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 650px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-faint);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s ease;
        }

        .modal-close:hover {
            color: #ef4444;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-family: 'Sarabun', sans-serif;
            background: var(--card-bg);
            color: var(--text-dark);
            transition: all 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group small {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .page-title-block {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-icon {
            font-size: 2rem;
        }

        @media (max-width: 768px) {
            .rooms-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .room-header {
                flex-direction: column;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">🏢</div>
                <div>
                    <div class="brand-name">Romar</div>
                    <div class="brand-subtitle">Dormitory</div>
                </div>
            </div>

            <div class="nav-wrapper">
                <nav class="sidebar-nav">
                    <ul>
                        <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                            <a href="dashboard.php">📊 Dashboard</a>
                        </li>

                        <?php if ($user['role'] === 'admin'): ?>
                        <li class="menu-section">การจัดการ</li>
                        <li class="<?php echo $current_page == 'meeting-rooms.php' ? 'active' : ''; ?>">
                            <a href="meeting-rooms.php">🏢 จัดการห้องประชุม</a>
                        </li>
                        <li class="<?php echo $current_page == 'documents.php' ? 'active' : ''; ?>">
                            <a href="documents.php">📄 จัดการเอกสาร</a>
                        </li>
                        <?php endif; ?>

                        <li class="menu-section">ฟีเจอร์</li>
                        <li class="active">
                            <a href="room-booking.php">📅 จองห้องประชุม</a>
                        </li>
                        <li class="<?php echo $current_page == 'announcements.php' ? 'active' : ''; ?>">
                            <a href="announcements.php">📢 ข่าวสาร</a>
                        </li>
                        <li class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                            <a href="../modules/tickets.php">🎫 IT Tickets</a>
                        </li>
                        <?php if ($user['role'] !== 'admin'): ?>
                        <li class="<?php echo $current_page == 'userdocuments.php' ? 'active' : ''; ?>">
                            <a href="userdocuments.php">📄 เอกสาร</a>
                        </li>
                        <?php endif; ?>

                        <li class="menu-section">ระบบ</li>
                        <li class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                            <a href="settings.php">⚙️ ตั้งค่า</a>
                        </li>
                        <li>
                            <a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-wrapper">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-title-block">
                        <div class="page-icon">📅</div>
                        <div>
                            <h1>จองห้องประชุม</h1>
                            <p class="page-subtitle">เลือกห้องประชุมที่ต้องการและทำการจอง</p>
                        </div>
                    </div>
                    <div class="user-info">
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            <div class="user-role"><?php echo $user['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน'; ?></div>
                        </div>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                    </div>
                </div>

                <!-- Alerts -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success show">
                        ✓ <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error show">
                        ✕ <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Calendar View -->
                <div class="card" style="height:500px;margin-bottom:2rem;">
                    <div class="card-header"><h3><i class="fas fa-calendar"></i> ปฏิทินห้องประชุม</h3></div>
                    <div class="card-body">
                        <div id="roomCalendar"></div>
                    </div>
                </div>
                
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

                            <button class="btn btn-primary" style="width: 100%; margin-top: auto;" 
                                    onclick="openBookingModal(<?php echo $room['room_id']; ?>, '<?php echo htmlspecialchars($room['room_name']); ?>', <?php echo $room['capacity']; ?>)">
                                📅 จองห้องนี้
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
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
                    <?php echo csrf_input(); ?>

                    <div class="form-group">
                        <label for="modal_room_name">ห้องที่เลือก:</label>
                        <input type="text" id="modal_room_name" readonly>
                    </div>

                    <div class="form-group">
                        <label for="booking_date">วันที่จอง: *</label>
                        <input type="date" name="booking_date" id="booking_date" required 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_time">เวลาเริ่ม: *</label>
                            <input type="time" name="start_time" id="start_time" required>
                        </div>

                        <div class="form-group">
                            <label for="end_time">เวลาสิ้นสุด: *</label>
                            <input type="time" name="end_time" id="end_time" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="num_attendees">จำนวนผู้เข้าร่วม: *</label>
                        <input type="number" name="num_attendees" id="num_attendees" required min="1">
                        <small>ความจุสูงสุด: <span id="max_capacity"></span> คน</small>
                    </div>

                    <div class="form-group">
                        <label for="booking_purpose">วัตถุประสงค์: *</label>
                        <textarea name="purpose" id="booking_purpose" required rows="3" 
                                  placeholder="ระบุวัตถุประสงค์ในการใช้ห้องประชุม..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="booking_notes">หมายเหตุ:</label>
                        <textarea name="notes" id="booking_notes" rows="2" 
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
