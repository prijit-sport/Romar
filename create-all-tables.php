<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>สร้างตารางทั้งหมด</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; }
h2 { color: #764ba2; margin-top: 30px; padding: 10px; background: white; border-left: 4px solid #667eea; }
.box { background: white; padding: 30px; border-radius: 12px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
ul { line-height: 2; }
</style></head><body>";

echo "<div class='box'>";
echo "<h1>🛠️ สร้างตารางทั้งหมด</h1>";

$db = Database::getInstance();

// รายการตารางทั้งหมดที่ต้องมี
$required_tables = [
    'users',
    'tickets',
    'meeting_rooms',
    'room_bookings',
    'conversations',
    'announcements',
    'documents',
    'activity_logs'
];

// ตรวจสอบตารางที่มีอยู่
echo "<h2>1. ตรวจสอบตารางที่มีอยู่</h2>";
$result = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
$existing_tables = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $existing_tables[] = $row['name'];
}

echo "<p><strong>ตารางที่มีอยู่:</strong></p><ul>";
foreach ($existing_tables as $table) {
    echo "<li class='success'>✅ {$table}</li>";
}
echo "</ul>";

// ตารางที่ขาด
$missing_tables = array_diff($required_tables, $existing_tables);
if (!empty($missing_tables)) {
    echo "<p><strong class='error'>ตารางที่ขาด:</strong></p><ul>";
    foreach ($missing_tables as $table) {
        echo "<li class='error'>❌ {$table}</li>";
    }
    echo "</ul>";
} else {
    echo "<p class='success'>✅ มีตารางครบทุกตารางแล้ว!</p>";
}

// สร้างตารางที่ขาด
if (isset($_GET['create'])) {
    echo "<div class='box'>";
    echo "<h2>2. กำลังสร้างตาราง...</h2>";
    
    $sql_statements = [];
    
    // ตาราง users
    $sql_statements['users'] = "CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        full_name TEXT NOT NULL,
        email TEXT,
        role TEXT DEFAULT 'staff' CHECK(role IN ('admin', 'staff')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME,
        is_active INTEGER DEFAULT 1
    )";
    
    // ตาราง tickets
    $sql_statements['tickets'] = "CREATE TABLE IF NOT EXISTS tickets (
        ticket_id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_number TEXT UNIQUE NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        priority TEXT DEFAULT 'medium' CHECK(priority IN ('low', 'medium', 'high', 'urgent')),
        status TEXT DEFAULT 'open' CHECK(status IN ('open', 'in_progress', 'resolved', 'closed')),
        category TEXT,
        created_by INTEGER,
        assigned_to INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        resolved_at DATETIME,
        FOREIGN KEY (created_by) REFERENCES users(user_id),
        FOREIGN KEY (assigned_to) REFERENCES users(user_id)
    )";
    
    // ตาราง meeting_rooms
    $sql_statements['meeting_rooms'] = "CREATE TABLE IF NOT EXISTS meeting_rooms (
        room_id INTEGER PRIMARY KEY AUTOINCREMENT,
        room_name TEXT NOT NULL,
        capacity INTEGER,
        location TEXT,
        amenities TEXT,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    // ตาราง room_bookings
    $sql_statements['room_bookings'] = "CREATE TABLE IF NOT EXISTS room_bookings (
        booking_id INTEGER PRIMARY KEY AUTOINCREMENT,
        room_id INTEGER NOT NULL,
        booked_by INTEGER NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        purpose TEXT,
        attendees INTEGER,
        status TEXT DEFAULT 'confirmed' CHECK(status IN ('confirmed', 'cancelled', 'completed')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES meeting_rooms(room_id),
        FOREIGN KEY (booked_by) REFERENCES users(user_id)
    )";
    
    // ตาราง conversations
    $sql_statements['conversations'] = "CREATE TABLE IF NOT EXISTS conversations (
        conversation_id INTEGER PRIMARY KEY AUTOINCREMENT,
        subject TEXT NOT NULL,
        conversation_with TEXT,
        conversation_type TEXT CHECK(conversation_type IN ('phone', 'email', 'in_person', 'other')),
        notes TEXT,
        recorded_by INTEGER NOT NULL,
        conversation_date DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (recorded_by) REFERENCES users(user_id)
    )";
    
    // ตาราง announcements
    $sql_statements['announcements'] = "CREATE TABLE IF NOT EXISTS announcements (
        announcement_id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        priority TEXT DEFAULT 'normal' CHECK(priority IN ('normal', 'important', 'urgent')),
        published_by INTEGER NOT NULL,
        publish_date DATETIME NOT NULL,
        expire_date DATETIME,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (published_by) REFERENCES users(user_id)
    )";
    
    // ตาราง documents
    $sql_statements['documents'] = "CREATE TABLE IF NOT EXISTS documents (
        document_id INTEGER PRIMARY KEY AUTOINCREMENT,
        document_name TEXT NOT NULL,
        file_name TEXT NOT NULL,
        file_path TEXT NOT NULL,
        file_size INTEGER,
        file_type TEXT,
        category TEXT,
        description TEXT,
        uploaded_by INTEGER NOT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
    )";
    
    // ตาราง activity_logs
    $sql_statements['activity_logs'] = "CREATE TABLE IF NOT EXISTS activity_logs (
        log_id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        module TEXT,
        description TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";
    
    // สร้างตารางทั้งหมด
    foreach ($sql_statements as $table_name => $sql) {
        try {
            $db->exec($sql);
            echo "<p class='success'>✅ สร้างตาราง {$table_name} สำเร็จ!</p>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error สร้าง {$table_name}: " . $e->getMessage() . "</p>";
        }
    }
    
    // สร้าง Indexes
    echo "<h3>สร้าง Indexes</h3>";
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status)",
        "CREATE INDEX IF NOT EXISTS idx_tickets_created_by ON tickets(created_by)",
        "CREATE INDEX IF NOT EXISTS idx_bookings_date ON room_bookings(booking_date)",
        "CREATE INDEX IF NOT EXISTS idx_announcements_active ON announcements(is_active)",
        "CREATE INDEX IF NOT EXISTS idx_activity_user ON activity_logs(user_id)"
    ];
    
    foreach ($indexes as $index_sql) {
        try {
            $db->exec($index_sql);
            echo "<p class='success'>✅ สร้าง Index สำเร็จ</p>";
        } catch (Exception $e) {
            echo "<p class='warning'>⚠️ Index อาจมีอยู่แล้ว</p>";
        }
    }
    
    // Insert ข้อมูลตัวอย่าง
    echo "<h3>เพิ่มข้อมูลตัวอย่าง</h3>";
    
    // ห้องประชุม
    $db->exec("INSERT OR IGNORE INTO meeting_rooms (room_id, room_name, capacity, location, amenities, is_active) VALUES 
        (1, 'ห้องประชุมใหญ่', 30, 'ชั้น 2', 'Projector, Whiteboard, Wi-Fi, Air-Con', 1),
        (2, 'ห้องประชุมเล็ก', 10, 'ชั้น 2', 'TV Screen, Whiteboard, Wi-Fi', 1),
        (3, 'ห้องอบรม', 50, 'ชั้น 3', 'Projector, Sound System, Air-Con', 1)");
    echo "<p class='success'>✅ เพิ่มข้อมูลห้องประชุม</p>";
    
    // ประกาศ
    $db->exec("INSERT OR IGNORE INTO announcements (announcement_id, title, content, priority, published_by, publish_date, is_active) VALUES 
        (1, 'ยินดีต้อนรับสู่ระบบจัดการหอพัก', 'ระบบพร้อมใช้งานแล้ว สามารถเริ่มใช้งานได้ทันที', 'important', 1, datetime('now'), 1)");
    echo "<p class='success'>✅ เพิ่มประกาศตัวอย่าง</p>";
    
    echo "<h3 style='color: green;'>🎉 สร้างตารางทั้งหมดเสร็จสิ้น!</h3>";
    echo "<p><a href='?' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px;'>← ตรวจสอบอีกครั้ง</a></p>";
    echo "</div>";
}

// แสดงสถานะหลังสร้าง
if (isset($_GET['create'])) {
    echo "<div class='box'>";
    echo "<h2>3. ตรวจสอบหลังสร้าง</h2>";
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
    echo "<ul>";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $is_required = in_array($row['name'], $required_tables);
        if ($is_required) {
            echo "<li class='success'>✅ {$row['name']}</li>";
        } else {
            echo "<li>{$row['name']}</li>";
        }
    }
    echo "</ul>";
    echo "</div>";
}

// ปุ่มสร้าง
if (!isset($_GET['create'])) {
    echo "<div class='box'>";
    echo "<h2>2. สร้างตารางที่ขาด</h2>";
    if (!empty($missing_tables)) {
        echo "<p class='warning'>⚠️ พบตารางที่ขาด " . count($missing_tables) . " ตาราง</p>";
        echo "<p>คลิกปุ่มด้านล่างเพื่อสร้างตารางทั้งหมด</p>";
        echo "<a href='?create=1' style='display: inline-block; padding: 12px 24px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>🛠️ สร้างตารางทั้งหมด</a>";
    } else {
        echo "<p class='success'>✅ มีตารางครบทุกตารางแล้ว!</p>";
    }
    echo "</div>";
}

// ลิงก์กลับ
echo "<div class='box' style='text-align: center;'>";
if (isset($_GET['create'])) {
    echo "<p><strong>✅ ตอนนี้พร้อม Login และเข้า Dashboard ได้แล้ว!</strong></p>";
    echo "<p><a href='auth/login.php' style='display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>🔐 ไปหน้า Login</a></p>";
} else {
    echo "<p><a href='index.php' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 6px;'>← กลับหน้าแรก</a></p>";
}
echo "</div>";

echo "</div>";
echo "</body></html>";
?>
