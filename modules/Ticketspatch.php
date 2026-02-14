<?php
/**
 * ============================================================
 *  tickets_patch.php
 *  ส่วนที่ต้องแก้ใน tickets.php เพื่อเรียก notification
 *  ดูบรรทัดที่ต้องแก้แต่ละส่วน
 * ============================================================
 */

// ── ต้องเพิ่มที่หัวไฟล์ tickets.php ─────────────────────────────
// หลัง require_once '../includes/functions.php';
require_once 'notification_helper.php';   // เพิ่มบรรทัดนี้


// ════════════════════════════════════════════════════════════
// ส่วนที่ 1: สร้าง Ticket (action = create)
// หาบรรทัด: $_SESSION['flash_success'] = 'สร้าง Ticket สำเร็จ!...
// แก้เป็น:
// ════════════════════════════════════════════════════════════
    if ($stmt->execute()) {
        $ticketId = $stmt->insert_id;

        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            handleFileUploads($db, $ticketId, $_FILES['attachments']);
        }

        logActivity($_SESSION['user_id'], 'สร้าง IT Ticket', 'Tickets', "สร้าง: $title ($ticketNumber)");
        sendTicketNotification($ticketId, 'created');

        // ✅ แจ้งเตือน admin/IT ว่ามี ticket ใหม่
        notifyNewTicket($db, $ticketId, $ticketNumber, $title, $_SESSION['user_id']);

        $_SESSION['flash_success'] = 'สร้าง Ticket สำเร็จ! หมายเลข: ' . $ticketNumber;
        header('Location: tickets.php');
        exit;
    }


// ════════════════════════════════════════════════════════════
// ส่วนที่ 2: เพิ่ม Comment (action = add_comment)
// หาบรรทัด: if ($stmt->execute()) { addTimeline(...
// แก้เป็น:
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    $ticketId   = (int)$_POST['ticket_id'];
    $comment    = sanitize($_POST['comment']);
    $isInternal = isset($_POST['is_internal']) ? 1 : 0;

    $stmt = $db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('iisi', $ticketId, $_SESSION['user_id'], $comment, $isInternal);

    if ($stmt->execute()) {
        $commentId = $stmt->insert_id;
        addTimeline($db, $ticketId, 'comment', $isInternal ? 'เพิ่มบันทึกภายใน' : 'เพิ่มความคิดเห็น');

        // ✅ แจ้งเตือนเมื่อมี comment ใหม่
        // ดึงข้อมูล ticket และ role ของคนที่ comment
        $ticketStmt = $db->prepare("SELECT ticket_number, title, created_by FROM tickets WHERE ticket_id = ?");
        $ticketStmt->bind_param('i', $ticketId);
        $ticketStmt->execute();
        $ticketData = $ticketStmt->get_result()->fetch_assoc();

        if ($ticketData) {
            notifyNewComment(
                $db,
                $ticketId,
                $commentId,
                $ticketData['ticket_number'],
                $ticketData['title'],
                $comment,
                $_SESSION['user_id'],
                $_SESSION['role'],          // role ของคนที่ comment
                $ticketData['created_by']   // ticket creator
            );
        }

        $_SESSION['flash_success'] = 'เพิ่มความคิดเห็นสำเร็จ!';
        header('Location: tickets.php');
        exit;
    }
}