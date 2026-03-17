<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';
if (file_exists(__DIR__ . '/Notificationhelper.php')) {
    require_once __DIR__ . '/Notificationhelper.php';
}

csrf_token();
apply_security_headers();

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$message = '';
$messageType = '';
$isAdmin = $_SESSION['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $limit = rate_limit_check('module_tickets_post', 40, 60);
    if (!$limit['allowed']) {
        security_audit_log('rate_limit_blocked', ['module' => 'tickets', 'retry_after' => $limit['retry_after']]);
        $_SESSION['flash_success'] = 'Too many requests. Retry in ' . $limit['retry_after'] . ' seconds';
        header('Location: tickets.php');
        exit;
    }
}

// โ… เธฃเธฑเธ flash message เธเธฒเธ session (เธซเธฅเธฑเธ PRG redirect)
if (isset($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    $messageType = 'success';
    unset($_SESSION['flash_success']);
}

// Handle Create Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // CSRF check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_success'] = 'Invalid CSRF token.';
        header('Location: tickets.php');
        exit;
    }
    $title = sanitize($_POST['title']);
    $category = sanitize($_POST['category']);
    $priority = sanitize($_POST['priority']);
    $urgency = sanitize($_POST['urgency']);
    $impact = sanitize($_POST['impact']);
    $description = sanitize($_POST['description']);
    $asset_id = !empty($_POST['asset_id']) ? (int)$_POST['asset_id'] : null;
    $location = sanitize($_POST['location']);
    
    // Generate ticket number: IT-YYYYMMDD-XXXX
    $today = date('Ymd');
    $prefix = "IT-$today-";
    
    // Get the last ticket number for today
    $stmtTicket = $db->prepare("SELECT ticket_number FROM tickets WHERE ticket_number LIKE ? ORDER BY ticket_number DESC LIMIT 1");
    $searchPattern = $prefix . '%';
    $stmtTicket->bind_param('s', $searchPattern);
    $stmtTicket->execute();
    $result = $stmtTicket->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $lastNumber = (int)substr($row['ticket_number'], -4);
        $runningNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $runningNumber = '0001';
    }
    
    $ticketNumber = $prefix . $runningNumber;
    
    // Calculate SLA based on priority and impact
    $slaHours = calculateSLA($priority, $impact);
    $dueDatetime = date('Y-m-d H:i:s', strtotime("+$slaHours hours"));
    
    $stmt = $db->prepare("INSERT INTO tickets (ticket_number, title, category, priority, urgency, impact, description, status, created_by, location, asset_id, sla_due_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, ?, ?, NOW())");
    $stmt->bind_param('sssssssisis', $ticketNumber, $title, $category, $priority, $urgency, $impact, $description, $_SESSION['user_id'], $location, $asset_id, $dueDatetime);
    
    if ($stmt->execute()) {
        $ticketId = $stmt->insert_id;
        
        // Handle file uploads
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            handleFileUploads($db, $ticketId, $_FILES['attachments']);
        }
        
        logActivity($_SESSION['user_id'], 'Created ticket', 'Tickets', "Created: $title ($ticketNumber)");
        sendTicketNotification($ticketId, 'created');

        // โ… เนเธเนเธเน€เธ•เธทเธญเธ admin/IT เธงเนเธฒเธกเธต ticket เนเธซเธกเน
        notifyNewTicket($db, $ticketId, $ticketNumber, $title, $_SESSION['user_id']);

        // โ… PRG: Redirect เธซเธฅเธฑเธ POST เธชเธณเน€เธฃเนเธ เธเนเธญเธเธเธฑเธ resubmit เน€เธกเธทเนเธญ refresh
        $_SESSION['flash_success'] = 'Ticket created successfully! Reference: ' . $ticketNumber;
        header('Location: tickets.php');
        exit;
    } else {
        $message = 'Failed to create ticket: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Handle Update Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_success'] = 'Invalid CSRF token.';
        header('Location: tickets.php');
        exit;
    }
    $ticketId = (int)$_POST['ticket_id'];
    if (!can_access_ticket($db, $ticketId, (int)$_SESSION['user_id'], $isAdmin)) {
        security_audit_log('access_denied', ['module' => 'tickets', 'action' => 'update', 'ticket_id' => $ticketId]);
        $_SESSION['flash_success'] = 'Access denied.';
        header('Location: tickets.php');
        exit;
    }
    $status = sanitize($_POST['status']);
    $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $resolution = sanitize($_POST['resolution']);
    
    // Get old data for comparison
    $stmtOld = $db->prepare("SELECT status, assigned_to FROM tickets WHERE ticket_id = ?");
    $stmtOld->bind_param('i', $ticketId);
    $stmtOld->execute();
    $oldData = $stmtOld->get_result()->fetch_assoc();
    
    $stmt = $db->prepare("UPDATE tickets SET status = ?, assigned_to = ?, resolution = ?, updated_at = NOW(), 
                          resolved_at = CASE WHEN ? IN ('resolved', 'closed') THEN NOW() ELSE resolved_at END 
                          WHERE ticket_id = ?");
    $stmt->bind_param('sissi', $status, $assignedTo, $resolution, $status, $ticketId);
    
    if ($stmt->execute()) {
        // Add timeline entry
        $changes = [];
        if ($oldData['status'] != $status) {
            $changes[] = "Status changed: {$oldData['status']} -> $status";
        }
        if ($oldData['assigned_to'] != $assignedTo) {
            $changes[] = "Assigned to: " . ($assignedTo ? getUserName($db, $assignedTo) : 'Unassigned');
        }

        if (!empty($changes)) {
            addTimeline($db, $ticketId, 'update', implode(', ', $changes));
        }
        
        $message = 'Ticket updated successfully!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'Updated ticket', 'Tickets', "Ticket ID: $ticketId");
        
        // Send notification
        sendTicketNotification($ticketId, 'updated');
        
        // โ… PRG redirect
        $_SESSION['flash_success'] = 'Ticket updated successfully!';
        header('Location: tickets.php');
        exit;
    }
}

// Handle Add Comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_success'] = 'Invalid CSRF token.';
        header('Location: tickets.php');
        exit;
    }
    $ticketId = (int)$_POST['ticket_id'];
    if (!can_access_ticket($db, $ticketId, (int)$_SESSION['user_id'], $isAdmin)) {
        security_audit_log('access_denied', ['module' => 'tickets', 'action' => 'add_comment', 'ticket_id' => $ticketId]);
        $_SESSION['flash_success'] = 'Access denied.';
        header('Location: tickets.php');
        exit;
    }
    $comment = sanitize($_POST['comment']);
    $isInternal = isset($_POST['is_internal']) ? 1 : 0;
    
    $stmt = $db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('iisi', $ticketId, $_SESSION['user_id'], $comment, $isInternal);
    
    if ($stmt->execute()) {
        $commentId = $stmt->insert_id;
        addTimeline($db, $ticketId, 'comment', $isInternal ? 'Internal comment' : 'Public comment');
        
        // โ… เนเธเนเธเน€เธ•เธทเธญเธเน€เธกเธทเนเธญเธกเธต comment เนเธซเธกเน
        $tStmt = $db->prepare("SELECT ticket_number, title, created_by FROM tickets WHERE ticket_id = ?");
        $tStmt->bind_param('i', $ticketId);
        $tStmt->execute();
        $tData = $tStmt->get_result()->fetch_assoc();
        if ($tData) {
            notifyNewComment($db, $ticketId, $commentId, $tData['ticket_number'], $tData['title'],
                $comment, $_SESSION['user_id'], $_SESSION['role'], $tData['created_by']);
        }
        
        // โ… PRG redirect
        $_SESSION['flash_success'] = 'Comment added successfully!';
        header('Location: tickets.php');
        exit;
    }
}

// // Handle Add Time Tracking
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_time') {
//     $ticketId = (int)$_POST['ticket_id'];
//     $hours = (float)$_POST['hours'];
//     $workDescription = sanitize($_POST['work_description']);
//     
//     $stmt = $db->prepare("INSERT INTO ticket_time_tracking (ticket_id, user_id, hours_spent, work_description, logged_at) VALUES (?, ?, ?, ?, NOW())");
//     $stmt->bind_param('iids', $ticketId, $_SESSION['user_id'], $hours, $workDescription);
//     
//     if ($stmt->execute()) {
//         addTimeline($db, $ticketId, 'time_log', "เธเธฑเธเธ—เธถเธเน€เธงเธฅเธฒ: $hours เธเธฑเนเธงเนเธกเธ");
//         $message = 'เธเธฑเธเธ—เธถเธเน€เธงเธฅเธฒเธ—เธณเธเธฒเธเธชเธณเน€เธฃเนเธ!';
//         $messageType = 'success';
//     }
// }

// Handle Link Related Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link_ticket') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_success'] = 'Invalid CSRF token.';
        header('Location: tickets.php');
        exit;
    }
    $ticketId = (int)$_POST['ticket_id'];
    if (!can_access_ticket($db, $ticketId, (int)$_SESSION['user_id'], $isAdmin)) {
        security_audit_log('access_denied', ['module' => 'tickets', 'action' => 'link_ticket', 'ticket_id' => $ticketId]);
        $_SESSION['flash_success'] = 'Access denied.';
        header('Location: tickets.php');
        exit;
    }
    $relatedTicketNumber = sanitize($_POST['related_ticket']);
    
    // Find related ticket ID
    $stmtFind = $db->prepare("SELECT ticket_id FROM tickets WHERE ticket_number = ?");
    $stmtFind->bind_param('s', $relatedTicketNumber);
    $stmtFind->execute();
    $resultFind = $stmtFind->get_result();
    
    if ($relatedRow = $resultFind->fetch_assoc()) {
        $relatedTicketId = $relatedRow['ticket_id'];
        
        $stmt = $db->prepare("INSERT INTO ticket_relations (ticket_id, related_ticket_id, relation_type) VALUES (?, ?, 'related')");
        $stmt->bind_param('ii', $ticketId, $relatedTicketId);
        
        if ($stmt->execute()) {
            addTimeline($db, $ticketId, 'link', "Linked ticket: $relatedTicketNumber");
            $message = 'Related ticket linked successfully!';
            $messageType = 'success';
        }
    }
}

// Get tickets with filters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$priority = isset($_GET['priority']) ? sanitize($_GET['priority']) : '';
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$assigned = isset($_GET['assigned']) ? sanitize($_GET['assigned']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$whereClauses = [];
$params = [];
$types = '';

if ($status) {
    $whereClauses[] = "t.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($priority) {
    $whereClauses[] = "t.priority = ?";
    $params[] = $priority;
    $types .= 's';
}

if ($category) {
    $whereClauses[] = "t.category = ?";
    $params[] = $category;
    $types .= 's';
}

if ($assigned === 'me' && $isAdmin) {
    $whereClauses[] = "t.assigned_to = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

if ($search) {
    $whereClauses[] = "(t.ticket_number LIKE ? OR t.title LIKE ? OR t.description LIKE ?)";
    $searchPattern = "%$search%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $types .= 'sss';
}

if (!$isAdmin) {
    $whereClauses[] = "t.created_by = ?";
    $params[] = $_SESSION['user_id'];
    $types .= 'i';
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$sql = "SELECT t.*, 
        creator.full_name as creator_name,
        assignee.full_name as assignee_name,
        a.asset_name,
        (SELECT COUNT(*) FROM ticket_comments WHERE ticket_id = t.ticket_id) as comment_count,
        0 as total_hours
        -- (SELECT SUM(hours_spent) FROM ticket_time_tracking WHERE ticket_id = t.ticket_id) as total_hours
        FROM tickets t 
        LEFT JOIN users creator ON t.created_by = creator.user_id 
        LEFT JOIN users assignee ON t.assigned_to = assignee.user_id
        LEFT JOIN assets a ON t.asset_id = a.asset_id
        $whereSQL 
        ORDER BY t.created_at DESC";

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
// Use prepared statement for stats (avoid string concat)
$statsBase = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_count,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count,
    SUM(CASE WHEN sla_due_date < NOW() AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as overdue_count
    FROM tickets";

if ($isAdmin) {
    $statsStmt = $db->prepare($statsBase);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
} else {
    $statsStmt = $db->prepare($statsBase . " WHERE created_by = ?");
    $userId = (int)$_SESSION['user_id'];
    $statsStmt->bind_param('i', $userId);
    $statsStmt->execute();
    $stats = $statsStmt->get_result()->fetch_assoc();
}

// Get IT team members for assignment
$itTeamSQL = "SELECT user_id, full_name FROM users WHERE role IN ('admin', 'it_support') ORDER BY full_name";
$itTeam = $db->query($itTeamSQL)->fetch_all(MYSQLI_ASSOC);

// Get assets for selection
// $assetsSQL = "SELECT asset_id, asset_name, asset_tag FROM assets WHERE status = 'active' ORDER BY asset_name";
// $assets = $db->query($assetsSQL)->fetch_all(MYSQLI_ASSOC);
$assets = []; // Temporary: Uncomment after creating assets table

$currentUser = getCurrentUser();

// Helper functions
function calculateSLA($priority, $impact) {
    $slaMatrix = [
        'urgent' => ['critical' => 2, 'high' => 4, 'medium' => 8, 'low' => 16],
        'high' => ['critical' => 4, 'high' => 8, 'medium' => 16, 'low' => 24],
        'normal' => ['critical' => 8, 'high' => 16, 'medium' => 24, 'low' => 48],
        'low' => ['critical' => 16, 'high' => 24, 'medium' => 48, 'low' => 72]
    ];
    
    return $slaMatrix[$priority][$impact] ?? 24;
}

function addTimeline($db, $ticketId, $type, $description) {
    $stmt = $db->prepare("INSERT INTO ticket_timeline (ticket_id, user_id, event_type, description, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('iiss', $ticketId, $_SESSION['user_id'], $type, $description);
    return $stmt->execute();
}

function getUserName($db, $userId) {
    $stmt = $db->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ? $result['full_name'] : 'Unknown';
}

function sendTicketNotification($ticketId, $eventType) {
    // This is a placeholder - implement email notification
    // You can use PHPMailer or similar library
}

function handleFileUploads($db, $ticketId, $files) {
    $uploadDir = '../uploads/tickets/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($files['name'] as $key => $filename) {
        if ($files['error'][$key] === UPLOAD_ERR_OK) {
            $tmpName = $files['tmp_name'][$key];
            $fileSize = $files['size'][$key];
            $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // sanitize original filename
            $originalName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);

            // Allowed extensions
            $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];

            // basic MIME type check
            $mime = $finfo->file($tmpName);
            $allowedMime = [
                'image/jpeg', 'image/png', 'image/gif', 'application/pdf',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain', 'application/zip'
            ];

            if (in_array($fileExt, $allowedExt) && $fileSize < 10485760 && in_array($mime, $allowedMime)) { // 10MB limit
                $newFilename = uniqid() . '_' . $originalName;
                $destination = $uploadDir . $newFilename;

                if (move_uploaded_file($tmpName, $destination)) {
                    $stmt = $db->prepare("INSERT INTO ticket_attachments (ticket_id, filename, original_filename, file_size, uploaded_by, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $uploader = (int)($_SESSION['user_id'] ?? 0);
                    $stmt->bind_param('issii', $ticketId, $newFilename, $originalName, $fileSize, $uploader);
                    $stmt->execute();
                }
            }
        }
    }
    $finfo = null;
}
$pageTitle = ui_text('page.title.tickets');
$activePage = 'tickets';
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>
<!-- Main Content -->
<main class="main-content">
            <!-- Breadcrumb Navigation -->
            <div class="breadcrumb-nav">
                <ol class="breadcrumb">
                    <?php if ($isAdmin): ?>
                    <li class="breadcrumb-item">
                        <a href="../admin/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="breadcrumb-separator">&rsaquo;</li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active">
                        <i class="fas fa-ticket-alt"></i> <?php echo ui_text('page.title.tickets'); ?>
                    </li>
                </ol>
                <?php if ($isAdmin): ?>
                    <a href="../admin/dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo ui_text('nav.back_to_dashboard'); ?>
                </a>
                <?php endif; ?>
            </div>
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-ticket-alt"></i> <?php echo ui_text('page.title.tickets'); ?></h1>
                </div>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> <?php echo ui_text('button.create_ticket'); ?>
                </button>
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
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p><?php echo ui_text('tickets.stats.total'); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['new_count']; ?></h3>
                        <p><?php echo ui_text('tickets.stats.new'); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['in_progress_count']; ?></h3>
                        <p><?php echo ui_text('tickets.stats.in_progress'); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['resolved_count']; ?></h3>
                        <p><?php echo ui_text('tickets.stats.resolved'); ?></p>
                    </div>
                </div>

                <?php if ($stats['overdue_count'] > 0): ?>
                <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['overdue_count']; ?></h3>
                        <p><?php echo ui_text('tickets.stats.overdue'); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" id="filterForm">
                    <div class="filter-grid">
                        <input type="text" name="search" class="form-control" placeholder="<?php echo ui_text('tickets.filter.search_placeholder'); ?>" value="<?php echo htmlspecialchars($search); ?>">
                        
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value=""><?php echo ui_text('tickets.filter.status'); ?></option>
                            <option value="new" <?php echo $status === 'new' ? 'selected' : ''; ?>><?php echo ui_text('tickets.status.new'); ?></option>
                            <option value="assigned" <?php echo $status === 'assigned' ? 'selected' : ''; ?>><?php echo ui_text('tickets.status.assigned'); ?></option>
                            <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>><?php echo ui_text('tickets.status.in_progress'); ?></option>
                            <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>><?php echo ui_text('tickets.status.resolved'); ?></option>
                            <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>><?php echo ui_text('tickets.status.closed'); ?></option>
                        </select>

                        <select name="priority" class="form-control" onchange="this.form.submit()">
                            <option value=""><?php echo ui_text('tickets.filter.priority'); ?></option>
                            <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>><?php echo ui_text('tickets.priority.low'); ?></option>
                            <option value="normal" <?php echo $priority === 'normal' ? 'selected' : ''; ?>><?php echo ui_text('tickets.priority.normal'); ?></option>
                            <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>><?php echo ui_text('tickets.priority.high'); ?></option>
                            <option value="urgent" <?php echo $priority === 'urgent' ? 'selected' : ''; ?>><?php echo ui_text('tickets.priority.urgent'); ?></option>
                        </select>

                        <select name="category" class="form-control" onchange="this.form.submit()">
                            <option value=""><?php echo ui_text('tickets.filter.category'); ?></option>
                            <option value="hardware" <?php echo $category === 'hardware' ? 'selected' : ''; ?>><?php echo ui_text('tickets.category.hardware'); ?></option>
                            <option value="software" <?php echo $category === 'software' ? 'selected' : ''; ?>><?php echo ui_text('tickets.category.software'); ?></option>
                            <option value="network" <?php echo $category === 'network' ? 'selected' : ''; ?>><?php echo ui_text('tickets.category.network'); ?></option>
                            <option value="account" <?php echo $category === 'account' ? 'selected' : ''; ?>><?php echo ui_text('tickets.category.account'); ?></option>
                            <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>><?php echo ui_text('tickets.category.other'); ?></option>
                        </select>

                        <?php if ($isAdmin): ?>
                        <select name="assigned" class="form-control" onchange="this.form.submit()">
                            <option value=""><?php echo ui_text('tickets.filter.assigned'); ?></option>
                            <option value="me" <?php echo $assigned === 'me' ? 'selected' : ''; ?>><?php echo ui_text('tickets.filter.assigned_me'); ?></option>
                        </select>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($status || $priority || $category || $assigned || $search): ?>
                    <div class="filter-footer">
                        <a href="tickets.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-times"></i> <?php echo ui_text('tickets.filter.clear'); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tickets Grid -->
            <div class="tickets-grid">
                <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3><?php echo ui_text('tickets.empty.title'); ?></h3>
                    <p><?php echo ui_text('tickets.empty.body'); ?></p>
                </div>
                        <?php else: ?>
                            <?php
                                $priorityDisplay = [
                                    'urgent' => ui_text('tickets.priority.urgent'),
                                    'high' => ui_text('tickets.priority.high'),
                                    'normal' => ui_text('tickets.priority.normal'),
                                    'low' => ui_text('tickets.priority.low'),
                                ];
                            ?>
                            <?php foreach ($tickets as $ticket): 
                    // Calculate SLA status
                    $slaStatus = 'ok';
                    $slaText = ui_text('tickets.sla.within');
                    
                    if ($ticket['status'] !== 'resolved' && $ticket['status'] !== 'closed') {
                        $now = time();
                        $due = strtotime($ticket['sla_due_date']);
                        $diff = $due - $now;
                        $hoursLeft = round($diff / 3600, 1);
                        
                        if ($diff < 0) {
                            $slaStatus = 'overdue';
                            $slaText = sprintf(ui_text('tickets.sla.overdue'), abs($hoursLeft));
                        } elseif ($diff < 7200) { // Less than 2 hours
                            $slaStatus = 'warning';
                            $slaText = sprintf(ui_text('tickets.sla.warning'), $hoursLeft);
                        } else {
                            $slaText = sprintf(ui_text('tickets.sla.remaining'), $hoursLeft);
                        }
                    } else {
                        $slaText = ui_text('tickets.sla.closed');
                    }
                ?>
                <div class="ticket-card priority-<?php echo $ticket['priority']; ?>">
                    <div class="ticket-header">
                        <div class="ticket-number">
                            <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                        </div>
                        <div class="ticket-title">
                            <?php echo htmlspecialchars($ticket['title']); ?>
                        </div>
                    </div>

                    <div class="ticket-body">
                        <div class="ticket-meta">
                            <span class="badge badge-status-<?php echo $ticket['status']; ?>">
                                <i class="fas fa-circle"></i>
                                <?php 
                                $statusLabels = [
                                    'new' => ui_text('tickets.status.new'),
                                    'assigned' => ui_text('tickets.status.assigned'),
                                    'in_progress' => ui_text('tickets.status.in_progress'),
                                    'resolved' => ui_text('tickets.status.resolved'),
                                    'closed' => ui_text('tickets.status.closed')
                                ];
                                echo $statusLabels[$ticket['status']] ?? ucfirst(str_replace('_', ' ', $ticket['status']));
                                ?>
                            </span>
                            
                            <span class="badge badge-priority-<?php echo $ticket['priority']; ?>">
                                <i class="fas fa-flag"></i>
                                <?php echo $priorityDisplay[$ticket['priority']] ?? ucfirst($ticket['priority']); ?>
                            </span>

                            <span class="badge badge-category">
                                <i class="fas fa-folder"></i>
                                <?php echo htmlspecialchars($ticket['category']); ?>
                            </span>

                            <?php if ($ticket['impact']): ?>
                            <span class="badge badge-impact">
                                <i class="fas fa-exclamation-circle"></i>
                                Impact: <?php echo ucfirst($ticket['impact']); ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="ticket-description">
                            <?php echo nl2br(htmlspecialchars(substr($ticket['description'], 0, 200))); ?>
                            <?php if (strlen($ticket['description']) > 200): ?>
                                <span class="read-more"><?php echo ui_text('tickets.read_more'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="ticket-footer">
                            <div class="ticket-info">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($ticket['creator_name']); ?>
                            </div>

                            <?php if ($ticket['assignee_name']): ?>
                            <div class="ticket-info">
                                <i class="fas fa-user-check"></i>
                                <?php echo htmlspecialchars($ticket['assignee_name']); ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($ticket['asset_name']): ?>
                            <div class="ticket-info">
                                <i class="fas fa-laptop"></i>
                                <?php echo htmlspecialchars($ticket['asset_name']); ?>
                            </div>
                            <?php endif; ?>

                            <div class="ticket-info">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?>
                            </div>

                            <?php if ($ticket['comment_count'] > 0): ?>
                            <div class="ticket-info">
                                <i class="fas fa-comments"></i>
                                <?php echo $ticket['comment_count']; ?> <?php echo ui_text('tickets.table.comments'); ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($ticket['total_hours']): ?>
                            <div class="ticket-info">
                                <i class="fas fa-clock"></i>
                                <?php echo number_format($ticket['total_hours'], 1); ?> <?php echo ui_text('tickets.table.hours'); ?>
                            </div>
                            <?php endif; ?>

                            <div>
                                <span class="sla-badge sla-<?php echo $slaStatus; ?>">
                                    <i class="fas fa-stopwatch"></i> <?php echo $slaText; ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($isAdmin): ?>
                        <div class="ticket-actions">
                            <button class="btn btn-primary btn-sm" onclick="openViewModal(<?php echo $ticket['ticket_id']; ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn btn-success btn-sm" onclick="openUpdateModal(<?php echo $ticket['ticket_id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="ticket-actions">
                            <button class="btn btn-primary btn-sm" onclick="openViewModal(<?php echo $ticket['ticket_id']; ?>)">
                                <i class="fas fa-eye"></i> View
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

    <!-- Create Ticket Modal -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-plus-circle"></i> <?php echo ui_text('tickets.form.create_title'); ?>
                </h2>
                <span class="modal-close" onclick="closeModal('createModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <?php echo csrf_input(); ?>
                    
                    <div class="form-group">
                        <label class="form-label" for="ticket_title">
                            <i class="fas fa-heading"></i> <?php echo ui_text('tickets.form.title'); ?> *
                        </label>
                        <input type="text" name="title" id="ticket_title" class="form-control" required placeholder="<?php echo ui_text('tickets.form.title_placeholder'); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="ticket_category">
                                <i class="fas fa-folder"></i> <?php echo ui_text('tickets.form.category'); ?> *
                            </label>
                            <select name="category" id="ticket_category" class="form-control" required>
                                <option value=""><?php echo ui_text('tickets.form.category_placeholder'); ?></option>
                                <option value="hardware"><?php echo ui_text('tickets.category.hardware'); ?></option>
                                <option value="software"><?php echo ui_text('tickets.category.software'); ?></option>
                                <option value="network"><?php echo ui_text('tickets.category.network'); ?></option>
                                <option value="account"><?php echo ui_text('tickets.category.account'); ?></option>
                                <option value="printer"><?php echo ui_text('tickets.category.printer'); ?></option>
                                <option value="email"><?php echo ui_text('tickets.category.email'); ?></option>
                                <option value="other"><?php echo ui_text('tickets.category.other'); ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="ticket_asset">
                                <i class="fas fa-laptop"></i> <?php echo ui_text('tickets.form.asset'); ?>
                            </label>
                            <select name="asset_id" id="ticket_asset" class="form-control">
                                <option value=""><?php echo ui_text('tickets.form.asset_none'); ?></option>
                                <?php foreach ($assets as $asset): ?>
                                <option value="<?php echo $asset['asset_id']; ?>">
                                    <?php echo htmlspecialchars($asset['asset_name'] . ' - ' . $asset['asset_tag']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="ticket_urgency">
                                <i class="fas fa-flag"></i> <?php echo ui_text('tickets.form.urgency'); ?> *
                            </label>
                            <select name="urgency" id="ticket_urgency" class="form-control" required>
                                <option value="low"><?php echo ui_text('tickets.urgency.low'); ?></option>
                                <option value="medium" selected><?php echo ui_text('tickets.urgency.medium'); ?></option>
                                <option value="high"><?php echo ui_text('tickets.urgency.high'); ?></option>
                                <option value="critical"><?php echo ui_text('tickets.urgency.critical'); ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="ticket_impact">
                                <i class="fas fa-exclamation-circle"></i> <?php echo ui_text('tickets.form.impact'); ?> *
                            </label>
                            <select name="impact" id="ticket_impact" class="form-control" required>
                                <option value="low"><?php echo ui_text('tickets.impact.low'); ?></option>
                                <option value="medium" selected><?php echo ui_text('tickets.impact.medium'); ?></option>
                                <option value="high"><?php echo ui_text('tickets.impact.high'); ?></option>
                                <option value="critical"><?php echo ui_text('tickets.impact.critical'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="ticket_priority">
                                <i class="fas fa-tachometer-alt"></i> <?php echo ui_text('tickets.form.priority'); ?> *
                            </label>
                            <select name="priority" id="ticket_priority" class="form-control" required>
                                <option value="low"><?php echo ui_text('tickets.priority.low'); ?></option>
                                <option value="normal" selected><?php echo ui_text('tickets.priority.normal'); ?></option>
                                <option value="high"><?php echo ui_text('tickets.priority.high'); ?></option>
                                <option value="urgent"><?php echo ui_text('tickets.priority.urgent'); ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="ticket_location">
                                <i class="fas fa-map-marker-alt"></i> <?php echo ui_text('tickets.form.location'); ?>
                            </label>
                            <input type="text" name="location" id="ticket_location" class="form-control" placeholder="<?php echo ui_text('tickets.form.location_placeholder'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="ticket_description">
                            <i class="fas fa-align-left"></i> <?php echo ui_text('tickets.form.description'); ?> *
                        </label>
                        <textarea name="description" id="ticket_description" class="form-control" required placeholder="<?php echo ui_text('tickets.form.description_placeholder'); ?>"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="fileInput">
                            <i class="fas fa-paperclip"></i> <?php echo ui_text('tickets.form.attachments'); ?>
                        </label>
                        <div class="file-upload" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p><?php echo ui_text('tickets.form.attachments_help'); ?></p>
                            <small class="form-note"><?php echo ui_text('tickets.form.attachments_note'); ?></small>
                        </div>
                        <input type="file" id="fileInput" name="attachments[]" multiple class="visually-hidden" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                    </div>

                    <div class="modal-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> <?php echo ui_text('tickets.form.submit'); ?>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">
                            <i class="fas fa-times"></i> <?php echo ui_text('tickets.form.cancel'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php ob_start(); ?>
    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function openViewModal(ticketId) {
            // Implement view ticket details
            window.location.href = 'ticket_view.php?id=' + ticketId;
        }

        function openUpdateModal(ticketId) {
            // Implement update ticket
            window.location.href = 'ticket_update.php?id=' + ticketId;
        }

        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        // Auto-hide alerts
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) alert.classList.remove('show');
        }, 5000);

        // File upload preview
        document.getElementById('fileInput')?.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files.length > 0) {
                const fileList = Array.from(files).map(f => f.name).join(', ');
                document.querySelector('.file-upload p').textContent = `${files.length} files selected: ${fileList}`;
            }
        });
    </script>
<?php $pageScripts = ob_get_clean(); ?>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
