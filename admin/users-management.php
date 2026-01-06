<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// ตรวจสอบ login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// ตรวจสอบสิทธิ์ Admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$page_title = "จัดการผู้ใช้งาน";

// จัดการ Actions
$success_message = '';
$error_message = '';

// เพิ่มผู้ใช้ใหม่
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    if (!empty($username) && !empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role, is_active, created_at) VALUES (:username, :password, :full_name, :email, :role, 1, :created_at)");
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':password', $hashed, SQLITE3_TEXT);
            $stmt->bindValue(':full_name', $full_name, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            $stmt->execute();
            
            logActivity($_SESSION['user_id'], 'เพิ่มผู้ใช้', 'User Management', "เพิ่มผู้ใช้: {$username}");
            $success_message = "เพิ่มผู้ใช้ {$username} สำเร็จ!";
        } catch (Exception $e) {
            $error_message = "เกิดข้อผิดพลาด: Username อาจซ้ำ";
        }
    }
}

// รีเซ็ตรหัสผ่าน
if (isset($_POST['reset_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];
    
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE user_id = :id");
    $stmt->bindValue(':password', $hashed, SQLITE3_TEXT);
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    logActivity($_SESSION['user_id'], 'รีเซ็ตรหัสผ่าน', 'User Management', "รีเซ็ตรหัสผ่านผู้ใช้ ID: {$user_id}");
    $success_message = "รีเซ็ตรหัสผ่านสำเร็จ!";
}

// เปลี่ยนสถานะ
if (isset($_POST['toggle_status'])) {
    $user_id = (int)$_POST['user_id'];
    $current_status = (int)$_POST['current_status'];
    $new_status = $current_status ? 0 : 1;
    
    $stmt = $db->prepare("UPDATE users SET is_active = :status WHERE user_id = :id");
    $stmt->bindValue(':status', $new_status, SQLITE3_INTEGER);
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    $action = $new_status ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
    logActivity($_SESSION['user_id'], $action, 'User Management', "เปลี่ยนสถานะผู้ใช้ ID: {$user_id}");
    $success_message = "{$action}ผู้ใช้สำเร็จ!";
}

// ลบผู้ใช้
if (isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    // ตรวจสอบว่าไม่ใช่ตัวเอง
    if ($user_id != $_SESSION['user_id']) {
        // ตรวจสอบว่าไม่ใช่ admin คนสุดท้าย
        $result = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1");
        $admin_count = $result->fetchArray(SQLITE3_ASSOC)['count'];
        
        $stmt = $db->prepare("SELECT role FROM users WHERE user_id = :id");
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user['role'] === 'admin' && $admin_count <= 1) {
            $error_message = "ไม่สามารถลบ Admin คนสุดท้ายได้!";
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = :id");
            $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
            $stmt->execute();
            
            logActivity($_SESSION['user_id'], 'ลบผู้ใช้', 'User Management', "ลบผู้ใช้ ID: {$user_id}");
            $success_message = "ลบผู้ใช้สำเร็จ!";
        }
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$search = isset($_GET['search']) ? $_GET['search'] : '';
if ($search) {
    $stmt = $db->prepare("SELECT * FROM users WHERE username LIKE :search OR full_name LIKE :search OR email LIKE :search ORDER BY created_at DESC");
    $stmt->bindValue(':search', "%{$search}%", SQLITE3_TEXT);
    $result = $stmt->execute();
} else {
    $result = $db->query("SELECT * FROM users ORDER BY created_at DESC");
}

$users = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ระบบจัดการหอพัก</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* เพิ่ม CSS ที่จำเป็น */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 12px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 1.3em; color: #2c3e50; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 1.5em; cursor: pointer; color: #6c757d; }
        .modal-body { padding: 25px; }
        .modal-footer { padding: 15px 25px; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: #2c3e50; }
        .form-control { width: 100%; padding: 10px 15px; border: 1px solid #ced4da; border-radius: 8px; font-size: 0.95em; }
        .btn-sm { padding: 6px 12px; font-size: 0.85em; }
        .search-box { display: flex; gap: 10px; margin-bottom: 20px; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 500; }
        .badge-admin { background: #667eea; color: white; }
        .badge-staff { background: #6c757d; color: white; }
        .badge-active { background: #28a745; color: white; }
        .badge-inactive { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/../includes/header.php'; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">✅ <?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">❌ <?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">รายการผู้ใช้งาน (<?php echo count($users); ?> คน)</h2>
                <button class="btn btn-primary" onclick="openModal('addUserModal')">➕ เพิ่มผู้ใช้ใหม่</button>
            </div>

            <div class="search-box">
                <form method="GET" style="display: flex; gap: 10px; width: 100%;">
                    <input type="text" name="search" class="form-control" placeholder="ค้นหา Username, ชื่อ, Email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">🔍 ค้นหา</button>
                    <?php if ($search): ?>
                        <a href="users-management.php" class="btn btn-warning">✖️ ล้าง</a>
                    <?php endif; ?>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>Email</th>
                        <th>บทบาท</th>
                        <th>สถานะ</th>
                        <th>สร้างเมื่อ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="badge badge-<?php echo $user['role']; ?>"><?php echo $user['role'] === 'admin' ? '👨‍💼 Admin' : '👤 Staff'; ?></span></td>
                            <td><span class="badge badge-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $user['is_active'] ? '✅ Active' : '❌ Inactive'; ?></span></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-success btn-sm" onclick="openResetPasswordModal(<?php echo $user['user_id']; ?>)">🔑</button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                    <button type="submit" name="toggle_status" class="btn btn-warning btn-sm"><?php echo $user['is_active'] ? '🔒' : '🔓'; ?></button>
                                </form>
                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('แน่ใจหรือ?')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm">🗑️</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">➕ เพิ่มผู้ใช้ใหม่</h3>
                <button class="modal-close" onclick="closeModal('addUserModal')">×</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชื่อ-นามสกุล *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">บทบาท *</label>
                        <select name="role" class="form-control" required>
                            <option value="staff">Staff (พนักงาน)</option>
                            <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" onclick="closeModal('addUserModal')">ยกเลิก</button>
                    <button type="submit" name="add_user" class="btn btn-primary">เพิ่มผู้ใช้</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">🔑 รีเซ็ตรหัสผ่าน</h3>
                <button class="modal-close" onclick="closeModal('resetPasswordModal')">×</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    <div class="form-group">
                        <label class="form-label">รหัสผ่านใหม่ *</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" onclick="closeModal('resetPasswordModal')">ยกเลิก</button>
                    <button type="submit" name="reset_password" class="btn btn-primary">รีเซ็ตรหัสผ่าน</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) { document.getElementById(modalId).classList.add('active'); }
        function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
        function openResetPasswordModal(userId) {
            document.getElementById('reset_user_id').value = userId;
            openModal('resetPasswordModal');
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>