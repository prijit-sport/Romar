<?php
/**
 * Helper Functions - shared utilities for the Romar platform.
 * Wrapped in function_exists() guards to avoid duplicate declarations.
 */

require_once __DIR__ . '/../config/database.php';

// Load optional config overrides if present
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/i18n.php';

// Load validation helper functions
if (file_exists(__DIR__ . '/validation.php')) {
    require_once __DIR__ . '/validation.php';
}

// Load backup helper functions
if (file_exists(__DIR__ . '/backup_helpers.php')) {
    require_once __DIR__ . '/backup_helpers.php';
}

// Load safe variable access helpers
if (file_exists(__DIR__ . '/safe_access.php')) {
    require_once __DIR__ . '/safe_access.php';
}

// Load logging & monitoring system
if (file_exists(__DIR__ . '/logger.php')) {
    require_once __DIR__ . '/logger.php';
}

/**
 * Verify user login
 */
if (!function_exists('verifyLogin')) {
function verifyLogin(string $username, string $password) {
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
function getUserById(int $userId) {
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
function getAllUsers(?string $role = null) {
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
function logActivity(int $userId, string $action, string $module = '', string $description = '') {
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
function getRecentActivities(int $limit = 10) {
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
function getActiveAnnouncements(int $limit = 5) {
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
function isRoomAvailable(int $roomId, string $date, string $startTime, string $endTime, ?int $excludeBookingId = null) {
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
function uploadFile(array $file, string $subFolder = 'documents') {
        if (empty($file['tmp_name'])) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }

        $extension = function_exists('getFileExtension')
            ? getFileExtension($file['name'])
            : strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $maxSize = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 10485760;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'File size exceeds the allowed limit'];
        }

        $allowedTypes = defined('ALLOWED_FILE_TYPES')
            ? ALLOWED_FILE_TYPES
            : ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

        if (!in_array($extension, $allowedTypes, true)) {
            return ['success' => false, 'error' => 'File type is not allowed'];
        }

        $uploadPath = defined('UPLOAD_PATH') ? UPLOAD_PATH : __DIR__ . '/../uploads/';
        $uploadDir = rtrim($uploadPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . trim($subFolder, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['success' => false, 'error' => 'Unable to create upload directory'];
        }

        $randomStr = function_exists('generateRandomString')
            ? generateRandomString(8)
            : substr(md5(uniqid()), 0, 8);

        $newFilename = time() . '_' . $randomStr . '.' . $extension;
        $uploadFilePath = $uploadDir . $newFilename;

        if (move_uploaded_file($file['tmp_name'], $uploadFilePath)) {
            return [
                'success' => true,
                'path' => trim($subFolder, DIRECTORY_SEPARATOR) . '/' . $newFilename,
                'filename' => $newFilename
            ];
        }

        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
}

/**
 * Format date to Thai format
 */
if (!function_exists('formatDateThai')) {
function formatDateThai(string $date) {
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
function formatDateShort(string $date) {
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
 * Get current user role
 */
if (!function_exists('getCurrentUserRole')) {
    function getCurrentUserRole() {
        return $_SESSION['role'] ?? 'user';
    }
}

/**
 * Redirect to page
 */
if (!function_exists('redirect')) {
    function redirect(string $url) {
        header("Location: $url");
        exit;
    }
}

/**
 * Sanitize input
 */
if (!function_exists('sanitize')) {
    function sanitize(string $data) {
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

/**
 * Get or create CSRF token for current session
 */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $_SESSION['csrf_token'] = hash('sha256', uniqid((string) mt_rand(), true));
            }
        }

        return $_SESSION['csrf_token'];
    }
}

/**
 * Render CSRF hidden input for POST forms
 */
if (!function_exists('csrf_input')) {
    function csrf_input() {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

/**
 * Validate CSRF token from request
 */
if (!function_exists('verify_csrf')) {
    function verify_csrf(string $token) {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return !empty($token) && !empty($sessionToken) && hash_equals($sessionToken, $token);
    }
}

/**
 * Attach common security headers (safe to call multiple times)
 */
if (!function_exists('apply_security_headers')) {
    function apply_security_headers(array $options = []) {
        if (headers_sent()) {
            return;
        }

        $allowInline = isset($options['allow_inline']) ? (bool)$options['allow_inline'] : true;
        $nonce = csp_nonce();

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        if ($allowInline) {
            header("Content-Security-Policy: default-src 'self' https: data: blob: 'unsafe-inline'; frame-ancestors 'self'");
        } else {
            header("Content-Security-Policy: default-src 'self' https: data: blob:; script-src 'self' 'nonce-{$nonce}' https:; style-src 'self' 'nonce-{$nonce}' https:; img-src 'self' https: data: blob:; frame-ancestors 'self'");
        }

        // Stricter policy in report-only mode for gradual hardening on all pages.
header("Content-Security-Policy-Report-Only: default-src 'self' https: data: blob: 'unsafe-inline'; script-src 'self' https: 'unsafe-inline'; style-src 'self' https: 'unsafe-inline'; img-src 'self' https: data: blob:; frame-ancestors 'self'");
    }
}

/**
 * CSP nonce helper
 */
if (!function_exists('csp_nonce')) {
    function csp_nonce() {
        static $nonce = null;
        if ($nonce !== null) {
            return $nonce;
        }

        try {
            $nonce = base64_encode(random_bytes(16));
        } catch (Exception $e) {
            $nonce = base64_encode(hash('sha256', uniqid((string) mt_rand(), true), true));
        }

        return $nonce;
    }
}

/**
 * Generate request id for tracing
 */
if (!function_exists('request_id')) {
    function request_id() {
        if (empty($_SERVER['HTTP_X_REQUEST_ID'])) {
            try {
                $_SERVER['HTTP_X_REQUEST_ID'] = bin2hex(random_bytes(8));
            } catch (Exception $e) {
                $_SERVER['HTTP_X_REQUEST_ID'] = uniqid('req_', true);
            }
        }

        return (string) $_SERVER['HTTP_X_REQUEST_ID'];
    }
}

/**
 * Basic rate limiting (session + ip key)
 */
if (!function_exists('rate_limit_check')) {
    function rate_limit_check(string $key, int $maxAttempts = 10, int $windowSeconds = 60) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $bucketKey = 'rate_limit_' . md5($key . '|' . $ip);
        $now = time();

        if (!isset($_SESSION[$bucketKey])) {
            $_SESSION[$bucketKey] = ['count' => 0, 'start' => $now];
        }

        $bucket = $_SESSION[$bucketKey];
        if (($now - (int)$bucket['start']) > $windowSeconds) {
            $bucket = ['count' => 0, 'start' => $now];
        }

        $allowed = ((int)$bucket['count'] < $maxAttempts);
        if ($allowed) {
            $bucket['count']++;
        }

        $_SESSION[$bucketKey] = $bucket;

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $maxAttempts - (int)$bucket['count']),
            'retry_after' => max(0, $windowSeconds - ($now - (int)$bucket['start'])),
        ];
    }
}

/**
 * Security audit log (JSON lines)
 */
if (!function_exists('security_audit_log')) {
    function security_audit_log(string $event, array $context = []) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/security.log';
        $policy = security_log_policy();
        rotate_security_log($logFile, (int)$policy['max_bytes'], (int)$policy['max_files']);

        $payload = [
            'ts' => date('c'),
            'event' => (string)$event,
            'request_id' => request_id(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'context' => is_array($context) ? $context : ['value' => $context],
        ];

        @file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }
}

/**
 * Security log retention policy by environment
 */
if (!function_exists('security_log_policy')) {
    function security_log_policy() {
        $env = strtolower((string)(getenv('ROMAR_APP_ENV') ?: 'dev'));
        $policy = [
            'dev' => ['max_bytes' => 5 * 1024 * 1024, 'max_files' => 10],
            'staging' => ['max_bytes' => 10 * 1024 * 1024, 'max_files' => 20],
            'prod' => ['max_bytes' => 20 * 1024 * 1024, 'max_files' => 30],
            'production' => ['max_bytes' => 20 * 1024 * 1024, 'max_files' => 30],
        ];

        $selected = $policy[$env] ?? $policy['dev'];
        $maxBytes = (int)(getenv('ROMAR_LOG_MAX_BYTES') ?: $selected['max_bytes']);
        $maxFiles = (int)(getenv('ROMAR_LOG_MAX_FILES') ?: $selected['max_files']);

        return [
            'env' => $env,
            'max_bytes' => max(1048576, $maxBytes),
            'max_files' => max(3, $maxFiles),
        ];
    }
}

/**
 * Rotate security log (daily or when exceeds max size)
 */
if (!function_exists('rotate_security_log')) {
    function rotate_security_log(string $logFile, int $maxBytes = 5242880, int $maxFiles = 10) {
        if (!file_exists($logFile)) {
            return;
        }

        $rotate = false;
        $today = date('Ymd');
        $fileDate = date('Ymd', filemtime($logFile));
        if ($fileDate !== $today) {
            $rotate = true;
        }

        if (filesize($logFile) >= $maxBytes) {
            $rotate = true;
        }

        if (!$rotate) {
            return;
        }

        $dir = dirname($logFile);
        $base = basename($logFile, '.log');
        $rotated = $dir . '/' . $base . '-' . date('Ymd-His') . '.log';
        @rename($logFile, $rotated);

        $archives = glob($dir . '/' . $base . '-*.log') ?: [];
        rsort($archives);
        if (count($archives) > $maxFiles) {
            $toDelete = array_slice($archives, $maxFiles);
            foreach ($toDelete as $f) {
                @unlink($f);
            }
        }
    }
}

/**
 * Check ticket ownership for non-admin users
 */
if (!function_exists('can_access_ticket')) {
    function can_access_ticket(mysqli $db, int $ticketId, int $userId, bool $isAdmin = false) {
        if ($isAdmin) {
            return true;
        }

        $stmt = $db->prepare("SELECT created_by FROM tickets WHERE ticket_id = ? LIMIT 1");
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row && (int)$row['created_by'] === (int)$userId;
    }
}

/**
 * JSON error helper for APIs
 */
if (!function_exists('json_error')) {
    function json_error(string $message, int $statusCode = 400, ?string $requestId = null) {
        if ($requestId === null) {
            $requestId = request_id();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'request_id' => $requestId,
        ]);
        exit;
    }
}

