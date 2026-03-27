<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user    = getCurrentUser();
$db      = getDB();
$current_page = basename($_SERVER['PHP_SELF']);
csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['message'] = 'Invalid CSRF token.';
    $_SESSION['messageType'] = 'error';
    $redirect = 'userdocuments.php';
    $params = [];
    if (!empty($_GET['category'])) {
        $params[] = 'category=' . urlencode($_GET['category']);
    }
    if (!empty($_GET['search'])) {
        $params[] = 'search=' . urlencode($_GET['search']);
    }
    if ($params) {
        $redirect .= '?' . implode('&', $params);
    }
    header('Location: ' . $redirect);
    exit;
}

// ✅ Handle file upload → PRG pattern (Post-Redirect-Get)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $title       = sanitize($_POST['title']);
    $category    = sanitize($_POST['category']);
    $description = sanitize($_POST['description']);
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file         = $_FILES['file'];
        $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip'];
        $fileExt      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExt, $allowedTypes)) {
            $_SESSION['message']     = 'ประเภทไฟล์ไม่ถูกต้อง อนุญาตเฉพาะ: ' . implode(', ', $allowedTypes);
            $_SESSION['messageType'] = 'error';
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $_SESSION['message']     = 'ไฟล์ใหญ่เกินไป (สูงสุด 10MB)';
            $_SESSION['messageType'] = 'error';
        } else {
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $filePath = $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                $stmt       = $db->prepare("INSERT INTO documents (title, description, category, file_name, file_path, file_type, file_size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $fileSize   = $file['size'];
                $uploadedBy = $_SESSION['user_id'];
                $stmt->bind_param('ssssssis', $title, $description, $category, $file['name'], $filePath, $fileExt, $fileSize, $uploadedBy);
                
                if ($stmt->execute()) {
                    $_SESSION['message']     = 'อัพโหลดไฟล์สำเร็จ!';
                    $_SESSION['messageType'] = 'success';
                    logActivity($_SESSION['user_id'], 'อัพโหลดเอกสาร', 'Documents', "อัพโหลด: $title");
                } else {
                    unlink($uploadDir . $fileName);
                    $_SESSION['message']     = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
                    $_SESSION['messageType'] = 'error';
                }
            } else {
                $_SESSION['message']     = 'ไม่สามารถอัพโหลดไฟล์ได้';
                $_SESSION['messageType'] = 'error';
            }
        }
    } else {
        $_SESSION['message']     = 'กรุณาเลือกไฟล์';
        $_SESSION['messageType'] = 'error';
    }
    
    // ✅ Redirect ทันที → ป้องกัน re-submit เมื่อ refresh
    $redirect = 'userdocuments.php';
    if (!empty($_GET['category'])) $redirect .= '?category=' . urlencode($_GET['category']);
    header('Location: ' . $redirect);
    exit;
}

// ✅ Handle file deletion → PRG pattern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $documentId = (int)$_POST['document_id'];
    
    $stmt = $db->prepare("SELECT * FROM documents WHERE document_id = ?");
    $stmt->bind_param('i', $documentId);
    $stmt->execute();
    $document = $stmt->get_result()->fetch_assoc();
    
    if (!$document) {
        $_SESSION['message']     = 'ไม่พบเอกสารที่ต้องการลบ';
        $_SESSION['messageType'] = 'error';
    } elseif ($document['uploaded_by'] != $_SESSION['user_id'] && $user['role'] !== 'admin') {
        $_SESSION['message']     = 'คุณไม่มีสิทธิ์ลบเอกสารนี้ (ลบได้เฉพาะเอกสารของตัวเอง)';
        $_SESSION['messageType'] = 'error';
    } else {
        $filePath = '../uploads/' . $document['file_path'];
        if (file_exists($filePath)) unlink($filePath);
        
        $stmt = $db->prepare("DELETE FROM documents WHERE document_id = ?");
        $stmt->bind_param('i', $documentId);
        
        if ($stmt->execute()) {
            $_SESSION['message']     = 'ลบเอกสารสำเร็จ';
            $_SESSION['messageType'] = 'success';
            logActivity($_SESSION['user_id'], 'ลบเอกสาร', 'Documents', "ลบ: " . $document['title']);
        } else {
            $_SESSION['message']     = 'เกิดข้อผิดพลาดในการลบเอกสาร';
            $_SESSION['messageType'] = 'error';
        }
    }
    
    // ✅ Redirect ทันที → ป้องกัน re-submit เมื่อ refresh
    $redirect = 'userdocuments.php';
    $params   = [];
    if (!empty($_GET['category'])) $params[] = 'category=' . urlencode($_GET['category']);
    if (!empty($_GET['search']))   $params[] = 'search='   . urlencode($_GET['search']);
    if ($params) $redirect .= '?' . implode('&', $params);
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

// Search & filter
$search   = isset($_GET['search'])   ? sanitize($_GET['search'])   : '';
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';

// Build query (same logic as documents.php)
if ($search && $category) {
    $stmt = $db->prepare("SELECT d.*, u.full_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.user_id WHERE d.category = ? AND (d.title LIKE ? OR d.description LIKE ? OR d.file_name LIKE ?) ORDER BY d.created_at DESC");
    $searchTerm = "%$search%";
    $stmt->bind_param('ssss', $category, $searchTerm, $searchTerm, $searchTerm);
} elseif ($search) {
    $stmt = $db->prepare("SELECT d.*, u.full_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.user_id WHERE d.title LIKE ? OR d.description LIKE ? OR d.category LIKE ? OR d.file_name LIKE ? ORDER BY d.created_at DESC");
    $searchTerm = "%$search%";
    $stmt->bind_param('ssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm);
} elseif ($category) {
    $stmt = $db->prepare("SELECT d.*, u.full_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.user_id WHERE d.category = ? ORDER BY d.created_at DESC");
    $stmt->bind_param('s', $category);
} else {
    $stmt = $db->prepare("SELECT d.*, u.full_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.user_id ORDER BY d.created_at DESC");
}
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เอกสาร - Romar</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #065f159c;
            color: #ffffff;
        }

        .container { display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #10ce30 0%, #000000 100%);
            position: fixed;
            left: 0; top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgb(0,0,0);
            z-index: 1000;
        }
        .sidebar-brand {
            padding: 25px 20px;
            border-bottom: 1px solid rgb(255,255,255);
            display: flex; align-items: center; gap: 15px; color: white;
        }
        .brand-icon  { font-size: 2em; }
        .brand-name  { font-size: 1.5em; font-weight: 700; }
        .brand-subtitle {
            color: #000000;
            font-size: 1em;
            opacity: 0.8;
        }
        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 20px;
            color: rgb(255,255,255); text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-nav li.active a {
            background: rgba(255,255,255,0.15); color: white;
            border-left: 4px solid #000000;
        }
        .menu-section {
            padding: 20px 20px 10px;
            color: rgb(255,255,255);
            font-size: 0.75em; text-transform: uppercase;
            letter-spacing: 1px; font-weight: 600;
        }

        /* Main */
        .main-content { flex: 1; margin-left: 260px; padding: 30px; }

        /* Page Header */
        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0,0,0);
            margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .page-header h1 { font-size: 1.8em; color: #000000; font-weight: 600; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-avatar {
            width: 45px; height: 45px; border-radius: 50%;
            background: linear-gradient(135deg, #10ce30 0%, #000000 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 600; font-size: 1.2em;
        }
        .user-details { text-align: right; }
        .user-name { font-weight: 600; color: #e2d51a; }
        .user-role  { font-size: 0.85em; color: #000000; }

        /* Category Filters */
        .filters {
            background: linear-gradient(135deg, #10ce30 0%, #000000 100%);
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgb(255,255,255);
            margin-bottom: 20px;
            display: flex; gap: 15px; flex-wrap: wrap; align-items: center;
        }
        .filter-label {
            font-weight: 600; color: white; font-size: 1.1em;
            display: flex; align-items: center; gap: 8px;
        }
        .category-menu { display: flex; gap: 10px; flex-wrap: wrap; }
        .tab-item {
            display: inline-block;
            padding: 10px 20px;
            background-color: #f8f9fa;
            color: #000000;
            border-radius: 30px;
            text-decoration: none !important;
            transition: all 0.3s;
            border: 1px solid #fffdfd;
            font-size: 14px;
        }
        .tab-item:hover { background-color: #ffffff; }
        .tab-item.active {
            background-color: #029925;
            color: white;
            border-color: #000000;
            padding: 10px 25px;
        }

        /* Search */
        .search-section {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0,0,0);
            margin-bottom: 20px;
        }
        .search-row { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 280px; position: relative; }
        .search-icon {
            position: absolute; left: 15px; top: 50%;
            transform: translateY(-50%); font-size: 1.2em;
        }
        .search-input {
            width: 100%;
            padding: 12px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1em;
            font-family: 'Sarabun', sans-serif;
            color: #333;
            transition: all 0.3s;
        }
        .search-input:focus {
            outline: none; border-color: #10ce30;
            box-shadow: 0 0 0 3px rgba(16,206,48,0.15);
        }
        .search-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #10ce30 0%, #000000 100%);
            color: white; border: none; border-radius: 10px;
            font-size: 1em; font-family: 'Sarabun', sans-serif;
            font-weight: 500; cursor: pointer; transition: all 0.3s;
        }
        .search-btn:hover { transform: translateY(-1px); }
        .clear-btn {
            padding: 12px 18px;
            background: #f1f5f9; color: #555;
            border: 1px solid #ccc; border-radius: 10px;
            text-decoration: none;
            font-family: 'Sarabun', sans-serif; font-size: 1em;
        }
        .clear-btn:hover { background: #e2e8f0; }

        /* Result count */
        .result-count { color: #ffffffcc; font-size: 0.9em; margin-bottom: 15px; }

        /* Document Grid */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .doc-card {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0,0,0);
            overflow: hidden; transition: all 0.3s;
        }
        .doc-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgb(0,0,0); }
        .doc-icon {
            padding: 30px;
            background: linear-gradient(135deg, #097e26 0%, #000000 100%);
            text-align: center; font-size: 3em;
        }
        .doc-body { padding: 20px; }
        .doc-title { font-size: 1.1em; font-weight: 600; color: #000000; margin-bottom: 8px; }
        .doc-meta { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0; font-size: 0.85em; color: #000000; }
        .doc-description { font-size: 0.9em; color: #000000; margin-bottom: 15px; line-height: 1.5; }
        .doc-actions { display: flex; gap: 10px; padding-top: 15px; border-top: 1px solid #e2e8f0; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.8em; font-weight: 500; }
        .badge-category { background: #727272; color: #fffdfd; }

        /* Buttons */
        .btn {
            padding: 8px 16px; border: none; border-radius: 8px;
            font-size: 0.9em; font-weight: 500; cursor: pointer;
            transition: all 0.3s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            font-family: 'Sarabun', sans-serif;
            flex: 1; justify-content: center;
        }
        .btn-primary {
            background: linear-gradient(135deg, #20d63e 0%, #000000 100%);
            color: white; box-shadow: 0 2px 6px rgb(0,0,0);
        }
        .btn-primary:hover { transform: translateY(-1px); }
        .btn-secondary { background: #718096; color: white; }
        .btn-secondary:hover { background: #4a5568; }

        /* Modal */
        .modal {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999; align-items: center; justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white; border-radius: 12px;
            width: 90%; max-width: 1000px; max-height: 90vh;
        }
        .modal-header {
            padding: 20px 25px; border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-title { font-size: 1.4em; font-weight: 600; color: #000; }
        .modal-close { font-size: 1.5em; cursor: pointer; color: #666; transition: color 0.2s; }
        .modal-close:hover { color: #ef4444; }
        .modal-body { padding: 20px 25px; max-height: calc(90vh - 80px); overflow: auto; }

        /* Empty State */
        .empty-state {
            grid-column: 1/-1; background: white;
            border-radius: 12px; padding: 60px 30px;
            text-align: center; box-shadow: 0 2px 8px rgb(0,0,0);
        }
        .empty-state .empty-icon { font-size: 4em; margin-bottom: 15px; }
        .empty-state h3 { color: #333; margin-bottom: 8px; }
        .empty-state p  { color: #94a3b8; }

        @media (max-width: 768px) {
            .sidebar { margin-left: -260px; }
            .main-content { margin-left: 0; padding: 15px; }
            .documents-grid { grid-template-columns: 1fr; }
            .search-row { flex-direction: column; }
            .search-box { min-width: 100%; }
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
                <li><a href="dashboard.php">📊 Dashboard</a></li>

                <?php if ($user['role'] === 'admin'): ?>
                <li class="menu-section">การจัดการ</li>
                <li><a href="meeting-rooms.php">🏢 จัดการห้องประชุม</a></li>
                <li><a href="documents.php">📄 จัดการเอกสาร</a></li>
                <?php endif; ?>

                <li class="menu-section">ฟีเจอร์</li>
                <li><a href="room-booking.php">📅 จองห้องประชุม</a></li>
                <li><a href="announcements.php">📢 ข่าวสาร</a></li>
                <?php if ($user['role'] !== 'admin'): ?>
                <li class="active"><a href="userdocuments.php">📄 เอกสาร</a></li>
                <li class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                    <a href="../modules/tickets.php">🎫 IT Tickets</a>
                </li>
                <?php endif; ?>
                <li class="menu-section">ระบบ</li>
                <li><a href="settings.php">⚙️ ตั้งค่า</a></li>
                <li><a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a></li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <h1>📄 เอกสาร</h1>
            <div class="user-info">
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class="user-role"><?php echo $user['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน'; ?></div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(mb_substr($user['full_name'], 0, 1)); ?>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>" style="margin: 20px 0; padding: 15px 20px; border-radius: 12px; display: flex; align-items: center; gap: 12px; <?php echo $messageType === 'success' ? 'background: #d1fae5; color: #065f46; border: 1px solid #10b981;' : 'background: #fee2e2; color: #991b1b; border: 1px solid #ef4444;'; ?>">
            <span style="font-size: 1.5em;"><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?></span>
            <span><?php echo $message; ?></span>
        </div>
        <?php endif; ?>

        <!-- Category Tabs -->
        <div class="filters">
            <span class="filter-label"><span>📁</span><span>หมวดหมู่:</span></span>
            <div class="category-menu">
                <a href="userdocuments.php" class="tab-item <?php echo !$category ? 'active' : ''; ?>">📁 ทั้งหมด</a>
                <a href="userdocuments.php?category=คู่มือ" class="tab-item <?php echo $category === 'คู่มือ' ? 'active' : ''; ?>">📘 คู่มือ</a>
                <a href="userdocuments.php?category=แบบฟอร์ม" class="tab-item <?php echo $category === 'แบบฟอร์ม' ? 'active' : ''; ?>">📝 แบบฟอร์ม</a>
                <a href="userdocuments.php?category=รูปภาพ" class="tab-item <?php echo $category === 'รูปภาพ' ? 'active' : ''; ?>">🖼️ รูปภาพ</a>
                <a href="userdocuments.php?category=เอกสารทั่วไป" class="tab-item <?php echo $category === 'เอกสารทั่วไป' ? 'active' : ''; ?>">📗 เอกสารทั่วไป</a>
            </div>
        </div>

        <!-- Search -->
        <div class="search-section">
            <form method="GET" class="search-row">
                <?php if ($category): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <?php endif; ?>
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input
                        type="text" name="search" class="search-input"
                        placeholder="ค้นหาชื่อเอกสาร..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>
                <button type="submit" class="search-btn">ค้นหา</button>
                <?php if ($search || $category): ?>
                <a href="userdocuments.php" class="clear-btn">✕ ล้าง</a>
                <?php endif; ?>
                <button type="button" class="btn btn-primary" onclick="openUploadModal()" style="margin-left: 10px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    ➕ อัพโหลดเอกสาร
                </button>
            </form>
        </div>

        <!-- Result Count -->
        <div class="result-count">
            <?php if ($search || $category): ?>
                พบ <strong><?php echo count($documents); ?></strong> รายการ
                <?php if ($search) echo ' สำหรับ "<strong>' . htmlspecialchars($search) . '</strong>"'; ?>
                <?php if ($category) echo ' ในหมวด "<strong>' . htmlspecialchars($category) . '</strong>"'; ?>
            <?php else: ?>
                เอกสารทั้งหมด <strong><?php echo count($documents); ?></strong> รายการ
            <?php endif; ?>
        </div>

        <!-- Documents Grid -->
        <div class="documents-grid">
            <?php if (empty($documents)): ?>
            <div class="empty-state">
                <div class="empty-icon">📂</div>
                <h3>ยังไม่มีเอกสาร</h3>
                <p>ไม่พบเอกสารในหมวดหมู่นี้</p>
            </div>
            <?php else: ?>
            <?php foreach ($documents as $doc):
                $icons = [
                    'pdf'  => '📕', 'doc' => '📘', 'docx' => '📘',
                    'xls'  => '📗', 'xlsx' => '📗',
                    'ppt'  => '📙', 'pptx' => '📙',
                    'jpg'  => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️',
                    'zip'  => '📦',
                ];
                $icon = $icons[$doc['file_type']] ?? '📄';
            ?>
            <div class="doc-card">
                <div class="doc-icon"><?php echo $icon; ?></div>
                <div class="doc-body">
                    <div class="doc-title"><?php echo htmlspecialchars($doc['title']); ?></div>
                    <div class="doc-meta">
                        <span class="badge badge-category"><?php echo htmlspecialchars($doc['category']); ?></span>
                        <span>📦 <?php echo round($doc['file_size'] / 1024, 2); ?> KB</span>
                    </div>
                    <?php if ($doc['description']): ?>
                    <div class="doc-description"><?php echo htmlspecialchars($doc['description']); ?></div>
                    <?php endif; ?>
                    <div class="doc-meta">
                        <span>👤 <?php echo htmlspecialchars($doc['full_name'] ?? ''); ?></span>
                        <span>📅 <?php echo formatDateShort($doc['created_at']); ?></span>
                    </div>
                    <div class="doc-actions">
                        <button class="btn btn-primary"
                            onclick="previewDocument('<?php echo $doc['file_path']; ?>', '<?php echo $doc['file_type']; ?>', '<?php echo addslashes(htmlspecialchars($doc['title'])); ?>')">
                            👁️ ดูตัวอย่าง
                        </button>
                        <a href="../uploads/<?php echo $doc['file_path']; ?>" class="btn btn-secondary" download>
                            ⬇️ ดาวน์โหลด
                        </a>
                        <?php if ($doc['uploaded_by'] == $_SESSION['user_id'] || $user['role'] === 'admin'): ?>
                        <button class="btn btn-danger" 
                            onclick="deleteDocument(<?php echo $doc['document_id']; ?>, '<?php echo addslashes(htmlspecialchars($doc['title'])); ?>')"
                            style="background: linear-gradient(135deg, #ef4444, #dc2626); color: white;">
                            🗑️ ลบ
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /main-content -->
</div><!-- /container -->

<!-- Upload Modal -->
<div class="modal" id="uploadModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 class="modal-title">➕ อัพโหลดเอกสารใหม่</h2>
            <span class="modal-close" onclick="closeModal('uploadModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 20px;">
                <input type="hidden" name="action" value="upload">
                <?php echo csrf_input(); ?>
                
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label for="udoc_title" style="color: #1e293b; font-weight: 600;">ชื่อเอกสาร <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="title" id="udoc_title" required 
                        style="padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1em;"
                        placeholder="เช่น คู่มือการใช้งาน">
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label for="udoc_category" style="color: #1e293b; font-weight: 600;">หมวดหมู่ <span style="color: #ef4444;">*</span></label>
                    <select name="category" id="udoc_category" required 
                        style="padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1em;">
                        <option value="">-- เลือกหมวดหมู่ --</option>
                        <option value="คู่มือ">📘 คู่มือ</option>
                        <option value="แบบฟอร์ม">📝 แบบฟอร์ม</option>
                        <option value="รูปภาพ">🖼️ รูปภาพ</option>
                        <option value="เอกสารทั่วไป">📗 เอกสารทั่วไป</option>
                    </select>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label for="udoc_description" style="color: #1e293b; font-weight: 600;">คำอธิบาย</label>
                    <textarea name="description" id="udoc_description" rows="3" 
                        style="padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1em; resize: vertical;"
                        placeholder="รายละเอียดเอกสาร (ถ้ามี)"></textarea>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label for="udoc_file" style="color: #1e293b; font-weight: 600;">ไฟล์ <span style="color: #ef4444;">*</span></label>
                    <input type="file" name="file" id="udoc_file" required 
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.zip"
                        style="padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1em;">
                    <small style="color: #64748b;">
                        ไฟล์ที่รองรับ: PDF, Word, Excel, PowerPoint, รูปภาพ, ZIP (สูงสุด 10MB)
                    </small>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                    <button type="button" onclick="closeModal('uploadModal')" 
                        style="padding: 12px 24px; background: #e2e8f0; color: #475569; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        ยกเลิก
                    </button>
                    <button type="submit" 
                        style="padding: 12px 24px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                        ➕ อัพโหลด
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal" id="previewModal">
    <div class="modal-content" style="max-width:1000px;max-height:90vh;">
        <div class="modal-header">
            <h2 class="modal-title" id="previewTitle">👁️ ดูตัวอย่างเอกสาร</h2>
            <span class="modal-close" onclick="closeModal('previewModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div id="previewContent"></div>
        </div>
    </div>
</div>

<script>
    function openUploadModal() {
        document.getElementById('uploadModal').classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function deleteDocument(documentId, title) {
        if (confirm('ต้องการลบเอกสาร "' + title + '" ใช่หรือไม่?\n\n⚠️ การลบจะไม่สามารถกู้คืนได้')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="document_id" value="${documentId}">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function previewDocument(filePath, fileType, title) {
        const modal   = document.getElementById('previewModal');
        const content = document.getElementById('previewContent');
        document.getElementById('previewTitle').textContent = '👁️ ' + title;

        const fullPath = '../uploads/' + filePath;
        const safePath = String(fullPath).replace(/"/g, '%22').replace(/'/g, '%27').replace(/</g, '%3C').replace(/>/g, '%3E');
        const safeTitle = String(title ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
        const safeTypeLabel = String(fileType || '').replace(/[^a-z0-9]/gi, '').toUpperCase();
        content.innerHTML = '';

        if (['jpg','jpeg','png','gif'].includes(fileType.toLowerCase())) {
            content.innerHTML = `
                <div style="text-align:center;background:#f8fafc;padding:20px;border-radius:12px;">
                    <img src="${safePath}" alt="${safeTitle}" style="max-width:100%;max-height:70vh;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.1);">
                </div>`;
        } else if (fileType.toLowerCase() === 'pdf') {
            content.innerHTML = `<iframe src="${safePath}" style="width:100%;height:70vh;border:none;border-radius:8px;"></iframe>`;
        } else if (['doc','docx','xls','xlsx','ppt','pptx'].includes(fileType.toLowerCase())) {
            const icons = {doc:'📘',docx:'📘',xls:'📗',xlsx:'📗',ppt:'📙',pptx:'📙'};
            content.innerHTML = `
                <div style="text-align:center;padding:60px 20px;background:linear-gradient(135deg,#f8fafc,#e2e8f0);border-radius:12px;">
                    <div style="font-size:5em;margin-bottom:20px;">${icons[fileType.toLowerCase()]||'📄'}</div>
                    <h2 style="color:#1e293b;margin-bottom:15px;">${safeTitle}</h2>
                    <p style="color:#64748b;margin:20px 0;line-height:1.6;">
                        ไฟล์ Office ไม่สามารถแสดงตัวอย่างได้บน Localhost<br>
                        กรุณาดาวน์โหลดเพื่อเปิดด้วยโปรแกรม Office
                    </p>
                    <a href="${safePath}" download
                       style="display:inline-flex;align-items:center;gap:8px;padding:14px 28px;background:linear-gradient(135deg,#20d63e,#000);color:white;border-radius:8px;text-decoration:none;font-weight:600;">
                        ⬇️ ดาวน์โหลดไฟล์
                    </a>
                </div>`;
        } else {
            content.innerHTML = `
                <div style="text-align:center;padding:60px 20px;background:#f8fafc;border-radius:12px;">
                    <div style="font-size:4em;margin-bottom:20px;">📄</div>
                    <h3 style="color:#1e293b;margin-bottom:10px;">${safeTitle}</h3>
                    <p style="color:#64748b;">ไม่สามารถแสดงตัวอย่างไฟล์ประเภทนี้ได้</p>
                    <a href="${safePath}" download
                       style="display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:linear-gradient(135deg,#20d63e,#000);color:white;border-radius:8px;text-decoration:none;margin-top:20px;font-weight:600;">
                        ⬇️ ดาวน์โหลดไฟล์
                    </a>
                </div>`;
        }

        modal.classList.add('active');
    }

    window.onclick = function(e) {
        if (e.target.classList.contains('modal')) e.target.classList.remove('active');
    };
</script>
</body>
</html>
