<?php
session_start();
require_once '../config/database.php';

// ตรวจสอบการ login และสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$db = getDb();
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// จัดการอนุมัติการจอง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'approve_booking') {
        $booking_id = $_POST['booking_id'];
        
        $stmt = $db->prepare("UPDATE bookings SET status = 'approved', approved_by = ?, approved_at = datetime('now'), updated_at = datetime('now') WHERE booking_id = ?");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $booking_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            Database::checkpoint();
            $success_message = "อนุมัติการจองสำเร็จ";
        } else {
            $error_message = "ไม่สามารถอนุมัติการจองได้";
        }
    }
    
    if ($_POST['action'] === 'reject_booking') {
        $booking_id = $_POST['booking_id'];
        
        $stmt = $db->prepare("UPDATE bookings SET status = 'rejected', approved_by = ?, approved_at = datetime('now'), updated_at = datetime('now') WHERE booking_id = ?");
        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(2, $booking_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            Database::checkpoint();
            $success_message = "ไม่อนุมัติการจองแล้ว";
        } else {
            $error_message = "ไม่สามารถดำเนินการได้";
        }
    }
    
    // จัดการห้องประชุม
    if ($_POST['action'] === 'add_room') {
        $room_name = trim($_POST['room_name']);
        $capacity = $_POST['capacity'];
        $location = trim($_POST['location']);
        $facilities = trim($_POST['facilities']);
        
        $stmt = $db->prepare("INSERT INTO meeting_rooms (room_name, capacity, location, facilities) VALUES (?, ?, ?, ?)");
        $stmt->bindValue(1, $room_name, SQLITE3_TEXT);
        $stmt->bindValue(2, $capacity, SQLITE3_INTEGER);
        $stmt->bindValue(3, $location, SQLITE3_TEXT);
        $stmt->bindValue(4, $facilities, SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            Database::checkpoint();
            $success_message = "เพิ่มห้องประชุมสำเร็จ";
        } else {
            $error_message = "ไม่สามารถเพิ่มห้องประชุมได้";
        }
    }
    
    if ($_POST['action'] === 'toggle_room_status') {
        $room_id = $_POST['room_id'];
        $new_status = $_POST['new_status'];
        
        $stmt = $db->prepare("UPDATE meeting_rooms SET is_active = ?, updated_at = datetime('now') WHERE room_id = ?");
        $stmt->bindValue(1, $new_status, SQLITE3_INTEGER);
        $stmt->bindValue(2, $room_id, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            Database::checkpoint();
            $success_message = $new_status ? "เปิดใช้งานห้องแล้ว" : "ปิดใช้งานห้องแล้ว";
        }
    }
}

// ดึงรายการจองที่รออนุมัติ
$pending_bookings = $db->query("
    SELECT b.*, r.room_name, r.location, u.full_name as user_name
    FROM bookings b
    JOIN meeting_rooms r ON b.room_id = r.room_id
    JOIN users u ON b.user_id = u.user_id
    WHERE b.status = 'pending'
    ORDER BY b.booking_date ASC, b.start_time ASC
");

// ดึงรายการห้องประชุมทั้งหมด
$rooms = $db->query("SELECT * FROM meeting_rooms ORDER BY room_name");

// สถิติ
$stats = [
    'total_rooms' => $db->querySingle("SELECT COUNT(*) FROM meeting_rooms"),
    'active_rooms' => $db->querySingle("SELECT COUNT(*) FROM meeting_rooms WHERE is_active = 1"),
    'pending_bookings' => $db->querySingle("SELECT COUNT(*) FROM bookings WHERE status = 'pending'"),
    'approved_today' => $db->querySingle("SELECT COUNT(*) FROM bookings WHERE status = 'approved' AND booking_date = date('now')")
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการห้องประชุม - Admin</title>
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

        .btn-success {
            background: #27ae60;
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 1em;
        }

        .section {
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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-active {
            background: #d4edda;
            color: #155724;
        }

        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
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
            color: #2c3e50;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 2em;
            cursor: pointer;
            color: #999;
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
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Sarabun', sans-serif;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.9em;
            }

            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🏢 จัดการห้องประชุม</h1>
            <div>
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
                <div class="stat-value"><?php echo $stats['total_rooms']; ?></div>
                <div class="stat-label">🏢 ห้องประชุมทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_rooms']; ?></div>
                <div class="stat-label">✅ ห้องที่ใช้งานได้</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending_bookings']; ?></div>
                <div class="stat-label">⏳ รออนุมัติ</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['approved_today']; ?></div>
                <div class="stat-label">📅 จองวันนี้</div>
            </div>
        </div>

        <!-- Pending Bookings -->
        <div class="section">
            <div class="section-title">
                <span>⏳</span>
                <span>รายการจองรออนุมัติ</span>
            </div>

            <?php
            $pending_array = [];
            while ($booking = $pending_bookings->fetchArray(SQLITE3_ASSOC)) {
                $pending_array[] = $booking;
            }
            ?>

            <?php if (count($pending_array) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ผู้จอง</th>
                            <th>ห้อง</th>
                            <th>วันที่</th>
                            <th>เวลา</th>
                            <th>จำนวนคน</th>
                            <th>วัตถุประสงค์</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_array as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                <td><strong><?php echo htmlspecialchars($booking['room_name']); ?></strong></td>
                                <td><?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></td>
                                <td><?php echo substr($booking['start_time'], 0, 5); ?> - <?php echo substr($booking['end_time'], 0, 5); ?></td>
                                <td><?php echo $booking['num_attendees']; ?> คน</td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($booking['purpose']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve_booking">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm">✅ อนุมัติ</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject_booking">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('ต้องการไม่อนุมัติการจองนี้ใช่หรือไม่?')">🚫 ไม่อนุมัติ</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div style="font-size: 3em; margin-bottom: 10px;">✅</div>
                    <p>ไม่มีรายการจองรออนุมัติ</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Meeting Rooms -->
        <div class="section">
            <div class="section-title">
                <span>🏢</span>
                <span>จัดการห้องประชุม</span>
                <button class="btn btn-primary btn-sm" onclick="openAddRoomModal()" style="margin-left: auto;">+ เพิ่มห้องใหม่</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ชื่อห้อง</th>
                        <th>ความจุ</th>
                        <th>สถานที่</th>
                        <th>สิ่งอำนวยความสะดวก</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($room = $rooms->fetchArray(SQLITE3_ASSOC)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($room['room_name']); ?></strong></td>
                            <td><?php echo $room['capacity']; ?> คน</td>
                            <td><?php echo htmlspecialchars($room['location']); ?></td>
                            <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars($room['facilities']); ?>
                            </td>
                            <td>
                                <?php if ($room['is_active']): ?>
                                    <span class="badge badge-active">ใช้งานได้</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_room_status">
                                    <input type="hidden" name="room_id" value="<?php echo $room['room_id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $room['is_active'] ? 0 : 1; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $room['is_active'] ? 'btn-danger' : 'btn-success'; ?>">
                                        <?php echo $room['is_active'] ? '🔒 ปิดใช้งาน' : '✅ เปิดใช้งาน'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Room Modal -->
    <div id="addRoomModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">เพิ่มห้องประชุมใหม่</div>
                <button class="modal-close" onclick="closeAddRoomModal()">&times;</button>
            </div>

            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_room">

                    <div class="form-group">
                        <label>ชื่อห้อง: *</label>
                        <input type="text" name="room_name" required>
                    </div>

                    <div class="form-group">
                        <label>ความจุ (คน): *</label>
                        <input type="number" name="capacity" required min="1">
                    </div>

                    <div class="form-group">
                        <label>สถานที่: *</label>
                        <input type="text" name="location" required>
                    </div>

                    <div class="form-group">
                        <label>สิ่งอำนวยความสะดวก:</label>
                        <textarea name="facilities" rows="3" placeholder="เช่น โปรเจคเตอร์, ไวท์บอร์ด, Wi-Fi..."></textarea>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeAddRoomModal()" style="flex: 1;">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary" style="flex: 1;">✅ เพิ่มห้อง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddRoomModal() {
            document.getElementById('addRoomModal').classList.add('active');
        }

        function closeAddRoomModal() {
            document.getElementById('addRoomModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('addRoomModal');
            if (event.target === modal) {
                closeAddRoomModal();
            }
        }
    </script>
</body>
</html>