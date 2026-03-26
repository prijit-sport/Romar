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
csrf_token();
$isValidCsrf = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? '')) {
    $isValidCsrf = false;
    $message = 'Invalid CSRF token.';
    $messageType = 'error';
}

// Handle Create User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create' && $isValidCsrf) {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update' && $isValidCsrf) {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $isValidCsrf) {
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
<?php
$pageTitle = 'ผู้ใช้งาน';
$activePage = 'users';
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <div class="breadcrumb-nav">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            </li>
            <li class="breadcrumb-separator">โ€บ</li>
            <li class="breadcrumb-item active">
                <i class="fas fa-users"></i> ผู้ใช้งาน
            </li>
        </ol>
    </div>

    <div class="page-header">
        <div>
            <h1><i class="fas fa-users"></i> ผู้ใช้งาน</h1>
            <p class="section-subtitle">จัดการบทบาทและสถานะของทีมงาน</p>
        </div>
        <div class="page-actions">
            <button class="btn btn-primary" id="createUserBtn">
                <i class="fas fa-plus"></i> สร้างผู้ใช้งาน
            </button>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <section class="section">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-users" style="color:white;"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                    <p>ผู้ใช้งานทั้งหมด</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                    <i class="fas fa-user" style="color:white;"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['active_count'] ?? 0); ?></h3>
                    <p>สถานะ Active</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #79c267, #16a34a);">
                    <i class="fas fa-user-tag" style="color:white;"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['staff_count'] ?? 0); ?></h3>
                    <p>Staff</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f97316, #ea580c);">
                    <i class="fas fa-user-shield" style="color:white;"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['admin_count'] ?? 0); ?></h3>
                    <p>Admin</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="filter-bar">
            <form method="GET">
                <div class="filter-grid">
                    <div class="input-icon-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="ค้นหาชื่อ, Username, Email หรือแผนก">
                    </div>
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
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section class="section">
        <div class="card">
            <div class="card-toolbar">
                <strong><i class="fas fa-users"></i> รายชื่อผู้ใช้งาน</strong>
                <div class="toolbar-actions">
                    <a href="?export=excel" class="btn btn-sm view-toggle btn-green">
                        <i class="fas fa-file-export"></i> Export
                    </a>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ชื่อ</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>แผนก</th>
                        <th>ตำแหน่ง</th>
                        <th>บทบาท</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="9" class="table-empty">
                            <i class="fas fa-user-slash"></i>
                            ยังไม่มีผู้ใช้งานในระบบ
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>#<?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['full_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['department'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['position'] ?? '-'); ?></td>
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
                                    <a href="userProfile.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-view" title="ดูข้อมูล">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn btn-sm btn-edit" onclick='editUser(<?php echo json_encode($user, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP); ?>)' title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-sm btn-delete" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>')" title="ลบ">
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
    </section>

    <!-- Create User Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> สร้างผู้ใช้งานใหม่</h2>
                <button class="close-btn" onclick="closeCreateModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <?php echo csrf_input(); ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_username">Username <span style="color:red;">*</span></label>
                        <input type="text" name="username" id="add_username" class="form-control" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="add_password">Password <span style="color:red;">*</span></label>
                        <input type="password" name="password" id="add_password" class="form-control" required autocomplete="new-password">
                    </div>
                </div>
                <div class="form-group">
                    <label for="add_full_name">ชื่อ-สกุล <span style="color:red;">*</span></label>
                    <input type="text" name="full_name" id="add_full_name" class="form-control" required autocomplete="name">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_email">Email</label>
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
                        <label for="add_role">บทบาท <span style="color:red;">*</span></label>
                        <select name="role" id="add_role" class="form-control" required>
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_status">สถานะ <span style="color:red;">*</span></label>
                        <select name="status" id="add_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                    <button type="button" class="btn" onclick="closeCreateModal()" style="background:#e2e8f0;">ยกเลิก</button>
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
                <?php echo csrf_input(); ?>
                <div class="form-group">
                    <label for="edit_full_name">ชื่อ-สกุล <span style="color:red;">*</span></label>
                    <input type="text" name="full_name" id="edit_full_name" class="form-control" required autocomplete="name">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_email">Email</label>
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
                        <input type="text" name="department" id="edit_department" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit_position">ตำแหน่ง</label>
                        <input type="text" name="position" id="edit_position" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_role">บทบาท <span style="color:red;">*</span></label>
                        <select name="role" id="edit_role" class="form-control" required>
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_status">สถานะ <span style="color:red;">*</span></label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                    <button type="button" class="btn" onclick="closeEditModal()" style="background:#e2e8f0;">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <form id="deleteForm" method="POST" class="visually-hidden">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="delete_user_id">
        <?php echo csrf_input(); ?>
    </form>
</main>

<?php $pageScripts = '<script src="' . BASE_URL . 'assets/js/users.js"></script>'; ?>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
