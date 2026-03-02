# 🔧 ACTION PLAN & CODE FIX EXAMPLES

## Critical Fixes Detailed Guide

---

## **FIX #1: SQL Injection in modules/assets.php**

### Locations to Fix:
- Line 79: SELECT query
- Line 88: INSERT query  
- Line 162: UPDATE query
- Line 234-237: COUNT queries

### **BEFORE (Vulnerable):**

```php
// Line 79 - SELECT
$checkDuplicate = $db->query("SELECT asset_id FROM assets WHERE asset_tag = '$asset_tag'");
if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
    $_SESSION['flash_message'] = "Asset Tag \"$asset_tag\" มีในระบบแล้ว";
    exit;
}

// Line 88 - INSERT
$dbResult = $db->query("INSERT INTO assets (
    asset_name, asset_tag, ... 
) VALUES (
    '$asset_name','$asset_tag',...
)");
```

### **AFTER (Secure):**

```php
// Line 79 - SELECT
$stmt = $db->prepare("SELECT asset_id FROM assets WHERE asset_tag = ?");
$stmt->bind_param('s', $asset_tag);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $_SESSION['flash_message'] = "Asset Tag \"" . htmlspecialchars($asset_tag) . "\" มีในระบบแล้ว";
    $_SESSION['flash_type'] = 'error';
    exit;
}

// Line 88 - INSERT
$stmt = $db->prepare("INSERT INTO assets (
    asset_name, asset_tag, asset_type, brand, model, serial_number, inventory_number,
    location, department, asset_group, assigned_to, tech_in_charge, alternate_user,
    purchase_date, warranty_expiry, purchase_price, salvage_value, useful_life_years, supplier,
    last_inventory_date, condition_status, status, notes,
    os_name, os_version, os_architecture, os_service_pack, os_product_key,
    ip_address, mac_address, network_domain, gateway, dns_server,
    cpu, cpu_cores, ram_gb, storage, gpu, monitor, created_at
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");

$stmt->bind_param('ssssssssssiissssssddsiiisssssssiiisss',
    $asset_name, $asset_tag, $asset_type, $brand, $model, $serial_number, $inventory_number,
    $location, $department, $asset_group, 
    $assigned_to, $tech_in_charge, $alternate_user,
    $purchase_date, $warranty_expiry, $purchase_price, $salvage_value, $useful_life_years, $supplier,
    $last_inventory_date, $condition_status, $status, $notes,
    $os_name, $os_version, $os_architecture, $os_service_pack, $os_product_key,
    $ip_address, $mac_address, $network_domain, $gateway, $dns_server,
    $cpu, $cpu_cores, $ram_gb, $storage, $gpu, $monitor
);

if ($stmt->execute()) {
    $_SESSION['flash_message'] = 'เพิ่มสินทรัพย์สำเร็จ!';
    $_SESSION['flash_type'] = 'success';
    logActivity($_SESSION['user_id'], 'เพิ่มสินทรัพย์', 'Assets', "เพิ่ม: $asset_name ($asset_tag)");
} else {
    $_SESSION['flash_message'] = 'เกิดข้อผิดพลาด: ' . htmlspecialchars($stmt->error);
    $_SESSION['flash_type'] = 'error';
}
```

---

## **FIX #2: SQL Injection in modules/assetsdetail.php**

### Problem Area 1: Line 57
```php
// ❌ BEFORE
if ($_POST['action'] == 'set_maintenance') {
    $assetId = (int)$_POST['assetId'];
    $db->query("UPDATE assets SET status='maintenance' WHERE asset_id=$assetId");
}

// ✅ AFTER
if ($_POST['action'] == 'set_maintenance') {
    $assetId = (int)$_POST['assetId'];
    $stmt = $db->prepare("UPDATE assets SET status='maintenance' WHERE asset_id=?");
    $stmt->bind_param('i', $assetId);
    $stmt->execute();
}
```

### Problem Area 2: Line 126 (Major INSERT/UPDATE)
```php
// ❌ BEFORE
$db->query("INSERT INTO asset_transfers (asset_id,from_user_id,to_user_id,from_location,to_location,from_dept,to_dept,transfer_date,reason,transferred_by) 
VALUES ($assetId,$fromUserSQL,$toUserSQL,'$fromLoc','$toLoc','$fromDept','$toDept','$transDate','$reason',$byUser)");

// ✅ AFTER
$stmt = $db->prepare("INSERT INTO asset_transfers (
    asset_id, from_user_id, to_user_id, from_location, to_location, 
    from_dept, to_dept, transfer_date, reason, transferred_by
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param('iiisssssi', 
    $assetId, $fromUserId, $toUserId, $fromLoc, $toLoc, 
    $fromDept, $toDept, $transDate, $reason, $byUser
);
$stmt->execute();
```

---

## **FIX #3: Fix config/config.php (Error Display)**

### **Change from (Line 14-18):**
```php
error_reporting(E_ALL);
ini_set('display_errors', 1); // ❌ WRONG
ini_set('log_errors', 1);
```

### **Change to:**
```php
// ========================================
// Environment Detection
// ========================================
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // Set via .env or server

if (APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);      // ✅ Hide errors from users
    ini_set('log_errors', 1);          // ✅ Log to file
} else {
    // Development
    error_reporting(E_ALL);
    ini_set('display_errors', 1);      // OK for development
    ini_set('log_errors', 1);
}

ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
```

---

## **FIX #4: Fix database.php (SQLite/MySQL Mismatch)**

### **Option A: Delete database.php**
If `database.php` at root is not needed, delete it and use `config/database.php`

### **Option B: Consolidate database.php**
```php
<?php
// Root level database.php - Redirect to proper connection
require_once __DIR__ . '/config/database.php';

$db = getDB(); // Use centralized connection
?>
```

---

## **FIX #5: Add CSRF Protection to modules/assets.php**

### **At the top of file (after session_start):**
```php
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// ✅ Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = md5(uniqid('', true));
    }
}

function validate_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle Create Asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // ✅ CSRF Check
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_type']    = 'error';
        $_SESSION['flash_message'] = 'Invalid CSRF token';
        header('Location: assets.php');
        exit;
    }
    
    // ... rest of the code
}
```

### **In the HTML form add:**
```php
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <input type="hidden" name="action" value="create">
    
    <!-- form fields -->
    
    <button type="submit">บันทึก</button>
</form>
```

---

## **FIX #6: Add Input Validation to room-booking.php**

### **Before POST processing (Line 14-20):**
```php
// ❌ BEFORE - No validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_room') {
    $room_id = $_POST['room_id'];
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $num_attendees = $_POST['num_attendees'];
    $purpose = trim($_POST['purpose']);
    $notes = trim($_POST['notes'] ?? '');
```

### **✅ AFTER - With validation:**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_room') {
    // Validate CSRF
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid CSRF token';
    } else {
        // Validate room_id
        $room_id = filter_var($_POST['room_id'], FILTER_VALIDATE_INT);
        if (!$room_id || $room_id < 1) {
            $error_message = 'Invalid room selection';
        }
        // Validate dates
        else if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['booking_date'])) {
            $error_message = 'Invalid booking date format';
        }
        // Validate times
        else if (!preg_match('/^\d{2}:\d{2}$/', $_POST['start_time']) || 
                 !preg_match('/^\d{2}:\d{2}$/', $_POST['end_time'])) {
            $error_message = 'Invalid time format';
        }
        // Validate attendees
        else if (($num_attendees = filter_var($_POST['num_attendees'], FILTER_VALIDATE_INT)) === false || 
                 $num_attendees < 1 || $num_attendees > 100) {
            $error_message = 'Invalid number of attendees (1-100)';
        }
        // Validate purpose
        else if (empty(trim($_POST['purpose'])) || strlen(trim($_POST['purpose'])) > 255) {
            $error_message = 'Purpose is required (max 255 characters)';
        }
        else {
            $booking_date = $_POST['booking_date'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $purpose = htmlspecialchars(trim($_POST['purpose']));
            $notes = htmlspecialchars(trim($_POST['notes'] ?? ''));
            
            // ... proceed with database query using prepared statements
        }
    }
}
```

---

## **FIX #7: Add Session Security (Login Enhancement)**

### **In auth/login.php after successful login (around Line 32):**

```php
if ($user) {
    // ✅ Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    
    // Log activity
    logActivity($user['user_id'], 'เข้าสู่ระบบ', 'Authentication', 'ผู้ใช้เข้าสู่ระบบ');
    
    // Redirect to dashboard
    redirect('admin/dashboard.php');
}
```

### **Add session timeout check to includes/functions.php:**

```php
if (!function_exists('checkSessionTimeout')) {
    function checkSessionTimeout() {
        $timeout = SESSION_TIMEOUT * 60; // Convert minutes to seconds
        
        if (isset($_SESSION['last_activity'])) {
            if ((time() - $_SESSION['last_activity']) > $timeout) {
                session_destroy();
                redirect('auth/login.php?timeout=1');
            }
        }
        
        $_SESSION['last_activity'] = time();
    }
}
```

Then call this function at the top of protected pages:
```php
checkSessionTimeout();
```

---

## **FIX #8: Consolidate Database Connection**

### **Create file: config/database.php (standardized)**

```php
<?php
/**
 * Database Configuration - MySQL Version
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'romar_user');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'romar_dormitory');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get MySQL Database Connection
 */
function getDB() {
    static $connection = null;
    
    if ($connection === null) {
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($connection->connect_error) {
            error_log("Database Connection Error: " . $connection->connect_error);
            die("Database connection failed. Please check your configuration.");
        }
        
        $connection->set_charset(DB_CHARSET);
        $connection->query("SET time_zone = '+07:00'");
    }
    
    return $connection;
}
?>
```

### **Delete or consolidate root-level database.php**

---

## **Testing Checklist After Fixes**

- [ ] All forms still work (assets, users, bookings)
- [ ] No SQL errors in error_log
- [ ] CSRF tokens are validated
- [ ] Input validation works (try invalid data)
- [ ] Cannot login with wrong credentials
- [ ] Session timeout works
- [ ] No errors shown to users
- [ ] Activity logging still works
- [ ] Database queries complete in <1 second

---

## **Estimated Effort**

| Fix | Time | Difficulty |
|-----|------|----------|
| #1 assets.php | 2-3 hours | Medium |
| #2 assetsdetail.php | 2-3 hours | Medium |
| #3 config.php | 15 min | Easy |
| #4 database.php | 30 min | Easy |
| #5 CSRF in assets.php | 1 hour | Easy |
| #6 Validation room-booking.php | 1-2 hours | Medium |
| #7 Session security | 1 hour | Easy |
| #8 Database consolidation | 1 hour | Easy |
| **TOTAL** | **9-14 hours** | **~2 days** |

---

## **Priority Order**

1. **Day 1 Morning:** Fix #3 (config.php) + Fix #4 (database.php)
2. **Day 1 Afternoon:** Fix #1 (assets.php)
3. **Day 2 Morning:** Fix #2 (assetsdetail.php)
4. **Day 2 Afternoon:** Fix #5, #6, #7, #8
5. **Day 3:** Testing & verification

---

*Use this guide to systematically fix all critical security issues.*
