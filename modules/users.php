<?php
$pageTitle = 'จัดการผู้ใช้ - Romar';
$activePage = 'users';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<main class="main-content">
<?php
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

// Handle Add/Edit/Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid CSRF token';
        $messageType = 'error';
    } else {
        if ($_POST['action'] === 'add') {
            $username = sanitize($_POST['username']);
            $passwordRaw = $_POST['password'] ?? '';
            $fullName = sanitize($_POST['full_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone'] ?? '');
            $department = sanitize($_POST['department'] ?? '');
            $position = sanitize($_POST['position'] ?? '');
            $role = sanitize($_POST['role']); 

            if ($username === '' || $passwordRaw === '' || $fullName === '' || $email === '' || $role === '') {
                $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
                $messageType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'รูปแบบ Email ไม่ถูกต้อง';
                $messageType = 'error';
            } elseif (!in_array($role, ['user', 'staff', 'admin', 'it_support'], true)) {
                $message = 'บทบาทไม่ถูกต้อง';
                $messageType = 'error';
            } else {
                $checkStmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $checkStmt->bind_param('ss', $username, $email);
                $checkStmt->execute();
                $exists = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();

                if ($exists) {
                    $message = 'Username หรือ Email นี้ถูกใช้งานแล้ว';
                    $messageType = 'error';
                } else {
                    $password = password_hash($passwordRaw, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, phone, department, position, role, status, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, NOW())");
                    if (!$stmt || !$stmt->bind_param('ssssssss', $username, $password, $fullName, $email, $phone, $department, $position, $role)) {
                        $message = 'SQL Prepare/Bind failed: ' . $db->error;
                        $messageType = 'error';
                        error_log("Users add prepare/bind error: " . $db->error);
                    } elseif ($stmt->execute()) {
                        $message = 'เพิ่มผู้ใช้สำเร็จ!';
                        $messageType = 'success';
                        logActivity($_SESSION['user_id'], 'เพิ่มผู้ใช้ใหม่', 'Users', "เพิ่มผู้ใช้: $username");
                    } else {
                        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
                        $messageType = 'error';
                    }
                    $stmt->close();
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $username = sanitize($_POST['username']);
            $fullName = sanitize($_POST['full_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone'] ?? '');
            $department = sanitize($_POST['department'] ?? '');
            $position = sanitize($_POST['position'] ?? '');
            $role = sanitize($_POST['role']);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $newPassword = $_POST['password'] ?? '';

            if ($userId <= 0 || $username === '' || $fullName === '' || $email === '' || $role === '') {
                $message = 'กรุณากรอกข้อมูลให้ครบถ้วน';
                $messageType = 'error';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'รูปแบบ Email ไม่ถูกต้อง';
                $messageType = 'error';
            } elseif (!in_array($role, ['user', 'staff', 'admin', 'it_support'], true)) {
                $message = 'บทบาทไม่ถูกต้อง';
                $messageType = 'error';
            } else {
                $checkStmt = $db->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ? LIMIT 1");
                $checkStmt->bind_param('ssi', $username, $email, $userId);
                $checkStmt->execute();
                $exists = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();

                if ($exists) {
                    $message = 'Username หรือ Email นี้ถูกใช้งานแล้ว';
                    $messageType = 'error';
                } else {
                    $updateFields = "username = ?, full_name = ?, email = ?, phone = ?, department = ?, position = ?, role = ?, status = 'active', is_active = ?";
                    $types = 'ssssssssi';
                    $params = [$username, $fullName, $email, $phone, $department, $position, $role, $isActive];
                    
                    if ($newPassword !== '') {
                        $password = password_hash($newPassword, PASSWORD_DEFAULT);
                        $updateFields .= ", password = ?";
                        $types .= 's';
                        $params[] = $password;
                    }
                    $params[] = $userId;
                    
                    $stmt = $db->prepare("UPDATE users SET $updateFields WHERE user_id = ?");
                    if (!$stmt || !$stmt->bind_param($types, ...$params)) {
                        $message = 'SQL Prepare/Bind failed: ' . $db->error;
                        $messageType = 'error';
                        error_log("Users edit prepare/bind error: " . $db->error);
                    } elseif ($stmt->execute()) {
                        $message = 'แก้ไขผู้ใช้สำเร็จ!';
                        $messageType = 'success';
logActivity($_SESSION['user_id'], 'แก้ไขข้อมูลผู้ใช้', 'Users', "แก้ไขผู้ใช้: $username (ID: $userId)");
if (isset($_GET['profile_id']) && (int)$_GET['profile_id'] === $userId) {
    header('Location: userProfile.php?id=' . $userId);
    exit;
}
                    } else {
                        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
                        $messageType = 'error';
                    }
                    $stmt->close();
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($userId === (int)$_SESSION['user_id']) {
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
                $stmt->close();
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
$stmt->close();

$currentUser = getCurrentUser();
?>

    <div class="page-header">
        <div class="page-title">
            <h1>👥 การจัดการผู้ใช้งาน</h1>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> show" id="alertMessage">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="search-bar">
                <form method="GET" style="display: flex; gap: 10px; flex: 1;">
                    <input type="text" name="search" class="search-input" placeholder="ค้นหา ชื่อ, อีเมล หรือยูสเซอร์เนม..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-secondary">🔍 ค้นหา</button>
                </form>
            </div>
            <button type="button" class="btn btn-primary" id="addUserBtn">
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
                    <?php $rank = 1; foreach ($users as $user): ?>
                    <tr>
                        <td><strong>#<?php echo $rank++; ?></strong></td>
                        <td><code><?php echo htmlspecialchars($user['username']); ?></code></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'admin' : ($user['role'] === 'staff' ? 'staff' : 'user'); ?>">
                                <?php
                                    if ($user['role'] === 'admin') {
                                        echo '<span class="badge-dot badge-dot-admin"></span>Admin';
                                            } elseif ($user['role'] === 'staff') {
                                                echo '<span class="badge-dot badge-dot-staff"></span>Staff';
                                            } else {
                                                echo '<span class="badge-dot badge-dot-user"></span>User';
                                            }
                                ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo !empty($user['is_active']) ? 'active' : 'inactive'; ?>">
                                <?php echo !empty($user['is_active']) ? '✅ ใช้งาน' : '❌ ปิดใช้งาน'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button
                                    type="button"
                                    class="btn-icon btn-profile"
                                    title="ดูโปรไฟล์"
                                    onclick="viewProfile(<?php echo (int)$user['user_id']; ?>)"
                                >
                                    👤
                                </button>
                                <button
                                    type="button"
                                    class="btn-icon btn-edit"
                                    title="แก้ไข"
                                    data-action="edit-user"
                                    data-user='<?php echo json_encode($user, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP); ?>'
                                >
                                    ✏️
                                </button>
                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                <button
                                    type="button"
                                    class="btn-icon btn-delete"
                                    title="ลบ"
                                    onclick="deleteUser(<?php echo (int)$user['user_id']; ?>, '<?php echo htmlspecialchars($user['full_name'] ?: $user['username'], ENT_QUOTES); ?>')"
                                >
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

    <!-- Add Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">➕ เพิ่มผู้ใช้ใหม่</h2>
                <button type="button" class="modal-close" data-close-modal="addModal">&times;</button>
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
                        <label class="form-label" for="add_phone">Phone</label>
                        <input type="tel" name="phone" id="add_phone" class="form-control" autocomplete="tel">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_department">แผนก</label>
                        <input type="text" name="department" id="add_department" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_position">ตำแหน่ง</label>
                        <input type="text" name="position" id="add_position" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_role">บทบาท *</label>
                        <select name="role" id="add_role" class="form-control" required>
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                            <option value="it_support">IT Support</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">✅ บันทึก</button>
                        <button type="button" class="btn btn-secondary" style="flex: 1;" data-close-modal="addModal">❌ ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">✏️ แก้ไขผู้ใช้</h2>
                <button type="button" class="modal-close" data-close-modal="editModal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <div class="form-group">
                        <label class="form-label" for="edit_username">Username *</label>
                        <input type="text" name="username" id="edit_username" class="form-control" autocomplete="username" required>
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
                        <label class="form-label" for="edit_phone">Phone</label>
                        <input type="tel" name="phone" id="edit_phone" class="form-control" autocomplete="tel">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_department">แผนก</label>
                        <input type="text" name="department" id="edit_department" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_position">ตำแหน่ง</label>
                        <input type="text" name="position" id="edit_position" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_role">บทบาท *</label>
                        <select name="role" id="edit_role" class="form-control" required>
                            <option value="user">User</option>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                            <option value="it_support">IT Support</option>
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
                        <button type="button" class="btn btn-secondary" style="flex: 1;" data-close-modal="editModal">❌ ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="delete_user_id">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    </form>

    <script>
        (function () {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            const addForm = document.getElementById('addForm');
            const editForm = document.getElementById('editForm');
            const deleteForm = document.getElementById('deleteForm');
            const addUserBtn = document.getElementById('addUserBtn');

            function setBodyScroll(blocked) {
                document.body.style.overflow = blocked ? 'hidden' : '';
            }

            function getActiveModal() {
                return document.querySelector('.modal.active');
            }

            function openModal(modalId) {
                const modal = document.getElementById(modalId);
                if (!modal) {
                    return;
                }

                const activeModal = getActiveModal();
                if (activeModal && activeModal !== modal) {
                    activeModal.classList.remove('active');
                }

                modal.classList.add('active');
                setBodyScroll(true);
            }

            function closeModal(modalId) {
                const modal = modalId ? document.getElementById(modalId) : getActiveModal();
                if (!modal) {
                    return;
                }

                modal.classList.remove('active');

                if (!getActiveModal()) {
                    setBodyScroll(false);
                }
            }

            function clearInvalidState(form) {
                if (!form) {
                    return;
                }

                form.querySelectorAll('.is-invalid').forEach(function (field) {
                    field.classList.remove('is-invalid');
                });
            }

            function resetAddForm() {
                if (!addForm) {
                    return;
                }

                addForm.reset();
                clearInvalidState(addForm);
            }

            function resetEditForm() {
                if (!editForm) {
                    return;
                }

                editForm.reset();
                clearInvalidState(editForm);
            }

            function populateEditForm(user) {
                if (!user || !editForm) {
                    return;
                }

                document.getElementById('edit_user_id').value = user.user_id || '';
                document.getElementById('edit_username').value = user.username || '';
                document.getElementById('edit_password').value = '';
                document.getElementById('edit_full_name').value = user.full_name || '';
                document.getElementById('edit_email').value = user.email || '';
                document.getElementById('edit_phone').value = user.phone || '';
                document.getElementById('edit_department').value = user.department || '';
                document.getElementById('edit_position').value = user.position || '';
                document.getElementById('edit_role').value = user.role || 'user';
                document.getElementById('edit_is_active').checked = Number(user.is_active) === 1;
            }

            function validateForm(form) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(function (field) {
                    const value = typeof field.value === 'string' ? field.value.trim() : field.value;
                    if (!value) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });

                return isValid;
            }

            if (addUserBtn) {
                addUserBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    resetAddForm();
                    openModal('addModal');
                });
            }

            document.addEventListener('click', function (event) {
                const editButton = event.target.closest('[data-action="edit-user"]');
                if (editButton) {
                    event.preventDefault();
                    resetEditForm();

                    const rawUser = editButton.getAttribute('data-user');
                    if (!rawUser) {
                        return;
                    }

                    try {
                        const user = JSON.parse(rawUser);
                        populateEditForm(user);
                        openModal('editModal');
                    } catch (error) {
                        console.error('Cannot parse user data:', error);
                    }
                    return;
                }

                const closeButton = event.target.closest('[data-close-modal]');
                if (closeButton) {
                    event.preventDefault();
                    closeModal(closeButton.getAttribute('data-close-modal'));
                    return;
                }

                if (event.target.classList.contains('modal')) {
                    closeModal(event.target.id);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });

            [addForm, editForm].filter(Boolean).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!validateForm(form)) {
                        event.preventDefault();
                    }
                });
            });

            document.querySelectorAll('#addForm [required], #editForm [required]').forEach(function (field) {
                field.addEventListener('input', function () {
                    if (field.value.trim()) {
                        field.classList.remove('is-invalid');
                    }
                });
            });

            window.deleteUser = function (userId, fullName) {
                if (!deleteForm || !userId) {
                    return;
                }

                const message = fullName
                    ? 'คุณแน่ใจหรือไม่ที่จะลบผู้ใช้ "' + fullName + '" ?'
                    : 'คุณแน่ใจหรือไม่ที่จะลบผู้ใช้นี้?';

                if (window.confirm(message)) {
                    document.getElementById('delete_user_id').value = userId;
                    deleteForm.submit();
                }
            };

            window.viewProfile = function(userId) {
                window.location.href = `userProfile.php?id=${userId}`;
            };

            setTimeout(function () {
                const alert = document.getElementById('alertMessage');
                if (alert) {
                    alert.classList.remove('show');
                }
            }, 5000);

        })();

    </script>
</main>

<?php require_once '../includes/footer.php'; ?>
