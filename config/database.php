<?php
/**
 * Database Configuration - MySQL Version
 * ไฟล์เชื่อมต่อฐานข้อมูล MySQL
 */

// กำหนดค่าการเชื่อมต่อ MySQL (prefer environment values)
define('DB_HOST', getenv('ROMAR_DB_HOST') ?: '127.0.0.1');        // หรือ IP ของ Server
define('DB_USER', getenv('ROMAR_DB_USER') ?: 'root');             // Username MySQL
define('DB_PASS', getenv('ROMAR_DB_PASS') !== false ? getenv('ROMAR_DB_PASS') : ''); // Password MySQL
define('DB_NAME', getenv('ROMAR_DB_NAME') ?: 'romar_dormitory');  // ชื่อ Database
define('DB_CHARSET', 'utf8mb4');
define('APP_DEBUG', filter_var(getenv('ROMAR_APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN));

// สำหรับ Production ให้เปลี่ยนค่าตามนี้:
// define('DB_HOST', '192.168.1.xxx');  // IP ของ Database Server
// define('DB_USER', 'romar_user');     // สร้าง User เฉพาะ
// define('DB_PASS', 'strong_password'); // รหัสผ่านที่แข็งแรง

/**
 * Get MySQL Database Connection
 * @return mysqli
 */
function getDB() {
    static $connection = null;
    
    if ($connection === null) {
        try {
            $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            // เช็คการเชื่อมต่อ
            if ($connection->connect_error) {
                throw new Exception("Connection failed: " . $connection->connect_error);
            }
            
            // ตั้งค่า Character Set
            $connection->set_charset(DB_CHARSET);
            
            // ตั้งค่า Timezone (ถ้าต้องการ)
            $connection->query("SET time_zone = '+07:00'");
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            http_response_code(500);
            die("Database connection error. Please contact administrator.");
        }
    }
    
    return $connection;
}

if (!function_exists('register_global_error_handlers')) {
    function register_global_error_handlers() {
        set_exception_handler(function ($e) {
            $message = $e instanceof Throwable ? $e->getMessage() : 'Unhandled exception';
            error_log('Unhandled exception: ' . $message);
            
            // Don't break static assets
            $isStatic = isset($_SERVER['REQUEST_URI']) && preg_match('/\\.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?|ttf|eot|mp4|webm)$/i', $_SERVER['REQUEST_URI']);
            if (!headers_sent() && !$isStatic) {
                http_response_code(500);
                header('Content-Type: text/html; charset=UTF-8');
                include __DIR__ . '/../error/500.html';
            }
            exit;
        });

        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            error_log(sprintf('PHP error [%d] %s in %s:%d', $severity, $message, $file, $line));
            
            // Don't break static assets
            $isStatic = isset($_SERVER['REQUEST_URI']) && preg_match('/\\.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?|ttf|eot|mp4|webm)$/i', $_SERVER['REQUEST_URI']);
            if (!headers_sent() && !$isStatic) {
                http_response_code(500);
                header('Content-Type: text/html; charset=UTF-8');
                include __DIR__ . '/../error/500.html';
            }
            exit;
        });
    }
}

/**
 * Database Class สำหรับจัดการ Connection
 */
class Database {
    /**
     * @var self|null
     */
    private static $instance = null;
    /**
     * @var mysqli
     */
    private $connection;
    
    private function __construct() {
        $this->connection = getDB();
    }
    
    /**
     * @return mysqli
     */
    public static function getInstance(): mysqli {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->connection;
    }
    
    /**
     * @param string $sql
     * @return mysqli_stmt|false
     */
    public static function prepare(string $sql): mysqli_stmt|false {
        $db = self::getInstance();
        return $db->prepare($sql);
    }
    
    /**
     * @param string $sql
     * @return mysqli_result|false
     */
    public static function query(string $sql): mysqli_result|false {
        $db = self::getInstance();
        return $db->query($sql);
    }
    
    /**
     * Checkpoint (Compatibility - ไม่จำเป็นสำหรับ MySQL)
     * @return bool
     */
    public static function checkpoint(): bool {
        // MySQL ไม่ต้องการ checkpoint เหมือน SQLite
        // ทิ้งไว้เพื่อความเข้ากันได้กับโค้ดเดิม
        return true;
    }
    
    /**
     * Close connection
     */
    public static function close(): void {
        if (self::$instance !== null) {
            self::$instance->connection->close();
            self::$instance = null;
        }
    }
}

/**
 * Helper Functions
 */

/**
 * Escape string
 * @param string $string
 * @return string
 */
function db_escape(string $string): string {
    $db = Database::getInstance();
    return $db->real_escape_string($string);
}

/**
 * Get last insert ID
 * @return int|string
 */
function db_insert_id(): int|string {
    $db = Database::getInstance();
    return $db->insert_id;
}

/**
 * Get affected rows
 * @return int
 */
function db_affected_rows(): int {
    $db = Database::getInstance();
    return $db->affected_rows;
}

/**
 * Begin Transaction
 * @return bool
 */
function db_begin_transaction(): bool {
    $db = Database::getInstance();
    return $db->begin_transaction();
}

/**
 * Commit Transaction
 * @return bool
 */
function db_commit(): bool {
    $db = Database::getInstance();
    return $db->commit();
}

/**
 * Rollback Transaction
 * @return bool
 */
function db_rollback(): bool {
    $db = Database::getInstance();
    return $db->rollback();
}

/**
 * Execute Query และคืนค่าผลลัพธ์
 * @param string $sql
 * @return mysqli_result|false
 */
function db_query(string $sql): mysqli_result|false {
    $db = Database::getInstance();
    $result = $db->query($sql);
    
    if (!$result) {
        error_log("MySQL Error: " . $db->error . " | SQL: " . $sql);
        return false;
    }
    
    return $result;
}

/**
 * Fetch single row
 * @param mysqli_result $result
 * @return array|null
 */
function db_fetch(mysqli_result $result): ?array {
    return $result->fetch_assoc();
}

/**
 * Fetch all rows
 */
/**
 * Fetch all rows
 * @param mysqli_result $result
 * @return array
 */
function db_fetch_all(mysqli_result $result): array {
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get single value
 * @param string $sql
 * @return mixed|null
 */
function db_single(string $sql) {
    $result = db_query($sql);
    if ($result && $row = db_fetch($result)) {
        return reset($row); // คืนค่าแรก
    }
    return null;
}

// Register handlers always; bootstrap DB unless explicitly skipped (useful for isolated unit tests).
register_global_error_handlers();
if ((getenv('ROMAR_SKIP_DB_BOOT') ?: '0') !== '1') {
    getDB();
}
