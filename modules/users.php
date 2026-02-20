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
$message = '';
$messageType = '';

// Handle Create User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $username = sanitize($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $department = sanitize($_POST['department']);
    $position = sanitize($_POST['position']);
    $role = sanitize($_POST['role']);
    $status = sanitize($_POST['status']);
    
    $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, phone, department, position, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('sssssssss', $username, $password, $full_name, $email, $phone, $department, $position, $role, $status);
    
    if ($stmt->execute()) {
        $message = 'เพิ่มผู้ใช้งานสำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'เพิ่มผู้ใช้งาน', 'Users', "เพิ่ม: $full_name ($username)");
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Handle Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $user_id = (int)$_POST['user_id'];
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $department = sanitize($_POST['department']);
    $position = sanitize($_POST['position']);
    $role = sanitize($_POST['role']);
    $status = sanitize($_POST['status']);
    
    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, department = ?, position = ?, role = ?, status = ? WHERE user_id = ?");
    $stmt->bind_param('sssssssi', $full_name, $email, $phone, $department, $position, $role, $status, $user_id);
    
    if ($stmt->execute()) {
        $message = 'อัปเดตผู้ใช้งานสำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'อัปเดตผู้ใช้งาน', 'Users', "อัปเดต: $full_name");
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Handle Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $user_id = (int)$_POST['user_id'];
    
    $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    
    if ($stmt->execute()) {
        $message = 'ลบผู้ใช้งานสำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'ลบผู้ใช้งาน', 'Users', "ลบ User ID: $user_id");
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Get Users
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$role = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $sql .= " AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if ($role) {
    $sql .= " AND role = ?";
    $params[] = $role;
    $types .= 's';
}

if ($status) {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $db->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get Statistics
$statsSQL = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff_count
    FROM users";
$stats = $db->query($statsSQL)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้งาน - IT Support</title>
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
            color: #000000;
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
             box-shadow: 0 4px 15px rgb(0, 0, 0);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgb(0, 0, 0);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            grid-template-columns: 2fr 1fr 1fr 1fr;
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
        .badge-admin { background: #feebc8; color: #c05621; }
        .badge-staff { background: #c6f6d5; color: #2f855a; }
        .badge-user { background: #e6fffa; color: #285e61; }

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

        .btn-view {
            background: #48bb78;
            color: white;
        }

        .btn-view:hover {
            background: #38a169;
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
            max-width: 600px;
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
            color: #000000;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
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
                    <li>
                        <a href="../admin/dashboard.php">
                            <i class="fas fa-arrow-left"></i> กลับ Dashboard หลัก
                        </a>
                    </li>
                    
                    <li class="menu-section">หลัก</li>
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="tickets.php"><i class="fas fa-ticket-alt"></i> IT Tickets</a></li>
                    <li><a href="assets.php"><i class="fas fa-box"></i> สินทรัพย์</a></li>
                    <li><a href="knowledgebase.php"><i class="fas fa-book"></i> Knowledge Base</a></li>
                    
                    <li class="menu-section">จัดการ</li>
                    <li class="active"><a href="users.php"><i class="fas fa-users"></i> ผู้ใช้งาน</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> รายงาน</a></li>
                    <li><a href="slaconfig.php"><i class="fas fa-clock"></i> ตั้งค่า SLA</a></li>
                    
                    <li class="menu-section">ระบบ</li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> ตั้งค่า</a></li>
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
                <span style="color: #2d3748; font-weight: 600;">จัดการผู้ใช้งาน</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-users"></i> จัดการผู้ใช้งาน</h1>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> เพิ่มผู้ใช้ใหม่
                </button>
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
                        <i class="fas fa-users" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                        <p>ผู้ใช้ทั้งหมด</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                        <i class="fas fa-user" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['user_count'] ?? 0); ?></h3>
                        <p>Users</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                        <i class="fas fa-user-tie" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['staff_count'] ?? 0); ?></h3>
                        <p>Staff</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                        <i class="fas fa-user-shield" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['admin_count'] ?? 0); ?></h3>
                        <p>Admin</p>
                    </div>
                </div>


            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET">
                    <div class="filter-grid">
                        <input type="text" name="search" class="form-control" placeholder="🔍 ค้นหาชื่อ, อีเมล, Username..." value="<?php echo htmlspecialchars($search); ?>">
                        
                        <select name="role" class="form-control" onchange="this.form.submit()">
                            <option value="">ทุกบทบาท</option>
                            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="staff" <?php echo $role === 'staff' ? 'selected' : ''; ?>>Staff</option>
                            <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>User</option>
                        </select>

                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">ทุกสถานะ</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        </select>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> ค้นหา
                        </button>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>Username</th>
                            <th>อีเมล</th>
                            <th>แผนก</th>
                            <th>ตำแหน่ง</th>
                            <th>บทบาท</th>
                            <th>สถานะ</th>
                            <th>การกระทำ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #718096;">
                                <i class="fas fa-users" style="font-size: 3em; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                                ไม่พบข้อมูลผู้ใช้งาน
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php $rank = 1; foreach ($users as $user): ?>
                            <tr>
                                <td><strong>#<?php echo $rank++; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role']; ?>">
                                        <?php echo strtoupper($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $user['status'] ?? 'inactive'; ?>">
                                        <?php echo strtoupper($user['status'] ?? 'inactive'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="userProfile.php?id=<?php echo $user['user_id']; ?>" class="btn btn-view btn-sm" title="ดูข้อมูลผู้ใช้">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-edit btn-sm" onclick='editUser(<?php echo json_encode($user); ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-delete btn-sm" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>')">
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

    <!-- Create User Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> เพิ่มผู้ใช้ใหม่</h2>
                <button class="close-btn" onclick="closeCreateModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_username">Username <span style="color: red;">*</span></label>
                        <input type="text" name="username" id="add_username" class="form-control" autocomplete="username" required>
                    </div>
                    <div class="form-group">
                        <label for="add_password">Password <span style="color: red;">*</span></label>
                        <input type="password" name="password" id="add_password" class="form-control" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="add_full_name">ชื่อ-นามสกุล <span style="color: red;">*</span></label>
                    <input type="text" name="full_name" id="add_full_name" class="form-control" autocomplete="name" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="add_email">อีเมล</label>
                        <input type="email" name="email" id="add_email" class="form-control" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="add_phone">เบอร์โทร</label>
                        <input type="text" name="phone" id="add_phone" class="form-control" autocomplete="tel">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="add_department">แผนก</label>
                        <input type="text" name="department" id="add_department" class="form-control" autocomplete="organization">
                    </div>
                    <div class="form-group">
                        <label for="add_position">ตำแหน่ง</label>
                        <input type="text" name="position" id="add_position" class="form-control" autocomplete="organization-title">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="add_role">บทบาท <span style="color: red;">*</span></label>
                        <select name="role" id="add_role" class="form-control" required>
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_status">สถานะ <span style="color: red;">*</span></label>
                        <select name="status" id="add_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeCreateModal()" style="background: #e2e8f0;">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-edit"></i> แก้ไขผู้ใช้งาน</h2>
                <button class="close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label for="edit_full_name">ชื่อ-นามสกุล <span style="color: red;">*</span></label>
                    <input type="text" name="full_name" id="edit_full_name" class="form-control" autocomplete="name" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_email">อีเมล</label>
                        <input type="email" name="email" id="edit_email" class="form-control" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">เบอร์โทร</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control" autocomplete="tel">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_department">แผนก</label>
                        <input type="text" name="department" id="edit_department" class="form-control" autocomplete="organization">
                    </div>
                    <div class="form-group">
                        <label for="edit_position">ตำแหน่ง</label>
                        <input type="text" name="position" id="edit_position" class="form-control" autocomplete="organization-title">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_role">บทบาท <span style="color: red;">*</span></label>
                        <select name="role" id="edit_role" class="form-control" required>
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">สถานะ <span style="color: red;">*</span></label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
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
        <input type="hidden" name="user_id" id="delete_user_id">
    </form>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.add('show');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('show');
        }

        function editUser(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_full_name').value = user.full_name || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_phone').value = user.phone || '';
            document.getElementById('edit_department').value = user.department || '';
            document.getElementById('edit_position').value = user.position || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status || 'inactive';
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        function deleteUser(userId, name) {
            if (confirm('ต้องการลบผู้ใช้งาน "' + name + '" ใช่หรือไม่?')) {
                document.getElementById('delete_user_id').value = userId;
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