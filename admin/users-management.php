<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Check login and admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$message = '';
$messageType = '';
$current_page = basename(__FILE__);

// Handle Add/Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    if ($_POST['action'] === 'add') {
        $username = sanitize($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'รูปแบบ Email ไม่ถูกต้อง';
            $messageType = 'error';
        } elseif (!in_array($role, ['user', 'staff', 'admin'])) {
            $message = 'บทบาทไม่ถูกต้อง';
            $messageType = 'error';
        
        } else {
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
            $stmt->bind_param('sssss', $username, $password, $fullName, $email, $role);
            
            if ($stmt->execute()) {
                $message = 'เพิ่มผู้ใช้สำเร็จ!';
                $messageType = 'success';
                logActivity($_SESSION['user_id'], 'เพิ่มผู้ใช้ใหม่', 'Users', "เพิ่มผู้ใช้: $username");
            } else {
                $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
                $messageType = 'error';
            }
        } // end email/role validation
    } elseif ($_POST['action'] === 'edit') {
        $userId = (int)$_POST['user_id'];
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'รูปแบบ Email ไม่ถูกต้อง';
            $messageType = 'error';
        } elseif (!in_array($role, ['user', 'staff', 'admin'])) {
            $message = 'บทบาทไม่ถูกต้อง';
            $messageType = 'error';
        } else {
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, is_active = ?, password = ? WHERE user_id = ?");
            $stmt->bind_param('sssisi', $fullName, $email, $role, $isActive, $password, $userId);
        } else {
            $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, is_active = ? WHERE user_id = ?");
            $stmt->bind_param('sssii', $fullName, $email, $role, $isActive, $userId);
        }
        
        if ($stmt->execute()) {
                $message = 'แก้ไขผู้ใช้สำเร็จ!';
                $messageType = 'success';
                logActivity($_SESSION['user_id'], 'แก้ไขข้อมูลผู้ใช้', 'Users', "แก้ไขผู้ใช้ ID: $userId");
            } else {
                $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
                $messageType = 'error';
            }
        } // end email/role validation
    } elseif ($_POST['action'] === 'delete') {
        $userId = (int)$_POST['user_id'];
        
        // ป้องกันลบตัวเอง
        if ($userId == $_SESSION['user_id']) {
            $message = 'ไม่สามารถลบบัญชีของตัวเองได้!';
            $messageType = 'error';
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param('i', $userId);
            
            if ($stmt->execute()) {
                $message = 'ลบผู้ใช้สำเร็จ!';
                $messageType = 'success';
                logActivity($_SESSION['user_id'], 'ลบผู้ใช้', 'Users', "ลบผู้ใช้ ID: $userId");
            } else {
                $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
                $messageType = 'error';
            }
        }
    }
}

// Get all users
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
if ($search) {
    $stmt = $db->prepare("SELECT * FROM users WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ? ORDER BY created_at DESC");
    $searchTerm = "%$search%";
    $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
} else {
    $stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC");
}
$stmt->execute();
$result = $stmt->get_result();
$users = $result->fetch_all(MYSQLI_ASSOC);

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้ - Romar</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #10ce30 0%, #000000 100%);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgb(0, 0, 0);
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

        .brand-icon {
            font-size: 2em;
        }

        .brand-name {
            font-size: 1.5em;
            font-weight: 700;
        }

        .brand-subtitle {
            color: rgba(255, 255, 255, 0.75);
            font-size: 1em;
            opacity: 0.8;
        }

        .sidebar-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: rgb(255, 255, 255);
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-nav a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .sidebar-nav li.active a {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left: 4px solid #000000;
        }

        .menu-section {
            padding: 20px 20px 10px;
            color: rgb(255, 255, 255);
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-title h1 {
            font-size: 1.8em;
            color: #000000;
            font-weight: 600;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            overflow: hidden;
        }

        .card-header {
            padding: 25px 30px;
            border-bottom: 1px solid #000000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        /* Search Bar */
        .search-bar {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 500px;
        }

        .search-input {
            flex: 1;
            padding: 12px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #1ae424;
            box-shadow: 0 0 0 3px rgba(16, 206, 48, 0.25);
        }

        /* Button */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10ce30 0%, #000000 100%);
            color: white;
            box-shadow: 0 4px 6px rgb(0, 0, 0);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgb(0, 3, 19);
        }

        .btn-secondary {
            background: #718096;
            color: white;
        }

        .btn-secondary:hover {
            background: #4a5568;
        }

        .btn-success {
            background: #12ca3a;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9em;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #000000;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 18px 20px;
            border-top: 1px solid #000000;
        }

        tbody tr {
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .badge-admin {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-staff {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-user {
            background: #dbeafe;
            color: #1e40af;
        }
        

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1.2em;
        }

        .btn-edit {
            background: #dbeafe;
            color: #169e2c;
        }

        .btn-edit:hover {
            background: #14b91c;
            color: white;
        }

        .btn-delete {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-delete:hover {
            background: #ef4444;
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
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5em;
            font-weight: 600;
            color: #000000;
        }

        .modal-close {
            font-size: 1.5em;
            cursor: pointer;
            color: #000000;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #ef4444;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #000000;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 3px rgb(255, 255, 255);
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-check input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
            animation: slideDown 0.3s;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -260px;
            }
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .card-header {
                flex-direction: column;
                align-items: stretch;
            }
            .search-bar {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">🏢</div>
                <div>
                    <div class="brand-name">Romar</div>
                    <div class="brand-subtitle">Dormitory</div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="dashboard.php">📊 Dashboard</a>
                    </li>

                    <li class="menu-section">การจัดการ</li>
                    <li class="active">
                        <a href="users-management.php">👥 จัดการผู้ใช้</a>
                    </li>
                    <li>
                        <a href="meeting-rooms.php">🏢 จัดการห้องประชุม</a>
                    </li>
                    <li>
                        <a href="documents.php">📄 จัดการเอกสาร</a>
                    </li>

                    <li class="menu-section">ฟีเจอร์</li>
                    <li>
                        <a href="room-booking.php">📅 จองห้องประชุม</a>
                    </li>
                    <li>
                        <a href="my-bookings.php">📋 รายการจองของฉัน</a>
                    </li>
                    
                  <li class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                        <a href="../modules/tickets.php">🎫 IT Tickets</a>
                    </li>

                    <li><a href="announcements.php">📢 ข่าวสาร</a>
                    </li>

                    <li class="menu-section">ระบบ</li>
                    <li>
                        <a href="settings.php">⚙️ ตั้งค่า</a>
                    </li>
                    <li>
                        <a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1>👥 การจัดการผู้ใช้งาน</h1>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> show" id="alertMessage">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <!-- Main Card -->
            <div class="card">
                <div class="card-header">
                    <div class="search-bar">
                        <form method="GET" style="display: flex; gap: 10px; flex: 1;">
                            <input type="text" name="search" class="search-input" placeholder="ค้นหา ชื่อ, อีเมล หรือยูสเซอร์เนม..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-secondary">🔍 ค้นหา</button>
                        </form>
                    </div>
                    <button class="btn btn-primary" onclick="openAddModal()">
                        ➕ เพิ่มผู้ใช้ใหม่
                    </button>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>บทบาท</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px;">
                                    <div style="color: #94a3b8;">
                                        <div style="font-size: 3em; margin-bottom: 10px;">👥</div>
                                        <p>ไม่พบผู้ใช้งาน</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong>#<?php echo $user['user_id']; ?></strong></td>
                                <td><code><?php echo htmlspecialchars($user['username']); ?></code></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $user['role'] === 'admin' ? 'admin' : 
                                            ($user['role'] === 'staff' ? 'staff' : 'user'); 
                                    ?>">
                                        <?php 
                                            if ($user['role'] === 'admin') {
                                                echo '<span style="display: inline-block; width: 8px; height: 8px; background: #92400e; border-radius: 50%; margin-right: 6px;"></span>Admin';
                                            } elseif ($user['role'] === 'staff') {
                                                echo '<span style="display: inline-block; width: 8px; height: 8px; background: #065f46; border-radius: 50%; margin-right: 6px;"></span>Staff';
                                            } else {
                                                echo '<span style="display: inline-block; width: 8px; height: 8px; background: #1e40af; border-radius: 50%; margin-right: 6px;"></span>User';
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['is_active'] ? '✅ ใช้งาน' : '❌ ปิดใช้งาน'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-icon btn-edit" onclick='openEditModal(<?php echo json_encode($user); ?>)' title="แก้ไข">
                                            ✏️
                                        </button>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <button class="btn-icon btn-delete" onclick="deleteUser(<?php echo $user['user_id']; ?>)" title="ลบ">
                                            🗑️
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
    </div>

    <!-- Add User Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">➕ เพิ่มผู้ใช้ใหม่</h2>
                <span class="modal-close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="addForm">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="add_username">Username *</label>
                        <input type="text" name="username" id="add_username" class="form-control" autocomplete="username" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_password">Password *</label>
                        <input type="password" name="password" id="add_password" class="form-control" autocomplete="new-password" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_full_name">ชื่อ-นามสกุล *</label>
                        <input type="text" name="full_name" id="add_full_name" class="form-control" autocomplete="name" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_email">Email *</label>
                        <input type="email" name="email" id="add_email" class="form-control" autocomplete="email" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_role">บทบาท *</label>
                        <select name="role" id="add_role" class="form-control" required>
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">✅ บันทึก</button>
                        <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('addModal')">❌ ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">✏️ แก้ไขผู้ใช้</h2>
                <span class="modal-close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_username">Username</label>
                        <input type="text" id="edit_username" class="form-control" autocomplete="username" readonly style="background: #f8fafc;">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_password">Password ใหม่ (เว้นว่างถ้าไม่เปลี่ยน)</label>
                        <input type="password" name="password" id="edit_password" class="form-control" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_full_name">ชื่อ-นามสกุล *</label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control" autocomplete="name" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_email">Email *</label>
                        <input type="email" name="email" id="edit_email" class="form-control" autocomplete="email" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_role">บทบาท *</label>
                        <select name="role" id="edit_role" class="form-control" required>
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                            <label for="edit_is_active">เปิดใช้งาน</label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">✅ บันทึก</button>
                        <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('editModal')">❌ ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Form -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="delete_user_id">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    </form>

    <script>
        // Auto hide alert
        setTimeout(() => {
            const alert = document.getElementById('alertMessage');
            if (alert) alert.classList.remove('show');
        }, 5000);

        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_is_active').checked = user.is_active == 1;
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function deleteUser(userId) {
            if (confirm('คุณแน่ใจหรือไม่ที่จะลบผู้ใช้นี้?')) {
                document.getElementById('delete_user_id').value = userId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>