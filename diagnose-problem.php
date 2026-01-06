<?php
/**
 * Diagnostic & Fix Script
 * ตรวจสอบและแก้ไขปัญหาโฟลเดอร์/ไฟล์หาย
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>ตรวจสอบและแก้ไขปัญหา</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 1200px; margin: 30px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; text-align: center; }
h2 { color: #764ba2; margin-top: 30px; padding: 10px; background: white; border-left: 4px solid #667eea; }
.box { background: white; padding: 30px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; font-weight: bold; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 0.9em; }
.btn { display: inline-block; padding: 12px 24px; margin: 10px 5px; text-decoration: none; border-radius: 8px; font-weight: 600; color: white; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
th { background: #f8f9fa; font-weight: 600; }
.path { font-family: monospace; background: #f8f9fa; padding: 2px 6px; border-radius: 4px; }
</style></head><body>";

echo "<div class='box'>";
echo "<h1>🔍 ตรวจสอบและวินิจฉัยปัญหา</h1>";

$project_root = __DIR__;
$issues = [];
$warnings = [];

// ========================================
// 1. ตรวจสอบโครงสร้างโฟลเดอร์
// ========================================
echo "<h2>1. ตรวจสอบโครงสร้างโฟลเดอร์</h2>";

$required_folders = [
    'uploads' => $project_root . '/uploads',
    'uploads/documents' => $project_root . '/uploads/documents',
    'uploads/images' => $project_root . '/uploads/images',
    'uploads/temp' => $project_root . '/uploads/temp',
    'database' => $project_root . '/database',
];

echo "<table>";
echo "<tr><th>โฟลเดอร์</th><th>Path</th><th>สถานะ</th></tr>";

foreach ($required_folders as $name => $path) {
    $exists = file_exists($path);
    $is_writable = $exists && is_writable($path);
    
    echo "<tr>";
    echo "<td><strong>{$name}</strong></td>";
    echo "<td class='path'>{$path}</td>";
    
    if ($exists) {
        if ($is_writable) {
            echo "<td class='success'>✅ มีอยู่และเขียนได้</td>";
        } else {
            echo "<td class='warning'>⚠️ มีอยู่แต่เขียนไม่ได้!</td>";
            $warnings[] = "โฟลเดอร์ {$name} เขียนไม่ได้";
        }
    } else {
        echo "<td class='error'>❌ ไม่มี!</td>";
        $issues[] = "ไม่พบโฟลเดอร์ {$name}";
    }
    
    echo "</tr>";
}

echo "</table>";

// ========================================
// 2. ตรวจสอบไฟล์ที่อัปโหลด
// ========================================
echo "<h2>2. ตรวจสอบไฟล์ที่อัปโหลด</h2>";

$uploads_dir = $project_root . '/uploads/documents';
if (file_exists($uploads_dir)) {
    $files = scandir($uploads_dir);
    $files = array_diff($files, ['.', '..', '.htaccess']);
    
    if (count($files) > 0) {
        echo "<p class='success'>✅ พบไฟล์ " . count($files) . " ไฟล์</p>";
        echo "<ul>";
        foreach ($files as $file) {
            $size = filesize($uploads_dir . '/' . $file);
            $size_kb = round($size / 1024, 2);
            echo "<li>{$file} ({$size_kb} KB)</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='info'>ℹ️ ยังไม่มีไฟล์ที่อัปโหลด</p>";
    }
} else {
    echo "<p class='error'>❌ ไม่พบโฟลเดอร์ uploads/documents</p>";
}

// ========================================
// 3. ตรวจสอบ Database
// ========================================
echo "<h2>3. ตรวจสอบ Database</h2>";

$db_path = $project_root . '/database/dormitory.db';

if (file_exists($db_path)) {
    echo "<p class='success'>✅ พบไฟล์ Database</p>";
    
    try {
        $db = new SQLite3($db_path);
        
        // ตรวจสอบ Journal Mode
        $result = $db->query("PRAGMA journal_mode");
        $mode = $result->fetchArray(SQLITE3_ASSOC);
        
        echo "<table>";
        echo "<tr><th>การตั้งค่า</th><th>ค่าปัจจุบัน</th><th>ผลกระทบ</th></tr>";
        
        echo "<tr>";
        echo "<td><strong>Journal Mode</strong></td>";
        echo "<td class='path'>{$mode[0]}</td>";
        
        if ($mode[0] === 'wal') {
            echo "<td class='warning'>⚠️ ใช้ WAL - อาจทำให้ข้อมูลไม่ถาวร!</td>";
            $warnings[] = "Database ใช้ WAL mode";
        } else {
            echo "<td class='success'>✅ ใช้ {$mode[0]} - ปลอดภัย</td>";
        }
        echo "</tr>";
        
        // ตรวจสอบ Synchronous
        $result = $db->query("PRAGMA synchronous");
        $sync = $result->fetchArray(SQLITE3_ASSOC);
        
        echo "<tr>";
        echo "<td><strong>Synchronous</strong></td>";
        echo "<td class='path'>{$sync[0]}</td>";
        
        if ($sync[0] >= 2) {
            echo "<td class='success'>✅ FULL - ข้อมูลปลอดภัย</td>";
        } else {
            echo "<td class='warning'>⚠️ ไม่ได้ตั้งเป็น FULL</td>";
            $warnings[] = "Synchronous ไม่ได้ตั้งเป็น FULL";
        }
        echo "</tr>";
        
        echo "</table>";
        
        // ตรวจสอบข้อมูลในตาราง documents
        echo "<h3>ข้อมูลในตาราง documents:</h3>";
        
        $result = $db->query("SELECT COUNT(*) as count FROM documents");
        $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
        
        if ($count > 0) {
            echo "<p class='success'>✅ พบเอกสาร {$count} รายการในฐานข้อมูล</p>";
            
            $result = $db->query("SELECT document_name, file_name, uploaded_at FROM documents ORDER BY uploaded_at DESC LIMIT 5");
            echo "<table>";
            echo "<tr><th>ชื่อเอกสาร</th><th>ชื่อไฟล์</th><th>อัปโหลดเมื่อ</th></tr>";
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                echo "<tr>";
                echo "<td>{$row['document_name']}</td>";
                echo "<td class='path'>{$row['file_name']}</td>";
                echo "<td>{$row['uploaded_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='info'>ℹ️ ยังไม่มีเอกสารในฐานข้อมูล</p>";
        }
        
        $db->close();
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ ไม่สามารถเชื่อมต่อ Database: {$e->getMessage()}</p>";
        $issues[] = "ไม่สามารถเชื่อมต่อ Database";
    }
    
} else {
    echo "<p class='error'>❌ ไม่พบไฟล์ Database ที่ {$db_path}</p>";
    $issues[] = "ไม่พบไฟล์ Database";
}

// ========================================
// 4. ตรวจสอบการตั้งค่า PHP
// ========================================
echo "<h2>4. ตรวจสอบการตั้งค่า PHP</h2>";

$php_settings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
];

echo "<table>";
echo "<tr><th>การตั้งค่า</th><th>ค่า</th></tr>";
foreach ($php_settings as $key => $value) {
    echo "<tr><td><strong>{$key}</strong></td><td class='path'>{$value}</td></tr>";
}
echo "</table>";

// ========================================
// 5. สรุปปัญหา
// ========================================
echo "<h2>5. สรุปปัญหา</h2>";

if (empty($issues) && empty($warnings)) {
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745;'>";
    echo "<h3 style='color: #155724;'>🎉 ไม่พบปัญหา!</h3>";
    echo "<p style='color: #155724;'>ระบบของคุณทำงานปกติ</p>";
    echo "</div>";
} else {
    if (!empty($issues)) {
        echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545; margin-bottom: 20px;'>";
        echo "<h3 style='color: #721c24;'>🔴 ปัญหาร้ายแรง:</h3>";
        echo "<ul style='color: #721c24;'>";
        foreach ($issues as $issue) {
            echo "<li>{$issue}</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
    if (!empty($warnings)) {
        echo "<div style='background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107;'>";
        echo "<h3 style='color: #856404;'>⚠️ คำเตือน:</h3>";
        echo "<ul style='color: #856404;'>";
        foreach ($warnings as $warning) {
            echo "<li>{$warning}</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
}

// ========================================
// 6. วิธีแก้ไข
// ========================================
if (!empty($issues) || !empty($warnings)) {
    echo "<h2>6. วิธีแก้ไข</h2>";
    
    echo "<div style='background: #e7f3ff; padding: 20px; border-radius: 8px;'>";
    
    if (isset($_GET['fix'])) {
        echo "<h3>กำลังแก้ไข...</h3>";
        
        // สร้างโฟลเดอร์
        foreach ($required_folders as $name => $path) {
            if (!file_exists($path)) {
                if (mkdir($path, 0755, true)) {
                    echo "<p class='success'>✅ สร้างโฟลเดอร์: {$name}</p>";
                } else {
                    echo "<p class='error'>❌ ไม่สามารถสร้างโฟลเดอร์: {$name}</p>";
                }
            }
        }
        
        // แก้ไข Database Settings
        if (file_exists($db_path)) {
            try {
                $db = new SQLite3($db_path);
                
                $db->exec("PRAGMA journal_mode = DELETE");
                echo "<p class='success'>✅ เปลี่ยน journal_mode เป็น DELETE</p>";
                
                $db->exec("PRAGMA synchronous = FULL");
                echo "<p class='success'>✅ ตั้ง synchronous = FULL</p>";
                
                $db->exec("PRAGMA auto_vacuum = FULL");
                echo "<p class='success'>✅ เปิด auto_vacuum = FULL</p>";
                
                // Checkpoint
                $db->exec("PRAGMA wal_checkpoint(FULL)");
                echo "<p class='success'>✅ Checkpoint database</p>";
                
                $db->close();
                
            } catch (Exception $e) {
                echo "<p class='error'>❌ ไม่สามารถแก้ไข Database: {$e->getMessage()}</p>";
            }
        }
        
        // สร้าง .htaccess
        $htaccess_content = "Options -Indexes\n";
        $htaccess_content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar)$\">\n";
        $htaccess_content .= "    Order Allow,Deny\n";
        $htaccess_content .= "    Allow from all\n";
        $htaccess_content .= "</FilesMatch>\n";
        
        if (file_exists($project_root . '/uploads')) {
            file_put_contents($project_root . '/uploads/.htaccess', $htaccess_content);
            echo "<p class='success'>✅ สร้างไฟล์ .htaccess</p>";
        }
        
        echo "<h3 style='color: green;'>🎉 แก้ไขเสร็จสิ้น!</h3>";
        echo "<p><a href='?' class='btn' style='background: #667eea;'>← ตรวจสอบอีกครั้ง</a></p>";
        
    } else {
        echo "<h3>คำแนะนำ:</h3>";
        echo "<ol>";
        
        if (!empty($issues)) {
            echo "<li>คลิกปุ่ม \"🔧 แก้ไขอัตโนมัติ\" ด้านล่าง</li>";
        }
        
        if (in_array("Database ใช้ WAL mode", $warnings)) {
            echo "<li>แก้ไข Database Settings เป็น DELETE mode</li>";
        }
        
        echo "<li>ตรวจสอบว่าโฟลเดอร์ถูกสร้างที่ตำแหน่งที่ถูกต้อง</li>";
        echo "<li>ตรวจสอบสิทธิ์การเขียนโฟลเดอร์</li>";
        echo "</ol>";
        
        echo "<p><a href='?fix=1' class='btn' style='background: #f59e0b;'>🔧 แก้ไขอัตโนมัติ</a></p>";
    }
    
    echo "</div>";
}

// ========================================
// 7. ข้อมูลเพิ่มเติม
// ========================================
echo "<h2>7. ข้อมูลเพิ่มเติม</h2>";

echo "<table>";
echo "<tr><th>รายการ</th><th>ค่า</th></tr>";
echo "<tr><td><strong>Project Root</strong></td><td class='path'>{$project_root}</td></tr>";
echo "<tr><td><strong>PHP Version</strong></td><td>" . phpversion() . "</td></tr>";
echo "<tr><td><strong>OS</strong></td><td>" . PHP_OS . "</td></tr>";
echo "<tr><td><strong>Server Software</strong></td><td>" . $_SERVER['SERVER_SOFTWARE'] . "</td></tr>";
echo "</table>";

echo "</div>";

// ========================================
// 8. วิธีป้องกันปัญหา
// ========================================
echo "<div class='box'>";
echo "<h2>💡 วิธีป้องกันปัญหาในอนาคต</h2>";

echo "<h3>1. ใส่โค้ดตรวจสอบโฟลเดอร์ใน config/database.php:</h3>";
echo "<pre><code>&lt;?php
// config/database.php

class Database {
    private static \$instance = null;
    
    public static function getInstance() {
        if (self::\$instance === null) {
            \$db_path = __DIR__ . '/../database/dormitory.db';
            
            // ตรวจสอบและสร้างโฟลเดอร์
            \$upload_dirs = [
                __DIR__ . '/../uploads',
                __DIR__ . '/../uploads/documents',
                __DIR__ . '/../uploads/images',
                __DIR__ . '/../uploads/temp',
            ];
            
            foreach (\$upload_dirs as \$dir) {
                if (!file_exists(\$dir)) {
                    mkdir(\$dir, 0755, true);
                }
            }
            
            self::\$instance = new SQLite3(\$db_path);
            
            // ตั้งค่า Database
            self::\$instance->exec('PRAGMA journal_mode = DELETE');
            self::\$instance->exec('PRAGMA synchronous = FULL');
        }
        
        return self::\$instance;
    }
}
?&gt;</code></pre>";

echo "<h3>2. อย่าลืม Commit Database หลังทุก Action:</h3>";
echo "<pre><code>&lt;?php
// หลังจาก INSERT, UPDATE, DELETE
\$db->exec('PRAGMA wal_checkpoint(FULL)');
?&gt;</code></pre>";

echo "<h3>3. สร้าง Cron Job Backup ข้อมูล:</h3>";
echo "<pre><code># Linux/Mac Crontab
0 2 * * * cp /path/to/database/dormitory.db /path/to/backup/dormitory_\$(date +\\%Y\\%m\\%d).db

# Windows Task Scheduler
xcopy \"C:\\xampp\\htdocs\\RomarDormitory-management\\database\\dormitory.db\" \"C:\\backup\\\" /Y</code></pre>";

echo "</div>";

echo "</body></html>";
?>