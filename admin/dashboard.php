<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Dashboard';

// Get statistics
$stats = getDashboardStats();

// Get recent activities
$recentActivities = getRecentActivities(5);

// Get active announcements
$announcements = getActiveAnnouncements(3);

// Include header and sidebar
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
    }

    .stat-card.blue {
        border-top: 4px solid #3498db;
    }

    .stat-card.green {
        border-top: 4px solid #27ae60;
    }

    .stat-card.orange {
        border-top: 4px solid #f39c12;
    }

    .stat-card.purple {
        border-top: 4px solid #9b59b6;
    }

    .stat-card.red {
        border-top: 4px solid #e74c3c;
    }

    .stat-card.teal {
        border-top: 4px solid #1abc9c;
    }

    .stat-icon {
        font-size: 2.5em;
        margin-bottom: 10px;
    }

    .stat-label {
        font-size: 0.9em;
        color: #7f8c8d;
        margin-bottom: 8px;
    }

    .stat-value {
        font-size: 2em;
        font-weight: 700;
        color: #2c3e50;
    }

    .content-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 25px;
    }

    .activity-item {
        padding: 15px;
        border-left: 3px solid #3498db;
        background: #f8f9fa;
        margin-bottom: 12px;
        border-radius: 6px;
    }

    .activity-time {
        font-size: 0.85em;
        color: #7f8c8d;
        margin-bottom: 5px;
    }

    .activity-description {
        color: #2c3e50;
        font-weight: 500;
    }

    .announcement-item {
        padding: 15px;
        background: #f8f9fa;
        margin-bottom: 15px;
        border-radius: 8px;
        border-left: 4px solid #3498db;
    }

    .announcement-item.important {
        border-left-color: #f39c12;
        background: #fef9e7;
    }

    .announcement-item.urgent {
        border-left-color: #e74c3c;
        background: #fadbd8;
    }

    .announcement-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
    }

    .announcement-content {
        font-size: 0.9em;
        color: #555;
        line-height: 1.6;
    }

    .announcement-meta {
        font-size: 0.8em;
        color: #7f8c8d;
        margin-top: 8px;
    }

    @media (max-width: 968px) {
        .content-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card blue">
        <div class="stat-icon">🎫</div>
        <div class="stat-label">IT Tickets ทั้งหมด</div>
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
        <div class="stat-icon">🏢</div>
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
            <h3 class="card-title">กิจกรรมล่าสุด</h3>
        </div>

        <?php if (empty($recentActivities)): ?>
            <p style="color: #7f8c8d; text-align: center; padding: 20px;">
                ยังไม่มีกิจกรรม
            </p>
        <?php else: ?>
            <?php foreach ($recentActivities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-time">
                        <?php echo formatDateThai($activity['created_at']); ?>
                    </div>
                    <div class="activity-description">
                        <strong><?php echo htmlspecialchars($activity['full_name'] ?? 'ระบบ'); ?>:</strong>
                        <?php echo htmlspecialchars($activity['action']); ?>
                        <?php if (!empty($activity['module'])): ?>
                            <span style="color: #7f8c8d;">(<?php echo htmlspecialchars($activity['module']); ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Announcements -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">ประกาศล่าสุด</h3>
        </div>

        <?php if (empty($announcements)): ?>
            <p style="color: #7f8c8d; text-align: center; padding: 20px;">
                ยังไม่มีประกาศ
            </p>
        <?php else: ?>
            <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-item <?php echo $announcement['priority']; ?>">
                    <div class="announcement-title">
                        <?php 
                        if ($announcement['priority'] == 'urgent') echo '🔴 ';
                        if ($announcement['priority'] == 'important') echo '⚠️ ';
                        echo htmlspecialchars($announcement['title']); 
                        ?>
                    </div>
                    <div class="announcement-content">
                        <?php echo nl2br(htmlspecialchars(mb_substr($announcement['content'], 0, 100))); ?>
                        <?php if (mb_strlen($announcement['content']) > 100) echo '...'; ?>
                    </div>
                    <div class="announcement-meta">
                        โดย: <?php echo htmlspecialchars($announcement['publisher_name']); ?> | 
                        <?php echo formatDateShort($announcement['publish_date']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <a href="<?php echo BASE_URL; ?>modules/announcements/index.php" class="btn" style="margin-top: 15px; width: 100%; text-align: center;">
            ดูประกาศทั้งหมด
        </a>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">เมนูด่วน</h3>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="<?php echo BASE_URL; ?>modules/tickets/create.php" class="btn">
            🎫 สร้าง IT Ticket
        </a>
        <a href="<?php echo BASE_URL; ?>modules/rooms/booking.php" class="btn">
            🏢 จองห้องประชุม
        </a>
        <a href="<?php echo BASE_URL; ?>modules/conversations/create.php" class="btn">
            💬 บันทึกสนทนา
        </a>
        <a href="<?php echo BASE_URL; ?>admin/documents.php" class="btn">
            📁 อัปโหลดเอกสาร
        </a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>