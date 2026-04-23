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

// Check Admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = getDB();

// Get user ID from URL
$profileUserId = intval($_GET['id'] ?? 0);
if (!$profileUserId) {
    header('Location: users.php');
    exit;
}

// ===== ดึงข้อมูล User (ตรงตาม DB จริง) =====
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param('i', $profileUserId);
$stmt->execute();
$profileUser = $stmt->get_result()->fetch_assoc();

if (!$profileUser) {
    header('Location: users.php');
    exit;
}

// ===== ดึง Tickets (ถ้ามีตาราง tickets) - FORCE DISPLAY =====
$tickets        = [];
$ticketStats    = ['total' => 0, 'open_count' => 0, 'progress_count' => 0, 'solved_count' => 0];
$hasTicketTable = false;
$ticketUserCol  = null;
$debugTickets   = '';

$checkTickets = $db->query("SHOW TABLES LIKE 'tickets'");
if ($checkTickets && $checkTickets->num_rows > 0) {
    $hasTicketTable = true;
    $debugTickets .= 'Found tickets table. ';

    // ลองทุก column possible
    $ticketCols = ['user_id', 'requester_id', 'created_by', 'reporter_id', 'open_by', 'assigned_to', 'owner_id'];
    foreach ($ticketCols as $col) {
        $chk = $db->query("SHOW COLUMNS FROM `tickets` LIKE '{$col}'");
        if ($chk && $chk->num_rows > 0) {
            $ticketUserCol = $col;
            $debugTickets .= "Using col: $col. ";
            break;
        }
    }

    if ($ticketUserCol) {
        // Stats query - add error handling
        $statsSql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('new','assigned') THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as progress_count,
            SUM(CASE WHEN status IN ('solved','closed') THEN 1 ELSE 0 END) as solved_count
            FROM tickets WHERE `{$ticketUserCol}` = ?";
        $stmt = $db->prepare($statsSql);
        if ($stmt) {
            $stmt->bind_param('i', $profileUserId);
            $stmt->execute();
            $rs = $stmt->get_result()->fetch_assoc();
            $ticketStats = array_merge($ticketStats, $rs ?: ['total' => 0]);
            $debugTickets .= 'Stats: ' . $ticketStats['total'] . '. ';
        }

        // Details query - REMOVE LIMIT first, use fallback JOIN
        $detailsSql = "SELECT t.*, COALESCE(u2.full_name, 'N/A') as assigned_name 
            FROM tickets t 
            LEFT JOIN users u2 ON t.assigned_to = u2.user_id
            WHERE t.`{$ticketUserCol}` = ? 
            ORDER BY t.created_at DESC";
        $stmt = $db->prepare($detailsSql);
        if ($stmt) {
            $stmt->bind_param('i', $profileUserId);
            $stmt->execute();
            $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $debugTickets .= 'Found ' . count($tickets) . ' tickets. ';
        } else {
            $debugTickets .= 'Details query failed. ';
        }
    } else {
        $debugTickets .= 'No matching user column found. ';
    }
} else {
    $debugTickets = 'No tickets table. ';
}

// ===== ดึง Assets (ถ้ามีตาราง assets) - FORCE DISPLAY =====
$assets       = [];
$hasAssetTable = false;
$assetUserCol  = null;
$debugAssets  = '';

$checkAssets = $db->query("SHOW TABLES LIKE 'assets'");
if ($checkAssets && $checkAssets->num_rows > 0) {
    $hasAssetTable = true;
    $debugAssets .= 'Found assets table. ';

    $possibleCols = ['assigned_to', 'user_id', 'owner_id', 'assigned_user_id', 'user_id', 'created_by'];
    foreach ($possibleCols as $col) {
        $chk = $db->query("SHOW COLUMNS FROM `assets` LIKE '{$col}'");
        if ($chk && $chk->num_rows > 0) {
            $assetUserCol = $col;
            $debugAssets .= "Using col: $col. ";
            break;
        }
    }

    if ($assetUserCol) {
        $sql = "SELECT * FROM assets WHERE `{$assetUserCol}` = ? ORDER BY created_at DESC LIMIT 50";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $profileUserId);
            $stmt->execute();
            $assets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $debugAssets .= 'Found ' . count($assets) . ' assets. ';
        }
    } else {
        $debugAssets .= 'No matching column. ';
    }
} else {
    $debugAssets = 'No assets table. ';
}

// ===== ดึง Activity Log (ถ้ามีตาราง) =====
$activityLogs = [];
$tables = ['activity_log', 'activity_logs', 'logs', 'user_logs'];
foreach ($tables as $tbl) {
    $check = $db->query("SHOW TABLES LIKE '$tbl'");
    if ($check && $check->num_rows > 0) {
        $stmt = $db->prepare("SELECT * FROM $tbl WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->bind_param('i', $profileUserId);
        $stmt->execute();
        $activityLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        break;
    }
}

// ===== Helper: Avatar Initials =====
$nameParts = preg_split('/\s+/', trim($profileUser['full_name'] ?? $profileUser['username']));
$initials  = '';
foreach (array_slice($nameParts, 0, 2) as $part) {
    $initials .= mb_substr($part, 0, 1, 'UTF-8');
}

// ===== Helper: สถานะ active (รองรับทั้ง status และ is_active) =====
$isActive = false;
if (isset($profileUser['status'])) {
    $isActive = $profileUser['status'] === 'active';
} elseif (isset($profileUser['is_active'])) {
    $isActive = (bool)$profileUser['is_active'];
}

switch ($profileUser['role']) {
    case 'admin':
        $roleBadgeClass = 'badge-admin';
        break;
    case 'staff':
        $roleBadgeClass = 'badge-staff';
        break;
    default:
$roleBadgeClass = 'badge-user' ;
        break;
}

// Priority / Status label helpers
function priorityLabel(?string $p): array {
    $priority = strtolower($p ?? 'low');
    switch ($priority) {
        case 'urgent':
            return ['label' => 'Urgent', 'class' => 'badge-urgent'];
        case 'high':
            return ['label' => 'High', 'class' => 'badge-high'];
        case 'medium':
            return ['label' => 'Medium', 'class' => 'badge-medium'];
        default:
            return ['label' => 'Low', 'class' => 'badge-low'];
    }
}
function statusLabel(?string $s): array {
    $status = strtolower($s ?? 'new');
    switch ($status) {
        case 'new':
            return ['label' => 'New', 'class' => 'badge-new'];
        case 'assigned':
            return ['label' => 'Assigned', 'class' => 'badge-assigned'];
        case 'in_progress':
            return ['label' => 'In Progress', 'class' => 'badge-progress'];
        case 'pending':
            return ['label' => 'Pending', 'class' => 'badge-pending'];
        case 'solved':
            return ['label' => 'Solved', 'class' => 'badge-solved'];
        case 'closed':
            return ['label' => 'Closed', 'class' => 'badge-closed'];
        default:
            return ['label' => ucfirst($s), 'class' => 'badge-user'];
    }
}

$pageTitle = 'โปรไฟล์ผู้ใช้ - ' . htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']);
$activePage = 'users';
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Breadcrumb & Page Header -->
<main class="main-content">

    <!-- Breadcrumb -->
    <div class="breadcrumb-nav profile-breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="../admin/dashboard.php">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="breadcrumb-separator">›</li>
<a href="users.php?profile_id=<?php echo $profileUserId; ?>">จัดการผู้ใช้งาน</a>
            <li class="breadcrumb-separator">›</li>
            <li class="breadcrumb-item active">
                <?php echo htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']); ?>
            </li>
        </ol>
    </div>

    <section class="profile-hero">
        <div class="profile-hero__main">
            <div class="profile-avatar"><?php echo htmlspecialchars($initials ?: 'U'); ?></div>
            <div class="profile-hero__content">
                <div class="profile-hero__top">
                    <h1 class="profile-hero__name"><?php echo htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']); ?></h1>
                    <div class="profile-hero__badges">
                        <span class="badge badge-<?php echo $roleBadgeClass; ?>"><?php echo strtoupper($profileUser['role']); ?></span>
                        <span class="badge badge-<?php echo $isActive ? 'success' : 'danger'; ?>"><?php echo $isActive ? 'Active' : 'Inactive'; ?></span>
                    </div>
                </div>
                <div class="profile-hero__meta">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($profileUser['username']); ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($profileUser['email'] ?? '-'); ?></span>
                    <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($profileUser['department'] ?? '-'); ?></span>
                    <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($profileUser['position'] ?? '-'); ?></span>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Cards -->
    <section class="section profile-stats-section">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-chart-bar"></i> สถิติการใช้งาน</h2>
        </div>
        <div class="section-body">
            <div class="stats-grid stats-grid-modern">
                <div class="stat-card-modern stat-card-modern--blue">
                    <div class="stat-card-modern__icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-card-modern__content">
                        <h3><?php echo $ticketStats['total']; ?></h3>
                        <p>ประวัติ Ticket</p>
                    </div>
                </div>

                <div class="stat-card-modern stat-card-modern--green">
                    <div class="stat-card-modern__icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-card-modern__content">
                        <h3><?php echo count($assets); ?></h3>
                        <p>ทรัพย์สินที่มี</p>
                    </div>
                </div>

                <div class="stat-card-modern stat-card-modern--orange">
                    <div class="stat-card-modern__icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-card-modern__content">
                        <h3><?php echo $ticketStats['progress_count']; ?></h3>
                        <p>กำลังบำรุงรักษา</p>
                    </div>
                </div>

                <div class="stat-card-modern stat-card-modern--red">
                    <div class="stat-card-modern__icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-card-modern__content">
                        <h3><?php echo $ticketStats['open_count']; ?></h3>
                        <p>รอดำเนินการ</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<div class="tabs-wrapper profile-tabs-wrapper"> 
            <div class="tabs-nav" id="profileTabsNav">
                <button type="button" class="tab-btn active" data-tab="tickets">
                    <i class="fas fa-ticket-alt"></i> ประวัติ Ticket
                    <span class="tab-count"><?php echo $ticketStats['total'] ?? 0; ?></span>
                </button>
                <button type="button" class="tab-btn" data-tab="assets">
                    <i class="fas fa-laptop"></i> สินทรัพย์ IT
                    <span class="tab-count"><?php echo count($assets); ?></span>
                </button>
                <button type="button" class="tab-btn" data-tab="info">
                    <i class="fas fa-id-card"></i> ข้อมูลส่วนตัว
                </button>
                <?php if (!empty($activityLogs)): ?>
                <button type="button" class="tab-btn" data-tab="activity">
                    <i class="fas fa-history"></i> ประวัติกิจกรรม
                    <span class="tab-count"><?php echo count($activityLogs); ?></span>
                </button>
                <?php endif; ?>
            </div>

            <!-- TAB: TICKETS -->
            <div id="tab-tickets" class="tab-panel active">
                <div class="page-section">
                    <h2 class="section-title">
                        <i class="fas fa-ticket-alt text-primary"></i>
                        ประวัติการแจ้ง Ticket (<?php echo $ticketStats['total']; ?>)
                    </h2>
                    <?php if ($hasTicketTable && $ticketUserCol): ?>
                    <a href="tickets.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> แจ้ง Ticket ใหม่
                    </a>
                    <?php endif; ?>
                </div>
                <?php if (!$hasTicketTable): ?>
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <p>ยังไม่มีตาราง tickets ในฐานข้อมูล</p>
                </div>
                <?php elseif (!$ticketUserCol): ?>
                <div class="empty-state">
                    <i class="fas fa-info-circle"></i>
                    <p>พบตาราง tickets แต่ไม่มี column เชื่อม user</p>
                </div>
                <?php elseif (empty($tickets)): ?>
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <p>ยังไม่มีประวัติการแจ้ง Ticket</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>หัวข้อ</th>
                                <th>สถานะ</th>
                                <th>รับผิดชอบ</th>
                                <th>วันที่</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($tickets, 0, 10) as $ticket): ?>
                            <tr>
                                <td><code>#<?php echo $ticket['id'] ?? $ticket['ticket_id'] ?? '-'; ?></code></td>
                                <td><?php echo htmlspecialchars($ticket['title'] ?? $ticket['subject'] ?? '-'); ?></td>
                                <td><span class="badge badge-info"><?php echo $ticket['status'] ?? 'new'; ?></span></td>
                                <td><?php echo htmlspecialchars($ticket['assigned_name'] ?? $ticket['assigned_to'] ?? 'รอ'); ?></td>
                                <td><?php echo date('d/m H:i', strtotime($ticket['created_at'] ?? 0)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB: ASSETS -->
            <div id="tab-assets" class="tab-panel">
                <div class="page-section">
                    <h2 class="section-title">
                        <i class="fas fa-laptop text-success"></i>
                        สินทรัพย์ IT (<?php echo count($assets); ?>)
                    </h2>
                </div>
                <?php if (!$hasAssetTable): ?>
                <div class="empty-state">
                    <i class="fas fa-laptop"></i>
                    <p>ยังไม่มีตาราง assets ในฐานข้อมูล</p>
                </div>
                <?php elseif (empty($assets)): ?>
                <div class="empty-state">
                    <i class="fas fa-laptop"></i>
                    <p>ยังไม่มีสินทรัพย์ที่ถูกมอบหมาย</p>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($assets as $asset): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($asset['asset_name'] ?? $asset['name'] ?? 'N/A'); ?></h6>
                                <?php if (!empty($asset['serial_number'])): ?>
                                <small class="text-muted">SN: <?php echo htmlspecialchars($asset['serial_number']); ?></small>
                                <?php endif; ?>
                                <small>Status: <?php echo $asset['status'] ?? 'active'; ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB: INFO -->
            <div id="tab-info" class="tab-panel">
                <div class="page-section">
                    <h2 class="section-title">
                        <i class="fas fa-id-card text-warning"></i>
                        ข้อมูลส่วนตัว
                    </h2>
                </div>
                <div class="info-grid">
                    <div>
                        <h4>ข้อมูลส่วนตัว</h4>
                        <p><strong>ชื่อ:</strong> <?php echo htmlspecialchars($profileUser['full_name'] ?? '-'); ?></p>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($profileUser['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($profileUser['email'] ?? '-'); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($profileUser['phone'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <h4>ข้อมูลองค์กร</h4>
                        <p><strong>แผนก:</strong> <?php echo htmlspecialchars($profileUser['department'] ?? '-'); ?></p>
                        <p><strong>ตำแหน่ง:</strong> <?php echo htmlspecialchars($profileUser['position'] ?? '-'); ?></p>
                    </div>
                    <div>
                        <h4>ข้อมูลบัญชี</h4>
                        <p><strong>User ID:</strong> #<?php echo $profileUser['user_id']; ?></p>
                        <p><strong>Role:</strong> <span class="badge badge-<?php echo $roleBadgeClass; ?>"><?php echo strtoupper($profileUser['role']); ?></span></p>
                        <p><strong>Status:</strong> <span class="badge badge-<?php echo $isActive ? 'success' : 'danger'; ?>"><?php echo $isActive ? 'Active' : 'Inactive'; ?></span></p>
                        <p><strong>Created:</strong> <?php echo !empty($profileUser['created_at']) ? date('d/m/Y H:i', strtotime($profileUser['created_at'])) : '-'; ?></p>
                    </div>
                </div>
            </div>

            <!-- TAB: ACTIVITY -->
            <?php if (!empty($activityLogs)): ?>
            <div id="tab-activity" class="tab-panel">
                <div class="page-section">
                    <h2 class="section-title">
                        <i class="fas fa-history text-purple"></i>
                        ประวัติกิจกรรม (<?php echo count($activityLogs); ?>)
                    </h2>
                </div>
<div class="timeline timeline-scroll" id="activityTimeline">
                    <?php foreach ($activityLogs as $log): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h6><?php echo htmlspecialchars($log['action'] ?? 'Action'); ?></h6>
                            <small><?php echo date('d/m/Y H:i', strtotime($log['created_at'] ?? 0)); ?> · <?php echo htmlspecialchars($log['module'] ?? '-'); ?></small>
                            <?php if (!empty($log['description'])): ?>
                            <p><?php echo htmlspecialchars($log['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

<style>
/* Refined profile tabs UX/UI */
.main-content {
    padding-top: 0.75rem;
}

.profile-breadcrumb {
    margin-bottom: 0.85rem;
}

.profile-hero {
    margin: -0.15rem 0 1rem;
    padding: 1.2rem 1.35rem;
    background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%);
    border: 1px solid #dbe7f3;
    border-radius: 20px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
}

.profile-hero__main {
    display: flex;
    align-items: center;
    gap: 1.1rem;
}

.profile-avatar {
    width: 72px;
    height: 72px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #3182ce 0%, #2563eb 100%);
    color: #ffffff;
    font-size: 1.55rem;
    font-weight: 700;
    box-shadow: 0 10px 24px rgba(37, 99, 235, 0.22);
    flex-shrink: 0;
}

.profile-hero__content {
    flex: 1;
    min-width: 0;
}

.profile-hero__top {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem 1rem;
    margin-bottom: 0.6rem;
}

.profile-hero__name {
    margin: 0;
    font-size: 1.65rem;
    line-height: 1.2;
    color: #0f172a;
}

.profile-hero__badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.profile-hero__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem 1rem;
    color: #475569;
    font-size: 0.95rem;
}

.profile-hero__meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid #dbe7f3;
    border-radius: 999px;
    padding: 0.45rem 0.8rem;
}

.profile-stats-section {
    margin-top: 0.5rem;
}

.profile-stats-section .section-header {
    margin-bottom: 0.7rem;
}

.profile-stats-section .section-title {
    margin-bottom: 0;
}

.stats-grid {
    gap: 1rem;
}

.stats-grid-modern {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.stat-card-modern {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-height: 88px;
    padding: 1.15rem 1.35rem;
    border-radius: 14px;
    color: #ffffff;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.14);
}

.stat-card-modern--blue {
    background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
}

.stat-card-modern--green {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-card-modern--orange {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-card-modern--red {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.stat-card-modern__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.16);
    font-size: 1.2rem;
    flex-shrink: 0;
}

.stat-card-modern__content h3 {
    margin: 0 0 0.2rem;
    font-size: 1.8rem;
    line-height: 1;
    font-weight: 800;
    color: #ffffff;
}

.stat-card-modern__content p {
    margin: 0;
    color: rgba(255, 255, 255, 0.9);
    line-height: 1.35;
    font-size: 0.95rem;
    font-weight: 500;
}

.profile-tabs-wrapper {
    margin-top: -0.35rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}

.tabs-nav {
    position: sticky;
    top: 0;
    z-index: 20;
    display: flex;
    flex-wrap: nowrap;
    gap: 0.75rem;
    padding: 0.8rem 1rem 0.65rem;
    background: linear-gradient(180deg, #f8fbff 0%, #eef4ff 100%);
    border-bottom: 1px solid #dbe7f3;
    overflow-x: hidden;
    overflow-y: hidden;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.tabs-nav::-webkit-scrollbar {
    display: none;
}

.tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    flex: 0 0 auto;
    padding: 0.85rem 1.25rem;
    border: 1px solid #d9e2ec;
    background: #ffffff;
    color: #334155;
    cursor: pointer;
    border-radius: 12px;
    font-weight: 600;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease;
    white-space: nowrap;
}

.tab-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(49, 130, 206, 0.12);
    background: #f8fbff;
}

.tab-btn.active {
    background: linear-gradient(135deg, #3182ce 0%, #2563eb 100%);
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 10px 22px rgba(37, 99, 235, 0.25);
}

.tab-panel {
    display: none;
    padding: 0.75rem 1.5rem 1.2rem;
    border-top: none;
    background: #ffffff;
}

.tab-panel.active {
    display: block;
}

.tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.8rem;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    background: rgba(15, 23, 42, 0.08);
    color: inherit;
}

.page-section {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.55rem 1rem;
    margin-bottom: 0.8rem;
}

.page-section .section-title {
    margin-bottom: 0;
}

.table-responsive,
.info-grid,
.timeline-scroll,
.row {
    scroll-margin-top: 5rem;
}

.table-responsive {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    background: #ffffff;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
}

.table {
    margin-bottom: 0;
}

.table thead th {
    background: #f8fafc;
    color: #334155;
    border-bottom: 1px solid #e2e8f0;
}

.table tbody td {
    vertical-align: middle;
}

.card {
    height: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
}

.card-body h6 {
    margin-bottom: 0.4rem;
    color: #0f172a;
}

.card-body small {
    display: block;
    color: #64748b;
    line-height: 1.45;
}

.empty-state {
    border: 1px dashed #cbd5e1;
    border-radius: 16px;
    padding: 2rem 1.25rem;
    background: #f8fafc;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.25rem;
}

.info-grid > div {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.2rem 1.25rem;
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.04);
}

.info-grid h4 {
    margin: 0 0 1rem;
    font-size: 1rem;
    color: #0f172a;
}

.info-grid p {
    margin-bottom: 0.75rem;
    color: #475569;
}

.timeline {
    position: relative;
}

.timeline-scroll {
    max-height: 600px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 0.5rem;
    overscroll-behavior: contain;
    scroll-behavior: smooth;
}

.timeline-scroll::-webkit-scrollbar {
    width: 8px;
}

.timeline-scroll::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 999px;
}

.timeline-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 999px;
}

.timeline-item {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.25rem;
    align-items: flex-start;
}

.timeline-dot {
    width: 12px;
    height: 12px;
    margin-top: 0.4rem;
    background: #4299e1;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 0 0 6px rgba(66, 153, 225, 0.12);
}

.timeline-content {
    flex: 1;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 0.95rem 1rem;
}

.timeline-content h6 {
    margin: 0 0 0.35rem;
    color: #0f172a;
}

.timeline-content small {
    display: inline-block;
    margin-bottom: 0.5rem;
    color: #64748b;
}

.timeline-content p {
    margin: 0;
    color: #475569;
}

@media (max-width: 768px) {
    .main-content {
        padding-top: 0.35rem;
    }

    .profile-hero {
        padding: 1rem;
        border-radius: 16px;
    }

    .profile-hero__main {
        align-items: flex-start;
        flex-direction: column;
    }

    .profile-avatar {
        width: 62px;
        height: 62px;
        font-size: 1.35rem;
        border-radius: 16px;
    }

    .profile-hero__name {
        font-size: 1.35rem;
    }

    .profile-hero__meta {
        gap: 0.5rem;
    }

    .profile-hero__meta span {
        width: 100%;
        justify-content: flex-start;
    }

    .stats-grid-modern {
        grid-template-columns: 1fr;
    }

    .stat-card-modern {
        min-height: 78px;
        padding: 1rem 1.1rem;
    }

    .stat-card-modern__content h3 {
        font-size: 1.45rem;
    }

    .profile-tabs-wrapper {
        margin-top: 0;
        border-radius: 14px;
    }

    .tabs-nav {
        padding: 0.7rem 0.85rem 0.6rem;
        gap: 0.5rem;
    }

    .tab-btn {
        padding: 0.75rem 1rem;
        font-size: 0.92rem;
    }

    .tab-panel {
        padding: 0.8rem 1rem 1rem;
    }

    .timeline-scroll {
        max-height: 520px;
    }
}
</style>

<script>
// Tab switching + lock tab nav scroll while keeping timeline scrollable
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab-btn[data-tab]');
    const panels = document.querySelectorAll('.tab-panel');
    const tabsNav = document.getElementById('profileTabsNav');

    if (tabsNav) {
        const lockTabNavScroll = function(event) {
            if (tabsNav.scrollWidth <= tabsNav.clientWidth) return;
            event.preventDefault();
            tabsNav.scrollLeft += event.deltaY || event.deltaX || 0;
        };

        tabsNav.addEventListener('wheel', lockTabNavScroll, { passive: false });
    }
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.tab;
            
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            panels.forEach(p => p.classList.remove('active'));
            document.getElementById(`tab-${target}`).classList.add('active');
        });
    });
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
