<?php
// System Test File
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== ROMAR SYSTEM TEST ===\n\n";

// Test 1: Check config files
echo "Test 1: Config Files\n";
if (file_exists('config/config.php')) {
    echo "✓ config/config.php exists\n";
} else {
    echo "✗ config/config.php NOT found\n";
}

if (file_exists('config/database.php')) {
    echo "✓ config/database.php exists\n";
} else {
    echo "✗ config/database.php NOT found\n";
}

// Test 2: Load config
echo "\nTest 2: Load Config\n";
try {
    require_once 'config/config.php';
    echo "✓ config/config.php loaded\n";
    if (defined('SITE_NAME')) {
        echo "  - SITE_NAME: " . SITE_NAME . "\n";
    }
} catch (Exception $e) {
    echo "✗ config/config.php error: " . $e->getMessage() . "\n";
}

// Test 3: Load database
echo "\nTest 3: Load Database Config\n";
try {
    require_once 'config/database.php';
    echo "✓ config/database.php loaded\n";
} catch (Exception $e) {
    echo "✗ config/database.php error: " . $e->getMessage() . "\n";
}

// Test 4: Load functions
echo "\nTest 4: Load Functions\n";
try {
    require_once 'includes/functions.php';
    echo "✓ includes/functions.php loaded\n";
} catch (Exception $e) {
    echo "✗ includes/functions.php error: " . $e->getMessage() . "\n";
}

// Test 5: Check functions
echo "\nTest 5: Check Key Functions\n";
$functions = ['isLoggedIn', 'redirect', 'sanitize', 'csrf_token', 'verify_csrf'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✓ $func() exists\n";
    } else {
        echo "✗ $func() NOT found\n";
    }
}

// Test 6: Check new helper functions
echo "\nTest 6: Check New Helper Functions\n";
$helpers = [
    'validate_batch' => 'validation.php',
    'get_safe_post' => 'safe_access.php',
    'log_error' => 'logger.php',
    'api_setup_cors' => 'api_security.php',
    'paginate' => 'performance.php'
];

foreach ($helpers as $func => $file) {
    if (function_exists($func)) {
        echo "✓ $func() exists (from $file)\n";
    } else {
        echo "✗ $func() NOT found (should be in $file)\n";
    }
}

// Test 7: Database connection
echo "\nTest 7: Database Connection\n";
if (isset($db)) {
    echo "✓ Database object exists\n";
    if ($db->connect_error) {
        echo "✗ Connection error: " . $db->connect_error . "\n";
    } else {
        echo "✓ Database connected\n";
        
        // Check tables
        $tables = ['users', 'announcements', 'bookings', 'documents'];
        $result = $db->query("SHOW TABLES");
        $existing = [];
        while ($row = $result->fetch_row()) {
            $existing[] = $row[0];
        }
        
        echo "  Existing tables: " . implode(', ', $existing) . "\n";
    }
} else {
    echo "✗ Database object not set\n";
}

echo "\n=== END TEST ===\n";
?>
