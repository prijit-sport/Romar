<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$message = '';
$messageType = '';

// Handle Cancel Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $bookingId = (int)$_POST['booking_id'];
    
    // เช็คว่าเป็นการจองของตัวเองหรือไม่
    $checkStmt = $db->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?");
    $checkStmt->bind_param('ii', $bookingId, $_SESSION['user_id']);
    $checkStmt->execute();
    $booking = $checkStmt->get_result()->fetch_assoc();
    
    if ($booking || $_SESSION['role'] === 'admin') {
        $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE booking_id = ?");
        $stmt->bind_param('i', $_POST['booking_id']);
        
        if ($stmt->execute()) {
            $message = 'ยกเลิกการจองสำเร็จ!';
            $messageType = 'success';
            logActivity($_SESSION['user_id'], 'ยกเลิกการจอง', 'Bookings', "ยกเลิกการจอง ID: " . $_POST['booking_id']);
        }
    }
}

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการจองของฉัน - Romar</title>
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .page-title h1 {
            font-size: 1.8em;
            color: #2d3748;
            font-weight: 600;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .tab-btn {
            padding: 12px 24px;
            border: none;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }

        /* Booking Cards */
        .bookings-grid {
            display: grid;
            gap: 20px;
        }

        .booking-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: all 0.3s;
            border-left: 4px solid;
        }

        .booking-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }

        .booking-card.pending { border-left-color: #f59e0b; }
        .booking-card.approved { border-left-color: #10b981; }
        .booking-card.cancelled { border-left-color: #ef4444; }
        .booking-card.completed { border-left-color: #6b7280; }

        .booking-body {
            padding: 25px;
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }

        .booking-room {
            flex: 1;
        }

        .room-name {
            font-size: 1.3em;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .room-location {
            color: #64748b;
            font-size: 0.9em;
        }

        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-icon {
            font-size: 1.2em;
        }

        .detail-text {
            font-size: 0.95em;
            color: #475569;
        }

        .booking-purpose {
            padding: 15px;
            background: #eff6ff;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .purpose-label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #1e40af;
        }

        .purpose-text {
            color: #475569;
            font-size: 0.95em;
        }

        .booking-actions {
            display: flex;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
        }

        .btn-cancel {
            background: #ef4444;
            color: white;
        }

        .btn-cancel:hover {
            background: #dc2626;
        }

        .btn-disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-completed {
            background: #e5e7eb;
            color: #374151;
        }

        /* Alert */
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            color: #94a3b8;
        }

        .empty-icon {
            font-size: 4em;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .sidebar {
                margin-left: -260px;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .booking-details {
                grid-template-columns: 1fr;
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

            <?php // compute current page for conditional menu logic ?>
            <?php $current_page = basename($_SERVER['PHP_SELF']); ?>

            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><a href="dashboard.php">📊 Dashboard</a></li>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <li class="menu-section">การจัดการ</li>
                    <li class="<?php echo $current_page == 'users-management.php' ? 'active' : ''; ?>"><a href="users-management.php">👥 จัดการผู้ใช้</a></li>
                    <li class="<?php echo $current_page == 'meeting-rooms.php' ? 'active' : ''; ?>"><a href="meeting-rooms.php">🏢 จัดการห้องประชุม</a></li>
                    <li class="<?php echo $current_page == 'documents.php' ? 'active' : ''; ?>"><a href="documents.php">📄 จัดการเอกสาร</a></li>
                    <?php endif; ?>
                    <li class="menu-section">ฟีเจอร์</li>
                    <li class="<?php echo $current_page == 'room-booking.php' ? 'active' : ''; ?>"><a href="room-booking.php">📅 จองห้องประชุม</a></li>
                    <?php if ($current_page !== 'room-booking.php'): ?>
                    <li class="<?php echo $current_page == 'my-bookings.php' ? 'active' : ''; ?>"><a href="my-bookings.php">📋 รายการจองของฉัน</a></li>
                    <?php endif; ?>
                    <li class="<?php echo $current_page == 'announcements.php' ? 'active' : ''; ?>"><a href="announcements.php">📢 ข่าวสาร</a></li>
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
                <h1>📋 รายการจองของฉัน</h1>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> show">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab-btn active" onclick="filterBookings('all')">ทั้งหมด</button>
                <button class="tab-btn" onclick="filterBookings('pending')">รออนุมัติ</button>
                <button class="tab-btn" onclick="filterBookings('approved')">อนุมัติแล้ว</button>
                <button class="tab-btn" onclick="filterBookings('cancelled')">ยกเลิกแล้ว</button>
            </div>

            <!-- Bookings -->
            <div class="bookings-grid" id="bookingsContainer">
                <?php
                // Get user bookings
                $userId = $_SESSION['user_id'];
                $stmt = $db->prepare("
                    SELECT b.*, r.room_name, r.location, u.full_name as booked_by
                    FROM bookings b
                    JOIN meeting_rooms r ON b.room_id = r.room_id
                    JOIN users u ON b.user_id = u.user_id
                    WHERE b.user_id = ?
                    ORDER BY b.booking_date DESC, b.start_time DESC
                ");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                if (empty($bookings)):
                ?>
                    <div class="empty-state">
                        <div class="empty-icon">📅</div>
                        <h3>ยังไม่มีการจอง</h3>
                        <p>คุณยังไม่มีรายการจองห้องประชุม</p>
                        <a href="room-booking.php" class="btn" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; margin-top: 20px;">
                            📅 จองห้องประชุม
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): 
                        $statusClass = strtolower($booking['status']);
                        $statusText = [
                            'pending' => '⏳ รออนุมัติ',
                            'approved' => '✅ อนุมัติแล้ว',
                            'cancelled' => '❌ ยกเลิกแล้ว',
                            'completed' => '✔️ เสร็จสิ้น'
                        ][$booking['status']] ?? $booking['status'];
                    ?>
                    <div class="booking-card <?php echo $statusClass; ?>" data-status="<?php echo $statusClass; ?>">
                        <div class="booking-body">
                            <div class="booking-header">
                                <div class="booking-room">
                                    <div class="room-name"><?php echo htmlspecialchars($booking['room_name']); ?></div>
                                    <div class="room-location">📍 <?php echo htmlspecialchars($booking['location']); ?></div>
                                </div>
                                <span class="badge badge-<?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>

                            <div class="booking-details">
                                <div class="detail-item">
                                    <span class="detail-icon">📅</span>
                                    <div>
                                        <div style="font-size: 0.85em; color: #94a3b8;">วันที่</div>
                                        <div class="detail-text"><strong><?php echo formatDateShort($booking['booking_date']); ?></strong></div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-icon">🕐</span>
                                    <div>
                                        <div style="font-size: 0.85em; color: #94a3b8;">เวลา</div>
                                        <div class="detail-text"><strong><?php echo substr($booking['start_time'], 0, 5); ?> - <?php echo substr($booking['end_time'], 0, 5); ?> น.</strong></div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-icon">👥</span>
                                    <div>
                                        <div style="font-size: 0.85em; color: #94a3b8;">จำนวนผู้เข้าร่วม</div>
                                        <div class="detail-text"><strong><?php echo $booking['num_attendees']; ?> คน</strong></div>
                                    </div>
                                </div>
                            </div>

                            <div class="booking-purpose">
                                <div class="purpose-label">📝 วัตถุประสงค์:</div>
                                <div class="purpose-text"><?php echo nl2br(htmlspecialchars($booking['purpose'])); ?></div>
                            </div>

                            <div class="booking-actions">
                                <?php if ($booking['status'] === 'pending' || $booking['status'] === 'approved'): ?>
                                <form method="POST" style="flex: 1;" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะยกเลิกการจองนี้?')">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                    <button type="submit" class="btn btn-cancel">
                                        ❌ ยกเลิกการจอง
                                    </button>
                                </form>
                                <?php else: ?>
                                <button class="btn btn-disabled" disabled>
                                    <?php echo $booking['status'] === 'cancelled' ? '❌ ยกเลิกแล้ว' : '✔️ เสร็จสิ้น'; ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function filterBookings(status) {
            const buttons = document.querySelectorAll('.tab-btn');
            const cards = document.querySelectorAll('.booking-card');
            
            // Update active tab
            buttons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.includes({
                    'all': 'ทั้งหมด',
                    'pending': 'รออนุมัติ',
                    'approved': 'อนุมัติแล้ว',
                    'cancelled': 'ยกเลิกแล้ว'
                }[status])) {
                    btn.classList.add('active');
                }
            });
            
            // Filter cards
            cards.forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) alert.classList.remove('show');
        }, 5000);
    </script>
</body>
</html>