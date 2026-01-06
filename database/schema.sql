CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    full_name TEXT NOT NULL,
    email TEXT,
    role TEXT DEFAULT 'staff' CHECK(role IN ('admin', 'staff')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    is_active INTEGER DEFAULT 1
);

-- ตาราง tickets (IT Ticket System)
CREATE TABLE IF NOT EXISTS tickets (
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
);

-- ตาราง meeting_rooms (ห้องประชุม)
CREATE TABLE IF NOT EXISTS meeting_rooms (
    room_id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_name TEXT NOT NULL,
    capacity INTEGER,
    location TEXT,
    amenities TEXT,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ตาราง room_bookings (การจองห้องประชุม)
CREATE TABLE IF NOT EXISTS room_bookings (
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
);

-- ตาราง conversations (บันทึกการสนทนา)
CREATE TABLE IF NOT EXISTS conversations (
    conversation_id INTEGER PRIMARY KEY AUTOINCREMENT,
    subject TEXT NOT NULL,
    conversation_with TEXT,
    conversation_type TEXT CHECK(conversation_type IN ('phone', 'email', 'in_person', 'other')),
    notes TEXT,
    recorded_by INTEGER NOT NULL,
    conversation_date DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id)
);

-- ตาราง announcements (ประกาศข่าวสาร)
CREATE TABLE IF NOT EXISTS announcements (
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
);

-- ตาราง documents (เอกสาร)
CREATE TABLE IF NOT EXISTS documents (
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
);

-- ตาราง activity_logs (บันทึกการใช้งาน)
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    module TEXT,
    description TEXT,
    ip_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- สร้าง Index เพื่อเพิ่มความเร็วในการค้นหา
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_created_by ON tickets(created_by);
CREATE INDEX IF NOT EXISTS idx_bookings_date ON room_bookings(booking_date);
CREATE INDEX IF NOT EXISTS idx_announcements_active ON announcements(is_active);

-- ===================================================================
-- ⚠️ สำคัญ: ข้อมูลเริ่มต้นจะถูกเพิ่มโดย insert-users.php
-- ไม่ควรใส่ password hash ตายตัวใน schema.sql
-- เพราะจะทำให้ login ไม่ได้
-- ===================================================================

-- Insert ห้องประชุมตัวอย่าง
INSERT OR IGNORE INTO meeting_rooms (room_name, capacity, location, amenities, is_active) 
VALUES 
('ห้องประชุมใหญ่', 30, 'ชั้น 2', 'Projector, Whiteboard, Wi-Fi, Air-Con', 1),
('ห้องประชุมเล็ก', 10, 'ชั้น 2', 'TV Screen, Whiteboard, Wi-Fi', 1),
('ห้องอบรม', 50, 'ชั้น 3', 'Projector, Sound System, Air-Con', 1);