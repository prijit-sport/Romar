<?php
// ไฟล์ Debug - เปิดการแสดง Error ทั้งหมด
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 ตรวจสอบระบบ</h1>";

// 1. ตรวจสอบ PHP Version
echo "<h2>1. PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "✅ PHP ทำงานปกติ<br><br>";

// 2. ตรวจสอบ SQLite
echo "<h2>2. SQLite Extension</h2>";
if (class_exists('SQLite3')) {
    echo "✅ SQLite3 พร้อมใช้งาน<br><br>";
} else {
    echo "❌ SQLite3 ไม่พร้อม - ต้องเปิดใช้งาน<br><br>";
}

// 3. ตรวจสอบโฟลเดอร์
echo "<h2>3. โครงสร้างโฟลเดอร์</h2>";
$folders = ['config', 'auth', 'database', 'includes', 'admin'];
foreach ($folders as $folder) {
    if (is_dir(__DIR__ . '/' . $folder)) {
        echo "✅ /" . $folder . " - มีอยู่<br>";
    } else {
        echo "❌ /" . $folder . " - ไม่มี<br>";
    }
}
echo "<br>";

// 4. ตรวจสอบไฟล์สำคัญ
echo "<h2>4. ไฟล์สำคัญ</h2>";
$files = [
    'config/config.php',
    'config/database.php',
    'includes/functions.php',
    'auth/login.php'
];

foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ " . $file . " - มีอยู่<br>";
    } else {
        echo "❌ " . $file . " - ไม่มี<br>";
    }
}
echo "<br>";

// 5. ทดสอบ require config
echo "<h2>5. ทดสอบโหลด Config</h2>";
try {
    require_once __DIR__ . '/config/config.php';
    echo "✅ config.php โหลดสำเร็จ<br>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
echo "<br>";

// 6. ทดสอบ Database
echo "<h2>6. ทดสอบ Database</h2>";
try {
    require_once __DIR__ . '/config/database.php';
    $db = Database::getInstance();
    echo "✅ Database เชื่อมต่อสำเร็จ<br>";
    
    // นับ users
    $result = $db->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    echo "✅ มีผู้ใช้ในระบบ: " . $row['count'] . " คน<br>";
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "<br>";
}
echo "<br>";

// 7. ทดสอบ Functions
echo "<h2>7. ทดสอบ Functions</h2>";
try {
    require_once __DIR__ . '/includes/functions.php';
    echo "✅ functions.php โหลดสำเร็จ<br>";
    
    if (function_exists('verifyLogin')) {
        echo "✅ ฟังก์ชัน verifyLogin() มีอยู่<br>";
    } else {
        echo "❌ ฟังก์ชัน verifyLogin() ไม่มี<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
echo "<br>";

// 8. สรุป
echo "<h2>📝 สรุป</h2>";
echo "<p>ถ้าทุกอย่างขึ้น ✅ แสดงว่าระบบพร้อมใช้งาน</p>";
echo "<p>ถ้ามี ❌ ให้แก้ไขปัญหาตามที่แสดง</p>";
echo "<br>";
echo '<a href="auth/login.php" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">ไปหน้า Login</a>';
?>

<style>
body {
    font-family: 'Sarabun', Arial, sans-serif;
    max-width: 900px;
    margin: 30px auto;
    padding: 20px;
    background: #f5f7fa;
}

h1 {
    color: #667eea;
}

h2 {
    color: #764ba2;
    margin-top: 20px;
    padding: 10px;
    background: white;
    border-left: 4px solid #667eea;
}
</style>