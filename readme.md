🏢 ระบบจัดการหอพัก (Dormitory Management System)
ระบบจัดการหอพักแบบ MVP (Minimum Viable Product) สำหรับการเรียนรู้ PHP + SQLite
⭐ ฟีเจอร์หลัก

✅ IT Ticket System - ระบบแจ้งซ่อม/แจ้งปัญหา
✅ Meeting Room Booking - จองห้องประชุม
✅ Conversation Log - บันทึกการสนทนา
✅ News/Announcement - ระบบประกาศข่าวสาร
✅ Document Upload - อัปโหลดไฟล์เอกสาร
✅ User-Management - จัดการผู้ใช้งาน (Admin)
✅ Activity Logging - บันทึกการใช้งานระบบ

🛠️ เทคโนโลยี

Frontend: HTML, CSS, JavaScript (Vanilla)
Backend: PHP 7.4+
Database: SQLite 3
Server: Apache (XAMPP)

📋 ความต้องการของระบบ

XAMPP (หรือ WAMP/LAMP)
PHP 7.4 หรือสูงกว่า
SQLite3 Extension (มีใน PHP ตั้งแต่ Version 5+)
Web Browser (Chrome, Firefox, Edge, Safari)

🚀 การติดตั้ง
1. ติดตั้ง XAMPP

ดาวน์โหลด XAMPP จาก: https://www.apachefriends.org/
ติดตั้งตามปกติ (แนะนำติดตั้งที่ C:\xampp)
เปิด XAMPP Control Panel
กด Start ที่ Apache (ไม่ต้องเปิด MySQL)

2. คัดลอกโปรเจค

คัดลอกโฟลเดอร์ dormitory-management ไปยัง C:\xampp\htdocs\
โครงสร้างควรเป็น: C:\xampp\htdocs\dormitory-management\

3. ทดสอบระบบ

เปิดเบราว์เซอร์
ไปที่: http://localhost/dormitory-management/
ระบบจะ redirect ไปยังหน้า Login

🔑 ข้อมูลเข้าสู่ระบบ
Admin

Username: admin
Password: admin123

Staff

Username: staff1
Password: staff123

📁 โครงสร้างโปรเจค
dormitory-management/
├── config/              # ไฟล์ตั้งค่า
│   ├── database.php     # เชื่อมต่อ SQLite
│   └── config.php       # ค่าคงที่ระบบ
├── database/            # Database
│   ├── dormitory.db     # SQLite Database (auto-create)
│   └── schema.sql       # SQL Schema
├── assets/              # ไฟล์ Static
│   ├── css/
│   ├── js/
│   ├── images/
│   └── uploads/         # ไฟล์ที่ Upload
├── includes/            # ไฟล์ Template
│   ├── header.php
│   ├── sidebar.php
│   ├── footer.php
│   └── functions.php
├── auth/                # Authentication
│   ├── login.php
│   └── logout.php
├── admin/               # หน้าแอดมิน
│   ├── dashboard.php
│   ├── users.php
│   └── settings.php
├── modules/             # โมดูลต่างๆ
│   ├── tickets/
│   ├── rooms/
│   ├── conversations/
│   ├── announcements/
│   └── documents/
└── index.php            # หน้าแรก
🗄️ Database
ระบบใช้ SQLite3 ซึ่ง:

ไม่ต้อง Setup MySQL Server
Database เป็นไฟล์เดียว (dormitory.db)
สร้างอัตโนมัติเมื่อเข้าใช้งานครั้งแรก
ตารางสร้างจาก database/schema.sql

ตารางหลัก

users - ผู้ใช้งาน
tickets - IT Tickets
meeting_rooms - ห้องประชุม
room_bookings - การจองห้อง
conversations - บันทึกสนทนา
announcements - ประกาศ
documents - เอกสาร
activity_logs - Log การใช้งาน

📝 การใช้งาน
เข้าสู่ระบบ

เปิด http://localhost/dormitory-management/
ใส่ Username และ Password
กด "เข้าสู่ระบบ"

สร้าง IT Ticket

เข้าเมนู "IT Tickets"
กด "สร้าง Ticket"
กรอกข้อมูล และบันทึก

จองห้องประชุม

เข้าเมนู "จองห้องประชุม"
เลือกห้อง วันที่ และเวลา
กด "จอง"

อัปโหลดเอกสาร

เข้าเมนู "เอกสาร"
กด "อัปโหลด"
เลือกไฟล์และบันทึก

⚙️ การตั้งค่า
แก้ไขไฟล์ config/config.php:
php// ชื่อระบบ
define('SITE_NAME', 'ระบบจัดการหอพัก');

// URL (ปรับตามโครงสร้าง)
define('BASE_URL', 'http://localhost/dormitory-management/');

// ขนาดไฟล์ Upload สูงสุด (10MB)
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// Session Timeout (60 นาที)
define('SESSION_TIMEOUT', 60);
🔒 ความปลอดภัย

Password เข้ารหัสด้วย password_hash() (bcrypt)
ใช้ Prepared Statements ป้องกัน SQL Injection
Session Timeout อัตโนมัติ
Input Sanitization
File Upload Validation

🐛 การแก้ปัญหา
Database ไม่ทำงาน
bash# ตรวจสอบว่า SQLite3 เปิดใช้งานหรือยัง
php -m | grep sqlite
Permission Denied (อัปโหลดไฟล์)
bash# Windows: คลิกขวาโฟลเดอร์ uploads > Properties > Security
# ให้สิทธิ์ Write ทุกคน
Session หมดอายุบ่อย
php// แก้ใน config/config.php
define('SESSION_TIMEOUT', 120); // เพิ่มเป็น 120 นาที
📚 แหล่งเรียนรู้

PHP Manual: https://www.php.net/manual/
SQLite Documentation: https://www.sqlite.org/docs.html
XAMPP Guide: https://www.apachefriends.org/docs/

🔄 ขั้นตอนถัดไป (MVP Phase 2)

 สร้างหน้า Ticket Management เต็มรูปแบบ
 ปฏิทินการจองห้องประชุม
 ระบบค้นหาและ Filter
 Export ข้อมูลเป็น Excel/PDF
 Email Notification
 Dashboard Charts/Graphs

👨‍💻 Developer
สร้างเพื่อการศึกษา PHP + SQLite
📄 License
MIT License - ใช้งานและแก้ไขได้ตามสบาย

Happy Coding! 🚀