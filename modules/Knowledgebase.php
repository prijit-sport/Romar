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

$db = getDB();
$isAdmin = $_SESSION['role'] === 'admin';
$message = '';
$messageType = '';
csrf_token();
$jsonAttrFlags = JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP;

$requestedAction = ($_SERVER['REQUEST_METHOD'] === 'POST') ? ($_POST['action'] ?? '') : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? '')) {
    if ($requestedAction === 'helpful') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    $message = 'Invalid CSRF token.';
    $messageType = 'error';
    $requestedAction = '';
}

// Handle Create Article
if ($requestedAction === 'create') {
    if (!$isAdmin) {
        http_response_code(403);
        $message = 'Access denied.';
        $messageType = 'error';
        $requestedAction = '';
    } else {
    $title = sanitize($_POST['title']);
    $category_id = (int)$_POST['category_id'];
    $content = trim((string)($_POST['content'] ?? ''));
    $tags = sanitize($_POST['tags']);
    
    $stmt = $db->prepare("INSERT INTO knowledgebase (title, category_id, content, tags, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('sissi', $title, $category_id, $content, $tags, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $message = 'เพิ่มบทความสำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'เพิ่มบทความ KB', 'Knowledge Base', "เพิ่ม: $title");
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
    }
}

// Handle Update Article
if ($requestedAction === 'update') {
    if (!$isAdmin) {
        http_response_code(403);
        $message = 'Access denied.';
        $messageType = 'error';
        $requestedAction = '';
    } else {
    $kb_id = (int)$_POST['kb_id'];
    $title = sanitize($_POST['title']);
    $category_id = (int)$_POST['category_id'];
    $content = trim((string)($_POST['content'] ?? ''));
    $tags = sanitize($_POST['tags']);
    
    $stmt = $db->prepare("UPDATE knowledgebase SET title = ?, category_id = ?, content = ?, tags = ?, updated_at = NOW() WHERE kb_id = ?");
    $stmt->bind_param('sissi', $title, $category_id, $content, $tags, $kb_id);
    
    if ($stmt->execute()) {
        $message = 'อัปเดตบทความสำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'อัปเดตบทความ KB', 'Knowledge Base', "อัปเดต: $title");
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
    }
}

// Handle Delete Article
if ($requestedAction === 'delete') {
    if (!$isAdmin) {
        http_response_code(403);
        $message = 'Access denied.';
        $messageType = 'error';
        $requestedAction = '';
    } else {
    $kb_id = (int)$_POST['kb_id'];
    
    $stmt = $db->prepare("DELETE FROM knowledgebase WHERE kb_id = ?");
    $stmt->bind_param('i', $kb_id);
    
    if ($stmt->execute()) {
        $message = 'ลบบทความสำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'ลบบทความ KB', 'Knowledge Base', "ลบ KB ID: $kb_id");
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
    }
}

// Handle Mark as Helpful
if ($requestedAction === 'helpful') {
    $kb_id = (int)$_POST['kb_id'];
    
    $stmt = $db->prepare("UPDATE knowledgebase SET helpful_count = helpful_count + 1 WHERE kb_id = ?");
    $stmt->bind_param('i', $kb_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

// Handle View Count  
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $kb_id = (int)$_GET['view'];
    $stmt = $db->prepare("UPDATE knowledgebase SET views = views + 1 WHERE kb_id = ?");
    $stmt->bind_param('i', $kb_id);
    $stmt->execute();
}

// Get Filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Build Query
$sql = "SELECT kb.*, kbc.name as category_name, kbc.icon as category_icon, u.full_name as author_name
        FROM knowledgebase kb
        LEFT JOIN kbcategories kbc ON kb.category_id = kbc.category_id
        LEFT JOIN users u ON kb.created_by = u.user_id
        WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $sql .= " AND (kb.title LIKE ? OR kb.content LIKE ? OR kb.tags LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if ($category > 0) {
    $sql .= " AND kb.category_id = ?";
    $params[] = $category;
    $types .= 'i';
}

$sql .= " ORDER BY kb.created_at DESC";

$stmt = $db->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$articles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get Categories
// เรียงตามลำดับความสำคัญ (display_order) และกรองเฉพาะที่เปิดใช้งาน (is_active = 1)
$categories = $db->query("SELECT * FROM kbcategories WHERE is_active = 1 ORDER BY display_order ASC, name ASC")->fetch_all(MYSQLI_ASSOC);

// Get Statistics
$statsSQL = "SELECT 
    COUNT(*) as total_articles,
    SUM(views) as total_views,
    SUM(helpful_count) as total_helpful
    FROM knowledgebase";
$stats = $db->query($statsSQL)->fetch_assoc();

// Get Popular Articles
$popularSQL = "SELECT kb.*, kbc.name as category_name, kbc.icon as category_icon
               FROM knowledgebase kb
               LEFT JOIN kbcategories kbc ON kb.category_id = kbc.category_id
               ORDER BY kb.views DESC
               LIMIT 5";
$popular = $db->query($popularSQL)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Base - IT Support</title>
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
            padding: 14px 20px;
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
            font-family: 'Sarabun', sans-serif;
        }
        
        .btn:active {
            transform: scale(0.98);
        }

        .btn-primary {
            background: linear-gradient(180deg, #10ce30 0%, #000000 );
            color: white;
            box-shadow: 0 4px 15px rgb(0, 0, 0);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgb(0, 0, 0);
        }

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

        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgb(0, 0, 0);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #c8d0daea;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(233, 221, 221, 0.58);
        }
        
        .form-control:hover {
            border-color: #e7dbdbb7;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .articles-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .article-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgb(0, 0, 0);
            transition: all 0.3s;
        }

        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgb(0, 0, 0);
        }

        .article-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .article-title {
            font-size: 1.4em;
            font-weight: 600;
            color: #000000;
            margin-bottom: 10px;
        }

        .article-meta {
            display: flex;
            gap: 15px;
            color: #000000;
            font-size: 0.9em;
            margin-bottom: 15px;
        }

        .article-content {
            color: #000000;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .article-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .category-badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            background: #bee3f8;
            color: #2c5282;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85em;
        }

        .btn-view {
            background: #4299e1;
            color: white;
        }

        .btn-edit {
            background: #48bb78;
            color: white;
        }

        .btn-delete {
            background: #f56565;
            color: white;
        }

        .btn-helpful {
            background: #ed8936;
            color: white;
        }

        .sidebar-widget {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgb(0, 0, 0);
            margin-bottom: 20px;
        }

        .widget-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #000000;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #020202;
        }

        .popular-item {
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 8px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .popular-item:hover {
            background: #e9dbdb4b;
        }

        .popular-title {
            font-weight: 600;
            color: #19be3d;
            margin-bottom: 5px;
        }

        .popular-meta {
            font-size: 0.85em;
            color: #000000;
        }

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
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            width: 95%;
            max-width: 1200px;
            max-height: 95vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .modal-content.large {
            max-width: 1400px;
        }
        
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .modal-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }
        
        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #5568d3;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f7fafc;
        }

        .modal-header h2 {
            font-size: 1.8em;
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
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2d3748;
            font-size: 1.05em;
        }
        
        .form-group label .required {
            color: #f56565;
            margin-left: 3px;
        }

        textarea.form-control {
            min-height: 400px;
            resize: vertical;
            line-height: 1.6;
            font-size: 1em;
        }
        
        textarea.form-control:focus {
            outline: none;
            border-color: #cccfdd8e;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
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
        
        .form-help {
            font-size: 0.9em;
            color: #718096;
            margin-top: 6px;
        }
        
        .input-hint {
            background: #ebf8ff;
            border-left: 3px solid #4299e1;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 0.9em;
            color: #2c5282;
            margin-bottom: 20px;
        }

        .view-content {
            line-height: 1.8;
            font-size: 1.05em;
        }

        .view-meta {
            display: flex;
            gap: 20px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #718096;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 98%;
                padding: 20px;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
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
            
            .modal-content {
                width: 100%;
                max-width: 100%;
                height: 100vh;
                max-height: 100vh;
                border-radius: 0;
                padding: 15px;
            }
            
            .modal-header h2 {
                font-size: 1.3em;
            }
            
            textarea.form-control {
                min-height: 250px;
            }
            
            .stats-grid {
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
                    <li><a href="assets.php"><i class="fas fa-box"></i> สินทรัพย์</a></li>
                    <li class="active"><a href="Knowledgebase.php"><i class="fas fa-book"></i> Knowledge Base</a></li>
                    
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
                <span style="color: #2d3748; font-weight: 600;">Knowledge Base</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-book"></i> Knowledge Base</h1>
                <?php if ($isAdmin): ?>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> เพิ่มบทความ
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
                        <i class="fas fa-book" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_articles'] ?? 0); ?></h3>
                        <p>บทความทั้งหมด</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                        <i class="fas fa-eye" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_views'] ?? 0); ?></h3>
                        <p>ยอดเข้าชม</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                        <i class="fas fa-thumbs-up" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($stats['total_helpful'] ?? 0); ?></h3>
                        <p>Helpful</p>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET">
                    <div class="filter-grid">
                        <input type="text" name="search" class="form-control" placeholder="🔍 ค้นหาบทความ..." value="<?php echo htmlspecialchars($search); ?>">
                        
                    <select name="category" class="form-control" onchange="this.form.submit()">
    <option value="0">ทุกหมวดหมู่</option>
    <?php foreach ($categories as $cat): ?>
        <?php 
            // บังคับให้ทั้งคู่เป็นตัวเลขเพื่อการเปรียบเทียบที่แม่นยำ
            $isSelected = ((int)$category === (int)$cat['category_id']) ? 'selected' : ''; 
        ?>
        <option value="<?php echo $cat['category_id']; ?>" <?php echo $isSelected; ?>>
            <?php echo htmlspecialchars($cat['name'] ?? ''); ?>
        </option>
    <?php endforeach; ?>
</select>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> ค้นหา
                        </button>
                    </div>
                </form>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Articles Section -->
                <div class="articles-section">
                    <?php if (empty($articles)): ?>
                    <div class="article-card" style="text-align: center; padding: 60px;">
                        <i class="fas fa-book-open" style="font-size: 4em; color: #cbd5e0; margin-bottom: 20px;"></i>
                        <h3 style="color: #718096;">ไม่พบบทความ</h3>
                        <p style="color: #a0aec0;">ลองค้นหาด้วยคำอื่นหรือเพิ่มบทความใหม่</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($articles as $article): ?>
                        <div class="article-card">
                            <div class="article-header">
                                <div style="flex: 1;">
                                    <div class="article-title"><?php echo htmlspecialchars($article['title']); ?></div>
                                    <div class="article-meta">
                                        <span><i class="<?php echo $article['category_icon'] ?? 'fas fa-folder'; ?>"></i> <?php echo htmlspecialchars($article['category_name'] ?? 'ไม่ระบุ'); ?></span>
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($article['author_name'] ?? 'ไม่ทราบ'); ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($article['created_at'])); ?></span>
                                    </div>
                                </div>
                                <span class="category-badge">
                                    <i class="<?php echo $article['category_icon'] ?? 'fas fa-folder'; ?>"></i> <?php echo htmlspecialchars($article['category_name'] ?? 'ไม่ระบุ'); ?>
                                </span>
                            </div>

                            <div class="article-content">
                                <?php echo substr(strip_tags($article['content']), 0, 200) . '...'; ?>
                            </div>

                            <?php if ($article['tags']): ?>
                            <div style="margin-bottom: 15px;">
                                <?php foreach (explode(',', $article['tags']) as $tag): ?>
                                <span style="background: #e2e8f0; padding: 4px 10px; border-radius: 8px; font-size: 0.85em; margin-right: 5px;">
                                    #<?php echo trim(htmlspecialchars($tag)); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <div class="article-footer">
                                <div style="display: flex; gap: 15px; color: #718096; font-size: 0.9em;">
                                    <span><i class="fas fa-eye"></i> <?php echo number_format($article['views']); ?></span>
                                    <span><i class="fas fa-thumbs-up"></i> <?php echo number_format($article['helpful_count']); ?></span>
                                </div>
                                <div class="action-btns">
                                    <button class="btn btn-view btn-sm" onclick='viewArticle(<?php echo json_encode($article, $jsonAttrFlags); ?>)'>
                                        <i class="fas fa-eye"></i> ดู
                                    </button>
                                    <button class="btn btn-helpful btn-sm" onclick="markHelpful(<?php echo $article['kb_id']; ?>)">
                                        <i class="fas fa-thumbs-up"></i> Helpful
                                    </button>
                                    <?php if ($isAdmin): ?>
                                    <button class="btn btn-edit btn-sm" onclick='editArticle(<?php echo json_encode($article, $jsonAttrFlags); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-delete btn-sm" onclick="deleteArticle(<?php echo $article['kb_id']; ?>, '<?php echo htmlspecialchars($article['title']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Widgets -->
                <div>
                    <!-- Categories Widget -->
                    <div class="sidebar-widget">
                        <h3 class="widget-title"><i class="fas fa-folder"></i> หมวดหมู่</h3>
                        <?php foreach ($categories as $cat): ?>
                        <a href="?category=<?php echo $cat['category_id']; ?>" style="display: block; padding: 10px; margin-bottom: 5px; border-radius: 8px; text-decoration: none; color: #2d3748; transition: all 0.3s;">
                            <i class="<?php echo $cat['icon'] ?? 'fas fa-folder'; ?>"></i> <?php echo htmlspecialchars($cat['name'] ?? ''); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Popular Articles Widget -->
                    <div class="sidebar-widget">
                        <h3 class="widget-title"><i class="fas fa-fire"></i> บทความยอดนิยม</h3>
                        <?php foreach ($popular as $pop): ?>
                        <div class="popular-item" onclick='viewArticle(<?php echo json_encode($pop, $jsonAttrFlags); ?>)'>
                            <div class="popular-title"><?php echo htmlspecialchars($pop['title']); ?></div>
                            <div class="popular-meta">
                                <i class="<?php echo $pop['category_icon'] ?? 'fas fa-folder'; ?>"></i> <?php echo htmlspecialchars($pop['category_name'] ?? 'ไม่ระบุ'); ?> • 
                                <i class="fas fa-eye"></i> <?php echo number_format($pop['views']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Article Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="viewTitle"><i class="fas fa-book-open"></i> รายละเอียดบทความ</h2>
                <button class="close-btn" onclick="closeViewModal()">&times;</button>
            </div>
            <div id="viewBody"></div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div id="articleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-plus-circle"></i> เพิ่มบทความใหม่</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="articleForm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="kb_id" id="kb_id">
                <?php echo csrf_input(); ?>
                
                <div class="input-hint">
                    <i class="fas fa-info-circle"></i> 
                    <strong>คำแนะนำ:</strong> กรอกข้อมูลให้ครบถ้วนเพื่อให้บทความมีคุณภาพและค้นหาได้ง่าย
                </div>
                
                <div class="form-group">
                    <label for="title">
                        หัวข้อบทความ 
                        <span class="required">*</span>
                    </label>
                    <input type="text" 
                           name="title" 
                           id="title" 
                           class="form-control" 
                           placeholder="เช่น วิธีการ Reset รหัสผ่าน Windows"
                           required>
                    <div class="form-help">ชื่อบทความที่สั้น กระชับ และเข้าใจง่าย</div>
                </div>

                <div class="form-group">
                    <label for="category_id">
                        หมวดหมู่ 
                        <span class="required">*</span>
                    </label>
                    <select name="category_id" id="category_id" class="form-control" required>
                        <option value="">-- เลือกหมวดหมู่ --</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>">
                            <?php echo htmlspecialchars($cat['name'] ?? ''); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-help">เลือกหมวดหมู่ที่เหมาะสมกับเนื้อหา</div>
                </div>

                <div class="form-group">
                    <label for="content">
                        เนื้อหา 
                        <span class="required">*</span>
                    </label>
                    <textarea name="content" 
                              id="content" 
                              class="form-control" 
                              placeholder="เขียนเนื้อหาบทความที่นี่...

คำแนะนำ:
- ใช้หัวข้อย่อยเพื่อแบ่งเนื้อหา (## หัวข้อย่อย)
- เขียนทีละขั้นตอนให้ชัดเจน
- ใส่ตัวอย่างหรือภาพประกอบถ้าจำเป็น
- ตรวจสอบความถูกต้องก่อนบันทึก"
                              required></textarea>
                    <div class="form-help">
                        <i class="fas fa-lightbulb"></i> 
                        เขียนเนื้อหาให้ละเอียด ครบถ้วน และง่ายต่อการทำความเข้าใจ
                    </div>
                </div>

                <div class="form-group">
                    <label for="tags">Tags (คำค้นหา)</label>
                    <input type="text" 
                           name="tags" 
                           id="tags" 
                           class="form-control" 
                           placeholder="เช่น windows, password, reset, troubleshoot">
                    <div class="form-help">
                        <i class="fas fa-tag"></i> 
                        คั่นแต่ละคำด้วยเครื่องหมายจุลภาค (,) เพื่อให้ค้นหาได้ง่าย
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px; padding-top: 20px; border-top: 2px solid #f7fafc;">
                    <button type="button" class="btn" onclick="closeModal()" style="background: #e2e8f0; color: #2d3748;">
                        <i class="fas fa-times"></i> ยกเลิก
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> บันทึกบทความ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="kb_id" id="delete_kb_id">
        <?php echo csrf_input(); ?>
    </form>

    <script>
        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderMultilineText(value) {
            return escapeHtml(value).replace(/\r?\n/g, '<br>');
        }

        function viewArticle(article) {
            // Update view count
            fetch('?view=' + article.kb_id);
            
            document.getElementById('viewTitle').textContent = article.title || 'รายละเอียดบทความ';
            
            let html = '<div class="view-meta">';
            html += '<span><i class="fas fa-folder"></i> ' + escapeHtml(article.category_name || 'ไม่ระบุ') + '</span>';
            html += '<span><i class="fas fa-user"></i> ' + escapeHtml(article.author_name || 'ไม่ทราบ') + '</span>';
            html += '<span><i class="fas fa-calendar"></i> ' + escapeHtml(new Date(article.created_at).toLocaleDateString('th-TH')) + '</span>';
            html += '<span><i class="fas fa-eye"></i> ' + Number(article.views || 0).toLocaleString() + ' views</span>';
            html += '<span><i class="fas fa-thumbs-up"></i> ' + Number(article.helpful_count || 0) + ' helpful</span>';
            html += '</div>';
            
            html += '<div class="view-content">' + renderMultilineText(article.content || '') + '</div>';
            
            if (article.tags) {
                html += '<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">';
                html += '<strong>Tags:</strong> ';
                article.tags.split(',').forEach(tag => {
                    html += '<span style="background: #e2e8f0; padding: 4px 10px; border-radius: 8px; font-size: 0.85em; margin-right: 5px;">#' + escapeHtml(tag.trim()) + '</span>';
                });
                html += '</div>';
            }
            
            document.getElementById('viewBody').innerHTML = html;
            document.getElementById('viewModal').classList.add('show');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('show');
        }

        function openCreateModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> เพิ่มบทความใหม่';
            document.getElementById('formAction').value = 'create';
            document.getElementById('articleForm').reset();
            document.getElementById('kb_id').value = '';
            
            // Clear form
            document.getElementById('title').value = '';
            document.getElementById('content').value = '';
            document.getElementById('tags').value = '';
            
            document.getElementById('articleModal').classList.add('show');
        }

        function editArticle(article) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> แก้ไขบทความ';
            document.getElementById('formAction').value = 'update';
            document.getElementById('kb_id').value = article.kb_id;
            document.getElementById('title').value = article.title;
            document.getElementById('category_id').value = article.category_id;
            document.getElementById('content').value = article.content;
            document.getElementById('tags').value = article.tags || '';
            document.getElementById('articleModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('articleModal').classList.remove('show');
        }

        function deleteArticle(kbId, title) {
            if (confirm('ต้องการลบบทความ "' + title + '" ใช่หรือไม่?')) {
                document.getElementById('delete_kb_id').value = kbId;
                document.getElementById('deleteForm').submit();
            }
        }

        function markHelpful(kbId) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=helpful&kb_id=' + kbId + '&csrf_token=<?php echo rawurlencode(csrf_token()); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('ขอบคุณสำหรับ Feedback!');
                    location.reload();
                }
            });
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>
