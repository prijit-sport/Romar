<?php
/**
 * SQLite to MySQL Migration Script
 * สคริปต์ย้ายข้อมูลจาก SQLite ไป MySQL
 */

// กำหนดค่า MySQL
define('MYSQL_HOST', 'localhost');
define('MYSQL_USER', 'root');
define('MYSQL_PASS', '');  // รหัสผ่าน MySQL (ค่าเริ่มต้นของ XAMPP คือ ว่าง)
define('MYSQL_DB', 'romar_dormitory');

// กำหนดค่า SQLite
define('SQLITE_DB', __DIR__ . '/../database/dormitory.db');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>SQLite to MySQL Migration</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; text-align: center; }
.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #28a745; }
.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #dc3545; }
.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #17a2b8; }
.warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #ffc107; }
.step { background: white; padding: 25px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
th, td { padding: 12px; border: 1px solid #dee2e6; text-align: left; }
th { background: #f8f9fa; font-weight: 600; }
.progress { background: #e9ecef; border-radius: 4px; height: 30px; overflow: hidden; margin: 10px 0; }
.progress-bar { background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; line-height: 30px; color: white; text-align: center; transition: width 0.5s; }
code { background: #f8f9fa; padding: 2px 6px; border-radius: 4px; font-family: monospace; color: #e83e8c; }
.btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin: 10px 5px 0 0; font-weight: 500; }
.btn:hover { background: #5568d3; }
</style></head><body>";

echo "<h1>🔄 SQLite → MySQL Migration</h1>";

try {
    // ============================================
    // Step 1: เชื่อมต่อ SQLite
    // ============================================
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 1: เชื่อมต่อ SQLite Database</h2>";
    
    if (!file_exists(SQLITE_DB)) {
        throw new Exception("ไม่พบไฟล์ SQLite: " . SQLITE_DB);
    }
    
    $sqlite = new SQLite3(SQLITE_DB);
    echo "<div class='success'>✅ เชื่อมต่อ SQLite สำเร็จ: " . basename(SQLITE_DB) . "</div>";
    echo "</div>";
    
    // ============================================
    // Step 2: เชื่อมต่อ MySQL
    // ============================================
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 2: เชื่อมต่อ MySQL Database</h2>";
    
    $mysql = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB);
    
    if ($mysql->connect_error) {
        throw new Exception("ไม่สามารถเชื่อมต่อ MySQL: " . $mysql->connect_error);
    }
    
    $mysql->set_charset('utf8mb4');
    echo "<div class='success'>✅ เชื่อมต่อ MySQL สำเร็จ: " . MYSQL_DB . "</div>";
    echo "</div>";
    
    // ============================================
    // Step 3: ตรวจสอบตารางใน MySQL
    // ============================================
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 3: ตรวจสอบโครงสร้างตาราง</h2>";
    
    $tables = ['users', 'activity_logs', 'documents', 'announcements', 'tickets', 'meeting_rooms', 'bookings'];
    $mysql_tables = [];
    
    $result = $mysql->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $mysql_tables[] = $row[0];
    }
    
    echo "<table>";
    echo "<tr><th>ตาราง</th><th>สถานะ</th></tr>";
    
    foreach ($tables as $table) {
        $exists = in_array($table, $mysql_tables);
        echo "<tr>";
        echo "<td><strong>" . $table . "</strong></td>";
        echo "<td>" . ($exists ? "<span style='color: #28a745;'>✅ พร้อม</span>" : "<span style='color: #dc3545;'>❌ ยังไม่มี</span>") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    $all_exist = count(array_intersect($tables, $mysql_tables)) === count($tables);
    
    if (!$all_exist) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ กรุณารันไฟล์ mysql-schema.sql ก่อน!</strong><br>";
        echo "เปิด phpMyAdmin → เลือก database 'romar_dormitory' → คลิก SQL → วาง mysql-schema.sql → Go";
        echo "</div>";
        throw new Exception("ยังไม่มีโครงสร้างตารางใน MySQL");
    }
    
    echo "<div class='success'>✅ โครงสร้างตารางพร้อมแล้ว</div>";
    echo "</div>";
    
    // ============================================
    // Step 4: Migrate ข้อมูล
    // ============================================
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 4: ย้ายข้อมูลจาก SQLite → MySQL</h2>";
    
    $total_records = 0;
    $migrated_records = 0;
    
    foreach ($tables as $table) {
        echo "<h3>📋 ตาราง: $table</h3>";
        
        // นับจำนวนข้อมูลใน SQLite
        $count_result = $sqlite->querySingle("SELECT COUNT(*) FROM $table");
        $total_records += $count_result;
        
        echo "<div class='info'>จำนวนข้อมูล: <strong>$count_result</strong> รายการ</div>";
        
        if ($count_result == 0) {
            echo "<div class='warning'>⚠️ ไม่มีข้อมูลในตาราง</div>";
            continue;
        }
        
        // ดึงข้อมูลจาก SQLite
        $data = $sqlite->query("SELECT * FROM $table");
        
        // ดึงชื่อคอลัมน์
        $columns = [];
        $first_row = true;
        
        $insert_count = 0;
        $skip_count = 0;
        
        while ($row = $data->fetchArray(SQLITE3_ASSOC)) {
            if ($first_row) {
                $columns = array_keys($row);
                $first_row = false;
            }
            
            // เตรียม SQL INSERT
            $col_names = implode(', ', array_map(function($col) use ($mysql) {
                return '`' . $mysql->real_escape_string($col) . '`';
            }, $columns));
            
            $values = array_map(function($val) use ($mysql) {
                if ($val === null) return 'NULL';
                return "'" . $mysql->real_escape_string($val) . "'";
            }, $row);
            
            $val_str = implode(', ', $values);
            
            $sql = "INSERT INTO `$table` ($col_names) VALUES ($val_str)";
            
            // Skip admin user ถ้ามีอยู่แล้ว
            if ($table === 'users' && isset($row['username']) && $row['username'] === 'admin') {
                // เช็คว่ามี admin ใน MySQL แล้วหรือไม่
                $check = $mysql->query("SELECT user_id FROM users WHERE username = 'admin'");
                if ($check && $check->num_rows > 0) {
                    $skip_count++;
                    continue;
                }
            }
            
            if ($mysql->query($sql)) {
                $insert_count++;
                $migrated_records++;
            } else {
                echo "<div class='error'>❌ Error: " . $mysql->error . "</div>";
            }
        }
        
        echo "<div class='success'>✅ ย้ายข้อมูลสำเร็จ: <strong>$insert_count</strong> รายการ";
        if ($skip_count > 0) {
            echo " (ข้าม: $skip_count)";
        }
        echo "</div>";
        
        // Progress bar
        $progress = ($count_result > 0) ? round(($insert_count / $count_result) * 100) : 0;
        echo "<div class='progress'>";
        echo "<div class='progress-bar' style='width: {$progress}%;'>{$progress}%</div>";
        echo "</div>";
    }
    
    echo "<div class='success'>";
    echo "<h3>🎉 Migration เสร็จสิ้น!</h3>";
    echo "<p>ย้ายข้อมูลทั้งหมด: <strong>$migrated_records / $total_records</strong> รายการ</p>";
    echo "</div>";
    
    echo "</div>";
    
    // ============================================
    // Step 5: ตรวจสอบข้อมูลใน MySQL
    // ============================================
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 5: ตรวจสอบข้อมูลใน MySQL</h2>";
    
    echo "<table>";
    echo "<tr><th>ตาราง</th><th>จำนวนข้อมูล (SQLite)</th><th>จำนวนข้อมูล (MySQL)</th><th>สถานะ</th></tr>";
    
    $all_match = true;
    
    foreach ($tables as $table) {
        $sqlite_count = $sqlite->querySingle("SELECT COUNT(*) FROM $table");
        $mysql_result = $mysql->query("SELECT COUNT(*) as count FROM $table");
        $mysql_count = $mysql_result->fetch_assoc()['count'];
        
        $match = ($sqlite_count <= $mysql_count); // <= เพราะอาจมี admin user เดิมใน MySQL
        
        if (!$match) $all_match = false;
        
        echo "<tr>";
        echo "<td><strong>$table</strong></td>";
        echo "<td>$sqlite_count</td>";
        echo "<td>$mysql_count</td>";
        echo "<td>" . ($match ? "<span style='color: #28a745;'>✅ ตรงกัน</span>" : "<span style='color: #dc3545;'>❌ ไม่ตรงกัน</span>") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if ($all_match) {
        echo "<div class='success'>✅ ข้อมูลทุกตารางถูกต้อง!</div>";
    } else {
        echo "<div class='warning'>⚠️ มีบางตารางที่จำนวนข้อมูลไม่ตรงกัน กรุณาตรวจสอบ</div>";
    }
    
    echo "</div>";
    
    // ============================================
    // Summary & Next Steps
    // ============================================
    echo "<div class='success' style='text-align: center; padding: 30px;'>";
    echo "<h2>✅ Migration สำเร็จ!</h2>";
    echo "<p style='margin: 20px 0; font-size: 1.1em;'>ข้อมูลถูกย้ายจาก SQLite ไป MySQL เรียบร้อยแล้ว</p>";
    
    echo "<div class='info' style='text-align: left; max-width: 600px; margin: 20px auto;'>";
    echo "<h3>📋 ขั้นตอนถัดไป:</h3>";
    echo "<ol>";
    echo "<li>แทนที่ไฟล์ <code>config/database.php</code> ด้วยเวอร์ชัน MySQL</li>";
    echo "<li>ทดสอบการ Login: username: <code>admin</code>, password: <code>admin123</code></li>";
    echo "<li>ตรวจสอบฟังก์ชันทุกอันทำงานได้ปกติ</li>";
    echo "<li>ย้ายไฟล์ไปเซิร์ฟเวอร์จริง</li>";
    echo "<li>เปลี่ยนรหัสผ่าน admin และ database</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<div>";
    echo "<a href='http://localhost/phpmyadmin/index.php?route=/database/structure&db=romar_dormitory' class='btn' target='_blank'>📊 เปิด phpMyAdmin</a>";
    echo "<a href='../admin/dashboard.php' class='btn'>🏠 ไปหน้า Dashboard</a>";
    echo "</div>";
    echo "</div>";
    
    // ปิดการเชื่อมต่อ
    $sqlite->close();
    $mysql->close();
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ เกิดข้อผิดพลาด</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>