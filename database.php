<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// ตรวจสอบ login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = Database::getInstance();
$page_title = "จัดการเอกสาร";

// สร้างโฟลเดอร์ uploads ถ้ายังไม่มี
$upload_dir = __DIR__ . '/../uploads/documents/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// จัดการ Actions
$success_message = '';
$error_message = '';

// อัปโหลดเอกสาร
if (isset($_POST['upload_document'])) {
    $document_name = trim($_POST['document_name']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === 0) {
        $file = $_FILES['document_file'];
        $file_size = $file['size'];
        $file_tmp = $file['tmp_name'];
        $file_name = $file['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // ตรวจสอบประเภทไฟล์
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed_extensions)) {
            // สร้างชื่อไฟล์ใหม่ (ป้องกันชื่อซ้ำ)
            $new_file_name = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file_name);
            $file_path = 'uploads/documents/' . $new_file_name;
            $full_path = __DIR__ . '/../' . $file_path;
            
            if (move_uploaded_file($file_tmp, $full_path)) {
                // บันทึกลง database
                $stmt = $db->prepare("INSERT INTO documents (document_name, file_name, file_path, file_size, file_type, category, description, uploaded_by, uploaded_at) VALUES (:document_name, :file_name, :file_path, :file_size, :file_type, :category, :description, :uploaded_by, :uploaded_at)");
                $stmt->bindValue(':document_name', $document_name, SQLITE3_TEXT);
                $stmt->bindValue(':file_name', $new_file_name, SQLITE3_TEXT);
                $stmt->bindValue(':file_path', $file_path, SQLITE3_TEXT);
                $stmt->bindValue(':file_size', $file_size, SQLITE3_INTEGER);
                $stmt->bindValue(':file_type', $file_ext, SQLITE3_TEXT);
                $stmt->bindValue(':category', $category, SQLITE3_TEXT);
                $stmt->bindValue(':description', $description, SQLITE3_TEXT);
                $stmt->bindValue(':uploaded_by', $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':uploaded_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
                $stmt->execute();
                
                logActivity($_SESSION['user_id'], 'อัปโหลดเอกสาร', 'Documents', "อัปโหลด: {$document_name}");
                $success_message = "อัปโหลดเอกสารสำเร็จ!";
            } else {
                $error_message = "ไม่สามารถอัปโหลดไฟล์ได้";
            }
        } else {
            $error_message = "ประเภทไฟล์ไม่ได้รับอนุญาต";
        }
    } else {
        $error_message = "กรุณาเลือกไฟล์";
    }
}

// ลบเอกสาร
if (isset($_POST['delete_document'])) {
    $doc_id = (int)$_POST['document_id'];
    
    // ดึงข้อมูลเอกสาร
    $stmt = $db->prepare("SELECT * FROM documents WHERE document_id = :id");
    $stmt->bindValue(':id', $doc_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $doc = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($doc) {
        // ลบไฟล์
        $full_path = __DIR__ . '/../' . $doc['file_path'];
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        
        // ลบจาก database
        $stmt = $db->prepare("DELETE FROM documents WHERE document_id = :id");
        $stmt->bindValue(':id', $doc_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        logActivity($_SESSION['user_id'], 'ลบเอกสาร', 'Documents', "ลบ: {$doc['document_name']}");
        $success_message = "ลบเอกสารสำเร็จ!";
    }
}

// ดึงข้อมูลเอกสารทั้งหมด
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

$sql = "SELECT d.*, u.full_name as uploader_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.user_id WHERE 1=1";

if ($search) {
    $sql .= " AND (d.document_name LIKE '%{$search}%' OR d.description LIKE '%{$search}%')";
}

if ($category_filter) {
    $sql .= " AND d.category = '{$category_filter}'";
}

$sql .= " ORDER BY d.uploaded_at DESC";

$result = $db->query($sql);
$documents = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $documents[] = $row;
}

// หมวดหมู่
$categories = ['ทั่วไป', 'คู่มือ', 'แบบฟอร์ม', 'รายงาน', 'นโยบาย', 'อื่นๆ'];

// ฟังก์ชันแสดงขนาดไฟล์
function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// ฟังก์ชันไอคอนตามประเภทไฟล์
function getFileIcon($ext) {
    $icons = [
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝',
        'xls' => '📊',
        'xlsx' => '📊',
        'ppt' => '📽️',
        'pptx' => '📽️',
        'txt' => '📃',
        'zip' => '🗜️',
        'rar' => '🗜️',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️'
    ];
    return isset($icons[$ext]) ? $icons[$ext] : '📁';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ระบบจัดการในRomar<</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', Arial, sans-serif;
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 1.3em;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 0.85em;
            opacity: 0.7;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-section {
            margin-bottom: 20px;
        }

        .menu-section-title {
            padding: 0 20px;
            font-size: 0.75em;
            text-transform: uppercase;
            opacity: 0.5;
            margin-bottom: 10px;
        }

        .menu-item {
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .menu-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .menu-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-left: 3px solid white;
        }

        .menu-item-icon {
            margin-right: 10px;
            font-size: 1.2em;
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 30px;
        }

        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 1.8em;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2em;
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease-out;
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

        .alert-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 25px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .card-title {
            font-size: 1.3em;
            color: #2c3e50;
            font-weight: 600;
        }

        /* Button */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-size: 0.95em;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #000;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85em;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
            background: #667eea;
            color: white;
        }

        /* Form */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.3em;
            color: #2c3e50;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: #6c757d;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-bar .form-control {
            flex: 1;
            min-width: 200px;
        }

        /* Document Card */
        .doc-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .doc-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .doc-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .doc-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }

        .doc-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .doc-meta {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .doc-actions {
            display: flex;
            gap: 5px;
            margin-top: 15px;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #667eea;
        }

        .stat-value {
            font-size: 2em;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>ระบบจัดการในRomar</h2>
            <p>Romar romarIndustrial Co., Ltd.</p>
        </div>

        <div class="sidebar-menu">
            <div class="menu-section">
                <a href="dashboard.php" class="menu-item">
                    <span class="menu-item-icon">📊</span>
                    Dashboard
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-section-title">ระบบหลัก</div>
                <a href="#" class="menu-item">
                    <span class="menu-item-icon">🎫</span>
                    IT Tickets
                </a>
                <a href="#" class="menu-item">
                    <span class="menu-item-icon">🏢</span>
                    จองห้องประชุม
                </a>
                <a href="#" class="menu-item">
                    <span class="menu-item-icon">📢</span>
                    ประกาศข่าวสาร
                </a>
                <a href="documents.php" class="menu-item active">
                    <span class="menu-item-icon">📁</span>
                    เอกสาร
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-section-title">การจัดการ</div>
                <a href="users-management.php" class="menu-item">
                    <span class="menu-item-icon">👥</span>
                    จัดการผู้ใช้
                </a>
                <a href="#" class="menu-item">
                    <span class="menu-item-icon">⚙️</span>
                    ตั้งค่า
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-section-title">บัญชี</div>
                <a href="../auth/logout.php" class="menu-item">
                    <span class="menu-item-icon">🚪</span>
                    ออกจากระบบ
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><?php echo $page_title; ?></h1>
            <div class="user-info">
                <div>
                    <div style="font-weight: 600;"><?php echo $_SESSION['full_name']; ?></div>
                    <div style="font-size: 0.85em; color: #6c757d;"><?php echo $_SESSION['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'พนักงาน'; ?></div>
                </div>
                <div class="user-avatar">
                    <?php echo substr($_SESSION['full_name'], 0, 1); ?>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                ✅ <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                ❌ <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($documents); ?></div>
                <div class="stat-label">📁 เอกสารทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php
                    $total_size = 0;
                    foreach ($documents as $doc) {
                        $total_size += $doc['file_size'];
                    }
                    echo formatBytes($total_size);
                    ?>
                </div>
                <div class="stat-label">💾 ขนาดรวม</div>
            </div>
        </div>

        <!-- Documents Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">รายการเอกสาร</h2>
                <button class="btn btn-primary" onclick="openModal('uploadModal')">
                    ⬆️ อัปโหลดเอกสาร
                </button>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" style="display: flex; gap: 10px; flex: 1; flex-wrap: wrap;">
                    <input type="text" name="search" class="form-control" placeholder="ค้นหาเอกสาร..." value="<?php echo htmlspecialchars($search); ?>">
                    
                    <select name="category" class="form-control" style="max-width: 200px;">
                        <option value="">ทุกหมวดหมู่</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                                <?php echo $cat; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">🔍 ค้นหา</button>
                    <?php if ($search || $category_filter): ?>
                        <a href="documents.php" class="btn btn-warning">✖️ ล้าง</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Documents Table -->
            <?php if (empty($documents)): ?>
                <p style="text-align: center; padding: 40px; color: #6c757d;">
                    📭 ยังไม่มีเอกสาร
                </p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ไอคอน</th>
                            <th>ชื่อเอกสาร</th>
                            <th>หมวดหมู่</th>
                            <th>ขนาดไฟล์</th>
                            <th>ผู้อัปโหลด</th>
                            <th>อัปโหลดเมื่อ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td style="font-size: 2em;"><?php echo getFileIcon($doc['file_type']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($doc['document_name']); ?></strong>
                                    <?php if ($doc['description']): ?>
                                        <br><small style="color: #6c757d;"><?php echo htmlspecialchars($doc['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge"><?php echo htmlspecialchars($doc['category']); ?></span></td>
                                <td><?php echo formatBytes($doc['file_size']); ?></td>
                                <td><?php echo htmlspecialchars($doc['uploader_name']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($doc['uploaded_at'])); ?></td>
                                <td>
                                    <a href="../<?php echo $doc['file_path']; ?>" download class="btn btn-success btn-sm">
                                        ⬇️ ดาวน์โหลด
                                    </a>
                                    
                                    <?php if ($doc['uploaded_by'] == $_SESSION['user_id'] || $_SESSION['role'] === 'admin'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('แน่ใจหรือว่าต้องการลบเอกสารนี้?')">
                                            <input type="hidden" name="document_id" value="<?php echo $doc['document_id']; ?>">
                                            <button type="submit" name="delete_document" class="btn btn-danger btn-sm">
                                                🗑️ ลบ
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">⬆️ อัปโหลดเอกสาร</h3>
                <button class="modal-close" onclick="closeModal('uploadModal')">×</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">ชื่อเอกสาร *</label>
                        <input type="text" name="document_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">หมวดหมู่ *</label>
                        <select name="category" class="form-control" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">คำอธิบาย</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">เลือกไฟล์ *</label>
                        <input type="file" name="document_file" class="form-control" required>
                        <small style="color: #6c757d;">
                            รองรับ: PDF, Word, Excel, PowerPoint, รูปภาพ, ZIP (สูงสุด 10MB)
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" onclick="closeModal('uploadModal')">ยกเลิก</button>
                    <button type="submit" name="upload_document" class="btn btn-primary">⬆️ อัปโหลด</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>