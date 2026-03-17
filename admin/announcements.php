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

// ✅ Handle Create (Admin only)
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

    // ✅ Redirect ป้องกัน re-submit
    $redirect = 'announcements.php';
    if (!empty($_GET['priority'])) $redirect .= '?priority=' . urlencode($_GET['priority']);
    header('Location: ' . $redirect);
    exit;
}

// ✅ Handle Edit (Admin only)
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

    // ✅ Redirect ป้องกัน re-submit
    $redirect = 'announcements.php';
    if (!empty($_GET['priority'])) $redirect .= '?priority=' . urlencode($_GET['priority']);
    header('Location: ' . $redirect);
    exit;
}

// ✅ Handle Delete (Admin only)
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

    // ✅ Redirect ป้องกัน re-submit
    $redirect = 'announcements.php';
    if (!empty($_GET['priority'])) $redirect .= '?priority=' . urlencode($_GET['priority']);
    header('Location: ' . $redirect);
    exit;
}

// ✅ อ่าน message จาก session แล้วลบออก
$message     = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message     = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    unset($_SESSION['message'], $_SESSION['messageType']);
}

// Get announcements
$priority = isset($_GET['priority']) ? sanitize($_GET['priority']) : '';

if ($isAdmin) {
    // Admin sees all
    if ($priority) {
        $stmt = $db->prepare("SELECT a.*, u.full_name FROM announcements a LEFT JOIN users u ON a.published_by = u.user_id WHERE a.priority = ? ORDER BY a.publish_date DESC");
        $stmt->bind_param('s', $priority);
    } else {
        $stmt = $db->prepare("SELECT a.*, u.full_name FROM announcements a LEFT JOIN users u ON a.published_by = u.user_id ORDER BY a.publish_date DESC");
    }
} else {
    // Users see only active
    if ($priority) {
        $stmt = $db->prepare("SELECT a.*, u.full_name FROM announcements a LEFT JOIN users u ON a.published_by = u.user_id WHERE a.is_active = 1 AND a.priority = ? ORDER BY a.publish_date DESC");
        $stmt->bind_param('s', $priority);
    } else {
        $stmt = $db->prepare("SELECT a.*, u.full_name FROM announcements a LEFT JOIN users u ON a.published_by = u.user_id WHERE a.is_active = 1 ORDER BY a.publish_date DESC");
    }
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #065f159c ;
            color: #ffffff;
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

        .brand-icon { font-size: 2em; }
        .brand-name { font-size: 1.5em; font-weight: 700; }
        .brand-subtitle {
            color: #000000;
            font-size: 1em;
            opacity: 0.8;
        }

        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: rgb(255, 255, 255);
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-nav li.active a { background: rgba(255,255,255,0.15); color: white; border-left: 4px solid #000000; }
        .menu-section {
            padding: 20px 20px 10px;
            color: rgb(255, 255, 255);
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* Main Content */
        .main-content { flex: 1; margin-left: 260px; padding: 30px; }

        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h1 { font-size: 1.8em; color: #000000; font-weight: 600; }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10ce30 0%, #000000 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(255, 255, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(255, 255, 255, 0.4);
        }

        .btn-sm { padding: 8px 16px; font-size: 0.9em; }

        /* Filters */
        .filters {
            background: linear-gradient(135deg, #000000 0%, #10ce30 100%);
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgb(255, 255, 255);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-label {
            font-weight: 600;
            color: white;
            font-size: 1.1em;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid rgb(255, 255, 255);
            background: rgba(0, 0, 0, 0);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95em;
            color: white;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }

        .filter-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background: white;
            color: #000000;
            border-color: white;
            box-shadow: 0 4px 8px rgb(255, 252, 252);
        }

        /* Announcements Grid */
        .announcements-grid {
            display: grid;
            gap: 20px;
        }

        .announcement-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: all 0.3s;
            border-left: 4px solid;
        }

        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }

        .announcement-card.priority-normal { border-left-color: #10ce30; }
        .announcement-card.priority-important { border-left-color: #ecf01c; }
        .announcement-card.priority-urgent { border-left-color: #f80d0d; }

        .announcement-body {
            padding: 25px;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .announcement-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #000000;
            margin-bottom: 10px;
        }

        .announcement-content {
            color: #000000;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .announcement-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.85em;
            color: #000000;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .announcement-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .badge-priority-normal { background: #dbeafe; color: #10ce30; }
        .badge-priority-important { background: #fef3c7; color: #ecf01c; }
        .badge-priority-urgent { background: #fee2e2; color: #ec2d2d; }

        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #e5e7eb; color: #6b7280; }

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
        }

        .modal.active { display: flex; }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
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
            color: #2d3748;
        }

        .modal-close {
            font-size: 1.5em;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.2s;
        }

        .modal-close:hover { color: #ef4444; }
        .modal-body { padding: 30px; }

        .form-group { margin-bottom: 20px; }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #475569;
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
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show { display: block; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

        @media (max-width: 768px) {
            .sidebar { margin-left: -260px; }
            .main-content { margin-left: 0; padding: 15px; }
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
                    <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <a href="dashboard.php">📊 Dashboard</a>
                    </li>

                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <li class="menu-section">การจัดการ</li>
                    <li class="<?php echo $current_page == 'users-management.php' ? 'active' : ''; ?>">
                        <a href="users-management.php">👥 จัดการผู้ใช้</a>
                    </li>
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

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <h1>📢 ข่าวสาร / ประกาศ</h1>
                </div>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    ➕ สร้างประกาศใหม่
                </button>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> show">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters">
                <span class="filter-label">📌 ระดับความสำคัญ:</span>
                <a href="announcements.php" class="filter-btn <?php echo !$priority ? 'active' : ''; ?>">ทั้งหมด</a>
                <a href="?priority=normal" class="filter-btn <?php echo $priority === 'normal' ? 'active' : ''; ?>">ปกติ</a>
                <a href="?priority=important" class="filter-btn <?php echo $priority === 'important' ? 'active' : ''; ?>">สำคัญ</a>
                <a href="?priority=urgent" class="filter-btn <?php echo $priority === 'urgent' ? 'active' : ''; ?>">เร่งด่วน</a>
            </div>

            <!-- Announcements -->
            <div class="announcements-grid">
                <?php if (empty($announcements)): ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px;">
                    <div style="font-size: 4em; margin-bottom: 15px;">📢</div>
                    <h3>ยังไม่มีประกาศ</h3>
                    <p style="color: #94a3b8;">ตรวจสอบข่าวสารและประกาศใหม่ๆ ได้ที่นี่</p>
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
                            <div style="flex: 1;">
                                <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                <span class="badge badge-priority-<?php echo $announcement['priority']; ?>"><?php echo $priorityText; ?></span>
                                <?php if ($isAdmin): ?>
                                <span class="badge badge-<?php echo $announcement['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $announcement['is_active'] ? '✅ Active' : '❌ Inactive'; ?>
                                </span>
                                <?php endif; ?>
                            </div>
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
                            <button class="btn btn-sm" style="flex: 1; background: #3b82f6; color: white;" onclick='openEditModal(<?php echo json_encode($announcement, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP); ?>)'>
                                ✏️ แก้ไข
                            </button>
                            <button class="btn btn-sm" style="flex: 1; background: #ef4444; color: white;" onclick="deleteAnnouncement(<?php echo $announcement['announcement_id']; ?>)">
                                🗑️ ลบ
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
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
                        <input type="text" name="title" id="add_title" class="form-control" autocomplete="off" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_content">เนื้อหา *</label>
                        <textarea name="content" id="add_content" class="form-control" autocomplete="off" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="add_priority">ระดับความสำคัญ *</label>
                        <select name="priority" id="add_priority" class="form-control" autocomplete="off" required>
                            <option value="normal">🟢 ปกติ</option>
                            <option value="important">🟡 สำคัญ</option>
                            <option value="urgent">🔴 เร่งด่วน</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="add_is_active" value="1" checked style="width: 20px; height: 20px;">
                            <label for="add_is_active">เปิดใช้งาน</label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">✅ สร้างประกาศ</button>
                        <button type="button" class="btn" style="flex: 1; background: #718096; color: white;" onclick="closeModal('createModal')">❌ ยกเลิก</button>
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
                        <input type="text" name="title" id="edit_title" class="form-control" autocomplete="off" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_content">เนื้อหา *</label>
                        <textarea name="content" id="edit_content" class="form-control" autocomplete="off" required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="edit_priority">ระดับความสำคัญ *</label>
                        <select name="priority" id="edit_priority" class="form-control" autocomplete="off" required>
                            <option value="normal">🟢 ปกติ</option>
                            <option value="important">🟡 สำคัญ</option>
                            <option value="urgent">🔴 เร่งด่วน</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1" style="width: 20px; height: 20px;">
                            <label for="edit_is_active">เปิดใช้งาน</label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="submit" class="btn" style="flex: 1; background: #10b981; color: white;">✅ บันทึก</button>
                        <button type="button" class="btn" style="flex: 1; background: #718096; color: white;" onclick="closeModal('editModal')">❌ ยกเลิก</button>
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

        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) alert.classList.remove('show');
        }, 5000);
    </script>
</body>
</html>
