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
$csrfToken = csrf_token();
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
            $message = 'บันทึกบทความเรียบร้อยแล้ว!';
            $messageType = 'success';
            logActivity($_SESSION['user_id'], 'เพิ่มบทความ', 'Knowledge Base', "เพิ่ม: $title");
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
            $message = 'อัปเดตบทความเรียบร้อยแล้ว!';
            $messageType = 'success';
            logActivity($_SESSION['user_id'], 'อัปเดตบทความ', 'Knowledge Base', "อัปเดต: $title");
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
            $message = 'ลบบทความเรียบร้อยแล้ว!';
            $messageType = 'success';
            logActivity($_SESSION['user_id'], 'ลบบทความ', 'Knowledge Base', "ลบ KB ID: $kb_id");
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
$pageTitle = ui_text('page.title.knowledgebase');
$activePage = 'knowledgebase';
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content" data-csrf-token="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="breadcrumb-nav">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="dashboard.php"><i class="fas fa-home"></i> <?php echo ui_text('nav.dashboard'); ?></a>
            </li>
            <li class="breadcrumb-separator">&rsaquo;</li>
            <li class="breadcrumb-item active"><i class="fas fa-book"></i> <?php echo ui_text('page.title.knowledgebase'); ?></li>
        </ol>
    </div>

    <div class="page-header">
        <div>
            <h1><i class="fas fa-book"></i> <?php echo ui_text('page.title.knowledgebase'); ?></h1>
            <p class="section-subtitle"><?php echo ui_text('page.subtitle.knowledgebase'); ?></p>
        </div>
        <div class="page-actions">
            <?php if ($isAdmin): ?>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> <?php echo ui_text('button.create_article'); ?>
            </button>
            <?php endif; ?>
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
                <div class="stat-icon gradient-purple"><i class="fas fa-book"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_articles'] ?? 0); ?></h3>
                    <p><?php echo ui_text('status.total_articles'); ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gradient-blue"><i class="fas fa-eye"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_views'] ?? 0); ?></h3>
                    <p><?php echo ui_text('status.total_views'); ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gradient-green"><i class="fas fa-thumbs-up"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_helpful'] ?? 0); ?></h3>
                    <p><?php echo ui_text('status.total_helpful'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="filter-bar">
            <form method="GET">
                <div class="filter-grid">
                    <input type="text" name="search" class="form-control" placeholder="<?php echo ui_text('filter.search_placeholder'); ?>" value="<?php echo htmlspecialchars($search); ?>">
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="0"><?php echo ui_text('filter.all_categories'); ?></option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>" <?php echo (int)$category === (int)$cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name'] ?? ''); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> <?php echo ui_text('button.search'); ?>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section class="section">
        <div class="section-grid knowledgebase-grid">
            <div class="section-body knowledgebase-articles">
                <?php if (empty($articles)): ?>
                <div class="empty-state knowledgebase-empty">
                    <i class="fas fa-book-open"></i>
                    <h3><?php echo ui_text('empty.knowledgebase.title'); ?></h3>
                    <p><?php echo ui_text('empty.knowledgebase.body'); ?></p>
                </div>
                <?php else: ?>
                    <?php foreach ($articles as $article): ?>
                        <?php $articleTags = array_filter(array_map('trim', explode(',', $article['tags'] ?? ''))); ?>
                        <article class="article-card">
                            <div class="article-header">
                                <div>
                                    <div class="article-title"><?php echo htmlspecialchars($article['title']); ?></div>
                                    <div class="article-meta">
                                        <span><i class="<?php echo $article['category_icon'] ?? 'fas fa-folder'; ?>"></i> <?php echo htmlspecialchars($article['category_name'] ?? 'ไม่ระบุหมวด'); ?></span>
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($article['author_name'] ?? '-'); ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($article['created_at'])); ?></span>
                                    </div>
                                </div>
                                <span class="category-badge">
                                    <i class="<?php echo $article['category_icon'] ?? 'fas fa-folder'; ?>"></i>
                                    <?php echo htmlspecialchars($article['category_name'] ?? 'ไม่ระบุ'); ?>
                                </span>
                            </div>

                            <div class="article-content">
                                <?php echo htmlspecialchars(mb_substr(strip_tags($article['content']), 0, 250)); ?><?php if (mb_strlen(strip_tags($article['content'])) > 250): ?>...<?php endif; ?>
                            </div>

                            <?php if (!empty($articleTags)): ?>
                            <div class="article-tags">
                                <?php foreach ($articleTags as $tag): ?>
                                <span class="article-tag">#<?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <div class="article-footer">
                                <div class="article-stats">
                                    <span><i class="fas fa-eye"></i> <?php echo number_format($article['views']); ?></span>
                                    <span><i class="fas fa-thumbs-up"></i> <?php echo number_format($article['helpful_count']); ?></span>
                                </div>
                                <div class="action-btns">
                                    <button type="button" class="btn btn-primary btn-sm" onclick='viewArticle(<?php echo json_encode($article, $jsonAttrFlags); ?>)'>
                                        <i class="fas fa-eye"></i> <?php echo ui_text('button.view_article'); ?>
                                    </button>
                                    <button type="button" class="btn btn-helpful btn-sm" onclick="markHelpful(<?php echo $article['kb_id']; ?>)">
                                        <i class="fas fa-thumbs-up"></i> <?php echo ui_text('button.helpful'); ?>
                                    </button>
                                    <?php if ($isAdmin): ?>
                                    <button type="button" class="btn btn-edit btn-sm" onclick='editArticle(<?php echo json_encode($article, $jsonAttrFlags); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-delete btn-sm" onclick="deleteArticle(<?php echo $article['kb_id']; ?>, <?php echo json_encode($article['title']); ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <aside class="section-body knowledgebase-sidebar">
                <div class="sidebar-widget">
                    <h3 class="widget-title"><i class="fas fa-folder"></i> <?php echo ui_text('widget.categories'); ?></h3>
                    <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?php echo $cat['category_id']; ?>" class="category-link <?php echo (int)$category === (int)$cat['category_id'] ? 'active' : ''; ?>">
                        <i class="<?php echo $cat['icon'] ?? 'fas fa-folder'; ?>"></i>
                        <?php echo htmlspecialchars($cat['name'] ?? ''); ?>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="sidebar-widget">
                    <h3 class="widget-title"><i class="fas fa-fire"></i> <?php echo ui_text('widget.popular_articles'); ?></h3>
                    <?php foreach ($popular as $pop): ?>
                    <button type="button" class="popular-item" onclick='viewArticle(<?php echo json_encode($pop, $jsonAttrFlags); ?>)'>
                        <div class="popular-title"><?php echo htmlspecialchars($pop['title']); ?></div>
                        <div class="popular-meta">
                            <i class="<?php echo $pop['category_icon'] ?? 'fas fa-folder'; ?>"></i>
                            <?php echo htmlspecialchars($pop['category_name'] ?? 'ไม่ระบุ'); ?> •
                            <i class="fas fa-eye"></i> <?php echo number_format($pop['views']); ?>
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>
    </section>
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="viewTitle" class="modal-title"><i class="fas fa-book-open"></i> <?php echo ui_text('modal.article_view_title'); ?></h2>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div id="viewBody"></div>
    </div>
</div>

<div id="articleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle" class="modal-title"><i class="fas fa-plus-circle"></i> <?php echo ui_text('modal.article_add_title'); ?></h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
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
                <label for="title">หัวข้อบทความ <span class="required">*</span></label>
                <input type="text" name="title" id="title" class="form-control" placeholder="เช่น วิธีการ Reset รหัสผ่าน Windows" required>
                <div class="form-help">ชื่อบทความที่ชัดเจน กระชับ และเข้าใจง่าย</div>
            </div>

            <div class="form-group">
                <label for="category_id">หมวดหมู่ <span class="required">*</span></label>
                <select name="category_id" id="category_id" class="form-control" required>
                    <option value="">-- เลือกหมวดหมู่ --</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['name'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-help">เลือกหมวดหมู่ที่เหมาะสมกับเนื้อหา</div>
            </div>

            <div class="form-group">
                <label for="content">เนื้อหา <span class="required">*</span></label>
                <textarea name="content" id="content" class="form-control" placeholder="เขียนเนื้อหาบทความที่นี่..." required></textarea>
                <div class="form-help"><i class="fas fa-lightbulb"></i> เขียนเนื้อหาให้ละเอียด ครบถ้วน และง่ายต่อการเข้าใจ</div>
            </div>

            <div class="form-group">
                <label for="tags">Tags (คำค้นหา)</label>
                <input type="text" name="tags" id="tags" class="form-control" placeholder="เช่น windows, password, reset, troubleshoot">
                <div class="form-help"><i class="fas fa-tag"></i> คั่นแต่ละคำด้วยเครื่องหมายจุลภาค (,) เพื่อให้ค้นหาได้ง่าย</div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()"><i class="fas fa-times"></i> ยกเลิก</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> บันทึกบทความ</button>
            </div>
        </form>
    </div>
</div>

<form id="deleteForm" method="POST" class="visually-hidden">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="kb_id" id="delete_kb_id">
    <?php echo csrf_input(); ?>
</form>

<?php $pageScripts = '<script src="' . BASE_URL . 'assets/js/knowledgebase.js"></script>'; ?>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>





