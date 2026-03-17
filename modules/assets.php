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

// ── Flash message (PRG pattern) ────────────────────────────────
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
    // ── Basic ──────────────────────────────────────────────
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
    // ── Personnel ──────────────────────────────────────────
    $assigned_to       = !empty($_POST['assigned_to'])      ? (int)$_POST['assigned_to']      : null;
    $tech_in_charge    = !empty($_POST['tech_in_charge'])   ? (int)$_POST['tech_in_charge']   : null;
    $alternate_user    = sanitize($_POST['alternate_user'] ?? '');
    // ── Purchase ───────────────────────────────────────────
    $purchase_date     = !empty($_POST['purchase_date'])    ? $_POST['purchase_date']    : null;
    $warranty_expiry   = !empty($_POST['warranty_expiry'])  ? $_POST['warranty_expiry']  : null;
    $purchase_price    = !empty($_POST['purchase_price'])   ? (float)$_POST['purchase_price']  : null;
    $salvage_value     = !empty($_POST['salvage_value'])    ? (float)$_POST['salvage_value']    : 0;
    $useful_life_years = !empty($_POST['useful_life_years'])? (int)$_POST['useful_life_years']  : 5;
    $supplier          = sanitize($_POST['supplier'] ?? '');
    $last_inventory_date = !empty($_POST['last_inventory_date']) ? $_POST['last_inventory_date'] : null;
    // ── OS ─────────────────────────────────────────────────
    $os_name           = sanitize($_POST['os_name'] ?? '');
    $os_version        = sanitize($_POST['os_version'] ?? '');
    $os_architecture   = sanitize($_POST['os_architecture'] ?? '');
    $os_service_pack   = sanitize($_POST['os_service_pack'] ?? '');
    $os_product_key    = sanitize($_POST['os_product_key'] ?? '');
    // ── Network ────────────────────────────────────────────
    $ip_address        = sanitize($_POST['ip_address'] ?? '');
    $mac_address       = sanitize($_POST['mac_address'] ?? '');
    $network_domain    = sanitize($_POST['network_domain'] ?? '');
    $gateway           = sanitize($_POST['gateway'] ?? '');
    $dns_server        = sanitize($_POST['dns_server'] ?? '');
    // ── Hardware ───────────────────────────────────────────
    $cpu               = sanitize($_POST['cpu'] ?? '');
    $cpu_cores         = !empty($_POST['cpu_cores']) ? (int)$_POST['cpu_cores'] : null;
    $ram_gb            = !empty($_POST['ram_gb'])    ? (int)$_POST['ram_gb']    : null;
    $storage           = sanitize($_POST['storage'] ?? '');
    $gpu               = sanitize($_POST['gpu'] ?? '');
    $monitor           = sanitize($_POST['monitor'] ?? '');

    // Build INSERT values
    $assignedSQL     = $assigned_to     ? $assigned_to     : null;
    $techSQL         = $tech_in_charge  ? $tech_in_charge  : null;

    // ── ตรวจสอบ asset_tag ซ้ำก่อน INSERT ──────────────────────
    $chk = $db->prepare("SELECT asset_id FROM assets WHERE asset_tag = ?");
    $chk->bind_param('s', $asset_tag);
    $chk->execute();
    $dupRes = $chk->get_result();
    if ($dupRes && $dupRes->num_rows > 0) {
        $_SESSION['flash_message'] = "Asset Tag \"" . htmlspecialchars($asset_tag) . "\" มีในระบบแล้ว กรุณาใช้รหัสสินทรัพย์ใหม่";
        $_SESSION['flash_type']    = 'error';
        $cat = $_GET['cat'] ?? 'all';
        header('Location: assets.php?cat=' . $cat);
        exit;
    }

    // Prepare insert statement
    $stmt = $db->prepare(
        "INSERT INTO assets (
            asset_name, asset_tag, asset_type, brand, model, serial_number,
            inventory_number, location, department, asset_group,
            assigned_to, tech_in_charge, alternate_user,
            purchase_date, warranty_expiry, purchase_price,
            salvage_value, useful_life_years, supplier,
            last_inventory_date, condition_status, status, notes,
            os_name, os_version, os_architecture, os_service_pack, os_product_key,
            ip_address, mac_address, network_domain, gateway, dns_server,
            cpu, cpu_cores, ram_gb, storage, gpu, monitor, created_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
    );

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
        logActivity($_SESSION['user_id'], 'เพิ่มสินทรัพย์', 'Assets', "เพิ่ม: $asset_name ($asset_tag)");
        $_SESSION['flash_message'] = 'เพิ่มสินทรัพย์สำเร็จ!';
        $_SESSION['flash_type']    = 'success';
    } else {
        $_SESSION['flash_message'] = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $_SESSION['flash_type']    = 'error';
    }

    $cat = $_GET['cat'] ?? 'all';
    header('Location: assets.php?cat=' . $cat);
    exit;
}

// Handle Update Asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    // CSRF validation
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid CSRF token';
        $_SESSION['flash_type']    = 'error';
        header('Location: assets.php');
        exit;
    }

    $asset_id          = (int)$_POST['asset_id'];
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
    $purchase_date     = !empty($_POST['purchase_date'])    ? $_POST['purchase_date']   : null;
    $warranty_expiry   = !empty($_POST['warranty_expiry'])  ? $_POST['warranty_expiry'] : null;
    $purchase_price    = !empty($_POST['purchase_price'])   ? (float)$_POST['purchase_price']   : null;
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

    // build dynamic update with prepared statement
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
    // add asset_id for WHERE clause
    $types .= 'i';
    $values[] = $asset_id;

    $sql = "UPDATE assets SET " . implode(', ', $setParts) . " WHERE asset_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$values);
    if ($stmt->execute()) {
        logActivity($_SESSION['user_id'], 'อัปเดตสินทรัพย์', 'Assets', "อัปเดต: $asset_name");
        $_SESSION['flash_message'] = 'อัปเดตสินทรัพย์สำเร็จ!';
        $_SESSION['flash_type']    = 'success';
    } else {
        $_SESSION['flash_message'] = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $_SESSION['flash_type']    = 'error';
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
        logActivity($_SESSION['user_id'], 'ลบสินทรัพย์', 'Assets', "ลบ Asset ID: $asset_id");
        $_SESSION['flash_message'] = 'ลบสินทรัพย์สำเร็จ!';
        $_SESSION['flash_type']    = 'success';
    } else {
        $_SESSION['flash_message'] = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $_SESSION['flash_type']    = 'error';
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
    $typeList = implode("','", $currentCat['types']);
    $sql .= " AND a.asset_type IN ('$typeList')";
}

if ($search) {
    $sql .= " AND (a.asset_name LIKE ? OR a.asset_tag LIKE ? OR a.serial_number LIKE ? OR a.ip_address LIKE ? OR a.os_name LIKE ? OR a.inventory_number LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
    $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
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

// Group assets by assigned user (for "by user" view)
$assetsByUser = [];
foreach ($assets as $a) {
    $key = $a['assigned_to'] ? $a['assigned_user_name'] : '— ไม่ได้มอบหมาย —';
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

// ── Export Excel ──────────────────────────────────────────────
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
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr style="background:#2d6a4f;color:#fff;font-weight:bold;">
        <th>Asset Tag</th><th>Inventory No.</th><th>ชื่ออุปกรณ์</th><th>ประเภท</th>
        <th>Brand</th><th>Model</th><th>Serial Number</th><th>สถานะ</th>
        <th>Location</th><th>แผนก</th><th>ผู้รับผิดชอบ</th>
        <th>IP Address</th><th>MAC Address</th><th>OS</th>
        <th>Warranty Expiry</th><th>ราคาซื้อ (฿)</th><th>หมายเหตุ</th>
    </tr>';
    foreach ($exportRows as $r) {
        $e = fn($v) => htmlspecialchars($v ?? '', ENT_QUOTES);
        echo "<tr>
            <td>{$e($r['asset_tag'])}</td><td>{$e($r['inventory_number'])}</td>
            <td>{$e($r['asset_name'])}</td><td>{$e($r['asset_type'])}</td>
            <td>{$e($r['brand'])}</td><td>{$e($r['model'])}</td>
            <td>{$e($r['serial_number'])}</td><td>{$e($r['status'])}</td>
            <td>{$e($r['location'])}</td><td>{$e($r['department'])}</td>
            <td>{$e($r['assigned_user_name'])}</td><td>{$e($r['ip_address'])}</td>
            <td>{$e($r['mac_address'])}</td><td>{$e($r['os_name'])}</td>
            <td>{$e($r['warranty_expiry'])}</td><td>{$e($r['purchase_price'])}</td>
            <td>{$e($r['notes'])}</td>
        </tr>";
    }
    echo '</table></body></html>';
    exit;
}

// Get Users for Assignment
$users = $db->query("SELECT user_id, full_name FROM users WHERE status = 'active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

// Get Locations
$locations = $db->query("SELECT DISTINCT location FROM assets WHERE location IS NOT NULL AND location != '' ORDER BY location")->fetch_all(MYSQLI_ASSOC);
$pageTitle = ui_text('page.title.assets');
$activePage = 'assets';
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb-nav">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="dashboard.php"><i class="fas fa-home"></i></a>
                    </li>
                    <li class="breadcrumb-separator">&rsaquo;</li>
                    <li class="breadcrumb-item">
                        <a href="assets.php?cat=all"><?php echo ui_text('nav.assets'); ?></a>
                    </li>
                    <?php if ($cat !== 'all'): ?>
                    <li class="breadcrumb-separator">&rsaquo;</li>
                    <li class="breadcrumb-item active">
                        <i class="fas <?= $currentCat['icon'] ?>"></i> <?= $currentCat['label'] ?>
                    </li>
                    <?php endif; ?>
                </ol>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1>
                        <i class="fas <?= $currentCat['icon'] ?>" style="color:<?= $currentCat['color'] ?>;"></i>
                        <?= $currentCat['label'] ?>
                        <span class="asset-count-badge"><?= count($assets) ?> ��¡��</span>
                    </h1>
                </div>
                <div class="page-actions">
                    <?php if ($isAdmin): ?>
                    <button class="btn btn-primary" onclick="openCreateModal()">
                        <i class="fas fa-plus"></i>
                        ����<?= $cat !== 'all' ? $currentCat['label'] : '�Թ��Ѿ��' ?>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> show">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-box"></i>
                </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                        <p>�Թ��Ѿ�������</p>
                    </div>
                </div>

                <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                    <i class="fas fa-check-circle"></i>
                </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['active_count'] ?? 0); ?></h3>
                        <p>�Թ��Ѿ����ҹ��</p>
                    </div>
                </div>

                <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                    <i class="fas fa-tools"></i>
                </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['maintenance_count'] ?? 0); ?></h3>
                        <p>���������ҧ���ا�ѡ��</p>
                    </div>
                </div>

                <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['warranty_expired_count'] ?? 0); ?></h3>
                        <p>����Ѻ��Сѹ�������</p>
                    </div>
                </div>

                <?php if (($stats['warranty_expiring_count'] ?? 0) > 0): ?>
                <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ecc94b, #d69e2e);">
                    <i class="fas fa-clock"></i>
                </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['warranty_expiring_count']); ?></h3>
                        <p>��Сѹ������ (30 �ѹ)</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Warranty Alert Banner -->
            <?php if (!empty($warrantyAlerts)): ?>
            <div class="warranty-alert-banner">
                <div class="alert-header" onclick="toggleWarrantyAlert()">
                    <div class="alert-title">
                        <i class="fas fa-bell"></i>
                        ����͹����Ѻ��Сѹ (<?= count($warrantyAlerts) ?> ��¡�� ���� 90 �ѹ)
                    </div>
                    <span id="warrantyToggleIcon" class="warranty-toggle-icon">
                        <i class="fas fa-chevron-down"></i> ����������´
                    </span>
                </div>
                <div id="warrantyAlertBody" style="display:none;">
                    <table class="warranty-alert-table">
                        <thead>
                            <tr>
                                <th>Asset Tag</th><th>ชื่ออุปกรณ์</th><th>ประเภท</th>
                                <th>ผู้รับผิดชอบ</th><th>Location</th>
                                <th>วันหมดประกัน</th><th>เหลือ</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($warrantyAlerts as $wa):
                            $d = (int)$wa['days_left'];
                            $bc = $d <= 14 ? 'days-critical' : ($d <= 30 ? 'days-warning' : 'days-ok');
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($wa['asset_tag']) ?></strong></td>
                                <td><?= htmlspecialchars($wa['asset_name']) ?></td>
                                <td><span class="type-badge type-<?= $wa['asset_type'] ?>"><?= strtoupper($wa['asset_type']) ?></span></td>
                                <td><?= htmlspecialchars($wa['assigned_user_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($wa['location'] ?? '—') ?></td>
                                <td><?= date('d/m/Y', strtotime($wa['warranty_expiry'])) ?></td>
                                <td><span class="days-badge <?= $bc ?>"><?= $d ?> วัน</span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Assets Table -->
            <div class="card" id="tableView">
                <div class="card-toolbar">
                    <strong>
                        <i class="fas <?= $currentCat['icon'] ?>" style="color:<?= $currentCat['color'] ?>;"></i>
                        <?= $currentCat['label'] ?>
                        <span class="asset-count-badge"><?= count($assets) ?></span>
                    </strong>
                    <!-- Inline search -->
                    <form method="GET" class="asset-filter-form">
                        <input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>">
                        <div class="input-icon-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control"
                                   placeholder="���� Asset Tag, �����Ţ, ����, IP ���� Serial">
                        </div>
                        <select name="status" onchange="this.form.submit()" class="form-control form-select-compact">
                            <option value="">ทุกสถานะ</option>
                            <option value="active"      <?= $status==='active'     ?'selected':'' ?>>Active</option>
                            <option value="inactive"    <?= $status==='inactive'   ?'selected':'' ?>>Inactive</option>
                            <option value="maintenance" <?= $status==='maintenance'?'selected':'' ?>>Maintenance</option>
                            <option value="retired"     <?= $status==='retired'    ?'selected':'' ?>>Retired</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-search"></i> ค้นหา
                        </button>
                    </form>
                    <div class="toolbar-actions">
                        <button onclick="switchView('table')" id="btnTableView" class="btn btn-primary btn-sm view-toggle active">
                            <i class="fas fa-list"></i> รายการ
                        </button>
                        <button onclick="switchView('user')" id="btnUserView" class="btn btn-sm view-toggle">
                            <i class="fas fa-users"></i> แยกตามผู้รับผิดชอบ
                        </button>
                        <a href="?export=excel" class="btn btn-sm view-toggle btn-green">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </a>
                    </div>
                </div>
                <div id="viewTable">
                <table>
                    <thead>
                        <tr>
                            <th>Asset Tag / Inventory</th>
                            <th>ชื่ออุปกรณ์</th>
                            <th>ประเภท</th>
                            <th>Brand / Model</th>
                            <th>OS</th>
                            <th>IP Address</th>
                            <th>Location / แผนก</th>
                            <th>ผู้รับผิดชอบ</th>
                            <th>Warranty</th>
                            <th>สถานะ</th>
                            <th>การกระทำ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assets)): ?>
                        <tr>
                            <td colspan="11" class="table-empty">
                                <i class="fas fa-box"></i>
                                ไม่พบข้อมูลสินทรัพย์
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($assets as $asset): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($asset['asset_tag']); ?></strong>
                                    <?php if (!empty($asset['inventory_number'])): ?>
                                    <br><small class="meta-note">INV: <?= htmlspecialchars($asset['inventory_number']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($asset['asset_name']); ?>
                                    <?php if (!empty($asset['serial_number'])): ?>
                                    <br><small class="meta-note muted">S/N: <?= htmlspecialchars($asset['serial_number']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="type-badge type-<?php echo $asset['asset_type']; ?>">
                                        <?php echo strtoupper($asset['asset_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($asset['brand'] ?? 'N/A'); ?></strong><br>
                                    <small class="meta-note"><?php echo htmlspecialchars($asset['model'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($asset['os_name'])): ?>
                                        <small class="meta-note highlight"><?= htmlspecialchars($asset['os_name']) ?></small>
                                        <?php if (!empty($asset['os_version'])): ?>
                                        <br><small class="meta-note"><?= htmlspecialchars($asset['os_version']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <small class="meta-note muted">—</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($asset['ip_address'])): ?>
                                        <code class="meta-code"><?= htmlspecialchars($asset['ip_address']) ?></code>
                                    <?php else: ?>
                                        <small class="meta-note muted">—</small>
                                    <?php endif; ?>
                                    <?php if (!empty($asset['mac_address'])): ?>
                                    <br><small class="meta-note muted"><?= htmlspecialchars($asset['mac_address']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($asset['location'] ?? 'N/A'); ?>
                                    <?php if (!empty($asset['department'])): ?>
                                    <br><small class="meta-note highlight"><?= htmlspecialchars($asset['department']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($asset['assigned_user_name'] ?? 'ไม่ได้มอบหมาย'); ?>
                                    <?php if (!empty($asset['tech_name'])): ?>
                                    <br><small class="meta-note"><i class="fas fa-tools"></i> <?= htmlspecialchars($asset['tech_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($asset['warranty_expiry']): ?>
                                        <?php 
                                        $warr_date = strtotime($asset['warranty_expiry']);
                                        $now = time();
                                        $days_diff = ($warr_date - $now) / 86400;
                                        if ($days_diff < 0) {
                                            echo '<span class="warranty-expired">หมดอายุแล้ว</span>';
                                        } elseif ($days_diff <= 30) {
                                            echo '<span class="warranty-warning">เหลือ ' . ceil($days_diff) . ' วัน</span>';
                                        } else {
                                            echo date('d/m/Y', $warr_date);
                                        }
                                        ?>
                                    <?php else: ?>
                                        <span style="color: #718096;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $asset['status']; ?>">
                                        <?php echo strtoupper($asset['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="assetsdetail.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-green" title="ดูรายละเอียด">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($isAdmin): ?>
                                        <button class="btn btn-edit btn-sm" onclick='editAsset(<?php echo json_encode($asset, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP); ?>)' title="แก้ไข">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-delete btn-sm" onclick="deleteAsset(<?php echo $asset['asset_id']; ?>, '<?php echo htmlspecialchars($asset['asset_name']); ?>')" title="ลบ">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>

                <!-- Grouped by User View -->
                <div id="viewUser" style="display:none;padding:20px;">
                <?php foreach ($assetsByUser as $userName => $userAssets): ?>
                    <div style="margin-bottom:25px;">
                        <div style="background:linear-gradient(135deg,#10ce30,#000);color:white;padding:12px 18px;border-radius:10px;display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <span><i class="fas fa-user" style="margin-right:8px;"></i><strong><?= htmlspecialchars($userName) ?></strong></span>
                            <span style="background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:20px;font-size:0.85em;"><?= count($userAssets) ?> รายการ</span>
                        </div>
                        <table style="margin:0;">
                            <thead>
                                <tr>
                                    <th>Asset Tag</th>
                                    <th>ชื่อสินทรัพย์</th>
                                    <th>ประเภท</th>
                                    <th>Brand/Model</th>
                                    <th>Location</th>
                                    <th>S/N</th>
                                    <th>สถานะ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($userAssets as $ua): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ua['asset_tag']) ?></strong></td>
                                <td><?= htmlspecialchars($ua['asset_name']) ?></td>
                                <td><span class="type-badge type-<?= $ua['asset_type'] ?>"><?= strtoupper($ua['asset_type']) ?></span></td>
                                <td><?= htmlspecialchars($ua['brand']??'N/A') ?><br><small style="color:#718096;"><?= htmlspecialchars($ua['model']??'') ?></small></td>
                                <td><?= htmlspecialchars($ua['location']??'N/A') ?></td>
                                <td><small><?= htmlspecialchars($ua['serial_number']??'N/A') ?></small></td>
                                <td><span class="badge badge-<?= $ua['status'] ?>"><?= strtoupper($ua['status']) ?></span></td>
                                <td>
                                    <a href="assetsdetail.php?id=<?= $ua['asset_id'] ?>" class="btn btn-sm" style="background:linear-gradient(135deg,#10ce30,#38a169);color:white;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Asset Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content" style="max-width:820px;">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> เพิ่มสินทรัพย์ใหม่</h2>
                <button class="close-btn" onclick="closeCreateModal()">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="create">

                <!-- Tab Navigation -->
                <div style="display:flex;gap:4px;background:#f7fafc;padding:6px;border-radius:10px;margin-bottom:20px;flex-wrap:wrap;">
                    <button type="button" onclick="switchModalTab('c','basic')" id="c_tab_basic" class="modal-tab active-tab">
                        <i class="fas fa-info-circle"></i> ข้อมูลทั่วไป
                    </button>
                    <button type="button" onclick="switchModalTab('c','os')" id="c_tab_os" class="modal-tab">
                        <i class="fab fa-windows"></i> OS
                    </button>
                    <button type="button" onclick="switchModalTab('c','hw')" id="c_tab_hw" class="modal-tab">
                        <i class="fas fa-microchip"></i> Hardware
                    </button>
                    <button type="button" onclick="switchModalTab('c','net')" id="c_tab_net" class="modal-tab">
                        <i class="fas fa-network-wired"></i> Network
                    </button>
                    <button type="button" onclick="switchModalTab('c','purchase')" id="c_tab_purchase" class="modal-tab">
                        <i class="fas fa-shopping-cart"></i> การจัดซื้อ
                    </button>
                </div>

                <!-- Tab: Basic -->
                <div id="c_section_basic">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_asset_tag">Asset Tag <span style="color:red;">*</span></label>
                            <input type="text" name="asset_tag" id="create_asset_tag" class="form-control" required placeholder="e.g., IT-DT-001">
                        </div>
                        <div class="form-group">
                            <label for="create_inventory_number">Inventory Number</label>
                            <input type="text" name="inventory_number" id="create_inventory_number" class="form-control" placeholder="�� INV-2024-001">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_asset_name">ชื่ออุปกรณ์ <span style="color:red;">*</span></label>
                            <input type="text" name="asset_name" id="create_asset_name" class="form-control" required placeholder="e.g., pc-romar001.romar.co.th">
                        </div>
                        <div class="form-group">
                            <label for="create_asset_type">ประเภท <span style="color:red;">*</span></label>
                            <select name="asset_type" id="create_asset_type" class="form-control" required>
                                <option value="desktop">Desktop - คอมพิวเตอร์ตั้งโต๊ะ</option>
                                <option value="laptop">Laptop - โน้ตบุ๊ค</option>
                                <option value="monitor">Monitor - จอมอนิเตอร์</option>
                                <option value="server">Server - เซิร์ฟเวอร์</option>
                                <option value="printer">Printer - เครื่องพิมพ์</option>
                                <option value="network">Network Device - อุปกรณ์เครือข่าย</option>
                                <option value="mobile">Mobile - มือถือ</option>
                                <option value="phone">Phone - โทรศัพท์บ้าน</option>
                                <option value="software">Software - ซอฟต์แวร์/โปรแกรม</option>
                                <option value="rack">Rack - แร็ควางอุปกรณ์</option>
                                <option value="enclosure">Enclosure - กล่องอุปกรณ์</option>
                                <option value="pdu">PDU - แหล่งจ่ายไฟ</option>
                                <option value="passive_device">Passive Device - อุปกรณ์พาสซีฟ</option>
                                <option value="cable">Cable - สายเชื่อมต่อ</option>
                                <option value="simcard">Simcard - ซิมการ์ด</option>
                                <option value="ink_cartridge">สแตนหมึก - ตลับหมึก</option>
                                <option value="consumable">วัสดุสิ้นเปลือง - Consumables</option>
                                <option value="addon">อุปกรณ์เพิ่มเติม - Add-on</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_brand">Manufacturer / Brand</label>
                            <input type="text" name="brand" id="create_brand" class="form-control" placeholder="e.g., HP, Dell, Lenovo">
                        </div>
                        <div class="form-group">
                            <label for="create_model">Model</label>
                            <input type="text" name="model" id="create_model" class="form-control" placeholder="e.g., ProDesk 400 G5">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_serial_number">Serial Number</label>
                            <input type="text" name="serial_number" id="create_serial_number" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="create_status">สถานะ <span style="color:red;">*</span></label>
                            <select name="status" id="create_status" class="form-control" required>
                                <option value="active">Active - ใช้งานอยู่</option>
                                <option value="inactive">Inactive - ไม่ได้ใช้งาน</option>
                                <option value="maintenance">Maintenance - ซ่อมบำรุง</option>
                                <option value="retired">Retired - เลิกใช้งาน</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_location">Location (ห้อง/สถานที่)</label>
                            <input type="text" name="location" id="create_location" class="form-control" placeholder="�� ��ͧ IT, ��� 2 �Ҥ���ӹѡ�ҹ�˭�">
                        </div>
                        <div class="form-group">
                            <label for="create_department">แผนก/ฝ่าย</label>
                            <input type="text" name="department" id="create_department" class="form-control" placeholder="e.g., IT, HR, Production">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_assigned_to">ผู้รับผิดชอบ (Assigned To / User)</label>
                            <select name="assigned_to" id="create_assigned_to" class="form-control">
                                <option value="">ไม่ได้มอบหมาย</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create_tech_in_charge">ช่างเทคนิค (Technician in Charge)</label>
                            <select name="tech_in_charge" id="create_tech_in_charge" class="form-control">
                                <option value="">— เลือกช่าง —</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_alternate_user">Alternate Username</label>
                            <input type="text" name="alternate_user" id="create_alternate_user" class="form-control" placeholder="�кت��ͼ����ҹ���ͧ (�����)">
                        </div>
                        <div class="form-group">
                            <label for="create_asset_group">กลุ่ม/ทีม</label>
                            <input type="text" name="asset_group" id="create_asset_group" class="form-control" placeholder="e.g., IT Team, Admin">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_condition">สภาพอุปกรณ์</label>
                            <select name="condition" id="create_condition" class="form-control">
                                <option value="good">Good - ดี</option>
                                <option value="fair">Fair - พอใช้</option>
                                <option value="poor">Poor - แย่</option>
                                <option value="damaged">Damaged - เสียหาย</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create_last_inventory_date">วันที่ Inventory ล่าสุด</label>
                            <input type="date" name="last_inventory_date" id="create_last_inventory_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="create_notes">Comments / หมายเหตุ</label>
                        <textarea name="notes" id="create_notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
                    </div>
                </div>

                <!-- Tab: OS -->
                <div id="c_section_os" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_os_name">Operating System</label>
                            <select name="os_name" id="create_os_name" class="form-control">
                                <option value="">— เลือก OS —</option>
                                <option>Windows 11 Pro</option>
                                <option>Windows 11 Enterprise</option>
                                <option>Windows 10 Pro</option>
                                <option>Windows 10 Enterprise</option>
                                <option>Windows Server 2022</option>
                                <option>Windows Server 2019</option>
                                <option>Windows Server 2016</option>
                                <option>Ubuntu 22.04</option>
                                <option>Ubuntu 20.04</option>
                                <option>CentOS 7</option>
                                <option>macOS Ventura</option>
                                <option>macOS Sonoma</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create_os_version">OS Version</label>
                            <input type="text" name="os_version" id="create_os_version" class="form-control" placeholder="e.g., 22H2, 21H2">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_os_architecture">Architecture</label>
                            <select name="os_architecture" id="create_os_architecture" class="form-control">
                                <option value="">—</option>
                                <option value="64-bit">64-bit</option>
                                <option value="32-bit">32-bit</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create_os_service_pack">Service Pack / Update</label>
                            <input type="text" name="os_service_pack" id="create_os_service_pack" class="form-control" placeholder="e.g., SP1, 23H2">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="create_os_product_key">OS Product Key</label>
                        <input type="text" name="os_product_key" id="create_os_product_key" class="form-control" placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX">
                    </div>
                </div>

                <!-- Tab: Hardware -->
                <div id="c_section_hw" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_cpu">CPU</label>
                            <input type="text" name="cpu" id="create_cpu" class="form-control" placeholder="e.g., Intel Core i5-10500">
                        </div>
                        <div class="form-group">
                            <label for="create_cpu_cores">CPU Cores</label>
                            <input type="number" name="cpu_cores" id="create_cpu_cores" class="form-control" placeholder="6" min="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_ram_gb">RAM (GB)</label>
                            <input type="number" name="ram_gb" id="create_ram_gb" class="form-control" placeholder="8" min="1">
                        </div>
                        <div class="form-group">
                            <label for="create_storage">Storage</label>
                            <input type="text" name="storage" id="create_storage" class="form-control" placeholder="e.g., 256GB SSD, 1TB HDD">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_gpu">GPU / Graphics Card</label>
                            <input type="text" name="gpu" id="create_gpu" class="form-control" placeholder="e.g., Intel UHD 630">
                        </div>
                        <div class="form-group">
                            <label for="create_monitor">Monitor</label>
                            <input type="text" name="monitor" id="create_monitor" class="form-control" placeholder="e.g., HP 22fw 21.5 inch">
                        </div>
                    </div>
                </div>

                <!-- Tab: Network -->
                <div id="c_section_net" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_ip_address">IP Address</label>
                            <input type="text" name="ip_address" id="create_ip_address" class="form-control" placeholder="e.g., 192.168.1.100">
                        </div>
                        <div class="form-group">
                            <label for="create_mac_address">MAC Address</label>
                            <input type="text" name="mac_address" id="create_mac_address" class="form-control" placeholder="e.g., AA:BB:CC:DD:EE:FF">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_network_domain">Network / Domain</label>
                            <input type="text" name="network_domain" id="create_network_domain" class="form-control" placeholder="e.g., romar.co.th">
                        </div>
                        <div class="form-group">
                            <label for="create_gateway">Gateway</label>
                            <input type="text" name="gateway" id="create_gateway" class="form-control" placeholder="e.g., 192.168.1.1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="create_dns_server">DNS Server</label>
                        <input type="text" name="dns_server" id="create_dns_server" class="form-control" placeholder="e.g., 8.8.8.8, 8.8.4.4">
                    </div>
                </div>

                <!-- Tab: Purchase -->
                <div id="c_section_purchase" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_purchase_date">Purchase Date (วันที่ซื้อ)</label>
                            <input type="date" name="purchase_date" id="create_purchase_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="create_warranty_expiry">Warranty Expiry (วันหมดประกัน)</label>
                            <input type="date" name="warranty_expiry" id="create_warranty_expiry" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_purchase_price">ราคาซื้อ (บาท)</label>
                            <input type="number" name="purchase_price" id="create_purchase_price" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="create_salvage_value">มูลค่าซาก (บาท)</label>
                            <input type="number" name="salvage_value" id="create_salvage_value" class="form-control" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_useful_life_years">อายุการใช้งาน (ปี)</label>
                            <input type="number" name="useful_life_years" id="create_useful_life_years" class="form-control" value="5" min="1" max="30">
                        </div>
                        <div class="form-group">
                            <label for="create_supplier">ผู้จัดจำหน่าย (Supplier)</label>
                            <input type="text" name="supplier" id="create_supplier" class="form-control" placeholder="�� ����ѷ�Ѵ��˹��� ���ͼ���Ѻ����">
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;border-top:1px solid #e2e8f0;padding-top:15px;">
                    <button type="button" class="btn" onclick="closeCreateModal()" style="background:#e2e8f0;">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึกสินทรัพย์</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Asset Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width:820px;">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> แก้ไขสินทรัพย์</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="asset_id" id="edit_asset_id">

                <!-- Tab Navigation -->
                <div style="display:flex;gap:4px;background:#f7fafc;padding:6px;border-radius:10px;margin-bottom:20px;flex-wrap:wrap;">
                    <button type="button" onclick="switchModalTab('e','basic')" id="e_tab_basic" class="modal-tab active-tab">
                        <i class="fas fa-info-circle"></i> ข้อมูลทั่วไป
                    </button>
                    <button type="button" onclick="switchModalTab('e','os')" id="e_tab_os" class="modal-tab">
                        <i class="fab fa-windows"></i> OS
                    </button>
                    <button type="button" onclick="switchModalTab('e','hw')" id="e_tab_hw" class="modal-tab">
                        <i class="fas fa-microchip"></i> Hardware
                    </button>
                    <button type="button" onclick="switchModalTab('e','net')" id="e_tab_net" class="modal-tab">
                        <i class="fas fa-network-wired"></i> Network
                    </button>
                    <button type="button" onclick="switchModalTab('e','purchase')" id="e_tab_purchase" class="modal-tab">
                        <i class="fas fa-shopping-cart"></i> การจัดซื้อ
                    </button>
                </div>

                <!-- Tab: Basic -->
                <div id="e_section_basic">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_asset_tag">Asset Tag <span style="color:red;">*</span></label>
                            <input type="text" name="asset_tag" id="edit_asset_tag" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_inventory_number">Inventory Number</label>
                            <input type="text" name="inventory_number" id="edit_inventory_number" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_asset_name">ชื่ออุปกรณ์ <span style="color:red;">*</span></label>
                            <input type="text" name="asset_name" id="edit_asset_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_asset_type">ประเภท <span style="color:red;">*</span></label>
                            <select name="asset_type" id="edit_asset_type" class="form-control" required>
                                <option value="desktop">Desktop - คอมพิวเตอร์ตั้งโต๊ะ</option>
                                <option value="laptop">Laptop - โน้ตบุ๊ค</option>
                                <option value="monitor">Monitor - จอมอนิเตอร์</option>
                                <option value="server">Server - เซิร์ฟเวอร์</option>
                                <option value="printer">Printer - เครื่องพิมพ์</option>
                                <option value="network">Network Device - อุปกรณ์เครือข่าย</option>
                                <option value="mobile">Mobile - มือถือ</option>
                                <option value="phone">Phone - โทรศัพท์บ้าน</option>
                                <option value="software">Software - ซอฟต์แวร์/โปรแกรม</option>
                                <option value="rack">Rack - แร็ควางอุปกรณ์</option>
                                <option value="enclosure">Enclosure - กล่องอุปกรณ์</option>
                                <option value="pdu">PDU - แหล่งจ่ายไฟ</option>
                                <option value="passive_device">Passive Device - อุปกรณ์พาสซีฟ</option>
                                <option value="cable">Cable - สายเชื่อมต่อ</option>
                                <option value="simcard">Simcard - ซิมการ์ด</option>
                                <option value="ink_cartridge">สแตนหมึก - ตลับหมึก</option>
                                <option value="consumable">วัสดุสิ้นเปลือง - Consumables</option>
                                <option value="addon">อุปกรณ์เพิ่มเติม - Add-on</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_brand">Manufacturer / Brand</label>
                            <input type="text" name="brand" id="edit_brand" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_model">Model</label>
                            <input type="text" name="model" id="edit_model" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_serial_number">Serial Number</label>
                            <input type="text" name="serial_number" id="edit_serial_number" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_status">สถานะ <span style="color:red;">*</span></label>
                            <select name="status" id="edit_status" class="form-control" required>
                                <option value="active">Active - ใช้งานอยู่</option>
                                <option value="inactive">Inactive - ไม่ได้ใช้งาน</option>
                                <option value="maintenance">Maintenance - ซ่อมบำรุง</option>
                                <option value="retired">Retired - เลิกใช้งาน</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_location">Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_department">แผนก/ฝ่าย</label>
                            <input type="text" name="department" id="edit_department" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_assigned_to">ผู้รับผิดชอบ (User)</label>
                            <select name="assigned_to" id="edit_assigned_to" class="form-control">
                                <option value="">ไม่ได้มอบหมาย</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_tech_in_charge">ช่างเทคนิค (Technician)</label>
                            <select name="tech_in_charge" id="edit_tech_in_charge" class="form-control">
                                <option value="">— เลือกช่าง —</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_alternate_user">Alternate Username</label>
                            <input type="text" name="alternate_user" id="edit_alternate_user" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_asset_group">กลุ่ม/ทีม</label>
                            <input type="text" name="asset_group" id="edit_asset_group" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_condition">สภาพอุปกรณ์</label>
                            <select name="condition" id="edit_condition" class="form-control">
                                <option value="good">Good - ดี</option>
                                <option value="fair">Fair - พอใช้</option>
                                <option value="poor">Poor - แย่</option>
                                <option value="damaged">Damaged - เสียหาย</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_last_inventory_date">วันที่ Inventory ล่าสุด</label>
                            <input type="date" name="last_inventory_date" id="edit_last_inventory_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_notes">Comments / หมายเหตุ</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <!-- Tab: OS -->
                <div id="e_section_os" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_os_name">Operating System</label>
                            <select name="os_name" id="edit_os_name" class="form-control">
                                <option value="">— เลือก OS —</option>
                                <option>Windows 11 Pro</option>
                                <option>Windows 11 Enterprise</option>
                                <option>Windows 10 Pro</option>
                                <option>Windows 10 Enterprise</option>
                                <option>Windows Server 2022</option>
                                <option>Windows Server 2019</option>
                                <option>Windows Server 2016</option>
                                <option>Ubuntu 22.04</option>
                                <option>Ubuntu 20.04</option>
                                <option>CentOS 7</option>
                                <option>macOS Ventura</option>
                                <option>macOS Sonoma</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_os_version">OS Version</label>
                            <input type="text" name="os_version" id="edit_os_version" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_os_architecture">Architecture</label>
                            <select name="os_architecture" id="edit_os_architecture" class="form-control">
                                <option value="">—</option>
                                <option value="64-bit">64-bit</option>
                                <option value="32-bit">32-bit</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_os_service_pack">Service Pack / Update</label>
                            <input type="text" name="os_service_pack" id="edit_os_service_pack" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_os_product_key">OS Product Key</label>
                        <input type="text" name="os_product_key" id="edit_os_product_key" class="form-control">
                    </div>
                </div>

                <!-- Tab: Hardware -->
                <div id="e_section_hw" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_cpu">CPU</label>
                            <input type="text" name="cpu" id="edit_cpu" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_cpu_cores">CPU Cores</label>
                            <input type="number" name="cpu_cores" id="edit_cpu_cores" class="form-control" min="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_ram_gb">RAM (GB)</label>
                            <input type="number" name="ram_gb" id="edit_ram_gb" class="form-control" min="1">
                        </div>
                        <div class="form-group">
                            <label for="edit_storage">Storage</label>
                            <input type="text" name="storage" id="edit_storage" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_gpu">GPU / Graphics Card</label>
                            <input type="text" name="gpu" id="edit_gpu" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_monitor">Monitor</label>
                            <input type="text" name="monitor" id="edit_monitor" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Tab: Network -->
                <div id="e_section_net" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_ip_address">IP Address</label>
                            <input type="text" name="ip_address" id="edit_ip_address" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_mac_address">MAC Address</label>
                            <input type="text" name="mac_address" id="edit_mac_address" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_network_domain">Network / Domain</label>
                            <input type="text" name="network_domain" id="edit_network_domain" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_gateway">Gateway</label>
                            <input type="text" name="gateway" id="edit_gateway" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_dns_server">DNS Server</label>
                        <input type="text" name="dns_server" id="edit_dns_server" class="form-control">
                    </div>
                </div>

                <!-- Tab: Purchase -->
                <div id="e_section_purchase" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_purchase_date">Purchase Date</label>
                            <input type="date" name="purchase_date" id="edit_purchase_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_warranty_expiry">Warranty Expiry</label>
                            <input type="date" name="warranty_expiry" id="edit_warranty_expiry" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_purchase_price">ราคาซื้อ (บาท)</label>
                            <input type="number" name="purchase_price" id="edit_purchase_price" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="edit_salvage_value">มูลค่าซาก (บาท)</label>
                            <input type="number" name="salvage_value" id="edit_salvage_value" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_useful_life">อายุการใช้งาน (ปี)</label>
                            <input type="number" name="useful_life_years" id="edit_useful_life" class="form-control" min="1" max="30">
                        </div>
                        <div class="form-group">
                            <label for="edit_supplier">ผู้จัดจำหน่าย (Supplier)</label>
                            <input type="text" name="supplier" id="edit_supplier" class="form-control">
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;border-top:1px solid #e2e8f0;padding-top:15px;">
                    <button type="button" class="btn" onclick="closeEditModal()" style="background:#e2e8f0;">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="asset_id" id="delete_asset_id">
    </form>

<?php ob_start(); ?>
    <script>

        function openCreateModal() {
            switchModalTab('c','basic');
            document.getElementById('createModal').classList.add('show');
        }
        function closeCreateModal() { document.getElementById('createModal').classList.remove('show'); }
        function closeEditModal()   { document.getElementById('editModal').classList.remove('show'); }

        // Switch tabs inside create (c) or edit (e) modal
        function switchModalTab(prefix, tab) {
            const sections = ['basic','os','hw','net','purchase'];
            sections.forEach(s => {
                const el = document.getElementById(prefix + '_section_' + s);
                const btn = document.getElementById(prefix + '_tab_' + s);
                if (el) el.style.display = (s === tab) ? '' : 'none';
                if (btn) btn.classList.toggle('active-tab', s === tab);
            });
        }

        function editAsset(asset) {
            // Reset to basic tab
            switchModalTab('e','basic');

            // Basic
            document.getElementById('edit_asset_id').value           = asset.asset_id;
            document.getElementById('edit_asset_tag').value          = asset.asset_tag || '';
            document.getElementById('edit_inventory_number').value   = asset.inventory_number || '';
            document.getElementById('edit_asset_name').value         = asset.asset_name || '';
            document.getElementById('edit_asset_type').value         = asset.asset_type || '';
            document.getElementById('edit_brand').value              = asset.brand || '';
            document.getElementById('edit_model').value              = asset.model || '';
            document.getElementById('edit_serial_number').value      = asset.serial_number || '';
            document.getElementById('edit_status').value             = asset.status || 'active';
            document.getElementById('edit_location').value           = asset.location || '';
            document.getElementById('edit_department').value         = asset.department || '';
            document.getElementById('edit_assigned_to').value        = asset.assigned_to || '';
            document.getElementById('edit_tech_in_charge').value     = asset.tech_in_charge || '';
            document.getElementById('edit_alternate_user').value     = asset.alternate_user || '';
            document.getElementById('edit_asset_group').value        = asset.asset_group || '';
            document.getElementById('edit_condition').value          = asset.condition_status || 'good';
            document.getElementById('edit_last_inventory_date').value= asset.last_inventory_date || '';
            document.getElementById('edit_notes').value              = asset.notes || '';
            // OS
            document.getElementById('edit_os_name').value            = asset.os_name || '';
            document.getElementById('edit_os_version').value         = asset.os_version || '';
            document.getElementById('edit_os_architecture').value    = asset.os_architecture || '';
            document.getElementById('edit_os_service_pack').value    = asset.os_service_pack || '';
            document.getElementById('edit_os_product_key').value     = asset.os_product_key || '';
            // Hardware
            document.getElementById('edit_cpu').value                = asset.cpu || '';
            document.getElementById('edit_cpu_cores').value          = asset.cpu_cores || '';
            document.getElementById('edit_ram_gb').value             = asset.ram_gb || '';
            document.getElementById('edit_storage').value            = asset.storage || '';
            document.getElementById('edit_gpu').value                = asset.gpu || '';
            document.getElementById('edit_monitor').value            = asset.monitor || '';
            // Network
            document.getElementById('edit_ip_address').value         = asset.ip_address || '';
            document.getElementById('edit_mac_address').value        = asset.mac_address || '';
            document.getElementById('edit_network_domain').value     = asset.network_domain || '';
            document.getElementById('edit_gateway').value            = asset.gateway || '';
            document.getElementById('edit_dns_server').value         = asset.dns_server || '';
            // Purchase
            document.getElementById('edit_purchase_date').value      = asset.purchase_date || '';
            document.getElementById('edit_warranty_expiry').value    = asset.warranty_expiry || '';
            document.getElementById('edit_purchase_price').value     = asset.purchase_price || '';
            document.getElementById('edit_salvage_value').value      = asset.salvage_value || 0;
            document.getElementById('edit_useful_life').value        = asset.useful_life_years || 5;
            document.getElementById('edit_supplier').value           = asset.supplier || '';

            document.getElementById('editModal').classList.add('show');
        }

        function deleteAsset(assetId, name) {
            if (confirm('ต้องการลบสินทรัพย์ "' + name + '" ใช่หรือไม่?')) {
                document.getElementById('delete_asset_id').value = assetId;
                document.getElementById('deleteForm').submit();
            }
        }

        function switchView(mode) {
            const tableDiv = document.getElementById('viewTable');
            const userDiv  = document.getElementById('viewUser');
            const btnTable = document.getElementById('btnTableView');
            const btnUser  = document.getElementById('btnUserView');
            if (mode === 'table') {
                tableDiv.style.display = 'block'; userDiv.style.display = 'none';
                btnTable.style.background = 'linear-gradient(180deg, #10ce30 0%, #000000)';
                btnTable.style.color = 'white';
                btnUser.style.background = '#e2e8f0'; btnUser.style.color = '#4a5568';
            } else {
                tableDiv.style.display = 'none'; userDiv.style.display = 'block';
                btnUser.style.background = 'linear-gradient(180deg, #10ce30 0%, #000000)';
                btnUser.style.color = 'white';
                btnTable.style.background = '#e2e8f0'; btnTable.style.color = '#4a5568';
            }
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) event.target.classList.remove('show');
        }

        function toggleWarrantyAlert() {
            const body = document.getElementById('warrantyAlertBody');
            const icon = document.getElementById('warrantyToggleIcon');
            if (body.style.display === 'none') {
                body.style.display = 'block';
                icon.innerHTML = '<i class="fas fa-chevron-up"></i> ซ่อน';
            } else {
                body.style.display = 'none';
                icon.innerHTML = '<i class="fas fa-chevron-down"></i> ดูรายละเอียด';
            }
        }
    </script>
<?php $pageScripts = ob_get_clean(); ?>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>














