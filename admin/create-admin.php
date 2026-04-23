<?php
/**
 * Create Admin User
 * สร้าง Admin user และตรวจสอบฐานข้อมูล
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('🔒 Admin creation restricted to CLI only. Use php admin/create-admin.php');
}

require_once dirname(__DIR__) . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Create Admin User</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; text-align: center; }
.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #28a745; }
.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #dc3545; }
.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #17a2b8; }
.warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #ffc107; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
th, td { padding: 12px; border: 1px solid #dee2e6; text-align: left; }
th { background: #f8f9fa; font-weight: 600; }
code { background: #f8f9fa; padding: 2px 6px; border-radius: 4px; color: #e83e8c; }
.btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin: 10px 5px 0 0; font-weight: 500; }
</style></head><body>";

echo "<h1>🔧 สร้าง Admin User</h1>";

try {
    $db = getDB();
    
    echo "<div class='success'>";
    echo "<h2>✅ เชื่อมต่อ MySQL สำเร็จ</h2>";
    echo "</div>";
    
    // ตรวจสอบตาราง users
    echo "<div class='info'>";
    echo "<h2>📊 ตรวจสอบตาราง users</h2>";
    
    $result = $db->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    $userCount = $row['count'];
    
    echo "<p>จำนวน users ในฐานข้อมูล: <strong>$userCount</strong> คน</p>";
    
    if ($userCount > 0) {
        echo "<h3>👥 รายชื่อ Users ทั้งหมด:</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Active</th></tr>";
        
        $users = $db->query("SELECT user_id, username, full_name, role, is_active FROM users");
        while ($user = $users->fetch_assoc()) {
            $activeText = $user['is_active'] ? '✅ ใช้งาน' : '❌ ปิดใช้งาน';
            $roleText = $user['role'] === 'admin' ? '<strong style="color: #dc3545;">Admin</strong>' : 'User';
            
            echo "<tr>";
            echo "<td>{$user['user_id']}</td>";
            echo "<td><code>{$user['username']}</code></td>";
            echo "<td>{$user['full_name']}</td>";
            echo "<td>$roleText</td>";
            echo "<td>$activeText</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // สร้าง Admin user ถ้ายังไม่มี
    $adminCheck = $db->query("SELECT * FROM users WHERE username = 'admin'");
    
    if ($adminCheck->num_rows == 0) {
        echo "<div class='warning'>";
        echo "<h2>⚠️ ไม่พบ Admin user</h2>";
        echo "<p>กำลังสร้าง Admin user ใหม่...</p>";
        echo "</div>";
        
        // สร้าง admin user
        $username = 'admin';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $fullName = 'Administrator';
        $email = 'admin@romar.com';
        
        $stmt = $db->prepare("
            INSERT INTO users (username, password, full_name, email, role, is_active, created_at)
            VALUES (?, ?, ?, ?, 'admin', 1, NOW())
        ");
        
        $stmt->bind_param('ssss', $username, $password, $fullName, $email);
        
        if ($stmt->execute()) {
            echo "<div class='success'>";
            echo "<h2>✅ สร้าง Admin สำเร็จ!</h2>";
            echo "<table>";
            echo "<tr><th>Username</th><td><code>admin</code></td></tr>";
            echo "<tr><th>Password</th><td><code>admin123</code></td></tr>";
            echo "<tr><th>Role</th><td><strong>Admin</strong></td></tr>";
            echo "</table>";
            echo "</div>";
        } else {
            throw new Exception("ไม่สามารถสร้าง Admin: " . $stmt->error);
        }
        
    } else {
        $admin = $adminCheck->fetch_assoc();
        
        echo "<div class='info'>";
        echo "<h2>ℹ️ พบ Admin User แล้ว</h2>";
        echo "<table>";
        echo "<tr><th>Username</th><td><code>{$admin['username']}</code></td></tr>";
        echo "<tr><th>Full Name</th><td>{$admin['full_name']}</td></tr>";
        echo "<tr><th>Email</th><td>{$admin['email']}</td></tr>";
        echo "<tr><th>Status</th><td>" . ($admin['is_active'] ? '✅ ใช้งาน' : '❌ ปิดใช้งาน') . "</td></tr>";
        echo "</table>";
        
        if (!$admin['is_active']) {
            // Enable admin
            $db->query("UPDATE users SET is_active = 1 WHERE username = 'admin'");
            echo "<p style='color: #28a745;'>✅ เปิดใช้งาน Admin แล้ว</p>";
        }
        
        echo "<h3>🔑 รหัสผ่าน Default:</h3>";
        echo "<p>ถ้าลืมรหัสผ่าน ให้ใช้ปุ่มด้านล่างเพื่อ Reset</p>";
        echo "<div class='warning'>";
        echo "<p>🔒 <strong>Password reset disabled for security.</strong></p>";
        echo "<p>Use CLI: <code>php admin/create-admin.php reset</code> หรือ change ใน DB directly.</p>";
        echo "</div>";
        echo "<a href='../auth/login.php' class='btn'>🔐 ไป Login</a>";
        echo "<a href='http://localhost/phpmyadmin/' class='btn' target='_blank'>📊 phpMyAdmin</a>";

        echo "</div>";
    }
    
    // 🔒 WEB PASSWORD RESET DISABLED FOR SECURITY
    // CLI reset: php admin/create-admin.php reset
    
    // ตรวจสอบตารางอื่นๆ
    echo "<div class='info'>";
    echo "<h2>📋 ตรวจสอบตารางอื่นๆ</h2>";
    echo "<table>";
    echo "<tr><th>ตาราง</th><th>จำนวนข้อมูล</th></tr>";
    
    $tables = ['meeting_rooms', 'bookings', 'documents', 'announcements', 'tickets', 'activity_logs'];
    
    foreach ($tables as $table) {
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM $table");
            $row = $result->fetch_assoc();
            echo "<tr><td><strong>$table</strong></td><td>{$row['count']} รายการ</td></tr>";
        } catch (Exception $e) {
            echo "<tr><td><strong>$table</strong></td><td style='color: #dc3545;'>❌ ไม่พบตาราง</td></tr>";
        }
    }
    
    echo "</table>";
    echo "</div>";
    
    // สร้าง Sample Meeting Rooms ถ้ายังไม่มี
    $roomCheck = $db->query("SELECT COUNT(*) as count FROM meeting_rooms");
    $roomRow = $roomCheck->fetch_assoc();
    
    if ($roomRow['count'] == 0) {
        echo "<div class='warning'>";
        echo "<h2>⚠️ ไม่พบห้องประชุม</h2>";
        echo "<p>กำลังสร้างห้องประชุมตัวอย่าง...</p>";
        echo "</div>";
        
        $rooms = [
            ['ห้องประชุมเล็ก', 10, 'ชั้น 2', 'โปรเจคเตอร์, ไวท์บอร์ด, Wi-Fi, เครื่องปรับอากาศ'],
            ['ห้องประชุมใหญ่', 30, 'ชั้น 2', 'โปรเจคเตอร์, ไวท์บอร์ด, Wi-Fi, ระบบเสียง, ระบบ Video Conference, เครื่องปรับอากาศ'],
            ['ห้องอบรม', 50, 'ชั้น 3', 'โปรเจคเตอร์, ไวท์บอร์ด, Wi-Fi, ระบบเสียง, คอมพิวเตอร์ 20 เครื่อง, เครื่องปรับอากาศ']
        ];
        
        $stmt = $db->prepare("
            INSERT INTO meeting_rooms (room_name, capacity, location, facilities, is_active, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        
        foreach ($rooms as $room) {
            $stmt->bind_param('siss', $room[0], $room[1], $room[2], $room[3]);
            $stmt->execute();
        }
        
        echo "<div class='success'>";
        echo "<h2>✅ สร้างห้องประชุมสำเร็จ!</h2>";
        echo "<ul>";
        foreach ($rooms as $room) {
            echo "<li>{$room[0]} (ความจุ {$room[1]} คน)</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
    // สรุป
    echo "<div class='success' style='text-align: center; padding: 30px;'>";
    echo "<h2>🎉 พร้อมใช้งานแล้ว!</h2>";
    echo "<h3>ข้อมูล Login:</h3>";
    echo "<table style='max-width: 400px; margin: 20px auto;'>";
    echo "<tr><th>Username</th><td><code>admin</code></td></tr>";
    echo "<tr><th>Password</th><td><code>admin123</code></td></tr>";
    echo "</table>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='../auth/login.php' class='btn'>🔐 ไปหน้า Login</a>";
    echo "<a href='http://localhost/phpmyadmin/index.php?route=/database/structure&db=romar_dormitory' class='btn' target='_blank'>📊 เปิด phpMyAdmin</a>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h2>📝 ขั้นตอนถัดไป</h2>";
    echo "<ol>";
    echo "<li>คลิกปุ่ม \"ไปหน้า Login\" ด้านบน</li>";
    echo "<li>ใส่ Username: <code>admin</code>, Password: <code>admin123</code></li>";
    echo "<li>เข้าสู่ระบบ</li>";
    echo "<li>ทดสอบฟีเจอร์ต่างๆ</li>";
    echo "<li>เปลี่ยนรหัสผ่านใน Settings</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ เกิดข้อผิดพลาด</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
    
    echo "<div class='warning'>";
    echo "<h2>💡 วิธีแก้ไข</h2>";
    echo "<ol>";
    echo "<li>ตรวจสอบว่า MySQL Service ทำงานหรือไม่</li>";
    echo "<li>ตรวจสอบ config/database.php</li>";
    echo "<li>ตรวจสอบว่า Database 'romar_dormitory' มีอยู่หรือไม่</li>";
    echo "<li>รัน mysql-schema.sql ใน phpMyAdmin ถ้ายังไม่ได้รัน</li>";
    echo "</ol>";
    echo "</div>";
}

echo "</body></html>";
?>
