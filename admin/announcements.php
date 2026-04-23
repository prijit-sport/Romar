<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$isAdmin = $_SESSION['role'] === 'admin';
csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['message'] = 'Invalid CSRF token.';
    $_SESSION['messageType'] = 'error';

    $redirect = 'announcements.php';
    if (!empty($_GET['priority'])) {
        $redirect .= '?priority=' . urlencode($_GET['priority']);
    }
    header('Location: ' . $redirect);
    exit;
}

// Handle Create (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create' && $isAdmin) {
    $title    = sanitize($_POST['title']);
    $content  = sanitize($_POST['content']);
    $priority = sanitize($_POST['priority']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $db->prepare("INSERT INTO announcements (title, content, priority, is_active, published_by, publish_date) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('sssii', $title, $content, $priority, $isActive, $_SESSION['user_id']);

    if ($stmt->execute()) {
        $_SESSION['message']     = 'สร้างประกาศสำเร็จ!';
        $_SESSION['messageType'] = 'success';
        logActivity($_SESSION['user_id'], 'สร้างประกาศ', 'Announcements', "สร้าง: $title");
    } else {
        $_SESSION['message']     = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $_SESSION['messageType'] = 'error';
    }

    $redirect = 'announcements.php';
    if (!empty($_GET['priority'])) $redirect .= '?priority=' . urlencode($_GET['priority']);
    header('Location: ' . $redirect);
    exit;
}

// Handle Edit (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit' && $isAdmin) {
    $announcementId = (int)$_POST['announcement_id'];
    $title    = sanitize($_POST['title']);
    $content  = sanitize($_POST['content']);
    $priority = sanitize($_POST['priority']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $db->prepare("UPDATE announcements SET title = ?, content = ?, priority = ?, is_active = ?, updated_at = NOW() WHERE announcement_id = ?");
    $stmt->bind_param('sssii', $title, $content, $priority, $isActive, $announcementId);

    if ($stmt->execute()) {
        $_SESSION['message']     = 'แก้ไขประกาศสำเร็จ!';
        $_SESSION['messageType'] = 'success';
        logActivity($_SESSION['user_id'], 'แก้ไขประกาศ', 'Announcements', "แก้ไข ID: $announcementId");
    } else {
        $_SESSION['message']     = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $_SESSION['messageType'] = 'error';
    }

    $redirect = 'announcements.php';
    if (!empty($_GET['priority'])) $redirect .= '?priority=' . urlencode($_GET['priority']);
    header('Location: ' . $redirect);
    exit;
}

// Handle Delete (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $isAdmin) {
    $announcementId = (int)$_POST['announcement_id'];

    $stmt = $db->prepare("DELETE FROM announcements WHERE announcement_id = ?");
    $stmt->bind_param('i', $announcementId);

    if ($stmt->execute()) {
        $_SESSION['message']     = 'ลบประกาศสำเร็จ!';
        $_SESSION['messageType'] = 'success';
        logActivity($_SESSION['user_id'], 'ลบประกาศ', 'Announcements', "ลบ ID: $announcementId");
    } else {
        $_SESSION['message']     = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $_SESSION['messageType'] = 'error';
    }

    $redirect = 'announcements.php';
    if (!empty($_GET['priority'])) $redirect .= '?priority=' . urlencode($_GET['priority']);
    header('Location: ' . $redirect);
    exit;
}

// Read message from session
$message     = $_SESSION['message'] ?? '';
$messageType = $_SESSION['messageType'] ?? '';
if (isset($_SESSION['message'])) {
    unset($_SESSION['message'], $_SESSION['messageType']);
}

// Get announcements
$priority = isset($_GET['priority']) ? sanitize($_GET['priority']) : '';

if ($isAdmin) {
    $whereClause = $priority ? 'WHERE a.priority = ?' : '';
    $stmt = $db->prepare("SELECT a.*, u.full_name FROM announcements a LEFT JOIN users u ON a.published_by = u.user_id $whereClause ORDER BY a.publish_date DESC");
    if ($priority) $stmt->bind_param('s', $priority);
} else {
    $whereClause = $priority ? 'WHERE a.is_active = 1 AND a.priority = ?' : 'WHERE a.is_active = 1';
    $stmt = $db->prepare("SELECT a.*, u.full_name FROM announcements a LEFT JOIN users u ON a.published_by = u.user_id $whereClause ORDER BY a.publish_date DESC");
    if ($priority) $stmt->bind_param('s', $priority);
}
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข่าวสาร - Romar</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/admin-theme.css">
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

            <div class="nav-wrapper">
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <a href="dashboard.php">📊 Dashboard</a>
                    </li>

                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <li class="menu-section">การจัดการ</li>
                    <li class="<?php echo $current_page == 'meeting-rooms.php' ? 'active' : ''; ?>">
                        <a href="meeting-rooms.php">🏢 จัดการห้องประชุม</a>
                    </li>
                    <li class="<?php echo $current_page == 'documents.php' ? 'active' : ''; ?>">
                        <a href="documents.php">📄 จัดการเอกสาร</a>
                    </li>
                    <?php endif; ?>

                    <li class="menu-section">ฟีเจอร์</li>
                    <li class="<?php echo $current_page == 'room-booking.php' ? 'active' : ''; ?>">
                        <a href="room-booking.php">📅 จองห้องประชุม</a>
                    </li>
                    <li class="active">
                        <a href="announcements.php">📢 ข่าวสาร</a>
                    </li>
                    <li class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                        <a href="../modules/tickets.php">🎫 IT Tickets</a>
                    </li>
                    <?php if ($currentUser['role'] !== 'admin'): ?>
                    <li class="<?php echo $current_page == 'userdocuments.php' ? 'active' : ''; ?>">
                        <a href="userdocuments.php">📄 เอกสาร</a>
                    </li>
                    <?php endif; ?>
                    <li class="menu-section">ระบบ</li>
                    <li class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                        <a href="settings.php">⚙️ ตั้งค่า</a>
                    </li>
                    <li>
                        <a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a>
                    </li>
                </ul>
            </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-wrapper" style="max-width: 1200px; margin: 0 auto; width: 100%;">
                <!-- Single Page Header -->
                <div class="page-header">
                    <div class="page-title-block">
                        <div class="page-icon">📢</div>
                        <div>
                            <h1>ข่าวสาร</h1>
                            <p class="page-description">ติดตามข่าวสาร ประกาศ และอัปเดตล่าสุด</p>
                        </div>
                    </div>
                    <?php if ($isAdmin): ?>
                    <button class="btn btn-primary" onclick="openCreateModal()">➕ สร้างประกาศใหม่</button>
                    <?php endif; ?>
                    <div class="user-info">
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                            <div class="user-role"><?php echo $currentUser['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน'; ?></div>
                        </div>
                        <div class="user-avatar"><?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?></div>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> show">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="filters" style="margin-bottom: 1.5rem;">
                    <span class="filter-label">📌 ระดับความสำคัญ:</span>
                    <a href="announcements.php" class="filter-btn <?php echo !$priority ? 'active' : ''; ?>">ทั้งหมด</a>
                    <a href="?priority=normal" class="filter-btn <?php echo $priority === 'normal' ? 'active' : ''; ?>">ปกติ</a>
                    <a href="?priority=important" class="filter-btn <?php echo $priority === 'important' ? 'active' : ''; ?>">สำคัญ</a>
                    <a href="?priority=urgent" class="filter-btn <?php echo $priority === 'urgent' ? 'active' : ''; ?>">เร่งด่วน</a>
                </div>

                <!-- Announcements Grid -->
                <div class="announcements-grid">
                    <?php if (empty($announcements)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📢</div>
                        <h3>ยังไม่มีประกาศ</h3>
                        <p>ตรวจสอบข่าวสารและประกาศใหม่ๆ ได้ที่นี่</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $announcement): 
                            $priorityText = [
                                'normal' => '🟢 ปกติ',
                                'important' => '🟡 สำคัญ',
                                'urgent' => '🔴 เร่งด่วน'
                            ][$announcement['priority']] ?? $announcement['priority'];
                        ?>
                        <div class="announcement-card priority-<?php echo $announcement['priority']; ?>">
                            <div class="announcement-body">
                                <div class="announcement-header">
                                    <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                    <span class="badge badge-priority-<?php echo $announcement['priority']; ?>"><?php echo $priorityText; ?></span>
                                    <?php if ($isAdmin): ?>
                                    <span class="badge badge-<?php echo $announcement['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $announcement['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="announcement-content">
                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                </div>
                                <div class="announcement-meta">
                                    <span>👤 <?php echo htmlspecialchars($announcement['full_name']); ?></span>
                                    <span>📅 <?php echo formatDateShort($announcement['publish_date']); ?></span>
                                </div>
                                <?php if ($isAdmin): ?>
        <div class="announcement-actions">
            <button class="btn btn-sm btn-primary" onclick='openEditModal(<?php echo json_encode($announcement, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP); ?>)'>✏️ แก้ไข</button>

            <button class="btn btn-sm" onclick="deleteAnnouncement(<?php echo $announcement['announcement_id']; ?>)" style="background: #ef4444; color: white;">🗑️ ลบ</button>
        </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- Create Modal -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">➕ สร้างประกาศใหม่</h2>
                <span class="modal-close" onclick="closeModal('createModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <?php echo csrf_input(); ?>
                    
                    <div class="form-group">
                        <label class="form-label" for="add_title">หัวข้อ *</label>
                        <input type="text" name="title" id="add_title" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_content">เนื้อหา *</label>
                        <textarea name="content" id="add_content" class="form-control" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_priority">ระดับความสำคัญ *</label>
                        <select name="priority" id="add_priority" class="form-control" required>
                            <option value="normal">🟢 ปกติ</option>
                            <option value="important">🟡 สำคัญ</option>
                            <option value="urgent">🔴 เร่งด่วน</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="add_is_active" value="1" checked>
                            <label for="add_is_active">เปิดใช้งาน</label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">✅ สร้าง</button>
                        <button type="button" class="btn" onclick="closeModal('createModal')" style="flex: 1; background: #6b7280; color: white;">❌ ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">✏️ แก้ไขประกาศ</h2>
                <span class="modal-close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="announcement_id" id="edit_announcement_id">
                    <?php echo csrf_input(); ?>
                    
                    <div class="form-group">
                        <label class="form-label" for="edit_title">หัวข้อ *</label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_content">เนื้อหา *</label>
                        <textarea name="content" id="edit_content" class="form-control" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_priority">ระดับความสำคัญ *</label>
                        <select name="priority" id="edit_priority" class="form-control" required>
                            <option value="normal">🟢 ปกติ</option>
                            <option value="important">🟡 สำคัญ</option>
                            <option value="urgent">🔴 เร่งด่วน</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                            <label for="edit_is_active">เปิดใช้งาน</label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">✅ บันทึก</button>
                        <button type="button" class="btn" onclick="closeModal('editModal')" style="flex: 1; background: #6b7280; color: white;">❌ ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="announcement_id" id="delete_announcement_id">
        <?php echo csrf_input(); ?>
    </form>

    <?php include '../admin/view-announcement-modal.html'; ?>
    <?php endif; ?>

    <script>
        <?php if ($isAdmin): ?>
        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }

        function openEditModal(announcement) {
            document.getElementById('edit_announcement_id').value = announcement.announcement_id;
            document.getElementById('edit_title').value = announcement.title;
            document.getElementById('edit_content').value = announcement.content;
            document.getElementById('edit_priority').value = announcement.priority;
            document.getElementById('edit_is_active').checked = announcement.is_active == 1;
            document.getElementById('editModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function deleteAnnouncement(id) {
            if (confirm('คุณแน่ใจหรือไม่ที่จะลบประกาศนี้?')) {
                document.getElementById('delete_announcement_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
        <?php endif; ?>

        // Auto-hide alert
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.classList.remove('show');
            }
        }, 5000);
    </script>
</body>
