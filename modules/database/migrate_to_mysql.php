ไฟล์ที่สร้างด้วยนะคับว่าใช้งานได้หรือ<?php
/**
 * Database Migration Script
 * Migrate from SQLite to MySQL
 * 
 * คำแนะนำ:
 * 1. สร้าง MySQL database ก่อน: CREATE DATABASE romar_dormitory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
 * 2. Import schema: mysql -u root -p romar_dormitory < database/schema_mysql.sql
 * 3. Run this script to migrate data
 */

require_once __DIR__ . '/../config/database.php';

echo "========================================\n";
echo "Romar Database Migration Tool\n";
echo "========================================\n\n";

// Check if running from command line or browser
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    echo "<pre style='font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px;'>";
}

function logMsg($msg, $type = 'info') {
    global $isCLI;
    $colors = [
        'info' => $isCLI ? "\033[36m" : '',
        'success' => $isCLI ? "\033[32m" : '',
        'error' => $isCLI ? "\033[31m" : '',
        'warning' => $isCLI ? "\033[33m" : '',
    ];
    $reset = $isCLI ? "\033[0m" : '';
    echo $colors[$type] . $msg . $reset . "\n";
}

// Check database connection
try {
    $db = getDB();
    logMsg("✓ Connected to MySQL database: " . DB_NAME, 'success');
} catch (Exception $e) {
    logMsg("✗ Cannot connect to MySQL: " . $e->getMessage(), 'error');
    exit(1);
}

// Check if tables exist
$requiredTables = [
    'users', 'meeting_rooms', 'bookings', 'conversations', 
    'announcements', 'documents', 'activity_logs', 'tickets',
    'ticket_comments', 'ticket_attachments', 'ticket_time_tracking',
    'ticket_timeline', 'ticket_relations', 'assets', 'sla_rules',
    'knowledge_base', 'kbcategories', 'system_settings', 'notifications'
];

logMsg("\n--- Checking tables ---", 'info');

$missingTables = [];
foreach ($requiredTables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        $missingTables[] = $table;
        logMsg("✗ Table '$table' not found", 'error');
    } else {
        logMsg("✓ Table '$table' exists", 'success');
    }
}

if (!empty($missingTables)) {
    logMsg("\n⚠ Please import schema_mysql.sql first!", 'warning');
    logMsg("Run: mysql -u root -p romar_dormitory < database/schema_mysql.sql", 'warning');
    exit(1);
}

// Check if admin user exists
$result = $db->query("SELECT COUNT(*) as cnt FROM users WHERE username = 'admin'");
$row = $result->fetch_assoc();

if ($row['cnt'] == 0) {
    logMsg("\n--- Creating default admin user ---", 'info');
    
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param('sssss', $username = 'admin', $adminPassword, $fullName = 'Administrator', $email = 'admin@romar.local', $role = 'admin');
    
    if ($stmt->execute()) {
        logMsg("✓ Admin user created (username: admin, password: admin123)", 'success');
    } else {
        logMsg("✗ Failed to create admin user: " . $stmt->error, 'error');
    }
} else {
    logMsg("\n✓ Admin user already exists", 'success');
}

// Check if meeting rooms exist
$result = $db->query("SELECT COUNT(*) as cnt FROM meeting_rooms");
$row = $result->fetch_assoc();

if ($row['cnt'] == 0) {
    logMsg("\n--- Inserting default meeting rooms ---", 'info');
    
    $rooms = [
        ['ห้องประชุมใหญ่', 30, 'ชั้น 2', 'Projector, Whiteboard, Wi-Fi, Air-Con'],
        ['ห้องประชุมเล็ก', 10, 'ชั้น 2', 'TV Screen, Whiteboard, Wi-Fi'],
        ['ห้องอบรม', 50, 'ชั้น 3', 'Projector, Sound System, Air-Con']
    ];
    
    $stmt = $db->prepare("INSERT INTO meeting_rooms (room_name, capacity, location, amenities, is_active) VALUES (?, ?, ?, ?, 1)");
    
    foreach ($rooms as $room) {
        $stmt->bind_param('siss', $room[0], $room[1], $room[2], $room[3]);
        $stmt->execute();
    }
    
    logMsg("✓ Default meeting rooms created", 'success');
}

// Check if SLA rules exist
$result = $db->query("SELECT COUNT(*) as cnt FROM sla_rules");
$row = $result->fetch_assoc();

if ($row['cnt'] == 0) {
    logMsg("\n--- Inserting default SLA rules ---", 'info');
    
    $slaRules = [
        ['Critical-Urgent', 'urgent', 'critical', 1, 2],
        ['Critical-High', 'urgent', 'high', 1, 4],
        ['High-High', 'high', 'high', 2, 8],
        ['Normal-Medium', 'normal', 'medium', 8, 24],
        ['Low-Low', 'low', 'low', 24, 72]
    ];
    
    $stmt = $db->prepare("INSERT INTO sla_rules (name, priority, impact, response_time_hours, resolution_time_hours) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($slaRules as $rule) {
        $stmt->bind_param('sssii', $rule[0], $rule[1], $rule[2], $rule[3], $rule[4]);
        $stmt->execute();
    }
    
    logMsg("✓ Default SLA rules created", 'success');
}

// Check if sample assets exist
$result = $db->query("SELECT COUNT(*) as cnt FROM assets");
$row = $result->fetch_assoc();

if ($row['cnt'] == 0) {
    logMsg("\n--- Inserting sample assets ---", 'info');
    
    $assets = [
        ['PC-001', 'Desktop Computer - Accounting', 'computer', 'Dell', 'OptiPlex 7090', 'active', 'Building A - 2F'],
        ['LP-001', 'Laptop - Manager', 'laptop', 'Lenovo', 'ThinkPad X1', 'active', 'Mobile'],
        ['PR-001', 'Laser Printer', 'printer', 'HP', 'LaserJet Pro', 'active', 'Building A - 2F']
    ];
    
    $stmt = $db->prepare("INSERT INTO assets (asset_tag, asset_name, asset_type, brand, model, status, location) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($assets as $asset) {
        $stmt->bind_param('sssssss', $asset[0], $asset[1], $asset[2], $asset[3], $asset[4], $asset[5], $asset[6]);
        $stmt->execute();
    }
    
    logMsg("✓ Sample assets created", 'success');
}

logMsg("\n========================================", 'info');
logMsg("Migration completed successfully!", 'success');
logMsg("========================================", 'info');
logMsg("\nNext steps:", 'info');
logMsg("1. Update .env file with MySQL credentials", 'info');
logMsg("2. Run: php -S localhost:8000 to start server", 'info');
logMsg("3. Login with: admin / admin123", 'info');

if (!$isCLI) {
    echo "</pre>";
    echo "<h2 style='color: green;'>✓ Migration Complete!</h2>";
    echo "<p>Login with: <strong>admin</strong> / <strong>admin123</strong></p>";
    echo "<p><a href='../auth/login.php'>Go to Login</a></p>";
}
?>

