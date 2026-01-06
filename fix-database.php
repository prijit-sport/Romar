<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Fix Database</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; }
h2 { color: #764ba2; margin-top: 30px; padding: 10px; background: white; border-left: 4px solid #667eea; }
.box { background: white; padding: 30px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 8px; overflow-x: auto; }
</style></head><body>";

echo "<div class='box'>";
echo "<h1>🔧 แก้ไข Database</h1>";

$db = Database::getInstance();

// ตรวจสอบตารางที่มีอยู่
echo "<h2>1. ตรวจสอบตารางที่มีอยู่</h2>";
$result = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
$tables = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $tables[] = $row['name'];
    echo "✅ " . $row['name'] . "<br>";
}

// ตรวจสอบตาราง activity_logs
echo "<br>";
if (in_array('activity_logs', $tables)) {
    echo "<p class='success'>✅ ตาราง activity_logs มีอยู่แล้ว</p>";
} else {
    echo "<p class='error'>❌ ตาราง activity_logs ไม่มี - ต้องสร้าง!</p>";
}

// สร้างตารางที่ขาด
if (isset($_GET['fix'])) {
    echo "<div class='box'>";
    echo "<h2>2. กำลังแก้ไข...</h2>";
    
    // สร้างตาราง activity_logs
    $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
        log_id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        module TEXT,
        description TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";
    
    if ($db->exec($sql)) {
        echo "<p class='success'>✅ สร้างตาราง activity_logs สำเร็จ!</p>";
    } else {
        echo "<p class='error'>❌ ไม่สามารถสร้างตารางได้</p>";
    }
    
    // สร้าง Index
    $db->exec("CREATE INDEX IF NOT EXISTS idx_activity_user ON activity_logs(user_id)");
    echo "<p class='success'>✅ สร้าง Index สำเร็จ!</p>";
    
    echo "<h3 style='color: green;'>🎉 แก้ไขเสร็จสิ้น!</h3>";
    echo "<p><a href='?' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px;'>← ตรวจสอบอีกครั้ง</a></p>";
    echo "</div>";
}

// ตรวจสอบหลังแก้ไข
if (isset($_GET['fix'])) {
    echo "<div class='box'>";
    echo "<h2>3. ตรวจสอบหลังแก้ไข</h2>";
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "✅ " . $row['name'] . "<br>";
    }
    echo "</div>";
}

// ปุ่มแก้ไข
if (!isset($_GET['fix'])) {
    echo "<div class='box'>";
    echo "<h2>2. แก้ไข Database</h2>";
    echo "<p>คลิกปุ่มด้านล่างเพื่อสร้างตารางที่ขาด</p>";
    echo "<a href='?fix=1' style='display: inline-block; padding: 12px 24px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>🔧 แก้ไข Database</a>";
    echo "</div>";
}

// ข้อมูลเพิ่มเติม
echo "<div class='box'>";
echo "<h2>📋 ข้อมูล SQL</h2>";
echo "<p>SQL ที่จะรัน:</p>";
echo "<pre>CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    module TEXT,
    description TEXT,
    ip_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);</pre>";
echo "</div>";

// ลิงก์กลับ
echo "<div class='box' style='text-align: center;'>";
if (isset($_GET['fix'])) {
    echo "<p><strong>✅ แก้ไขเสร็จแล้ว! ตอนนี้สามารถ Login ได้</strong></p>";
    echo "<p><a href='auth/login.php' style='display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>🔐 ไปหน้า Login</a></p>";
} else {
    echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px;'>← กลับหน้าแรก</a></p>";
}
echo "</div>";

echo "</div>";
echo "</body></html>";
?>