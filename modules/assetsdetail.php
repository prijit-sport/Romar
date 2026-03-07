<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; }

csrf_token();
apply_security_headers();

$db      = getDB();
$isAdmin = $_SESSION['role'] === 'admin';
$assetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $limit = rate_limit_check('module_assetsdetail_post', 50, 60);
    if (!$limit['allowed']) {
        security_audit_log('rate_limit_blocked', ['module' => 'assetsdetail', 'retry_after' => $limit['retry_after']]);
        $message = 'Too many requests. Retry in ' . $limit['retry_after'] . ' seconds';
        $messageType = 'error';
        $_POST['action'] = '';
    }
}

if (!$assetId) { header('Location: assets.php'); exit; }

// ── Category definitions (shared) ──────────────────────────────
$ASSET_CATEGORIES = [
    'all'       => ['label'=>'สินทรัพย์ทั้งหมด', 'icon'=>'fa-boxes',       'types'=>[]],
    'computers' => ['label'=>'คอมพิวเตอร์',       'icon'=>'fa-desktop',     'types'=>['desktop','laptop']],
    'monitors'  => ['label'=>'จอมอนิเตอร์',       'icon'=>'fa-tv',          'types'=>['monitor']],
    'network'   => ['label'=>'อุปกรณ์เครือข่าย', 'icon'=>'fa-network-wired','types'=>['network']],
    'printers'  => ['label'=>'เครื่องพิมพ์',      'icon'=>'fa-print',       'types'=>['printer']],
    'phones'    => ['label'=>'โทรศัพท์/มือถือ',   'icon'=>'fa-mobile-alt',  'types'=>['mobile','phone']],
    'software'  => ['label'=>'ซอฟต์แวร์',         'icon'=>'fa-compact-disc','types'=>['software']],
    'other'     => ['label'=>'อื่นๆ',              'icon'=>'fa-cube',        'types'=>['other']],
];
$catCounts = [];
foreach ($ASSET_CATEGORIES as $key => $catDef) {
    if ($key === 'all') {
        $r = $db->query("SELECT COUNT(*) as cnt FROM assets");
    } else {
        // ✅ ใช้ Prepared Statements เพื่อป้องกัน SQL Injection
        $types = $catDef['types'];
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM assets WHERE asset_type IN ($placeholders)");
        $stmt->bind_param(str_repeat('s', count($types)), ...$types);
        $stmt->execute();
        $r = $stmt->get_result();
    }
    $catCounts[$key] = $r ? $r->fetch_assoc()['cnt'] : 0;
}

// ── Handle POST Actions ────────────────────────────────────────
$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isAdmin) {
    if (in_array($action, ['add_repair', 'add_borrow', 'return_asset', 'add_transfer'], true)) {
        security_audit_log('access_denied', ['module' => 'assetsdetail', 'action' => $action, 'asset_id' => $assetId]);
        $message = 'Access denied';
        $messageType = 'error';
        $action = '';
    }
}

// เพิ่มประวัติซ่อม
if ($action === 'add_repair') {
    // CSRF check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token'; $messageType = 'error';
    } else {
        $stmt = $db->prepare("INSERT INTO asset_repairs (asset_id,repair_date,problem_desc,repair_detail,repair_cost,vendor,technician,status,warranty_claim,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $cost = (float)$_POST['repair_cost'];
        $wc   = isset($_POST['warranty_claim']) ? 1 : 0;
        $repairDate   = $_POST['repair_date'];
        $problemDesc  = sanitize($_POST['problem_desc']);
        $repairDetail = sanitize($_POST['repair_detail']);
        $vendor       = sanitize($_POST['vendor']);
        $technician   = sanitize($_POST['technician']);
        $repairStatus = sanitize($_POST['repair_status']);
        $userId       = $_SESSION['user_id'];
        $stmt->bind_param('isssdsssii',
            $assetId, $repairDate, $problemDesc,
            $repairDetail, $cost, $vendor,
            $technician, $repairStatus,
            $wc, $userId
        );
        if ($stmt->execute()) {
            // ถ้า status = in_progress → อัปเดตสถานะอุปกรณ์เป็น maintenance
            if ($_POST['repair_status'] === 'in_progress') {
                $upd = $db->prepare("UPDATE assets SET status = 'maintenance' WHERE asset_id = ?");
                $upd->bind_param('i', $assetId);
                $upd->execute();
            }
            logActivity($_SESSION['user_id'], 'เพิ่มประวัติซ่อม', 'Assets', "Asset ID: $assetId");
            $message = 'บันทึกประวัติการซ่อมเรียบร้อย'; $messageType = 'success';
        } else { $message = 'เกิดข้อผิดพลาด: '.$stmt->error; $messageType = 'error'; }
    }
}

// เพิ่มการยืม
if ($action === 'add_borrow') {
    // CSRF check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token'; $messageType = 'error';
    } else {
        $stmt = $db->prepare("INSERT INTO asset_borrows (asset_id,borrower_id,approved_by,borrow_date,expected_return,purpose,condition_out,created_by,status) VALUES (?,?,?,?,?,?,?,?,'borrowed')");
        $borrowerId = (int)$_POST['borrower_id'];
        $approvedBy = $isAdmin ? $_SESSION['user_id'] : null;
        $borrowDate     = $_POST['borrow_date'];
        $expectedReturn = !empty($_POST['expected_return']) ? $_POST['expected_return'] : null;
        $purpose        = sanitize($_POST['purpose']);
        $conditionOut   = sanitize($_POST['condition_out']);
        $userId         = $_SESSION['user_id'];
        $stmt->bind_param('iiissssi',
            $assetId, $borrowerId, $approvedBy,
            $borrowDate, $expectedReturn,
            $purpose, $conditionOut,
            $userId
        );
        if ($stmt->execute()) {
            $upd = $db->prepare("UPDATE assets SET status = 'inactive' WHERE asset_id = ?");
            $upd->bind_param('i', $assetId);
            $upd->execute();
            logActivity($_SESSION['user_id'], 'บันทึกการยืม', 'Assets', "Asset ID: $assetId ผู้ยืม: $borrowerId");
            $message = 'บันทึกการยืมเรียบร้อย'; $messageType = 'success';
        } else { $message = 'เกิดข้อผิดพลาด: '.$stmt->error; $messageType = 'error'; }
    }
}

// คืนอุปกรณ์
if ($action === 'return_asset') {
    // CSRF check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token'; $messageType = 'error';
    } else {
        $borrowId    = (int)$_POST['borrow_id'];
        $condIn      = sanitize($_POST['condition_in']);
        $returnDate  = $_POST['actual_return'];
        $stmt = $db->prepare("UPDATE asset_borrows SET actual_return=?, condition_in=?, status='returned' WHERE borrow_id=?");
        $stmt->bind_param('ssi', $returnDate, $condIn, $borrowId);
        if ($stmt->execute()) {
            $upd = $db->prepare("UPDATE assets SET status = 'active' WHERE asset_id = ?");
            $upd->bind_param('i', $assetId);
            $upd->execute();
            logActivity($_SESSION['user_id'], 'คืนอุปกรณ์', 'Assets', "Asset ID: $assetId");
            $message = 'บันทึกการคืนเรียบร้อย'; $messageType = 'success';
        }
    }
}

// โอนย้าย
if ($action === 'add_transfer') {
    // CSRF check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid CSRF token'; $messageType = 'error';
    } else {
        $fromUser    = !empty($_POST['from_user_id']) ? (int)$_POST['from_user_id'] : null;
        $toUser      = !empty($_POST['to_user_id'])   ? (int)$_POST['to_user_id']   : null;
        $fromLoc     = sanitize($_POST['from_location']);
        $toLoc       = sanitize($_POST['to_location']);
        $fromDept    = sanitize($_POST['from_dept']);
        $toDept      = sanitize($_POST['to_dept']);
        $transDate   = $_POST['transfer_date'];
        $reason      = sanitize($_POST['reason']);
        $byUser      = $_SESSION['user_id'];

        $stmt = $db->prepare("INSERT INTO asset_transfers (
            asset_id,from_user_id,to_user_id,from_location,to_location,
            from_dept,to_dept,transfer_date,reason,transferred_by
        ) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('iiissssssi',
            $assetId, $fromUser, $toUser, $fromLoc, $toLoc,
            $fromDept, $toDept, $transDate, $reason, $byUser
        );
        $stmt->execute();

        // Update asset details if provided
        $updates = [];
        $params = [];
        $typesUpdate = '';
        if ($toUser !== null) {
            $updates[] = "assigned_to = ?";
            $typesUpdate .= 'i';
            $params[] = $toUser;
        }
        if ($toLoc !== '') {
            $updates[] = "location = ?";
            $typesUpdate .= 's';
            $params[] = $toLoc;
        }
        if ($toDept !== '') {
            $updates[] = "department = ?";
            $typesUpdate .= 's';
            $params[] = $toDept;
        }
        if ($updates) {
            $sql = "UPDATE assets SET " . implode(', ', $updates) . " WHERE asset_id = ?";
            $params[] = $assetId;
            $typesUpdate .= 'i';
            $updStmt = $db->prepare($sql);
            $updStmt->bind_param($typesUpdate, ...$params);
            $updStmt->execute();
        }

        logActivity($byUser, 'โอนย้ายสินทรัพย์', 'Assets', "Asset ID: $assetId → $toLoc");
        $message = 'บันทึกการโอนย้ายเรียบร้อย'; $messageType = 'success';
    }
}

// ── Load Asset ────────────────────────────────────────────────
$stmt = $db->prepare("SELECT a.*, u.full_name as assigned_name FROM assets a LEFT JOIN users u ON a.assigned_to=u.user_id WHERE a.asset_id = ? LIMIT 1");
$stmt->bind_param('i', $assetId);
$stmt->execute();
$assetRes = $stmt->get_result();
if (!$assetRes || $assetRes->num_rows === 0) { header('Location: assets.php'); exit; }
$asset = $assetRes->fetch_assoc();

// Load sub-data
$repStmt = $db->prepare("SELECT r.*, u.full_name as created_by_name FROM asset_repairs r LEFT JOIN users u ON r.created_by=u.user_id WHERE r.asset_id = ? ORDER BY r.repair_date DESC");
$repStmt->bind_param('i', $assetId);
$repStmt->execute();
$repairs = $repStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$borStmt = $db->prepare("SELECT b.*, u.full_name as borrower_name, a.full_name as approved_name FROM asset_borrows b LEFT JOIN users u ON b.borrower_id=u.user_id LEFT JOIN users a ON b.approved_by=a.user_id WHERE b.asset_id = ? ORDER BY b.borrow_date DESC");
$borStmt->bind_param('i', $assetId);
$borStmt->execute();
$borrows = $borStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$trnStmt = $db->prepare("SELECT t.*, fu.full_name as from_name, tu.full_name as to_name, by_u.full_name as by_name FROM asset_transfers t LEFT JOIN users fu ON t.from_user_id=fu.user_id LEFT JOIN users tu ON t.to_user_id=tu.user_id LEFT JOIN users by_u ON t.transferred_by=by_u.user_id WHERE t.asset_id = ? ORDER BY t.transfer_date DESC");
$trnStmt->bind_param('i', $assetId);
$trnStmt->execute();
$transfers = $trnStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$users = $db->query("SELECT user_id, full_name FROM users WHERE status='active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

// ── Depreciation Calc (Straight-Line) ─────────────────────────
$depData = null;
if ($asset['purchase_price'] && $asset['purchase_date']) {
    $purchasePrice  = (float)$asset['purchase_price'];
    $salvageValue   = (float)($asset['salvage_value'] ?? 0);
    $usefulLife     = (int)($asset['useful_life_years'] ?? 5);
    $yearlyDep      = ($purchasePrice - $salvageValue) / max($usefulLife, 1);
    $yearsUsed      = (date('Y') - date('Y', strtotime($asset['purchase_date'])));
    $totalDep       = min($yearlyDep * $yearsUsed, $purchasePrice - $salvageValue);
    $currentValue   = max($purchasePrice - $totalDep, $salvageValue);
    $depPercent     = round($totalDep / max($purchasePrice, 1) * 100);
    $depData = compact('purchasePrice','salvageValue','usefulLife','yearlyDep','yearsUsed','totalDep','currentValue','depPercent');
}

// Repair total cost
$repairTotal = array_sum(array_column($repairs, 'repair_cost'));

// Active borrow
$activeBorrow = null;
foreach ($borrows as $b) { if ($b['status'] === 'borrowed') { $activeBorrow = $b; break; } }

$activeTab = $_GET['tab'] ?? 'repair';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($asset['asset_name']) ?> - รายละเอียดสินทรัพย์</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Sarabun',sans-serif; background:#065f159c; color:#000; min-height:100vh; }
        .container { display:flex; min-height:100vh; }
        /* Sidebar */
        .sidebar { width:280px; background:linear-gradient(180deg,#10ce30 0%,#000 100%); position:fixed; left:0; top:0; height:100vh; overflow-y:auto; box-shadow:4px 0 20px rgba(0,0,0,0.3); z-index:1000; }
        .sidebar-brand { padding:25px 20px; border-bottom:1px solid #fff; color:white; }
        .brand-title { font-size:1.8em; font-weight:700; color:white; display:flex; align-items:center; gap:12px; }
        .brand-subtitle { font-size:0.85em; color:#000; margin-top:5px; }
        .sidebar-nav ul { list-style:none; padding:20px 0; }
        .sidebar-nav a { display:flex; align-items:center; gap:15px; padding:15px 20px; color:#fff; text-decoration:none; transition:all 0.3s; }
        .sidebar-nav a:hover { background:rgba(255,255,255,0.1); padding-left:25px; }
        .sidebar-nav li.active a { background:linear-gradient(90deg,rgb(17,224,35),rgb(184,209,39)); border-left:4px solid #fff; }
        .menu-section { padding:25px 20px 10px; color:#fff; font-size:0.75em; text-transform:uppercase; letter-spacing:1.5px; font-weight:600; }
        /* Main */
        .main-content { flex:1; margin-left:280px; padding:30px; }
        .breadcrumb-nav { background:#fff; padding:15px 30px; border-radius:12px; margin-bottom:20px; box-shadow:0 2px 10px rgba(0,0,0,0.05); display:flex; align-items:center; justify-content:space-between; }
        .breadcrumb { display:flex; align-items:center; gap:10px; list-style:none; }
        .breadcrumb a { color:#10ce30; text-decoration:none; }
        .back-button { background:linear-gradient(135deg,#10ce30,#000); color:white; border:none; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:600; display:flex; align-items:center; gap:8px; cursor:pointer; }
        /* Asset Header Card */
        .asset-header { background:white; padding:30px; border-radius:16px; margin-bottom:25px; box-shadow:0 4px 20px rgba(0,0,0,0.3); }
        .asset-header-grid { display:grid; grid-template-columns:auto 1fr auto; gap:25px; align-items:start; }
        .asset-icon-big { width:80px; height:80px; border-radius:16px; background:linear-gradient(135deg,#10ce30,#000); display:flex; align-items:center; justify-content:center; font-size:2.5em; color:white; }
        .asset-name { font-size:1.8em; font-weight:700; }
        .asset-tag-badge { display:inline-block; background:#edf2f7; color:#4a5568; padding:4px 14px; border-radius:8px; font-size:0.9em; font-weight:600; margin-top:5px; }
        .meta-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:15px; margin-top:20px; }
        .meta-item label { font-size:0.8em; color:#718096; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:4px; }
        .meta-item span { font-size:0.95em; color:#2d3748; }
        /* Stats Row */
        .stats-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:15px; margin-bottom:25px; }
        .mini-stat { background:white; padding:20px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.2); text-align:center; }
        .mini-stat h3 { font-size:1.6em; font-weight:700; }
        .mini-stat p { font-size:0.85em; color:#718096; margin-top:4px; }
        /* Tabs */
        .tabs { display:flex; gap:5px; background:white; padding:8px; border-radius:12px; margin-bottom:20px; box-shadow:0 2px 10px rgba(0,0,0,0.2); }
        .tab-btn { flex:1; padding:12px; border:none; background:none; border-radius:8px; font-size:0.95em; font-weight:600; cursor:pointer; transition:all 0.2s; color:#718096; font-family:'Sarabun',sans-serif; }
        .tab-btn.active { background:linear-gradient(135deg,#10ce30,#38a169); color:white; }
        .tab-btn:hover:not(.active) { background:#f7fafc; }
        /* Card */
        .card { background:white; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.2); overflow:hidden; margin-bottom:20px; }
        .card-header { padding:20px 25px; border-bottom:2px solid #f7fafc; display:flex; justify-content:space-between; align-items:center; }
        .card-title { font-size:1.1em; font-weight:700; display:flex; align-items:center; gap:10px; }
        .card-body { padding:20px 25px; }
        table { width:100%; border-collapse:collapse; }
        thead { background:linear-gradient(135deg,#10ce30,#000); color:white; }
        th { padding:12px 15px; text-align:left; font-weight:600; font-size:0.9em; }
        td { padding:12px 15px; border-bottom:1px solid #f7fafc; vertical-align:middle; font-size:0.9em; }
        tr:hover td { background:#f7fafc; }
        .no-data { text-align:center; padding:40px; color:#718096; }
        /* Badge */
        .badge { padding:4px 12px; border-radius:12px; font-size:0.8em; font-weight:600; }
        .badge-completed  { background:#c6f6d5; color:#276749; }
        .badge-in_progress{ background:#fef5e7; color:#d69e2e; }
        .badge-pending    { background:#bee3f8; color:#2c5282; }
        .badge-borrowed   { background:#fed7d7; color:#c53030; }
        .badge-returned   { background:#c6f6d5; color:#276749; }
        .badge-overdue    { background:#fed7d7; color:#c53030; }
        .badge-good       { background:#c6f6d5; color:#276749; }
        .badge-fair       { background:#fefcbf; color:#744210; }
        .badge-poor       { background:#fed7d7; color:#c53030; }
        .badge-damaged    { background:#fc8181; color:#fff; }
        /* Form */
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .form-group { margin-bottom:15px; }
        .form-group label { display:block; font-weight:600; margin-bottom:6px; font-size:0.9em; color:#4a5568; }
        .form-control { width:100%; padding:10px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:0.95em; font-family:'Sarabun',sans-serif; }
        .form-control:focus { outline:none; border-color:#10ce30; box-shadow:0 0 0 3px rgba(16,206,48,0.15); }
        .btn { padding:10px 20px; border:none; border-radius:8px; font-size:0.95em; font-weight:600; cursor:pointer; transition:all 0.3s; display:inline-flex; align-items:center; gap:8px; font-family:'Sarabun',sans-serif; }
        .btn-primary { background:linear-gradient(135deg,#10ce30,#38a169); color:white; }
        .btn-danger  { background:linear-gradient(135deg,#fc8181,#e53e3e); color:white; }
        .btn-warning { background:linear-gradient(135deg,#f6ad55,#dd6b20); color:white; }
        .btn-sm { padding:6px 14px; font-size:0.85em; }
        .alert { padding:14px 20px; border-radius:10px; margin-bottom:20px; font-weight:500; }
        .alert-success { background:#c6f6d5; color:#276749; border:1px solid #9ae6b4; }
        .alert-error   { background:#fed7d7; color:#c53030; border:1px solid #fc8181; }
        /* Depreciation */
        .dep-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .dep-card { background:#f7fafc; border-radius:12px; padding:20px; }
        .dep-card h4 { font-size:0.85em; color:#718096; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
        .dep-card .value { font-size:1.6em; font-weight:700; color:#2d3748; }
        .dep-bar-wrap { background:#e2e8f0; border-radius:8px; height:12px; margin-top:15px; }
        .dep-bar { background:linear-gradient(90deg,#fc8181,#e53e3e); height:12px; border-radius:8px; transition:width 0.5s; }
        /* Modal */
        .modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center; }
        .modal.show { display:flex; }
        .modal-content { background:white; border-radius:16px; padding:30px; width:600px; max-height:85vh; overflow-y:auto; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .close-btn { background:none; border:none; font-size:1.5em; cursor:pointer; }
        .warranty-claimed { background:#c6f6d5; color:#276749; padding:3px 10px; border-radius:8px; font-size:0.8em; }
        /* Active borrow banner */
        .borrow-banner { background:#fed7d7; border:2px solid #fc8181; border-radius:12px; padding:15px 20px; margin-bottom:20px; display:flex; align-items:center; gap:15px; }
        .borrow-banner i { font-size:1.8em; color:#e53e3e; }
        /* Assets accordion */
        .nav-parent { display:flex; align-items:center; padding:13px 20px; color:white; text-decoration:none; cursor:pointer; transition:all 0.3s; justify-content:space-between; user-select:none; }
        .nav-parent:hover { background:rgba(255,255,255,0.1); }
        .nav-parent.open { background:rgba(255,255,255,0.12); }
        .nav-parent .arrow { transition:transform 0.3s; font-size:0.75em; }
        .nav-parent.open .arrow { transform:rotate(90deg); }
        .nav-submenu { list-style:none; padding:0; margin:0; max-height:0; overflow:hidden; transition:max-height 0.35s ease; background:rgba(0,0,0,0.25); }
        .nav-submenu.open { max-height:600px; }
        .nav-submenu li a { padding:10px 20px 10px 42px !important; font-size:0.93em !important; }
        .nav-submenu li.active a { background:linear-gradient(90deg,rgba(17,224,35,0.8),rgba(184,209,39,0.6)) !important; border-left:3px solid #fff !important; color:white !important; }
        .submenu-badge { background:rgba(255,255,255,0.25); padding:1px 7px; border-radius:10px; font-size:0.78em; font-weight:700; }
    </style>
</head>
<body>
<div class="container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div>
                <div class="brand-title"><i class="fas fa-ticket-alt"></i> IT Support</div>
                <div class="brand-subtitle">Ticket Management System</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="../admin/dashboard.php"><i class="fas fa-arrow-left" style="width:18px;"></i> กลับ Dashboard หลัก</a></li>
                <li class="menu-section">หลัก</li>
                <li><a href="dashboard.php"><i class="fas fa-home" style="width:18px;"></i> Dashboard</a></li>
                <li><a href="tickets.php"><i class="fas fa-ticket-alt" style="width:18px;"></i> IT Tickets</a></li>
                <li><a href="Knowledgebase.php"><i class="fas fa-book" style="width:18px;"></i> Knowledge Base</a></li>

                <li class="menu-section">Assets</li>
                <?php
                $thisCat2 = 'all';
                foreach ($ASSET_CATEGORIES as $k => $cd) {
                    if (!empty($cd['types']) && in_array($asset['asset_type'], $cd['types'])) { $thisCat2 = $k; break; }
                }
                ?>
                <li>
                    <div class="nav-parent open" onclick="toggleAssets(this)">
                        <span style="display:flex;align-items:center;gap:10px;">
                            <i class="fas fa-boxes" style="width:18px;"></i>
                            สินทรัพย์ทั้งหมด
                            <?php if ($catCounts['all'] > 0): ?>
                            <span class="submenu-badge"><?= $catCounts['all'] ?></span>
                            <?php endif; ?>
                        </span>
                        <i class="fas fa-chevron-right arrow"></i>
                    </div>
                    <ul class="nav-submenu open">
                        <li>
                            <a href="assets.php?cat=all" style="display:flex;justify-content:space-between;align-items:center;">
                                <span><i class="fas fa-layer-group" style="width:16px;"></i> ทั้งหมด</span>
                                <span class="submenu-badge"><?= $catCounts['all'] ?></span>
                            </a>
                        </li>
                        <?php foreach ($ASSET_CATEGORIES as $key => $catDef):
                            if ($key === 'all') continue;
                            $isActiveCat = in_array($asset['asset_type'], $catDef['types']); ?>
                        <li class="<?= $isActiveCat ? 'active' : '' ?>">
                            <a href="assets.php?cat=<?= $key ?>" style="display:flex;justify-content:space-between;align-items:center;">
                                <span><i class="fas <?= $catDef['icon'] ?>" style="width:16px;"></i> <?= $catDef['label'] ?></span>
                                <?php if ($catCounts[$key] > 0): ?>
                                <span class="submenu-badge"><?= $catCounts[$key] ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>

                <?php if ($isAdmin): ?>
                <li class="menu-section">จัดการ</li>
                <li><a href="users.php"><i class="fas fa-users" style="width:18px;"></i> ผู้ใช้งาน</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar" style="width:18px;"></i> รายงาน</a></li>
                <li><a href="assetsreports.php"><i class="fas fa-chart-line" style="width:18px;"></i> รายงานสินทรัพย์</a></li>
                <li><a href="slaconfig.php"><i class="fas fa-clock" style="width:18px;"></i> ตั้งค่า SLA</a></li>
                <li><a href="settings.php"><i class="fas fa-cog" style="width:18px;"></i> ตั้งค่า</a></li>
                <?php endif; ?>
                <li class="menu-section">ระบบ</li>
                <li><a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')"><i class="fas fa-sign-out-alt" style="width:18px;"></i> ออกจากระบบ</a></li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Breadcrumb -->
        <div class="breadcrumb-nav">
            <ol class="breadcrumb" style="display:flex;align-items:center;gap:8px;list-style:none;padding:0;margin:0;">
                <li><a href="dashboard.php" style="color:#667eea;text-decoration:none;"><i class="fas fa-home"></i></a></li>
                <li style="color:#ccc;">›</li>
                <li><a href="assets.php?cat=all" style="color:#667eea;text-decoration:none;">Assets</a></li>
                <?php if ($thisCat2 !== 'all'): ?>
                <li style="color:#ccc;">›</li>
                <li><a href="assets.php?cat=<?= $thisCat2 ?>" style="color:#667eea;text-decoration:none;">
                    <i class="fas <?= $ASSET_CATEGORIES[$thisCat2]['icon'] ?>"></i> <?= $ASSET_CATEGORIES[$thisCat2]['label'] ?>
                </a></li>
                <?php endif; ?>
                <li style="color:#ccc;">›</li>
                <li style="color:#2d3748;font-weight:600;"><?= htmlspecialchars($asset['asset_name']) ?></li>
            </ol>
            <a href="assets.php?cat=<?= $thisCat2 ?>" class="back-button"><i class="fas fa-arrow-left"></i> กลับ</a>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($activeBorrow): ?>
        <div class="borrow-banner">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>อุปกรณ์นี้ถูกยืมออกไปอยู่!</strong><br>
                ผู้ยืม: <strong><?= htmlspecialchars($activeBorrow['borrower_name']) ?></strong> 
                | ยืมวันที่: <?= date('d/m/Y', strtotime($activeBorrow['borrow_date'])) ?>
                | กำหนดคืน: <?= $activeBorrow['expected_return'] ? date('d/m/Y', strtotime($activeBorrow['expected_return'])) : '-' ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Asset Header -->
        <div class="asset-header">
            <div class="asset-header-grid">
                <div class="asset-icon-big"><i class="fas fa-<?= $asset['asset_type']==='laptop'?'laptop':($asset['asset_type']==='printer'?'print':($asset['asset_type']==='server'?'server':($asset['asset_type']==='network'?'network-wired':'desktop'))) ?>"></i></div>
                <div>
                    <div class="asset-name"><?= htmlspecialchars($asset['asset_name']) ?></div>
                    <span class="asset-tag-badge"><i class="fas fa-tag"></i> <?= htmlspecialchars($asset['asset_tag']) ?></span>
                    <?php if (!empty($asset['inventory_number'])): ?>
                    <span class="asset-tag-badge" style="background:#e9d8fd;color:#553c9a;margin-left:6px;"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($asset['inventory_number']) ?></span>
                    <?php endif; ?>
                    <span class="badge badge-<?= $asset['status'] ?>" style="margin-left:8px;"><?= strtoupper($asset['status']) ?></span>

                    <!-- Section: อุปกรณ์ -->
                    <div style="margin-top:20px;padding-top:15px;border-top:1px solid #e2e8f0;">
                        <div style="font-size:0.75em;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><i class="fas fa-laptop"></i> ข้อมูลอุปกรณ์</div>
                        <div class="meta-grid">
                            <div class="meta-item"><label>Manufacturer / Brand</label><span><?= htmlspecialchars($asset['brand']??'-') ?></span></div>
                            <div class="meta-item"><label>Model</label><span><?= htmlspecialchars($asset['model']??'-') ?></span></div>
                            <div class="meta-item"><label>Serial Number</label><span><?= htmlspecialchars($asset['serial_number']??'-') ?></span></div>
                            <div class="meta-item"><label>Location</label><span><?= htmlspecialchars($asset['location']??'-') ?></span></div>
                            <div class="meta-item"><label>แผนก/ฝ่าย</label><span><?= htmlspecialchars($asset['department']??'-') ?></span></div>
                            <div class="meta-item"><label>กลุ่ม/ทีม</label><span><?= htmlspecialchars($asset['asset_group']??'-') ?></span></div>
                        </div>
                    </div>

                    <!-- Section: ผู้ดูแล -->
                    <div style="margin-top:15px;padding-top:15px;border-top:1px solid #e2e8f0;">
                        <div style="font-size:0.75em;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><i class="fas fa-users"></i> ผู้รับผิดชอบ</div>
                        <div class="meta-grid">
                            <div class="meta-item"><label>User (ผู้ใช้)</label><span><?= htmlspecialchars($asset['assigned_name']??'ไม่ได้มอบหมาย') ?></span></div>
                            <div class="meta-item"><label>Technician in Charge</label>
                                <?php
                                $techName = '-';
                                if (!empty($asset['tech_in_charge'])) {
                                    // ✅ ใช้ Prepared Statements เพื่อป้องกัน SQL Injection
                                    $techId = (int)$asset['tech_in_charge'];
                                    $techStmt = $db->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
                                    $techStmt->bind_param('i', $techId);
                                    $techStmt->execute();
                                    $techResult = $techStmt->get_result();
                                    if ($techResult && $techResult->num_rows) {
                                        $techName = $techResult->fetch_assoc()['full_name'];
                                    }
                                }
                                ?><span><?= htmlspecialchars($techName) ?></span>
                            </div>
                            <div class="meta-item"><label>Alternate Username</label><span><?= htmlspecialchars($asset['alternate_user']??'-') ?></span></div>
                            <div class="meta-item"><label>Last Inventory</label><span><?= !empty($asset['last_inventory_date']) ? date('d/m/Y', strtotime($asset['last_inventory_date'])) : '-' ?></span></div>
                            <div class="meta-item"><label>สภาพอุปกรณ์</label>
                                <span class="badge badge-<?= $asset['condition_status']??'good' ?>"><?= ucfirst($asset['condition_status']??'good') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Section: OS & Hardware -->
                    <?php if (!empty($asset['os_name']) || !empty($asset['cpu']) || !empty($asset['ip_address'])): ?>
                    <div style="margin-top:15px;padding-top:15px;border-top:1px solid #e2e8f0;">
                        <div style="font-size:0.75em;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><i class="fas fa-microchip"></i> OS & Hardware & Network</div>
                        <div class="meta-grid" style="grid-template-columns:repeat(4,1fr);">
                            <?php if (!empty($asset['os_name'])): ?>
                            <div class="meta-item"><label>OS</label><span style="color:#2b6cb0;font-weight:600;"><?= htmlspecialchars($asset['os_name']) ?><?= !empty($asset['os_version']) ? ' '.$asset['os_version'] : '' ?><?= !empty($asset['os_architecture']) ? ' ('.$asset['os_architecture'].')' : '' ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($asset['cpu'])): ?>
                            <div class="meta-item"><label>CPU</label><span><?= htmlspecialchars($asset['cpu']) ?><?= !empty($asset['cpu_cores']) ? ' ('.$asset['cpu_cores'].' cores)' : '' ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($asset['ram_gb'])): ?>
                            <div class="meta-item"><label>RAM</label><span><?= $asset['ram_gb'] ?> GB</span></div>
                            <?php endif; ?>
                            <?php if (!empty($asset['storage'])): ?>
                            <div class="meta-item"><label>Storage</label><span><?= htmlspecialchars($asset['storage']) ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($asset['ip_address'])): ?>
                            <div class="meta-item"><label>IP Address</label><span><code style="background:#edf2f7;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($asset['ip_address']) ?></code></span></div>
                            <?php endif; ?>
                            <?php if (!empty($asset['mac_address'])): ?>
                            <div class="meta-item"><label>MAC Address</label><span style="font-family:monospace;font-size:0.9em;"><?= htmlspecialchars($asset['mac_address']) ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($asset['network_domain'])): ?>
                            <div class="meta-item"><label>Domain</label><span><?= htmlspecialchars($asset['network_domain']) ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($asset['monitor'])): ?>
                            <div class="meta-item"><label>Monitor</label><span><?= htmlspecialchars($asset['monitor']) ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Section: Purchase -->
                    <div style="margin-top:15px;padding-top:15px;border-top:1px solid #e2e8f0;">
                        <div style="font-size:0.75em;font-weight:700;color:#718096;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><i class="fas fa-shopping-cart"></i> ข้อมูลการจัดซื้อ</div>
                        <div class="meta-grid">
                            <div class="meta-item"><label>วันที่ซื้อ</label><span><?= $asset['purchase_date'] ? date('d/m/Y', strtotime($asset['purchase_date'])) : '-' ?></span></div>
                            <div class="meta-item"><label>Warranty</label>
                                <span>
                                <?php if ($asset['warranty_expiry']): 
                                    $d = (strtotime($asset['warranty_expiry'])-time())/86400;
                                    if ($d < 0) echo '<span style="color:#e53e3e;font-weight:600;">หมดอายุแล้ว</span>';
                                    elseif ($d <= 30) echo '<span style="color:#d69e2e;font-weight:600;">เหลือ '.ceil($d).' วัน</span>';
                                    else echo date('d/m/Y', strtotime($asset['warranty_expiry']));
                                else: echo '-'; endif; ?>
                                </span>
                            </div>
                            <div class="meta-item"><label>ราคาซื้อ</label><span><?= $asset['purchase_price'] ? '฿'.number_format($asset['purchase_price'],2) : '-' ?></span></div>
                            <div class="meta-item"><label>Supplier</label><span><?= htmlspecialchars($asset['supplier']??'-') ?></span></div>
                        </div>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:10px;min-width:160px;">
                    <?php if ($isAdmin): ?>
                    <button class="btn btn-primary btn-sm" onclick="showTab('repair');document.getElementById('addRepairModal').classList.add('show')">
                        <i class="fas fa-tools"></i> บันทึกการซ่อม
                    </button>
                    <button class="btn btn-warning btn-sm" onclick="document.getElementById('addBorrowModal').classList.add('show')">
                        <i class="fas fa-hand-holding"></i> บันทึกการยืม
                    </button>
                    <button class="btn btn-sm" style="background:linear-gradient(135deg,#4299e1,#3182ce);color:white;" onclick="document.getElementById('addTransferModal').classList.add('show')">
                        <i class="fas fa-exchange-alt"></i> โอนย้าย
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- GLPI-style layout: sub-nav + main detail -->
        <div style="display:flex;gap:20px;align-items:flex-start;margin-top:20px;">

        <!-- Left Sub-Navigation Panel (GLPI style) -->
        <div style="width:200px;flex-shrink:0;">
            <div style="background:white;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.1);overflow:hidden;">
                <!-- Panel Title -->
                <div style="background:linear-gradient(135deg,#2d3748,#1a202c);color:white;padding:12px 16px;font-weight:700;font-size:0.9em;display:flex;align-items:center;gap:8px;">
                    <i class="fas <?= $ASSET_CATEGORIES[$thisCat??'all']['icon'] ?? 'fa-box' ?>"></i>
                    <?= $ASSET_CATEGORIES[$thisCat??'all']['label'] ?? 'Asset' ?>
                </div>
                <ul style="list-style:none;padding:0;margin:0;">
                    <?php
                    $subMenuItems = [
                        ['tab'=>'info',        'icon'=>'fa-info-circle',   'label'=>'ข้อมูลทั่วไป',       'count'=>null],
                        ['tab'=>'os',          'icon'=>'fa-windows',       'label'=>'Operating System',    'count'=> !empty($asset['os_name']) ? 1 : 0],
                        ['tab'=>'hardware',    'icon'=>'fa-microchip',     'label'=>'Components',          'count'=> (!empty($asset['cpu']) || !empty($asset['ram_gb'])) ? 1 : 0],
                        ['tab'=>'network',     'icon'=>'fa-network-wired', 'label'=>'Network',             'count'=> !empty($asset['ip_address']) ? 1 : 0],
                        ['tab'=>'software_tab','icon'=>'fa-compact-disc',  'label'=>'Software',            'count'=>null],
                        ['tab'=>'repair',      'icon'=>'fa-tools',         'label'=>'การซ่อม',             'count'=>count($repairs)],
                        ['tab'=>'borrow',      'icon'=>'fa-hand-holding',  'label'=>'การยืม-คืน',          'count'=>count($borrows)],
                        ['tab'=>'transfer',    'icon'=>'fa-exchange-alt',  'label'=>'โอนย้าย',             'count'=>count($transfers)],
                        ['tab'=>'depreciation','icon'=>'fa-chart-line',    'label'=>'เสื่อมราคา',          'count'=>null],
                        ['tab'=>'tickets',     'icon'=>'fa-ticket-alt',    'label'=>'Tickets',             'count'=>null],
                    ];
                    foreach ($subMenuItems as $item):
                    ?>
                    <li>
                        <a href="#" onclick="showTab('<?= $item['tab'] ?>');return false;"
                           class="glpi-sub-link"
                           id="subnav_<?= $item['tab'] ?>"
                           style="display:flex;justify-content:space-between;align-items:center;padding:9px 16px;text-decoration:none;color:#4a5568;font-size:0.87em;border-bottom:1px solid #f7fafc;transition:all 0.15s;">
                            <span><i class="fas <?= $item['icon'] ?>" style="width:16px;opacity:0.7;"></i> <?= $item['label'] ?></span>
                            <?php if ($item['count'] !== null && $item['count'] > 0): ?>
                            <span style="background:#e2e8f0;color:#4a5568;padding:1px 7px;border-radius:10px;font-size:0.8em;font-weight:700;"><?= $item['count'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Quick Actions (admin only) -->
            <?php if ($isAdmin): ?>
            <div style="background:white;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.1);margin-top:12px;overflow:hidden;">
                <div style="background:linear-gradient(135deg,#10ce30,#276749);color:white;padding:10px 16px;font-weight:700;font-size:0.85em;">
                    <i class="fas fa-bolt"></i> Actions
                </div>
                <ul style="list-style:none;padding:6px 0;margin:0;">
                    <li><a href="#" onclick="document.getElementById('addRepairModal').classList.add('show');return false;" style="display:block;padding:8px 16px;color:#4a5568;text-decoration:none;font-size:0.85em;"><i class="fas fa-tools" style="width:16px;color:#dd6b20;"></i> บันทึกการซ่อม</a></li>
                    <li><a href="#" onclick="document.getElementById('addBorrowModal').classList.add('show');return false;" style="display:block;padding:8px 16px;color:#4a5568;text-decoration:none;font-size:0.85em;"><i class="fas fa-hand-holding" style="width:16px;color:#3182ce;"></i> บันทึกการยืม</a></li>
                    <li><a href="#" onclick="document.getElementById('addTransferModal').classList.add('show');return false;" style="display:block;padding:8px 16px;color:#4a5568;text-decoration:none;font-size:0.85em;"><i class="fas fa-exchange-alt" style="width:16px;color:#10ce30;"></i> โอนย้าย</a></li>
                    <li style="border-top:1px solid #f7fafc;margin-top:4px;">
                        <a href="assets.php?cat=<?= $thisCat ?? 'all' ?>" style="display:block;padding:8px 16px;color:#e53e3e;text-decoration:none;font-size:0.85em;"><i class="fas fa-trash" style="width:16px;"></i> ลบสินทรัพย์</a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Main Content -->
        <div style="flex:1;min-width:0;">

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="mini-stat">
                <h3><?= count($repairs) ?></h3>
                <p><i class="fas fa-tools" style="color:#dd6b20;"></i> ครั้งที่ซ่อม</p>
            </div>
            <div class="mini-stat">
                <h3>฿<?= number_format($repairTotal, 0) ?></h3>
                <p><i class="fas fa-baht-sign" style="color:#e53e3e;"></i> ค่าซ่อมรวม</p>
            </div>
            <div class="mini-stat">
                <h3><?= count($borrows) ?></h3>
                <p><i class="fas fa-hand-holding" style="color:#3182ce;"></i> ครั้งที่ยืม</p>
            </div>
            <div class="mini-stat">
                <h3><?= count($transfers) ?></h3>
                <p><i class="fas fa-exchange-alt" style="color:#10ce30;"></i> ครั้งที่โอนย้าย</p>
            </div>
            <?php if ($depData): ?>
            <div class="mini-stat">
                <h3>฿<?= number_format($depData['currentValue'], 0) ?></h3>
                <p><i class="fas fa-chart-line" style="color:#553c9a;"></i> มูลค่าปัจจุบัน</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tabs (hidden, controlled by sub-nav) -->
        <div class="tabs" style="display:none;">
            <button class="tab-btn <?= $activeTab==='repair'?'active':'' ?>" onclick="showTab('repair')">repair</button>
            <button class="tab-btn <?= $activeTab==='borrow'?'active':'' ?>" onclick="showTab('borrow')">borrow</button>
            <button class="tab-btn <?= $activeTab==='transfer'?'active':'' ?>" onclick="showTab('transfer')">transfer</button>
            <button class="tab-btn <?= $activeTab==='depreciation'?'active':'' ?>" onclick="showTab('depreciation')">dep</button>
        </div>

        <!-- Tab: Repair -->
        <div id="tab-repair" class="tab-content" style="display:<?= $activeTab==='repair'?'block':'none' ?>;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-tools"></i> ประวัติการซ่อม</div>
                    <?php if ($isAdmin): ?>
                    <button class="btn btn-primary btn-sm" onclick="document.getElementById('addRepairModal').classList.add('show')"><i class="fas fa-plus"></i> บันทึกการซ่อม</button>
                    <?php endif; ?>
                </div>
                <table>
                    <thead><tr><th>วันที่</th><th>ปัญหา</th><th>รายละเอียด</th><th>ช่าง/ผู้ดำเนินการ</th><th>บริษัท</th><th>ค่าใช้จ่าย</th><th>ประกัน</th><th>สถานะ</th></tr></thead>
                    <tbody>
                    <?php if (empty($repairs)): ?>
                        <tr><td colspan="8" class="no-data"><i class="fas fa-tools" style="font-size:2em;opacity:0.3;"></i><br>ยังไม่มีประวัติการซ่อม</td></tr>
                    <?php else: ?>
                        <?php foreach ($repairs as $r): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($r['repair_date'])) ?></td>
                            <td><strong><?= htmlspecialchars($r['problem_desc']) ?></strong></td>
                            <td style="max-width:200px;"><?= nl2br(htmlspecialchars($r['repair_detail']??'')) ?></td>
                            <td><?= htmlspecialchars($r['technician']??'-') ?></td>
                            <td><?= htmlspecialchars($r['vendor']??'-') ?></td>
                            <td><strong style="color:<?= $r['repair_cost']>0?'#e53e3e':'#718096'; ?>">฿<?= number_format($r['repair_cost'],2) ?></strong></td>
                            <td><?php if ($r['warranty_claim']): ?><span class="warranty-claimed"><i class="fas fa-check"></i> เบิกประกัน</span><?php else: echo '-'; endif; ?></td>
                            <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f7fafc;font-weight:700;">
                            <td colspan="5" style="text-align:right;padding:12px 15px;">รวมค่าซ่อมทั้งหมด:</td>
                            <td style="color:#e53e3e;font-size:1.1em;">฿<?= number_format($repairTotal,2) ?></td>
                            <td colspan="2"></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Borrow -->
        <div id="tab-borrow" class="tab-content" style="display:<?= $activeTab==='borrow'?'block':'none' ?>;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-hand-holding"></i> ประวัติการยืม-คืน</div>
                    <?php if ($isAdmin): ?>
                    <button class="btn btn-warning btn-sm" onclick="document.getElementById('addBorrowModal').classList.add('show')"><i class="fas fa-plus"></i> บันทึกการยืม</button>
                    <?php endif; ?>
                </div>
                <table>
                    <thead><tr><th>ผู้ยืม</th><th>วันที่ยืม</th><th>กำหนดคืน</th><th>วันที่คืน</th><th>วัตถุประสงค์</th><th>สภาพตอนยืม</th><th>สภาพตอนคืน</th><th>สถานะ</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($borrows)): ?>
                        <tr><td colspan="9" class="no-data"><i class="fas fa-hand-holding" style="font-size:2em;opacity:0.3;"></i><br>ยังไม่มีประวัติการยืม</td></tr>
                    <?php else: ?>
                        <?php foreach ($borrows as $bw): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($bw['borrower_name']??'-') ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($bw['borrow_date'])) ?></td>
                            <td><?= $bw['expected_return'] ? date('d/m/Y', strtotime($bw['expected_return'])) : '-' ?></td>
                            <td><?= $bw['actual_return'] ? date('d/m/Y', strtotime($bw['actual_return'])) : '<span style="color:#e53e3e;">ยังไม่คืน</span>' ?></td>
                            <td><?= htmlspecialchars($bw['purpose']??'-') ?></td>
                            <td><span class="badge badge-<?= $bw['condition_out']??'good' ?>"><?= ucfirst($bw['condition_out']??'good') ?></span></td>
                            <td><?= $bw['condition_in'] ? '<span class="badge badge-'.$bw['condition_in'].'">'.ucfirst($bw['condition_in']).'</span>' : '-' ?></td>
                            <td><span class="badge badge-<?= $bw['status'] ?>"><?= $bw['status'] ?></span></td>
                            <td>
                                <?php if ($bw['status']==='borrowed' && $isAdmin): ?>
                                <button class="btn btn-primary btn-sm" onclick="returnAsset(<?= $bw['borrow_id'] ?>)"><i class="fas fa-undo"></i> บันทึกคืน</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Transfer -->
        <div id="tab-transfer" class="tab-content" style="display:<?= $activeTab==='transfer'?'block':'none' ?>;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-exchange-alt"></i> ประวัติการโอนย้าย</div>
                    <?php if ($isAdmin): ?>
                    <button class="btn btn-sm" style="background:linear-gradient(135deg,#4299e1,#3182ce);color:white;" onclick="document.getElementById('addTransferModal').classList.add('show')"><i class="fas fa-plus"></i> บันทึกโอนย้าย</button>
                    <?php endif; ?>
                </div>
                <table>
                    <thead><tr><th>วันที่</th><th>จาก</th><th>ไป</th><th>เหตุผล</th><th>ดำเนินการโดย</th></tr></thead>
                    <tbody>
                    <?php if (empty($transfers)): ?>
                        <tr><td colspan="5" class="no-data"><i class="fas fa-exchange-alt" style="font-size:2em;opacity:0.3;"></i><br>ยังไม่มีประวัติการโอนย้าย</td></tr>
                    <?php else: ?>
                        <?php foreach ($transfers as $tr): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($tr['transfer_date'])) ?></td>
                            <td>
                                <?php if ($tr['from_name']): ?><strong><?= htmlspecialchars($tr['from_name']) ?></strong><br><?php endif; ?>
                                <?php if ($tr['from_location']): ?><small style="color:#718096;"><?= htmlspecialchars($tr['from_location']) ?></small><br><?php endif; ?>
                                <?php if ($tr['from_dept']): ?><small style="color:#10ce30;"><?= htmlspecialchars($tr['from_dept']) ?></small><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tr['to_name']): ?><strong><?= htmlspecialchars($tr['to_name']) ?></strong><br><?php endif; ?>
                                <?php if ($tr['to_location']): ?><small style="color:#718096;"><?= htmlspecialchars($tr['to_location']) ?></small><br><?php endif; ?>
                                <?php if ($tr['to_dept']): ?><small style="color:#10ce30;"><?= htmlspecialchars($tr['to_dept']) ?></small><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($tr['reason']??'-') ?></td>
                            <td><?= htmlspecialchars($tr['by_name']??'-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Depreciation -->
        <div id="tab-depreciation" class="tab-content" style="display:<?= $activeTab==='depreciation'?'block':'none' ?>;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-line"></i> การเสื่อมราคา (Straight-Line Method)</div>
                </div>
                <div class="card-body">
                <?php if (!$depData): ?>
                    <div class="no-data">
                        <i class="fas fa-calculator" style="font-size:3em;opacity:0.3;"></i><br>
                        ยังไม่มีข้อมูล — กรุณาเพิ่ม <strong>ราคาซื้อ (Purchase Price)</strong> และ <strong>วันที่ซื้อ</strong> ในข้อมูลสินทรัพย์ก่อนครับ
                    </div>
                <?php else: ?>
                    <div class="dep-grid">
                        <div class="dep-card"><h4>ราคาซื้อ</h4><div class="value">฿<?= number_format($depData['purchasePrice'],2) ?></div></div>
                        <div class="dep-card"><h4>มูลค่าซาก</h4><div class="value">฿<?= number_format($depData['salvageValue'],2) ?></div></div>
                        <div class="dep-card"><h4>อายุการใช้งาน</h4><div class="value"><?= $depData['usefulLife'] ?> ปี</div></div>
                        <div class="dep-card"><h4>ค่าเสื่อมราคาต่อปี</h4><div class="value" style="color:#e53e3e;">฿<?= number_format($depData['yearlyDep'],2) ?></div></div>
                        <div class="dep-card"><h4>ใช้งานมาแล้ว</h4><div class="value"><?= $depData['yearsUsed'] ?> ปี</div></div>
                        <div class="dep-card"><h4>ค่าเสื่อมราคาสะสม</h4><div class="value" style="color:#e53e3e;">฿<?= number_format($depData['totalDep'],2) ?></div></div>
                    </div>
                    <div style="margin-top:25px;background:#f7fafc;border-radius:12px;padding:25px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <span style="font-weight:700;font-size:1.1em;">มูลค่าปัจจุบัน</span>
                            <span style="font-size:1.8em;font-weight:700;color:#10ce30;">฿<?= number_format($depData['currentValue'],2) ?></span>
                        </div>
                        <div class="dep-bar-wrap">
                            <div class="dep-bar" style="width:<?= $depData['depPercent'] ?>%;background:linear-gradient(90deg,#10ce30,<?= $depData['depPercent']>70?'#e53e3e':'#38a169' ?>);"></div>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:0.85em;color:#718096;">
                            <span>เสื่อมราคาไปแล้ว <?= $depData['depPercent'] ?>%</span>
                            <span>คงเหลือ <?= 100-$depData['depPercent'] ?>%</span>
                        </div>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Info (General details) -->
        <div id="tab-info" class="tab-content" style="display:none;">
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-info-circle"></i> ข้อมูลทั่วไป</div></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <?php
                        $infoRows = [
                            ['Name',                $asset['asset_name']],
                            ['Asset Tag',           $asset['asset_tag']],
                            ['Inventory Number',    $asset['inventory_number']??''],
                            ['Status',              strtoupper($asset['status'])],
                            ['Location',            $asset['location']??''],
                            ['แผนก/ฝ่าย',           $asset['department']??''],
                            ['User',                $asset['assigned_name']??'ไม่ได้มอบหมาย'],
                            ['Alternate Username',  $asset['alternate_user']??''],
                            ['กลุ่ม/ทีม',           $asset['asset_group']??''],
                            ['สภาพ',               ucfirst($asset['condition_status']??'good')],
                            ['Purchase Date',       $asset['purchase_date'] ? date('d/m/Y',strtotime($asset['purchase_date'])) : ''],
                            ['Warranty Expiry',     $asset['warranty_expiry'] ? date('d/m/Y',strtotime($asset['warranty_expiry'])) : ''],
                            ['Supplier',            $asset['supplier']??''],
                            ['ราคาซื้อ',            $asset['purchase_price'] ? '฿'.number_format($asset['purchase_price'],2) : ''],
                            ['Last Inventory',      $asset['last_inventory_date'] ? date('d/m/Y',strtotime($asset['last_inventory_date'])) : ''],
                            ['Comments',            $asset['notes']??''],
                        ];
                        foreach ($infoRows as $r): if (empty($r[1])) continue; ?>
                        <div style="border-bottom:1px solid #f7fafc;padding:10px 0;">
                            <div style="font-size:0.78em;color:#718096;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;"><?= $r[0] ?></div>
                            <div style="font-weight:600;color:#2d3748;"><?= htmlspecialchars($r[1]) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: OS -->
        <div id="tab-os" class="tab-content" style="display:none;">
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fab fa-windows"></i> Operating System</div></div>
                <div class="card-body">
                <?php if (empty($asset['os_name'])): ?>
                    <div class="no-data"><i class="fab fa-windows" style="font-size:3em;opacity:0.3;"></i><br>ยังไม่มีข้อมูล OS</div>
                <?php else: ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <?php foreach ([
                            ['Operating System', $asset['os_name']],
                            ['Version',          $asset['os_version']??''],
                            ['Architecture',     $asset['os_architecture']??''],
                            ['Service Pack',     $asset['os_service_pack']??''],
                            ['Product Key',      $asset['os_product_key']??''],
                        ] as $r): if (empty($r[1])) continue; ?>
                        <div style="border-bottom:1px solid #f7fafc;padding:10px 0;">
                            <div style="font-size:0.78em;color:#718096;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;"><?= $r[0] ?></div>
                            <div style="font-weight:600;color:#2b6cb0;"><?= htmlspecialchars($r[1]) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Hardware -->
        <div id="tab-hardware" class="tab-content" style="display:none;">
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-microchip"></i> Components / Hardware</div></div>
                <div class="card-body">
                <?php if (empty($asset['cpu']) && empty($asset['ram_gb'])): ?>
                    <div class="no-data"><i class="fas fa-microchip" style="font-size:3em;opacity:0.3;"></i><br>ยังไม่มีข้อมูล Hardware</div>
                <?php else: ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <?php foreach ([
                            ['CPU',      $asset['cpu']??''],
                            ['CPU Cores',$asset['cpu_cores'] ? $asset['cpu_cores'].' cores' : ''],
                            ['RAM',      $asset['ram_gb'] ? $asset['ram_gb'].' GB' : ''],
                            ['Storage',  $asset['storage']??''],
                            ['GPU',      $asset['gpu']??''],
                            ['Monitor',  $asset['monitor']??''],
                            ['Brand',    $asset['brand']??''],
                            ['Model',    $asset['model']??''],
                            ['Serial No',$asset['serial_number']??''],
                        ] as $r): if (empty($r[1])) continue; ?>
                        <div style="border-bottom:1px solid #f7fafc;padding:10px 0;">
                            <div style="font-size:0.78em;color:#718096;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;"><?= $r[0] ?></div>
                            <div style="font-weight:600;color:#2d3748;"><?= htmlspecialchars($r[1]) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Network -->
        <div id="tab-network" class="tab-content" style="display:none;">
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-network-wired"></i> Network Ports & Connections</div></div>
                <div class="card-body">
                <?php if (empty($asset['ip_address']) && empty($asset['mac_address'])): ?>
                    <div class="no-data"><i class="fas fa-network-wired" style="font-size:3em;opacity:0.3;"></i><br>ยังไม่มีข้อมูล Network</div>
                <?php else: ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <?php foreach ([
                            ['IP Address', $asset['ip_address']??''],
                            ['MAC Address',$asset['mac_address']??''],
                            ['Domain',     $asset['network_domain']??''],
                            ['Gateway',    $asset['gateway']??''],
                            ['DNS Server', $asset['dns_server']??''],
                        ] as $r): if (empty($r[1])) continue; ?>
                        <div style="border-bottom:1px solid #f7fafc;padding:10px 0;">
                            <div style="font-size:0.78em;color:#718096;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;"><?= $r[0] ?></div>
                            <div style="font-weight:600;"><code style="background:#edf2f7;padding:3px 8px;border-radius:4px;font-size:0.95em;"><?= htmlspecialchars($r[1]) ?></code></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab: Software placeholder -->
        <div id="tab-software_tab" class="tab-content" style="display:none;">
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-compact-disc"></i> Software</div></div>
                <div class="card-body">
                    <div class="no-data"><i class="fas fa-compact-disc" style="font-size:3em;opacity:0.3;"></i><br>ฟีเจอร์นี้อยู่ระหว่างพัฒนา</div>
                </div>
            </div>
        </div>

        <!-- Tab: Tickets -->
        <div id="tab-tickets" class="tab-content" style="display:none;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-ticket-alt"></i> Tickets ที่เกี่ยวข้อง</div>
                    <a href="tickets.php?asset_id=<?= $assetId ?>" class="btn btn-sm btn-primary"><i class="fas fa-external-link-alt"></i> ดูทั้งหมด</a>
                </div>
                <div class="card-body">
                    <div class="no-data"><i class="fas fa-ticket-alt" style="font-size:3em;opacity:0.3;"></i><br>ไม่มี Ticket ที่เชื่อมกับ Asset นี้</div>
                </div>
            </div>
        </div>

        </div><!-- end right column -->
        </div><!-- end two-column flex layout -->

<!-- Modal: เพิ่มการซ่อม -->
<div id="addRepairModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h2><i class="fas fa-tools"></i> บันทึกการซ่อม</h2><button class="close-btn" onclick="this.closest('.modal').classList.remove('show')">&times;</button></div>
        <form method="POST">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="add_repair">
            <div class="form-row">
                <div class="form-group"><label>วันที่ซ่อม *</label><input type="date" name="repair_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>สถานะการซ่อม</label>
                    <select name="repair_status" class="form-control">
                        <option value="completed">เสร็จแล้ว</option>
                        <option value="in_progress">กำลังซ่อม</option>
                        <option value="pending">รอดำเนินการ</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label>ปัญหาที่พบ *</label><input type="text" name="problem_desc" class="form-control" required placeholder="เช่น จอไม่ติด, พัดลมเสีย"></div>
            <div class="form-group"><label>รายละเอียดการซ่อม</label><textarea name="repair_detail" class="form-control" rows="3" placeholder="บรรยายรายละเอียดการซ่อม..."></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>ค่าใช้จ่าย (บาท)</label><input type="number" name="repair_cost" class="form-control" value="0" step="0.01" min="0"></div>
                <div class="form-group"><label>บริษัท/ร้านซ่อม</label><input type="text" name="vendor" class="form-control" placeholder="ชื่อบริษัทหรือร้านซ่อม"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>ช่างเทคนิค</label><input type="text" name="technician" class="form-control" placeholder="ชื่อช่าง"></div>
                <div class="form-group" style="display:flex;align-items:center;padding-top:25px;">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;"><input type="checkbox" name="warranty_claim" style="width:18px;height:18px;"> เบิกซ่อมภายใต้ประกัน</label>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:15px;">
                <button type="button" class="btn" style="background:#e2e8f0;" onclick="this.closest('.modal').classList.remove('show')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: บันทึกการยืม -->
<div id="addBorrowModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h2><i class="fas fa-hand-holding"></i> บันทึกการยืมอุปกรณ์</h2><button class="close-btn" onclick="this.closest('.modal').classList.remove('show')">&times;</button></div>
        <!-- Asset Info Banner -->
        <div style="background:#f0fff4;border:1px solid #9ae6b4;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:12px;">
            <i class="fas fa-laptop" style="color:#38a169;font-size:1.4em;"></i>
            <div>
                <div style="font-weight:700;color:#276749;"><?= htmlspecialchars($asset['asset_tag']) ?> — <?= htmlspecialchars($asset['asset_name']) ?></div>
                <div style="font-size:0.85em;color:#4a7c59;"><?= htmlspecialchars($asset['brand']??'') ?> <?= htmlspecialchars($asset['model']??'') ?> &nbsp;|&nbsp; <?= htmlspecialchars($asset['location']??'N/A') ?></div>
            </div>
        </div>
        <form method="POST">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="add_borrow">
            <div class="form-group"><label>ผู้ยืม *</label>
                <select name="borrower_id" class="form-control" required>
                    <option value="">-- เลือกผู้ยืม --</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>วันที่ยืม *</label><input type="date" name="borrow_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>กำหนดคืน</label><input type="date" name="expected_return" class="form-control"></div>
            </div>
            <div class="form-group"><label>วัตถุประสงค์</label><textarea name="purpose" class="form-control" rows="2" placeholder="เช่น ไปอบรม, ซ่อมบำรุง"></textarea></div>
            <div class="form-group"><label>สภาพอุปกรณ์ตอนยืม</label>
                <select name="condition_out" class="form-control">
                    <option value="good">Good - ดี</option>
                    <option value="fair">Fair - พอใช้</option>
                    <option value="poor">Poor - แย่</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:15px;">
                <button type="button" class="btn" style="background:#e2e8f0;" onclick="this.closest('.modal').classList.remove('show')">ยกเลิก</button>
                <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> บันทึกการยืม</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: คืนอุปกรณ์ -->
<div id="returnModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h2><i class="fas fa-undo"></i> บันทึกการคืนอุปกรณ์</h2><button class="close-btn" onclick="this.closest('.modal').classList.remove('show')">&times;</button></div>
        <!-- Asset Info Banner -->
        <div style="background:#fffbeb;border:1px solid #f6e05e;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:12px;">
            <i class="fas fa-undo" style="color:#d69e2e;font-size:1.4em;"></i>
            <div>
                <div style="font-weight:700;color:#744210;"><?= htmlspecialchars($asset['asset_tag']) ?> — <?= htmlspecialchars($asset['asset_name']) ?></div>
                <div style="font-size:0.85em;color:#975a16;"><?= htmlspecialchars($asset['brand']??'') ?> <?= htmlspecialchars($asset['model']??'') ?></div>
            </div>
        </div>
        <form method="POST">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="return_asset">
            <input type="hidden" name="borrow_id" id="return_borrow_id">
            <div class="form-group"><label>วันที่คืน *</label><input type="date" name="actual_return" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-group"><label>สภาพอุปกรณ์ตอนคืน</label>
                <select name="condition_in" class="form-control">
                    <option value="good">Good - ดี</option>
                    <option value="fair">Fair - พอใช้</option>
                    <option value="poor">Poor - แย่</option>
                    <option value="damaged">Damaged - เสียหาย</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:15px;">
                <button type="button" class="btn" style="background:#e2e8f0;" onclick="this.closest('.modal').classList.remove('show')">ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึกการคืน</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: โอนย้าย -->
<div id="addTransferModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h2><i class="fas fa-exchange-alt"></i> บันทึกการโอนย้าย</h2><button class="close-btn" onclick="this.closest('.modal').classList.remove('show')">&times;</button></div>
        <!-- Asset Info Banner -->
        <div style="background:#ebf8ff;border:1px solid #90cdf4;border-radius:10px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:12px;">
            <i class="fas fa-exchange-alt" style="color:#3182ce;font-size:1.4em;"></i>
            <div>
                <div style="font-weight:700;color:#1a365d;"><?= htmlspecialchars($asset['asset_tag']) ?> — <?= htmlspecialchars($asset['asset_name']) ?></div>
                <div style="font-size:0.85em;color:#2b6cb0;"><?= htmlspecialchars($asset['brand']??'') ?> <?= htmlspecialchars($asset['model']??'') ?> &nbsp;|&nbsp; ผู้ดูแลปัจจุบัน: <strong><?= htmlspecialchars($asset['assigned_name']??'ไม่ได้มอบหมาย') ?></strong></div>
            </div>
        </div>
        <form method="POST">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="add_transfer">
            <div class="form-group"><label>วันที่โอนย้าย *</label><input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="form-row">
                <div class="form-group"><label>จากผู้ใช้</label>
                    <select name="from_user_id" class="form-control">
                        <option value="">-- เลือก --</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>" <?= $asset['assigned_to']==$u['user_id']?'selected':'' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>ไปยังผู้ใช้</label>
                    <select name="to_user_id" class="form-control">
                        <option value="">-- เลือก --</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>จาก Location</label><input type="text" name="from_location" class="form-control" value="<?= htmlspecialchars($asset['location']??'') ?>"></div>
                <div class="form-group"><label>ไปยัง Location</label><input type="text" name="to_location" class="form-control" placeholder="ห้อง/แผนก ปลายทาง"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>จากแผนก</label><input type="text" name="from_dept" class="form-control"></div>
                <div class="form-group"><label>ไปยังแผนก</label><input type="text" name="to_dept" class="form-control"></div>
            </div>
            <div class="form-group"><label>เหตุผล</label><textarea name="reason" class="form-control" rows="2" placeholder="เหตุผลในการโอนย้าย..."></textarea></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:15px;">
                <button type="button" class="btn" style="background:#e2e8f0;" onclick="this.closest('.modal').classList.remove('show')">ยกเลิก</button>
                <button type="submit" class="btn" style="background:linear-gradient(135deg,#4299e1,#3182ce);color:white;"><i class="fas fa-save"></i> บันทึกการโอนย้าย</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAssets(el) {
    el.classList.toggle('open');
    const submenu = el.nextElementSibling;
    submenu.classList.toggle('open');
}

const ALL_TABS = ['info','os','hardware','network','software_tab','repair','borrow','transfer','depreciation','tickets'];

function showTab(name) {
    // Hide all tabs
    ALL_TABS.forEach(t => {
        const el = document.getElementById('tab-' + t);
        if (el) el.style.display = 'none';
    });
    // Show target
    const target = document.getElementById('tab-' + name);
    if (target) target.style.display = 'block';

    // Update sub-nav highlights
    ALL_TABS.forEach(t => {
        const link = document.getElementById('subnav_' + t);
        if (link) {
            if (t === name) {
                link.style.background = 'linear-gradient(135deg,#10ce30,#276749)';
                link.style.color = 'white';
            } else {
                link.style.background = '';
                link.style.color = '#4a5568';
            }
        }
    });
}

function returnAsset(borrowId) {
    document.getElementById('return_borrow_id').value = borrowId;
    document.getElementById('returnModal').classList.add('show');
}

// Auto-select first populated tab on load
window.addEventListener('load', function() {
    const initTab = '<?= $activeTab ?? 'repair' ?>';
    showTab(initTab);
});

window.onclick = e => { if (e.target.classList.contains('modal')) e.target.classList.remove('show'); }
</script>
</body>
</html>
