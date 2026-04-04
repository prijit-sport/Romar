<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
csrf_token();

// Handle Cancel Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token.';
        $messageType = 'error';
    } else {
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
    <link rel="stylesheet" href="../includes/admin-theme.css">
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
                    <li class="<?php echo $current_page == 'meeting-rooms.php' ? 'active' : ''; ?>"><a href="meeting-rooms.php">🏢 จัดการห้องประชุม</a></li>
                    <li class="<?php echo $current_page == 'documents.php' ? 'active' : ''; ?>"><a href="documents.php">📄 จัดการเอกสาร</a></li>
                    <?php endif; ?>
                    <li class="menu-section">ฟีเจอร์</li>
                    <li class="<?php echo $current_page == 'room-booking.php' ? 'active' : ''; ?>"><a href="room-booking.php">📅 จองห้องประชุม</a></li>
                    <?php if ($current_page !== 'room-booking.php'): ?>               
                    <?php endif; ?>
                    <li class="<?php echo $current_page == 'announcements.php' ? 'active' : ''; ?>"><a href="announcements.php">📢 ข่าวสาร</a></li>
                     <li class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                        <a href="../modules/tickets.php">🎫 แจ้งปัญหาการใช้งาน IT</a>
                    </li>
                    <li class="menu-section">ระบบ</li>
                    <li><a href="settings.php">⚙️ ตั้งค่า</a></li>
                    <li><a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-wrapper">
                <div class="page-header" style="justify-content: space-between;">
                    <div class="page-title-block">
                        <div class="page-icon" style="width:60px; height:60px; border-radius:1rem; background: linear-gradient(135deg, #1a3edc, #0b2c73); display:flex; align-items:center; justify-content:center; color:#fff; font-size:2rem; box-shadow:0 15px 35px rgba(15,23,42,0.25);">📋</div>
                        <div>
                            <h1 style="margin:0; font-size:2rem; font-weight:700;">รายการจองของฉัน</h1>
                            <p style="margin:0.25rem 0 0; color:#475569; font-weight:500;">ดูและจัดการการจองห้องประชุมของคุณ</p>
                        </div>
                    </div>
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
                                    <?php echo csrf_input(); ?>
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

