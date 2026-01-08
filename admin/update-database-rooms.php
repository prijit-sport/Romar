<?php
/**
 * Database Migration: Create tables for Meeting Room Booking System
 * สร้างตารางสำหรับระบบจองห้องประชุม
 */

require_once '../config/database.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Update Database - Room Booking</title>";
echo "<style>
body { font-family: 'Sarabun', Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
h1 { color: #667eea; }
.success { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #c3e6cb; }
.error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #f5c6cb; }
.info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #bee5eb; }
.step { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin: 10px 5px 0 0; }
.btn:hover { background: #5568d3; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 10px; border: 1px solid #dee2e6; text-align: left; }
th { background: #f8f9fa; font-weight: 600; }
</style></head><body>";

echo "<h1>🏢 อัปเดตฐานข้อมูล - ระบบจองห้องประชุม</h1>";

try {
    $db = Database::getInstance();
    
    // ตรวจสอบตารางที่มีอยู่
    $existing_tables = [];
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $existing_tables[] = $row['name'];
    }
    
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 1: สร้างตาราง meeting_rooms</h2>";
    
    if (in_array('meeting_rooms', $existing_tables)) {
        echo "<div class='info'>✅ ตาราง meeting_rooms มีอยู่แล้ว</div>";
    } else {
        $sql = "CREATE TABLE meeting_rooms (
            room_id INTEGER PRIMARY KEY AUTOINCREMENT,
            room_name TEXT NOT NULL,
            capacity INTEGER NOT NULL,
            location TEXT,
            facilities TEXT,
            image TEXT,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        )";
        
        $db->exec($sql);
        echo "<div class='success'>✅ สร้างตาราง meeting_rooms สำเร็จ!</div>";
        
        // เพิ่มข้อมูลห้องประชุมตัวอย่าง
        $sample_rooms = [
            ['ห้องประชุม A', 10, 'ชั้น 1', 'โปรเจคเตอร์, ไวท์บอร์ด, Wi-Fi'],
            ['ห้องประชุม B', 20, 'ชั้น 2', 'โปรเจคเตอร์, ไวท์บอร์ด, Wi-Fi, ระบบเสียง'],
            ['ห้องประชุม C', 30, 'ชั้น 3', 'โปรเจคเตอร์, ไวท์บอร์ด, Wi-Fi, ระบบเสียง, ระบบ Video Conference'],
            ['ห้องประชุมใหญ่', 50, 'ชั้น 4', 'โปรเจคเตอร์ 2 เครื่อง, ไวท์บอร์ด, Wi-Fi, ระบบเสียง, ระบบ Video Conference, จอ LED']
        ];
        
        foreach ($sample_rooms as $room) {
            $stmt = $db->prepare("INSERT INTO meeting_rooms (room_name, capacity, location, facilities) VALUES (?, ?, ?, ?)");
            $stmt->bindValue(1, $room[0], SQLITE3_TEXT);
            $stmt->bindValue(2, $room[1], SQLITE3_INTEGER);
            $stmt->bindValue(3, $room[2], SQLITE3_TEXT);
            $stmt->bindValue(4, $room[3], SQLITE3_TEXT);
            $stmt->execute();
        }
        
        echo "<div class='success'>✅ เพิ่มข้อมูลห้องประชุมตัวอย่าง 4 ห้อง</div>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 2: สร้างตาราง bookings</h2>";
    
    if (in_array('bookings', $existing_tables)) {
        echo "<div class='info'>✅ ตาราง bookings มีอยู่แล้ว</div>";
    } else {
        $sql = "CREATE TABLE bookings (
            booking_id INTEGER PRIMARY KEY AUTOINCREMENT,
            room_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            booking_date TEXT NOT NULL,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL,
            num_attendees INTEGER NOT NULL,
            purpose TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            notes TEXT,
            approved_by INTEGER,
            approved_at TEXT,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (room_id) REFERENCES meeting_rooms(room_id),
            FOREIGN KEY (user_id) REFERENCES users(user_id),
            FOREIGN KEY (approved_by) REFERENCES users(user_id)
        )";
        
        $db->exec($sql);
        echo "<div class='success'>✅ สร้างตาราง bookings สำเร็จ!</div>";
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 3: แสดงโครงสร้างตาราง meeting_rooms</h2>";
    
    $result = $db->query("PRAGMA table_info(meeting_rooms)");
    echo "<table>";
    echo "<tr><th>ลำดับ</th><th>ชื่อคอลัมน์</th><th>ชนิดข้อมูล</th><th>Null?</th></tr>";
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['cid'] . "</td>";
        echo "<td><strong>" . $row['name'] . "</strong></td>";
        echo "<td>" . $row['type'] . "</td>";
        echo "<td>" . ($row['notnull'] ? 'NO' : 'YES') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 4: แสดงโครงสร้างตาราง bookings</h2>";
    
    $result = $db->query("PRAGMA table_info(bookings)");
    echo "<table>";
    echo "<tr><th>ลำดับ</th><th>ชื่อคอลัมน์</th><th>ชนิดข้อมูล</th><th>Null?</th></tr>";
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['cid'] . "</td>";
        echo "<td><strong>" . $row['name'] . "</strong></td>";
        echo "<td>" . $row['type'] . "</td>";
        echo "<td>" . ($row['notnull'] ? 'NO' : 'YES') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>ขั้นตอนที่ 5: แสดงข้อมูลห้องประชุม</h2>";
    
    $result = $db->query("SELECT * FROM meeting_rooms");
    echo "<table>";
    echo "<tr><th>ID</th><th>ชื่อห้อง</th><th>ความจุ</th><th>สถานที่</th><th>สถานะ</th></tr>";
    
    $room_count = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $room_count++;
        echo "<tr>";
        echo "<td>" . $row['room_id'] . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['room_name']) . "</strong></td>";
        echo "<td>" . $row['capacity'] . " คน</td>";
        echo "<td>" . htmlspecialchars($row['location']) . "</td>";
        echo "<td>" . ($row['is_active'] ? '✅ ใช้งานได้' : '❌ ปิดใช้งาน') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p>จำนวนห้องประชุม: <strong>" . $room_count . "</strong> ห้อง</p>";
    echo "</div>";
    
    // Checkpoint
    Database::checkpoint();
    
    echo "<div class='success'>";
    echo "<h2>🎉 อัปเดตสำเร็จ!</h2>";
    echo "<p>ฐานข้อมูลสำหรับระบบจองห้องประชุมพร้อมใช้งานแล้ว</p>";
    echo "<a href='room-booking.php' class='btn'>📅 จองห้องประชุม</a>";
    echo "<a href='my-bookings.php' class='btn'>📋 รายการจองของฉัน</a>";
    echo "<a href='meeting-rooms.php' class='btn'>🏢 จัดการห้องประชุม (Admin)</a>";
    echo "<a href='dashboard.php' class='btn'>← กลับหน้าหลัก</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ เกิดข้อผิดพลาด</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>