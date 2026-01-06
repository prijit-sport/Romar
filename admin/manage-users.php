<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>จัดการ Users</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 1000px; margin: 30px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; }
h2 { color: #764ba2; margin-top: 30px; padding: 10px; background: white; border-left: 4px solid #667eea; }
.box { background: white; padding: 30px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
th { background: #f0f0f0; font-weight: 600; }
.btn { display: inline-block; padding: 8px 16px; margin: 2px; text-decoration: none; border-radius: 6px; font-size: 0.9em; }
.btn-danger { background: #e74c3c; color: white; }
.btn-primary { background: #667eea; color: white; }
.btn-success { background: #27ae60; color: white; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
</style></head><body>";

echo "<h1>👥 จัดการผู้ใช้งาน</h1>";

$db = Database::getInstance();

// ลบ user
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    echo "<div class='box'>";
    echo "<h2>🗑️ ลบผู้ใช้งาน</h2>";
    
    // ตรวจสอบก่อนลบ
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :id");
    $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($user) {
        // ลบ user
        $stmt = $db->prepare("DELETE FROM users WHERE user_id = :id");
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        echo "<p class='success'>✅ ลบผู้ใช้ '{$user['username']}' สำเร็จ!</p>";
        echo "<p><a href='?' class='btn btn-primary'>← กลับไปดูรายการ</a></p>";
    } else {
        echo "<p class='error'>❌ ไม่พบผู้ใช้นี้</p>";
    }
    echo "</div>";
}

// แสดงรายการ users
echo "<div class='box'>";
echo "<h2>📋 รายการผู้ใช้งานทั้งหมด</h2>";

$result = $db->query("SELECT * FROM users ORDER BY user_id");
$users = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

if (empty($users)) {
    echo "<p>ไม่มีผู้ใช้งานในระบบ</p>";
} else {
    echo "<table>";
    echo "<tr>";
    echo "<th>ID</th><th>Username</th><th>ชื่อ-นามสกุล</th><th>Email</th><th>บทบาท</th><th>สถานะ</th><th>สร้างเมื่อ</th><th>จัดการ</th>";
    echo "</tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['user_id']}</td>";
        echo "<td><strong>{$user['username']}</strong></td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>" . ($user['role'] === 'admin' ? '👨‍💼 Admin' : '👤 Staff') . "</td>";
        echo "<td>" . ($user['is_active'] ? '✅ Active' : '❌ Inactive') . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($user['created_at'])) . "</td>";
        echo "<td>";
        
        // ป้องกันไม่ให้ลบ admin คนสุดท้าย
        $admin_count = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetchArray(SQLITE3_ASSOC)['count'];
        
        if ($user['role'] === 'admin' && $admin_count <= 1) {
            echo "<span style='color: #95a5a6; font-size: 0.85em;'>ไม่สามารถลบ Admin คนสุดท้าย</span>";
        } else {
            echo "<a href='?delete={$user['user_id']}' class='btn btn-danger' onclick='return confirm(\"แน่ใจหรือว่าต้องการลบ {$user['username']}?\")'>🗑️ ลบ</a>";
        }
        
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='margin-top: 20px;'><strong>จำนวนผู้ใช้ทั้งหมด:</strong> " . count($users) . " คน</p>";
}
echo "</div>";

// ข้อมูลสำคัญ
echo "<div class='box'>";
echo "<h2>⚠️ คำเตือน</h2>";
echo "<ul style='line-height: 2;'>";
echo "<li>การลบผู้ใช้จะไม่สามารถกู้คืนได้</li>";
echo "<li>ระบบจะไม่ให้ลบ Admin คนสุดท้าย (ป้องกันไม่มี Admin เหลือ)</li>";
echo "<li>ถ้าต้องการเพิ่ม User ใหม่ ให้ไปที่ <a href='insert-users.php'>insert-users.php</a></li>";
echo "<li>ถ้าต้องการแก้ไข Password ให้ไปที่ <a href='fix-password.php'>fix-password.php</a></li>";
echo "</ul>";
echo "</div>";

// เพิ่ม User ใหม่ (ฟอร์มง่ายๆ)
echo "<div class='box'>";
echo "<h2>➕ เพิ่มผู้ใช้ใหม่</h2>";

if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    
    if (!empty($username) && !empty($password)) {
        // Hash password
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert
        $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role, is_active, created_at) VALUES (:username, :password, :full_name, :email, :role, 1, :created_at)");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':password', $hashed, SQLITE3_TEXT);
        $stmt->bindValue(':full_name', $full_name, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':role', $role, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            echo "<p class='success'>✅ เพิ่มผู้ใช้ '{$username}' สำเร็จ!</p>";
            echo "<p><a href='?' class='btn btn-primary'>← กลับไปดูรายการ</a></p>";
        } else {
            echo "<p class='error'>❌ เกิดข้อผิดพลาด (อาจมี username ซ้ำ)</p>";
        }
    }
} else {
    echo "<form method='POST'>";
    echo "<table>";
    echo "<tr><td>Username:</td><td><input type='text' name='username' required style='width: 100%; padding: 8px;'></td></tr>";
    echo "<tr><td>Password:</td><td><input type='password' name='password' required style='width: 100%; padding: 8px;'></td></tr>";
    echo "<tr><td>ชื่อ-นามสกุล:</td><td><input type='text' name='full_name' required style='width: 100%; padding: 8px;'></td></tr>";
    echo "<tr><td>Email:</td><td><input type='email' name='email' style='width: 100%; padding: 8px;'></td></tr>";
    echo "<tr><td>บทบาท:</td><td>";
    echo "<select name='role' style='width: 100%; padding: 8px;'>";
    echo "<option value='staff'>Staff (พนักงาน)</option>";
    echo "<option value='admin'>Admin (ผู้ดูแลระบบ)</option>";
    echo "</select>";
    echo "</td></tr>";
    echo "<tr><td colspan='2'><button type='submit' name='add_user' style='padding: 12px 24px; background: #27ae60; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;'>➕ เพิ่มผู้ใช้</button></td></tr>";
    echo "</table>";
    echo "</form>";
}
echo "</div>";

// ลิงก์กลับ
echo "<div class='box' style='text-align: center;'>";
echo "<p><a href='index.php' class='btn btn-primary'>← กลับหน้าแรก</a></p>";
echo "<p><a href='auth/login.php' class='btn btn-success'>🔐 ไปหน้า Login</a></p>";
echo "</div>";

echo "</body></html>";
?>