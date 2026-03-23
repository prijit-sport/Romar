<?php
session_start();
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
$profileUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$profileUserId) {
    header('Location: users.php');
    exit;
}

// ===== ดึงข้อมูล User (ตรงกับ DB จริง) =====
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param('i', $profileUserId);
$stmt->execute();
$profileUser = $stmt->get_result()->fetch_assoc();

if (!$profileUser) {
    header('Location: users.php');
    exit;
}

// ===== ดึง Tickets (ถ้ามีตาราง tickets) =====
$tickets        = [];
$ticketStats    = ['total' => 0, 'open_count' => 0, 'progress_count' => 0, 'solved_count' => 0];
$hasTicketTable = false;
$ticketUserCol  = null;

$checkTickets = $db->query("SHOW TABLES LIKE 'tickets'");
if ($checkTickets && $checkTickets->num_rows > 0) {
    $hasTicketTable = true;

    // เช็ค column ที่เชื่อม user ทุกชื่อที่เป็นไปได้
    foreach (['user_id', 'requester_id', 'created_by', 'reporter_id', 'open_by'] as $col) {
        $chk = $db->query("SHOW COLUMNS FROM `tickets` LIKE '{$col}'");
        if ($chk && $chk->num_rows > 0) {
            $ticketUserCol = $col;
            break;
        }
    }

    // Query เฉพาะเมื่อพบ column จริงในตาราง
    if ($ticketUserCol) {
        $stmt = $db->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('new','assigned') THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as progress_count,
            SUM(CASE WHEN status IN ('solved','closed') THEN 1 ELSE 0 END) as solved_count
            FROM tickets WHERE `{$ticketUserCol}` = ?");
        $stmt->bind_param('i', $profileUserId);
        $stmt->execute();
        $ticketStats = $stmt->get_result()->fetch_assoc();

        // เช็ค assigned_to
        $assignedCol = $db->query("SHOW COLUMNS FROM `tickets` LIKE 'assigned_to'");
        if ($assignedCol && $assignedCol->num_rows > 0) {
            $stmt = $db->prepare("SELECT t.*, u2.full_name as assigned_name 
                FROM tickets t 
                LEFT JOIN users u2 ON t.assigned_to = u2.user_id
                WHERE t.`{$ticketUserCol}` = ? 
                ORDER BY t.created_at DESC LIMIT 30");
        } else {
            $stmt = $db->prepare("SELECT t.* 
                FROM tickets t 
                WHERE t.`{$ticketUserCol}` = ? 
                ORDER BY t.created_at DESC LIMIT 30");
        }
        $stmt->bind_param('i', $profileUserId);
        $stmt->execute();
        $tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// ===== ดึง Assets (ถ้ามีตาราง assets) =====
$assets       = [];
$hasAssetTable = false;
$assetUserCol  = null;

$checkAssets = $db->query("SHOW TABLES LIKE 'assets'");
if ($checkAssets && $checkAssets->num_rows > 0) {
    $hasAssetTable = true;

    // เช็ค column ที่เชื่อม user ทุกชื่อที่เป็นไปได้
    foreach (['assigned_to', 'user_id', 'owner_id', 'assigned_user_id'] as $col) {
        $chk = $db->query("SHOW COLUMNS FROM `assets` LIKE '{$col}'");
        if ($chk && $chk->num_rows > 0) {
            $assetUserCol = $col;
            break;
        }
    }

    if ($assetUserCol) {
        $stmt = $db->prepare("SELECT * FROM assets WHERE `{$assetUserCol}` = ? ORDER BY created_at DESC");
        $stmt->bind_param('i', $profileUserId);
        $stmt->execute();
        $assets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
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

$roleBadgeClass = match($profileUser['role']) {
    'admin' => 'badge-admin',
    'staff' => 'badge-staff',
    default => 'badge-user',
};

// Priority / Status label helpers
function priorityLabel($p) {
    return match(strtolower($p ?? 'low')) {
        'urgent' => ['label' => 'Urgent', 'class' => 'badge-urgent'],
        'high'   => ['label' => 'High',   'class' => 'badge-high'],
        'medium' => ['label' => 'Medium', 'class' => 'badge-medium'],
        default  => ['label' => 'Low',    'class' => 'badge-low'],
    };
}
function statusLabel($s) {
    return match(strtolower($s ?? 'new')) {
        'new'         => ['label' => 'New',         'class' => 'badge-new'],
        'assigned'    => ['label' => 'Assigned',    'class' => 'badge-assigned'],
        'in_progress' => ['label' => 'In Progress', 'class' => 'badge-progress'],
        'pending'     => ['label' => 'Pending',     'class' => 'badge-pending'],
        'solved'      => ['label' => 'Solved',      'class' => 'badge-solved'],
        'closed'      => ['label' => 'Closed',      'class' => 'badge-closed'],
        default       => ['label' => ucfirst($s),   'class' => 'badge-user'],
    };
}

$pageTitle = 'โปรไฟล์ผู้ใช้ - ' . htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']);
$activePage = 'users';
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Breadcrumb & Page Header -->
<div class="page-header">
    <div class="breadcrumb-nav">
        <div class="breadcrumb">
            <a href="../admin/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <span>›</span>
            <a href="users.php">จัดการผู้ใช้งาน</a>
            <span>›</span>
            <span class="active"><?php echo htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']); ?></span>
        </div>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.25rem;"><i class="fas fa-user" style="color: var(--primary-dark);"></i> <?php echo htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']); ?></h1>
            <p class="page-subtitle">User ID: #<?php echo $profileUser['user_id']; ?> | <?php echo strtoupper($profileUser['role']); ?> | <?php echo $isActive ? 'Active' : 'Inactive'; ?></p>
        </div>
        <button type="button" class="btn btn-primary" data-profile="<?php echo htmlspecialchars(json_encode($profileUser, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-edit"></i> แก้ไขข้อมูล
        </button>
    </div>
</div>

<?php $pageScripts = '<script nonce="' . htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8') . '">window.pageConfig = {userId: ' . $profileUserId . '};</script>
<script nonce="' . htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8') . '" src="../assets/js/userProfile.js"></script>'; ?>

<section class="profile-overview">
    <div class="card section-body">
        <div class="profile-header" style="grid-template-columns: 120px 1fr auto; gap: 1.5rem;">
            <div class="profile-avatar" style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--primary-navy), var(--primary-dark)); box-shadow: var(--shadow-xl); font-size: 2.5rem;">
                <div class="avatar-initials"><?php echo htmlspecialchars($initials ?: '?'); ?></div>
            </div>
            <div class="profile-meta">
                <div class="profile-info">
                    <h3 style="font-size: 1.75rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($profileUser['username'] ?? 'N/A'); ?></h3>
                    <?php if (!empty($profileUser['email'])): ?>
                    <p class="profile-email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($profileUser['email']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($profileUser['department'])): ?>
                    <p class="profile-dept"><i class="fas fa-building"></i> <?php echo htmlspecialchars($profileUser['department']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-badges" style="align-self: start;">
                <span class="badge badge-<?php echo $profileUser['role']; ?>"><?php echo strtoupper($profileUser['role']); ?></span>
                <span class="badge badge-<?php echo $isActive ? 'active' : 'inactive'; ?>"><?php echo $isActive ? 'ACTIVE' : 'INACTIVE'; ?></span>
            </div>
        </div>
    </div>
    
    <!-- Stats inside profile-overview -->
    <div class="stats-grid" style="margin-top: 2rem;">
        <div class="stat-card">
            <div class="stat-icon gradient-purple">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $ticketStats['total'] ?? 0; ?></h3>
                <p>Tickets ทั้งหมด</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gradient-blue">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo ($ticketStats['open_count'] ?? 0) + ($ticketStats['progress_count'] ?? 0); ?></h3>
                <p>กำลังดำเนินการ</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gradient-green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $ticketStats['solved_count'] ?? 0; ?></h3>
                <p>แก้ไขสำเร็จ</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="fas fa-laptop"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo count($assets); ?></h3>
                <p>สินทรัพย์ IT</p>
            </div>
        </div>
    </div>
</section>

<!-- Tabs -->
<div class="tabs-wrapper"> 
            <div class="tabs-nav">
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
                        ประวัติการแจ้ง Ticket
                    </h2>
                    <a href="tickets.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> แจ้ง Ticket ใหม่
                    </a>
                </div>

                <?php if (!$hasTicketTable): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>ยังไม่มีตาราง tickets ในฐานข้อมูล</strong><br>
                    <small>เมื่อสร้างตาราง <code>tickets</code> แล้ว ข้อมูลจะแสดงที่นี่อัตโนมัติ</small>
                </div>
                <?php elseif ($hasTicketTable && !$ticketUserCol): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>พบตาราง tickets แต่ยังไม่มี column เชื่อมกับ User</strong><br>
                    <small>กรุณาเพิ่ม column <code>user_id</code> หรือ <code>requester_id</code> ในตาราง tickets</small>
                </div>
                <?php elseif (empty($tickets)): ?>
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <p>ยังไม่มีประวัติการแจ้ง Ticket</p>
                </div>
                <?php else: ?>
                <div class="table-panel">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>หัวข้อ</th>
                                <th>หมวดหมู่</th>
                                <th>Priority</th>
                                <th>สถานะ</th>
                                <th>ผู้รับผิดชอบ</th>
                                <th>วันที่แจ้ง</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket):
                                $sl = statusLabel($ticket['status']   ?? 'new');
                                $pl = priorityLabel($ticket['priority'] ?? 'low');
                                $ticketId = $ticket['ticket_id'] ?? $ticket['id'] ?? 0;
                                $ticketTitle = $ticket['title'] ?? $ticket['subject'] ?? '-';
                            ?>
                            <tr>
                                <td>
                                    <code>#TK-<?php echo str_pad($ticketId, 4, '0', STR_PAD_LEFT); ?></code>
                                </td>
                                <td><strong><?php echo htmlspecialchars($ticketTitle); ?></strong></td>
                                <td>
                                    <?php if (!empty($ticket['category'])): ?>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($ticket['category']); ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><span class="badge <?php echo $pl['class']; ?>"><?php echo $pl['label']; ?></span></td>
                                <td><span class="badge <?php echo $sl['class']; ?>"><?php echo $sl['label']; ?></span></td>
                                <td><?php echo htmlspecialchars($ticket['assigned_name'] ?? 'รอมอบหมาย'); ?></td>
                                <td><?php echo !empty($ticket['created_at']) ? date('d/m H:i', strtotime($ticket['created_at'])) : '-'; ?></td>
                                <td>
                                    <a href="ticket_view.php?id=<?php echo $ticketId; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB: ASSETS -->
            <div id="tab-assets" class="tab-panel">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-laptop" style="color:#48bb78;"></i>
                        สินทรัพย์ IT ที่ผู้ใช้ครอบครอง
                    </div>
                    <a href="assets.php" class="btn btn-primary" style="font-size:0.85em; padding:8px 16px;">
                        <i class="fas fa-plus"></i> กำหนดสินทรัพย์
                    </a>
                </div>

                <?php if (!$hasAssetTable): ?>
                <div class="no-table-info">
                    <i class="fas fa-exclamation-triangle" style="margin-top:2px;"></i>
                    <div>
                        <strong>ยังไม่มีตาราง assets ในฐานข้อมูล</strong><br>
                        <small>เมื่อสร้างตาราง <code>assets</code> แล้ว ข้อมูลจะแสดงที่นี่อัตโนมัติ</small>
                    </div>
                </div>
                <?php elseif ($hasAssetTable && !$assetUserCol): ?>
                <div class="no-table-info">
                    <i class="fas fa-info-circle" style="margin-top:2px;"></i>
                    <div>
                        <strong>พบตาราง assets แต่ยังไม่มี column เชื่อมกับ User</strong><br>
                        <small>กรุณาเพิ่ม column <code>assigned_to</code> หรือ <code>user_id</code> ในตาราง assets</small>
                    </div>
                </div>
                <?php elseif (empty($assets)): ?>
                <div class="empty-state">
                    <i class="fas fa-laptop"></i>
                    <p>ยังไม่มีสินทรัพย์ที่ถูกมอบหมาย</p>
                </div>
                <?php else: ?>
                <div class="asset-grid">
                    <?php foreach ($assets as $asset):
                        $assetIcon = match(strtolower($asset['asset_type'] ?? $asset['type'] ?? $asset['category'] ?? '')) {
                            'notebook', 'laptop', 'computer' => '💻',
                            'monitor', 'display'             => '🖥️',
                            'printer'                        => '🖨️',
                            'phone', 'mobile'                => '📱',
                            'server'                         => '🖧',
                            'network', 'switch', 'router'    => '🌐',
                            default                          => '📦',
                        };
                        $assetName = $asset['asset_name'] ?? $asset['name'] ?? 'N/A';
                    ?>
                    <div class="asset-card">
                        <div class="asset-icon"><?php echo $assetIcon; ?></div>
                        <div class="asset-name"><?php echo htmlspecialchars($assetName); ?></div>
                        <?php if (!empty($asset['serial_number'])): ?>
                        <div class="asset-serial">SN: <?php echo htmlspecialchars($asset['serial_number']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($asset['status'])): ?>
                        <span class="badge <?php echo $asset['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo strtoupper($asset['status']); ?>
                        </span>
                        <?php endif; ?>
                        <div style="margin-top:12px;">
                            <?php if (!empty($asset['brand'])): ?>
                            <div class="asset-detail-row"><span>ยี่ห้อ</span><span><?php echo htmlspecialchars($asset['brand']); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($asset['model'])): ?>
                            <div class="asset-detail-row"><span>รุ่น</span><span><?php echo htmlspecialchars($asset['model']); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($asset['purchase_date'])): ?>
                            <div class="asset-detail-row">
                                <span>วันที่ซื้อ</span>
                                <span><?php echo date('d/m/Y', strtotime($asset['purchase_date'])); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($asset['warranty_expiry'])): ?>
                            <div class="asset-detail-row">
                                <span>ประกัน</span>
                                <span style="color:<?php echo strtotime($asset['warranty_expiry']) > time() ? '#2f855a' : '#c53030'; ?>;">
                                    ถึง <?php echo date('d/m/Y', strtotime($asset['warranty_expiry'])); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($asset['location'])): ?>
                            <div class="asset-detail-row"><span>ที่ตั้ง</span><span><?php echo htmlspecialchars($asset['location']); ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- TAB: INFO (ตรงตาม DB จริงทุก column) -->
            <div id="tab-info" class="tab-panel">
                <div class="section-title" style="margin-bottom:20px;">
                    <i class="fas fa-id-card" style="color:#ed8936;"></i>
                    ข้อมูลผู้ใช้งานทั้งหมด
                </div>
                <div class="info-grid">
                    <div>
                        <div class="info-section" style="margin-bottom:16px;">
                            <h4><i class="fas fa-user" style="color:#667eea;"></i> ข้อมูลส่วนตัว</h4>
                            <div class="info-row">
                                <span class="info-key">ชื่อ-นามสกุล</span>
                                <span class="info-val"><?php echo htmlspecialchars($profileUser['full_name'] ?? '-'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-key">Username</span>
                                <span class="info-val" style="font-family:monospace;"><?php echo htmlspecialchars($profileUser['username']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-key">อีเมล</span>
                                <span class="info-val"><?php echo htmlspecialchars($profileUser['email'] ?? '-'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-key">เบอร์โทร</span>
                                <span class="info-val"><?php echo htmlspecialchars($profileUser['phone'] ?? '-'); ?></span>
                            </div>
                        </div>
                        <div class="info-section">
                            <h4><i class="fas fa-building" style="color:#ed8936;"></i> ข้อมูลองค์กร</h4>
                            <div class="info-row">
                                <span class="info-key">แผนก</span>
                                <span class="info-val"><?php echo htmlspecialchars($profileUser['department'] ?? '-'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-key">ตำแหน่ง</span>
                                <span class="info-val"><?php echo htmlspecialchars($profileUser['position'] ?? '-'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="info-section">
                            <h4><i class="fas fa-shield-alt" style="color:#48bb78;"></i> ข้อมูลบัญชีระบบ</h4>
                            <div class="info-row">
                                <span class="info-key">User ID</span>
                                <span class="info-val" style="font-family:monospace;">#<?php echo $profileUser['user_id']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-key">บทบาท</span>
                                <span class="info-val">
                                    <span class="badge <?php echo $roleBadgeClass; ?>">
                                        <?php echo strtoupper($profileUser['role']); ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-key">สถานะ (status)</span>
                                <span class="info-val">
                                    <?php $st = $profileUser['status'] ?? 'inactive'; ?>
                                    <span class="badge badge-<?php echo $st; ?>">
                                        <?php echo strtoupper($st); ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-key">is_active</span>
                                <span class="info-val">
                                    <span class="badge <?php echo $profileUser['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $profileUser['is_active'] ? 'YES (1)' : 'NO (0)'; ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-key">วันที่สมัคร</span>
                                <span class="info-val">
                                    <?php echo !empty($profileUser['created_at'])
                                        ? date('d/m/Y H:i', strtotime($profileUser['created_at'])) : '-'; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-key">Login ล่าสุด</span>
                                <span class="info-val">
                                    <?php echo !empty($profileUser['last_login'])
                                        ? date('d/m/Y H:i', strtotime($profileUser['last_login'])) : 'ยังไม่เคย Login'; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-key">แก้ไขล่าสุด</span>
                                <span class="info-val">
                                    <?php echo !empty($profileUser['updated_at'])
                                        ? date('d/m/Y H:i', strtotime($profileUser['updated_at'])) : '-'; ?>
                                </span>
                            </div>
                            <?php if (isset($profileUser['sort_order'])): ?>
                            <div class="info-row">
                                <span class="info-key">Sort Order</span>
                                <span class="info-val"><?php echo $profileUser['sort_order'] ?? '-'; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: ACTIVITY LOG (optional) -->
            <?php if (!empty($activityLogs)): ?>
            <div id="tab-activity" class="tab-panel">
                <div class="section-title" style="margin-bottom:20px;">
                    <i class="fas fa-history" style="color:#9f7aea;"></i>
                    ประวัติกิจกรรมล่าสุด
                </div>
                <div class="timeline">
                    <?php foreach ($activityLogs as $log): ?>
                    <div class="tl-item">
                        <div class="tl-dot" style="background:#ebf4ff; color:#667eea;">
                            <i class="fas fa-circle" style="font-size:0.5em;"></i>
                        </div>
                        <div class="tl-content">
                            <div class="tl-title"><?php echo htmlspecialchars($log['action'] ?? '-'); ?></div>
                            <div class="tl-meta">
                                <?php echo !empty($log['created_at'])
                                    ? date('d/m/Y H:i', strtotime($log['created_at'])) : ''; ?>
                                <?php if (!empty($log['module'])): ?> · <?php echo htmlspecialchars($log['module']); ?><?php endif; ?>
                            </div>
                            <?php if (!empty($log['description'])): ?>
                            <div class="tl-body"><?php echo htmlspecialchars($log['description']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- end tabs-wrapper -->
    </div><!-- end main-content -->
</div><!-- end container -->

<!-- Edit Modal - Modern CSS Classes -->
<div id="editModal" class="modal" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-user-edit"></i> แก้ไขผู้ใช้งาน</h2>
            <button type="button" class="modal-close" data-edit-action="close-edit-modal" aria-label="ปิด">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div class="form-group">
                    <label for="edit_full_name" class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="edit_full_name" required class="form-control" autocomplete="name">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_email" class="form-label">อีเมล</label>
                        <input type="email" name="email" id="edit_email" class="form-control" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="edit_phone" class="form-label">เบอร์โทร</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control" autocomplete="tel">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_department" class="form-label">แผนก</label>
                        <input type="text" name="department" id="edit_department" class="form-control" autocomplete="organization">
                    </div>
                    <div class="form-group">
                        <label for="edit_position" class="form-label">ตำแหน่ง</label>
                        <input type="text" name="position" id="edit_position" class="form-control" autocomplete="organization-title">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_role" class="form-label">บทบาท <span class="text-danger">*</span></label>
                        <select name="role" id="edit_role" required class="form-control">
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status" class="form-label">สถานะ <span class="text-danger">*</span></label>
                        <select name="status" id="edit_status" required class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-edit-action="close-edit-modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/userProfile.js"></script>
</body>
</html>
