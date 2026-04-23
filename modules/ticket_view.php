<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

csrf_token();
apply_security_headers(['allow_inline' => false]);
$cspNonce = htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAdmin = $_SESSION['role'] === 'admin';

if (!$ticketId) {
    header('Location: tickets.php');
    exit;
}

// Get ticket details
$stmt = $db->prepare("
    SELECT t.*, 
    creator.full_name AS creator_name, creator.email AS creator_email,
    assignee.full_name AS assignee_name,
    a.asset_name, a.asset_tag, a.asset_type
    FROM tickets t
    LEFT JOIN users creator ON t.created_by = creator.user_id
    LEFT JOIN users assignee ON t.assigned_to = assignee.user_id
    LEFT JOIN assets a ON t.asset_id = a.asset_id
    WHERE t.ticket_id = ?
");
$stmt->bind_param('i', $ticketId);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}

// Check permission
if (!$isAdmin && $ticket['created_by'] != $_SESSION['user_id']) {
    die('Access Denied');
}

// ✅ Auto mark-read: เมื่อเปิดดู ticket นี้ → mark notification ที่เกี่ยวกับ ticket นี้ว่าอ่านแล้ว
$markStmt = $db->prepare("
    UPDATE notification_recipients nr
    INNER JOIN notifications n ON nr.notif_id = n.notif_id
    SET nr.is_read = 1, nr.read_at = NOW()
    WHERE n.ticket_id = ?
    AND nr.user_id = ?
    AND nr.is_read = 0
");
$markStmt->bind_param('ii', $ticketId, $_SESSION['user_id']);
$markStmt->execute();

// Get comments
$stmtComments = $db->prepare("
    SELECT tc.*, u.full_name, u.role
    FROM ticket_comments tc
    LEFT JOIN users u ON tc.user_id = u.user_id
    WHERE tc.ticket_id = ?
    ORDER BY tc.created_at ASC
");
$stmtComments->bind_param('i', $ticketId);
$stmtComments->execute();
$comments = $stmtComments->get_result()->fetch_all(MYSQLI_ASSOC);

// Get timeline
$stmtTimeline = $db->prepare("
    SELECT tt.*, u.full_name
    FROM ticket_timeline tt
    LEFT JOIN users u ON tt.user_id = u.user_id
    WHERE tt.ticket_id = ?
    ORDER BY tt.created_at DESC
");
$stmtTimeline->bind_param('i', $ticketId);
$stmtTimeline->execute();
$timeline = $stmtTimeline->get_result()->fetch_all(MYSQLI_ASSOC);

// Get attachments
$attachmentDateColumn = 'uploaded_at';
$attachmentDateResult = $db->query("SHOW COLUMNS FROM ticket_attachments LIKE 'uploaded_at'");
if (!$attachmentDateResult || $attachmentDateResult->num_rows === 0) {
    $attachmentDateColumn = 'created_at';
}

$stmtAttach = $db->prepare("
    SELECT ta.*, u.full_name AS uploader_name, ta.$attachmentDateColumn AS attachment_created_at
    FROM ticket_attachments ta
    LEFT JOIN users u ON ta.uploaded_by = u.user_id
    WHERE ta.ticket_id = ?
    ORDER BY ta.$attachmentDateColumn DESC
");
$stmtAttach->bind_param('i', $ticketId);
$stmtAttach->execute();
$attachments = $stmtAttach->get_result()->fetch_all(MYSQLI_ASSOC);

// Get time tracking
$stmtTime = $db->prepare("
    SELECT tt.*, u.full_name
    FROM ticket_time_tracking tt
    LEFT JOIN users u ON tt.user_id = u.user_id
    WHERE tt.ticket_id = ?
    ORDER BY tt.logged_at DESC
");
$stmtTime->bind_param('i', $ticketId);
$stmtTime->execute();
$timeTracking = $stmtTime->get_result()->fetch_all(MYSQLI_ASSOC);

// Get related tickets
$stmtRelated = $db->prepare("
    SELECT tr.*, t.ticket_number, t.title, t.status
    FROM ticket_relations tr
    LEFT JOIN tickets t ON tr.related_ticket_id = t.ticket_id
    WHERE tr.ticket_id = ?
");
$stmtRelated->bind_param('i', $ticketId);
$stmtRelated->execute();
$relatedTickets = $stmtRelated->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate SLA status
$slaStatus = 'ok';
$slaText = '';
if ($ticket['sla_due_date'] && $ticket['status'] !== 'resolved' && $ticket['status'] !== 'closed') {
    $now = time();
    $due = strtotime($ticket['sla_due_date']);
    $diff = $due - $now;
    $hoursLeft = round($diff / 3600, 1);
    
    if ($diff < 0) {
        $slaStatus = 'overdue';
        $slaText = 'เกินกำหนด ' . abs($hoursLeft) . ' ชั่วโมง';
    } elseif ($diff < 7200) {
        $slaStatus = 'warning';
        $slaText = 'เหลือ ' . $hoursLeft . ' ชั่วโมง';
    } else {
        $slaText = 'เหลือ ' . $hoursLeft . ' ชั่วโมง';
    }
} elseif ($ticket['resolved_at']) {
    $slaText = 'เสร็จสิ้นแล้ว';
}

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($ticket['ticket_number']); ?> - Ticket Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style nonce="<?php echo $cspNonce; ?>">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #f0f3f7 100%);
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e8ecf1;
        }

        .ticket-number-badge {
            font-size: 1.6em;
            font-weight: 700;
            color: #1e40af;
            letter-spacing: -0.5px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #1e40af;
            color: white;
        }

        .btn-primary:hover {
            background: #1e3a8a;
            box-shadow: 0 2px 8px rgba(30, 64, 175, 0.2);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            border: 1px solid #e8ecf1;
        }

        .card-title {
            font-size: 1.25em;
            font-weight: 700;
            margin-bottom: 18px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #f0f3f7;
            padding-bottom: 12px;
        }

        .ticket-title-main {
            font-size: 1.8em;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }

        .status-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1.5px solid;
            background: transparent;
        }

        .badge-status-new { border-color: #3b82f6; color: #1e40af; }
        .badge-status-assigned { border-color: #8b5cf6; color: #6d28d9; }
        .badge-status-in_progress { border-color: #f59e0b; color: #b45309; }
        .badge-status-resolved { border-color: #10b981; color: #047857; }
        .badge-status-pending { border-color: #f097a7; color: #9c1c4a; }
        .badge-status-closed { border-color: #9ca3af; color: #374151; }

        .badge-priority-urgent { border-color: #ef4444; color: #dc2626; }
        .badge-priority-high { border-color: #f97316; color: #c2410c; }
        .badge-priority-normal { border-color: #3b82f6; color: #1e40af; }
        .badge-priority-low { border-color: #10b981; color: #047857; }

        .badge-impact-critical { border-color: #ef4444; color: #dc2626; }
        .badge-impact-high { border-color: #f97316; color: #c2410c; }
        .badge-impact-medium { border-color: #f59e0b; color: #b45309; }
        .badge-impact-low { border-color: #10b981; color: #047857; }
        .badge-impact-normal { border-color: #6b7280; color: #4b5563; }

        .badge-urgency-critical { border-color: #ef4444; color: #dc2626; }
        .badge-urgency-high { border-color: #f97316; color: #c2410c; }
        .badge-urgency-medium { border-color: #f59e0b; color: #b45309; }
        .badge-urgency-low { border-color: #10b981; color: #047857; }
        .badge-urgency-normal { border-color: #6b7280; color: #4b5563; }

        .badge-category { border-color: #8b5cf6; color: #6d28d9; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 3px solid #e5e7eb;
        }

        .info-label {
            font-size: 0.8em;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 0.95em;
            color: #1f2937;
            font-weight: 500;
        }

        .description-box {
            background: #f9fafb;
            padding: 16px;
            border-radius: 8px;
            border-left: 3px solid #1e40af;
            line-height: 1.6;
            color: #374151;
            margin-bottom: 20px;
        }

        .sla-indicator {
            padding: 12px 16px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            border-left: 3px solid;
            background: transparent;
        }

        .sla-ok {
            border-left-color: #10b981;
            background: #f0fdf4;
            color: #065f46;
        }

        .sla-warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
            color: #92400e;
        }

        .sla-overdue {
            border-left-color: #ef4444;
            background: #fef2f2;
            color: #7f1d1d;
        }

        .comment-item {
            padding: 16px;
            border-radius: 8px;
            background: #f9fafb;
            margin-bottom: 12px;
            border-left: 3px solid #e5e7eb;
        }

        .comment-item.internal {
            background: #fef7f0;
            border-left-color: #f59e0b;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            align-items: center;
        }

        .comment-author {
            font-weight: 700;
            color: #1f2937;
        }

        .comment-time {
            font-size: 0.8em;
            color: #9ca3af;
        }

        .comment-text {
            color: #4b5563;
            line-height: 1.6;
        }

        .timeline-item {
            padding: 12px;
            padding-left: 24px;
            border-left: 2px solid #e5e7eb;
            margin-left: 0;
            margin-bottom: 12px;
            position: relative;
        }

        .timeline-item:before {
            content: '';
            position: absolute;
            left: -8px;
            top: 12px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #1e40af;
            border: 2px solid white;
        }

        .timeline-text {
            color: #374151;
            margin-bottom: 4px;
        }

        .timeline-time {
            font-size: 0.75em;
            color: #9ca3af;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e5e7eb;
        }

        .attachment-icon {
            width: 44px;
            height: 44px;
            background: #f0f3f7;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e40af;
            font-size: 1.2em;
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 2px;
            font-size: 0.9em;
        }

        .attachment-meta {
            font-size: 0.8em;
            color: #9ca3af;
        }

        .time-entry {
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e5e7eb;
        }

        .time-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .hours-badge {
            background: #f0f3f7;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #1f2937;
            font-size: 0.95em;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95em;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        @media (max-width: 968px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-bar">
            <div class="ticket-number-badge">
                <i class="fas fa-ticket-alt"></i> <?php echo htmlspecialchars($ticket['ticket_number']); ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="tickets.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> กลับ
                </a>
                <?php if ($isAdmin): ?>
                <a href="ticket_update.php?id=<?php echo $ticketId; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> แก้ไข
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="main-grid">
            <!-- Left Column -->
            <div>
                <!-- Ticket Details -->
                <div class="card">
                    <h1 class="ticket-title-main"><?php echo htmlspecialchars($ticket['title']); ?></h1>

                    <div class="status-badges">
                        <span class="badge badge-status-<?php echo $ticket['status']; ?>">
                            <i class="fas fa-circle-dot"></i>
                            <?php 
                            $statusLabels = [
                                'new' => 'ใหม่',
                                'assigned' => 'มอบหมาย',
                                'in_progress' => 'ดำเนินการ',
                                'pending' => 'รอดำเนินการ',
                                'resolved' => 'แก้ไขแล้ว',
                                'closed' => 'ปิด'
                            ];
                            echo $statusLabels[$ticket['status']] ?? ucfirst($ticket['status']);
                            ?>
                        </span>

                        <span class="badge badge-priority-<?php echo $ticket['priority']; ?>">
                            <i class="fas fa-arrow-up"></i>
                            <?php echo ucfirst($ticket['priority']); ?>
                        </span>

                        <span class="badge badge-impact-<?php echo strtolower($ticket['impact'] ?? 'normal'); ?>">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo htmlspecialchars($ticket['impact'] ?? 'N/A'); ?>
                        </span>

                        <span class="badge badge-urgency-<?php echo strtolower($ticket['urgency'] ?? 'normal'); ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <?php echo htmlspecialchars($ticket['urgency'] ?? 'N/A'); ?>
                        </span>

                        <span class="badge badge-category">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($ticket['category'] ?? 'N/A'); ?>
                        </span>
                    </div>

                    <?php if ($slaText): ?>
                    <div class="sla-indicator sla-<?php echo $slaStatus; ?>">
                        <i class="fas fa-stopwatch"></i>
                        <strong>SLA:</strong> <?php echo $slaText; ?>
                        <?php if ($ticket['sla_due_date']): ?>
                        <span style="margin-left: auto;">
                            กำหนดเวลา: <?php echo date('d/m/Y H:i', strtotime($ticket['sla_due_date'])); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="card-title">
                        <i class="fas fa-info-circle"></i> รายละเอียด
                    </div>

                    <div class="description-box">
                        <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                    </div>

                    <?php if ($ticket['resolution']): ?>
                    <div class="card-title" style="margin-top: 30px;">
                        <i class="fas fa-check-circle"></i> การแก้ไข
                    </div>
                    <div class="description-box" style="border-left-color: #48bb78;">
                        <?php echo nl2br(htmlspecialchars($ticket['resolution'])); ?>
                    </div>
                    <?php endif; ?>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-user"></i> ผู้แจ้ง
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($ticket['creator_name']); ?>
                            </div>
                        </div>

                        <?php if ($ticket['assignee_name']): ?>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-user-check"></i> ผู้รับผิดชอบ
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($ticket['assignee_name']); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-calendar-plus"></i> วันที่สร้าง
                            </div>
                            <div class="info-value">
                                <?php echo date('d/m/Y H:i:s', strtotime($ticket['created_at'])); ?>
                            </div>
                        </div>

                        <?php if ($ticket['updated_at']): ?>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-calendar-check"></i> อัปเดตล่าสุด
                            </div>
                            <div class="info-value">
                                <?php echo date('d/m/Y H:i:s', strtotime($ticket['updated_at'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($ticket['location']): ?>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-map-marker-alt"></i> สถานที่
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($ticket['location']); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($ticket['asset_name']): ?>
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-laptop"></i> สินทรัพย์
                            </div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($ticket['asset_name'] . ' (' . $ticket['asset_tag'] . ')'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Comments -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-comments"></i> ความคิดเห็น (<?php echo count($comments); ?>)
                    </div>

                    <?php if (empty($comments)): ?>
                    <p style="color: #a0aec0; text-align: center; padding: 40px 0;">
                        ยังไม่มีความคิดเห็น
                    </p>
                    <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                    <div class="comment-item <?php echo $comment['is_internal'] ? 'internal' : ''; ?>">
                        <div class="comment-header">
                            <div>
                                <span class="comment-author">
                                    <?php echo htmlspecialchars($comment['full_name']); ?>
                                </span>
                                <?php if ($comment['is_internal']): ?>
                                <span class="badge" style="background: #fed7d7; color: #742a2a; margin-left: 10px;">
                                    <i class="fas fa-lock"></i> Internal
                                </span>
                                <?php endif; ?>
                            </div>
                            <span class="comment-time">
                                <?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?>
                            </span>
                        </div>
                        <div class="comment-text">
                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Add Comment Form -->
                    <form method="POST" action="tickets.php" style="margin-top: 25px;">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticketId; ?>">
                        
                        <div class="form-group">
                            <label class="form-label">เพิ่มความคิดเห็น</label>
                            <textarea name="comment" class="form-control" required placeholder="พิมพ์ความคิดเห็นของคุณ..."></textarea>
                        </div>

                        <?php if ($isAdmin): ?>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_internal" value="1">
                                <span>บันทึกภายใน (เฉพาะทีม IT เท่านั้น)</span>
                            </label>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> ส่งความคิดเห็น
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Attachments -->
                <?php if (!empty($attachments)): ?>
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-paperclip"></i> ไฟล์แนบ (<?php echo count($attachments); ?>)
                    </div>
                    <?php foreach ($attachments as $att): ?>
                    <div class="attachment-item">
                        <div class="attachment-icon">
                            <i class="fas fa-file"></i>
                        </div>
                        <div class="attachment-info">
                            <div class="attachment-name">
                                <?php echo htmlspecialchars($att['file_name']); ?>
                            </div>
                            <div class="attachment-meta">
                                <?php echo number_format($att['file_size'] / 1024, 1); ?> KB • 
                                <?php echo htmlspecialchars($att['uploader_name']); ?> • 
                                <?php echo date('d/m/Y H:i', strtotime($att['attachment_created_at'])); ?>
                            </div>
                        </div>
                        <a href="../uploads/tickets/<?php echo htmlspecialchars($att['file_name']); ?>" 
                           target="_blank" class="btn btn-secondary" style="padding: 8px 16px;">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Time Tracking -->
                <?php if (!empty($timeTracking)): ?>
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-clock"></i> บันทึกเวลาทำงาน
                    </div>
                    <?php 
                    $totalHours = 0;
                    foreach ($timeTracking as $time): 
                        $totalHours += $time['hours_spent'];
                    ?>
                    <div class="time-entry">
                        <div class="time-header">
                            <strong><?php echo htmlspecialchars($time['full_name']); ?></strong>
                            <span class="hours-badge">
                                <?php echo number_format($time['hours_spent'], 1); ?> ชม.
                            </span>
                        </div>
                        <div style="color: #4a5568; margin-bottom: 5px;">
                            <?php echo htmlspecialchars($time['work_description']); ?>
                        </div>
                        <div style="font-size: 0.85em; color: #a0aec0;">
                            <?php echo date('d/m/Y H:i', strtotime($time['logged_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #e2e8f0; font-weight: 700;">
                        รวมทั้งหมด: <?php echo number_format($totalHours, 1); ?> ชั่วโมง
                    </div>
                </div>
                <?php endif; ?>

                <!-- Related Tickets -->
                <?php if (!empty($relatedTickets)): ?>
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-link"></i> Ticket ที่เกี่ยวข้อง
                    </div>
                    <?php foreach ($relatedTickets as $rel): ?>
                    <div style="padding: 12px; background: #f7fafc; border-radius: 8px; margin-bottom: 10px;">
                        <div style="font-weight: 600; color: #667eea; margin-bottom: 5px;">
                            <a href="ticket_view.php?id=<?php echo $rel['related_ticket_id']; ?>" style="text-decoration: none; color: inherit;">
                                <?php echo htmlspecialchars($rel['ticket_number']); ?>
                            </a>
                        </div>
                        <div style="color: #4a5568; font-size: 0.95em;">
                            <?php echo htmlspecialchars($rel['title']); ?>
                        </div>
                        <div style="margin-top: 5px;">
                            <span class="badge badge-status-<?php echo $rel['status']; ?>" style="font-size: 0.8em; padding: 4px 10px;">
                                <?php echo ucfirst($rel['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Timeline -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-history"></i> ประวัติ
                    </div>
                    <?php if (empty($timeline)): ?>
                    <p style="color: #a0aec0; text-align: center; padding: 20px 0;">
                        ยังไม่มีประวัติ
                    </p>
                    <?php else: ?>
                    <?php foreach ($timeline as $item): ?>
                    <div class="timeline-item">
                        <div class="timeline-text">
                            <?php if ($item['full_name']): ?>
                            <strong><?php echo htmlspecialchars($item['full_name']); ?></strong>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($item['description']); ?>
                            <?php if (isset($item['old_value']) && isset($item['new_value']) && $item['old_value'] && $item['new_value']): ?>
                            <br><small><?php echo htmlspecialchars($item['old_value']); ?> → <?php echo htmlspecialchars($item['new_value']); ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="timeline-time">
                            <?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
