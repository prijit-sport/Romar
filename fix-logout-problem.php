<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>แก้ไขปัญหา Users หาย</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; text-align: center; }
h2 { color: #764ba2; margin-top: 30px; padding: 10px; background: white; border-left: 4px solid #667eea; }
.box { background: white; padding: 30px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.btn { display: inline-block; padding: 12px 24px; margin: 10px; text-decoration: none; border-radius: 8px; font-weight: 600; }
</style></head><body>";

echo "<div class='box'>";
echo "<h1>🔧 แก้ไขปัญหา Users หายหลัง Logout</h1>";

$db = Database::getInstance();

if (isset($_GET['step'])) {
    $step = $_GET['step'];
    
    if ($step == '1') {
        // ขั้นตอน 1: แก้ไข Database Settings
        echo "<h2>ขั้นตอน 1: แก้ไข Database Settings</h2>";
        
        // ปิด WAL mode
        $db->exec("PRAGMA journal_mode = DELETE");
        echo "<p class='success'>✅ เปลี่ยน journal_mode = DELETE</p>";
        
        // เปิด FULL sync
        $db->exec("PRAGMA synchronous = FULL");
        echo "<p class='success'>✅ ตั้ง synchronous = FULL</p>";
        
        // Auto-commit
        $db->exec("PRAGMA auto_vacuum = FULL");
        echo "<p class='success'>✅ เปิด auto_vacuum = FULL</p>";
        
        // ตรวจสอบ settings
        echo "<h3>ตรวจสอบ Settings ปัจจุบัน:</h3>";
        $result = $db->query("PRAGMA journal_mode");
        $mode = $result->fetchArray(SQLITE3_ASSOC);
        echo "<p>Journal Mode: <strong>{$mode[0]}</strong></p>";
        
        $result = $db->query("PRAGMA synchronous");
        $sync = $result->fetchArray(SQLITE3_ASSOC);
        echo "<p>Synchronous: <strong>{$sync[0]}</strong></p>";
        
        echo "<p class='success'>✅ Database Settings แก้ไขเสร็จแล้ว!</p>";
        echo "<p><a href='?step=2' class='btn' style='background: #667eea; color: white;'>→ ขั้นตอนถัดไป: เพิ่ม Users</a></p>";
        
    } elseif ($step == '2') {
        // ขั้นตอน 2: เพิ่ม Users ใหม่
        echo "<h2>ขั้นตอน 2: เพิ่ม Users</h2>";
        
        // ลบ users เก่า
        $db->exec("DELETE FROM users");
        echo "<p class='success'>✅ ลบ users เก่า</p>";
        
        // สร้าง password hash
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $staff_password = password_hash('staff123', PASSWORD_DEFAULT);
        
        // Insert admin
        $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role, is_active, created_at) VALUES (:username, :password, :full_name, :email, :role, 1, :created_at)");
        $stmt->bindValue(':username', 'admin', SQLITE3_TEXT);
        $stmt->bindValue(':password', $admin_password, SQLITE3_TEXT);
        $stmt->bindValue(':full_name', 'ผู้ดูแลระบบ', SQLITE3_TEXT);
        $stmt->bindValue(':email', 'admin@dormitory.com', SQLITE3_TEXT);
        $stmt->bindValue(':role', 'admin', SQLITE3_TEXT);
        $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->execute();
        echo "<p class='success'>✅ เพิ่ม admin สำเร็จ!</p>";
        
        // Insert staff1
        $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role, is_active, created_at) VALUES (:username, :password, :full_name, :email, :role, 1, :created_at)");
        $stmt->bindValue(':username', 'staff1', SQLITE3_TEXT);
        $stmt->bindValue(':password', $staff_password, SQLITE3_TEXT);
        $stmt->bindValue(':full_name', 'พนักงาน 1', SQLITE3_TEXT);
        $stmt->bindValue(':email', 'staff1@dormitory.com', SQLITE3_TEXT);
        $stmt->bindValue(':role', 'staff', SQLITE3_TEXT);
        $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->execute();
        echo "<p class='success'>✅ เพิ่ม staff1 สำเร็จ!</p>";
        
        // Commit explicitly (SQLite auto-commits แต่เราจะ sync ให้แน่ใจ)
        $db->exec("PRAGMA wal_checkpoint(FULL)");
        echo "<p class='success'>✅ Checkpoint database</p>";
        
        // ตรวจสอบข้อมูล
        $result = $db->query("SELECT COUNT(*) as count FROM users");
        $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
        echo "<p class='success'>✅ มี {$count} users ในระบบ</p>";
        
        echo "<p><a href='?step=3' class='btn' style='background: #667eea; color: white;'>→ ขั้นตอนถัดไป: ทดสอบ</a></p>";
        
    } elseif ($step == '3') {
        // ขั้นตอน 3: ทดสอบ
        echo "<h2>ขั้นตอน 3: ทดสอบ</h2>";
        
        // ตรวจสอบว่ามี users หรือไม่
        $result = $db->query("SELECT COUNT(*) as count FROM users");
        $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
        
        if ($count > 0) {
            echo "<p class='success'>✅ พบ {$count} users ในระบบ</p>";
            
            // ทดสอบ login
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->bindValue(':username', 'admin', SQLITE3_TEXT);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($user) {
                $verify = password_verify('admin123', $user['password']);
                echo "<p>ทดสอบ Login: admin / admin123</p>";
                echo "<p>ผลลัพธ์: " . ($verify ? '<span class="success">✅ Login สำเร็จ!</span>' : '<span class="error">❌ Login ล้มเหลว!</span>') . "</p>";
                
                if ($verify) {
                    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #28a745;'>";
                    echo "<h3 style='color: #155724;'>🎉 แก้ไขสำเร็จ!</h3>";
                    echo "<p style='color: #155724;'>ตอนนี้คุณสามารถ:</p>";
                    echo "<ol style='color: #155724;'>";
                    echo "<li>Login เข้าระบบ</li>";
                    echo "<li>Logout ออก</li>";
                    echo "<li>Login เข้าใหม่ได้</li>";
                    echo "</ol>";
                    echo "<p><strong>ทดสอบเลย:</strong></p>";
                    echo "<p><a href='auth/login.php' class='btn' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;'>🔐 ไปหน้า Login</a></p>";
                    echo "</div>";
                }
            }
        } else {
            echo "<p class='error'>❌ ไม่พบ users! กลับไปขั้นตอนที่ 2</p>";
            echo "<p><a href='?step=2' class='btn' style='background: #f59e0b; color: white;'>← กลับขั้นตอนที่ 2</a></p>";
        }
    }
    
} else {
    // หน้าแรก
    echo "<h2>🎯 แผนการแก้ไข</h2>";
    echo "<p>ปัญหา: <strong>Users หายหลัง Logout</strong></p>";
    echo "<p>สาเหตุ: <strong>Database ใช้ WAL mode ทำให้ transaction ไม่ commit</strong></p>";
    
    echo "<h3>ขั้นตอนการแก้ไข:</h3>";
    echo "<ol>";
    echo "<li><strong>แก้ไข Database Settings</strong> - ปิด WAL mode, เปิด FULL sync</li>";
    echo "<li><strong>เพิ่ม Users ใหม่</strong> - ลบเก่า สร้างใหม่ พร้อม commit</li>";
    echo "<li><strong>ทดสอบ</strong> - ทดสอบ login และ logout</li>";
    echo "</ol>";
    
    echo "<p><a href='?step=1' class='btn' style='background: #f59e0b; color: white;'>🚀 เริ่มแก้ไข!</a></p>";
    
    // แสดงข้อมูลปัจจุบัน
    echo "<h3>ข้อมูลปัจจุบัน:</h3>";
    $result = $db->query("SELECT COUNT(*) as count FROM users");
    $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
    echo "<p>Users ในระบบ: <strong>{$count}</strong> คน</p>";
    
    $result = $db->query("PRAGMA journal_mode");
    $mode = $result->fetchArray(SQLITE3_ASSOC);
    echo "<p>Journal Mode: <strong>{$mode[0]}</strong></p>";
}

echo "</div>";
echo "</body></html>";
?>