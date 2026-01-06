<?php
/**
 * Database Configuration
 * จัดการการเชื่อมต่อ SQLite Database
 * พร้อมสร้างโฟลเดอร์อัตโนมัติ
 */

class Database {
    private static $instance = null;
    private static $db = null;
    
    /**
     * Get Database Instance (Singleton Pattern)
     */
    public static function getInstance() {
        if (self::$db === null) {
            try {
                // Path ไปยัง Database
                $db_path = __DIR__ . '/../database/dormitory.db';
                
                // ตรวจสอบและสร้างโฟลเดอร์ database ถ้ายังไม่มี
                $db_dir = dirname($db_path);
                if (!file_exists($db_dir)) {
                    mkdir($db_dir, 0755, true);
                }
                
                // ตรวจสอบและสร้างโฟลเดอร์ uploads
                self::createUploadDirectories();
                
                // เชื่อมต่อ Database
                self::$db = new SQLite3($db_path);
                
                // ตั้งค่า Database ให้ปลอดภัย
                self::configureDatabaseSettings();
                
            } catch (Exception $e) {
                die("Database Connection Error: " . $e->getMessage());
            }
        }
        
        return self::$db;
    }
    
    /**
     * สร้างโฟลเดอร์ uploads อัตโนมัติ
     */
    private static function createUploadDirectories() {
        $project_root = __DIR__ . '/..';
        
        $upload_dirs = [
            $project_root . '/uploads',
            $project_root . '/uploads/documents',
            $project_root . '/uploads/images',
            $project_root . '/uploads/temp',
        ];
        
        foreach ($upload_dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        // สร้าง .htaccess เพื่อความปลอดภัย
        $htaccess_path = $project_root . '/uploads/.htaccess';
        if (!file_exists($htaccess_path)) {
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar)$\">\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            
            file_put_contents($htaccess_path, $htaccess_content);
        }
    }
    
    /**
     * ตั้งค่า Database
     */
    private static function configureDatabaseSettings() {
        // เปลี่ยน Journal Mode เป็น DELETE (ไม่ใช้ WAL)
        self::$db->exec("PRAGMA journal_mode = DELETE");
        
        // ตั้ง Synchronous เป็น FULL (ข้อมูลปลอดภัย)
        self::$db->exec("PRAGMA synchronous = FULL");
        
        // เปิด Foreign Keys
        self::$db->exec("PRAGMA foreign_keys = ON");
        
        // Auto Vacuum
        self::$db->exec("PRAGMA auto_vacuum = FULL");
    }
    
    /**
     * Checkpoint Database (บังคับให้บันทึกข้อมูล)
     */
    public static function checkpoint() {
        if (self::$db !== null) {
            self::$db->exec("PRAGMA wal_checkpoint(FULL)");
        }
    }
    
    /**
     * Close Database Connection
     */
    public static function close() {
        if (self::$db !== null) {
            self::$db->close();
            self::$db = null;
        }
    }
}

// เริ่มต้น Database Connection
Database::getInstance();

/**
 * Backward Compatibility Function
 * ฟังก์ชันนี้เพื่อรองรับโค้ดเก่าที่เรียก getDb()
 */
function getDb() {
    return Database::getInstance();
}
?>