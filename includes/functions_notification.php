<?php
/**
 * Notification Helper Functions
 * Global functions for ticket notifications
 */
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/logger.php';

/**
 * สร้าง notification เมื่อมี ticket ใหม่ → แจ้ง admin/IT
 */
if (!function_exists('notifyNewTicket')) {
function notifyNewTicket(mysqli $db, int $ticketId, string $ticketNumber, string $ticketTitle, int $createdBy) {
    $message = "🎫 Ticket ใหม่: [{$ticketNumber}] {$ticketTitle}";
    
    // Insert notification
    $stmt = $db->prepare("
        INSERT INTO notifications (type, ticket_id, message, triggered_by) 
        VALUES ('new_ticket', ?, ?, ?)
    ");
    if (!$stmt) {
        log_error('notifyNewTicket: prepare failed', 'ERROR', [
            'file' => __FILE__,
            'line' => __LINE__,
            'trace' => $db->error
        ]);
        return false;
    }
    $stmt->bind_param('isi', $ticketId, $message, $createdBy);
    if (!$stmt->execute()) {
        log_error('notifyNewTicket: execute failed', 'ERROR', [
            'file' => __FILE__,
            'line' => __LINE__,
            'trace' => $stmt->error
        ]);
        return false;
    }
    $notifId = $db->insert_id;
    log_event('notification_created', "New ticket notification created: #{$notifId} for ticket {$ticketNumber}", $createdBy, [
        'notif_id' => $notifId,
        'ticket_id' => $ticketId,
        'ticket_number' => $ticketNumber
    ]);
    
    // Send to admin/it_support (except creator)
    $recpStmt = $db->prepare("
        SELECT user_id FROM users 
        WHERE role IN ('admin', 'it_support') AND is_active = 1 AND user_id != ?
    ");
    if (!$recpStmt) {
        log_error('notifyNewTicket: recipient prepare failed', 'ERROR', [
            'file' => __FILE__,
            'line' => __LINE__,
            'trace' => $db->error
        ]);
        return false;
    }
    $recpStmt->bind_param('i', $createdBy);
    $recpStmt->execute();
    $recipients = $recpStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $recipientCount = count($recipients);
    log_event('notification_recipients', "Ticket {$ticketNumber}: found {$recipientCount} recipients (excluded creator {$createdBy})", $createdBy, [
        'ticket_id' => $ticketId,
        'recipient_count' => $recipientCount,
        'recipients' => array_column($recipients, 'user_id')
    ]);
    
    _insertRecipients($db, $notifId, $recipients);
    return true;
}
}

/**
 * สร้าง notification เมื่อมี comment ใหม่
 */
if (!function_exists('notifyNewComment')) {
function notifyNewComment(mysqli $db, int $ticketId, int $commentId, string $ticketNumber, string $ticketTitle, string $commentText, int $commentedBy, string $commentedByRole, int $ticketCreatedBy) {
    $shortComment = mb_strlen($commentText) > 60 ? mb_substr($commentText, 0, 60) . '...' : $commentText;
    $message = "💬 [{$ticketNumber}] มีความคิดเห็นใหม่: \"{$shortComment}\"";

    $stmt = $db->prepare("
        INSERT INTO notifications (type, ticket_id, comment_id, message, triggered_by) 
        VALUES ('new_comment', ?, ?, ?, ?)
    ");
    if (!$stmt) {
        log_error('notifyNewComment: prepare failed', 'ERROR', [
            'file' => __FILE__,
            'line' => __LINE__,
            'trace' => $db->error
        ]);
        return false;
    }
    $stmt->bind_param('iisi', $ticketId, $commentId, $message, $commentedBy);
    if (!$stmt->execute()) {
        log_error('notifyNewComment: execute failed', 'ERROR', [
            'file' => __FILE__,
            'line' => __LINE__,
            'trace' => $stmt->error
        ]);
        return false;
    }
    $notifId = $db->insert_id;
    log_event('notification_comment_created', "New comment notification created: #{$notifId} for ticket {$ticketNumber}", $commentedBy, [
        'notif_id' => $notifId,
        'ticket_id' => $ticketId,
        'comment_id' => $commentId,
        'ticket_number' => $ticketNumber
    ]);

    if (in_array($commentedByRole, ['admin', 'it_support'])) {
        $recipients = [['user_id' => $ticketCreatedBy]];
        log_event('notification_comment_recipients', "Ticket {$ticketNumber}: admin/IT commented -> notify creator {$ticketCreatedBy}", $commentedBy, [
            'ticket_id' => $ticketId,
            'recipient_type' => 'ticket_creator',
            'recipient_count' => 1
        ]);
    } else {
        $recpStmt = $db->prepare("
            SELECT user_id FROM users 
            WHERE role IN ('admin', 'it_support') AND is_active = 1 AND user_id != ?
        ");
        if (!$recpStmt) {
            log_error('notifyNewComment: recipient prepare failed', 'ERROR', [
                'file' => __FILE__,
                'line' => __LINE__,
                'trace' => $db->error
            ]);
            return false;
        }
        $recpStmt->bind_param('i', $commentedBy);
        $recpStmt->execute();
        $recipients = $recpStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $recipientCount = count($recipients);
        log_event('notification_comment_recipients', "Ticket {$ticketNumber}: user commented -> found {$recipientCount} admin/IT recipients", $commentedBy, [
            'ticket_id' => $ticketId,
            'recipient_type' => 'admin_it_support',
            'recipient_count' => $recipientCount,
            'recipients' => array_column($recipients, 'user_id')
        ]);
    }

    _insertRecipients($db, $notifId, $recipients);
    return true;
}
}

/**
 * Helper: Insert recipients
 */
if (!function_exists('_insertRecipients')) {
function _insertRecipients(mysqli $db, int $notifId, array $recipients) {
    if (empty($recipients)) {
        log_event('notification_no_recipients', "No recipients found for notification {$notifId}", null, [
            'notif_id' => $notifId
        ]);
        return;
    }

    $stmt = $db->prepare("
        INSERT IGNORE INTO notification_recipients (notif_id, user_id, is_read) 
        VALUES (?, ?, 0)
    ");
    if (!$stmt) {
        log_error('_insertRecipients: prepare failed', 'ERROR', [
            'file' => __FILE__,
            'line' => __LINE__,
            'trace' => $db->error,
            'notif_id' => $notifId
        ]);
        return;
    }

    $insertedCount = 0;
    foreach ($recipients as $r) {
        $userId = $r['user_id'] ?? 0;
        $stmt->bind_param('ii', $notifId, $userId);
        if ($stmt->execute()) {
            $insertedCount++;
        } else {
            log_error('_insertRecipients: execute failed', 'ERROR', [
                'file' => __FILE__,
                'line' => __LINE__,
                'trace' => $stmt->error,
                'notif_id' => $notifId,
                'user_id' => $userId
            ]);
        }
    }

    log_event('notification_recipients_inserted', "Inserted {$insertedCount} recipients for notification {$notifId}", null, [
        'notif_id' => $notifId,
        'inserted_count' => $insertedCount,
        'total_recipients' => count($recipients)
    ]);
}
}

if (!function_exists('sendNotificationEmail')) {
function sendNotificationEmail(array $payload, array $recipients): bool {
    if (empty($recipients)) {
        return false;
    }

    $mailer = new PHPMailer();
    $mailer->isSMTP();
    $mailer->SMTPAuth = true;
    $mailer->Host = getenv('ROMAR_MAIL_HOST') ?: 'smtp.gmail.com';
    $mailer->Port = (int)(getenv('ROMAR_MAIL_PORT') ?: 587);
    $mailer->Username = getenv('ROMAR_MAIL_USERNAME') ?: '';
    $mailer->Password = getenv('ROMAR_MAIL_PASSWORD') ?: '';
    $mailer->SMTPSecure = getenv('ROMAR_MAIL_ENCRYPTION') ?: 'tls';
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom(
        getenv('ROMAR_MAIL_FROM') ?: $mailer->Username,
        getenv('ROMAR_MAIL_FROM_NAME') ?: 'Romar System'
    );
    $mailer->Subject = $payload['subject'] ?? 'Romar Notification';
    $mailer->Body = $payload['body'] ?? '';
    $mailer->AltBody = $payload['alt'] ?? strip_tags($mailer->Body);
    $mailer->isHTML(true);

    foreach ($recipients as $recipient) {
        $email = $recipient['email'] ?? '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mailer->addAddress($email, $recipient['full_name'] ?? '');
        }
    }

    try {
        return $mailer->send();
    } catch (\Throwable $e) {
        error_log('Notification email failed: ' . $e->getMessage());
        return false;
    }
}
}

if (!function_exists('sendNotificationSlack')) {
function sendNotificationSlack(array $payload): bool {
    $webhook = getenv('ROMAR_NOTIFICATION_SLACK_WEBHOOK');
    if (empty($webhook)) {
        return false;
    }

    $text = $payload['slack'] ?? ($payload['subject'] ?? 'Romar alert');
    $body = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
    $headers = ['Content-Type: application/json'];

    if (function_exists('curl_init')) {
        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_handle_close($ch);
        return $response !== false && $status >= 200 && $status < 300;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 5,
        ],
    ]);
    $result = @file_get_contents($webhook, false, $context);
    return $result !== false;
}
}

if (!function_exists('buildTicketNotificationPayload')) {
function buildTicketNotificationPayload(array $ticket, string $eventType): array {
    $eventLabels = [
        'created' => 'New ticket created',
        'updated' => 'Ticket updated',
    ];
    $eventLabel = $eventLabels[$eventType] ?? ucfirst($eventType);
    $ticketNumber = $ticket['ticket_number'] ?? 'Unknown';
    $title = $ticket['title'] ?? 'Untitled ticket';
    $priority = $ticket['priority'] ?? 'normal';
    $status = $ticket['status'] ?? 'open';
    $link = (defined('BASE_URL') ? BASE_URL : '') . 'modules/ticket_view.php?id=' . ($ticket['ticket_id'] ?? 0);

    $subject = "[{$ticketNumber}] {$eventLabel}";
    $bodyLines = [
        "Event: {$eventLabel}",
        "Ticket: {$ticketNumber}",
        "Title: {$title}",
        "Priority: {$priority}",
        "Status: {$status}",
        "Link: {$link}",
    ];
    if (!empty($ticket['description'])) {
        $bodyLines[] = '';
        $bodyLines[] = 'Description:';
        $bodyLines[] = $ticket['description'];
    }

    return [
        'subject' => $subject,
        'body' => implode("\n", $bodyLines),
        'slack' => "{$eventLabel}: {$title} - <{$link}|View ticket>\nStatus: {$status} | Priority: {$priority}",
        'link' => $link,
    ];
}
}

/**
 * Gather notification recipients (admin/it_support) for email alerts
 */
if (!function_exists('gatherNotificationRecipients')) {
function gatherNotificationRecipients(mysqli $db, ?int $excludeUserId = null): array {
    $sql = "
        SELECT email, full_name 
        FROM users 
        WHERE role IN ('admin', 'it_support') AND is_active = 1
    ";
    if ($excludeUserId !== null) {
        $sql .= " AND user_id != ?";
    }
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        log_error('gatherNotificationRecipients: prepare failed', 'ERROR', [
            'file' => __FILE__,
            'line' => __LINE__,
            'trace' => $db->error
        ]);
        return [];
    }
    if ($excludeUserId !== null) {
        $stmt->bind_param('i', $excludeUserId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
}

/**
 * Email notification wrapper (placeholder - implement PHPMailer later)
 */
if (!function_exists('sendTicketNotification')) {
function sendTicketNotification(int $ticketId, string $eventType, ?int $excludeUserId = null) {
    if (!function_exists('getDB')) {
        return false;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM tickets WHERE ticket_id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    if (!$ticket) {
        return false;
    }

    $payload = buildTicketNotificationPayload($ticket, $eventType);
    $recipients = gatherNotificationRecipients($db, $excludeUserId);
    $sentEmail = sendNotificationEmail($payload, $recipients);
    $sentSlack = sendNotificationSlack($payload);

    return $sentEmail || $sentSlack;
}
}
?>

