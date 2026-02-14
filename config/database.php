<?php
/**
 * Database Configuration - MySQL Version
 * ไฟล์เชื่อมต่อฐานข้อมูล MySQL
 */

// กำหนดค่าการเชื่อมต่อ MySQL
define('DB_HOST', 'localhost');        // หรือ IP ของ Server
define('DB_USER', 'root');             // Username MySQL
define('DB_PASS', '');                 // Password MySQL
define('DB_NAME', 'romar_dormitory');  // ชื่อ Database
define('DB_CHARSET', 'utf8mb4');

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
            die("Database Error: " . $e->getMessage());
        }
    }
    
    return $connection;
}

/**
 * Database Class สำหรับจัดการ Connection
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connection = getDB();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->connection;
    }
    
    /**
     * Prepare statement (Compatibility method)
     */
    public static function prepare($sql) {
        $db = self::getInstance();
        return $db->prepare($sql);
    }
    
    /**
     * Query (Compatibility method)
     */
    public static function query($sql) {
        $db = self::getInstance();
        return $db->query($sql);
    }
    
    /**
     * Checkpoint (Compatibility - ไม่จำเป็นสำหรับ MySQL)
     */
    public static function checkpoint() {
        // MySQL ไม่ต้องการ checkpoint เหมือน SQLite
        // ทิ้งไว้เพื่อความเข้ากันได้กับโค้ดเดิม
        return true;
    }
    
    /**
     * Close connection
     */
    public static function close() {
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
 */
function db_escape($string) {
    $db = Database::getInstance();
    return $db->real_escape_string($string);
}

/**
 * Get last insert ID
 */
function db_insert_id() {
    $db = Database::getInstance();
    return $db->insert_id;
}

/**
 * Get affected rows
 */
function db_affected_rows() {
    $db = Database::getInstance();
    return $db->affected_rows;
}

/**
 * Begin Transaction
 */
function db_begin_transaction() {
    $db = Database::getInstance();
    return $db->begin_transaction();
}

/**
 * Commit Transaction
 */
function db_commit() {
    $db = Database::getInstance();
    return $db->commit();
}

/**
 * Rollback Transaction
 */
function db_rollback() {
    $db = Database::getInstance();
    return $db->rollback();
}

/**
 * Execute Query และคืนค่าผลลัพธ์
 */
function db_query($sql) {
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
 */
function db_fetch($result) {
    if ($result && $result instanceof mysqli_result) {
        return $result->fetch_assoc();
    }
    return false;
}

/**
 * Fetch all rows
 */
function db_fetch_all($result) {
    if ($result && $result instanceof mysqli_result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

/**
 * Get single value
 */
function db_single($sql) {
    $result = db_query($sql);
    if ($result && $row = db_fetch($result)) {
        return reset($row); // คืนค่าแรก
    }
    return null;
}

// เชื่อมต่อฐานข้อมูลเมื่อ load ไฟล์
getDB();
?>