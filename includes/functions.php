<?php
/**
 * Helper Functions
 * ฟังก์ชันช่วยเหลือสำหรับการทำงานกับ Database
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Verify user login
 * @param string $username
 * @param string $password
 * @return array|false
 */
function verifyLogin($username, $password) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // อัพเดท last_login
        $updateStmt = $db->prepare("UPDATE users SET last_login = datetime('now') WHERE user_id = :user_id");
        $updateStmt->bindValue(':user_id', $user['user_id'], SQLITE3_INTEGER);
        $updateStmt->execute();
        
        return $user;
    }
    
    return false;
}

/**
 * Get user by ID
 * @param int $userId
 * @return array|false
 */
function getUserById($userId) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

/**
 * Get all users
 * @param string $role (optional)
 * @return array
 */
function getAllUsers($role = null) {
    $db = getDB();
    
    if ($role) {
        $stmt = $db->prepare("SELECT * FROM users WHERE role = :role ORDER BY created_at DESC");
        $stmt->bindValue(':role', $role, SQLITE3_TEXT);
    } else {
        $stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC");
    }
    
    $result = $stmt->execute();
    $users = [];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    
    return $users;
}

/**
 * Log activity
 * @param int $userId
 * @param string $action
 * @param string $module
 * @param string $description
 */
function logActivity($userId, $action, $module = '', $description = '') {
    $db = getDB();
    
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, module, description, ip_address)
        VALUES (:user_id, :action, :module, :description, :ip_address)
    ");
    
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':action', $action, SQLITE3_TEXT);
    $stmt->bindValue(':module', $module, SQLITE3_TEXT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $stmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
    
    $stmt->execute();
}

/**
 * Get dashboard statistics
 * @return array
 */
function getDashboardStats() {
    $db = getDB();
    
    $stats = [];
    
    // Total tickets
    $result = $db->query("SELECT COUNT(*) as count FROM tickets");
    $stats['total_tickets'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Open tickets
    $result = $db->query("SELECT COUNT(*) as count FROM tickets WHERE status = 'open'");
    $stats['open_tickets'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Total announcements
    $result = $db->query("SELECT COUNT(*) as count FROM announcements WHERE is_active = 1");
    $stats['active_announcements'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Total users
    $result = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $stats['total_users'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Today's bookings
    $result = $db->query("SELECT COUNT(*) as count FROM room_bookings WHERE booking_date = date('now')");
    $stats['today_bookings'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Total documents
    $result = $db->query("SELECT COUNT(*) as count FROM documents");
    $stats['total_documents'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    return $stats;
}

/**
 * Get recent activities
 * @param int $limit
 * @return array
 */
function getRecentActivities($limit = 10) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT a.*, u.full_name, u.username
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.user_id
        ORDER BY a.created_at DESC
        LIMIT :limit
    ");
    
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $activities = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $activities[] = $row;
    }
    
    return $activities;
}

/**
 * Get active announcements
 * @param int $limit
 * @return array
 */
function getActiveAnnouncements($limit = 5) {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as publisher_name
        FROM announcements a
        LEFT JOIN users u ON a.published_by = u.user_id
        WHERE a.is_active = 1
        AND (a.expire_date IS NULL OR a.expire_date > datetime('now'))
        ORDER BY a.priority DESC, a.publish_date DESC
        LIMIT :limit
    ");
    
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $announcements = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $announcements[] = $row;
    }
    
    return $announcements;
}

/**
 * Generate ticket number
 * @return string
 */
function generateTicketNumber() {
    $db = getDB();
    
    // Get last ticket number
    $result = $db->query("SELECT ticket_number FROM tickets ORDER BY ticket_id DESC LIMIT 1");
    $lastTicket = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($lastTicket) {
        // Extract number and increment
        $lastNumber = (int)substr($lastTicket['ticket_number'], 2);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return 'TK' . date('Y') . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * Check if room is available
 * @param int $roomId
 * @param string $date
 * @param string $startTime
 * @param string $endTime
 * @param int $excludeBookingId (for editing)
 * @return bool
 */
function isRoomAvailable($roomId, $date, $startTime, $endTime, $excludeBookingId = null) {
    $db = getDB();
    
    $sql = "
        SELECT COUNT(*) as count
        FROM room_bookings
        WHERE room_id = :room_id
        AND booking_date = :booking_date
        AND status != 'cancelled'
        AND (
            (:start_time BETWEEN start_time AND end_time)
            OR (:end_time BETWEEN start_time AND end_time)
            OR (start_time BETWEEN :start_time AND :end_time)
        )
    ";
    
    if ($excludeBookingId) {
        $sql .= " AND booking_id != :exclude_id";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':room_id', $roomId, SQLITE3_INTEGER);
    $stmt->bindValue(':booking_date', $date, SQLITE3_TEXT);
    $stmt->bindValue(':start_time', $startTime, SQLITE3_TEXT);
    $stmt->bindValue(':end_time', $endTime, SQLITE3_TEXT);
    
    if ($excludeBookingId) {
        $stmt->bindValue(':exclude_id', $excludeBookingId, SQLITE3_INTEGER);
    }
    
    $result = $stmt->execute();
    $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    return $count == 0;
}

/**
 * Upload file
 * @param array $file ($_FILES)
 * @param string $subFolder
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function uploadFile($file, $subFolder = 'documents') {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'error' => 'ไม่มีไฟล์ที่อัปโหลด'];
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'ขนาดไฟล์ใหญ่เกินกำหนด'];
    }
    
    // Check file type
    $extension = getFileExtension($file['name']);
    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        return ['success' => false, 'error' => 'ประเภทไฟล์ไม่ได้รับอนุญาต'];
    }
    
    // Create upload directory if not exists
    $uploadDir = UPLOAD_PATH . $subFolder . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $newFilename = time() . '_' . generateRandomString(8) . '.' . $extension;
    $uploadPath = $uploadDir . $newFilename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return [
            'success' => true,
            'path' => $subFolder . '/' . $newFilename,
            'filename' => $newFilename
        ];
    }
    
    return ['success' => false, 'error' => 'ไม่สามารถอัปโหลดไฟล์ได้'];
}
?>