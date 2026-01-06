<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>แก้ไข Users - Final Fix</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; text-align: center; }
h2 { color: #764ba2; margin-top: 30px; padding: 10px; background: white; border-left: 4px solid #667eea; }
.box { background: white; padding: 30px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 0.85em; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
th { background: #f0f0f0; font-weight: 600; }
.btn { display: inline-block; padding: 12px 24px; margin: 10px 5px; text-decoration: none; border-radius: 8px; font-weight: 600; }
.btn-danger { background: #e74c3c; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
</style></head><body>";

echo "<div class='box'>";
echo "<h1>🔧 แก้ไข Users - Final Fix</h1>";
echo "<p style='text-align: center; color: #666;'>สคริปต์นี้จะแก้ปัญหา Login ให้คุณครั้งเดียวจบ!</p>";

$db = Database::getInstance();

// ตรวจสอบ users ปัจจุบัน
echo "<h2>1. ตรวจสอบ Users ปัจจุบัน</h2>";
$result = $db->query("SELECT user_id, username, full_name, role, LENGTH(password) as pwd_length FROM users");
$current_users = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $current_users[] = $row;
}

if (!empty($current_users)) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>ชื่อ</th><th>บทบาท</th><th>Password Length</th></tr>";
    foreach ($current_users as $user) {
        echo "<tr>";
        echo "<td>{$user['user_id']}</td>";
        echo "<td><strong>{$user['username']}</strong></td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>{$user['pwd_length']} chars</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p>จำนวน: " . count($current_users) . " คน</p>";
} else {
    echo "<p class='warning'>⚠️ ไม่มี users ในระบบ</p>";
}

// ทดสอบ password ปัจจุบัน
echo "<h2>2. ทดสอบ Password ปัจจุบัน</h2>";
$test_passwords = [
    'admin' => 'admin123',
    'staff1' => 'staff123'
];

foreach ($test_passwords as $username => $password) {
    $stmt = $db->prepare("SELECT password FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($user) {
        $verify = password_verify($password, $user['password']);
        echo "<p><strong>{$username}</strong> / {$password}: ";
        if ($verify) {
            echo "<span class='success'>✅ ถูกต้อง!</span></p>";
        } else {
            echo "<span class='error'>❌ ผิด! (ต้องแก้ไข)</span></p>";
            echo "<p style='font-size: 0.85em; color: #666;'>Hash: " . substr($user['password'], 0, 50) . "...</p>";
        }
    } else {
        echo "<p><strong>{$username}</strong>: <span class='error'>❌ ไม่พบ user นี้</span></p>";
    }
}

// แก้ไข users
if (isset($_GET['fix'])) {
    echo "<div class='box' style='background: #fff3cd; border: 2px solid #ffc107;'>";
    echo "<h2>3. กำลังแก้ไข...</h2>";
    
    // ลบ users ทั้งหมด
    $db->exec("DELETE FROM users");
    echo "<p class='success'>✅ ลบ users เก่าทั้งหมด</p>";
    
    // สร้าง password hash ใหม่
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $staff_password = password_hash('staff123', PASSWORD_DEFAULT);
    
    echo "<p><strong>Password Hash ที่สร้างใหม่:</strong></p>";
    echo "<pre>admin123: {$admin_password}</pre>";
    echo "<pre>staff123: {$staff_password}</pre>";
    
    // ทดสอบ hash ทันที
    echo "<p><strong>ทดสอบ Hash ทันที:</strong></p>";
    $test1 = password_verify('admin123', $admin_password);
    $test2 = password_verify('staff123', $staff_password);
    echo "<p>admin123 verify: " . ($test1 ? '<span class="success">✅ OK</span>' : '<span class="error">❌ FAIL</span>') . "</p>";
    echo "<p>staff123 verify: " . ($test2 ? '<span class="success">✅ OK</span>' : '<span class="error">❌ FAIL</span>') . "</p>";
    
    if ($test1 && $test2) {
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
        
        // ทดสอบ query กลับมา
        echo "<p><strong>ทดสอบ Query กลับมา:</strong></p>";
        $result = $db->query("SELECT user_id, username, full_name, LENGTH(password) as pwd_len FROM users");
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>ชื่อ</th><th>Password Length</th></tr>";
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            echo "<tr><td>{$row['user_id']}</td><td>{$row['username']}</td><td>{$row['full_name']}</td><td>{$row['pwd_len']}</td></tr>";
        }
        echo "</table>";
        
        // ทดสอบ login จริง
        echo "<p><strong>ทดสอบ Login จริง:</strong></p>";
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindValue(':username', 'admin', SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user) {
            $verify = password_verify('admin123', $user['password']);
            echo "<p>Username: <strong>admin</strong></p>";
            echo "<p>Password Test: <strong>admin123</strong></p>";
            echo "<p>Result: " . ($verify ? '<span class="success">✅ Login สำเร็จ!</span>' : '<span class="error">❌ Login ล้มเหลว!</span>') . "</p>";
            
            if ($verify) {
                echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #28a745;'>";
                echo "<h3 style='color: #155724; margin-top: 0;'>🎉 สำเร็จแล้ว!</h3>";
                echo "<p style='color: #155724;'>ตอนนี้คุณสามารถ Login ได้แล้ว!</p>";
                echo "<p><strong>ข้อมูล Login:</strong></p>";
                echo "<ul style='color: #155724;'>";
                echo "<li>Username: <strong>admin</strong></li>";
                echo "<li>Password: <strong>admin123</strong></li>";
                echo "</ul>";
                echo "<p style='text-align: center; margin-top: 20px;'>";
                echo "<a href='auth/login.php' class='btn btn-primary'>🔐 ไปหน้า Login</a>";
                echo "</p>";
                echo "</div>";
            }
        }
        
    } else {
        echo "<p class='error'>❌ Hash ทดสอบล้มเหลว! มีปัญหากับ PHP password functions!</p>";
    }
    
    echo "</div>";
}

// ปุ่มแก้ไข
if (!isset($_GET['fix'])) {
    echo "<div class='box' style='background: #fff3cd; border: 2px solid #ffc107; text-align: center;'>";
    echo "<h2>⚠️ พร้อมแก้ไขหรือยัง?</h2>";
    echo "<p>สคริปต์นี้จะ:</p>";
    echo "<ul style='text-align: left; display: inline-block;'>";
    echo "<li>ลบ users ทั้งหมด</li>";
    echo "<li>สร้าง admin และ staff1 ใหม่</li>";
    echo "<li>ใช้ password hash ที่ถูกต้อง 100%</li>";
    echo "<li>ทดสอบ login ให้ก่อน redirect</li>";
    echo "</ul>";
    echo "<p><a href='?fix=1' class='btn btn-danger'>🔧 เริ่มแก้ไข!</a></p>";
    echo "</div>";
}

// ลิงก์กลับ
echo "<div class='box' style='text-align: center;'>";
echo "<p><a href='check-database.php' class='btn' style='background: #6c757d; color: white;'>← ตรวจสอบ Database</a></p>";
echo "</div>";

echo "</div>";
echo "</body></html>";
?>