<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);

// Check login and admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$message = '';
$messageType = '';
csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['message'] = 'Invalid CSRF token.';
    $_SESSION['messageType'] = 'error';
    $redirectUrl = 'documents.php';
    if (isset($_GET['category']) && $_GET['category'] !== '') {
        $redirectUrl .= '?category=' . urlencode($_GET['category']);
    }
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $redirectUrl .= (strpos($redirectUrl, '?') !== false ? '&' : '?') . 'search=' . urlencode($_GET['search']);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// Create uploads directory if not exists
$uploadDir = '../uploads/documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $title = sanitize($_POST['title']);
    $category = sanitize($_POST['category']);
    $description = sanitize($_POST['description']);
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedTypes)) {
            $_SESSION['message'] = 'ประเภทไฟล์ไม่ถูกต้อง!';
            $_SESSION['messageType'] = 'error';
        } elseif ($file['size'] > 10485760) { // 10MB
            $_SESSION['message'] = 'ไฟล์ใหญ่เกิน 10MB!';
            $_SESSION['messageType'] = 'error';
        } else {
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $stmt = $db->prepare("INSERT INTO documents (title, category, description, file_name, file_path, file_size, file_type, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $relativePath = 'documents/' . $fileName;
                $stmt->bind_param('sssssisi', $title, $category, $description, $fileName, $relativePath, $file['size'], $extension, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = 'อัปโหลดเอกสารสำเร็จ!';
                    $_SESSION['messageType'] = 'success';
                    logActivity($_SESSION['user_id'], 'อัปโหลดเอกสาร', 'Documents', "อัปโหลด: $title");
                } else {
                    unlink($filePath);
                    $_SESSION['message'] = 'เกิดข้อผิดพลาด: ' . $stmt->error;
                    $_SESSION['messageType'] = 'error';
                }
            }
        }
    } else {
        $_SESSION['message'] = 'กรุณาเลือกไฟล์!';
        $_SESSION['messageType'] = 'error';
    }
    
    // ✅ Redirect เพื่อป้องกัน duplicate submission
    header('Location: documents.php' . ($category ? '?category=' . urlencode($category) : ''));
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $docId = (int)$_POST['document_id'];
    
    $stmt = $db->prepare("SELECT file_path FROM documents WHERE document_id = ?");
    $stmt->bind_param('i', $docId);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    
    if ($doc) {
        $fullPath = '../uploads/' . $doc['file_path'];
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        
        $deleteStmt = $db->prepare("DELETE FROM documents WHERE document_id = ?");
        $deleteStmt->bind_param('i', $docId);
        
        if ($deleteStmt->execute()) {
            $_SESSION['message'] = 'ลบเอกสารสำเร็จ!';
            $_SESSION['messageType'] = 'success';
            logActivity($_SESSION['user_id'], 'ลบเอกสาร', 'Documents', "ลบเอกสาร ID: $docId");
        }
    }
    
    // ✅ Redirect เพื่อป้องกัน duplicate submission
    $redirectUrl = 'documents.php';
    if (isset($_GET['category'])) {
        $redirectUrl .= '?category=' . urlencode($_GET['category']);
    }
    if (isset($_GET['search'])) {
        $redirectUrl .= (strpos($redirectUrl, '?') !== false ? '&' : '?') . 'search=' . urlencode($_GET['search']);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// ✅ แสดง message จาก session แล้วลบออก
$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    unset($_SESSION['message'], $_SESSION['messageType']);
}

// Get all documents
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// --- นำโค้ด Logic ที่คุณส่งมาวางตรงนี้ ---
if ($search && $category) {
    // ค้นหาภายใต้หมวดหมู่ที่เลือก
    $stmt = $db->prepare("SELECT d.*, u.full_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.user_id WHERE d.category = ? AND (d.title LIKE ? OR d.description LIKE ? OR d.file_name LIKE ?) ORDER BY d.created_at DESC");
    $searchTerm = "%$search%";
    $stmt->bind_param('ssss', $category, $searchTerm, $searchTerm, $searchTerm);
} elseif ($search) {
    // ค้นหาจากทั้งหมด
    $stmt = $db->prepare("SELECT d.*, u.full_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.user_id WHERE d.title LIKE ? OR d.description LIKE ? OR d.category LIKE ? OR d.file_name LIKE ? ORDER BY d.created_at DESC");
    $searchTerm = "%$search%";
    $stmt->bind_param('ssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm);
} elseif ($category) {
    // กรองเฉพาะหมวดหมู่
    $stmt = $db->prepare("SELECT d.*, u.full_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.user_id WHERE d.category = ? ORDER BY d.created_at DESC");
    $stmt->bind_param('s', $category);
} else {
    // แสดงทั้งหมด
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
    <title>จัดการเอกสาร - Romar</title>
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
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            margin-bottom: 30px;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .page-title h1 { font-size: 1.8em; color: #000000; font-weight: 600; }

        /* Search Bar */
        .search-section {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            margin-bottom: 25px;
        }

        .search-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 45px 12px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #10ce30;
            box-shadow: 0 0 0 3px rgb(0, 0, 0);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2em;
            color: #ffffff;
        }

        .clear-search {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #ffffff;
            font-size: 1.2em;
            display: none;
        }

        .clear-search.show {
            display: block;
        }

        .clear-search:hover {
            color: #ef4444;
        }

        .search-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #10ce30 0%, #000000 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgb(0, 0, 0);
        }

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
            background: linear-gradient(135deg, #20d63e 0%, #000000 100%);
            color: white;
            box-shadow: 0 4px 6px rgb(0, 0, 0);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgb(0, 0, 0);
        }

        .btn-secondary { background: #718096; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-sm { padding: 8px 16px; font-size: 0.9em; }

        /* Filters */
        .filters {
            background: linear-gradient(135deg, #10ce30 0%, #000000 100%);
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
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95em;
            color: white;
            font-weight: 500;
        }

        .filter-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background: white;
            color: #0c7927;
            border-color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Documents Grid */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .doc-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            overflow: hidden;
            transition: all 0.3s;
        }

        .doc-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgb(0, 0, 0);
        }

        .doc-icon {
            padding: 30px;
            background: linear-gradient(135deg, #097e26 0%, #000000 100%);
            text-align: center;
            font-size: 3em;
        }

        .doc-body {
            padding: 20px;
        }

        .doc-title {
            font-size: 1.1em;
            font-weight: 600;
            color: #000000;
            margin-bottom: 8px;
        }

        .doc-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
            font-size: 0.85em;
            color: #000000;
        }

        .doc-description {
            font-size: 0.9em;
            color: #000000;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .doc-actions {
            display: flex;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #ffffff;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .badge-category {
            background: #727272;
            color: #fffdfd;
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
        }

        .modal.active { display: flex; }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #000000;
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

        .modal-close:hover { color: #ef4444; }

        .modal-body { padding: 30px; }

        .form-group { margin-bottom: 20px; }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #000000;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #dfcbcb5b;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.97)
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .file-upload {
            border: 2px dashed #000000;
            padding: 30px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload:hover {
            border-color: #000000;
            background: #f8fafc;
        }

        .file-upload-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }

        /* Alert */
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
            .documents-grid { grid-template-columns: 1fr; }
            .header-row { flex-direction: column; align-items: stretch; }
            .search-row { flex-direction: column; }
            .search-box { min-width: 100%; }
            .filters { flex-direction: column; align-items: stretch; }
            .filter-btn { text-align: center; }
        }
     .category-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap; 
    padding: 10px 0;
}


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

.tab-item:hover {
    background-color: #ffffff;
    text-decoration: none; 
}

.tab-item.active {
    background-color: #029925; 
    color: white;
    border-color: #000000;
    padding: 10px 25px;
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
                    <li class="menu-section">การจัดการ</li>
                    <li><a href="users-management.php">👥 จัดการผู้ใช้</a></li>
                    <li><a href="meeting-rooms.php">🏢 จัดการห้องประชุม</a></li>
                    <li class="active"><a href="documents.php">📄 จัดการเอกสาร</a></li>
                    <li class="menu-section">ฟีเจอร์</li>
                    <li><a href="room-booking.php">📅 จองห้องประชุม</a></li>
                    <li><a href="announcements.php">📢 ข่าวสาร</a></li>
                    <li class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                        <a href="../modules/tickets.php">🎫 IT Tickets</a>
                    </li>
                    <li class="menu-section">ระบบ</li>
                    <li><a href="settings.php">⚙️ ตั้งค่า</a></li>
                    <li><a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <div class="header-row">
                    <div class="page-title">
                        <h1>📄 จัดการเอกสาร</h1>
                    </div>
                    <button class="btn btn-primary" onclick="openUploadModal()">
                        ⬆️ อัปโหลดเอกสาร
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> show">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

              <!-- Filters -->
            <div class="filters">
                <span class="filter-label">
                    <span>📁</span>
                    <span>หมวดหมู่:</span>
                </span>
                <div class="category-menu">
    <a href="documents.php" class="tab-item <?php echo !$category ? 'active' : ''; ?>">
        📁 ทั้งหมด
    </a>
    <a href="documents.php?category=คู่มือ" class="tab-item <?php echo $category === 'คู่มือ' ? 'active' : ''; ?>">
        📘 คู่มือ
    </a>
    <a href="documents.php?category=แบบฟอร์ม" class="tab-item <?php echo $category === 'แบบฟอร์ม' ? 'active' : ''; ?>">
        📝 แบบฟอร์ม
    </a>
    <a href="documents.php?category=รูปภาพ" class="tab-item <?php echo $category === 'รูปภาพ' ? 'active' : ''; ?>">
        🖼️ รูปภาพ
    </a>
    <a href="documents.php?category=เอกสารทั่วไป" class="tab-item <?php echo $category === 'เอกสารทั่วไป' ? 'active' : ''; ?>">
        📗 เอกสารทั่วไป
    </a>
</div>
            </div>
            <!-- Search Bar -->
            <div class="search-section">
    <form method="GET" class="search-row">

        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input 
                type="text" 
                name="search" 
                class="search-input" 
                autocomplete="off"
                placeholder="ค้นหาชื่อเอกสาร..."
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
            >
        </div>
        <button type="submit" class="search-btn">ค้นหา</button>
    </form>
</div>

          

            <!-- Documents -->
            <div class="documents-grid">
                <?php if (empty($documents)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; background: white; border-radius: 12px;">
                    <div style="font-size: 4em; margin-bottom: 15px;">📄</div>
                    <h3>ยังไม่มีเอกสาร</h3>
                    <p style="color: #94a3b8;">เริ่มต้นด้วยการอัปโหลดเอกสารแรกของคุณ</p>
                </div>
                <?php else: ?>
                <?php foreach ($documents as $doc): 
                    $icons = [
                        'pdf' => '📕', 'doc' => '📘', 'docx' => '📘',
                        'xls' => '📗', 'xlsx' => '📗',
                        'ppt' => '📙', 'pptx' => '📙',
                        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️',
                        'zip' => '📦'
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
                            <span>👤 <?php echo htmlspecialchars($doc['full_name']); ?></span>
                            <span>📅 <?php echo formatDateShort($doc['created_at']); ?></span>
                        </div>
                        <div class="doc-actions">
                            <button class="btn btn-primary btn-sm" onclick="previewDocument('<?php echo $doc['file_path']; ?>', '<?php echo $doc['file_type']; ?>', '<?php echo addslashes($doc['title']); ?>')" style="flex: 1;">
                                👁️ ดูตัวอย่าง
                            </button>
                            <a href="../uploads/<?php echo $doc['file_path']; ?>" class="btn btn-secondary btn-sm" download style="flex: 1; justify-content: center;">
                                ⬇️ ดาวน์โหลด
                            </a>
                            <button class="btn btn-sm" style="flex: 1; background: #ef4444; color: white;" onclick="deleteDocument(<?php echo $doc['document_id']; ?>)">
                                🗑️ ลบ
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">⬆️ อัปโหลดเอกสาร</h2>
                <span class="modal-close" onclick="closeModal('uploadModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <?php echo csrf_input(); ?>
                    
                    <div class="form-group">
                        <label class="form-label" for="doc_title">ชื่อเอกสาร *</label>
                        <input type="text" name="title" id="doc_title" class="form-control" autocomplete="off" required>
                    </div>

               <div class="form-group">
    <label class="form-label" for="doc_category">หมวดหมู่ *</label>
    <select name="category" id="doc_category" class="form-control" autocomplete="off" required>
        <option value="" disabled selected>--- เลือกหมวดหมู่ ---</option>
        <option value="คู่มือ">คู่มือ</option>
        <option value="แบบฟอร์ม">แบบฟอร์ม</option>
        <option value="รูปภาพ">รูปภาพ</option>
        <option value="เอกสารทั่วไป">เอกสารทั่วไป</option>
    </select>
</div>

                    <div class="form-group">
                        <label class="form-label" for="doc_description">รายละเอียด</label>
                        <textarea name="description" id="doc_description" class="form-control" autocomplete="off"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="fileInput">เลือกไฟล์ * (สูงสุด 10MB)</label>
                        <div class="file-upload" onclick="document.getElementById('fileInput').click()">
                            <div class="file-upload-icon">📁</div>
                            <p>คลิกเพื่อเลือกไฟล์</p>
                            <p style="font-size: 0.85em; color: #94a3b8; margin-top: 8px;">
                                รองรับ: PDF, Word, Excel, PowerPoint, รูปภาพ, ZIP
                            </p>
                        </div>
                        <input type="file" id="fileInput" name="file" style="display: none;" required onchange="updateFileName(this)">
                        <p id="fileName" style="margin-top: 10px; color: #667eea; font-weight: 500;"></p>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 30px;">
                        <button type="submit" class="btn btn-success" style="flex: 1;">✅ อัปโหลด</button>
                        <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('uploadModal')">❌ ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal" id="previewModal">
        <div class="modal-content" style="max-width: 1000px; max-height: 90vh;">
            <div class="modal-header">
                <h2 class="modal-title" id="previewTitle">👁️ ดูตัวอย่างเอกสาร</h2>
                <span class="modal-close" onclick="closeModal('previewModal')">&times;</span>
            </div>
            <div class="modal-body" style="max-height: calc(90vh - 100px); overflow: auto;">
                <div id="previewContent"></div>
            </div>
        </div>
    </div>

    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="document_id" id="delete_document_id">
        <?php echo csrf_input(); ?>
    </form>

    <script>
        function openUploadModal() {
            document.getElementById('uploadModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function updateFileName(input) {
            const fileName = input.files[0]?.name;
            if (fileName) {
                document.getElementById('fileName').textContent = '✅ เลือกไฟล์: ' + fileName;
            }
        }

        function deleteDocument(docId) {
            if (confirm('คุณแน่ใจหรือไม่ที่จะลบเอกสารนี้?')) {
                document.getElementById('delete_document_id').value = docId;
                document.getElementById('deleteForm').submit();
            }
        }

        function previewDocument(filePath, fileType, title) {
            const modal = document.getElementById('previewModal');
            const content = document.getElementById('previewContent');
            const titleElement = document.getElementById('previewTitle');
            
            titleElement.textContent = '👁️ ' + title;
            
            const fullPath = '../uploads/' + filePath;
            const safePath = String(fullPath).replace(/"/g, '%22').replace(/'/g, '%27').replace(/</g, '%3C').replace(/>/g, '%3E');
            const safeTitle = String(title ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
            const safeTypeLabel = String(fileType || '').replace(/[^a-z0-9]/gi, '').toUpperCase();
            
            // ล้างเนื้อหาเดิม
            content.innerHTML = '';
            
            // Preview ตามประเภทไฟล์
            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileType.toLowerCase())) {
                // รูปภาพ - แสดงเต็ม
                content.innerHTML = `
                    <div style="text-align: center; background: #f8fafc; padding: 20px; border-radius: 12px;">
                        <img src="${safePath}" alt="${safeTitle}" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    </div>
                `;
            } else if (fileType.toLowerCase() === 'pdf') {
                // PDF - ใช้ iframe (ทำงานได้บน localhost)
                content.innerHTML = `
                    <iframe src="${safePath}" style="width: 100%; height: 70vh; border: none; border-radius: 8px;"></iframe>
                `;
            } else if (['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'].includes(fileType.toLowerCase())) {
                // เอกสาร Office - แสดง icon และข้อมูล (localhost ไม่สามารถใช้ Google Docs Viewer)
                const icons = {
                    'doc': '📘', 'docx': '📘',
                    'xls': '📗', 'xlsx': '📗',
                    'ppt': '📙', 'pptx': '📙'
                };
                const fileNames = {
                    'doc': 'Word Document', 'docx': 'Word Document',
                    'xls': 'Excel Spreadsheet', 'xlsx': 'Excel Spreadsheet',
                    'ppt': 'PowerPoint', 'pptx': 'PowerPoint'
                };
                const icon = icons[fileType.toLowerCase()] || '📄';
                const typeName = fileNames[fileType.toLowerCase()] || 'Office Document';
                
                content.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border-radius: 12px;">
                        <div style="font-size: 6em; margin-bottom: 20px; animation: pulse 2s infinite;">${icon}</div>
                        <h2 style="color: #1e293b; margin-bottom: 15px; font-size: 1.8em;">${safeTitle}</h2>
                        <div style="display: inline-block; background: white; padding: 12px 24px; border-radius: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                            <span style="color: #667eea; font-weight: 600; font-size: 1.1em;">${typeName} (.${safeTypeLabel})</span>
                        </div>
                        <p style="color: #64748b; font-size: 1.1em; margin: 25px 0; max-width: 500px; margin-left: auto; margin-right: auto; line-height: 1.6;">
                            ไฟล์ Office ไม่สามารถแสดงตัวอย่างได้บนระบบ Localhost<br>
                            กรุณาดาวน์โหลดไฟล์เพื่อเปิดด้วยโปรแกรม Office
                        </p>
                        <a href="${safePath}" download class="btn btn-primary" style="margin-top: 25px; display: inline-flex; padding: 16px 32px; font-size: 1.1em; gap: 10px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                            ⬇️ ดาวน์โหลดไฟล์
                        </a>
                        <p style="color: #94a3b8; font-size: 0.9em; margin-top: 20px;">
                            💡 คลิกปุ่มเพื่อดาวน์โหลดและเปิดด้วย Microsoft Office หรือ LibreOffice
                        </p>
                    </div>
                    <style>
                        @keyframes pulse {
                            0%, 100% { transform: scale(1); }
                            50% { transform: scale(1.05); }
                        }
                    </style>
                `;
            } else {
                // ไฟล์อื่นๆ - แสดงข้อมูล
                const icons = {
                    'zip': '📦',
                    'rar': '📦',
                    'txt': '📝'
                };
                const icon = icons[fileType.toLowerCase()] || '📄';
                
                content.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; background: #f8fafc; border-radius: 12px;">
                        <div style="font-size: 5em; margin-bottom: 20px;">${icon}</div>
                        <h3 style="color: #1e293b; margin-bottom: 10px;">${safeTitle}</h3>
                        <p style="color: #64748b; font-size: 1.1em;">ไฟล์ประเภท: ${fileType.toUpperCase()}</p>
                        <p style="color: #64748b; margin-top: 15px;">ไม่สามารถแสดงตัวอย่างไฟล์ประเภทนี้ได้</p>
                        <p style="color: #64748b;">กรุณาดาวน์โหลดเพื่อดูเนื้อหา</p>
                        <a href="${safePath}" download class="btn btn-primary" style="margin-top: 25px; display: inline-flex;">
                            ⬇️ ดาวน์โหลดไฟล์
                        </a>
                    </div>
                `;
            }
            
            modal.classList.add('active');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) alert.classList.remove('show');
        }, 5000);
    </script>
</body>
</html>
