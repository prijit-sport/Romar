<?php
// โหลดไฟล์ตั้งค่าและฟังก์ชัน
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
apply_security_headers(['allow_inline' => false]);
csrf_token();
$cspNonce = htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8');

// ถ้า Login อยู่แล้ว redirect ไป dashboard
if (isLoggedIn()) {
    redirect('admin/dashboard.php');
}

$error = '';
$timeout = isset($_GET['timeout']) ? true : false;

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token';
        security_audit_log('csrf_invalid', ['module' => 'auth_login']);
    } else {
        $limit = rate_limit_check('auth_login', 8, 300);
        if (!$limit['allowed']) {
            $error = 'Too many attempts. Retry in ' . $limit['retry_after'] . ' seconds';
            security_audit_log('rate_limit_blocked', ['module' => 'auth_login', 'retry_after' => $limit['retry_after']]);
        } else {
            $username = sanitize($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
            } else {
                $user = verifyLogin($username, $password);
                
                if ($user) {
                    session_regenerate_id(true);

                    // Set session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();
                    
                    // Log activity
                    logActivity($user['user_id'], 'เข้าสู่ระบบ', 'Authentication', 'ผู้ใช้เข้าสู่ระบบ');
                    
                    // Redirect by role
                    redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'modules/dashboard.php');
                } else {
                    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                    security_audit_log('login_failed', ['username' => $username]);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style nonce="<?php echo $cspNonce; ?>">
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg,  #1e40af 40%,#3b82f6 60%, #60a5fa 100%);
            position: relative;
            overflow: hidden;
        }

        /* Animated background circles */
        body::before,
        body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            animation: float 20s infinite ease-in-out;
        }

        body::before {
            width: 400px;
            height: 400px;
            background: white;
            top: -100px;
            left: -100px;
        }

        body::after {
            width: 300px;
            height: 300px;
            background: white;
            bottom: -50px;
            right: -50px;
            animation-delay: 2s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-30px) scale(1.1); }
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgb(0, 0, 0);
            overflow: hidden;
            width: 420px;
            max-width: 90%;
            position: relative;
            z-index: 1;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg,  #1e3a8a 0%,  #1e40af 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .login-header h1 {
            font-size: 1.8em;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .login-header p {
            font-size: 0.95em;
            opacity: 0.9;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #000000;
            font-weight: 500;
            font-size: 0.95em;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 3px rgb(255, 255, 255);
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 1.3em;
            user-select: none;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg,  #1e40af 0%, #3b82f6 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Sarabun', sans-serif;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgb(246, 250, 35);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.95em;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger {
            background: #fee;
            border-left: 4px solid #e53e3e;
            color: #c53030;
        }

        .alert-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }

        .login-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            text-align: center;
            font-size: 0.9em;
            color: #000000;
        }

        .login-footer strong {
            color: #000000;
        }

        .demo-info {
            margin-top: 15px;
            padding: 15px;
            background: #4cb93de8;
            border-radius: 8px;
            font-size: 0.85em;
            line-height: 1.6;
        }

        .demo-info strong {
            display: block;
            margin-bottom: 8px;
            color: #f1ff33;
        }

        .demo-info code {
            background: white;
            padding: 2px 8px;
            border-radius: 4px;
            color: #d32f2f;
            font-family: monospace;
        }

        @media (max-width: 480px) {
            .login-container {
                width: 100%;
                border-radius: 0;
            }

            .login-header {
                padding: 30px 20px;
            }

            .login-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?php echo SITE_NAME; ?></h1>
            <p><?php echo SITE_NAME_EN; ?></p>
        </div>

        <div class="login-body">
            <?php if ($timeout): ?>
                <div class="alert alert-warning">
                    ⚠️ Session หมดอายุ กรุณาเข้าสู่ระบบใหม่
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    ❌ <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrf_input(); ?>
                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        placeholder="กรอกชื่อผู้ใช้"
                        required
                        autofocus
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">รหัสผ่าน</label>
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="กรอกรหัสผ่าน"
                            required
                        >
                        <span id="password-toggle" class="password-toggle">👁️</span>
                    </div>
                </div>

                <button type="submit" class="btn">เข้าสู่ระบบ</button>
            </form>
        </div>

        <div class="login-footer">
            <strong><?php echo SITE_NAME; ?></strong><br>
            Developed with ❤️ for Learning PHP & SQLite
        </div>
    </div>

    <script nonce="<?php echo $cspNonce; ?>">
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('password-toggle');
            if (toggle) {
                toggle.addEventListener('click', togglePassword);
            }
        });
    </script>
</body>
</html>

