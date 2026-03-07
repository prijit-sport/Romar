<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);

// Check login and admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$message = '';
$messageType = '';
csrf_token();

// รับ flash message จาก session หลัง redirect
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'];
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_message'] = 'Invalid CSRF token.';
    $_SESSION['flash_type'] = 'error';
    header('Location: meeting-rooms.php');
    exit;
}

// Handle Add/Edit/Delete Room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $roomName = sanitize($_POST['room_name']);
        $capacity = (int)$_POST['capacity'];
        $location = sanitize($_POST['location']);
        $facilities = sanitize($_POST['facilities']);
        $image = isset($_POST['image']) ? sanitize($_POST['image']) : '';
        
        $stmt = $db->prepare("INSERT INTO meeting_rooms (room_name, capacity, location, facilities, image, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $stmt->bind_param('sisss', $roomName, $capacity, $location, $facilities, $image);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'เพิ่มห้องประชุม', 'Meeting Rooms', "เพิ่มห้อง: $roomName");
            $_SESSION['flash_message'] = 'เพิ่มห้องประชุมสำเร็จ!';
            $_SESSION['flash_type'] = 'success';
            header('Location: meeting-rooms.php');
            exit;
        } else {
            $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'edit') {
        $roomId = (int)$_POST['room_id'];
        $roomName = sanitize($_POST['room_name']);
        $capacity = (int)$_POST['capacity'];
        $location = sanitize($_POST['location']);
        $facilities = sanitize($_POST['facilities']);
        $image = isset($_POST['image']) ? sanitize($_POST['image']) : '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $db->prepare("UPDATE meeting_rooms SET room_name = ?, capacity = ?, location = ?, facilities = ?, image = ?, is_active = ? WHERE room_id = ?");
        $stmt->bind_param('sisssii', $roomName, $capacity, $location, $facilities, $image, $isActive, $roomId);
        
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'แก้ไขห้องประชุม', 'Meeting Rooms', "แก้ไขห้อง ID: $roomId");
            $_SESSION['flash_message'] = 'แก้ไขห้องประชุมสำเร็จ!';
            $_SESSION['flash_type'] = 'success';
            header('Location: meeting-rooms.php');
            exit;
        } else {
            $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'delete') {
        $roomId = (int)$_POST['room_id'];
        
        // เช็คว่ามีการจองอยู่หรือไม่
        $checkStmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE room_id = ? AND status != 'cancelled'");
        $checkStmt->bind_param('i', $roomId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkRow = $checkResult->fetch_assoc();
        
        if ($checkRow['count'] > 0) {
            $message = 'ไม่สามารถลบได้! มีการจองห้องนี้อยู่';
            $messageType = 'error';
        } else {
            $stmt = $db->prepare("DELETE FROM meeting_rooms WHERE room_id = ?");
            $stmt->bind_param('i', $roomId);
            
            if ($stmt->execute()) {
                logActivity($_SESSION['user_id'], 'ลบห้องประชุม', 'Meeting Rooms', "ลบห้อง ID: $roomId");
                $_SESSION['flash_message'] = 'ลบห้องประชุมสำเร็จ!';
                $_SESSION['flash_type'] = 'success';
                header('Location: meeting-rooms.php');
                exit;
            } else {
                $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
                $messageType = 'error';
            }
        }
    } elseif ($_POST['action'] === 'approve') {
        $bookingId = (int)$_POST['booking_id'];
        $stmt = $db->prepare("UPDATE bookings SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE booking_id = ? AND status = 'pending'");
        $stmt->bind_param('ii', $_SESSION['user_id'], $bookingId);
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'อนุมัติจอง', 'Bookings', "อนุมัติ Booking ID: $bookingId");
            $_SESSION['flash_message'] = 'อนุมัติการจองสำเร็จ!';
            $_SESSION['flash_type'] = 'success';
            header('Location: meeting-rooms.php');
            exit;
        } else {
            $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'reject') {
        $bookingId = (int)$_POST['booking_id'];
        $stmt = $db->prepare("UPDATE bookings SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE booking_id = ? AND status = 'pending'");
        $stmt->bind_param('ii', $_SESSION['user_id'], $bookingId);
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'ปฏิเสทธิ์จอง', 'Bookings', "ปฏิเสทธิ์ Booking ID: $bookingId");
            $_SESSION['flash_message'] = 'ปฏิเสทธิ์การจองสำเร็จ!';
            $_SESSION['flash_type'] = 'success';
            header('Location: meeting-rooms.php');
            exit;
        } else {
            $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
            $messageType = 'error';
        }
    }
}
$rooms = $db->query("SELECT * FROM meeting_rooms ORDER BY room_name ASC")->fetch_all(MYSQLI_ASSOC);

// ดึง pending bookings สำหรับ อนุมัติ พร้อม JOIN room กับ user
$pendingBookings = $db->query("
    SELECT b.*, mr.room_name, u.username 
    FROM bookings b 
    JOIN meeting_rooms mr ON b.room_id = mr.room_id 
    JOIN users u ON b.user_id = u.user_id 
    WHERE b.status = 'pending' 
    ORDER BY b.booking_date ASC, b.start_time ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการห้องประชุม - Romar</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #065f159c;
            color: #000000;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #10ce30 0%, #000000 100%);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgb(0, 0, 0);
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 25px 20px;
            border-bottom: 1px solid rgb(255, 255, 255);
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }

        .brand-icon {
            font-size: 2em;
        }

        .brand-name {
            font-size: 1.5em;
            font-weight: 700;
        }

        .brand-subtitle {
            color: #000000;
            font-size: 1em;
            opacity: 0.8;
        }

        .sidebar-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: rgb(255, 255, 255);
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .sidebar-nav li.active a {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left: 4px solid #000000;
        }

        .menu-section {
            padding: 20px 20px 10px;
            color: rgb(255, 255, 255);
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }

        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h1 {
            font-size: 1.8em;
            color: #0a0a0a;
            font-weight: 600;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #000000 0%, #10ce30 100%);
            color: white;
            box-shadow: 0 4px 6px rgb(0, 0, 0);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgb(0, 0, 0);
        }

        .btn-secondary {
            background: #718096;
            color: white;
        }

        .btn-success {
            background: #10ce30;
            color: white;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9em;
        }

        /* Cards Grid */
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .room-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            overflow: hidden;
            transition: all 0.3s;
        }

        .room-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgb(255, 255, 255);
        }

        .room-header {
            padding: 20px;
            background: linear-gradient(135deg, #070707 0%, #10ce30 100%);
            color: white;
        }

        .room-name {
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .room-location {
            opacity: 0.9;
            font-size: 0.95em;
        }

        .room-body {
            padding: 20px;
        }

        .room-info {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-icon {
            font-size: 1.2em;
        }

        .info-text {
            font-size: 0.95em;
            color: #0067f7;
        }

        .room-facilities {
            margin-bottom: 15px;
        }

        .facilities-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #000000;
        }

        .facilities-list {
            color: #000000;
            font-size: 0.9em;
            line-height: 1.6;
        }

        .room-actions {
            display: flex;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5em;
            font-weight: 600;
            color: #000000;
        }

        .modal-close {
            font-size: 1.5em;
            cursor: pointer;
            color: #000000;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #ef4444;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #000000;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 3px rgb(255, 255, 255);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* Pending Bookings Section */
        .section-title {
            font-size: 1.4em;
            font-weight: 600;
            color: #000000;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pending-count {
            background: #f59e0b;
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: 600;
        }

        .pending-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #eeda88;
        }

        .pending-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
        }

        .pending-table th {
            text-align: left;
            padding: 12px 14px;
            background: #fef3c7;
            color: #92400e;
            font-weight: 600;
            border-bottom: 2px solid #f59e0b;
            white-space: nowrap;
        }

        .pending-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
            color: #050505;
        }

        .pending-table tr:last-child td {
            border-bottom: none;
        }

        .pending-table tr:hover td {
            background: #fffbeb;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .btn-approve {
            background: #10b981;
            color: white;
            padding: 7px 14px;
            border: none;
            border-radius: 6px;
            font-size: 0.88em;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Sarabun', sans-serif;
        }

        .btn-approve:hover { background: #059669; }

        .btn-reject {
            background: #ef4444;
            color: white;
            padding: 7px 14px;
            border: none;
            border-radius: 6px;
            font-size: 0.88em;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Sarabun', sans-serif;
        }

        .btn-reject:hover { background: #dc2626; }

        .action-btns { display: flex; gap: 8px; }

        .empty-pending {
            text-align: center;
            padding: 30px;
            color: #000000;
            font-size: 1.05em;
        }

        @media (max-width: 768px) {
            .sidebar { margin-left: -260px; }
            .main-content { margin-left: 0; padding: 15px; }
            .rooms-grid { grid-template-columns: 1fr; }
            .pending-table { font-size: 0.85em; }
            .pending-table th, .pending-table td { padding: 8px 10px; }
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

            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <li class="menu-section">การจัดการ</li>
                    <li><a href="users-management.php">👥 จัดการผู้ใช้</a></li>
                    <li class="active"><a href="meeting-rooms.php">🏢 จัดการห้องประชุม</a></li>
                    <li><a href="documents.php">📄 จัดการเอกสาร</a></li>
                    <li class="menu-section">ฟีเจอร์</li>
                    <li><a href="room-booking.php">📅 จองห้องประชุม</a></li>
                    <li><a href="announcements.php">📢 ข่าวสาร</a></li>
                     <li class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                        <a href="../modules/tickets.php">🎫 IT Tickets</a>
                    </li>
                    <li class="menu-section">ระบบ</li>
                    <li><a href="settings.php">⚙️ ตั้งค่า</a></li>
                    <li><a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>🏢 จัดการห้องประชุม</h1>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    ➕ เพิ่มห้องประชุม
                </button>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> show">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- ===== Pending Bookings สำหรับ อนุมัติ/ปฏิเสท ===== -->
            <div class="pending-section">
                <div class="section-title">
                    ⏳ การจองที่รอการอนุมัติ
                    <?php if (count($pendingBookings) > 0): ?>
                        <span class="pending-count"><?php echo count($pendingBookings); ?> รายการ</span>
                    <?php endif; ?>
                </div>

                <?php if (count($pendingBookings) === 0): ?>
                    <div class="empty-pending">✅ ไม่มีการจองที่รอการอนุมัติ</div>
                <?php else: ?>
                    <table class="pending-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ห้องประชุม</th>
                                <th>ผู้จอง</th>
                                <th>วันที่จอง</th>
                                <th>เวลา</th>
                                <th>วัตถุประสงค์</th>
                                <th>จำนวนผู้เข้าร่วม</th>
                                <th>การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingBookings as $i => $booking): ?>
                            <tr>
                                <td><?php echo $i + 1; ?></td>
                                <td><?php echo htmlspecialchars($booking['room_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['username']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></td>
                                <td><?php echo date('H:i', strtotime($booking['start_time'])); ?> - <?php echo date('H:i', strtotime($booking['end_time'])); ?> น.</td>
                                <td><?php echo htmlspecialchars($booking['purpose']); ?></td>
                                <td><?php echo $booking['num_attendees']; ?> คน</td>
                                <td>
                                    <div class="action-btns">
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <?php echo csrf_input(); ?>
                                            <button type="submit" class="btn-approve" onclick="return confirm('อนุมัติการจองนี้?')">✅ อนุมัติ</button>
                                        </form>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <?php echo csrf_input(); ?>
                                            <button type="submit" class="btn-reject" onclick="return confirm('ปฏิเสทธิ์การจองนี้?')">❌ ปฏิเสท</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="rooms-grid">
                <?php foreach ($rooms as $room): ?>
                <div class="room-card" data-room='<?php echo json_encode($room, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP); ?>'>
                    <div class="room-header">
                        <div class="room-name"><?php echo htmlspecialchars($room['room_name']); ?></div>
                        <div class="room-location">📍 <?php echo htmlspecialchars($room['location']); ?></div>
                    </div>
                    <div class="room-body">
                        <div class="room-info">
                            <div class="info-item">
                                <span class="info-icon">👥</span>
                                <span class="info-text"><?php echo $room['capacity']; ?> คน</span>
                            </div>
                            <div class="info-item">
                                <span class="badge badge-<?php echo $room['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $room['is_active'] ? '✅ เปิดใช้งาน' : '❌ ปิดใช้งาน'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="room-facilities">
                            <div class="facilities-label">🔧 สิ่งอำนวยความสะดวก:</div>
                            <div class="facilities-list"><?php echo nl2br(htmlspecialchars($room['facilities'])); ?></div>
                        </div>
                       
                        <div class="room-actions">
                            <button class="btn btn-secondary btn-sm" onclick="openEditModal(this)" style="flex: 1;">
                                ✏️ แก้ไข
                            </button>
                            <button class="btn btn-sm" style="flex: 1; background: #ef4444; color: white;" onclick="deleteRoom(<?php echo $room['room_id']; ?>)">
                                🗑️ ลบ
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">➕ เพิ่มห้องประชุม</h2>
                <span class="modal-close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <?php echo csrf_input(); ?>
                    
                    <div class="form-group">
                        <label class="form-label" for="add_room_name">ชื่อห้องประชุม *</label>
                        <input type="text" name="room_name" id="add_room_name" class="form-control" autocomplete="off" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_capacity">ความจุ (คน) *</label>
                        <input type="number" name="capacity" id="add_capacity" class="form-control" autocomplete="off" min="1" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_location">สถานที่ *</label>
                        <input type="text" name="location" id="add_location" class="form-control" autocomplete="off" placeholder="เช่น ชั้น 2" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_facilities">สิ่งอำนวยความสะดวก *</label>
                        <textarea name="facilities" id="add_facilities" class="form-control" autocomplete="off" placeholder="เช่น โปรเจคเตอร์, ไวท์บอร์ด, Wi-Fi" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_image">รูปห้อง (URL)</label>
                        <input type="text" name="image" id="add_image" class="form-control" autocomplete="off" placeholder="เช่น images/room1.jpg">
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">✅ บันทึก</button>
                        <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('addModal')">❌ ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">✏️ แก้ไขห้องประชุม</h2>
                <span class="modal-close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="room_id" id="edit_room_id">
                    <?php echo csrf_input(); ?>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_room_name">ชื่อห้องประชุม *</label>
                        <input type="text" name="room_name" id="edit_room_name" class="form-control" autocomplete="off" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_capacity">ความจุ (คน) *</label>
                        <input type="number" name="capacity" id="edit_capacity" class="form-control" autocomplete="off" min="1" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_location">สถานที่ *</label>
                        <input type="text" name="location" id="edit_location" class="form-control" autocomplete="off" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_facilities">สิ่งอำนวยความสะดวก *</label>
                        <textarea name="facilities" id="edit_facilities" class="form-control" autocomplete="off" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_image">รูปห้อง (URL)</label>
                        <input type="text" name="image" id="edit_image" class="form-control" autocomplete="off" placeholder="เช่น images/room1.jpg">
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1" style="width: 20px; height: 20px;">
                            <label for="edit_is_active">เปิดใช้งาน</label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">✅ บันทึก</button>
                        <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('editModal')">❌ ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="room_id" id="delete_room_id">
        <?php echo csrf_input(); ?>
    </form>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function openEditModal(btn) {
            const room = JSON.parse(btn.closest('.room-card').dataset.room);
            document.getElementById('edit_room_id').value = room.room_id;
            document.getElementById('edit_room_name').value = room.room_name;
            document.getElementById('edit_capacity').value = room.capacity;
            document.getElementById('edit_location').value = room.location;
            document.getElementById('edit_facilities').value = room.facilities;
            document.getElementById('edit_image').value = room.image || '';
            document.getElementById('edit_is_active').checked = room.is_active == 1;
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function deleteRoom(roomId) {
            if (confirm('คุณแน่ใจหรือไม่ที่จะลบห้องประชุมนี้?')) {
                document.getElementById('delete_room_id').value = roomId;
                document.getElementById('deleteForm').submit();
            }
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) alert.classList.remove('show');
        }, 5000);
    </script>
</body>
</html>
