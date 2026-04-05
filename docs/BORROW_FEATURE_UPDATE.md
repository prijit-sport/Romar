# ยืม-คืน (Borrowing) Feature Enhancement

## ประเทศการเปลี่ยนแปลง (Changes Summary)

### 1. **ส่วนติดต่อผู้ใช้ (UI Changes)**
   - ✅ เพิ่มปุ่ม **"บันทึกการยืม"** ในส่วน "ประวัติการยืม-คืน" 
     - ปุ่มนี้มีไว้สำหรับ Admin ที่จะสามารถเพิ่มประวัติการยืมใหม่ได้
   - ✅ เพิ่มฟิลด์ **"สถานที่ยืมไป (Location)"** ในฟอร์มบันทึกการยืม
     - ช่วยให้ติดตามถึงว่าอุปกรณ์ถูกนำไปที่ไหน
   - ✅ อัปเดตตารางประวัติเพื่อแสดงสถานที่ยืมไป
     - เพิ่มคอลัมน์ "สถานที่ยืมไป" ในตารางแสดงประวัติ

### 2. **เปลี่ยนแปลงระหว่างเซิร์ฟเวอร์ (Backend Changes)**
   - ✅ อัปเดต `add_borrow` action ใน `assetsdetail.php`
     - เพิ่มการจัดการ `borrow_location` parameter
     - บันทึก location ในฐานข้อมูล
   - ✅ อัปเดต activity log เพื่อบันทึกสถานที่

### 3. **เปลี่ยนแปลงฐานข้อมูล (Database Changes)**
   - ✅ เพิ่มคอลัมน์ `borrow_location` ในตาราง `asset_borrows`
   - ✅ เพิ่มคอลัมน์อื่น ๆ ที่อาจขาดหายไป:
     - `expected_return` - กำหนดวันคืน
     - `actual_return` - วันที่คืนจริง
     - `condition_out` - สภาพตอนยืม
     - `condition_in` - สภาพตอนคืน

## การทำงาน (How to Use)

### สำหรับ Admin:
1. ไปที่หน้ารายละเอียดอุปกรณ์ (Asset Detail)
2. คลิกแท็บ **"ยืม-คืน"**
3. คลิกปุ่ม **"บันทึกการยืม"** (สีน้ำเงิน)
4. กรอกข้อมูล:
   - 👤 **ผู้ยืม** - เลือกจากรายชื่อผู้ใช้
   - 📅 **วันที่ยืม** - วันที่เริ่มยืม
   - 📅 **กำหนดคืน** - วันที่คาดว่าจะคืน (เลือกได้)
   - 📍 **สถานที่ยืมไป** **(ใหม่!)** - ที่เก็บอุปกรณ์ เช่น "บ้านที่ 25", "Lab Room 301"
   - 📝 **วัตถุประสงค์** - เช่น "ไปอบรม", "ซ่อมบำรุง"
   - 📊 **สภาพอุปกรณ์ตอนยืม** - Good/Fair/Poor
5. คลิก **"บันทึกการยืม"**

### การเรียกดูประวัติ:
- ตารางประวัติจะแสดง:
  - ผู้ยืม | วันที่ยืม | กำหนดคืน | **สถานที่ยืมไป** | วันที่คืน | วัตถุประสงค์ | สภาพตอนยืม | สภาพตอนคืน | สถานะ

## การติดตั้ง / Migration

### วิธีที่ 1: ใช้ Migration Script (แนะนำ)
```bash
# ไปที่ folder database
cd /path/to/Romar/database/

# รันตัวสคริปต์ migrate
php migrate.php
```

### วิธีที่ 2: รัน SQL manually
1. เปิด phpMyAdmin หรือ MySQL client
2. เลือก database `romar_dormitory`
3. รันคำสั่ง SQL จากไฟล์:
   - `migrations/001_update_asset_borrows_table.sql`

### วิธีที่ 3: รัน SQL ใน terminal
```bash
mysql -u [username] -p [database_name] < /path/to/migrations/001_update_asset_borrows_table.sql
```

## ไฟล์ที่แก้ไข

- ✅ `/modules/assetsdetail.php` - เพิ่มปุ่มและอัปเดต logic
- ✅ `/database/migrate.php` - Migration runner script
- ✅ `/database/migrations/001_update_asset_borrows_table.sql` - Migration SQL

## ความปลอดภัย

- ✅ ดำเนินการ CSRF verification
- ✅ Sanitize input ด้วย `sanitize()` function
- ✅ อนุญาตเฉพาะ Admin ให้บันทึกการยืม
- ✅ บันทึกกิจกรรม (activity log) ครบถ้วน

## ปัญหาที่อาจเกิด

### ปัญหา: ปุ่ม "บันทึกการยืม" ไม่ปรากฏ
- **สาเหตุ**: ผู้ใช้งานไม่ใช่ Admin
- **แก้ไข**: มีเฉพาะ Admin เท่านั้นที่สามารถบันทึกการยืมได้

### ปัญหา: Error "Column 'borrow_location' doesn't exist"
- **สาเหตุ**: ยังไม่ได้รัน migration
- **แก้ไข**: รัน migration script ตามขั้นตอนข้างบน

### ปัญหา: Dropdown ผู้ยืมว่าง
- **สาเหตุ**: ไม่มีผู้ใช้งาน active ในระบบ
- **แก้ไข**: ตรวจสอบว่ามีผู้ใช้งาน status = 'active' ในตาราง users

## สรุป

ตอนนี้ระบบสามารถ:
1. ✅ บันทึกว่าใครยืมอุปกรณ์
2. ✅ **บันทึกไปที่ไหน (สถานที่)** ← ใหม่!
3. ✅ ติดตามกำหนดคืน
4. ✅ บันทึกวันคืนและสภาพ
5. ✅ ดูประวัติการยืม-คืนทั้งหมด
