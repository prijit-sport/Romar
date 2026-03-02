# 📋 CODE REVIEW REPORT - Romar Dormitory Management System
**วันที่: 2 มีนาคม 2026**

---

## 📊 สรุปอักษร

| ประเมิน | สถานะ | หมายเหตุ |
|-------|-------|--------|
| **ไฟล์ทั้งหมด** | 45+ ไฟล์ | PHP, HTML, JS, CSS |
| **ความปลอดภัย** | 🔴 น้อยเสี่ยง | SQL Injection, Error Exposure |
| **คุณภาพโค้ด** | 🟡 ปานกลาง | ผสมผสานระหว่างดี-ปรับปรุง |
| **การจัดการข้อมูล** | 🟡 ปานกลาง | ขาดการ validate บางส่วน |
| **โครงสร้าง** | 🟢 ดี | โฟลเดอร์แบ่งหมวดชัดเจน |

---

## 🚨 CRITICAL ISSUES - ต้องแก้ทันที

### 1. **SQL INJECTION VULNERABILITIES** ⚠️⚠️⚠️
**ไฟล์ที่มีปัญหา:**
- [modules/assets.php](modules/assets.php#L79) - บรรทัด 79, 88, 162, 234, 237
- [modules/assetsdetail.php](modules/assetsdetail.php#L28) - บรรทัด 28, 29, 57, 89, 103, 126
- [modules/dashboard.php](modules/dashboard.php#L25) - บรรทัด 25+
- [modules/assetsreports.php](modules/assetsreports.php#L56) - บรรทัด 56, 103, 104

**ปัญหา:**
```php
// ❌ WRONG - SQL Injection Risk
$db->query("SELECT asset_id FROM assets WHERE asset_tag = '$asset_tag'");
$db->query("SELECT * FROM assets WHERE asset_type IN ('$typeList')");
$db->query("UPDATE assets SET status='maintenance' WHERE asset_id=$assetId");
```

**วิธีแก้:**
```php
// ✅ CORRECT - Use Prepared Statements
$stmt = $db->prepare("SELECT asset_id FROM assets WHERE asset_tag = ?");
$stmt->bind_param('s', $asset_tag);
$stmt->execute();
$result = $stmt->get_result();
```

---

### 2. **Database Connection Mismatch** ⚠️⚠️
**ไฟล์:**
- [database.php](database.php#L50) - ใช้ SQLite3 binding แต่ config ใช้ MySQL
- [config/database.php](config/database.php#L1-50) - MySQL connection

**ปัญหา:**
```php
// config/database.php ใช้ MySQLi
function getDB() {
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
}

// database.php ใช้ SQLite3
$stmt->bindValue(':document_name', $document_name, SQLITE3_TEXT); // ❌
```

---

### 3. **Error Exposure in Production** ⚠️⚠️
**ไฟล์:** [config/config.php](config/config.php#L14-18)

**ปัญหา:**
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);  // ❌ Should be 0 in production
```
- แสดง error messages ทั้งหมดให้เห็น (SQL errors, file path, etc.)
- ช่วยให้ hacker เรียนรู้โครงสร้างระบบ

**แก้ไข:**
```php
// Production
error_reporting(E_ALL);
ini_set('display_errors', 0);      // ✅
ini_set('log_errors', 1);           // ✅
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
```

---

## 🔐 SECURITY ISSUES

### 4. **Session Security**
**ไฟล์:** [config/config.php](config/config.php#L42), [auth/login.php](auth/login.php#L20-35)

**ปัญหา:**
- ✅ มี Session timeout setting (60 นาที) แต่ไม่ implement
- ⚠️ ไม่มี regenerate session ID หลัง login
- ⚠️ ไม่มี HTTPS requirement

**แก้ไข:**
```php
// login.php - Add after successful login
session_regenerate_id(true);  // ✅ Prevent session fixation

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > (SESSION_TIMEOUT * 60)) {
    session_destroy();
    redirect('auth/login.php?timeout=1');
}
$_SESSION['last_activity'] = time();
```

---

### 5. **CSRF Protection Inconsistent**

**ไฟล์ที่มี CSRF:**
- ✅ [admin/users-management.php](admin/users-management.php#L7-11) - Proper CSRF token
- ✅ [modules/tickets.php](modules/tickets.php#L10-19) - Proper CSRF token
- ❌ [modules/assets.php](modules/assets.php#L14-20) - No CSRF token
- ❌ [admin/room-booking.php](admin/room-booking.php#L14) - No CSRF token
- ❌ [modules/users.php](modules/users.php#L1-25) - No CSRF token

**แนวทาง:** ต้อง implement CSRF token ให้สม่ำเสมอในทุก POST form

---

### 6. **Input Validation Missing/Incomplete**
**ไฟล์:**
- [admin/room-booking.php](admin/room-booking.php#L17-22) - ไม่มี validate input
- [modules/assets.php](modules/assets.php#L15-80) - ใช้ sanitize() แต่ไม่มี type validation

**ตัวอย่าง:**
```php
// room-booking.php - No validation
$room_id = $_POST['room_id'];        // ❌ Not validated
$num_attendees = $_POST['num_attendees'];  // ❌ Not int validated
```

---

### 7. **Password Hashing Good** ✅
- ✅ [auth/login.php](auth/login.php#L15) ใช้ `password_verify()`
- ✅ [admin/users-management.php](admin/users-management.php#L33) ใช้ `password_hash(PASSWORD_DEFAULT)`

---

## 📋 CODE QUALITY ISSUES

### 8. **Inconsistent Prepared Statements Usage**

**ไฟล์ดี ✅:**
- [auth/login.php](auth/login.php#L15-25) - Properly using prepared statements
- [admin/users-management.php](admin/users-management.php#L34-45) - Consistent use
- [modules/tickets.php](modules/tickets.php#L47-60) - Good practices
- [api/getnotifications.php](api/getnotifications.php#L12-35) - Proper implementation

**ไฟล์ที่ต้องแก้ ❌:**
- [modules/assets.php](modules/assets.php) - Mixed string concat & prepared
- [modules/assetsdetail.php](modules/assetsdetail.php) - Heavy SQL concatenation
- [modules/dashboard.php](modules/dashboard.php#L15-70) - String concat in queries

---

### 9. **Config File Issues**

**[config/config.php](config/config.php):**
- ✅ Good: Has timezone setting
- ✅ Good: Has constants for system names
- ⚠️ Warning: BASE_URL hardcoded to IP (192.168.2.99)
- ⚠️ Warning: UPLOAD_PATH could be outside web root for security
- ❌ Missing: Environment detection (dev/prod)

**[config/database.php](config/database.php):**
- ✅ Good: Using MySQLi with prepared statements
- ✅ Good: Error handling with try-catch
- ✅ Good: Singleton pattern with getDB()
- ⚠️ Warning: Default MySQL user is 'root' with empty password

---

### 10. **Database Operations Concerns**

**ไฟล์:** [database.php](database.php) - ดูเหมือนมี issues

**ปัญหา:**
- ใช้ SQLITE3 binding (`SQLITE3_TEXT`) ในไฟล์ที่ config ใช้ MySQL
- อาจมี conflict ระหว่างไฟล์นี้กับ config/database.php
- Recommend: ลบไฟล์นี้หรือ merge กับ config/database.php

---

## ✅ GOOD PRACTICES FOUND

### 11. **Functions & Helpers** ✅
**ไฟล์:** [includes/functions.php](includes/functions.php)

**ดี:**
- ✅ Modular helper functions (verifyLogin, getUserById, logActivity)
- ✅ Function exists check: `if (!function_exists('verifyLogin'))`
- ✅ Using prepared statements throughout
- ✅ Activity logging implemented

---

### 12. **Notification System** ✅
**ไฟล์:** 
- [api/getnotifications.php](api/getnotifications.php) - Well structured
- [modules/Notificationhelper.php](modules/Notificationhelper.php) - Good logic

**ดี:**
- ✅ Proper prepared statements
- ✅ JSON API endpoint
- ✅ Unread count tracking
- ✅ Time formatting for UI

---

### 13. **Authentication** ✅
**ไฟล์:** [auth/login.php](auth/login.php)

**ดี:**
- ✅ Using password_verify for security
- ✅ Session variables set properly
- ✅ Login activity logging
- ✅ Responsive UI design

---

### 14. **Code Organization** ✅

**โครงสร้างดี:**
```
Romar/
├── config/          ✅ Config centralized
├── auth/            ✅ Auth separated
├── includes/        ✅ Helpers/functions
├── modules/         ✅ Features separated
├── admin/           ✅ Admin panel
├── api/             ✅ API endpoints
├── uploads/         ✅ File handling
└── logs/            ✅ Error logging
```

---

## 📝 FILES STATUS SUMMARY

### 🟢 FILES - NO ISSUES (GOOD TO GO)
1. [auth/login.php](auth/login.php) - Well secured
2. [api/getnotifications.php](api/getnotifications.php) - Good API
3. [modules/Notificationhelper.php](modules/Notificationhelper.php) - Good helper
4. [admin/users-management.php](admin/users-management.php) - Good CRUD
5. [includes/functions.php](includes/functions.php#L1-100) - Good helpers
6. [config/config.php](config/config.php) - Structure OK (except error display)
7. [modules/tickets.php](modules/tickets.php) - CSRF implemented, good practices
8. [includes/header.php](includes/header.php) - UI good
9. [modules/Knowledgebase.php](modules/Knowledgebase.php) - Structure OK

---

### 🟡 FILES - NEEDS MODIFICATION (Medium Priority)
1. [config/database.php](config/database.php) - Minor cleanup needed
2. [admin/dashboard.php](admin/dashboard.php) - SQL concatenation
3. [modules/dashboard.php](modules/dashboard.php) - SQL concatenation issues
4. [admin/room-booking.php](admin/room-booking.php) - Missing validation & CSRF
5. [modules/users.php](modules/users.php) - Missing CSRF & validation
6. [modules/userProfile.php](modules/userProfile.php) - Need review for SQL injection
7. [modules/assetsreports.php](modules/assetsreports.php) - SQL concatenation
8. [modules/Ticketspatch.php](modules/Ticketspatch.php) - Unclear purpose

---

### 🔴 FILES - CRITICAL ISSUES (High Priority)
1. **[modules/assets.php](modules/assets.php)** ⚠️⚠️⚠️
   - SQL Injection: Line 79, 88, 162, 234, 237
   - No CSRF token
   - String concatenation in queries
   - **ACTION:** Replace all string concat with prepared statements

2. **[modules/assetsdetail.php](modules/assetsdetail.php)** ⚠️⚠️⚠️
   - SQL Injection: Line 28, 29, 57, 89, 103, 126, 140
   - Heavy reliance on string concatenation
   - **ACTION:** Rewrite with prepared statements

3. **[database.php](database.php)** ⚠️⚠️
   - SQLite3 binding in MySQL environment
   - Should be merged/consolidated with config/database.php
   - **ACTION:** Consolidate database connection logic

4. **[config/config.php](config/config.php)** ⚠️
   - Error display enabled for production
   - No environment detection
   - **ACTION:** Add environment mode setting

---

## 🛠️ RECOMMENDED ACTIONS (Priority Order)

### IMMEDIATE (This Week)
- [ ] **P1:** Fix SQL Injection in assets.php - Replace string concat with prepared statements
- [ ] **P1:** Fix SQL Injection in assetsdetail.php - Use prepared statements
- [ ] **P1:** Disable error display in config.php (set display_errors = 0)
- [ ] **P2:** Add CSRF tokens to assets.php, room-booking.php, users.php

### SHORT TERM (This Month)
- [ ] **P2:** Consolidate database connection logic (database.php + config/database.php)
- [ ] **P2:** Add input validation for all POST requests
- [ ] **P2:** Implement session regeneration after login
- [ ] **P2:** Add HTTPS requirement to config
- [ ] **P3:** Review and audit all SQL queries

### MEDIUM TERM (Next Month)
- [ ] **P3:** Add unit tests for critical functions
- [ ] **P3:** Implement API rate limiting
- [ ] **P3:** Add logging for security events
- [ ] **P3:** Code review for XSS vulnerabilities
- [ ] **P3:** Implement Content Security Policy (CSP)

### LONG TERM
- [ ] Consider using a framework (Laravel/Symfony) for better security
- [ ] Implement automated security scanning
- [ ] Regular security audits
- [ ] Staff training on secure coding

---

## 📊 VULNERABILITY SCORING

| Category | Score | Risk |
|----------|-------|------|
| SQL Injection | 8/10 | 🔴 HIGH |
| XSS Prevention | 6/10 | 🟡 MEDIUM |
| CSRF Protection | 5/10 | 🟡 MEDIUM |
| Authentication | 7/10 | 🟡 MEDIUM |
| Input Validation | 5/10 | 🟡 MEDIUM |
| Error Handling | 4/10 | 🔴 HIGH |
| Session Security | 6/10 | 🟡 MEDIUM |
| **Overall Score** | **6/10** | **🟡 NEEDS IMPROVEMENT** |

---

## 💡 BEST PRACTICES CHECKLIST

- [ ] ✅ Error logging enabled
- [ ] ❌ Error display disabled in production
- [ ] ✅ Prepared statements for most queries
- [ ] ❌ Prepared statements for ALL queries (audit needed)
- [ ] ✅ Password hashing (bcrypt/PASSWORD_DEFAULT)
- [ ] ⚠️ CSRF tokens (inconsistent)
- [ ] ❌ Input validation (incomplete)
- [ ] ❌ Output encoding (XSS prevention) - Not reviewed
- [ ] ⚠️ Session regeneration (not implemented)
- [ ] ❌ Rate limiting (not implemented)
- [ ] ✅ Activity logging (implemented)
- [ ] ❌ API authentication (not reviewed)

---

## 📚 CODE EXAMPLES

### Example 1: Fix SQL Injection in assets.php
```php
// ❌ BEFORE (Line 79)
$checkDuplicate = $db->query("SELECT asset_id FROM assets WHERE asset_tag = '$asset_tag'");

// ✅ AFTER
$checkDuplicate = $db->prepare("SELECT asset_id FROM assets WHERE asset_tag = ?");
$checkDuplicate->bind_param('s', $asset_tag);
$checkDuplicate->execute();
$result = $checkDuplicate->get_result();
```

### Example 2: Add CSRF Protection
```php
// Add at top of form-handling file
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// In HTML form
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

// Validate in POST handler
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}
```

### Example 3: Add Input Validation
```php
// Before: $num_attendees = $_POST['num_attendees'];
// After:
$num_attendees = filter_var($_POST['num_attendees'], FILTER_VALIDATE_INT) ?: null;
if ($num_attendees === null || $num_attendees < 1) {
    throw new Exception('Invalid number of attendees');
}
```

---

## 📞 CONCLUSION

### Summary
ระบบ Romar มีโครงสร้างที่ดี แต่มีปัญหาความปลอดภัยที่ต้องแก้ไขเร่ง ปัญหาหลักคือ:
1. SQL Injection vulnerabilities (critical) 
2. Error exposure in production
3. Inconsistent CSRF protection
4. Incomplete input validation

### Recommendation
**ไม่ควรนำไปใช้ production** จนกว่าแก้ไข P1 issues

### Timeline
- **P1 Issues:** 1-2 week
- **P2 Issues:** 2-3 weeks
- **P3 Issues:** 1-2 months

---

*Report Generated: 2 March 2026*
*Reviewed by: Code Review Agent*
