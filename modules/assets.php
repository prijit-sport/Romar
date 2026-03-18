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

// โ”€โ”€ Flash message (PRG pattern) โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
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
    // โ”€โ”€ Basic โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
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
    // โ”€โ”€ Personnel โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
    $assigned_to       = !empty($_POST['assigned_to'])      ? (int)$_POST['assigned_to']      : null;
    $tech_in_charge    = !empty($_POST['tech_in_charge'])   ? (int)$_POST['tech_in_charge']   : null;
    $alternate_user    = sanitize($_POST['alternate_user'] ?? '');
    // โ”€โ”€ Purchase โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
    $purchase_date     = !empty($_POST['purchase_date'])    ? $_POST['purchase_date']    : null;
    $warranty_expiry   = !empty($_POST['warranty_expiry'])  ? $_POST['warranty_expiry']  : null;
    $purchase_price    = !empty($_POST['purchase_price'])   ? (float)$_POST['purchase_price']  : null;
    $salvage_value     = !empty($_POST['salvage_value'])    ? (float)$_POST['salvage_value']    : 0;
    $useful_life_years = !empty($_POST['useful_life_years'])? (int)$_POST['useful_life_years']  : 5;
    $supplier          = sanitize($_POST['supplier'] ?? '');
    $last_inventory_date = !empty($_POST['last_inventory_date']) ? $_POST['last_inventory_date'] : null;
    // โ”€โ”€ OS โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
    $os_name           = sanitize($_POST['os_name'] ?? '');
    $os_version        = sanitize($_POST['os_version'] ?? '');
    $os_architecture   = sanitize($_POST['os_architecture'] ?? '');
    $os_service_pack   = sanitize($_POST['os_service_pack'] ?? '');
    $os_product_key    = sanitize($_POST['os_product_key'] ?? '');
    // โ”€โ”€ Network โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
    $ip_address        = sanitize($_POST['ip_address'] ?? '');
    $mac_address       = sanitize($_POST['mac_address'] ?? '');
    $network_domain    = sanitize($_POST['network_domain'] ?? '');
    $gateway           = sanitize($_POST['gateway'] ?? '');
    $dns_server        = sanitize($_POST['dns_server'] ?? '');
    // โ”€โ”€ Hardware โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
    $cpu               = sanitize($_POST['cpu'] ?? '');
    $cpu_cores         = !empty($_POST['cpu_cores']) ? (int)$_POST['cpu_cores'] : null;
    $ram_gb            = !empty($_POST['ram_gb'])    ? (int)$_POST['ram_gb']    : null;
    $storage           = sanitize($_POST['storage'] ?? '');
    $gpu               = sanitize($_POST['gpu'] ?? '');
    $monitor           = sanitize($_POST['monitor'] ?? '');

    // Build INSERT values
    $assignedSQL     = $assigned_to     ? $assigned_to     : null;
    $techSQL         = $tech_in_charge  ? $tech_in_charge  : null;

    // โ”€โ”€ เธ•เธฃเธงเธเธชเธญเธ asset_tag เธเนเธณเธเนเธญเธ INSERT โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
    $chk = $db->prepare("SELECT asset_id FROM assets WHERE asset_tag = ?");
    $chk->bind_param('s', $asset_tag);
    $chk->execute();
    $dupRes = $chk->get_result();
    if ($dupRes && $dupRes->num_rows > 0) {
        $_SESSION['flash_message'] = "Asset Tag \"" . htmlspecialchars($asset_tag) . "\" เธกเธตเนเธเธฃเธฐเธเธเนเธฅเนเธง เธเธฃเธธเธ“เธฒเนเธเนเธฃเธซเธฑเธชเธชเธดเธเธ—เธฃเธฑเธเธขเนเนเธซเธกเน";
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
        logActivity($_SESSION['user_id'], 'เน€เธเธดเนเธกเธชเธดเธเธ—เธฃเธฑเธเธขเน', 'Assets', "เน€เธเธดเนเธก: $asset_name ($asset_tag)");
        $_SESSION['flash_message'] = 'เน€เธเธดเนเธกเธชเธดเธเธ—เธฃเธฑเธเธขเนเธชเธณเน€เธฃเนเธ!';
        $_SESSION['flash_type']    = 'success';
    } else {
        $_SESSION['flash_message'] = 'เน€เธเธดเธ”เธเนเธญเธเธดเธ”เธเธฅเธฒเธ”: ' . $stmt->error;
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
        logActivity($_SESSION['user_id'], 'เธญเธฑเธเน€เธ”เธ•เธชเธดเธเธ—เธฃเธฑเธเธขเน', 'Assets', "เธญเธฑเธเน€เธ”เธ•: $asset_name");
        $_SESSION['flash_message'] = 'เธญเธฑเธเน€เธ”เธ•เธชเธดเธเธ—เธฃเธฑเธเธขเนเธชเธณเน€เธฃเนเธ!';
        $_SESSION['flash_type']    = 'success';
    } else {
        $_SESSION['flash_message'] = 'เน€เธเธดเธ”เธเนเธญเธเธดเธ”เธเธฅเธฒเธ”: ' . $stmt->error;
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
        logActivity($_SESSION['user_id'], 'เธฅเธเธชเธดเธเธ—เธฃเธฑเธเธขเน', 'Assets', "เธฅเธ Asset ID: $asset_id");
        $_SESSION['flash_message'] = 'เธฅเธเธชเธดเธเธ—เธฃเธฑเธเธขเนเธชเธณเน€เธฃเนเธ!';
        $_SESSION['flash_type']    = 'success';
    } else {
        $_SESSION['flash_message'] = 'เน€เธเธดเธ”เธเนเธญเธเธดเธ”เธเธฅเธฒเธ”: ' . $stmt->error;
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
    $key = $a['assigned_to'] ? $a['assigned_user_name'] : 'โ€” เนเธกเนเนเธ”เนเธกเธญเธเธซเธกเธฒเธข โ€”';
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

// โ”€โ”€ Export Excel โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
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
        <th>Asset Tag</th><th>Inventory No.</th><th>เธเธทเนเธญเธญเธธเธเธเธฃเธ“เน</th><th>เธเธฃเธฐเน€เธ เธ—</th>
        <th>Brand</th><th>Model</th><th>Serial Number</th><th>เธชเธ–เธฒเธเธฐ</th>
        <th>Location</th><th>เนเธเธเธ</th><th>เธเธนเนเธฃเธฑเธเธเธดเธ”เธเธญเธ</th>
        <th>IP Address</th><th>MAC Address</th><th>OS</th>
        <th>Warranty Expiry</th><th>เธฃเธฒเธเธฒเธเธทเนเธญ (เธฟ)</th><th>เธซเธกเธฒเธขเน€เธซเธ•เธธ</th>
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
                        <span class="asset-count-badge"><?= count($assets) ?> assets</span>
                    </h1>
                    <p class="page-subtitle">Inventory overview for <?= $currentCat['label'] ?> assets</p>
                </div>
                <div class="page-actions">
                    <button type="button" id="categoryToggle" class="btn btn-secondary category-toggle-btn" title="Asset categories" aria-controls="categoryPanel" aria-expanded="false">
                        <i class="fas fa-layer-group" aria-hidden="true"></i>
                        <span><?php echo ui_text('nav.assets_categories'); ?></span>
                    </button>
                    <?php if ($isAdmin): ?>
                    <button class="btn btn-primary" onclick="openCreateModal()">
                        <i class="fas fa-plus"></i>
                        Add <?= $cat !== 'all' ? strtolower($currentCat['label']) : 'asset' ?>
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
                        <p>Total assets</p>
                    </div>
                </div>

                <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                    <i class="fas fa-check-circle"></i>
                </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['active_count'] ?? 0); ?></h3>
                        <p>Active assets</p>
                    </div>
                </div>

                <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                    <i class="fas fa-tools"></i>
                </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['maintenance_count'] ?? 0); ?></h3>
                        <p>Under maintenance</p>
                    </div>
                </div>

                <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['warranty_expired_count'] ?? 0); ?></h3>
                        <p>Warranty expired</p>
                    </div>
                </div>

                <?php if (($stats['warranty_expiring_count'] ?? 0) > 0): ?>
                <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ecc94b, #d69e2e);">
                    <i class="fas fa-clock"></i>
                </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['warranty_expiring_count']); ?></h3>
                        <p>Warranties expiring within 30 days</p>
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
                        ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝอน๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝับ๏ฟฝ๏ฟฝะกัน (<?= count($warrantyAlerts) ?> ๏ฟฝ๏ฟฝยก๏ฟฝ๏ฟฝ ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ 90 ๏ฟฝัน)
                    </div>
                    <span id="warrantyToggleIcon" class="warranty-toggle-icon">
                        <i class="fas fa-chevron-down"></i> ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝยด
                    </span>
                </div>
                <div id="warrantyAlertBody" style="display:none;">
                    <table class="warranty-alert-table">
                        <thead>
                            <tr>
                                <th>Asset Tag</th><th>เธเธทเนเธญเธญเธธเธเธเธฃเธ“เน</th><th>เธเธฃเธฐเน€เธ เธ—</th>
                                <th>เธเธนเนเธฃเธฑเธเธเธดเธ”เธเธญเธ</th><th>Location</th>
                                <th>เธงเธฑเธเธซเธกเธ”เธเธฃเธฐเธเธฑเธ</th><th>เน€เธซเธฅเธทเธญ</th>
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
                                <td><?= htmlspecialchars($wa['assigned_user_name'] ?? 'โ€”') ?></td>
                                <td><?= htmlspecialchars($wa['location'] ?? 'โ€”') ?></td>
                                <td><?= date('d/m/Y', strtotime($wa['warranty_expiry'])) ?></td>
                                <td><span class="days-badge <?= $bc ?>"><?= $d ?> เธงเธฑเธ</span></td>
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
                                   placeholder="๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ Asset Tag, ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝลข, ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ, IP ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ Serial">
                        </div>
                        <select name="status" onchange="this.form.submit()" class="form-control form-select-compact">
                            <option value="">เธ—เธธเธเธชเธ–เธฒเธเธฐ</option>
                            <option value="active"      <?= $status==='active'     ?'selected':'' ?>>Active</option>
                            <option value="inactive"    <?= $status==='inactive'   ?'selected':'' ?>>Inactive</option>
                            <option value="maintenance" <?= $status==='maintenance'?'selected':'' ?>>Maintenance</option>
                            <option value="retired"     <?= $status==='retired'    ?'selected':'' ?>>Retired</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-search"></i> เธเนเธเธซเธฒ
                        </button>
                    </form>
                    <div class="toolbar-actions">
                        <button onclick="switchView('table')" id="btnTableView" class="btn btn-primary btn-sm view-toggle active">
                            <i class="fas fa-list"></i> เธฃเธฒเธขเธเธฒเธฃ
                        </button>
                        <button onclick="switchView('user')" id="btnUserView" class="btn btn-sm view-toggle">
                            <i class="fas fa-users"></i> เนเธขเธเธ•เธฒเธกเธเธนเนเธฃเธฑเธเธเธดเธ”เธเธญเธ
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
                            <th>เธเธทเนเธญเธญเธธเธเธเธฃเธ“เน</th>
                            <th>เธเธฃเธฐเน€เธ เธ—</th>
                            <th>Brand / Model</th>
                            <th>OS</th>
                            <th>IP Address</th>
                            <th>Location / เนเธเธเธ</th>
                            <th>เธเธนเนเธฃเธฑเธเธเธดเธ”เธเธญเธ</th>
                            <th>Warranty</th>
                            <th>เธชเธ–เธฒเธเธฐ</th>
                            <th>เธเธฒเธฃเธเธฃเธฐเธ—เธณ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assets)): ?>
                        <tr>
                            <td colspan="11" class="table-empty">
                                <i class="fas fa-box"></i>
                                เนเธกเนเธเธเธเนเธญเธกเธนเธฅเธชเธดเธเธ—เธฃเธฑเธเธขเน
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
                                        <small class="meta-note muted">โ€”</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($asset['ip_address'])): ?>
                                        <code class="meta-code"><?= htmlspecialchars($asset['ip_address']) ?></code>
                                    <?php else: ?>
                                        <small class="meta-note muted">โ€”</small>
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
                                    <?php echo htmlspecialchars($asset['assigned_user_name'] ?? 'เนเธกเนเนเธ”เนเธกเธญเธเธซเธกเธฒเธข'); ?>
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
                                            echo '<span class="warranty-expired">เธซเธกเธ”เธญเธฒเธขเธธเนเธฅเนเธง</span>';
                                        } elseif ($days_diff <= 30) {
                                            echo '<span class="warranty-warning">เน€เธซเธฅเธทเธญ ' . ceil($days_diff) . ' เธงเธฑเธ</span>';
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
                                        <a href="assetsdetail.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-green" title="เธ”เธนเธฃเธฒเธขเธฅเธฐเน€เธญเธตเธขเธ”">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($isAdmin): ?>
                                        <button class="btn btn-edit btn-sm" onclick='editAsset(<?php echo json_encode($asset, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP); ?>)' title="เนเธเนเนเธ">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-delete btn-sm" onclick="deleteAsset(<?php echo $asset['asset_id']; ?>, '<?php echo htmlspecialchars($asset['asset_name']); ?>')" title="เธฅเธ">
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
                            <span style="background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:20px;font-size:0.85em;"><?= count($userAssets) ?> เธฃเธฒเธขเธเธฒเธฃ</span>
                        </div>
                        <table style="margin:0;">
                            <thead>
                                <tr>
                                    <th>Asset Tag</th>
                                    <th>เธเธทเนเธญเธชเธดเธเธ—เธฃเธฑเธเธขเน</th>
                                    <th>เธเธฃเธฐเน€เธ เธ—</th>
                                    <th>Brand/Model</th>
                                    <th>Location</th>
                                    <th>S/N</th>
                                    <th>เธชเธ–เธฒเธเธฐ</th>
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

            <!-- Category Side Panel -->
            <div id="categoryBackdrop" class="category-backdrop" aria-hidden="true"></div>
            <aside id="categoryPanel" class="category-panel" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="categoryPanelTitle">
                <header class="category-panel-header">
                    <div>
                        <h3 id="categoryPanelTitle">
                            <i class="fas fa-layer-group" aria-hidden="true"></i>
                            Asset categories
                        </h3>
                        <p class="category-panel-help">Filter your assets by category for faster navigation.</p>
                    </div>
                    <button type="button" class="category-panel-close" aria-label="Close category panel">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </header>
                <div class="category-panel-body">
                    <div class="category-grid">
                        <?php foreach ($ASSET_CATEGORIES as $key => $category): ?>
                        <a href="#" class="category-card <?= ($activeAssetCategory === $key) ? 'active' : '' ?> category-link" data-cat="<?= htmlspecialchars($key) ?>" <?php echo ($activeAssetCategory === $key) ? 'aria-current="true"' : ''; ?>>
                            <div class="category-icon" style="color: <?= htmlspecialchars($category['color']) ?>">
                                <i class="fas <?= htmlspecialchars($category['icon']) ?>"></i>
                            </div>
                            <div class="category-label"><?= htmlspecialchars($category['label']) ?></div>
                            <?php if (isset($catCounts[$key])): ?>
                            <div class="category-count"><?= number_format($catCounts[$key]) ?> assets</div>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="category-panel-footer">
                        <a href="assets.php?cat=all" class="btn btn-sm btn-secondary category-panel-footer-link">
                            <i class="fas fa-box" aria-hidden="true"></i>
                            Show all assets
                        </a>
                    </div>
                </div>
            </aside>
        </div>
    </div>
    <!-- Create Asset Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content" style="max-width:820px;">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> เน€เธเธดเนเธกเธชเธดเธเธ—เธฃเธฑเธเธขเนเนเธซเธกเน</h2>
                <button class="close-btn" onclick="closeCreateModal()">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="create">

                <!-- Tab Navigation -->
                <div style="display:flex;gap:4px;background:#f7fafc;padding:6px;border-radius:10px;margin-bottom:20px;flex-wrap:wrap;">
                    <button type="button" onclick="switchModalTab('c','basic')" id="c_tab_basic" class="modal-tab active-tab">
                        <i class="fas fa-info-circle"></i> เธเนเธญเธกเธนเธฅเธ—เธฑเนเธงเนเธ
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
                        <i class="fas fa-shopping-cart"></i> เธเธฒเธฃเธเธฑเธ”เธเธทเนเธญ
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
                            <input type="text" name="inventory_number" id="create_inventory_number" class="form-control" placeholder="๏ฟฝ๏ฟฝ INV-2024-001">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_asset_name">เธเธทเนเธญเธญเธธเธเธเธฃเธ“เน <span style="color:red;">*</span></label>
                            <input type="text" name="asset_name" id="create_asset_name" class="form-control" required placeholder="e.g., pc-romar001.romar.co.th">
                        </div>
                        <div class="form-group">
                            <label for="create_asset_type">เธเธฃเธฐเน€เธ เธ— <span style="color:red;">*</span></label>
                            <select name="asset_type" id="create_asset_type" class="form-control" required>
                                <?php foreach ($ASSET_CATEGORIES as $catKey => $catData): ?>
                                    <?php if (!empty($catData['types'])): ?>
                                        <optgroup label="<?= htmlspecialchars($catData['label']) ?>">
                                            <?php foreach ($catData['types'] as $type): ?>
                                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $type))) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                <?php endforeach; ?>
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
                            <label for="create_status">เธชเธ–เธฒเธเธฐ <span style="color:red;">*</span></label>
                            <select name="status" id="create_status" class="form-control" required>
                                <option value="active">Active - เนเธเนเธเธฒเธเธญเธขเธนเน</option>
                                <option value="inactive">Inactive - เนเธกเนเนเธ”เนเนเธเนเธเธฒเธ</option>
                                <option value="maintenance">Maintenance - เธเนเธญเธกเธเธณเธฃเธธเธ</option>
                                <option value="retired">Retired - เน€เธฅเธดเธเนเธเนเธเธฒเธ</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_location">Location (เธซเนเธญเธ/เธชเธ–เธฒเธเธ—เธตเน)</label>
                            <input type="text" name="location" id="create_location" class="form-control" placeholder="๏ฟฝ๏ฟฝ ๏ฟฝ๏ฟฝอง IT, ๏ฟฝ๏ฟฝ๏ฟฝ 2 ๏ฟฝาค๏ฟฝ๏ฟฝ๏ฟฝำนัก๏ฟฝาน๏ฟฝหญ๏ฟฝ">
                        </div>
                        <div class="form-group">
                            <label for="create_department">เนเธเธเธ/เธเนเธฒเธข</label>
                            <input type="text" name="department" id="create_department" class="form-control" placeholder="e.g., IT, HR, Production">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_assigned_to">เธเธนเนเธฃเธฑเธเธเธดเธ”เธเธญเธ (Assigned To / User)</label>
                            <select name="assigned_to" id="create_assigned_to" class="form-control">
                                <option value="">เนเธกเนเนเธ”เนเธกเธญเธเธซเธกเธฒเธข</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create_tech_in_charge">เธเนเธฒเธเน€เธ—เธเธเธดเธ (Technician in Charge)</label>
                            <select name="tech_in_charge" id="create_tech_in_charge" class="form-control">
                                <option value="">โ€” เน€เธฅเธทเธญเธเธเนเธฒเธ โ€”</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_alternate_user">Alternate Username</label>
                            <input type="text" name="alternate_user" id="create_alternate_user" class="form-control" placeholder="๏ฟฝะบุช๏ฟฝ๏ฟฝอผ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝาน๏ฟฝ๏ฟฝ๏ฟฝอง (๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ)">
                        </div>
                        <div class="form-group">
                            <label for="create_asset_group">เธเธฅเธธเนเธก/เธ—เธตเธก</label>
                            <input type="text" name="asset_group" id="create_asset_group" class="form-control" placeholder="e.g., IT Team, Admin">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_condition">เธชเธ เธฒเธเธญเธธเธเธเธฃเธ“เน</label>
                            <select name="condition" id="create_condition" class="form-control">
                                <option value="good">Good - เธ”เธต</option>
                                <option value="fair">Fair - เธเธญเนเธเน</option>
                                <option value="poor">Poor - เนเธขเน</option>
                                <option value="damaged">Damaged - เน€เธชเธตเธขเธซเธฒเธข</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="create_last_inventory_date">เธงเธฑเธเธ—เธตเน Inventory เธฅเนเธฒเธชเธธเธ”</label>
                            <input type="date" name="last_inventory_date" id="create_last_inventory_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="create_notes">Comments / เธซเธกเธฒเธขเน€เธซเธ•เธธ</label>
                        <textarea name="notes" id="create_notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
                    </div>
                </div>

                <!-- Tab: OS -->
                <div id="c_section_os" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_os_name">Operating System</label>
                            <select name="os_name" id="create_os_name" class="form-control">
                                <option value="">โ€” เน€เธฅเธทเธญเธ OS โ€”</option>
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
                                <option value="">โ€”</option>
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
                            <label for="create_purchase_date">Purchase Date (เธงเธฑเธเธ—เธตเนเธเธทเนเธญ)</label>
                            <input type="date" name="purchase_date" id="create_purchase_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="create_warranty_expiry">Warranty Expiry (เธงเธฑเธเธซเธกเธ”เธเธฃเธฐเธเธฑเธ)</label>
                            <input type="date" name="warranty_expiry" id="create_warranty_expiry" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_purchase_price">เธฃเธฒเธเธฒเธเธทเนเธญ (เธเธฒเธ—)</label>
                            <input type="number" name="purchase_price" id="create_purchase_price" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="create_salvage_value">เธกเธนเธฅเธเนเธฒเธเธฒเธ (เธเธฒเธ—)</label>
                            <input type="number" name="salvage_value" id="create_salvage_value" class="form-control" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="create_useful_life_years">เธญเธฒเธขเธธเธเธฒเธฃเนเธเนเธเธฒเธ (เธเธต)</label>
                            <input type="number" name="useful_life_years" id="create_useful_life_years" class="form-control" value="5" min="1" max="30">
                        </div>
                        <div class="form-group">
                            <label for="create_supplier">เธเธนเนเธเธฑเธ”เธเธณเธซเธเนเธฒเธข (Supplier)</label>
                            <input type="text" name="supplier" id="create_supplier" class="form-control" placeholder="๏ฟฝ๏ฟฝ ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝัท๏ฟฝัด๏ฟฝ๏ฟฝหน๏ฟฝ๏ฟฝ๏ฟฝ ๏ฟฝ๏ฟฝ๏ฟฝอผ๏ฟฝ๏ฟฝ๏ฟฝับ๏ฟฝ๏ฟฝ๏ฟฝ๏ฟฝ">
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;border-top:1px solid #e2e8f0;padding-top:15px;">
                    <button type="button" class="btn" onclick="closeCreateModal()" style="background:#e2e8f0;">เธขเธเน€เธฅเธดเธ</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> เธเธฑเธเธ—เธถเธเธชเธดเธเธ—เธฃเธฑเธเธขเน</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Asset Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width:820px;">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> เนเธเนเนเธเธชเธดเธเธ—เธฃเธฑเธเธขเน</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="asset_id" id="edit_asset_id">

                <!-- Tab Navigation -->
                <div style="display:flex;gap:4px;background:#f7fafc;padding:6px;border-radius:10px;margin-bottom:20px;flex-wrap:wrap;">
                    <button type="button" onclick="switchModalTab('e','basic')" id="e_tab_basic" class="modal-tab active-tab">
                        <i class="fas fa-info-circle"></i> เธเนเธญเธกเธนเธฅเธ—เธฑเนเธงเนเธ
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
                        <i class="fas fa-shopping-cart"></i> เธเธฒเธฃเธเธฑเธ”เธเธทเนเธญ
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
                            <label for="edit_asset_name">เธเธทเนเธญเธญเธธเธเธเธฃเธ“เน <span style="color:red;">*</span></label>
                            <input type="text" name="asset_name" id="edit_asset_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_asset_type">เธเธฃเธฐเน€เธ เธ— <span style="color:red;">*</span></label>
                            <select name="asset_type" id="edit_asset_type" class="form-control" required>
                                <?php foreach ($ASSET_CATEGORIES as $catKey => $catData): ?>
                                    <?php if (!empty($catData['types'])): ?>
                                        <optgroup label="<?= htmlspecialchars($catData['label']) ?>">
                                            <?php foreach ($catData['types'] as $type): ?>
                                                <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $type))) ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                <?php endforeach; ?>
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
                            <label for="edit_status">เธชเธ–เธฒเธเธฐ <span style="color:red;">*</span></label>
                            <select name="status" id="edit_status" class="form-control" required>
                                <option value="active">Active - เนเธเนเธเธฒเธเธญเธขเธนเน</option>
                                <option value="inactive">Inactive - เนเธกเนเนเธ”เนเนเธเนเธเธฒเธ</option>
                                <option value="maintenance">Maintenance - เธเนเธญเธกเธเธณเธฃเธธเธ</option>
                                <option value="retired">Retired - เน€เธฅเธดเธเนเธเนเธเธฒเธ</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_location">Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_department">เนเธเธเธ/เธเนเธฒเธข</label>
                            <input type="text" name="department" id="edit_department" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_assigned_to">เธเธนเนเธฃเธฑเธเธเธดเธ”เธเธญเธ (User)</label>
                            <select name="assigned_to" id="edit_assigned_to" class="form-control">
                                <option value="">เนเธกเนเนเธ”เนเธกเธญเธเธซเธกเธฒเธข</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_tech_in_charge">เธเนเธฒเธเน€เธ—เธเธเธดเธ (Technician)</label>
                            <select name="tech_in_charge" id="edit_tech_in_charge" class="form-control">
                                <option value="">โ€” เน€เธฅเธทเธญเธเธเนเธฒเธ โ€”</option>
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
                            <label for="edit_asset_group">เธเธฅเธธเนเธก/เธ—เธตเธก</label>
                            <input type="text" name="asset_group" id="edit_asset_group" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_condition">เธชเธ เธฒเธเธญเธธเธเธเธฃเธ“เน</label>
                            <select name="condition" id="edit_condition" class="form-control">
                                <option value="good">Good - เธ”เธต</option>
                                <option value="fair">Fair - เธเธญเนเธเน</option>
                                <option value="poor">Poor - เนเธขเน</option>
                                <option value="damaged">Damaged - เน€เธชเธตเธขเธซเธฒเธข</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_last_inventory_date">เธงเธฑเธเธ—เธตเน Inventory เธฅเนเธฒเธชเธธเธ”</label>
                            <input type="date" name="last_inventory_date" id="edit_last_inventory_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_notes">Comments / เธซเธกเธฒเธขเน€เธซเธ•เธธ</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <!-- Tab: OS -->
                <div id="e_section_os" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_os_name">Operating System</label>
                            <select name="os_name" id="edit_os_name" class="form-control">
                                <option value="">โ€” เน€เธฅเธทเธญเธ OS โ€”</option>
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
                                <option value="">โ€”</option>
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
                            <label for="edit_purchase_price">เธฃเธฒเธเธฒเธเธทเนเธญ (เธเธฒเธ—)</label>
                            <input type="number" name="purchase_price" id="edit_purchase_price" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="edit_salvage_value">เธกเธนเธฅเธเนเธฒเธเธฒเธ (เธเธฒเธ—)</label>
                            <input type="number" name="salvage_value" id="edit_salvage_value" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_useful_life">เธญเธฒเธขเธธเธเธฒเธฃเนเธเนเธเธฒเธ (เธเธต)</label>
                            <input type="number" name="useful_life_years" id="edit_useful_life" class="form-control" min="1" max="30">
                        </div>
                        <div class="form-group">
                            <label for="edit_supplier">เธเธนเนเธเธฑเธ”เธเธณเธซเธเนเธฒเธข (Supplier)</label>
                            <input type="text" name="supplier" id="edit_supplier" class="form-control">
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;border-top:1px solid #e2e8f0;padding-top:15px;">
                    <button type="button" class="btn" onclick="closeEditModal()" style="background:#e2e8f0;">เธขเธเน€เธฅเธดเธ</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> เธเธฑเธเธ—เธถเธเธเธฒเธฃเนเธเนเนเธ</button>
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

<?php $pageScripts = '<script src="' . BASE_URL . 'assets/js/assets.js"></script>'; ?>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>















