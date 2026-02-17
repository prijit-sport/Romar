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
$message = '';
$messageType = '';

// Handle Create Asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $asset_name = sanitize($_POST['asset_name']);
    $asset_tag = sanitize($_POST['asset_tag']);
    $asset_type = sanitize($_POST['asset_type']);
    $brand = sanitize($_POST['brand']);
    $model = sanitize($_POST['model']);
    $serial_number = sanitize($_POST['serial_number']);
    $location = sanitize($_POST['location']);
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
    $warranty_expiry = !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null;
    $status = sanitize($_POST['status']);
    $notes = sanitize($_POST['notes']);
    
    $stmt = $db->prepare("INSERT INTO assets (asset_name, asset_tag, asset_type, brand, model, serial_number, location, assigned_to, purchase_date, warranty_expiry, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('sssssssissss', $asset_name, $asset_tag, $asset_type, $brand, $model, $serial_number, $location, $assigned_to, $purchase_date, $warranty_expiry, $status, $notes);
    
    if ($stmt->execute()) {
        $message = 'เพิ่มสินทรัพย์สำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'เพิ่มสินทรัพย์', 'Assets', "เพิ่ม: $asset_name ($asset_tag)");
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Handle Update Asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $asset_id = (int)$_POST['asset_id'];
    $asset_name = sanitize($_POST['asset_name']);
    $asset_tag = sanitize($_POST['asset_tag']);
    $asset_type = sanitize($_POST['asset_type']);
    $brand = sanitize($_POST['brand']);
    $model = sanitize($_POST['model']);
    $serial_number = sanitize($_POST['serial_number']);
    $location = sanitize($_POST['location']);
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
    $warranty_expiry = !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null;
    $status = sanitize($_POST['status']);
    $notes = sanitize($_POST['notes']);
    
    $stmt = $db->prepare("UPDATE assets SET asset_name = ?, asset_tag = ?, asset_type = ?, brand = ?, model = ?, serial_number = ?, location = ?, assigned_to = ?, purchase_date = ?, warranty_expiry = ?, status = ?, notes = ? WHERE asset_id = ?");
    $stmt->bind_param('ssssssssssssi', $asset_name, $asset_tag, $asset_type, $brand, $model, $serial_number, $location, $assigned_to, $purchase_date, $warranty_expiry, $status, $notes, $asset_id);
    
    if ($stmt->execute()) {
        $message = 'อัปเดตสินทรัพย์สำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'อัปเดตสินทรัพย์', 'Assets', "อัปเดต: $asset_name");
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Handle Delete Asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $asset_id = (int)$_POST['asset_id'];
    
    $stmt = $db->prepare("DELETE FROM assets WHERE asset_id = ?");
    $stmt->bind_param('i', $asset_id);
    
    if ($stmt->execute()) {
        $message = 'ลบสินทรัพย์สำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'ลบสินทรัพย์', 'Assets', "ลบ Asset ID: $asset_id");
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Get Assets with filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$location = isset($_GET['location']) ? sanitize($_GET['location']) : '';

$sql = "SELECT a.*, u.full_name as assigned_user_name 
        FROM assets a 
        LEFT JOIN users u ON a.assigned_to = u.user_id 
        WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $sql .= " AND (a.asset_name LIKE ? OR a.asset_tag LIKE ? OR a.serial_number LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar (same as users.php) -->
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
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="tickets.php"><i class="fas fa-ticket-alt"></i> IT Tickets</a></li>
                    <li class="active"><a href="assets.php"><i class="fas fa-box"></i> สินทรัพย์</a></li>
                    <li><a href="knowledgebase.php"><i class="fas fa-book"></i> Knowledge Base</a></li>
                    <?php if ($isAdmin): ?>
                    <li class="menu-section">จัดการ</li>
                    <li><a href="users.php"><i class="fas fa-users"></i> ผู้ใช้งาน</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> รายงาน</a></li>
                    <li><a href="slaconfig.php"><i class="fas fa-clock"></i> ตั้งค่า SLA</a></li>
                    <?php endif; ?>
                    
                    <li class="menu-section">ระบบ</li>
                    <?php if ($isAdmin): ?>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> ตั้งค่า</a></li>
                    <?php endif; ?>
                    <li><a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">
                        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                    </a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb-nav">
                <a href="dashboard.php" style="color: #667eea; text-decoration: none;">Dashboard</a> › 
                <span style="color: #2d3748; font-weight: 600;">จัดการสินทรัพย์</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-box"></i> จัดการสินทรัพย์</h1>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> เพิ่มสินทรัพย์
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

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET">
                    <div class="filter-grid">
                        <input type="text" name="search" class="form-control" placeholder="🔍 ค้นหาชื่อ, Asset Tag, Serial Number..." value="<?php echo htmlspecialchars($search); ?>">
                        
                        <select name="type" class="form-control" onchange="this.form.submit()">
                            <option value="">ทุกประเภท</option>
                            <option value="desktop" <?php echo $type === 'desktop' ? 'selected' : ''; ?>>Desktop</option>
                            <option value="laptop" <?php echo $type === 'laptop' ? 'selected' : ''; ?>>Laptop</option>
                            <option value="server" <?php echo $type === 'server' ? 'selected' : ''; ?>>Server</option>
                            <option value="printer" <?php echo $type === 'printer' ? 'selected' : ''; ?>>Printer</option>
                            <option value="network" <?php echo $type === 'network' ? 'selected' : ''; ?>>Network</option>
                            <option value="mobile" <?php echo $type === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                            <option value="software" <?php echo $type === 'software' ? 'selected' : ''; ?>>Software</option>
                            <option value="other" <?php echo $type === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>

                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">ทุกสถานะ</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="maintenance" <?php echo $status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="retired" <?php echo $status === 'retired' ? 'selected' : ''; ?>>Retired</option>
                        </select>

                        <select name="location" class="form-control" onchange="this.form.submit()">
                            <option value="">ทุกสถานที่</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['location']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> ค้นหา
                        </button>
                    </div>
                </form>
            </div>

            <!-- Assets Table -->
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Asset Tag</th>
                            <th>ชื่อสินทรัพย์</th>
                            <th>ประเภท</th>
                            <th>Brand/Model</th>
                            <th>Location</th>
                            <th>Assigned To</th>
                            <th>Warranty</th>
                            <th>สถานะ</th>
                            <th>การกระทำ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assets)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #718096;">
                                <i class="fas fa-box" style="font-size: 3em; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                                ไม่พบข้อมูลสินทรัพย์
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($assets as $asset): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($asset['asset_tag']); ?></strong></td>
                                <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                <td>
                                    <span class="type-badge type-<?php echo $asset['asset_type']; ?>">
                                        <?php echo strtoupper($asset['asset_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($asset['brand'] ?? 'N/A'); ?><br>
                                    <small style="color: #718096;"><?php echo htmlspecialchars($asset['model'] ?? ''); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($asset['location'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($asset['assigned_user_name'] ?? 'ไม่ได้มอบหมาย'); ?></td>
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
                                        <?php if ($isAdmin): ?>
                                        <button class="btn btn-edit btn-sm" onclick='editAsset(<?php echo json_encode($asset); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-delete btn-sm" onclick="deleteAsset(<?php echo $asset['asset_id']; ?>, '<?php echo htmlspecialchars($asset['asset_name']); ?>')">
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
        </div>
    </div>

    <!-- Create Asset Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> เพิ่มสินทรัพย์ใหม่</h2>
                <button class="close-btn" onclick="closeCreateModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="create_asset_tag">Asset Tag <span style="color: red;">*</span></label>
                        <input type="text" name="asset_tag" id="create_asset_tag" class="form-control" required placeholder="e.g., IT-DT-001">
                    </div>
                    <div class="form-group">
                        <label for="create_asset_name">ชื่อสินทรัพย์ <span style="color: red;">*</span></label>
                        <input type="text" name="asset_name" id="create_asset_name" class="form-control" required placeholder="e.g., Desktop Computer">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="create_asset_type">ประเภท <span style="color: red;">*</span></label>
                        <select name="asset_type" id="create_asset_type" class="form-control" required>
                            <option value="desktop">Desktop</option>
                            <option value="laptop">Laptop</option>
                            <option value="server">Server</option>
                            <option value="printer">Printer</option>
                            <option value="network">Network</option>
                            <option value="mobile">Mobile</option>
                            <option value="software">Software</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="create_status">สถานะ <span style="color: red;">*</span></label>
                        <select name="status" id="create_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="retired">Retired</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="create_brand">Brand</label>
                        <input type="text" name="brand" id="create_brand" class="form-control" placeholder="e.g., Dell, HP">
                    </div>
                    <div class="form-group">
                        <label for="create_model">Model</label>
                        <input type="text" name="model" id="create_model" class="form-control" placeholder="e.g., OptiPlex 7080">
                    </div>
                </div>

                <div class="form-group">
                    <label for="create_serial_number">Serial Number</label>
                    <input type="text" name="serial_number" id="create_serial_number" class="form-control" placeholder="e.g., SN123456789">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="create_location">Location</label>
                        <input type="text" name="location" id="create_location" class="form-control" placeholder="e.g., IT Room 301">
                    </div>
                    <div class="form-group">
                        <label for="create_assigned_to">Assigned To</label>
                        <select name="assigned_to" id="create_assigned_to" class="form-control">
                            <option value="">ไม่ได้มอบหมาย</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="create_purchase_date">Purchase Date</label>
                        <input type="date" name="purchase_date" id="create_purchase_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="create_warranty_expiry">Warranty Expiry</label>
                        <input type="date" name="warranty_expiry" id="create_warranty_expiry" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label for="create_notes">Notes</label>
                    <textarea name="notes" id="create_notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeCreateModal()" style="background: #e2e8f0;">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Asset Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> แก้ไขสินทรัพย์</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="asset_id" id="edit_asset_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_asset_tag">Asset Tag <span style="color: red;">*</span></label>
                        <input type="text" name="asset_tag" id="edit_asset_tag" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_asset_name">ชื่อสินทรัพย์ <span style="color: red;">*</span></label>
                        <input type="text" name="asset_name" id="edit_asset_name" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_asset_type">ประเภท <span style="color: red;">*</span></label>
                        <select name="asset_type" id="edit_asset_type" class="form-control" required>
                            <option value="desktop">Desktop</option>
                            <option value="laptop">Laptop</option>
                            <option value="server">Server</option>
                            <option value="printer">Printer</option>
                            <option value="network">Network</option>
                            <option value="mobile">Mobile</option>
                            <option value="software">Software</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">สถานะ <span style="color: red;">*</span></label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="retired">Retired</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_brand">Brand</label>
                        <input type="text" name="brand" id="edit_brand" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_model">Model</label>
                        <input type="text" name="model" id="edit_model" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_serial_number">Serial Number</label>
                    <input type="text" name="serial_number" id="edit_serial_number" class="form-control">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_location">Location</label>
                        <input type="text" name="location" id="edit_location" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_assigned_to">Assigned To</label>
                        <select name="assigned_to" id="edit_assigned_to" class="form-control">
                            <option value="">ไม่ได้มอบหมาย</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

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

                <div class="form-group">
                    <label for="edit_notes">Notes</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeEditModal()" style="background: #e2e8f0;">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
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
        function openCreateModal() {
            document.getElementById('createModal').classList.add('show');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('show');
        }

        function editAsset(asset) {
            document.getElementById('edit_asset_id').value = asset.asset_id;
            document.getElementById('edit_asset_tag').value = asset.asset_tag;
            document.getElementById('edit_asset_name').value = asset.asset_name;
            document.getElementById('edit_asset_type').value = asset.asset_type;
            document.getElementById('edit_status').value = asset.status;
            document.getElementById('edit_brand').value = asset.brand || '';
            document.getElementById('edit_model').value = asset.model || '';
            document.getElementById('edit_serial_number').value = asset.serial_number || '';
            document.getElementById('edit_location').value = asset.location || '';
            document.getElementById('edit_assigned_to').value = asset.assigned_to || '';
            document.getElementById('edit_purchase_date').value = asset.purchase_date || '';
            document.getElementById('edit_warranty_expiry').value = asset.warranty_expiry || '';
            document.getElementById('edit_notes').value = asset.notes || '';
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function deleteAsset(assetId, name) {
            if (confirm('ต้องการลบสินทรัพย์ "' + name + '" ใช่หรือไม่?')) {
                document.getElementById('delete_asset_id').value = assetId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>