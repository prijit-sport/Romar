<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$isAdmin = $_SESSION['role'] === 'admin';

// ── Flash message (PRG pattern) ────────────────────────────────
$message     = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type']    ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Handle Create Asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
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

    // Build INSERT
    $assignedSQL     = $assigned_to     ? $assigned_to     : 'NULL';
    $techSQL         = $tech_in_charge  ? $tech_in_charge  : 'NULL';
    $cpuCoresSQL     = $cpu_cores       ? $cpu_cores       : 'NULL';
    $ramSQL          = $ram_gb          ? $ram_gb          : 'NULL';
    $priceSQL        = $purchase_price  ? $purchase_price  : 'NULL';
    $purchaseDateSQL = $purchase_date   ? "'$purchase_date'"   : 'NULL';
    $warrantySQL     = $warranty_expiry ? "'$warranty_expiry'" : 'NULL';
    $invDateSQL      = $last_inventory_date ? "'$last_inventory_date'" : 'NULL';

    // ── ตรวจสอบ asset_tag ซ้ำก่อน INSERT ──────────────────────
    $checkDuplicate = $db->query("SELECT asset_id FROM assets WHERE asset_tag = '$asset_tag'");
    if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
        $_SESSION['flash_message'] = "Asset Tag \"$asset_tag\" มีในระบบแล้ว กรุณาใช้รหัสสินทรัพย์ใหม่";
        $_SESSION['flash_type']    = 'error';
        $cat = $_GET['cat'] ?? 'all';
        header('Location: assets.php?cat=' . $cat);
        exit;
    } // not duplicate

    $dbResult = $db->query("INSERT INTO assets (
        asset_name, asset_tag, asset_type, brand, model, serial_number, inventory_number,
        location, department, asset_group, assigned_to, tech_in_charge, alternate_user,
        purchase_date, warranty_expiry, purchase_price, salvage_value, useful_life_years, supplier,
        last_inventory_date, condition_status, status, notes,
        os_name, os_version, os_architecture, os_service_pack, os_product_key,
        ip_address, mac_address, network_domain, gateway, dns_server,
        cpu, cpu_cores, ram_gb, storage, gpu, monitor, created_at
    ) VALUES (
        '$asset_name','$asset_tag','$asset_type','$brand','$model','$serial_number','$inventory_number',
        '$location','$department','$asset_group',$assignedSQL,$techSQL,'$alternate_user',
        $purchaseDateSQL,$warrantySQL,$priceSQL,$salvage_value,$useful_life_years,'$supplier',
        $invDateSQL,'$condition','$status','$notes',
        '$os_name','$os_version','$os_architecture','$os_service_pack','$os_product_key',
        '$ip_address','$mac_address','$network_domain','$gateway','$dns_server',
        '$cpu',$cpuCoresSQL,$ramSQL,'$storage','$gpu','$monitor',NOW()
    )");
    if ($dbResult) {
        logActivity($_SESSION['user_id'], 'เพิ่มสินทรัพย์', 'Assets', "เพิ่ม: $asset_name ($asset_tag)");
        $_SESSION['flash_message'] = 'เพิ่มสินทรัพย์สำเร็จ!';
        $_SESSION['flash_type']    = 'success';
    } else {
        $_SESSION['flash_message'] = 'เกิดข้อผิดพลาด: ' . $db->error;
        $_SESSION['flash_type']    = 'error';
    }

    $cat = $_GET['cat'] ?? 'all';
    header('Location: assets.php?cat=' . $cat);
    exit;
}

// Handle Update Asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
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
    $assigned_to       = !empty($_POST['assigned_to'])      ? (int)$_POST['assigned_to']      : 'NULL';
    $tech_in_charge    = !empty($_POST['tech_in_charge'])   ? (int)$_POST['tech_in_charge']   : 'NULL';
    $alternate_user    = sanitize($_POST['alternate_user'] ?? '');
    $purchase_date     = !empty($_POST['purchase_date'])    ? "'".$_POST['purchase_date']."'"   : 'NULL';
    $warranty_expiry   = !empty($_POST['warranty_expiry'])  ? "'".$_POST['warranty_expiry']."'" : 'NULL';
    $purchase_price    = !empty($_POST['purchase_price'])   ? (float)$_POST['purchase_price']   : 'NULL';
    $salvage_value     = !empty($_POST['salvage_value'])    ? (float)$_POST['salvage_value']    : 0;
    $useful_life_years = !empty($_POST['useful_life_years'])? (int)$_POST['useful_life_years']  : 5;
    $supplier          = sanitize($_POST['supplier'] ?? '');
    $last_inventory_date = !empty($_POST['last_inventory_date']) ? "'".$_POST['last_inventory_date']."'" : 'NULL';
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
    $cpu_cores         = !empty($_POST['cpu_cores']) ? (int)$_POST['cpu_cores'] : 'NULL';
    $ram_gb            = !empty($_POST['ram_gb'])    ? (int)$_POST['ram_gb']    : 'NULL';
    $storage           = sanitize($_POST['storage'] ?? '');
    $gpu               = sanitize($_POST['gpu'] ?? '');
    $monitor           = sanitize($_POST['monitor'] ?? '');

    $dbResult = $db->query("UPDATE assets SET
        asset_name='$asset_name', asset_tag='$asset_tag', asset_type='$asset_type',
        brand='$brand', model='$model', serial_number='$serial_number', inventory_number='$inventory_number',
        location='$location', department='$department', asset_group='$asset_group',
        assigned_to=$assigned_to, tech_in_charge=$tech_in_charge, alternate_user='$alternate_user',
        purchase_date=$purchase_date, warranty_expiry=$warranty_expiry,
        purchase_price=$purchase_price, salvage_value=$salvage_value, useful_life_years=$useful_life_years,
        supplier='$supplier', last_inventory_date=$last_inventory_date,
        condition_status='$condition', status='$status', notes='$notes',
        os_name='$os_name', os_version='$os_version', os_architecture='$os_architecture',
        os_service_pack='$os_service_pack', os_product_key='$os_product_key',
        ip_address='$ip_address', mac_address='$mac_address', network_domain='$network_domain',
        gateway='$gateway', dns_server='$dns_server',
        cpu='$cpu', cpu_cores=$cpu_cores, ram_gb=$ram_gb, storage='$storage', gpu='$gpu', monitor='$monitor'
        WHERE asset_id=$asset_id");

    if ($dbResult) {
        logActivity($_SESSION['user_id'], 'อัปเดตสินทรัพย์', 'Assets', "อัปเดต: $asset_name");
        $_SESSION['flash_message'] = 'อัปเดตสินทรัพย์สำเร็จ!';
        $_SESSION['flash_type']    = 'success';
    } else {
        $_SESSION['flash_message'] = 'เกิดข้อผิดพลาด: ' . $db->error;
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

// ── Category definitions (GLPI-style) ─────────────────────────
$ASSET_CATEGORIES = [
    'all'          => ['label'=>'สินทรัพย์ทั้งหมด',  'icon'=>'fa-boxes',          'types'=>[], 'color'=>'#667eea'],
    'computers'    => ['label'=>'คอมพิวเตอร์',        'icon'=>'fa-desktop',        'types'=>['desktop','laptop'], 'color'=>'#4299e1'],
    'monitors'     => ['label'=>'จอมอนิเตอร์',        'icon'=>'fa-tv',             'types'=>['monitor'], 'color'=>'#38a169'],
    'network'      => ['label'=>'อุปกรณ์เครือข่าย',  'icon'=>'fa-network-wired',  'types'=>['network'], 'color'=>'#805ad5'],
    'printers'     => ['label'=>'เครื่องพิมพ์',       'icon'=>'fa-print',          'types'=>['printer'], 'color'=>'#dd6b20'],
    'phones'       => ['label'=>'โทรศัพท์/มือถือ',    'icon'=>'fa-mobile-screen-button', 'types'=>['mobile','phone'], 'color'=>'#e53e3e'],
    'software'     => ['label'=>'ซอฟต์แวร์',          'icon'=>'fa-floppy-disk',    'types'=>['software'], 'color'=>'#3182ce'],
    'other'        => ['label'=>'อื่นๆ',               'icon'=>'fa-box',            'types'=>['other'], 'color'=>'#718096'],
];

// Current category from URL
$cat = isset($_GET['cat']) ? sanitize($_GET['cat']) : 'all';
if (!array_key_exists($cat, $ASSET_CATEGORIES)) $cat = 'all';
$currentCat = $ASSET_CATEGORIES[$cat];

// Count per category for sidebar badges
$catCounts = [];
foreach ($ASSET_CATEGORIES as $key => $catDef) {
    if ($key === 'all') {
        $r = $db->query("SELECT COUNT(*) as cnt FROM assets");
    } else {
        $typeList = implode("','", $catDef['types']);
        $r = $db->query("SELECT COUNT(*) as cnt FROM assets WHERE asset_type IN ('$typeList')");
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

// Get Users for Assignment
$users = $db->query("SELECT user_id, full_name FROM users WHERE status = 'active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

// Get Locations
$locations = $db->query("SELECT DISTINCT location FROM assets WHERE location IS NOT NULL AND location != '' ORDER BY location")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสินทรัพย์ - IT Support</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #065f159c;
            color: #000000;
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #10ce30 0%, #000000 100%);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
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

        .brand-title {
            font-size: 1.8em;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-subtitle {
            font-size: 0.85em;
            color: rgb(0, 0, 0);
            margin-top: 5px;
        }

        .sidebar-nav ul {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            color: rgb(255, 255, 255);
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding-left: 25px;
        }

        .sidebar-nav li.active a {
            background: linear-gradient(90deg, rgb(17, 224, 35), rgb(184, 209, 39));
            color: white;
            border-left: 4px solid #fff;
        }

        .menu-section {
            padding: 25px 20px 10px;
            color: rgb(255, 255, 255);
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .breadcrumb-nav {
            background: rgb(255, 255, 255);
            padding: 15px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgb(0, 0, 0);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 2em;
            color: #070707;
            font-weight: 700;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(180deg, #10ce30 0%, #000000 );
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgb(0, 0, 0);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgb(0, 0, 0);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
        }

        .stat-info h3 {
            font-size: 2em;
            font-weight: 700;
            color: #000000;
        }

        .stat-info p {
            color: #000000;
            font-size: 0.9em;
        }

        /* Filter Bar */
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgb(0, 0, 0);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 15px;
        }

        .form-control {
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Sarabun', sans-serif;
        }

        /* Table */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgb(0, 0, 0);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f7fafc;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-active { background: #c6f6d5; color: #2f855a; }
        .badge-inactive { background: #fed7d7; color: #c53030; }
        .badge-maintenance { background: #feebc8; color: #c05621; }
        .badge-retired { background: #e2e8f0; color: #4a5568; }

        .type-badge {
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .type-desktop { background: #bee3f8; color: #2c5282; }
        .type-laptop { background: #e6fffa; color: #285e61; }
        .type-server { background: #fed7d7; color: #c53030; }
        .type-printer { background: #fef5e7; color: #d69e2e; }
        .type-network { background: #e9d8fd; color: #553c9a; }
        .type-mobile { background: #c6f6d5; color: #2f855a; }

        .warranty-warning {
            color: #ed8936;
            font-weight: 600;
        }

        .warranty-expired {
            color: #f56565;
            font-weight: 600;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85em;
        }

        .btn-edit {
            background: #4299e1;
            color: white;
        }

        .btn-delete {
            background: #f56565;
            color: white;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-size: 1.5em;
            color: #1a202c;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #718096;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Assets popup flyout menu */
        .nav-parent {
            display: flex;
            align-items: center;
            padding: 13px 20px;
            color: white;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s;
            justify-content: space-between;
            user-select: none;
            position: relative;
        }
        .nav-parent:hover, .nav-parent.open {
            background: rgba(255,255,255,0.12);
        }
        .nav-parent .arrow {
            transition: transform 0.25s;
            font-size: 0.75em;
        }
        .nav-parent.open .arrow {
            transform: rotate(90deg);
        }
        /* Remove old inline submenu */
        .nav-submenu {
            display: none;
        }
        /* Floating popup box */
        .assets-popup {
            display: none;
            position: fixed;
            left: 220px;
            background: #1a472a;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px;
            box-shadow: 4px 4px 20px rgba(0,0,0,0.5);
            min-width: 230px;
            z-index: 9999;
            padding: 6px 0;
            animation: popupFadeIn 0.18s ease;
        }
        .assets-popup.show { display: block; }
        @keyframes popupFadeIn {
            from { opacity:0; transform: translateX(-8px); }
            to   { opacity:1; transform: translateX(0); }
        }
        .assets-popup-title {
            padding: 8px 16px 6px;
            font-size: 0.75em;
            font-weight: 700;
            color: rgba(255,255,255,0.5);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 4px;
        }
        .assets-popup a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 16px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-size: 0.92em;
            transition: background 0.15s;
            gap: 8px;
        }
        .assets-popup a:hover {
            background: rgba(255,255,255,0.1);
        }
        .assets-popup a.active-item {
            background: linear-gradient(90deg, rgba(17,224,35,0.7), rgba(184,209,39,0.5));
            color: white;
            font-weight: 600;
        }
        .assets-popup a span.left-label {
            display: flex; align-items: center; gap: 9px;
        }
        .submenu-badge {
            background: rgba(255,255,255,0.25);
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 0.78em;
            font-weight: 700;
        }
        .modal-tab {
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            background: none;
            font-size: 0.88em;
            font-weight: 600;
            cursor: pointer;
            color: #718096;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .modal-tab:hover { background: #e2e8f0; color: #2d3748; }
        .modal-tab.active-tab { background: linear-gradient(135deg, #10ce30, #000); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-brand">
                <div>
                    <div class="brand-title">
                        <i class="fas fa-ticket-alt"></i>
                        IT Support
                    </div>
                    <div class="brand-subtitle">Ticket Management System</div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <?php if ($isAdmin): ?>
                    <li>
                        <a href="../admin/dashboard.php">
                            <i class="fas fa-arrow-left"></i> กลับ Dashboard หลัก
                        </a>
                    </li>
                    <?php endif; ?>

                    <li class="menu-section">หลัก</li>
                    <li><a href="dashboard.php"><i class="fas fa-home" style="width:18px;"></i> Dashboard</a></li>
                    <li><a href="tickets.php"><i class="fas fa-ticket-alt" style="width:18px;"></i> IT Tickets</a></li>
                    <li><a href="knowledgebase.php"><i class="fas fa-book" style="width:18px;"></i> Knowledge Base</a></li>

                    <!-- Assets popup parent -->
                    <li class="menu-section">Assets</li>

                    <!-- Assets flyout trigger -->
                    <li style="position:relative;">
                        <div class="nav-parent <?= $cat !== '' ? 'open' : '' ?>" id="assetsToggle"
                             onclick="toggleAssetsPopup(event, this)">
                            <span style="display:flex;align-items:center;gap:10px;">
                                <i class="fas fa-boxes" style="width:18px;"></i>
                                สินทรัพย์ทั้งหมด
                                <?php if ($catCounts['all'] > 0): ?>
                                <span class="submenu-badge"><?= $catCounts['all'] ?></span>
                                <?php endif; ?>
                            </span>
                            <i class="fas fa-chevron-right arrow"></i>
                        </div>
                        <!-- hidden old submenu (kept for compatibility) -->
                        <ul class="nav-submenu" id="assetsSubmenu"></ul>
                    </li>

                    <?php if ($isAdmin): ?>
                    <li class="menu-section">จัดการ</li>
                    <li><a href="users.php"><i class="fas fa-users" style="width:18px;"></i> ผู้ใช้งาน</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar" style="width:18px;"></i> รายงาน</a></li>
                    <li><a href="assetsreports.php"><i class="fas fa-chart-line" style="width:18px;"></i> รายงานสินทรัพย์</a></li>
                    <li><a href="slaconfig.php"><i class="fas fa-clock" style="width:18px;"></i> ตั้งค่า SLA</a></li>
                    <?php endif; ?>

                    <li class="menu-section">ระบบ</li>
                    <?php if ($isAdmin): ?>
                    <li><a href="settings.php"><i class="fas fa-cog" style="width:18px;"></i> ตั้งค่า</a></li>
                    <?php endif; ?>
                    <li><a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">
                        <i class="fas fa-sign-out-alt" style="width:18px;"></i> ออกจากระบบ
                    </a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb-nav">
                <div style="display:flex;align-items:center;gap:8px;">
                    <a href="dashboard.php" style="color:#667eea;text-decoration:none;"><i class="fas fa-home"></i></a>
                    <span style="color:#ccc;">›</span>
                    <a href="assets.php?cat=all" style="color:#667eea;text-decoration:none;">Assets</a>
                    <?php if ($cat !== 'all'): ?>
                    <span style="color:#ccc;">›</span>
                    <span style="color:#2d3748;font-weight:600;"><i class="fas <?= $currentCat['icon'] ?>"></i> <?= $currentCat['label'] ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas <?= $currentCat['icon'] ?>" style="color:<?= $currentCat['color'] ?>;"></i>
                    <?= $currentCat['label'] ?>
                    <span style="font-size:0.55em;background:#e2e8f0;color:#4a5568;padding:4px 12px;border-radius:20px;margin-left:10px;font-weight:500;"><?= count($assets) ?> รายการ</span>
                </h1>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> เพิ่ม<?= $cat !== 'all' ? $currentCat['label'] : 'สินทรัพย์' ?>
                </button>
                <?php endif; ?>
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
                        <i class="fas fa-box" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                        <p>สินทรัพย์ทั้งหมด</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                        <i class="fas fa-check-circle" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['active_count'] ?? 0); ?></h3>
                        <p>Active</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                        <i class="fas fa-tools" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['maintenance_count'] ?? 0); ?></h3>
                        <p>Maintenance</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                        <i class="fas fa-exclamation-triangle" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['warranty_expired_count'] ?? 0); ?></h3>
                        <p>Warranty Expired</p>
                    </div>
                </div>

                <?php if (($stats['warranty_expiring_count'] ?? 0) > 0): ?>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ecc94b, #d69e2e);">
                        <i class="fas fa-clock" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['warranty_expiring_count']); ?></h3>
                        <p>Warranty Expiring (30 days)</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Assets Table -->
            <div class="card" id="tableView">
                <div style="padding:15px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <strong style="color:#4a5568;white-space:nowrap;">
                        <i class="fas <?= $currentCat['icon'] ?>" style="color:<?= $currentCat['color'] ?>;"></i>
                        <?= $currentCat['label'] ?>
                        <span style="background:#e2e8f0;color:#718096;padding:2px 10px;border-radius:12px;font-size:0.85em;font-weight:500;margin-left:8px;"><?= count($assets) ?></span>
                    </strong>
                    <!-- Inline search -->
                    <form method="GET" style="display:flex;gap:8px;align-items:center;flex:1;max-width:600px;">
                        <input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>">
                        <div style="position:relative;flex:1;">
                            <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#a0aec0;font-size:0.85em;"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                   placeholder="ค้นหาชื่อ, Asset Tag, IP, Serial..."
                                   style="width:100%;padding:9px 12px 9px 34px;border:1px solid #e2e8f0;border-radius:8px;font-family:'Sarabun',sans-serif;font-size:0.9em;">
                        </div>
                        <select name="status" onchange="this.form.submit()"
                                style="padding:9px 10px;border:1px solid #e2e8f0;border-radius:8px;font-family:'Sarabun',sans-serif;font-size:0.9em;min-width:130px;">
                            <option value="">ทุกสถานะ</option>
                            <option value="active"      <?= $status==='active'     ?'selected':'' ?>>Active</option>
                            <option value="inactive"    <?= $status==='inactive'   ?'selected':'' ?>>Inactive</option>
                            <option value="maintenance" <?= $status==='maintenance'?'selected':'' ?>>Maintenance</option>
                            <option value="retired"     <?= $status==='retired'    ?'selected':'' ?>>Retired</option>
                        </select>
                        <button type="submit" class="btn btn-primary" style="padding:9px 16px;white-space:nowrap;">
                            <i class="fas fa-search"></i> ค้นหา
                        </button>
                    </form>
                    <div style="display:flex;gap:6px;">
                        <button onclick="switchView('table')" id="btnTableView" class="btn btn-primary btn-sm" style="font-size:0.82em;padding:7px 12px;">
                            <i class="fas fa-list"></i> รายการ
                        </button>
                        <button onclick="switchView('user')" id="btnUserView" class="btn btn-sm" style="font-size:0.82em;padding:7px 12px;background:#e2e8f0;">
                            <i class="fas fa-users"></i> แยกตามผู้รับผิดชอบ
                        </button>
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
                            <td colspan="11" style="text-align: center; padding: 40px; color: #718096;">
                                <i class="fas fa-box" style="font-size: 3em; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                                ไม่พบข้อมูลสินทรัพย์
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($assets as $asset): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($asset['asset_tag']); ?></strong>
                                    <?php if (!empty($asset['inventory_number'])): ?>
                                    <br><small style="color:#718096;">INV: <?= htmlspecialchars($asset['inventory_number']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($asset['asset_name']); ?>
                                    <?php if (!empty($asset['serial_number'])): ?>
                                    <br><small style="color:#999;">S/N: <?= htmlspecialchars($asset['serial_number']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="type-badge type-<?php echo $asset['asset_type']; ?>">
                                        <?php echo strtoupper($asset['asset_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($asset['brand'] ?? 'N/A'); ?></strong><br>
                                    <small style="color: #718096;"><?php echo htmlspecialchars($asset['model'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($asset['os_name'])): ?>
                                        <small style="color:#2b6cb0;font-weight:600;"><?= htmlspecialchars($asset['os_name']) ?></small>
                                        <?php if (!empty($asset['os_version'])): ?>
                                        <br><small style="color:#718096;"><?= htmlspecialchars($asset['os_version']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <small style="color:#ccc;">—</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($asset['ip_address'])): ?>
                                        <code style="font-size:0.85em;background:#edf2f7;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($asset['ip_address']) ?></code>
                                    <?php else: ?>
                                        <small style="color:#ccc;">—</small>
                                    <?php endif; ?>
                                    <?php if (!empty($asset['mac_address'])): ?>
                                    <br><small style="color:#999;"><?= htmlspecialchars($asset['mac_address']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($asset['location'] ?? 'N/A'); ?>
                                    <?php if (!empty($asset['department'])): ?>
                                    <br><small style="color:#10ce30;font-weight:600;"><?= htmlspecialchars($asset['department']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($asset['assigned_user_name'] ?? 'ไม่ได้มอบหมาย'); ?>
                                    <?php if (!empty($asset['tech_name'])): ?>
                                    <br><small style="color:#718096;"><i class="fas fa-tools"></i> <?= htmlspecialchars($asset['tech_name']) ?></small>
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
                                        <a href="assetsdetail.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm" style="background:linear-gradient(135deg,#10ce30,#38a169);color:white;" title="ดูรายละเอียด">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($isAdmin): ?>
                                        <button class="btn btn-edit btn-sm" onclick='editAsset(<?php echo json_encode($asset); ?>)' title="แก้ไข">
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
                            <label>Asset Tag <span style="color:red;">*</span></label>
                            <input type="text" name="asset_tag" class="form-control" required placeholder="e.g., IT-DT-001">
                        </div>
                        <div class="form-group">
                            <label>Inventory Number</label>
                            <input type="text" name="inventory_number" class="form-control" placeholder="หมายเลขครุภัณฑ์">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ชื่ออุปกรณ์ <span style="color:red;">*</span></label>
                            <input type="text" name="asset_name" class="form-control" required placeholder="e.g., pc-romar001.romar.co.th">
                        </div>
                        <div class="form-group">
                            <label>ประเภท <span style="color:red;">*</span></label>
                            <select name="asset_type" class="form-control" required>
                                <option value="desktop">Desktop</option>
                                <option value="laptop">Laptop</option>
                                <option value="monitor">Monitor (จอมอนิเตอร์)</option>
                                <option value="server">Server</option>
                                <option value="printer">Printer</option>
                                <option value="network">Network Device</option>
                                <option value="mobile">Mobile</option>
                                <option value="software">Software</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Manufacturer / Brand</label>
                            <input type="text" name="brand" class="form-control" placeholder="e.g., HP, Dell, Lenovo">
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" name="model" class="form-control" placeholder="e.g., ProDesk 400 G5">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Serial Number</label>
                            <input type="text" name="serial_number" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>สถานะ <span style="color:red;">*</span></label>
                            <select name="status" class="form-control" required>
                                <option value="active">Active - ใช้งานอยู่</option>
                                <option value="inactive">Inactive - ไม่ได้ใช้งาน</option>
                                <option value="maintenance">Maintenance - ซ่อมบำรุง</option>
                                <option value="retired">Retired - เลิกใช้งาน</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Location (ห้อง/สถานที่)</label>
                            <input type="text" name="location" class="form-control" placeholder="e.g., IT Room, ฝ่ายผลิต">
                        </div>
                        <div class="form-group">
                            <label>แผนก/ฝ่าย</label>
                            <input type="text" name="department" class="form-control" placeholder="e.g., IT, HR, Production">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ผู้รับผิดชอบ (Assigned To / User)</label>
                            <select name="assigned_to" class="form-control">
                                <option value="">ไม่ได้มอบหมาย</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>ช่างเทคนิค (Technician in Charge)</label>
                            <select name="tech_in_charge" class="form-control">
                                <option value="">— เลือกช่าง —</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Alternate Username</label>
                            <input type="text" name="alternate_user" class="form-control" placeholder="ชื่อผู้ใช้สำรอง">
                        </div>
                        <div class="form-group">
                            <label>กลุ่ม/ทีม</label>
                            <input type="text" name="asset_group" class="form-control" placeholder="e.g., IT Team, Admin">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>สภาพอุปกรณ์</label>
                            <select name="condition" class="form-control">
                                <option value="good">Good - ดี</option>
                                <option value="fair">Fair - พอใช้</option>
                                <option value="poor">Poor - แย่</option>
                                <option value="damaged">Damaged - เสียหาย</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>วันที่ Inventory ล่าสุด</label>
                            <input type="date" name="last_inventory_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Comments / หมายเหตุ</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
                    </div>
                </div>

                <!-- Tab: OS -->
                <div id="c_section_os" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Operating System</label>
                            <select name="os_name" class="form-control">
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
                            <label>OS Version</label>
                            <input type="text" name="os_version" class="form-control" placeholder="e.g., 22H2, 21H2">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Architecture</label>
                            <select name="os_architecture" class="form-control">
                                <option value="">—</option>
                                <option value="64-bit">64-bit</option>
                                <option value="32-bit">32-bit</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Service Pack / Update</label>
                            <input type="text" name="os_service_pack" class="form-control" placeholder="e.g., SP1, 23H2">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>OS Product Key</label>
                        <input type="text" name="os_product_key" class="form-control" placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX">
                    </div>
                </div>

                <!-- Tab: Hardware -->
                <div id="c_section_hw" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>CPU</label>
                            <input type="text" name="cpu" class="form-control" placeholder="e.g., Intel Core i5-10500">
                        </div>
                        <div class="form-group">
                            <label>CPU Cores</label>
                            <input type="number" name="cpu_cores" class="form-control" placeholder="6" min="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>RAM (GB)</label>
                            <input type="number" name="ram_gb" class="form-control" placeholder="8" min="1">
                        </div>
                        <div class="form-group">
                            <label>Storage</label>
                            <input type="text" name="storage" class="form-control" placeholder="e.g., 256GB SSD, 1TB HDD">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>GPU / Graphics Card</label>
                            <input type="text" name="gpu" class="form-control" placeholder="e.g., Intel UHD 630">
                        </div>
                        <div class="form-group">
                            <label>Monitor</label>
                            <input type="text" name="monitor" class="form-control" placeholder="e.g., HP 22fw 21.5 inch">
                        </div>
                    </div>
                </div>

                <!-- Tab: Network -->
                <div id="c_section_net" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>IP Address</label>
                            <input type="text" name="ip_address" class="form-control" placeholder="e.g., 192.168.1.100">
                        </div>
                        <div class="form-group">
                            <label>MAC Address</label>
                            <input type="text" name="mac_address" class="form-control" placeholder="e.g., AA:BB:CC:DD:EE:FF">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Network / Domain</label>
                            <input type="text" name="network_domain" class="form-control" placeholder="e.g., romar.co.th">
                        </div>
                        <div class="form-group">
                            <label>Gateway</label>
                            <input type="text" name="gateway" class="form-control" placeholder="e.g., 192.168.1.1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>DNS Server</label>
                        <input type="text" name="dns_server" class="form-control" placeholder="e.g., 8.8.8.8, 8.8.4.4">
                    </div>
                </div>

                <!-- Tab: Purchase -->
                <div id="c_section_purchase" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Purchase Date (วันที่ซื้อ)</label>
                            <input type="date" name="purchase_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Warranty Expiry (วันหมดประกัน)</label>
                            <input type="date" name="warranty_expiry" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ราคาซื้อ (บาท)</label>
                            <input type="number" name="purchase_price" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label>มูลค่าซาก (บาท)</label>
                            <input type="number" name="salvage_value" class="form-control" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>อายุการใช้งาน (ปี)</label>
                            <input type="number" name="useful_life_years" class="form-control" value="5" min="1" max="30">
                        </div>
                        <div class="form-group">
                            <label>ผู้จัดจำหน่าย (Supplier)</label>
                            <input type="text" name="supplier" class="form-control" placeholder="ชื่อบริษัทผู้ขาย">
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
                            <label>Asset Tag <span style="color:red;">*</span></label>
                            <input type="text" name="asset_tag" id="edit_asset_tag" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Inventory Number</label>
                            <input type="text" name="inventory_number" id="edit_inventory_number" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ชื่ออุปกรณ์ <span style="color:red;">*</span></label>
                            <input type="text" name="asset_name" id="edit_asset_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>ประเภท <span style="color:red;">*</span></label>
                            <select name="asset_type" id="edit_asset_type" class="form-control" required>
                                <option value="desktop">Desktop</option>
                                <option value="laptop">Laptop</option>
                                <option value="monitor">Monitor (จอมอนิเตอร์)</option>
                                <option value="server">Server</option>
                                <option value="printer">Printer</option>
                                <option value="network">Network Device</option>
                                <option value="mobile">Mobile</option>
                                <option value="software">Software</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Manufacturer / Brand</label>
                            <input type="text" name="brand" id="edit_brand" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" name="model" id="edit_model" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Serial Number</label>
                            <input type="text" name="serial_number" id="edit_serial_number" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>สถานะ <span style="color:red;">*</span></label>
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
                            <label>Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>แผนก/ฝ่าย</label>
                            <input type="text" name="department" id="edit_department" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ผู้รับผิดชอบ (User)</label>
                            <select name="assigned_to" id="edit_assigned_to" class="form-control">
                                <option value="">ไม่ได้มอบหมาย</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>ช่างเทคนิค (Technician)</label>
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
                            <label>Alternate Username</label>
                            <input type="text" name="alternate_user" id="edit_alternate_user" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>กลุ่ม/ทีม</label>
                            <input type="text" name="asset_group" id="edit_asset_group" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>สภาพอุปกรณ์</label>
                            <select name="condition" id="edit_condition" class="form-control">
                                <option value="good">Good - ดี</option>
                                <option value="fair">Fair - พอใช้</option>
                                <option value="poor">Poor - แย่</option>
                                <option value="damaged">Damaged - เสียหาย</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>วันที่ Inventory ล่าสุด</label>
                            <input type="date" name="last_inventory_date" id="edit_last_inventory_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Comments / หมายเหตุ</label>
                        <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <!-- Tab: OS -->
                <div id="e_section_os" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Operating System</label>
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
                            <label>OS Version</label>
                            <input type="text" name="os_version" id="edit_os_version" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Architecture</label>
                            <select name="os_architecture" id="edit_os_architecture" class="form-control">
                                <option value="">—</option>
                                <option value="64-bit">64-bit</option>
                                <option value="32-bit">32-bit</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Service Pack / Update</label>
                            <input type="text" name="os_service_pack" id="edit_os_service_pack" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>OS Product Key</label>
                        <input type="text" name="os_product_key" id="edit_os_product_key" class="form-control">
                    </div>
                </div>

                <!-- Tab: Hardware -->
                <div id="e_section_hw" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>CPU</label>
                            <input type="text" name="cpu" id="edit_cpu" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>CPU Cores</label>
                            <input type="number" name="cpu_cores" id="edit_cpu_cores" class="form-control" min="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>RAM (GB)</label>
                            <input type="number" name="ram_gb" id="edit_ram_gb" class="form-control" min="1">
                        </div>
                        <div class="form-group">
                            <label>Storage</label>
                            <input type="text" name="storage" id="edit_storage" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>GPU / Graphics Card</label>
                            <input type="text" name="gpu" id="edit_gpu" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Monitor</label>
                            <input type="text" name="monitor" id="edit_monitor" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Tab: Network -->
                <div id="e_section_net" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>IP Address</label>
                            <input type="text" name="ip_address" id="edit_ip_address" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>MAC Address</label>
                            <input type="text" name="mac_address" id="edit_mac_address" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Network / Domain</label>
                            <input type="text" name="network_domain" id="edit_network_domain" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Gateway</label>
                            <input type="text" name="gateway" id="edit_gateway" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>DNS Server</label>
                        <input type="text" name="dns_server" id="edit_dns_server" class="form-control">
                    </div>
                </div>

                <!-- Tab: Purchase -->
                <div id="e_section_purchase" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Purchase Date</label>
                            <input type="date" name="purchase_date" id="edit_purchase_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Warranty Expiry</label>
                            <input type="date" name="warranty_expiry" id="edit_warranty_expiry" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ราคาซื้อ (บาท)</label>
                            <input type="number" name="purchase_price" id="edit_purchase_price" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label>มูลค่าซาก (บาท)</label>
                            <input type="number" name="salvage_value" id="edit_salvage_value" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>อายุการใช้งาน (ปี)</label>
                            <input type="number" name="useful_life_years" id="edit_useful_life" class="form-control" min="1" max="30">
                        </div>
                        <div class="form-group">
                            <label>ผู้จัดจำหน่าย (Supplier)</label>
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
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="asset_id" id="delete_asset_id">
    </form>

    <script>

        // ── Assets Popup Flyout ──────────────────────────────────
        const POPUP_ITEMS = <?php
            $items = [];
            foreach ($ASSET_CATEGORIES as $key => $catDef) {
                $items[] = [
                    'key'   => $key,
                    'label' => $catDef['label'],
                    'icon'  => $catDef['icon'],
                    'count' => $catCounts[$key] ?? 0,
                ];
            }
            echo json_encode($items);
        ?>;
        const CURRENT_CAT = '<?= $cat ?>';

        let popupEl = null;

        function buildPopup() {
            const div = document.createElement('div');
            div.id = 'assetsPopup';
            div.className = 'assets-popup';
            div.innerHTML = '<div class="assets-popup-title"><i class="fas fa-boxes"></i> ประเภทสินทรัพย์</div>';
            POPUP_ITEMS.forEach(item => {
                const isAll  = item.key === 'all';
                const active = item.key === CURRENT_CAT ? 'active-item' : '';
                const badge  = item.count > 0 ? `<span class="submenu-badge">${item.count}</span>` : '';
                const icon   = isAll ? 'fa-layer-group' : item.icon;
                div.innerHTML += `
                    <a href="assets.php?cat=${item.key}" class="${active}">
                        <span class="left-label"><i class="fas ${icon}" style="width:16px;text-align:center;"></i> ${item.label}</span>
                        ${badge}
                    </a>`;
            });
            document.body.appendChild(div);
            // close when clicking outside
            setTimeout(() => {
                document.addEventListener('click', closePopupOutside);
            }, 10);
            return div;
        }

        function toggleAssetsPopup(e, triggerEl) {
            e.stopPropagation();
            if (popupEl && popupEl.classList.contains('show')) {
                popupEl.classList.remove('show');
                triggerEl.classList.remove('open');
                document.removeEventListener('click', closePopupOutside);
                return;
            }
            if (!popupEl) popupEl = buildPopup();
            // Position next to trigger
            const rect = triggerEl.getBoundingClientRect();
            popupEl.style.top  = rect.top + 'px';
            popupEl.style.left = '220px';
            popupEl.classList.add('show');
            triggerEl.classList.add('open');
        }

        function closePopupOutside(e) {
            if (popupEl && !popupEl.contains(e.target) && !document.getElementById('assetsToggle').contains(e.target)) {
                popupEl.classList.remove('show');
                const toggle = document.getElementById('assetsToggle');
                if (toggle) toggle.classList.remove('open');
                document.removeEventListener('click', closePopupOutside);
            }
        }

        // Auto-open popup on page load if on assets page
        document.addEventListener('DOMContentLoaded', function() {
            if (CURRENT_CAT !== '') {
                const toggle = document.getElementById('assetsToggle');
                if (toggle) toggle.classList.add('open');
            }
        });


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
    </script>
</body>
</html>