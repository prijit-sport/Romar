<?php
/**
 * Notification Helper Functions
 * Global functions for ticket notifications
 */
use PHPMailer\PHPMailer\PHPMailer;

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
    $stmt->bind_param('isi', $ticketId, $message, $createdBy);
    if (!$stmt->execute()) return false;
    $notifId = $db->insert_id;
    
    // Send to admin/it_support (except creator)
    $recpStmt = $db->prepare("
        SELECT user_id FROM users 
        WHERE role IN ('admin', 'it_support') AND status = 'active' AND user_id != ?
    ");
    $recpStmt->bind_param('i', $createdBy);
    $recpStmt->execute();
    $recipients = $recpStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
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
    $stmt->bind_param('iisi', $ticketId, $commentId, $message, $commentedBy);
    if (!$stmt->execute()) return false;
    $notifId = $db->insert_id;

    if (in_array($commentedByRole, ['admin', 'it_support'])) {
        $recipients = [['user_id' => $ticketCreatedBy]];
    } else {
        $recpStmt = $db->prepare("
            SELECT user_id FROM users 
            WHERE role IN ('admin', 'it_support') AND status = 'active' AND user_id != ?
        ");
        $recpStmt->bind_param('i', $commentedBy);
        $recpStmt->execute();
        $recipients = $recpStmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    if (empty($recipients)) return;
    
    $stmt = $db->prepare("
        INSERT IGNORE INTO notification_recipients (notif_id, user_id, is_read) 
        VALUES (?, ?, 0)
    ");
    foreach ($recipients as $r) {
        $stmt->bind_param('ii', $notifId, $r['user_id']);
        $stmt->execute();
    }
}
}

if (!function_exists('gatherNotificationRecipients')) {
function gatherNotificationRecipients(mysqli $db, ?int $excludeUserId = null): array {
    $conditions = "role IN ('admin', 'it_support') AND status = 'active' AND is_active = 1";
    $sql = "SELECT user_id, full_name, email FROM users WHERE {$conditions}";
    if ($excludeUserId) {
        $sql .= " AND user_id != ?";
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if ($excludeUserId) {
        $stmt->bind_param('i', $excludeUserId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $recipients = [];
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $recipients[$row['email']] = [
                'user_id' => (int)$row['user_id'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
            ];
        }
    }

    $extra = getenv('ROMAR_NOTIFICATION_EMAIL_TO') ?: '';
    if ($extra !== '') {
        $emails = preg_split('/[\s,;]+/', $extra, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($emails as $email) {
            $email = trim($email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[$email] = [
                    'user_id' => 0,
                    'full_name' => 'Notification Recipient',
                    'email' => $email,
                ];
            }
        }
    }

    return array_values($recipients);
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

