<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<h1>🔐 ตรวจสอบและแก้ไข Password</h1>";

// เชื่อมต่อ Database
$db = Database::getInstance();

// ดึงข้อมูล users ทั้งหมด
echo "<h2>1. ข้อมูล Users ในระบบ</h2>";
$result = $db->query("SELECT user_id, username, full_name, role FROM users");

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>ID</th><th>Username</th><th>ชื่อ</th><th>บทบาท</th>";
echo "</tr>";

while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>" . $row['username'] . "</td>";
    echo "<td>" . $row['full_name'] . "</td>";
    echo "<td>" . $row['role'] . "</td>";
    echo "</tr>";
}
echo "</table><br>";

// ทดสอบ password
echo "<h2>2. ทดสอบ Password</h2>";

$test_passwords = [
    'admin' => 'admin123',
    'staff1' => 'staff123'
];

foreach ($test_passwords as $username => $password) {
    echo "<h3>ทดสอบ: $username</h3>";
    
    // ดึงข้อมูล user
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($user) {
        echo "✅ พบผู้ใช้: " . $user['full_name'] . "<br>";
        echo "Password ใน DB: " . substr($user['password'], 0, 50) . "...<br>";
        
        // ทดสอบ password_verify
        if (password_verify($password, $user['password'])) {
            echo "<span style='color: green;'>✅ Password ถูกต้อง!</span><br>";
        } else {
            echo "<span style='color: red;'>❌ Password ไม่ถูกต้อง!</span><br>";
            echo "<strong>ต้องแก้ไข!</strong><br>";
        }
    } else {
        echo "<span style='color: red;'>❌ ไม่พบผู้ใช้นี้</span><br>";
    }
    echo "<br>";
}

// แก้ไข password
echo "<h2>3. แก้ไข Password</h2>";
echo "<p>กด Execute เพื่ออัพเดท password ใหม่</p>";

if (isset($_GET['fix'])) {
    echo "<h3>🔧 กำลังแก้ไข...</h3>";
    
    // Hash password ใหม่
    $admin_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $staff_hash = password_hash('staff123', PASSWORD_DEFAULT);
    
    // Update admin
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE username = 'admin'");
    $stmt->bindValue(':password', $admin_hash, SQLITE3_TEXT);
    $stmt->execute();
    echo "✅ อัพเดท password สำหรับ admin<br>";
    
    // Update staff1
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE username = 'staff1'");
    $stmt->bindValue(':password', $staff_hash, SQLITE3_TEXT);
    $stmt->execute();
    echo "✅ อัพเดท password สำหรับ staff1<br>";
    
    echo "<br><h3 style='color: green;'>✅ แก้ไขเสร็จสิ้น!</h3>";
    echo "<p><a href='?'>← ย้อนกลับเพื่อตรวจสอบอีกครั้ง</a></p>";
    echo "<p><strong>ตอนนี้สามารถ Login ได้แล้ว!</strong></p>";
    echo "<p><a href='auth/login.php' style='display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>ไปหน้า Login</a></p>";
} else {
    echo "<a href='?fix=1' style='display: inline-block; padding: 12px 24px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>🔧 Execute - แก้ไข Password</a>";
}

echo "<br><br>";
echo "<h2>4. ข้อมูล Password Hash</h2>";
echo "<p><strong>admin123</strong> (hashed): " . password_hash('admin123', PASSWORD_DEFAULT) . "</p>";
echo "<p><strong>staff123</strong> (hashed): " . password_hash('staff123', PASSWORD_DEFAULT) . "</p>";
?>

<style>
body {
    font-family: 'Sarabun', Arial, sans-serif;
    max-width: 1000px;
    margin: 30px auto;
    padding: 20px;
    background: #f5f7fa;
}

h1 {
    color: #667eea;
}

h2 {
    color: #764ba2;
    margin-top: 30px;
    padding: 10px;
    background: white;
    border-left: 4px solid #667eea;
}

h3 {
    color: #333;
}

table {
    background: white;
    width: 100%;
}

code {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 4px;
    color: #e74c3c;
}
</style>