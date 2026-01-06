<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>ตรวจสอบปัญหา Logout</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 1000px; margin: 30px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; }
h2 { color: #764ba2; margin-top: 30px; padding: 10px; background: white; border-left: 4px solid #667eea; }
.box { background: white; padding: 30px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 0.85em; white-space: pre-wrap; }
.btn { display: inline-block; padding: 12px 24px; margin: 10px 5px; text-decoration: none; border-radius: 8px; font-weight: 600; color: white; }
</style></head><body>";

echo "<div class='box'>";
echo "<h1>🔍 ตรวจสอบปัญหา Users หายหลัง Logout</h1>";

$db = Database::getInstance();

// ตรวจสอบ users ปัจจุบัน
echo "<h2>1. ตรวจสอบ Users ในระบบ</h2>";
$result = $db->query("SELECT COUNT(*) as count FROM users");
$count = $result->fetchArray(SQLITE3_ASSOC)['count'];

if ($count > 0) {
    echo "<p class='success'>✅ มี {$count} users ในระบบ</p>";
    
    $result = $db->query("SELECT user_id, username, full_name, role FROM users");
    echo "<ul>";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "<li><strong>{$row['username']}</strong> ({$row['full_name']}) - {$row['role']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p class='error'>❌ ไม่มี users ในระบบ! (ถูกลบไปแล้ว)</p>";
}

// ตรวจสอบไฟล์ logout.php
echo "<h2>2. ตรวจสอบไฟล์ Logout</h2>";
$logout_file = __DIR__ . '/auth/logout.php';

if (file_exists($logout_file)) {
    echo "<p class='success'>✅ พบไฟล์ logout.php</p>";
    echo "<p><strong>เนื้อหาไฟล์:</strong></p>";
    $content = file_get_contents($logout_file);
    echo "<pre>" . htmlspecialchars($content) . "</pre>";
    
    // ตรวจสอบคำสั่งอันตราย
    $dangerous = false;
    if (stripos($content, 'DELETE FROM users') !== false) {
        echo "<p class='error'>⚠️ พบคำสั่ง DELETE FROM users! นี่คือสาเหตุ!</p>";
        $dangerous = true;
    }
    if (stripos($content, 'TRUNCATE') !== false) {
        echo "<p class='error'>⚠️ พบคำสั่ง TRUNCATE!</p>";
        $dangerous = true;
    }
    if (stripos($content, 'DROP TABLE') !== false) {
        echo "<p class='error'>⚠️ พบคำสั่ง DROP TABLE!</p>";
        $dangerous = true;
    }
    
    if (!$dangerous) {
        echo "<p class='success'>✅ ไม่พบคำสั่งอันตรายในไฟล์ logout</p>";
    }
} else {
    echo "<p class='error'>❌ ไม่พบไฟล์ logout.php</p>";
}

// ตรวจสอบ Database Mode
echo "<h2>3. ตรวจสอบ SQLite Mode</h2>";
$result = $db->query("PRAGMA journal_mode");
$mode = $result->fetchArray(SQLITE3_ASSOC);
echo "<p>Journal Mode: <strong>" . $mode[0] . "</strong></p>";

if ($mode[0] === 'wal') {
    echo "<p class='warning'>⚠️ ใช้ WAL mode - อาจมีปัญหา transaction</p>";
    echo "<p>แนะนำ: เปลี่ยนเป็น DELETE mode</p>";
}

// แสดงวิธีแก้ไข
echo "<div class='box' style='background: #fff3cd; border: 2px solid #ffc107;'>";
echo "<h2>4. วิธีแก้ไข</h2>";
echo "<p><strong>ปัญหาที่พบ:</strong> Users หายหลัง Logout</p>";
echo "<p><strong>สาเหตุที่เป็นไปได้:</strong></p>";
echo "<ol>";
echo "<li>Logout function มีคำสั่งลบ users (ไม่น่าจะใช่)</li>";
echo "<li>Database Transaction ไม่ได้ commit (น่าจะเป็น!)</li>";
echo "<li>SQLite WAL mode ทำให้ข้อมูลไม่ถาวร (น่าจะเป็น!)</li>";
echo "</ol>";

echo "<p><strong>วิธีแก้:</strong></p>";
echo "<ol>";
echo "<li>แก้ไข database.php ให้ commit transaction</li>";
echo "<li>เปลี่ยน journal mode เป็น DELETE</li>";
echo "<li>ปิด WAL mode</li>";
echo "</ol>";

if (isset($_GET['fix'])) {
    echo "<h3>กำลังแก้ไข...</h3>";
    
    // เปลี่ยน journal mode
    $db->exec("PRAGMA journal_mode = DELETE");
    echo "<p class='success'>✅ เปลี่ยน journal mode เป็น DELETE</p>";
    
    // ปิด WAL
    $db->exec("PRAGMA synchronous = FULL");
    echo "<p class='success'>✅ เปิด synchronous = FULL</p>";
    
    echo "<p class='success'>✅ แก้ไขเสร็จสิ้น!</p>";
    echo "<p><a href='?' class='btn' style='background: #667eea;'>← ตรวจสอบอีกครั้ง</a></p>";
}

if (!isset($_GET['fix'])) {
    echo "<p><a href='?fix=1' class='btn' style='background: #f59e0b;'>🔧 แก้ไข Database Settings</a></p>";
}

echo "</div>";

// คำแนะนำ
echo "<div class='box'>";
echo "<h2>5. ทดสอบหลังแก้ไข</h2>";
echo "<ol>";
echo "<li>คลิก \"แก้ไข Database Settings\" ข้างบน</li>";
echo "<li>รัน <a href='final-fix-users.php'>final-fix-users.php</a> อีกครั้ง</li>";
echo "<li>Login เข้าระบบ</li>";
echo "<li>Logout ออก</li>";
echo "<li>Login อีกครั้ง</li>";
echo "<li>ถ้ายัง Login ได้ = แก้ปัญหาสำเร็จ!</li>";
echo "</ol>";
echo "</div>";

// ตรวจสอบ Activity Logs
echo "<h2>6. Activity Logs (ดูว่าเกิดอะไรขึ้น)</h2>";
$result = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10");
$logs = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $logs[] = $row;
}

if (!empty($logs)) {
    echo "<ul>";
    foreach ($logs as $log) {
        echo "<li>[{$log['created_at']}] {$log['action']} - {$log['description']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>ไม่มี activity logs</p>";
}

echo "</div>";
echo "</body></html>";
?>