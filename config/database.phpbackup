<?php
/**
 * Database Configuration and Connection
 * Version 2.0 - Fixed locking issues
 */

class Database {
    private static $instance = null;
    private static $db = null;
    private static $db_path = __DIR__ . '/../database/dormitory.db';

    /**
     * Get database instance (singleton pattern)
     */
    public static function getInstance() {
        if (self::$db === null) {
            try {
                // เปิดการเชื่อมต่อ database
                self::$db = new SQLite3(self::$db_path);
                
                // ตั้งค่า busy timeout เพื่อรอ lock (5 วินาที)
                self::$db->busyTimeout(5000);
                
                // เปิด foreign keys
                self::$db->exec('PRAGMA foreign_keys = ON');
                
                // ใช้ DELETE mode แทน WAL (แก้ปัญหา locking)
                self::$db->exec('PRAGMA journal_mode = DELETE');
                
                // ตั้งค่า synchronous เป็น NORMAL
                self::$db->exec('PRAGMA synchronous = NORMAL');
                
                // ตั้งค่า temp_store เป็น MEMORY
                self::$db->exec('PRAGMA temp_store = MEMORY');
                
                // ตั้งค่า cache size
                self::$db->exec('PRAGMA cache_size = 10000');
                
            } catch (Exception $e) {
                die("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$db;
    }

    /**
     * Checkpoint database (ปรับให้ปลอดภัยกว่า)
     */
    public static function checkpoint() {
        try {
            $db = self::getInstance();
            
            // ใช้ PRAGMA wal_checkpoint แบบ PASSIVE
            // ไม่ force ให้ดีกว่า
            @$db->querySingle("PRAGMA wal_checkpoint(PASSIVE)", false);
            
            return true;
        } catch (Exception $e) {
            // ไม่ throw error - แค่ log
            error_log("Checkpoint warning: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction() {
        try {
            $db = self::getInstance();
            $db->exec('BEGIN IMMEDIATE');
            return true;
        } catch (Exception $e) {
            error_log("Begin transaction error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Commit transaction
     */
    public static function commit() {
        try {
            $db = self::getInstance();
            $db->exec('COMMIT');
            return true;
        } catch (Exception $e) {
            error_log("Commit error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Rollback transaction
     */
    public static function rollback() {
        try {
            $db = self::getInstance();
            $db->exec('ROLLBACK');
            return true;
        } catch (Exception $e) {
            error_log("Rollback error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Close database connection
     */
    public static function close() {
        if (self::$db !== null) {
            self::$db->close();
            self::$db = null;
        }
    }

    /**
     * Get database path
     */
    public static function getPath() {
        return self::$db_path;
    }

    /**
     * Optimize database
     */
    public static function optimize() {
        try {
            $db = self::getInstance();
            $db->exec('VACUUM');
            $db->exec('ANALYZE');
            return true;
        } catch (Exception $e) {
            error_log("Optimize error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Helper function to get database instance
 */
function getDb() {
    return Database::getInstance();
}

/**
 * Helper function for checkpoint
 * ไม่แสดง error ถ้าล้มเหลว
 */
function dbCheckpoint() {
    @Database::checkpoint();
}
?>