<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>ตรวจสอบ Database</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; }
h2 { color: #764ba2; margin-top: 30px; padding: 10px; background: white; border-left: 4px solid #667eea; }
.box { background: white; padding: 30px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
th { background: #f0f0f0; font-weight: 600; }
</style></head><body>";

echo "<div class='box'>";
echo "<h1>🔍 ตรวจสอบ Database แบบละเอียด</h1>";

$db = Database::getInstance();

// 1. ข้อมูล Database
echo "<h2>1. ข้อมูล Database</h2>";
echo "<p><strong>Database Path:</strong> " . DB_PATH . "</p>";
echo "<p><strong>ไฟล์มีอยู่จริง:</strong> " . (file_exists(DB_PATH) ? '✅ Yes' : '❌ No') . "</p>";
if (file_exists(DB_PATH)) {
    echo "<p><strong>ขนาดไฟล์:</strong> " . number_format(filesize(DB_PATH) / 1024, 2) . " KB</p>";
}

// 2. ตารางทั้งหมด
echo "<h2>2. ตารางทั้งหมดใน Database</h2>";
$result = $db->query("SELECT name, sql FROM sqlite_master WHERE type='table' ORDER BY name");

$tables = [];
echo "<table>";
echo "<tr><th>ลำดับ</th><th>ชื่อตาราง</th><th>สถานะ</th></tr>";
$i = 1;
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $tables[] = $row['name'];
    $is_required = in_array($row['name'], ['users', 'tickets', 'meeting_rooms', 'room_bookings', 'conversations', 'announcements', 'documents', 'activity_logs']);
    
    echo "<tr>";
    echo "<td>{$i}</td>";
    echo "<td><strong>{$row['name']}</strong></td>";
    echo "<td>" . ($is_required ? "<span class='success'>✅ Required</span>" : "ℹ️ System") . "</td>";
    echo "</tr>";
    $i++;
}
echo "</table>";
echo "<p><strong>จำนวนตารางทั้งหมด:</strong> " . count($tables) . " ตาราง</p>";

// 3. ตรวจสอบตารางที่จำเป็น
echo "<h2>3. ตรวจสอบตารางที่จำเป็น</h2>";
$required_tables = [
    'users' => 'ผู้ใช้งาน',
    'tickets' => 'IT Tickets',
    'meeting_rooms' => 'ห้องประชุม',
    'room_bookings' => 'การจองห้อง',
    'conversations' => 'บันทึกสนทนา',
    'announcements' => 'ประกาศข่าวสาร',
    'documents' => 'เอกสาร',
    'activity_logs' => 'บันทึกกิจกรรม'
];

echo "<table>";
echo "<tr><th>ตาราง</th><th>คำอธิบาย</th><th>สถานะ</th><th>จำนวนแถว</th></tr>";
foreach ($required_tables as $table => $description) {
    $exists = in_array($table, $tables);
    
    echo "<tr>";
    echo "<td><strong>{$table}</strong></td>";
    echo "<td>{$description}</td>";
    
    if ($exists) {
        echo "<td><span class='success'>✅ มี</span></td>";
        
        // นับจำนวนแถว
        try {
            $count_result = $db->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $count_result->fetchArray(SQLITE3_ASSOC)['count'];
            echo "<td>{$count} แถว</td>";
        } catch (Exception $e) {
            echo "<td>-</td>";
        }
    } else {
        echo "<td><span class='error'>❌ ไม่มี</span></td>";
        echo "<td>-</td>";
    }
    echo "</tr>";
}
echo "</table>";

// 4. ตารางที่ขาด
$missing = array_diff(array_keys($required_tables), $tables);
if (!empty($missing)) {
    echo "<div style='background: #fee; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3 class='error'>❌ ตารางที่ขาด:</h3>";
    echo "<ul>";
    foreach ($missing as $table) {
        echo "<li><strong>{$table}</strong> - {$required_tables[$table]}</li>";
    }
    echo "</ul>";
    echo "<p><a href='create-all-tables.php?create=1' style='display: inline-block; padding: 12px 24px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>🛠️ สร้างตารางที่ขาด</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #efe; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3 class='success'>✅ ตารางครบทุกตารางแล้ว!</h3>";
    echo "</div>";
}

// 5. โครงสร้างตาราง tickets (ถ้ามี)
if (in_array('tickets', $tables)) {
    echo "<h2>4. โครงสร้างตาราง tickets</h2>";
    $result = $db->query("PRAGMA table_info(tickets)");
    echo "<table>";
    echo "<tr><th>ลำดับ</th><th>ชื่อคอลัมน์</th><th>ชนิดข้อมูล</th><th>NULL</th><th>ค่าเริ่มต้น</th><th>Primary Key</th></tr>";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['cid']}</td>";
        echo "<td><strong>{$row['name']}</strong></td>";
        echo "<td>{$row['type']}</td>";
        echo "<td>" . ($row['notnull'] ? '❌' : '✅') . "</td>";
        echo "<td>" . ($row['dflt_value'] ?? '-') . "</td>";
        echo "<td>" . ($row['pk'] ? '🔑' : '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 6. ทดสอบ Query
echo "<h2>5. ทดสอบ Query ตาราง tickets</h2>";
if (in_array('tickets', $tables)) {
    try {
        $result = $db->query("SELECT COUNT(*) as count FROM tickets");
        $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
        echo "<p class='success'>✅ Query สำเร็จ! มี {$count} รายการใน tickets</p>";
        
        // ลอง SELECT *
        $result = $db->query("SELECT * FROM tickets LIMIT 5");
        echo "<p class='success'>✅ SELECT * สำเร็จ!</p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Query ล้มเหลว: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>❌ ไม่สามารถ Query ได้เพราะตาราง tickets ไม่มี!</p>";
}

echo "</div>";

// ปุ่มกลับ
echo "<div class='box' style='text-align: center;'>";
echo "<p><a href='admin/dashboard.php' style='display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px;'>📊 กลับไป Dashboard</a></p>";
echo "<p><a href='create-all-tables.php' style='display: inline-block; padding: 10px 20px; background: #f59e0b; color: white; text-decoration: none; border-radius: 6px;'>🛠️ สร้างตารางทั้งหมด</a></p>";
echo "</div>";

echo "</body></html>";
?>