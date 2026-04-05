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

$user = getCurrentUser();
$stats = getDashboardStats();
$activities = getRecentActivities(5);
$announcements = getActiveAnnouncements(3);

// Get current page for sidebar
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ระบบจัดการ Romar</title>
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
                    <li class="<?php echo $current_page == 'room-booking.php' ? 'active' : ''; ?>">
                        <a href="room-booking.php">📅 จองห้องประชุม</a>
                    </li>
                    <li class="<?php echo $current_page == 'announcements.php' ? 'active' : ''; ?>">
                        <a href="announcements.php">📢 ข่าวสาร</a>
                    </li>
                     <li class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                        <a href="../modules/tickets.php">🎫 แจ้งปัญหาการใช้งาน IT</a>
                    </li>
                    <?php if ($user['role'] !== 'admin'): ?>
                    <li class="<?php echo $current_page == 'userdocuments.php' ? 'active' : ''; ?>">
                        <a href="userdocuments.php">📄 เอกสาร</a>
                    </li>
                    <?php endif; ?>
                    <li class="menu-section">ระบบ</li>
                    <li><a href="settings.php">⚙️ ตั้งค่า</a></li>
                    <li>
                        <a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a>
                    </li>
                </ul>
            </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="page-header">
                <h1>📊 Dashboard</h1>
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

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">🎫</div>
                    <div class="stat-label">Tickets ทั้งหมด</div>
                    <div class="stat-value"><?php echo $stats['total_tickets']; ?></div>
                </div>

                <div class="stat-card red">
                    <div class="stat-icon">🔴</div>
                    <div class="stat-label">Tickets ที่เปิดอยู่</div>
                    <div class="stat-value"><?php echo $stats['open_tickets']; ?></div>
                </div>

                <div class="stat-card green">
                    <div class="stat-icon">📢</div>
                    <div class="stat-label">ประกาศที่ Active</div>
                    <div class="stat-value"><?php echo $stats['active_announcements']; ?></div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-icon">👥</div>
                    <div class="stat-label">ผู้ใช้งาน</div>
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                </div>

                <div class="stat-card orange">
                    <div class="stat-icon">📅</div>
                    <div class="stat-label">การจองวันนี้</div>
                    <div class="stat-value"><?php echo $stats['today_bookings']; ?></div>
                </div>

                <div class="stat-card teal">
                    <div class="stat-icon">📁</div>
                    <div class="stat-label">เอกสารทั้งหมด</div>
                    <div class="stat-value"><?php echo $stats['total_documents']; ?></div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">กิจกรรมล่าสุด</h2>
                    </div>
                    
                    <?php if (empty($activities)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <p>ยังไม่มีกิจกรรม</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-time">
                                    <?php echo formatDateThai($activity['created_at']); ?>
                                </div>
                                <div>
                                    <span class="activity-user"><?php echo htmlspecialchars($activity['full_name'] ?? 'ระบบ'); ?></span>
                                    <span><?php echo htmlspecialchars($activity['action']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Announcements -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">ประกาศล่าสุด</h2>
                    </div>
                    
                    <?php if (empty($announcements)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📢</div>
                            <p>ยังไม่มีประกาศ</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="announcement-item <?php echo $announcement['priority']; ?>">
                                <div class="announcement-title">
                                    <?php 
                                    if ($announcement['priority'] === 'urgent') echo '🔴 ';
                                    elseif ($announcement['priority'] === 'important') echo '🟡 ';
                                    echo htmlspecialchars($announcement['title']); 
                                    ?>
                                </div>
                                <div class="announcement-content">
                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">เมนูด่วน</h2>
                </div>
                <div class="quick-actions">
                    <a href="room-booking.php" class="quick-action-btn">
                        <span class="quick-action-icon">📅</span>
                        <span class="quick-action-text">จองห้องประชุม</span>
                    </a>
                    <a href="my-bookings.php" class="quick-action-btn">
                        <span class="quick-action-icon">📋</span>
                        <span class="quick-action-text">รายการจองของฉัน</span>
                    </a>
                    <a href="<?php echo $user['role'] === 'admin' ? 'documents.php' : 'userdocuments.php'; ?>" class="quick-action-btn">
                        <span class="quick-action-icon">📄</span>
                        <span class="quick-action-text">เอกสาร</span>
                    </a>
                    <a href="announcements.php" class="quick-action-btn">
                        <span class="quick-action-icon">📢</span>
                        <span class="quick-action-text">ข่าวสาร</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

