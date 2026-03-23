<?php
if (!function_exists('romar_load_env_file')) {
    /**
     * Load key=value pairs from .env into process env when not already set.
     */
    function romar_load_env_file($path) {
        if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), "\"'");
            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

romar_load_env_file(__DIR__ . '/../.env');

if (!function_exists('romar_normalize_base_url')) {
    function romar_normalize_base_url($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        return rtrim($value, '/') . '/';
    }
}

if (!function_exists('romar_detect_base_url')) {
    function romar_detect_base_url() {
        $envBaseUrl = getenv('ROMAR_BASE_URL');
        if ($envBaseUrl !== false && trim((string)$envBaseUrl) !== '') {
            return romar_normalize_base_url($envBaseUrl);
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';

        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            $host = trim((string)($_SERVER['SERVER_NAME'] ?? 'localhost'));
        }

        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $segments = array_values(array_filter(explode('/', trim($scriptName, '/')), 'strlen'));
        $basePath = '/';
        if ($segments) {
            $basePath = '/' . $segments[0] . '/';
        }

        return romar_normalize_base_url($scheme . '://' . $host . $basePath);
    }
}

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
// เพิ่มตัวแปรแยก environment
if (!defined('APP_ENV')) {
    // Default can be provided via environment variables or .env file.
    $appEnv = getenv('APP_ENV');
    if ($appEnv === false || $appEnv === '') {
        $appEnv = getenv('ROMAR_APP_ENV') ?: 'development';
    }

    $normalizedAppEnv = strtolower(trim((string)$appEnv));
    if ($normalizedAppEnv === 'prod') {
        $normalizedAppEnv = 'production';
    } elseif ($normalizedAppEnv === 'dev') {
        $normalizedAppEnv = 'development';
    }

define('APP_ENV', $normalizedAppEnv);
}

// บังคับให้ PHP ส่งข้อความที่เข้ารหัสด้วย UTF-8 เสมอ
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');
    mb_regex_encoding('UTF-8');
}

if (APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);      // ปิดการแสดง error ให้ผู้ใช้งาน
    ini_set('log_errors', 1);          // เปิดการบันทึกลงไฟล์
} else {
    // development / testing
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// ========================================
// ค่าคงที่ของระบบ
// ========================================

// ชื่อระบบ
define('SITE_NAME', 'ระบบจัดการในRomar');
define('SITE_NAME_EN', 'Romar');

// URL พื้นฐาน (ปรับตามโครงสร้างของคุณ)
// ✅ แก้ไขจาก localhost เป็น IP Address ของ Server
define('BASE_URL', romar_detect_base_url());

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
// Helper Functions (Core Config Only)
// ========================================

/**
 * Redirect to URL
 * Note: This is the main redirect function used throughout the application
 */
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}
?>
