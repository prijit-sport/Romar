<?php
/**
 * Minimal Test - Step by step loading
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Start Session...\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "✓ Session OK\n\n";

echo "Step 2: Load Config...\n";
try {
    require_once __DIR__ . '/config/config.php';
    echo "✓ Config OK\n\n";
} catch (Throwable $e) {
    echo "✗ Config Error: " . $e->getMessage() . "\n";
    die();
}

echo "Step 3: Load Database Config...\n";
try {
    require_once __DIR__ . '/config/database.php';
    echo "✓ Database Config OK\n\n";
} catch (Throwable $e) {
    echo "✗ Database Error: " . $e->getMessage() . "\n";
    die();
}

echo "Step 4: Check if getDB function exists...\n";
if (function_exists('getDB')) {
    echo "✓ getDB() exists\n\n";
} else {
    echo "✗ getDB() not found\n";
}

echo "Step 5: Try to get database connection...\n";
try {
    $db = getDB();
    echo "✓ Database connected\n\n";
} catch (Throwable $e) {
    echo "✗ Connection Error: " . $e->getMessage() . "\n";
    die();
}

echo "Step 6: Load Functions...\n";
try {
    require_once __DIR__ . '/includes/functions.php';
    echo "✓ Functions OK\n\n";
} catch (Throwable $e) {
    echo "✗ Functions Error: " . $e->getMessage() . "\n";
    die();
}

echo "Step 7: Check key functions...\n";
$functions = ['isLoggedIn', 'sanitize', 'csrf_token', 'validate_batch', 'log_error'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✓ $func()\n";
    } else {
        echo "✗ $func() NOT FOUND\n";
    }
}

echo "\n✓✓✓ All steps completed successfully! ✓✓✓\n";
?>
