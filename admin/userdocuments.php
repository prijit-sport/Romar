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

$user = getCurrentUser();
$db = getDB();
$message = '';
$messageType = '';
csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['message'] = 'Invalid CSRF token.';
    $_SESSION['messageType'] = 'error';
    $redirectUrl = 'userdocuments.php';
    if (isset($_GET['category']) && $_GET['category'] !== '') {
        $redirectUrl .= '?category=' . urlencode($_GET['category']);
    }
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $redirectUrl .= (strpos($redirectUrl, '?') !== false ? '&' : '?') . 'search=' . urlencode($_GET['search']);
    }
    header('Location: ' . $redirectUrl);
    exit;
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
$uploadDir = '../uploads/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
$relativePath = 'documents/' . $fileName;
$filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $stmt = $db->prepare("INSERT INTO documents (title, category, description, file_name, file_path, file_size, file_type, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param('sssssisi', $title, $category, $description, $file['name'], $relativePath, $file['size'], $extension, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = 'อัปโหลดเอกสารสำเร็จ!';
                    $_SESSION['messageType'] = 'success';
                    logActivity($_SESSION['user_id'], 'อัปโหลดเอกสาร', 'Documents', "อัปโหลด: $title");
                } else {
                    unlink($filePath);
                    $_SESSION['message'] = 'เกิดข้อผิดพลาด: ' . $stmt->error;
                    $_SESSION['messageType'] = 'error';
                }
            } else {
                $_SESSION['message'] = 'ไม่สามารถอัปโหลดไฟล์ได้!';
                $_SESSION['messageType'] = 'error';
            }
        }
    } else {
        $_SESSION['message'] = 'กรุณาเลือกไฟล์!';
        $_SESSION['messageType'] = 'error';
    }
    
    // Redirect to prevent duplicate submission
    header('Location: userdocuments.php' . ($category ? '?category=' . urlencode($category) : ''));
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $docId = (int)$_POST['document_id'];
    
    $stmt = $db->prepare("SELECT * FROM documents WHERE document_id = ? AND uploaded_by = ?");
    $stmt->bind_param('ii', $docId, $_SESSION['user_id']);
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
            logActivity($_SESSION['user_id'], 'ลบเอกสาร', 'Documents', "ลบ: " . $doc['title']);
        }
    } else {
        $_SESSION['message'] = 'ไม่พบเอกสารหรือไม่มีสิทธิ์ลบ!';
        $_SESSION['messageType'] = 'error';
    }
    
    // Redirect to prevent duplicate submission
    $redirectUrl = 'userdocuments.php';
    if (isset($_GET['category'])) {
        $redirectUrl .= '?category=' . urlencode($_GET['category']);
    }
    if (isset($_GET['search'])) {
        $redirectUrl .= (strpos($redirectUrl, '?') !== false ? '&' : '?') . 'search=' . urlencode($_GET['search']);
    }
    header('Location: ' . $redirectUrl);
    exit;
}

// Show message from session then clear
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'];
    unset($_SESSION['message'], $_SESSION['messageType']);
}

// Get ALL documents for everyone (shared view)
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$where = "1=1"; // Everyone sees all documents
$params = [];
$types = '';

if ($search) {
    $where .= " AND (d.title LIKE ? OR d.description LIKE ? OR d.category LIKE ? OR d.file_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $types = 'ssss';
}

if ($category) {
    $where .= " AND d.category = ?";
    $params[] = $category;
    $types .= 's';
}

$query = "SELECT d.*, u.full_name FROM documents d LEFT JOIN users u ON d.uploaded_by = u.user_id WHERE $where ORDER BY d.created_at DESC";
$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เอกสารของฉัน - Romar</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../includes/admin-theme.css">
    <style>
        .filters {
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
        }

        .search-section form { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }

        .category-menu .tab-item {
            padding: 0.55rem 1rem;
            border: 1px solid var(--border-light);
        }

        .category-menu .tab-item.active {
            background: rgba(59, 130, 246, 0.2);
            border-color: var(--blue);
            color: var(--blue);
        }

        .doc-icon {
            background: linear-gradient(135deg, var(--blue-soft), var(--blue));
            color: white;
            font-size: 2.5em;
        }

        @media (max-width: 768px) {
            .documents-grid { grid-template-columns: 1fr; }
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
                    <li><a href="../modules/tickets.php">🎫 IT Tickets</a></li>
                    <li class="active"><a href="userdocuments.php">📄 เอกสาร</a></li>
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
        <h1>📄 เอกสารทั้งหมด</h1>
    </div>
    <button class="btn btn-primary" onclick="openUploadModal()">
        ⬆️ อัปโหลดเอกสารใหม่
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
                    <a href="userdocuments.php" class="tab-item <?php echo !$category ? 'active' : ''; ?>">📁 ทั้งหมด</a>
                    <a href="userdocuments.php?category=คู่มือ" class="tab-item <?php echo $category === 'คู่มือ' ? 'active' : ''; ?>">📘 คู่มือ</a>
                    <a href="userdocuments.php?category=แบบฟอร์ม" class="tab-item <?php echo $category === 'แบบฟอร์ม' ? 'active' : ''; ?>">📝 แบบฟอร์ม</a>
                    <a href="userdocuments.php?category=รูปภาพ" class="tab-item <?php echo $category === 'รูปภาพ' ? 'active' : ''; ?>">🖼️ รูปภาพ</a>
                    <a href="userdocuments.php?category=เอกสารทั่วไป" class="tab-item <?php echo $category === 'เอกสารทั่วไป' ? 'active' : ''; ?>">📗 เอกสารทั่วไป</a>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="search-section">
                <form method="GET" class="search-row">
                    <?php if ($category): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <?php endif; ?>
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search" class="search-input" autocomplete="off"
                            placeholder="ค้นหาชื่อเอกสาร..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="search-btn">ค้นหา</button>
                    <?php if ($search): ?>
                    <a href="userdocuments.php<?php echo $category ? '?category=' . urlencode($category) : ''; ?>" class="clear-btn">✕ ล้าง</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Documents -->
            <div class="documents-grid">
                <?php if (empty($documents)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; background: white; border-radius: 12px;">
                    <div style="font-size: 4em; margin-bottom: 15px;">📄</div>
                    <h3>ยังไม่มีเอกสารในระบบ</h3>
                    <p style="color: #94a3b8;">ไม่มีเอกสารใดๆ ในระบบ เริ่มต้นด้วยการอัปโหลดเป็นคนแรก</p>
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
            const safePath = encodeURI(fullPath);
            const safeTitle = String(title ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '<')
                .replace(/>/g, '>')
                .replace(/"/g, '"')
                .replace(/'/g, '&#39;');
            const safeTypeLabel = String(fileType || '').replace(/[^a-z0-9]/gi, '').toUpperCase();
            
            content.innerHTML = '';
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(fileType.toLowerCase())) {
                content.innerHTML = `
                    <div style="text-align: center; background: #f8fafc; padding: 20px; border-radius: 12px;">
                        <img src="${safePath}" alt="${safeTitle}" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    </div>`;
            } else if (fileType.toLowerCase() === 'pdf') {
                content.innerHTML = `<iframe src="${safePath}" style="width: 100%; height: 70vh; border: none; border-radius: 8px;"></iframe>`;
            } else {
                const icons = {'pdf':'📕','doc':'📘','xls':'📗','zip':'📦'};
                const icon = icons[fileType.toLowerCase()] || '📄';
                content.innerHTML = `
                    <div style="text-align:center;padding:60px 20px;background:#f8fafc;border-radius:12px;">
                        <div style="font-size:4em;margin-bottom:20px;">${icon}</div>
                        <h3 style="color:#1e293b;">${safeTitle}</h3>
                        <p style="color:#64748b;">ไม่สามารถแสดงตัวอย่างไฟล์ .${safeTypeLabel} ได้</p>
                        <a href="${safePath}" download style="display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:#20d63e;color:white;border-radius:8px;text-decoration:none;margin-top:20px;">
                            ⬇️ ดาวน์โหลดไฟล์
                        </a>
                    </div>`;
            }
            
            modal.classList.add('active');
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        };
    </script>
</body>
</html>

