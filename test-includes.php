<?php
/**
 * Test Individual Includes
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test 1: validation.php\n";
try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/includes/validation.php';
    echo "✓ validation.php OK\n";
} catch (Throwable $e) {
    echo "✗ validation.php error: " . $e->getMessage() . "\n";
}

echo "\nTest 2: backup_helpers.php\n";
try {
    require_once __DIR__ . '/includes/backup_helpers.php';
    echo "✓ backup_helpers.php OK\n";
} catch (Throwable $e) {
    echo "✗ backup_helpers.php error: " . $e->getMessage() . "\n";
}

echo "\nTest 3: safe_access.php\n";
try {
    require_once __DIR__ . '/includes/safe_access.php';
    echo "✓ safe_access.php OK\n";
} catch (Throwable $e) {
    echo "✗ safe_access.php error: " . $e->getMessage() . "\n";
}

echo "\nTest 4: logger.php\n";
try {
    require_once __DIR__ . '/includes/logger.php';
    echo "✓ logger.php OK\n";
} catch (Throwable $e) {
    echo "✗ logger.php error: " . $e->getMessage() . "\n";
}

echo "\nTest 5: api_security.php\n";
try {
    require_once __DIR__ . '/includes/api_security.php';
    echo "✓ api_security.php OK\n";
} catch (Throwable $e) {
    echo "✗ api_security.php error: " . $e->getMessage() . "\n";
}

echo "\nTest 6: performance.php\n";
try {
    require_once __DIR__ . '/includes/performance.php';
    echo "✓ performance.php OK\n";
} catch (Throwable $e) {
    echo "✗ performance.php error: " . $e->getMessage() . "\n";
}

?>
