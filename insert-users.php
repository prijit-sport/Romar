<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Insert Users</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 800px; margin: 30px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; }
h2 { color: #764ba2; margin-top: 30px; padding: 10px; background: white; border-left: 4px solid #667eea; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.box { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 8px; overflow-x: auto; }
</style></head><body>";
echo "<div class='box'>";

echo "<h1>👥 เพิ่มข้อมูล Users</h1>";

$db = Database::getInstance();

// ตรวจสอบข้อมูลปัจจุบัน
echo "<div class='box'>";
echo "<h2>1. ตรวจสอบข้อมูลปัจจุบัน</h2>";
$result = $db->query("SELECT COUNT(*) as count FROM users");
$row = $result->fetchArray(SQLITE3_ASSOC);
echo "<p>จำนวน Users ในระบบ: <strong>{$row['count']}</strong> คน</p>";

if ($row['count'] > 0) {
    echo "<p>รายชื่อ Users:</p>";
    $result = $db->query("SELECT user_id, username, full_name, role FROM users");
    echo "<ul>";
    while ($user = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "<li>ID: {$user['user_id']} | Username: <strong>{$user['username']}</strong> | ชื่อ: {$user['full_name']} | บทบาท: {$user['role']}</li>";
    }
    echo "</ul>";
}
echo "</div>";

// เพิ่มข้อมูล
if (isset($_GET['add'])) {
    echo "<div class='box'>";
    echo "<h2>2. กำลังเพิ่มข้อมูล...</h2>";
    
    // ลบข้อมูลเก่า (ถ้ามี)
    $db->exec("DELETE FROM users");
    echo "<p>✅ ลบข้อมูลเก่า</p>";
    
    // สร้าง password hash
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $staff_password = password_hash('staff123', PASSWORD_DEFAULT);
    
    // Insert Admin
    $stmt = $db->prepare("
        INSERT INTO users (username, password, full_name, email, role, is_active, created_at) 
        VALUES (:username, :password, :full_name, :email, :role, :is_active, :created_at)
    ");
    
    $stmt->bindValue(':username', 'admin', SQLITE3_TEXT);
    $stmt->bindValue(':password', $admin_password, SQLITE3_TEXT);
    $stmt->bindValue(':full_name', 'ผู้ดูแลระบบ', SQLITE3_TEXT);
    $stmt->bindValue(':email', 'admin@dormitory.com', SQLITE3_TEXT);
    $stmt->bindValue(':role', 'admin', SQLITE3_TEXT);
    $stmt->bindValue(':is_active', 1, SQLITE3_INTEGER);
    $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $stmt->execute();
    
    echo "<p class='success'>✅ เพิ่ม Admin สำเร็จ!</p>";
    
    // Insert Staff1
    $stmt = $db->prepare("
        INSERT INTO users (username, password, full_name, email, role, is_active, created_at) 
        VALUES (:username, :password, :full_name, :email, :role, :is_active, :created_at)
    ");
    
    $stmt->bindValue(':username', 'staff1', SQLITE3_TEXT);
    $stmt->bindValue(':password', $staff_password, SQLITE3_TEXT);
    $stmt->bindValue(':full_name', 'พนักงาน 1', SQLITE3_TEXT);
    $stmt->bindValue(':email', 'staff1@dormitory.com', SQLITE3_TEXT);
    $stmt->bindValue(':role', 'staff', SQLITE3_TEXT);
    $stmt->bindValue(':is_active', 1, SQLITE3_INTEGER);
    $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $stmt->execute();
    
    echo "<p class='success'>✅ เพิ่ม Staff1 สำเร็จ!</p>";
    
    echo "<h3 style='color: green;'>🎉 เพิ่มข้อมูลสำเร็จ!</h3>";
    echo "<p><a href='?' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px;'>← ย้อนกลับตรวจสอบ</a></p>";
    echo "</div>";
    
    // แสดงข้อมูลใหม่
    echo "<div class='box'>";
    echo "<h2>3. ข้อมูลหลังเพิ่ม</h2>";
    $result = $db->query("SELECT user_id, username, full_name, email, role FROM users");
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Username</th><th>ชื่อ</th><th>Email</th><th>บทบาท</th></tr>";
    while ($user = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "<tr>";
        echo "<td>{$user['user_id']}</td>";
        echo "<td><strong>{$user['username']}</strong></td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    echo "<div class='box'>";
    echo "<h2>✅ เสร็จสิ้น!</h2>";
    echo "<p>ตอนนี้สามารถ Login ได้แล้ว!</p>";
    echo "<p><strong>ข้อมูล Login:</strong></p>";
    echo "<ul>";
    echo "<li>Admin: <code>admin</code> / <code>admin123</code></li>";
    echo "<li>Staff: <code>staff1</code> / <code>staff123</code></li>";
    echo "</ul>";
    echo "<p><a href='auth/login.php' style='display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>🔐 ไปหน้า Login</a></p>";
    echo "</div>";
    
} else {
    echo "<div class='box'>";
    echo "<h2>2. เพิ่มข้อมูล Users</h2>";
    echo "<p>คลิกปุ่มด้านล่างเพื่อเพิ่มข้อมูล Users</p>";
    echo "<p style='color: red;'><strong>⚠️ คำเตือน:</strong> การดำเนินการนี้จะลบข้อมูล Users เก่าทั้งหมดและเพิ่มข้อมูลใหม่</p>";
    echo "<a href='?add=1' style='display: inline-block; padding: 12px 24px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>➕ เพิ่มข้อมูล Users</a>";
    echo "</div>";
}

echo "</body></html>";
?>