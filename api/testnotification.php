<?php
/**
 * ============================================================
 *  notification_helper.php
 *  ฟังก์ชันสร้าง notification และส่งให้ผู้รับที่เกี่ยวข้อง
 *  
 *  วิธีใช้: require_once 'notification_helper.php';
 *  หรือ copy function เหล่านี้ไปวางใน includes/functions.php
 * ============================================================
 */

/**
 * สร้าง notification เมื่อมี ticket ใหม่
 * → แจ้งเตือน admin และ it_support ทุกคน
 */
function notifyNewTicket($db, $ticketId, $ticketNumber, $ticketTitle, $createdBy) {
    $message = "🎫 Ticket ใหม่: [{$ticketNumber}] {$ticketTitle}";
    
    // บันทึก notification
    $stmt = $db->prepare("
        INSERT INTO notifications (type, ticket_id, message, triggered_by) 
        VALUES ('new_ticket', ?, ?, ?)
    ");
    $stmt->bind_param('isi', $ticketId, $message, $createdBy);
    if (!$stmt->execute()) return false;
    $notifId = $db->insert_id;
    
    // ส่งให้ admin และ it_support ทุกคน (ยกเว้นคนสร้าง ticket เอง)
    $recpStmt = $db->prepare("
        SELECT user_id FROM users 
        WHERE role IN ('admin', 'it_support') 
        AND status = 'active'
        AND user_id != ?
    ");
    $recpStmt->bind_param('i', $createdBy);
    $recpStmt->execute();
    $recipients = $recpStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    _insertRecipients($db, $notifId, $recipients);
    return true;
}

/**
 * สร้าง notification เมื่อมี comment ใหม่
 * Logic:
 *  - ถ้า user ทั่วไป comment → แจ้ง admin + it_support
 *  - ถ้า admin/IT comment → แจ้ง ticket creator
 */
function notifyNewComment($db, $ticketId, $commentId, $ticketNumber, $ticketTitle, $commentText, $commentedBy, $commentedByRole, $ticketCreatedBy) {
    $shortComment = mb_strlen($commentText) > 60 
        ? mb_substr($commentText, 0, 60) . '...' 
        : $commentText;

    $message = "💬 [{$ticketNumber}] มีความคิดเห็นใหม่: \"{$shortComment}\"";

    // บันทึก notification
    $stmt = $db->prepare("
        INSERT INTO notifications (type, ticket_id, comment_id, message, triggered_by) 
        VALUES ('new_comment', ?, ?, ?, ?)
    ");
    $stmt->bind_param('iisi', $ticketId, $commentId, $message, $commentedBy);
    if (!$stmt->execute()) return false;
    $notifId = $db->insert_id;

    if (in_array($commentedByRole, ['admin', 'it_support'])) {
        // Admin/IT comment → แจ้ง ticket creator
        $recipients = [['user_id' => $ticketCreatedBy]];
    } else {
        // User comment → แจ้ง admin + it_support ทุกคน (ยกเว้นคนที่ comment เอง)
        $recpStmt = $db->prepare("
            SELECT user_id FROM users 
            WHERE role IN ('admin', 'it_support') 
            AND status = 'active'
            AND user_id != ?
        ");
        $recpStmt->bind_param('i', $commentedBy);
        $recpStmt->execute();
        $recipients = $recpStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    _insertRecipients($db, $notifId, $recipients);
    return true;
}

/**
 * Helper: บันทึก recipients ลงตาราง
 */
function _insertRecipients($db, $notifId, $recipients) {
    if (empty($recipients)) return;
    
    $stmt = $db->prepare("
        INSERT IGNORE INTO notification_recipients (notif_id, user_id) 
        VALUES (?, ?)
    ");
    foreach ($recipients as $r) {
        $stmt->bind_param('ii', $notifId, $r['user_id']);
        $stmt->execute();
    }
}