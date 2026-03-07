# Romar Dormitory Management System - Project Report

## สถานะโปรเจกต์

โปรเจกต์นี้เป็นระบบจัดการหอพัก (Dormitory Management System) ที่พัฒนาด้วย PHP + MySQL

---

## ปัญหาที่พบและแนวทางแก้ไข

### 1. Database Schema Mismatch ✅ แก้ไขแล้ว

**ปัญหา:**
- `database/schema.sql` ใช้ SQLite syntax (INTEGER PRIMARY KEY AUTOINCREMENT)
- แต่โค้ดทั้งหมดใช้ MySQL/mysqli

**แนวทางแก้ไข:**
- สร้างไฟล์ใหม่ `database/schema_mysql.sql` ที่ใช้ MySQL syntax สมบูรณ์
- ปรับปรุง schema ให้รองรับทุกฟีเจอร์ (tickets, assets, SLA, etc.)

### 2. ไฟล์ Migration ✅ เพิ่มแล้ว

สร้าง `modules/database/migrate_to_mysql.php` สำหรับ:
- ตรวจสอบการเชื่อมต่อ database
- สร้าง default admin user
- เพิ่ม default meeting rooms, SLA rules, และ sample assets

### 3. Duplicate Functions

**ปัญหา:**
มีฟังก์ชันที่ประกาศซ้ำกันหลายตำแหน่ง:
- `redirect()` - ประกาศใน config.php และ functions.php
- `isLoggedIn()` - ประกาศใน config.php และ functions.php  
- `isAdmin()` - ประกาศใน config.php และ functions.php
- `getCurrentUserId()` - ประกาศใน config.php และ functions.php

**แนวทางแก้ไข:**
ใช้ `function_exists()` check ก่อนประกาศฟังก์ชัน (มีอยู่แล้วใน functions.php)

### 4. Session Handling

**ปัญหา:**
หลายไฟล์เรียก `session_start()` โดยไม่ตรวจสอบสถานะ

**แนวทางแก้ไข:**
ใช้ `session_status()` ก่อนเรียก session_start()

### 5. Raw SQL Queries

**ปัญหา:**
บางตำแหน่งใช้ `$db->query()` โดยตรงกับ user input (potential SQL injection)

**แนวทางแก้ไข:**
ใช้ Prepared Statements เสมอ (มีการใช้แล้วส่วนใหญ่)

---

## ขั้นตอนการติดตั้ง

### 1. สร้าง Database

```sql
-- เปิด MySQL และสร้าง database
CREATE DATABASE romar_dormitory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Import Schema

```bash
# Import MySQL schema
mysql -u root -p romar_dormitory < database/schema_mysql.sql
```

### 3. ตั้งค่า Environment

แก้ไขไฟล์ `.env`:
```
ROMAR_DB_HOST=127.0.0.1
ROMAR_DB_USER=root
ROMAR_DB_PASS=your_password
ROMAR_DB_NAME=romar_dormitory
```

### 4. Run Migration Script

```bash
php modules/database/migrate_to_mysql.php
```

### 5. เริ่มใช้งาน

```bash
# Start PHP server
php -S localhost:8000

# เปิด browser
http://localhost:8000
```

**Default Login:**
- Username: `admin`
- Password: `admin123`

---

## โครงสร้างไฟล์

```
Romar/
├── admin/                 # Admin pages
│   ├── dashboard.php
│   ├── announcements.php
│   ├── documents.php
│   ├── meeting-rooms.php
│   ├── room-booking.php
│   ├── users-management.php
│   └── ...
├── api/                   # API endpoints
├── auth/                  # Authentication
│   ├── login.php
│   └── logout.php
├── config/                # Configuration
│   ├── config.php
│   └── database.php
├── database/              # Database schemas
│   ├── schema.sql         # SQLite (เก่า)
│   └── schema_mysql.sql   # MySQL (ใหม่)
├── includes/              # Shared functions
│   ├── functions.php
│   ├── header.php
│   └── footer.php
├── modules/               # Module pages
│   ├── tickets.php
│   ├── dashboard.php
│   ├── assets.php
│   └── ...
├── uploads/               # File uploads
├── .htaccess             # Security config
└── index.php             # Entry point
```

---

## Security Features

✅ CSRF Protection  
✅ Prepared Statements  
✅ Session Management  
✅ Rate Limiting  
✅ Security Headers (CSP, X-Frame-Options, etc.)  
✅ Password Hashing (password_hash)  
✅ Input Sanitization  

---

## ฟีเจอร์หลัก

1. **User Management** - จัดการผู้ใช้งาน (admin, staff, user)
2. **IT Tickets** - ระบบแจ้งปัญหา IT
3. **Assets** - จัดการทรัพย์สิน IT
4. **Meeting Rooms** - จองห้องประชุม
5. **Announcements** - ประกาศข่าวสาร
6. **Documents** - จัดการเอกสาร
7. **SLA** - ติดตาม SLA
8. **Knowledge Base** - ฐานความรู้

---

## สถานะการแก้ไข

| หัวข้อ | สถานะ |
|--------|-------|
| MySQL Schema | ✅ เสร็จสิ้น |
| Migration Script | ✅ เสร็จสิ้น |
| Duplicate Functions | ⚠️ ใช้ function_exists แล้ว |
| Session Handling | ⚠️ มีการ handle ในบางไฟล์ |
| SQL Injection | ⚠️ ใช้ Prepared Statements ส่วนใหญ่ |

---

## หมายเหตุ

- โปรเจกต์นี้เดิมใช้ SQLite แต่ถูก migrate เป็น MySQL
- ควรลบไฟล์ `database/schema.sql` หรือย้ายไป folder อื่นถ้าไม่ใช้แล้ว
- ไฟล์ `database.php` เดิมอาจไม่จำเป็นแล้ว (ใช้ config/database.php แทน)

