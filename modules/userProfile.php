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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ผู้ใช้ - <?php echo htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #065f159c;
            color: #000;
            min-height: 100vh;
        }

        .container { display: flex; min-height: 100vh; }

        /* ===== SIDEBAR (เหมือน users.php ทุกอย่าง) ===== */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #10ce30 0%, #000000 100%);
            position: fixed; left: 0; top: 0;
            height: 100vh; overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0,0,0,0.3);
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 25px 20px;
            border-bottom: 1px solid rgb(255,255,255);
            display: flex; align-items: center; gap: 15px; color: white;
        }

        .brand-title {
            font-size: 1.8em; font-weight: 700; color: white;
            display: flex; align-items: center; gap: 12px;
        }

        .brand-subtitle { font-size: 0.85em; color: rgb(0,0,0); margin-top: 5px; }

        .sidebar-nav ul { list-style: none; padding: 20px 0; }

        .sidebar-nav a {
            display: flex; align-items: center; gap: 15px;
            padding: 15px 20px; color: rgb(255,255,255);
            text-decoration: none; transition: all 0.3s;
        }

        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1); color: white; padding-left: 25px;
        }

        .sidebar-nav li.active a {
            background: linear-gradient(90deg, rgb(17,224,35), rgb(184,209,39));
            color: white; border-left: 4px solid #fff;
        }

        .menu-section {
            padding: 25px 20px 10px; color: rgb(255,255,255);
            font-size: 0.75em; text-transform: uppercase;
            letter-spacing: 1.5px; font-weight: 600;
        }

        /* ===== MAIN ===== */
        .main-content { flex: 1; margin-left: 280px; padding: 30px; }

        .breadcrumb-nav {
            background: white; padding: 15px 30px;
            border-radius: 12px; margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .breadcrumb-nav a { color: #667eea; text-decoration: none; }
        .breadcrumb-nav a:hover { text-decoration: underline; }

        /* ===== PROFILE HEADER ===== */
        .profile-header {
            background: white; border-radius: 16px;
            box-shadow: 0 4px 20px rgb(0,0,0);
            padding: 30px; margin-bottom: 24px;
            display: flex; align-items: flex-start; gap: 24px;
            position: relative; overflow: hidden;
        }

        .profile-header::after {
            content: ''; position: absolute; top: 0; right: 0;
            width: 260px; height: 100%;
            background: linear-gradient(135deg, rgba(16,206,48,0.05), transparent);
            pointer-events: none;
        }

        .avatar-circle {
            width: 90px; height: 90px; border-radius: 50%;
            background: linear-gradient(135deg, #10ce30, #000);
            display: flex; align-items: center; justify-content: center;
            font-size: 2em; font-weight: 700; color: white; flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(16,206,48,0.4);
        }

        .profile-info { flex: 1; }
        .profile-info h2 { font-size: 1.8em; font-weight: 700; color: #000; margin-bottom: 4px; }
        .profile-info .username { color: #718096; font-size: 0.95em; margin-bottom: 12px; }

        .profile-meta { display: flex; flex-wrap: wrap; gap: 16px; font-size: 0.9em; color: #4a5568; margin-bottom: 14px; }
        .profile-meta-item { display: flex; align-items: center; gap: 6px; }
        .profile-badges { display: flex; gap: 8px; }

        .profile-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }

        /* ===== STATS ===== */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px; margin-bottom: 24px;
        }

        .stat-card {
            background: white; padding: 20px; border-radius: 16px;
            box-shadow: 0 4px 20px rgb(0,0,0);
            display: flex; align-items: center; gap: 16px;
        }

        .stat-icon {
            width: 50px; height: 50px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4em;
        }

        .stat-info h3 { font-size: 1.8em; font-weight: 700; color: #000; }
        .stat-info p  { color: #718096; font-size: 0.85em; }

        /* ===== TABS ===== */
        .tabs-wrapper {
            background: white; border-radius: 16px;
            box-shadow: 0 4px 20px rgb(0,0,0); overflow: hidden;
        }

        .tabs-nav { display: flex; border-bottom: 2px solid #f0f0f0; overflow-x: auto; }

        .tab-btn {
            padding: 16px 22px; border: none; background: transparent;
            font-family: 'Sarabun', sans-serif; font-size: 0.95em;
            font-weight: 500; color: #718096; cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            white-space: nowrap; border-bottom: 3px solid transparent;
            margin-bottom: -2px; transition: all 0.2s;
        }

        .tab-btn:hover { color: #10ce30; background: #f0fff4; }
        .tab-btn.active { color: #000; border-bottom-color: #10ce30; font-weight: 700; }

        .tab-count {
            background: #f0f0f0; color: #718096;
            padding: 2px 8px; border-radius: 10px; font-size: 0.8em;
        }
        .tab-btn.active .tab-count { background: #10ce30; color: white; }

        .tab-panel { padding: 28px; display: none; }
        .tab-panel.active { display: block; animation: fadeIn 0.2s ease; }

        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }

        /* ===== TABLE ===== */
        table { width: 100%; border-collapse: collapse; }
        thead { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        th    { padding: 14px 16px; text-align: left; font-weight: 600; font-size: 0.9em; }
        td    { padding: 14px 16px; border-bottom: 1px solid #f7fafc; vertical-align: middle; }
        tbody tr:hover { background: #f7fafc; }

        /* ===== BADGES ===== */
        .badge {
            padding: 5px 12px; border-radius: 12px;
            font-size: 0.8em; font-weight: 600; display: inline-block;
        }
        .badge-active    { background: #c6f6d5; color: #2f855a; }
        .badge-inactive  { background: #fed7d7; color: #c53030; }
        .badge-suspended { background: #fef3c7; color: #92400e; }
        .badge-admin     { background: #feebc8; color: #c05621; }
        .badge-staff     { background: #c6f6d5; color: #2f855a; }
        .badge-user      { background: #e6fffa; color: #285e61; }
        .badge-new       { background: #ebf8ff; color: #2b6cb0; }
        .badge-assigned  { background: #fff3e0; color: #c05621; }
        .badge-progress  { background: #fce7f3; color: #97266d; }
        .badge-pending   { background: #ede9fe; color: #553c9a; }
        .badge-solved    { background: #c6f6d5; color: #276749; }
        .badge-closed    { background: #f7fafc;  color: #718096; }
        .badge-urgent    { background: #fff5f5; color: #c53030; }
        .badge-high      { background: #fffaf0; color: #c05621; }
        .badge-medium    { background: #fffff0; color: #975a16; }
        .badge-low       { background: #f0fff4; color: #276749; }

        /* ===== INFO GRID ===== */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        .info-section {
            background: #f8fafc; border-radius: 12px;
            padding: 20px; border: 1px solid #e2e8f0;
        }

        .info-section h4 {
            font-size: 0.95em; font-weight: 700; color: #2d3748;
            margin-bottom: 14px; display: flex; align-items: center; gap: 6px;
        }

        .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        .info-row:last-child { border-bottom: none; }
        .info-key { font-size: 0.85em; color: #718096; width: 140px; flex-shrink: 0; }
        .info-val { font-size: 0.9em; color: #2d3748; font-weight: 500; }

        /* ===== ASSET CARDS ===== */
        .asset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 16px;
        }

        .asset-card {
            border: 1px solid #e2e8f0; border-radius: 12px;
            padding: 18px; background: #f8fafc; transition: all 0.2s;
        }

        .asset-card:hover { border-color: #10ce30; box-shadow: 0 4px 12px rgba(16,206,48,0.15); background: white; }
        .asset-icon   { font-size: 2em; margin-bottom: 8px; }
        .asset-name   { font-weight: 700; font-size: 1em; margin-bottom: 4px; }
        .asset-serial { font-size: 0.8em; color: #718096; font-family: monospace; margin-bottom: 10px; }

        .asset-detail-row {
            display: flex; justify-content: space-between;
            font-size: 0.85em; color: #4a5568;
            padding: 4px 0; border-bottom: 1px solid #eee;
        }
        .asset-detail-row:last-child { border-bottom: none; }

        /* ===== TIMELINE ===== */
        .timeline { display: flex; flex-direction: column; }
        .tl-item { display: flex; gap: 14px; padding-bottom: 22px; position: relative; }
        .tl-item:not(:last-child)::before {
            content: ''; position: absolute; left: 15px; top: 34px;
            width: 2px; bottom: 0; background: #e2e8f0;
        }
        .tl-dot {
            width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85em; z-index: 1;
        }
        .tl-content { flex: 1; }
        .tl-title { font-weight: 600; font-size: 0.95em; color: #2d3748; }
        .tl-meta  { font-size: 0.82em; color: #718096; margin-top: 2px; }
        .tl-body  {
            font-size: 0.88em; color: #4a5568; margin-top: 6px;
            background: #f8fafc; padding: 8px 12px;
            border-radius: 8px; border-left: 3px solid #10ce30;
        }

        /* ===== SECTION HEADER ===== */
        .section-header {
            display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 18px;
        }
        .section-title { font-size: 1em; font-weight: 700; color: #2d3748; display: flex; align-items: center; gap: 8px; }

        /* ===== BUTTONS ===== */
        .btn {
            padding: 10px 20px; border: none; border-radius: 8px;
            font-size: 0.9em; font-weight: 600; cursor: pointer;
            transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; font-family: 'Sarabun', sans-serif;
        }
        .btn-primary {
            background: linear-gradient(180deg, #10ce30 0%, #000);
            color: white; box-shadow: 0 4px 15px rgb(0,0,0);
        }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-secondary { background: #718096; color: white; }
        .btn-edit      { background: #4299e1; color: white; }

        /* ===== EMPTY STATE ===== */
        .empty-state { text-align: center; padding: 50px 20px; color: #a0aec0; }
        .empty-state i { font-size: 3em; display: block; margin-bottom: 12px; opacity: 0.5; }
        .empty-state p { font-size: 0.95em; }

        /* ===== NO TABLE WARNING ===== */
        .no-table-info {
            background: #fffbeb; border: 1px solid #fcd34d;
            border-radius: 10px; padding: 18px 22px;
            color: #92400e; font-size: 0.9em;
            display: flex; align-items: flex-start; gap: 10px;
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .info-grid { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- ===== SIDEBAR ===== -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div>
                <div class="brand-title">
                    <i class="fas fa-ticket-alt"></i> IT Support
                </div>
                <div class="brand-subtitle">Ticket Management System</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li>
                    <a href="../admin/dashboard.php">
                        <i class="fas fa-arrow-left"></i> กลับ Dashboard หลัก
                    </a>
                </li>
                <li class="menu-section">หลัก</li>
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="tickets.php"><i class="fas fa-ticket-alt"></i> IT Tickets</a></li>
                <li><a href="assets.php"><i class="fas fa-box"></i> สินทรัพย์</a></li>
                <li><a href="Knowledgebase.php"><i class="fas fa-book"></i> Knowledge Base</a></li>
                <li class="menu-section">จัดการ</li>
                <li class="active"><a href="users.php"><i class="fas fa-users"></i> ผู้ใช้งาน</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> รายงาน</a></li>
                <li><a href="slaconfig.php"><i class="fas fa-clock"></i> ตั้งค่า SLA</a></li>
                <li class="menu-section">ระบบ</li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> ตั้งค่า</a></li>
                <li>
                    <a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">
                        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">

        <!-- Breadcrumb -->
        <div class="breadcrumb-nav">
            <a href="dashboard.php">Dashboard</a> ›
            <a href="users.php">จัดการผู้ใช้งาน</a> ›
            <span style="color:#2d3748; font-weight:600;">
                <?php echo htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']); ?>
            </span>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="avatar-circle">
                <?php echo htmlspecialchars($initials ?: '?'); ?>
            </div>

            <div class="profile-info">
                <h2><?php echo htmlspecialchars($profileUser['full_name'] ?? $profileUser['username']); ?></h2>
                <div class="username">
                    @<?php echo htmlspecialchars($profileUser['username']); ?>
                    &nbsp;·&nbsp; #<?php echo $profileUser['user_id']; ?>
                </div>
                <div class="profile-meta">
                    <?php if (!empty($profileUser['email'])): ?>
                    <div class="profile-meta-item">
                        <i class="fas fa-envelope" style="color:#667eea;"></i>
                        <?php echo htmlspecialchars($profileUser['email']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($profileUser['phone'])): ?>
                    <div class="profile-meta-item">
                        <i class="fas fa-phone" style="color:#48bb78;"></i>
                        <?php echo htmlspecialchars($profileUser['phone']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($profileUser['department'])): ?>
                    <div class="profile-meta-item">
                        <i class="fas fa-building" style="color:#ed8936;"></i>
                        <?php echo htmlspecialchars($profileUser['department']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($profileUser['position'])): ?>
                    <div class="profile-meta-item">
                        <i class="fas fa-briefcase" style="color:#9f7aea;"></i>
                        <?php echo htmlspecialchars($profileUser['position']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($profileUser['created_at'])): ?>
                    <div class="profile-meta-item">
                        <i class="fas fa-calendar" style="color:#f6ad55;"></i>
                        สมัครเมื่อ <?php echo date('d/m/Y', strtotime($profileUser['created_at'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="profile-badges">
                    <span class="badge <?php echo $roleBadgeClass; ?>">
                        <?php echo strtoupper($profileUser['role']); ?>
                    </span>
                    <span class="badge <?php echo $isActive ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo $isActive ? 'ACTIVE' : 'INACTIVE'; ?>
                    </span>
                </div>
            </div>

            <div class="profile-actions">
                <button class="btn btn-edit" onclick='openEditModal(<?php echo json_encode($profileUser, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP); ?>)'>
                    <i class="fas fa-edit"></i> แก้ไขข้อมูล
                </button>
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> กลับรายชื่อ
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#667eea,#764ba2);">
                    <i class="fas fa-ticket-alt" style="color:white;"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $ticketStats['total'] ?? 0; ?></h3>
                    <p>Tickets ทั้งหมด</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#f6ad55,#ed8936);">
                    <i class="fas fa-hourglass-half" style="color:white;"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo ($ticketStats['open_count'] ?? 0) + ($ticketStats['progress_count'] ?? 0); ?></h3>
                    <p>กำลังดำเนินการ</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#48bb78,#38a169);">
                    <i class="fas fa-check-circle" style="color:white;"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $ticketStats['solved_count'] ?? 0; ?></h3>
                    <p>แก้ไขสำเร็จ</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#4299e1,#3182ce);">
                    <i class="fas fa-laptop" style="color:white;"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($assets); ?></h3>
                    <p>สินทรัพย์ IT</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs-wrapper">
            <div class="tabs-nav">
                <button class="tab-btn active" onclick="switchTab('tickets', this)">
                    <i class="fas fa-ticket-alt"></i> ประวัติ Ticket
                    <span class="tab-count"><?php echo $ticketStats['total'] ?? 0; ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('assets', this)">
                    <i class="fas fa-laptop"></i> สินทรัพย์ IT
                    <span class="tab-count"><?php echo count($assets); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('info', this)">
                    <i class="fas fa-id-card"></i> ข้อมูลส่วนตัว
                </button>
                <?php if (!empty($activityLogs)): ?>
                <button class="tab-btn" onclick="switchTab('activity', this)">
                    <i class="fas fa-history"></i> ประวัติกิจกรรม
                    <span class="tab-count"><?php echo count($activityLogs); ?></span>
                </button>
                <?php endif; ?>
            </div>

            <!-- TAB: TICKETS -->
            <div id="tab-tickets" class="tab-panel active">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-ticket-alt" style="color:#667eea;"></i>
                        ประวัติการแจ้ง Ticket
                    </div>
                    <a href="tickets.php" class="btn btn-primary" style="font-size:0.85em; padding:8px 16px;">
                        <i class="fas fa-plus"></i> แจ้ง Ticket ใหม่
                    </a>
                </div>

                <?php if (!$hasTicketTable): ?>
                <div class="no-table-info">
                    <i class="fas fa-exclamation-triangle" style="margin-top:2px;"></i>
                    <div>
                        <strong>ยังไม่มีตาราง tickets ในฐานข้อมูล</strong><br>
                        <small>เมื่อสร้างตาราง <code>tickets</code> แล้ว ข้อมูลจะแสดงที่นี่อัตโนมัติ</small>
                    </div>
                </div>
                <?php elseif ($hasTicketTable && !$ticketUserCol): ?>
                <div class="no-table-info">
                    <i class="fas fa-info-circle" style="margin-top:2px;"></i>
                    <div>
                        <strong>พบตาราง tickets แต่ยังไม่มี column เชื่อมกับ User</strong><br>
                        <small>กรุณาเพิ่ม column <code>user_id</code> หรือ <code>requester_id</code> ในตาราง tickets</small>
                    </div>
                </div>
                <?php elseif (empty($tickets)): ?>
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <p>ยังไม่มีประวัติการแจ้ง Ticket</p>
                </div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table>
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
                                    <code style="color:#667eea; font-weight:700;">
                                        #TK-<?php echo str_pad($ticketId, 4, '0', STR_PAD_LEFT); ?>
                                    </code>
                                </td>
                                <td><strong><?php echo htmlspecialchars($ticketTitle); ?></strong></td>
                                <td>
                                    <?php if (!empty($ticket['category'])): ?>
                                    <span class="badge" style="background:#ebf8ff;color:#2b6cb0;">
                                        <?php echo htmlspecialchars($ticket['category']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span style="color:#a0aec0;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?php echo $pl['class']; ?>"><?php echo $pl['label']; ?></span></td>
                                <td><span class="badge <?php echo $sl['class']; ?>"><?php echo $sl['label']; ?></span></td>
                                <td><?php echo htmlspecialchars($ticket['assigned_name'] ?? 'รอมอบหมาย'); ?></td>
                                <td style="color:#718096; font-size:0.85em;">
                                    <?php echo !empty($ticket['created_at']) ? date('d/m/Y H:i', strtotime($ticket['created_at'])) : '-'; ?>
                                </td>
                                <td>
                                    <a href="ticket-detail.php?id=<?php echo $ticketId; ?>"
                                       class="btn" style="background:#ebf8ff;color:#2b6cb0;padding:6px 12px;font-size:0.82em;">
                                        <i class="fas fa-eye"></i> ดู
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

<!-- Edit Modal (POST ไปยัง users.php แล้ว redirect กลับ) -->
<div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:white; padding:30px; border-radius:16px; width:90%; max-width:600px; max-height:90vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="font-size:1.5em;"><i class="fas fa-user-edit"></i> แก้ไขผู้ใช้งาน</h2>
            <button onclick="closeEditModal()" style="background:none; border:none; font-size:1.5em; cursor:pointer; color:#718096;">&times;</button>
        </div>
        <form method="POST" action="users.php">
            <input type="hidden" name="action" value="update" autocomplete="off">
            <input type="hidden" name="user_id" id="edit_user_id" autocomplete="off">

            <div style="margin-bottom:14px;">
                <label for="edit_full_name" style="display:block; margin-bottom:6px; font-weight:600;">ชื่อ-นามสกุล *</label>
                <input type="text" name="full_name" id="edit_full_name" required autocomplete="name"
                    style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; font-family:'Sarabun',sans-serif; font-size:1em;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label for="edit_email" style="display:block; margin-bottom:6px; font-weight:600;">อีเมล</label>
                    <input type="email" name="email" id="edit_email" autocomplete="email"
                        style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; font-family:'Sarabun',sans-serif; font-size:1em;">
                </div>
                <div>
                    <label for="edit_phone" style="display:block; margin-bottom:6px; font-weight:600;">เบอร์โทร</label>
                    <input type="text" name="phone" id="edit_phone" autocomplete="tel"
                        style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; font-family:'Sarabun',sans-serif; font-size:1em;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                <div>
                    <label for="edit_department" style="display:block; margin-bottom:6px; font-weight:600;">แผนก</label>
                    <input type="text" name="department" id="edit_department" autocomplete="organization"
                        style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; font-family:'Sarabun',sans-serif; font-size:1em;">
                </div>
                <div>
                    <label for="edit_position" style="display:block; margin-bottom:6px; font-weight:600;">ตำแหน่ง</label>
                    <input type="text" name="position" id="edit_position" autocomplete="organization-title"
                        style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; font-family:'Sarabun',sans-serif; font-size:1em;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px;">
                <div>
                    <label for="edit_role" style="display:block; margin-bottom:6px; font-weight:600;">บทบาท *</label>
                    <select name="role" id="edit_role" required autocomplete="off"
                        style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; font-family:'Sarabun',sans-serif; font-size:1em;">
                        <option value="user">User</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label for="edit_status" style="display:block; margin-bottom:6px; font-weight:600;">สถานะ *</label>
                    <select name="status" id="edit_status" required autocomplete="off"
                        style="width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:8px; font-family:'Sarabun',sans-serif; font-size:1em;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> บันทึก
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(name, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        btn.classList.add('active');
    }

    function openEditModal(user) {
        document.getElementById('edit_user_id').value    = user.user_id;
        document.getElementById('edit_full_name').value  = user.full_name   || '';
        document.getElementById('edit_email').value      = user.email       || '';
        document.getElementById('edit_phone').value      = user.phone       || '';
        document.getElementById('edit_department').value = user.department  || '';
        document.getElementById('edit_position').value   = user.position    || '';
        document.getElementById('edit_role').value       = user.role;
        document.getElementById('edit_status').value     = user.status      || 'inactive';
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    window.onclick = function(e) {
        const m = document.getElementById('editModal');
        if (e.target === m) closeEditModal();
    }
</script>
</body>
</html>
