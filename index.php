<?php
/**
 * Romar System - Main Entry Point
 * Application routing and initialization
 */

// Prevent output before headers
ob_start();

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Type: text/html; charset=UTF-8");

// Load configuration and database
try {
    // Load config
    if (file_exists(__DIR__ . '/config/config.php')) {
        require_once __DIR__ . '/config/config.php';
    } else {
        throw new Exception("Config file not found");
    }
    
    // Load database first (contains error handlers)
    if (file_exists(__DIR__ . '/config/database.php')) {
        require_once __DIR__ . '/config/database.php';
        
        // Register global error handlers
        if (function_exists('register_global_error_handlers')) {
            register_global_error_handlers();
        }
    } else {
        throw new Exception("Database config not found");
    }
    
} catch (Throwable $e) {
    error_log("CRITICAL: index.php initialization error: " . $e->getMessage());
    ob_end_clean();
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <title>System Initialization Error</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 40px auto; 
                max-width: 600px; 
                line-height: 1.6;
                color: #333;
            }
            .error { 
                background: #ffebee; 
                padding: 20px; 
                border-left: 5px solid #c62828; 
                margin: 20px 0;
            }
            code { 
                background: #f5f5f5; 
                padding: 2px 6px; 
                border-radius: 3px;
                font-family: monospace;
            }
        </style>
    </head>
    <body>
        <h1>System Initialization Error</h1>
        <div class="error">
            <p>Unable to initialize system components.</p>
            <p>Please contact administrator immediately.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Determine routing
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? 'user';

// Set redirect target
if ($isLoggedIn) {
    $target = ($userRole === 'admin') ? 'admin/dashboard.php' : 'modules/dashboard.php';
} else {
    $target = 'auth/login.php';
}

// Build redirect URL
$redirectUrl = $target;
if (defined('BASE_URL') && BASE_URL !== '') {
    $redirectUrl = BASE_URL . ltrim($target, '/');
}

// Prevent redirect loops
$redirected = isset($_GET['redirected']) ? (int)$_GET['redirected'] : 0;
if ($redirected === 0) {
    ob_end_clean();
    header("Location: " . $redirectUrl . (strpos($redirectUrl, '?') === false ? '?' : '&') . "redirected=1");
    exit;
}

// Fallback loading page (after one redirect attempt)
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Romar - Loading</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex; 
            justify-content: center; 
            align-items: center;
            height: 100vh; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
        }
        h1 { 
            color: #333; 
            margin-bottom: 20px;
            font-size: 28px;
        }
        p { 
            color: #666; 
            margin: 15px 0; 
            font-size: 14px;
        }
        .loading { 
            margin: 30px 0; 
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { 
            0% { transform: rotate(0deg); } 
            100% { transform: rotate(360deg); } 
        }
        a { 
            color: #667eea; 
            text-decoration: none;
            font-weight: 500;
        }
        a:hover { 
            text-decoration: underline; 
        }
        .note {
            color: #999;
            font-size: 12px;
            margin-top: 30px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Romar System</h1>
        <p>Loading application...</p>
        <div class="loading">
            <div class="spinner"></div>
        </div>
        <p>
            <small>If this page persists,</small><br>
            <a href="<?php echo htmlspecialchars($target); ?>">click here to continue</a>
        </p>
        <div class="note">
            <p>For issues, please contact the system administrator.</p>
        </div>
    </div>
    
    <script>
        // Auto-redirect after 3 seconds
        setTimeout(function() {
            window.location.href = <?php echo json_encode($target); ?>;
        }, 3000);
    </script>
</body>
</html>

