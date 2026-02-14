<?php
session_start();
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #065f159c;
            color: #ffffff;
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

        .sidebar-nav li {
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
            background: rgba(255, 255, 255, 0.15);
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

        /* Header */
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

        .page-header h1 {
            font-size: 1.8em;
            color: #000000;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10ce30 0%, #000000 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2em;
        }

        .user-details {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: #e2d51a;
        }

        .user-role {
            font-size: 0.85em;
            color: #000000;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            border-left: 4px solid;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .stat-card.blue { border-left-color: #000000; }
        .stat-card.red { border-left-color: #000000; }
        .stat-card.green { border-left-color: #000000; }
        .stat-card.purple { border-left-color: #000000; }
        .stat-card.orange { border-left-color: #000000; }
        .stat-card.teal { border-left-color: #000000; }

        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 0.9em;
            color: #000000;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: 700;
            color: #000000;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            padding: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #000000;
        }

        .card-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #000000;
        }

        /* Activity Item */
        .activity-item {
            padding: 15px;
            border-left: 3px solid #e2e8f0;
            margin-bottom: 15px;
            background: #2c8a3b;
            border-radius: 8px;
        }

        .activity-time {
            font-size: 0.85em;
            color: #000000;
            margin-bottom: 5px;
        }

        .activity-user {
            font-weight: 600;
            color: #e2d51a;
        }

        /* Announcement Item */
        .announcement-item {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid;
            background: #f8fafc;
        }

        .announcement-item.urgent {
            border-left-color: #f80d0d;
            background: #fef2f2;
        }

        .announcement-item.important {
            border-left-color: #ecf01c;
            background: #fffbeb;
        }

        .announcement-item.normal {
            border-left-color: #10ce30;
            background: #eff6ff;
        }

        .announcement-title {
            font-weight: 600;
            color: #000000;
            margin-bottom: 5px;
        }

        .announcement-content {
            font-size: 0.9em;
            color: #000000;
            margin-bottom: 8px;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: linear-gradient(135deg, #10ce30 0%, #000000 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgb(0, 0, 0);
        }

        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgb(0, 0, 0);
        }

        .quick-action-icon {
            font-size: 2em;
        }

        .quick-action-text {
            font-weight: 500;
            text-align: center;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #000000;
        }

        .empty-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .content-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                margin-left: -260px;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions {
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

            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <a href="dashboard.php">📊 Dashboard</a>
                    </li>

                    <?php if ($user['role'] === 'admin'): ?>
                    <li class="menu-section">การจัดการ</li>
                    <li class="<?php echo $current_page == 'users-management.php' ? 'active' : ''; ?>">
                        <a href="users-management.php">👥 จัดการผู้ใช้</a>
                    </li>
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
                    <li class="<?php echo $current_page == 'my-bookings.php' ? 'active' : ''; ?>">
                        <a href="my-bookings.php">📋 รายการจองของฉัน</a>
                    </li>
                    <li class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                        <a href="../modules/tickets.php">🎫 IT Tickets</a>
                    </li>
                    <li class="<?php echo $current_page == 'announcements.php' ? 'active' : ''; ?>">
                        <a href="announcements.php">📢 ข่าวสาร</a>
                    </li>
                    <?php if ($user['role'] !== 'admin'): ?>
                    <li class="<?php echo $current_page == 'userdocuments.php' ? 'active' : ''; ?>">
                        <a href="userdocuments.php">📄 เอกสาร</a>
                    </li>
                    <?php endif; ?>
                    <li class="menu-section">ระบบ</li>
                    <li><a href="settings.php">⚙️ ตั้งค่า</a></li>
                    </li>
                    <li>
                        <a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a>
                    </li>
                </ul>
            </nav>
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
                    <?php if ($user['role'] === 'admin'): ?>
                    <a href="users-management.php" class="quick-action-btn">
                        <span class="quick-action-icon">👥</span>
                        <span class="quick-action-text">จัดการผู้ใช้</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>