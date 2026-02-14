<?php
/**
 * Database Migration: Add avatar column to users table
 * เพิ่มคอลัมน์ avatar ในตาราง users
 */

require_once '../config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Update Database</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; }
.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #c3e6cb; }
.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #f5c6cb; }
.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #bee5eb; }
.step { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin: 10px 5px 0 0; }
.btn:hover { background: #5568d3; }
</style></head><body>";

echo "<h1>🔧 อัปเดตฐานข้อมูล</h1>";

try {
    $db = Database::getInstance();
    
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 1: ตรวจสอบและเพิ่มคอลัมน์ที่จำเป็น</h2>";
    
    // ตรวจสอบว่ามีคอลัมน์อะไรบ้าง
    $result = $db->query("PRAGMA table_info(users)");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['name'];
    }
    
    // คอลัมน์ที่ต้องการ
    $required_columns = [
        'avatar' => 'TEXT',
        'phone' => 'TEXT',
        'department' => 'TEXT'
    ];
    
    $added_columns = [];
    
    foreach ($required_columns as $col_name => $col_type) {
        if (in_array($col_name, $columns)) {
            echo "<div class='info'>✅ คอลัมน์ <strong>{$col_name}</strong> มีอยู่แล้ว</div>";
        } else {
            echo "<div class='info'>⚠️ ไม่พบคอลัมน์ <strong>{$col_name}</strong> - กำลังเพิ่ม...</div>";
            
            // เพิ่มคอลัมน์
            $db->exec("ALTER TABLE users ADD COLUMN {$col_name} {$col_type}");
            $added_columns[] = $col_name;
            
            echo "<div class='success'>✅ เพิ่มคอลัมน์ <strong>{$col_name}</strong> สำเร็จ!</div>";
        }
    }
    
    if (count($added_columns) > 0) {
        echo "<div class='success'><strong>สรุป:</strong> เพิ่มคอลัมน์ " . implode(', ', $added_columns) . " เรียบร้อยแล้ว</div>";
    }
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 2: ตรวจสอบโครงสร้างตาราง users</h2>";
    
    // แสดงโครงสร้างตาราง
    $result = $db->query("PRAGMA table_info(users)");
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>ลำดับ</th>";
    echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>ชื่อคอลัมน์</th>";
    echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>ชนิดข้อมูล</th>";
    echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>Null?</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . $row['cid'] . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'><strong>" . $row['name'] . "</strong></td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . $row['type'] . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . ($row['notnull'] ? 'NO' : 'YES') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 3: ตรวจสอบข้อมูลผู้ใช้</h2>";
    
    $result = $db->query("SELECT user_id, username, full_name, email, phone, department, avatar FROM users LIMIT 5");
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>ID</th>";
    echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>Username</th>";
    echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>ชื่อ-สกุล</th>";
    echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>อีเมล</th>";
    echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>เบอร์โทร</th>";
    echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>แผนก</th>";
    echo "<th style='padding: 10px; border: 1px solid #dee2e6; text-align: left;'>Avatar</th>";
    echo "</tr>";
    
    $user_count = 0;
    while ($row = $result->fetch_assoc()) {
        $user_count++;
        echo "<tr>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . $row['user_id'] . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . htmlspecialchars($row['username']) . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . ($row['phone'] ?? '-') . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . ($row['department'] ?? '-') . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #dee2e6;'>" . ($row['avatar'] ? '✅ มีรูป' : '❌ ไม่มีรูป') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p style='margin-top: 10px;'>จำนวนผู้ใช้: <strong>" . $user_count . "</strong> คน</p>";
    echo "</div>";
    
    // Checkpoint
    Database::checkpoint();
    
    echo "<div class='success'>";
    echo "<h2>🎉 อัปเดตสำเร็จ!</h2>";
    echo "<p>ฐานข้อมูลได้รับการอัปเดตแล้ว คุณสามารถใช้งานฟีเจอร์รูปโปรไฟล์ได้แล้ว</p>";
    echo "<a href='settings.php' class='btn'>← กลับหน้าตั้งค่า</a>";
    echo "<a href='dashboard.php' class='btn'>📊 ไปหน้า Dashboard</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ เกิดข้อผิดพลาด</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>