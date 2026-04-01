<?php
/**
 * Romar Diagnostics Page
 * Use this to debug system issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Romar System Diagnostics</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto;
        }
        h1 { 
            color: #4ec9b0;
            margin-bottom: 30px;
            font-size: 24px;
        }
        h2 {
            color: #569cd6;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 16px;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
        }
        .section {
            background: #252526;
            border-left: 3px solid #569cd6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success {
            border-left-color: #4ec9b0;
            color: #4ec9b0;
        }
        .error {
            border-left-color: #f48771;
            color: #f48771;
        }
        .warning {
            border-left-color: #dcdcaa;
            color: #dcdcaa;
        }
        .info {
            border-left-color: #9cdcfe;
            color: #9cdcfe;
        }
        code {
            background: #1e1e1e;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            font-size: 12px;
        }
        .test-item {
            margin: 10px 0;
            padding: 8px;
            background: #1e1e1e;
            border-radius: 3px;
        }
        .pass::before { content: "✓ "; color: #4ec9b0; font-weight: bold; }
        .fail::before { content: "✗ "; color: #f48771; font-weight: bold; }
        .warn::before { content: "⚠ "; color: #dcdcaa; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Romar System Diagnostics</h1>
        
        <!-- PHP Version -->
        <h2>1. PHP Environment</h2>
        <div class="section">
            <div class="test-item pass">
                PHP Version: <code><?php echo phpversion(); ?></code>
            </div>
            <?php if (extension_loaded('mysqli')): ?>
                <div class="test-item pass">
                    MySQLi Extension: Loaded
                </div>
            <?php else: ?>
                <div class="test-item fail">
                    MySQLi Extension: NOT Loaded (CRITICAL)
                </div>
            <?php endif; ?>
            <?php if (extension_loaded('json')): ?>
                <div class="test-item pass">
                    JSON Extension: Loaded
                </div>
            <?php else: ?>
                <div class="test-item fail">
                    JSON Extension: NOT Loaded
                </div>
            <?php endif; ?>
        </div>

        <!-- Configuration Files -->
        <h2>2. Configuration Files</h2>
        <div class="section">
            <?php
            $configFiles = [
                'config/config.php' => 'Main Config',
                'config/database.php' => 'Database Config',
                'includes/functions.php' => 'Functions Library',
            ];
            
            foreach ($configFiles as $file => $label) {
                $path = __DIR__ . '/' . $file;
                $exists = file_exists($path);
                $readable = $exists && is_readable($path);
                ?>
                <div class="test-item <?php echo ($readable ? 'pass' : 'fail'); ?>">
                    <?php echo $label; ?>: 
                    <code><?php echo $file; ?></code>
                    <?php if ($readable): ?>
                        - OK
                    <?php elseif ($exists): ?>
                        - EXISTS but NOT READABLE
                    <?php else: ?>
                        - NOT FOUND
                    <?php endif; ?>
                </div>
            <?php } ?>
        </div>

        <!-- Load Configuration -->
        <h2>3. Config Loading</h2>
        <div class="section">
            <?php
            try {
                require_once __DIR__ . '/config/config.php';
                echo '<div class="test-item pass">config/config.php loaded successfully</div>';
                
                if (defined('SITE_NAME')) {
                    echo '<div class="test-item pass">SITE_NAME defined: <code>' . htmlspecialchars(SITE_NAME) . '</code></div>';
                }
                if (defined('BASE_URL')) {
                    echo '<div class="test-item pass">BASE_URL defined: <code>' . htmlspecialchars(BASE_URL) . '</code></div>';
                }
            } catch (Throwable $e) {
                echo '<div class="test-item fail">config/config.php error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <!-- Database Connection -->
        <h2>4. Database Configuration</h2>
        <div class="section">
            <?php
            try {
                require_once __DIR__ . '/config/database.php';
                echo '<div class="test-item pass">config/database.php loaded</div>';
                
                // Check database constants
                if (defined('DB_HOST')) {
                    echo '<div class="test-item info">DB_HOST: <code>' . htmlspecialchars(DB_HOST) . '</code></div>';
                }
                if (defined('DB_NAME')) {
                    echo '<div class="test-item info">DB_NAME: <code>' . htmlspecialchars(DB_NAME) . '</code></div>';
                }
                if (defined('DB_USER')) {
                    echo '<div class="test-item info">DB_USER: <code>' . htmlspecialchars(DB_USER) . '</code></div>';
                }
                
                // Try to connect
                echo '<h3 style="color: #9cdcfe; margin-top: 15px; margin-bottom: 10px;">Connection Test:</h3>';
                $testConn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                
                if ($testConn->connect_error) {
                    echo '<div class="test-item fail">Connection Failed: ' . htmlspecialchars($testConn->connect_error) . '</div>';
                } else {
                    echo '<div class="test-item pass">Database: Connected successfully</div>';
                    
                    // Check tables
                    $tables = [];
                    $result = $testConn->query("SHOW TABLES");
                    if ($result) {
                        while ($row = $result->fetch_row()) {
                            $tables[] = $row[0];
                        }
                        echo '<div class="test-item pass">Tables found: ' . count($tables) . '</div>';
                        if (count($tables) > 0) {
                            echo '<div class="test-item info">Tables: <code>' . implode(', ', $tables) . '</code></div>';
                        }
                    }
                    
                    $testConn->close();
                }
            } catch (Throwable $e) {
                echo '<div class="test-item fail">Database config error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <!-- Functions Library -->
        <h2>5. Functions Library</h2>
        <div class="section">
            <?php
            $functions = [
                'isLoggedIn' => 'Auth',
                'sanitize' => 'Security',
                'csrf_token' => 'CSRF Protection',
                'redirect' => 'Routing',
                'validate_batch' => 'Validation (NEW)',
                'get_safe_post' => 'Safe Access (NEW)',
                'log_error' => 'Logging (NEW)',
                'api_setup_cors' => 'API Security (NEW)',
            ];
            
            foreach ($functions as $func => $category) {
                $exists = function_exists($func);
                $class = $exists ? 'pass' : 'fail';
                $status = $exists ? 'Loaded' : 'NOT FOUND';
                echo "<div class='test-item $class'>$func() [$category]: $status</div>";
            }
            ?>
        </div>

        <!-- Error Log -->
        <h2>6. Recent Errors (Last 20 lines)</h2>
        <div class="section">
            <?php
            $errorLog = __DIR__ . '/logs/php_errors.log';
            if (file_exists($errorLog)) {
                $lines = file($errorLog);
                $recent = array_slice($lines, -20);
                echo '<pre style="background: #1e1e1e; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px;">';
                echo htmlspecialchars(implode('', $recent));
                echo '</pre>';
            } else {
                echo '<div class="test-item warn">No error log found. First error will create it.</div>';
            }
            ?>
        </div>

        <!-- System Paths -->
        <h2>7. System Paths</h2>
        <div class="section">
            <div class="test-item info">
                Document Root: <code><?php echo htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A'); ?></code>
            </div>
            <div class="test-item info">
                Script Path: <code><?php echo htmlspecialchars(__DIR__); ?></code>
            </div>
            <div class="test-item info">
                Request URI: <code><?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A'); ?></code>
            </div>
        </div>

        <!-- Action Buttons -->
        <h2>8. Quick Actions</h2>
        <div class="section">
            <div class="test-item info">
                <a href="index.php" style="color: #9cdcfe; text-decoration: none;">→ Go to Homepage</a>
            </div>
            <div class="test-item info">
                <a href="auth/login.php" style="color: #9cdcfe; text-decoration: none;">→ Go to Login</a>
            </div>
            <?php if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])): ?>
                <div class="test-item info">
                    <a href="modules/dashboard.php" style="color: #9cdcfe; text-decoration: none;">→ Go to Dashboard</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
