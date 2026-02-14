<?php
/**
 * Helper Functions - MySQL Version (Final Fixed)
 * ป้องกันการประกาศฟังก์ชันซ้ำด้วย function_exists()
 */

require_once __DIR__ . '/../config/database.php';

// เช็คว่ามี config.php หรือไม่
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}

/**
 * Verify user login
 */
if (!function_exists('verifyLogin')) {
    function verifyLogin($username, $password) {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($password, $user['password'])) {
            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->bind_param('i', $user['user_id']);
            $updateStmt->execute();
            
            return $user;
        }
        
        return false;
    }
}

/**
 * Get user by ID
 */
if (!function_exists('getUserById')) {
    function getUserById($userId) {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}

/**
 * Get all users
 */
if (!function_exists('getAllUsers')) {
    function getAllUsers($role = null) {
        $db = getDB();
        
        if ($role) {
            $stmt = $db->prepare("SELECT * FROM users WHERE role = ? ORDER BY created_at DESC");
            $stmt->bind_param('s', $role);
        } else {
            $stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC");
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        return $users;
    }
}

/**
 * Log activity
 */
if (!function_exists('logActivity')) {
    function logActivity($userId, $action, $module = '', $description = '') {
        $db = getDB();
        
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, module, description, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt->bind_param('issss', $userId, $action, $module, $description, $ip);
        $stmt->execute();
    }
}

/**
 * Get dashboard statistics
 */
if (!function_exists('getDashboardStats')) {
    function getDashboardStats() {
        $db = getDB();
        
        $stats = [];
        
        // Total tickets
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM tickets");
            $row = $result->fetch_assoc();
            $stats['total_tickets'] = $row['count'];
        } catch (Exception $e) {
            $stats['total_tickets'] = 0;
        }
        
        // Open tickets
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM tickets WHERE status = 'open'");
            $row = $result->fetch_assoc();
            $stats['open_tickets'] = $row['count'];
        } catch (Exception $e) {
            $stats['open_tickets'] = 0;
        }
        
        // Active announcements
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM announcements WHERE is_active = 1");
            $row = $result->fetch_assoc();
            $stats['active_announcements'] = $row['count'];
        } catch (Exception $e) {
            $stats['active_announcements'] = 0;
        }
        
        // Total users
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
            $row = $result->fetch_assoc();
            $stats['total_users'] = $row['count'];
        } catch (Exception $e) {
            $stats['total_users'] = 0;
        }
        
        // Today's bookings
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM bookings WHERE booking_date = CURDATE() AND status = 'approved'");
            $row = $result->fetch_assoc();
            $stats['today_bookings'] = $row['count'];
        } catch (Exception $e) {
            $stats['today_bookings'] = 0;
        }
        
        // Total documents
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM documents");
            $row = $result->fetch_assoc();
            $stats['total_documents'] = $row['count'];
        } catch (Exception $e) {
            $stats['total_documents'] = 0;
        }
        
        return $stats;
    }
}

/**
 * Get recent activities
 */
if (!function_exists('getRecentActivities')) {
    function getRecentActivities($limit = 10) {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT a.*, u.full_name, u.username
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.user_id
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $activities = [];
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        
        return $activities;
    }
}

/**
 * Get active announcements
 */
if (!function_exists('getActiveAnnouncements')) {
    function getActiveAnnouncements($limit = 5) {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT a.*, u.full_name as publisher_name
            FROM announcements a
            LEFT JOIN users u ON a.published_by = u.user_id
            WHERE a.is_active = 1
            AND (a.expire_date IS NULL OR a.expire_date > NOW())
            ORDER BY 
                CASE a.priority 
                    WHEN 'urgent' THEN 1
                    WHEN 'important' THEN 2
                    ELSE 3
                END,
                a.publish_date DESC
            LIMIT ?
        ");
        
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $announcements = [];
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
        
        return $announcements;
    }
}

/**
 * Generate ticket number
 */
if (!function_exists('generateTicketNumber')) {
    function generateTicketNumber() {
        $db = getDB();
        
        $result = $db->query("SELECT ticket_number FROM tickets ORDER BY ticket_id DESC LIMIT 1");
        $lastTicket = $result->fetch_assoc();
        
        if ($lastTicket) {
            $lastNumber = (int)substr($lastTicket['ticket_number'], 6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return 'TK' . date('Y') . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}

/**
 * Check if room is available
 */
if (!function_exists('isRoomAvailable')) {
    function isRoomAvailable($roomId, $date, $startTime, $endTime, $excludeBookingId = null) {
        $db = getDB();
        
        $sql = "
            SELECT COUNT(*) as count
            FROM bookings
            WHERE room_id = ?
            AND booking_date = ?
            AND status != 'cancelled'
            AND (
                (? BETWEEN start_time AND end_time)
                OR (? BETWEEN start_time AND end_time)
                OR (start_time BETWEEN ? AND ?)
            )
        ";
        
        if ($excludeBookingId) {
            $sql .= " AND booking_id != ?";
        }
        
        $stmt = $db->prepare($sql);
        
        if ($excludeBookingId) {
            $stmt->bind_param('isssssi', $roomId, $date, $startTime, $endTime, $startTime, $endTime, $excludeBookingId);
        } else {
            $stmt->bind_param('isssss', $roomId, $date, $startTime, $endTime, $startTime, $endTime);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['count'] == 0;
    }
}

/**
 * Upload file
 */
if (!function_exists('uploadFile')) {
    function uploadFile($file, $subFolder = 'documents') {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['success' => false, 'error' => 'ไม่มีไฟล์ที่อัปโหลด'];
        }
        
        // ใช้ฟังก์ชันจาก config.php ถ้ามี
        if (function_exists('getFileExtension')) {
            $extension = getFileExtension($file['name']);
        } else {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        }
        
        $maxSize = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 10485760;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'ขนาดไฟล์ใหญ่เกินกำหนด'];
        }
        
        $allowedTypes = defined('ALLOWED_FILE_TYPES') ? ALLOWED_FILE_TYPES : ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($extension, $allowedTypes)) {
            return ['success' => false, 'error' => 'ประเภทไฟล์ไม่ได้รับอนุญาต'];
        }
        
        $uploadPath = defined('UPLOAD_PATH') ? UPLOAD_PATH : __DIR__ . '/../uploads/';
        $uploadDir = $uploadPath . $subFolder . '/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // ใช้ฟังก์ชันจาก config.php ถ้ามี
        if (function_exists('generateRandomString')) {
            $randomStr = generateRandomString(8);
        } else {
            $randomStr = substr(md5(uniqid()), 0, 8);
        }
        
        $newFilename = time() . '_' . $randomStr . '.' . $extension;
        $uploadFilePath = $uploadDir . $newFilename;
        
        if (move_uploaded_file($file['tmp_name'], $uploadFilePath)) {
            return [
                'success' => true,
                'path' => $subFolder . '/' . $newFilename,
                'filename' => $newFilename
            ];
        }
        
        return ['success' => false, 'error' => 'ไม่สามารถอัปโหลดไฟล์ได้'];
    }
}

/**
 * Format date to Thai format
 */
if (!function_exists('formatDateThai')) {
    function formatDateThai($date) {
        if (empty($date)) return '-';
        
        $timestamp = strtotime($date);
        $thaiMonths = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
            5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
            9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];
        
        $day = date('d', $timestamp);
        $month = $thaiMonths[(int)date('m', $timestamp)];
        $year = date('Y', $timestamp) + 543;
        $time = date('H:i', $timestamp);
        
        return "{$day} {$month} {$year} {$time} น.";
    }
}

/**
 * Format date short
 */
if (!function_exists('formatDateShort')) {
    function formatDateShort($date) {
        if (empty($date)) return '-';
        
        $timestamp = strtotime($date);
        $thaiMonths = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
            5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
            9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];
        
        $day = date('d', $timestamp);
        $month = $thaiMonths[(int)date('m', $timestamp)];
        $year = date('Y', $timestamp) + 543;
        
        return "{$day} {$month} {$year}";
    }
}

/**
 * Check if user is admin
 */
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

/**
 * Check if user is logged in
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

/**
 * Redirect to page
 */
if (!function_exists('redirect')) {
    function redirect($url) {
        header("Location: $url");
        exit;
    }
}

/**
 * Sanitize input
 */
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

/**
 * Get current user ID
 */
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

/**
 * Get current user
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        $userId = getCurrentUserId();
        if ($userId) {
            return getUserById($userId);
        }
        return false;
    }
}
?>