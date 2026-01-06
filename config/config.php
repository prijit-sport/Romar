<?php
/**
 * Configuration File
 * ไฟล์ตั้งค่าระบบทั้งหมด
 */

// เริ่ม Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// ตั้งค่าการแสดง Error 
// สำหรับ Production: ปิดการแสดง error
// สำหรับ Development: เปิดเพื่อดู error
error_reporting(E_ALL);
ini_set('display_errors', 1); // เปลี่ยนเป็น 0 เพื่อไม่แสดง error
ini_set('log_errors', 1); // เขียน error ลงไฟล์ log แทน
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// ========================================
// ค่าคงที่ของระบบ
// ========================================

// ชื่อระบบ
define('SITE_NAME', 'ระบบจัดการหอพัก');
define('SITE_NAME_EN', 'ROMARDORMITORY-MANAGEMENT System');

// URL พื้นฐาน (ปรับตามโครงสร้างของคุณ)
define('BASE_URL', 'http://localhost/ROMARDORMITORY-MANAGEMENT/');

// Path ของโปรเจค
define('ROOT_PATH', dirname(__DIR__));

// Upload Settings
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Session timeout (minutes)
define('SESSION_TIMEOUT', 60);

// ========================================
// ค่าคงที่สำหรับ Tickets
// ========================================
define('TICKET_PRIORITIES', ['low' => 'ต่ำ', 'medium' => 'ปานกลาง', 'high' => 'สูง', 'urgent' => 'เร่งด่วน']);
define('TICKET_STATUSES', ['open' => 'เปิด', 'in_progress' => 'กำลังดำเนินการ', 'resolved' => 'แก้ไขแล้ว', 'closed' => 'ปิด']);

// ========================================
// ค่าคงที่สำหรับ Announcements
// ========================================
define('ANNOUNCEMENT_PRIORITIES', ['normal' => 'ปกติ', 'important' => 'สำคัญ', 'urgent' => 'เร่งด่วน']);

// ========================================
// ค่าคงที่สำหรับ Conversations
// ========================================
define('CONVERSATION_TYPES', ['phone' => 'โทรศัพท์', 'email' => 'อีเมล', 'in_person' => 'พบปะโดยตรง', 'other' => 'อื่นๆ']);

// ========================================
// Helper Functions
// ========================================

/**
 * Redirect to URL
 */
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current username
 */
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

/**
 * Get current user full name
 */
function getCurrentUserFullName() {
    return $_SESSION['full_name'] ?? 'ผู้ใช้';
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Format date to Thai format
 */
function formatDateThai($date) {
    if (empty($date)) return '-';
    
    $timestamp = strtotime($date);
    $thaiMonths = [
        1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน',
        'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม',
        'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    
    $day = date('j', $timestamp);
    $month = $thaiMonths[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp) + 543;
    $time = date('H:i', $timestamp);
    
    return "$day $month $year เวลา $time น.";
}

/**
 * Format date to short Thai format
 */
function formatDateShort($date) {
    if (empty($date)) return '-';
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = date('m', $timestamp);
    $year = date('Y', $timestamp) + 543;
    
    return "$day/$month/$year";
}

/**
 * Sanitize input
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Set flash message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // success, error, warning, info
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Check session timeout
 */
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        
        if ($elapsed > (SESSION_TIMEOUT * 60)) {
            session_unset();
            session_destroy();
            redirect('auth/login.php?timeout=1');
        }
    }
    
    $_SESSION['last_activity'] = time();
}

// Auto-check session timeout for logged in users
if (isLoggedIn() && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    checkSessionTimeout();
}
?>