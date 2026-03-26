<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/asset_categories.php';

$ASSET_CATEGORIES = getAssetCategories();

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

csrf_token();
apply_security_headers();

$db = getDB();
$isAdmin = $_SESSION['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $limit = rate_limit_check('module_assets_post', 40, 60);
    if (!$limit['allowed']) {
        security_audit_log('rate_limit_blocked', ['module' => 'assets', 'retry_after' => $limit['retry_after']]);
        $_SESSION['flash_message'] = 'Too many requests. Retry in ' . $limit['retry_after'] . ' seconds';
        $_SESSION['flash_type'] = 'error';
        header('Location: assets.php');
        exit;
    }
}

// Flash message (PRG pattern) 
$message     = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type']    ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Handle Create Asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // CSRF validation
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid CSRF token';
        $_SESSION['flash_type']    = 'error';
        header('Location: assets.php');
        exit;
    }
    
    $asset_name        = sanitize($_POST['asset_name']);
    $asset_tag         = sanitize($_POST['asset_tag']);
    $asset_type        = sanitize($_POST['asset_type']);
    $brand             = sanitize($_POST['brand']);
    $model             = sanitize($_POST['model']);
    $serial_number     = sanitize($_POST['serial_number']);
    $inventory_number  = sanitize($_POST['inventory_number'] ?? '');
    $location          = sanitize($_POST['location']);
    $department        = sanitize($_POST['department'] ?? '');
    $asset_group       = sanitize($_POST['asset_group'] ?? '');
    $status            = sanitize($_POST['status']);
    $condition         = sanitize($_POST['condition'] ?? 'good');
    $notes             = sanitize($_POST['notes']);
    
    $assigned_to       = !empty($_POST['assigned_to'])      ? (int)$_POST['assigned_to']      : null;
    $tech_in_charge    = !empty($_POST['tech_in_charge'])   ? (int)$_POST['tech_in_charge']   : null;
    $alternate_user    = sanitize($_POST['alternate_user'] ?? '');
    
    $purchase_date     = !empty($_POST['purchase_date'])    ? $_POST['purchase_date']    : null;
    $warranty_expiry   = !empty($_POST['warranty_expiry'])  ? $_POST['warranty_expiry']  : null;
    $purchase_price    = !empty($_POST['purchase_price'])   ? (float)$_POST['purchase_price']  : null;
    $salvage_value     = !empty($_POST['salvage_value'])    ? (float)$_POST['salvage_value']    : 0;
    $useful_life_years = !empty($_POST['useful_life_years'])? (int)$_POST['useful_life_years']  : 5;
    $supplier          = sanitize($_POST['supplier'] ?? '');
    $last_inventory_date = !empty($_POST['last_inventory_date']) ? $_POST['last_inventory_date'] : null;
    
    $os_name           = sanitize($_POST['os_name'] ?? '');
    $os_version        = sanitize($_POST['os_version'] ?? '');
    $os_architecture   = sanitize($_POST['os_architecture'] ?? '');
    $os_service_pack   = sanitize($_POST['os_service_pack'] ?? '');
    $os_product_key    = sanitize($_POST['os_product_key'] ?? '');
    
    $ip_address        = sanitize($_POST['ip_address'] ?? '');
    $mac_address       = sanitize($_POST['mac_address'] ?? '');
    $network_domain    = sanitize($_POST['network_domain'] ?? '');
    $gateway           = sanitize($_POST['gateway'] ?? '');
    $dns_server        = sanitize($_POST['dns_server'] ?? '');
    
    $cpu               = sanitize($_POST['cpu'] ?? '');
    $cpu_cores         = !empty($_POST['cpu_cores']) ? (int)$_POST['cpu_cores'] : null;
    $ram_gb            = !empty($_POST['ram_gb'])    ? (int)$_POST['ram_gb']    : null;
    $storage           = sanitize($_POST['storage'] ?? '');
    $gpu               = sanitize($_POST['gpu'] ?? '');
    $monitor           = sanitize($_POST['monitor'] ?? '');

    // Check duplicate asset_tag
    $chk = $db->prepare("SELECT asset_id FROM assets WHERE asset_tag = ?");
    $chk->bind_param('s', $asset_tag);
    $chk->execute();
    $dupRes = $chk->get_result();
    if ($dupRes && $dupRes->num_rows > 0) {
        $_SESSION['flash_message'] = "Asset Tag \"" . htmlspecialchars($asset_tag) . "\" already exists";
        $_SESSION['flash_type']    = 'error';
        $cat = $_GET['cat'] ?? 'all';
        header('Location: assets.php?cat=' . $cat);
        exit;
    }

    // Prepare insert statement
    $stmt = $db->prepare("
        INSERT INTO assets (
            asset_name, asset_tag, asset_type, brand, model, serial_number,
            inventory_number, location, department, asset_group,
            assigned_to, tech_in_charge, alternate_user,
            purchase_date, warranty_expiry, purchase_price,
            salvage_value, useful_life_years, supplier,
            last_inventory_date, condition_status, status, notes,
            os_name, os_version, os_architecture, os_service_pack, os_product_key,
            ip_address, mac_address, network_domain, gateway, dns_server,
            cpu, cpu_cores, ram_gb, storage, gpu, monitor, created_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
    ");

    $assignedSQL     = $assigned_to     ? $assigned_to     : null;
    $techSQL         = $tech_in_charge  ? $tech_in_charge  : null;

    $stmt->bind_param(
        'ssssssssssiiisssiddsssssssssss',
        $asset_name, $asset_tag, $asset_type, $brand, $model, $serial_number,
        $inventory_number, $location, $department, $asset_group,
        $assignedSQL, $techSQL, $alternate_user,
        $purchase_date, $warranty_expiry, $purchase_price,
        $salvage_value, $useful_life_years, $supplier,
        $last_inventory_date, $condition, $status, $notes,
        $os_name, $os_version, $os_architecture, $os_service_pack, $os_product_key,
        $ip_address, $mac_address, $network_domain, $gateway, $dns_server,
        $cpu, $cpu_cores, $ram_gb, $storage, $gpu, $monitor
    );

    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'create_asset', 'Assets', "Created new asset: $asset_name ($asset_tag)");
        $_SESSION['flash_message'] = 'Asset created successfully!';
        $_SESSION['flash_type']    = 'success';
    } else {
        $_SESSION['flash_message'] = 'Error creating asset: ' . $stmt->error;
        $_SESSION['flash_type']    = 'error';
    }

    $cat = $_GET['cat'] ?? 'all';
    header('Location: assets.php?cat=' . $cat);
    exit;
}

// Handle Update Asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid CSRF token';
        $_SESSION['flash_type']    = 'error';
        header('Location: assets.php');
        exit;
    }

    $asset_id = (int)$_POST['asset_id'];
    
    // Sanitize all inputs (same as create)
    $asset_name = sanitize($_POST['asset_name']);
    $asset_tag = sanitize($_POST['asset_tag']);
    $asset_type = sanitize($_POST['asset_type']);
    $brand = sanitize($_POST['brand']);
    $model = sanitize($_POST['model']);
    $serial_number = sanitize($_POST['serial_number']);
    $inventory_number = sanitize($_POST['inventory_number'] ?? '');
    $location = sanitize($_POST['location']);
    $department = sanitize($_POST['department'] ?? '');
    $asset_group = sanitize($_POST['asset_group'] ?? '');
    $status = sanitize($_POST['status']);
    $condition = sanitize($_POST['condition'] ?? 'good');
    $notes = sanitize($_POST['notes']);
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $tech_in_charge = !empty($_POST['tech_in_charge']) ? (int)$_POST['tech_in_charge'] : null;
    $alternate_user = sanitize($_POST['alternate_user'] ?? '');
    $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
    $warranty_expiry = !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null;
    $purchase_price = !empty($_POST['purchase_price']) ? (float)$_POST['purchase_price'] : null;
    $salvage_value = !empty($_POST['salvage_value']) ? (float)$_POST['salvage_value'] : 0;
    $useful_life_years = !empty($_POST['useful_life_years']) ? (int)$_POST['useful_life_years'] : 5;
    $supplier = sanitize($_POST['supplier'] ?? '');
    $last_inventory_date = !empty($_POST['last_inventory_date']) ? $_POST['last_inventory_date'] : null;
    $os_name = sanitize($_POST['os_name'] ?? '');
    $os_version = sanitize($_POST['os_version'] ?? '');
    $os_architecture = sanitize($_POST['os_architecture'] ?? '');
    $os_service_pack = sanitize($_POST['os_service_pack'] ?? '');
    $os_product_key = sanitize($_POST['os_product_key'] ?? '');
    $ip_address = sanitize($_POST['ip_address'] ?? '');
    $mac_address = sanitize($_POST['mac_address'] ?? '');
    $network_domain = sanitize($_POST['network_domain'] ?? '');
    $gateway = sanitize($_POST['gateway'] ?? '');
    $dns_server = sanitize($_POST['dns_server'] ?? '');
    $cpu = sanitize($_POST['cpu'] ?? '');
    $cpu_cores = !empty($_POST['cpu_cores']) ? (int)$_POST['cpu_cores'] : null;
    $ram_gb = !empty($_POST['ram_gb']) ? (int)$_POST['ram_gb'] : null;
    $storage = sanitize($_POST['storage'] ?? '');
    $gpu = sanitize($_POST['gpu'] ?? '');
    $monitor = sanitize($_POST['monitor'] ?? '');

    $fieldsToUpdate = [
        'asset_name' => $asset_name,
        'asset_tag' => $asset_tag,
        'asset_type' => $asset_type,
        'brand' => $brand,
        'model' => $model,
        'serial_number' => $serial_number,
        'inventory_number' => $inventory_number,
        'location' => $location,
        'department' => $department,
        'asset_group' => $asset_group,
        'assigned_to' => $assigned_to,
        'tech_in_charge' => $tech_in_charge,
        'alternate_user' => $alternate_user,
        'purchase_date' => $purchase_date,
        'warranty_expiry' => $warranty_expiry,
        'purchase_price' => $purchase_price,
        'salvage_value' => $salvage_value,
        'useful_life_years' => $useful_life_years,
        'supplier' => $supplier,
        'last_inventory_date' => $last_inventory_date,
        'condition_status' => $condition,
        'status' => $status,
        'notes' => $notes,
        'os_name' => $os_name,
        'os_version' => $os_version,
        'os_architecture' => $os_architecture,
        'os_service_pack' => $os_service_pack,
        'os_product_key' => $os_product_key,
        'ip_address' => $ip_address,
        'mac_address' => $mac_address,
        'network_domain' => $network_domain,
        'gateway' => $gateway,
        'dns_server' => $dns_server,
        'cpu' => $cpu,
        'cpu_cores' => $cpu_cores,
        'ram_gb' => $ram_gb,
        'storage' => $storage,
        'gpu' => $gpu,
        'monitor' => $monitor
    ];

    $setParts = [];
    $types = '';
    $values = [];
    foreach ($fieldsToUpdate as $col => $val) {
        $setParts[] = "$col = ?";
        if (in_array($col, ['assigned_to','tech_in_charge','useful_life_years','cpu_cores','ram_gb'])) {
            $types .= 'i';
        } elseif (in_array($col, ['purchase_price','salvage_value'])) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $values[] = $val;
    }
    $types .= 'i';
    $values[] = $asset_id;

    $sql = "UPDATE assets SET " . implode(', ', $setParts) . " WHERE asset_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'update_asset', 'Assets', "Updated asset: $asset_name");
        $_SESSION['flash_message'] = 'Asset updated successfully!';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Error updating asset: ' . $stmt->error;
        $_SESSION['flash_type'] = 'error';
    }
    
    $cat = $_GET['cat'] ?? 'all';
    header('Location: assets.php?cat=' . $cat);
    exit;
}

// Handle Delete Asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $asset_id = (int)$_POST['asset_id'];
    
    $stmt = $db->prepare("DELETE FROM assets WHERE asset_id = ?");
    $stmt->bind_param('i', $asset_id);
    
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'delete_asset', 'Assets', "Deleted asset ID: $asset_id");
        $_SESSION['flash_message'] = 'Asset deleted successfully!';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Error deleting asset: ' . $stmt->error;
        $_SESSION['flash_type'] = 'error';
    }
    $cat = $_GET['cat'] ?? 'all';
    header('Location: assets.php?cat=' . $cat);
    exit;
}

// Current category from URL
$cat = isset($_GET['cat']) ? sanitize($_GET['cat']) : 'all';
if (!array_key_exists($cat, $ASSET_CATEGORIES)) $cat = 'all';
$currentCat = $ASSET_CATEGORIES[$cat];
$activeAssetCategory = $cat;

// Count per category for sidebar badges
$catCounts = [];
foreach ($ASSET_CATEGORIES as $key => $catDef) {
    if ($key === 'all') {
        $r = $db->query("SELECT COUNT(*) as cnt FROM assets");
    } else {
        $typesArr = $catDef['types'];
        if (count($typesArr) > 0) {
            $placeholders = implode(',', array_fill(0, count($typesArr), '?'));
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM assets WHERE asset_type IN ($placeholders)");
            $bindTypes = str_repeat('s', count($typesArr));
            $stmt->bind_param($bindTypes, ...$typesArr);
            $stmt->execute();
            $r = $stmt->get_result();
        } else {
            $r = $db->query("SELECT COUNT(*) as cnt FROM assets");
        }
    }
    $catCounts[$key] = $r ? $r->fetch_assoc()['cnt'] : 0;
}

// Get Assets with filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$location = isset($_GET['location']) ? sanitize($_GET['location']) : '';

$sql = "SELECT a.*, 
               u.full_name as assigned_user_name,
               t.full_name as tech_name
        FROM assets a 
        LEFT JOIN users u ON a.assigned_to = u.user_id 
        LEFT JOIN users t ON a.tech_in_charge = t.user_id
        WHERE 1=1";
$params = [];
$types = '';

// Apply category filter
if ($cat !== 'all' && !empty($currentCat['types'])) {
    $typeList = implode("','", array_map('db_escape', $currentCat['types']));
    $sql .= " AND a.asset_type IN ('$typeList')";
}

if ($search) {
    $sql .= " AND (a.asset_name LIKE ? OR a.asset_tag LIKE ? OR a.serial_number LIKE ? OR a.ip_address LIKE ? OR a.os_name LIKE ? OR a.inventory_number LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_fill(0, 6, $searchTerm);
    $types .= 'ssssss';
}

if ($type) {
    $sql .= " AND a.asset_type = ?";
    $params[] = $type;
    $types .= 's';
}

if ($status) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($location) {
    $sql .= " AND a.location LIKE ?";
    $params[] = "%$location%";
    $types .= 's';
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $db->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$assets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group assets by assigned user
$assetsByUser = [];
foreach ($assets as $a) {
    $key = $a['assigned_user_name'] ?: 'Unassigned';
    $assetsByUser[$key][] = $a;
}
ksort($assetsByUser);

// Get Statistics
$statsSQL = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_count,
    SUM(CASE WHEN warranty_expiry < NOW() AND warranty_expiry IS NOT NULL THEN 1 ELSE 0 END) as warranty_expired_count,
    SUM(CASE WHEN warranty_expiry BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as warranty_expiring_count
    FROM assets";
$stats = $db->query($statsSQL)->fetch_assoc();

// Get warranty expiring assets (within 90 days)
$warrantyAlerts = $db->query("
    SELECT a.asset_id, a.asset_name, a.asset_tag, a.asset_type, a.warranty_expiry,
           a.location, u.full_name as assigned_user_name,
           DATEDIFF(a.warranty_expiry, NOW()) as days_left
    FROM assets a
    LEFT JOIN users u ON a.assigned_to = u.user_id
    WHERE a.warranty_expiry IS NOT NULL
      AND a.warranty_expiry >= NOW()
      AND a.warranty_expiry <= DATE_ADD(NOW(), INTERVAL 90 DAY)
    ORDER BY a.warranty_expiry ASC
")->fetch_all(MYSQLI_ASSOC);

// Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $exportRows = $db->query("
        SELECT a.asset_tag, a.inventory_number, a.asset_name, a.asset_type,
               a.brand, a.model, a.serial_number, a.status, a.location, a.department,
               u.full_name as assigned_user_name, a.ip_address, a.mac_address,
               a.os_name, a.warranty_expiry, a.purchase_price, a.notes
        FROM assets a
        LEFT JOIN users u ON a.assigned_to = u.user_id
        ORDER BY a.asset_tag
    ")->fetch_all(MYSQLI_ASSOC);
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="assets_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
    echo '<table border="1">';
    echo '<tr style="background:#2d6a4f;color:#fff;font-weight:bold;">
        <th>Asset Tag</th><th>Inv No</th><th>Name</th><th>Type</th><th>Brand</th><th>Model</th>
        <th>S/N</th><th>Status</th><th>Location</th><th>Dept</th><th>Assigned</th><th>IP</th><th>MAC</th>
        <th>OS</th><th>Warranty</th><th>Price</th><th>Notes</th>
    </tr>';
    foreach ($exportRows as $r) {
        echo '<tr>
            <td>' . htmlspecialchars($r['asset_tag'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['inventory_number'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['asset_name'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['asset_type'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['brand'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['model'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['serial_number'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['status'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['location'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['department'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['assigned_user_name'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['ip_address'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['mac_address'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['os_name'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['warranty_expiry'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['purchase_price'] ?? '') . '</td>
            <td>' . htmlspecialchars($r['notes'] ?? '') . '</td>
        </tr>';
    }
    echo '</table>';
    exit;
}

// Get Users for Assignment
$users = $db->query("SELECT user_id, full_name FROM users WHERE status = 'active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = ui_text('page.title.assets') ?: 'ทรัพย์สิน IT';
$activePage = 'assets';
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <!-- Breadcrumb -->
    <div class="breadcrumb-nav">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../admin/dashboard.php"><i class="fas fa-home"></i></a></li>
            <li class="breadcrumb-separator">&rsaquo;</li>
            <li class="breadcrumb-item active">
                <i class="fas fa-box"></i> Assets
            </li>
        </ol>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
<h1><i class="fas fa-boxes-stacked" style="color:#4299e1;"></i> <?= ui_text('page.title.assets') ?: 'ทรัพย์สิน IT' ?>
                <span class="badge badge-blue"><?= count($assets) ?> <?= ui_text('nav.assets') ?: 'ทรัพย์สิน' ?></span>
            </h1>
<p class="page-subtitle"><?= ui_text('page.subtitle.assets') ?: 'ระบบจัดการทรัพย์สินไอทีครบวงจร' ?></p>
        </div>
        <div class="page-actions">
            <button id="openAssetOverlayBtn" type="button" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-plus me-1"></i> <?= ui_text('button.add_asset') ?: 'Add new assets' ?>
            </button>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4 g-3">
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <div class="stat-card stat-card-compact gradient-blue">
                <div class="stat-icon">
                    <i class="fas fa-boxes-stacked"></i>
                </div>
                <div class="stat-content">
                    <h4><?= number_format($stats['total'] ?? 0) ?></h4>
                    <p>ทรัพย์สินทั้งหมด</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <div class="stat-card stat-card-compact gradient-green">
                <div class="stat-icon">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="stat-content">
                    <h4><?= number_format($stats['active_count'] ?? 0) ?></h4>
                    <p>ใช้งานได้</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <div class="stat-card stat-card-compact gradient-orange">
                <div class="stat-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-content">
                    <h4><?= number_format($stats['maintenance_count'] ?? 0) ?></h4>
                    <p>กำลังบำรุงรักษา</p>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <div class="stat-card stat-card-compact gradient-red">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h4><?= ($stats['warranty_expired_count'] ?? 0) + ($stats['warranty_expiring_count'] ?? 0) ?></h4>
                    <p>ประกันใกล้หมด</p>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .stat-card-sm {
        padding: 0.5rem;
        height: 80px;
    }
    .stat-icon-sm {
        width: 35px;
        height: 35px;
        font-size: 1rem;
    }
    .stat-card-sm h4 {
        font-size: 1.1rem;
        margin-bottom: 0.25rem;
    }
    .category-panel {
        border-left: 4px solid #4299e1;
    }
    .cat-btn:hover {
        transform: translateX(5px);
        transition: all 0.2s ease;
    }
    </style>

    <!-- Warranty Alerts -->
    <?php if (!empty($warrantyAlerts)): ?>
    <div class="alert alert-warning warranty-alert">
        <h5><i class="fas fa-clock"></i> Warranty Expiring Soon (<?= count($warrantyAlerts) ?> assets)</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Assigned</th>
                        <th>Expires</th>
                        <th>Days Left</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($warrantyAlerts, 0, 5) as $alert): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($alert['asset_tag']) ?></strong></td>
                        <td><?= htmlspecialchars($alert['asset_name']) ?></td>
                        <td><span class="badge badge-info"><?= strtoupper($alert['asset_type']) ?></span></td>
                        <td><?= htmlspecialchars($alert['assigned_user_name'] ?? 'Unassigned') ?></td>
                        <td><?= date('M j', strtotime($alert['warranty_expiry'])) ?></td>
                        <td><span class="badge <?= ($alert['days_left'] <= 14) ? 'bg-danger' : 'bg-warning' ?>">
                            <?= $alert['days_left'] ?> days
                        </span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Category Filter + Table Layout -->
    <div class="row g-4 align-items-stretch">
        <div class="col-12 col-lg-3 order-lg-1">
            <div class="card h-100 category-panel sticky-top" style="top: 20px;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-list me-1"></i> <?= htmlspecialchars($ASSET_CATEGORIES["all"]["label"] ?? "Asset Categories") ?></h6>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleCategoryPanel()">?</button>
                </div>
                <div class="card-body p-2" style="max-height: 70vh; overflow-y: auto;">
                    <?php foreach ($ASSET_CATEGORIES as $key => $catDef): ?>
                        <a href="?cat=<?= $key ?>" class="btn btn-sm d-block mb-1 w-100 text-start cat-btn <?= $cat === $key ? "btn-primary active" : "" ?>" style="<?= $cat === $key ? "background-color: " . ($catDef["color"] ?? "#4299e1") . "; color: white !important; border-color: " . ($catDef["color"] ?? "#4299e1") . ";" : "" ?>">
                            <i class="fas <?= $catDef["icon"] ?? "fa-layer-group" ?> me-1"></i>
                            <?= htmlspecialchars($catDef["label"]) ?>
                            <span class="badge badge-light ms-1"><?= $catCounts[$key] ?? 0 ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-9 order-lg-2">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list text-primary"></i> <?= htmlspecialchars($currentCat["label"]) ?> (<?= count($assets) ?>)</h5>
                    <div class="d-flex gap-2 align-items-center">
                        <button class="btn btn-sm btn-outline-primary d-md-none" onclick="toggleCategoryPanel()">
                            <i class="fas fa-list"></i> เมนู
                        </button>
                        <div class="input-group input-group-sm" style="width: 280px;">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="quickSearch" placeholder="ค้นหา asset...">
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tag / Inv#</th>
                                    <th>Asset</th>
                                    <th>Type</th>
                                    <th>Brand/Model</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                    <th>Assigned</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($asset["asset_tag"]) ?></strong>
                                        <?php if ($asset["inventory_number"]): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($asset["inventory_number"]) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($asset["asset_name"]) ?></td>
                                    <td><span class="badge bg-info"><?= strtoupper($asset["asset_type"]) ?></span></td>
                                    <td>
                                        <strong><?= htmlspecialchars($asset["brand"] ?? "") ?></strong>
                                        <?php if ($asset["model"]): ?><br><small><?= htmlspecialchars($asset["model"]) ?></small><?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-<?= ["active" => "success", "maintenance" => "warning", "inactive" => "secondary", "retired" => "dark"][ $asset["status"] ] ?>"><?= ucfirst($asset["status"]) ?></span></td>
                                    <td><?= htmlspecialchars($asset["location"] ?? "N/A") ?></td>
                                    <td><?= htmlspecialchars($asset["assigned_user_name"] ?? "Unassigned") ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="assetsdetail.php?id=<?= $asset["asset_id"] ?>" class="btn btn-outline-primary" title="View details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($isAdmin): ?>
                                            <button class="btn btn-outline-success" onclick="editAsset(<?= htmlspecialchars(json_encode($asset), ENT_QUOTES, 'UTF-8') ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="confirmDelete(<?= $asset["asset_id"] ?>, &quot;<?= addslashes($asset["asset_name"]) ?>&quot;)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($assets)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted"><?= ui_text("tickets.empty.body") ?: "ไม่มีทรัพย์สินในหมวดนี้" ?></p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="asset-overlay" id="assetOverlay">
    <div class="asset-overlay-backdrop" onclick="closeAssetOverlay()"></div>
    <div class="asset-overlay-content card">
        <div class="card-header d-flex justify-content-between align-items-start">
            <div>
                <h5 id="assetFormTitle" class="mb-1"><i class="fas fa-plus"></i> Add New Asset</h5>
                <p class="text-muted small mb-0">ใช้พื้นที่นี้เพื่อจัดเก็บข้อมูลรายละเอียดของทรัพย์สิน</p>
            </div>
            <button type="button" class="btn-close" aria-label="Close" onclick="closeAssetOverlay()"></button>
        </div>
        <div class="card-body p-0">
            <form id="assetForm" method="POST" class="asset-form px-3 pt-3 pb-2">
                <?= csrf_input() ?>
                <input type="hidden" name="action" id="assetFormAction" value="create">
                <input type="hidden" name="asset_id" id="assetFormId">
                <ul class="nav nav-tabs nav-justified" id="assetTabs">
                    <li class="nav-item">
                        <a class="nav-link active" href="#basic" data-bs-toggle="tab">Basic</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#hardware" data-bs-toggle="tab">Hardware</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#network" data-bs-toggle="tab">Network</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#purchase" data-bs-toggle="tab">Purchase</a>
                    </li>
                </ul>
                <div class="tab-content mt-3">
                    <div class="tab-pane fade show active" id="basic">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="asset_tag">Asset Tag <span class="text-danger">*</span></label>
                                <input id="asset_tag" type="text" name="asset_tag" class="form-control" required maxlength="50">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="inventory_number">Inventory #</label>
                                <input id="inventory_number" type="text" name="inventory_number" class="form-control">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="asset_name">Asset Name <span class="text-danger">*</span></label>
                                <input id="asset_name" type="text" name="asset_name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="asset_type">Type <span class="text-danger">*</span></label>
                                <select id="asset_type" name="asset_type" class="form-select" required>
                                    <option value="">Select type...</option>
                                    <?php foreach ($ASSET_CATEGORIES as $catKey => $cat): ?>
                                        <?php if (!empty($cat["types"])): ?>
                                            <optgroup label="<?= htmlspecialchars($cat["label"]) ?>">
                                                <?php foreach ($cat["types"] as $t): ?>
                                                    <option value="<?= htmlspecialchars($t) ?>"><?= ucwords(str_replace('_', ' ', $t)) ?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-0">
                            <div class="col-md-6">
                                <label class="form-label" for="brand">Brand</label>
                                <input id="brand" type="text" name="brand" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="model">Model</label>
                                <input id="model" type="text" name="model" class="form-control">
                            </div>
                        </div>
                        <div class="row g-3 mt-0">
                            <div class="col-md-4">
                                <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                                <select id="status" name="status" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="retired">Retired</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="location">Location <span class="text-danger">*</span></label>
                                <input id="location" type="text" name="location" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="department">Department</label>
                                <input id="department" type="text" name="department" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="hardware">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="os_name">OS Name</label>
                                <input id="os_name" type="text" name="os_name" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="os_version">OS Version</label>
                                <input id="os_version" type="text" name="os_version" class="form-control">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="cpu">CPU</label>
                                <input id="cpu" type="text" name="cpu" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="cpu_cores">CPU Cores</label>
                                <input id="cpu_cores" type="number" name="cpu_cores" class="form-control" min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="ram_gb">RAM (GB)</label>
                                <input id="ram_gb" type="number" name="ram_gb" class="form-control" min="1">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="storage">Storage</label>
                                <input id="storage" type="text" name="storage" class="form-control" placeholder="e.g., 512GB SSD">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="gpu">GPU</label>
                                <input id="gpu" type="text" name="gpu" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="network">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="ip_address">IP Address</label>
                                <input id="ip_address" type="text" name="ip_address" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="mac_address">MAC Address</label>
                                <input id="mac_address" type="text" name="mac_address" class="form-control">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="gateway">Gateway</label>
                                <input id="gateway" type="text" name="gateway" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="dns_server">DNS Server</label>
                                <input id="dns_server" type="text" name="dns_server" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="purchase">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label" for="purchase_date">Purchase Date</label>
                                <input id="purchase_date" type="date" name="purchase_date" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="warranty_expiry">Warranty Expiry</label>
                                <input id="warranty_expiry" type="date" name="warranty_expiry" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="purchase_price">Price (THB)</label>
                                <input id="purchase_price" type="number" name="purchase_price" step="0.01" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="useful_life_years">Useful Life (Years)</label>
                                <input id="useful_life_years" type="number" name="useful_life_years" value="5" min="1" max="20" class="form-control">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="supplier">Supplier</label>
                                <input id="supplier" type="text" name="supplier" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="last_inventory_date">Last Inventory</label>
                                <input id="last_inventory_date" type="date" name="last_inventory_date" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control notes-area" rows="3" placeholder="Additional information..."></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="assigned_to">Assigned To</label>
                        <select id="assigned_to" name="assigned_to" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= $user["user_id"] ?>"><?= htmlspecialchars($user["full_name"]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="tech_in_charge">Tech Support</label>
                        <select id="tech_in_charge" name="tech_in_charge" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= $user["user_id"] ?>"><?= htmlspecialchars($user["full_name"]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="text-center mt-3 mb-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Save Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.asset-overlay {
    position: fixed;
    inset: 0;
    background: rgba(255, 255, 255, 0);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    z-index: 1050;
}
.asset-overlay.active {
    display: flex;
}
.asset-overlay-content {
    width: min(420px, 100%);
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    border-radius: 1rem;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.35);
}
.asset-overlay-content .card-body {
    max-height: calc(100vh - 120px);
    overflow-y: auto;
}
.asset-overlay .nav-tabs {
    border-bottom: 1px solid #e2e8f0;
}
.notes-area {
    min-height: 110px;
}
.asset-overlay .nav-tabs .nav-link {
    font-size: 0.9rem;
    padding: 0.4rem 0.75rem;
}
@media (max-width: 576px) {
    .asset-overlay {
        padding: 1rem;
    }
}
</style>

<script>
const assetOverlay = document.getElementById('assetOverlay');
const assetForm = document.getElementById('assetForm');
const assetFormTitle = document.getElementById('assetFormTitle');
const assetFormAction = document.getElementById('assetFormAction');
const assetFormId = document.getElementById('assetFormId');
const resetAssetFormBtn = document.getElementById('resetAssetFormBtn');
const quickSearch = document.getElementById('quickSearch');
const openAssetOverlayBtn = document.getElementById('openAssetOverlayBtn');

function showAssetOverlay() {
    if (assetOverlay) {
        assetOverlay.classList.add('active');
    }
}

function closeAssetOverlay() {
    if (assetOverlay) {
        assetOverlay.classList.remove('active');
    }
}

window.resetAssetForm = function resetAssetForm() {
    if (!assetForm) return;
    assetForm.reset();
    assetFormAction.value = 'create';
    assetFormId.value = '';
    assetFormTitle.innerHTML = '<i class="fas fa-plus"></i> Add New Asset';
};

window.editAsset = function editAsset(asset) {
    if (!assetForm) return;
    assetFormTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Asset';
    assetFormAction.value = 'update';
    assetFormId.value = asset.asset_id;

    const fields = {
        'asset_tag': asset.asset_tag,
        'asset_name': asset.asset_name,
        'asset_type': asset.asset_type,
        'brand': asset.brand || '',
        'model': asset.model || '',
        'serial_number': asset.serial_number || '',
        'inventory_number': asset.inventory_number || '',
        'status': asset.status,
        'location': asset.location || '',
        'department': asset.department || '',
        'notes': asset.notes || '',
        'assigned_to': asset.assigned_to || '',
        'tech_in_charge': asset.tech_in_charge || '',
        'os_name': asset.os_name || '',
        'os_version': asset.os_version || '',
        'os_architecture': asset.os_architecture || '',
        'os_service_pack': asset.os_service_pack || '',
        'os_product_key': asset.os_product_key || '',
        'ip_address': asset.ip_address || '',
        'mac_address': asset.mac_address || '',
        'gateway': asset.gateway || '',
        'dns_server': asset.dns_server || '',
        'cpu': asset.cpu || '',
        'cpu_cores': asset.cpu_cores || '',
        'ram_gb': asset.ram_gb || '',
        'storage': asset.storage || '',
        'gpu': asset.gpu || '',
        'purchase_date': asset.purchase_date || '',
        'warranty_expiry': asset.warranty_expiry || '',
        'purchase_price': asset.purchase_price || '',
        'useful_life_years': asset.useful_life_years || '',
        'supplier': asset.supplier || '',
        'last_inventory_date': asset.last_inventory_date || '',
        'alternate_user': asset.alternate_user || '',
        'monitor': asset.monitor || ''
    };

    Object.keys(fields).forEach(key => {
        const element = document.querySelector(`[name="${key}"]`);
        if (element) {
            element.value = fields[key];
        }
    });

    showAssetOverlay();
};

function confirmDelete(id, name) {
    if (confirm(`Delete "${name}"? This cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="asset_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

;(function initAssetPage() {
    resetAssetForm();
    if (resetAssetFormBtn) {
        resetAssetFormBtn.addEventListener('click', resetAssetForm);
    }
    if (openAssetOverlayBtn) {
        openAssetOverlayBtn.addEventListener('click', () => {
            resetAssetForm();
            showAssetOverlay();
        });
    }

    if (quickSearch) {
        quickSearch.addEventListener('input', function (e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }
})();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>



