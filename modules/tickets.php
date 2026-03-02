<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
if (file_exists(__DIR__ . '/notificationhelper.php')) {
    require_once __DIR__ . '/notificationhelper.php';
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = md5(uniqid('', true));
    }
}

function validate_csrf($token) {
    return isset($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$message = '';
$messageType = '';

// ✅ รับ flash message จาก session (หลัง PRG redirect)
if (isset($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    $messageType = 'success';
    unset($_SESSION['flash_success']);
}

// Handle Create Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // CSRF check
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
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
        
        logActivity($_SESSION['user_id'], 'สร้าง IT Ticket', 'Tickets', "สร้าง: $title ($ticketNumber)");
        sendTicketNotification($ticketId, 'created');
        
        // ✅ แจ้งเตือน admin/IT ว่ามี ticket ใหม่
        notifyNewTicket($db, $ticketId, $ticketNumber, $title, $_SESSION['user_id']);
        
        // ✅ PRG: Redirect หลัง POST สำเร็จ ป้องกัน resubmit เมื่อ refresh
        $_SESSION['flash_success'] = 'สร้าง Ticket สำเร็จ! หมายเลข: ' . $ticketNumber;
        header('Location: tickets.php');
        exit;
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Handle Update Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_success'] = 'Invalid CSRF token.';
        header('Location: tickets.php');
        exit;
    }
    $ticketId = (int)$_POST['ticket_id'];
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
            $changes[] = "สถานะ: {$oldData['status']} → $status";
        }
        if ($oldData['assigned_to'] != $assignedTo) {
            $changes[] = "มอบหมาย: " . ($assignedTo ? getUserName($db, $assignedTo) : 'ไม่ระบุ');
        }
        
        if (!empty($changes)) {
            addTimeline($db, $ticketId, 'update', implode(', ', $changes));
        }
        
        $message = 'อัปเดต Ticket สำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'อัปเดต Ticket', 'Tickets', "Ticket ID: $ticketId");
        
        // Send notification
        sendTicketNotification($ticketId, 'updated');
        
        // ✅ PRG redirect
        $_SESSION['flash_success'] = 'อัปเดต Ticket สำเร็จ!';
        header('Location: tickets.php');
        exit;
    }
}

// Handle Add Comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_success'] = 'Invalid CSRF token.';
        header('Location: tickets.php');
        exit;
    }
    $ticketId = (int)$_POST['ticket_id'];
    $comment = sanitize($_POST['comment']);
    $isInternal = isset($_POST['is_internal']) ? 1 : 0;
    
    $stmt = $db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('iisi', $ticketId, $_SESSION['user_id'], $comment, $isInternal);
    
    if ($stmt->execute()) {
        $commentId = $stmt->insert_id;
        addTimeline($db, $ticketId, 'comment', $isInternal ? 'เพิ่มบันทึกภายใน' : 'เพิ่มความคิดเห็น');
        
        // ✅ แจ้งเตือนเมื่อมี comment ใหม่
        $tStmt = $db->prepare("SELECT ticket_number, title, created_by FROM tickets WHERE ticket_id = ?");
        $tStmt->bind_param('i', $ticketId);
        $tStmt->execute();
        $tData = $tStmt->get_result()->fetch_assoc();
        if ($tData) {
            notifyNewComment($db, $ticketId, $commentId, $tData['ticket_number'], $tData['title'],
                $comment, $_SESSION['user_id'], $_SESSION['role'], $tData['created_by']);
        }
        
        // ✅ PRG redirect
        $_SESSION['flash_success'] = 'เพิ่มความคิดเห็นสำเร็จ!';
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
//         addTimeline($db, $ticketId, 'time_log', "บันทึกเวลา: $hours ชั่วโมง");
//         $message = 'บันทึกเวลาทำงานสำเร็จ!';
//         $messageType = 'success';
//     }
// }

// Handle Link Related Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link_ticket') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_success'] = 'Invalid CSRF token.';
        header('Location: tickets.php');
        exit;
    }
    $ticketId = (int)$_POST['ticket_id'];
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
            addTimeline($db, $ticketId, 'link', "เชื่อมโยงกับ Ticket: $relatedTicketNumber");
            $message = 'เชื่อมโยง Ticket สำเร็จ!';
            $messageType = 'success';
        }
    }
}

// Get tickets with filters
$isAdmin = $_SESSION['role'] === 'admin';
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
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
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
            $mime = finfo_file($finfo, $tmpName);
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
    finfo_close($finfo);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Tickets - Enhanced System</title>
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

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #10ce30 0%, #000000 100%);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgb(0, 0, 0);
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
            padding: 15px 20px;
            color: rgb(255, 255, 255);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 1em;
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


        /* Breadcrumb Navigation */
        .breadcrumb-nav {
            background: rgb(255, 255, 255);
            padding: 15px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgb(0, 0, 0);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            list-style: none;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95em;
        }

        .breadcrumb-item a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .breadcrumb-item a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .breadcrumb-item.active {
            color: #2d3748;
            font-weight: 600;
        }

        .breadcrumb-separator {
            color: #cbd5e0;
            font-size: 0.8em;
        }

        .back-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgb(0, 0, 0);
        }

        .back-button i {
            font-size: 1.1em;
        }
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
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

        .page-title h1 {
            font-size: 2em;
            color: #000000;
            font-weight: 700;
        }

        /* Statistics Cards */
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
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgb(0, 0, 0);
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
            margin-bottom: 5px;
            color: #000000;
        }

        .stat-info p {
            color: #000000;
            font-size: 0.9em;
        }

        /* Filter Bar */
        .filter-bar {
            background: white;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgb(0, 0, 0);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1em;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 3px rgb(255, 255, 255);
        }

        /* Tickets Grid */
        .tickets-grid {
            display: grid;
            gap: 20px;
        }

        .ticket-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgb(0, 0, 0);
            transition: all 0.3s;
            border-left: 5px solid #e2e8f0;
        }

        .ticket-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgb(0, 0, 0);
        }

        .ticket-card.priority-urgent {
            border-left-color: #f56565;
        }

        .ticket-card.priority-high {
            border-left-color: #ed8936;
        }

        .ticket-card.priority-normal {
            border-left-color: #4299e1;
        }

        .ticket-card.priority-low {
            border-left-color: #48bb78;
        }

        .ticket-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-bottom: 1px solid #000000;
        }

        .ticket-number {
            font-size: 0.9em;
            color: #000000;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .ticket-title {
            font-size: 1.3em;
            font-weight: 700;
            color: #000000;
            margin-bottom: 10px;
        }

        .ticket-body {
            padding: 25px;
        }

        .ticket-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-status-new { background: #e3f2fd; color: #1976d2; }
        .badge-status-assigned { background: #f3e5f5; color: #7b1fa2; }
        .badge-status-in_progress { background: #fff3e0; color: #f57c00; }
        .badge-status-resolved { background: #e8f5e9; color: #388e3c; }
        .badge-status-closed { background: #eceff1; color: #455a64; }

        .badge-priority-urgent { background: #fee; color: #c53030; }
        .badge-priority-high { background: #fff5f0; color: #c05621; }
        .badge-priority-normal { background: #ebf8ff; color: #2c5282; }
        .badge-priority-low { background: #f0fff4; color: #276749; }

        .ticket-description {
            color: #4a5568;
            line-height: 1.7;
            margin-bottom: 20px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 10px;
        }

        .ticket-footer {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .ticket-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            color: #000000;
        }

        .ticket-info i {
            color: #000000;
        }

        .sla-badge {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .sla-ok {
            background: #c6f6d5;
            color: #22543d;
        }

        .sla-warning {
            background: #feebc8;
            color: #7c2d12;
        }

        .sla-overdue {
            background: #fed7d7;
            color: #742a2a;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Sarabun', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgb(0, 0, 0);
        }

        .btn-secondary {
            background: #718096;
            color: white;
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9em;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgb(0, 0, 0);
            backdrop-filter: blur(5px);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgb(0, 0, 0);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
        }

        .modal-title {
            font-size: 1.5em;
            font-weight: 700;
            color: #000000;
        }

        .modal-close {
            font-size: 2em;
            font-weight: 300;
            color: #000000;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-close:hover {
            color: #1a202c;
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95em;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .file-upload {
            border: 2px dashed #cbd5e0;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload:hover {
            border-color: #667eea;
            background: #f7fafc;
        }

        .file-upload i {
            font-size: 3em;
            color: #a0aec0;
            margin-bottom: 15px;
        }

        /* Alert */
        .alert {
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: none;
            align-items: center;
            gap: 15px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        .alert.show {
            display: flex;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #38a169;
        }

        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #e53e3e;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 24px;
            border-radius: 10px;
            background: white;
            color: #4a5568;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .tab-btn:hover {
            background: #f7fafc;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgb(0, 0, 0)
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;

            .breadcrumb-nav {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .breadcrumb {
                font-size: 0.85em;
            }
            
            .back-button {
                width: 100%;
                justify-content: center;
            }
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
                    <li class="active"><a href="tickets.php"><i class="fas fa-ticket-alt"></i> IT Tickets</a></li>
                    <li><a href="assets.php"><i class="fas fa-box"></i> สินทรัพย์</a></li>
                    <li><a href="knowledgebase.php"><i class="fas fa-book"></i> Knowledge Base</a></li>
                    
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
            <!-- Breadcrumb Navigation -->
            <div class="breadcrumb-nav">
                <ol class="breadcrumb">
                    <?php if ($isAdmin): ?>
                    <li class="breadcrumb-item">
                        <a href="../admin/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="breadcrumb-separator">›</li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active">
                        <i class="fas fa-ticket-alt"></i> IT Tickets
                    </li>
                </ol>
                <?php if ($isAdmin): ?>
                <a href="../admin/dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    กลับหน้าหลัก
                </a>
                <?php endif; ?>
            </div>
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-ticket-alt"></i> IT Tickets</h1>
                </div>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> สร้าง Ticket ใหม่
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
                        <i class="fas fa-ticket-alt" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Tickets</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                        <i class="fas fa-clock" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['new_count']; ?></h3>
                        <p>New</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                        <i class="fas fa-tasks" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['in_progress_count']; ?></h3>
                        <p>In Progress</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                        <i class="fas fa-check-circle" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['resolved_count']; ?></h3>
                        <p>Resolved</p>
                    </div>
                </div>

                <?php if ($stats['overdue_count'] > 0): ?>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                        <i class="fas fa-exclamation-triangle" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['overdue_count']; ?></h3>
                        <p>SLA Overdue</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" id="filterForm">
                    <div class="filter-grid">
                        <input type="text" name="search" class="form-control" placeholder="🔍 ค้นหา Ticket..." value="<?php echo htmlspecialchars($search); ?>">
                        
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">ทุกสถานะ</option>
                            <option value="new" <?php echo $status === 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="assigned" <?php echo $status === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>

                        <select name="priority" class="form-control" onchange="this.form.submit()">
                            <option value="">ทุกระดับความสำคัญ</option>
                            <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="normal" <?php echo $priority === 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $priority === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>

                        <select name="category" class="form-control" onchange="this.form.submit()">
                            <option value="">ทุกหมวดหมู่</option>
                            <option value="hardware" <?php echo $category === 'hardware' ? 'selected' : ''; ?>>Hardware</option>
                            <option value="software" <?php echo $category === 'software' ? 'selected' : ''; ?>>Software</option>
                            <option value="network" <?php echo $category === 'network' ? 'selected' : ''; ?>>Network</option>
                            <option value="account" <?php echo $category === 'account' ? 'selected' : ''; ?>>Account</option>
                            <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>

                        <?php if ($isAdmin): ?>
                        <select name="assigned" class="form-control" onchange="this.form.submit()">
                            <option value="">ทั้งหมด</option>
                            <option value="me" <?php echo $assigned === 'me' ? 'selected' : ''; ?>>มอบหมายให้ฉัน</option>
                        </select>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($status || $priority || $category || $assigned || $search): ?>
                    <div style="margin-top: 15px;">
                        <a href="tickets.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-times"></i> ล้างตัวกรอง
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tickets Grid -->
            <div class="tickets-grid">
                <?php if (empty($tickets)): ?>
                <div style="text-align: center; padding: 80px 20px; background: white; border-radius: 16px;">
                    <i class="fas fa-inbox" style="font-size: 5em; color: #cbd5e0; margin-bottom: 20px;"></i>
                    <h3 style="color: #000000; margin-bottom: 10px;">ไม่พบ Ticket</h3>
                    <p style="color: #000000;">ลองปรับเปลี่ยนตัวกรองหรือสร้าง Ticket ใหม่</p>
                </div>
                <?php else: ?>
                <?php foreach ($tickets as $ticket): 
                    // Calculate SLA status
                    $slaStatus = 'ok';
                    $slaText = 'ภายในกำหนด';
                    
                    if ($ticket['status'] !== 'resolved' && $ticket['status'] !== 'closed') {
                        $now = time();
                        $due = strtotime($ticket['sla_due_date']);
                        $diff = $due - $now;
                        $hoursLeft = round($diff / 3600, 1);
                        
                        if ($diff < 0) {
                            $slaStatus = 'overdue';
                            $slaText = 'เกินกำหนด ' . abs($hoursLeft) . ' ชม.';
                        } elseif ($diff < 7200) { // Less than 2 hours
                            $slaStatus = 'warning';
                            $slaText = 'เหลือ ' . $hoursLeft . ' ชม.';
                        } else {
                            $slaText = 'เหลือ ' . $hoursLeft . ' ชม.';
                        }
                    } else {
                        $slaText = 'เสร็จสิ้น';
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
                                    'new' => 'New',
                                    'assigned' => 'Assigned',
                                    'in_progress' => 'In Progress',
                                    'resolved' => 'Resolved',
                                    'closed' => 'Closed'
                                ];
                                echo $statusLabels[$ticket['status']] ?? $ticket['status'];
                                ?>
                            </span>
                            
                            <span class="badge badge-priority-<?php echo $ticket['priority']; ?>">
                                <i class="fas fa-flag"></i>
                                <?php echo ucfirst($ticket['priority']); ?>
                            </span>

                            <span class="badge" style="background: #e6fffa; color: #234e52;">
                                <i class="fas fa-folder"></i>
                                <?php echo htmlspecialchars($ticket['category']); ?>
                            </span>

                            <?php if ($ticket['impact']): ?>
                            <span class="badge" style="background: #fef5e7; color: #7d6608;">
                                <i class="fas fa-exclamation-circle"></i>
                                Impact: <?php echo ucfirst($ticket['impact']); ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="ticket-description">
                            <?php echo nl2br(htmlspecialchars(substr($ticket['description'], 0, 200))); ?>
                            <?php if (strlen($ticket['description']) > 200): ?>
                                <span style="color: #667eea; cursor: pointer;">... อ่านเพิ่มเติม</span>
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
                                <?php echo $ticket['comment_count']; ?> ความคิดเห็น
                            </div>
                            <?php endif; ?>

                            <?php if ($ticket['total_hours']): ?>
                            <div class="ticket-info">
                                <i class="fas fa-clock"></i>
                                <?php echo number_format($ticket['total_hours'], 1); ?> ชม.
                            </div>
                            <?php endif; ?>

                            <div>
                                <span class="sla-badge sla-<?php echo $slaStatus; ?>">
                                    <i class="fas fa-stopwatch"></i> <?php echo $slaText; ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($isAdmin): ?>
                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button class="btn btn-primary btn-sm" onclick="openViewModal(<?php echo $ticket['ticket_id']; ?>)">
                                <i class="fas fa-eye"></i> ดูรายละเอียด
                            </button>
                            <button class="btn btn-success btn-sm" onclick="openUpdateModal(<?php echo $ticket['ticket_id']; ?>)">
                                <i class="fas fa-edit"></i> อัปเดต
                            </button>
                        </div>
                        <?php else: ?>
                        <div style="margin-top: 20px;">
                            <button class="btn btn-primary btn-sm" onclick="openViewModal(<?php echo $ticket['ticket_id']; ?>)">
                                <i class="fas fa-eye"></i> ดูรายละเอียด
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
                    <i class="fas fa-plus-circle"></i> สร้าง Ticket ใหม่
                </h2>
                <span class="modal-close" onclick="closeModal('createModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label class="form-label" for="ticket_title">
                            <i class="fas fa-heading"></i> หัวข้อ *
                        </label>
                        <input type="text" name="title" id="ticket_title" class="form-control" required placeholder="ระบุหัวข้อปัญหา">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="ticket_category">
                                <i class="fas fa-folder"></i> หมวดหมู่ *
                            </label>
                            <select name="category" id="ticket_category" class="form-control" required>
                                <option value="">เลือกหมวดหมู่</option>
                                <option value="hardware">🖥️ Hardware</option>
                                <option value="software">💿 Software</option>
                                <option value="network">🌐 Network</option>
                                <option value="account">👤 Account</option>
                                <option value="printer">🖨️ Printer</option>
                                <option value="email">📧 Email</option>
                                <option value="other">📌 Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="ticket_asset">
                                <i class="fas fa-laptop"></i> สินทรัพย์ที่เกี่ยวข้อง
                            </label>
                            <select name="asset_id" id="ticket_asset" class="form-control">
                                <option value="">ไม่ระบุ</option>
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
                                <i class="fas fa-flag"></i> ความเร่งด่วน (Urgency) *
                            </label>
                            <select name="urgency" id="ticket_urgency" class="form-control" required>
                                <option value="low">🟢 Low - สามารถรอได้</option>
                                <option value="medium" selected>🟡 Medium - ควรแก้ไขเร็ว</option>
                                <option value="high">🟠 High - ต้องแก้ไขด่วน</option>
                                <option value="critical">🔴 Critical - เร่งด่วนมาก</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="ticket_impact">
                                <i class="fas fa-exclamation-circle"></i> ผลกระทบ (Impact) *
                            </label>
                            <select name="impact" id="ticket_impact" class="form-control" required>
                                <option value="low">🟢 Low - ส่งผลต่อตัวเอง</option>
                                <option value="medium" selected>🟡 Medium - ส่งผลต่อทีม</option>
                                <option value="high">🟠 High - ส่งผลต่อแผนก</option>
                                <option value="critical">🔴 Critical - ส่งผลต่อองค์กร</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="ticket_priority">
                                <i class="fas fa-tachometer-alt"></i> ระดับความสำคัญ (Priority) *
                            </label>
                            <select name="priority" id="ticket_priority" class="form-control" required>
                                <option value="low">🟢 Low</option>
                                <option value="normal" selected>🔵 Normal</option>
                                <option value="high">🟠 High</option>
                                <option value="urgent">🔴 Urgent</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="ticket_location">
                                <i class="fas fa-map-marker-alt"></i> สถานที่
                            </label>
                            <input type="text" name="location" id="ticket_location" class="form-control" placeholder="อาคาร / ชั้น / ห้อง">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="ticket_description">
                            <i class="fas fa-align-left"></i> รายละเอียดปัญหา *
                        </label>
                        <textarea name="description" id="ticket_description" class="form-control" required placeholder="อธิบายปัญหาอย่างละเอียด..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="fileInput">
                            <i class="fas fa-paperclip"></i> แนบไฟล์ (ถ้ามี)
                        </label>
                        <div class="file-upload" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>คลิกเพื่อเลือกไฟล์หรือลากไฟล์มาวาง</p>
                            <small style="color: #a0aec0;">รองรับ: JPG, PNG, PDF, DOC, XLS (สูงสุด 10MB)</small>
                        </div>
                        <input type="file" id="fileInput" name="attachments[]" multiple style="display: none;" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-check"></i> สร้าง Ticket
                        </button>
                        <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('createModal')">
                            <i class="fas fa-times"></i> ยกเลิก
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
                document.querySelector('.file-upload p').textContent = `เลือกแล้ว ${files.length} ไฟล์: ${fileList}`;
            }
        });
    </script>
</body>
</html>