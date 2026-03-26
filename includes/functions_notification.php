<?php
/**
 * Notification Helper Functions
 * Global functions for ticket notifications
 */

/**
 * สร้าง notification เมื่อมี ticket ใหม่ → แจ้ง admin/IT
 */
if (!function_exists('notifyNewTicket')) {
function notifyNewTicket($db, $ticketId, $ticketNumber, $ticketTitle, $createdBy) {
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
function notifyNewComment($db, $ticketId, $commentId, $ticketNumber, $ticketTitle, $commentText, $commentedBy, $commentedByRole, $ticketCreatedBy) {
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
function _insertRecipients($db, $notifId, $recipients) {
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

/**
 * Email notification wrapper (placeholder - implement PHPMailer later)
 */
if (!function_exists('sendTicketNotification')) {
function sendTicketNotification($ticketId, $eventType) {
    // Currently just calls DB notification
    // Future: Add email/SMS via PHPMailer
    return true;
}
}
?>

