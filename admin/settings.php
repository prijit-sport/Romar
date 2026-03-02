<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$message = '';
$messageType = '';

// Handle Update Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fullName = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    
    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
    $stmt->bind_param('ssi', $fullName, $email, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $message = 'อัปเดตโปรไฟล์สำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'อัปเดตโปรไฟล์', 'Settings', 'อัปเดตข้อมูลส่วนตัว');
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Handle Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Get current user
    $stmt = $db->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!password_verify($currentPassword, $user['password'])) {
        $message = 'รหัสผ่านปัจจุบันไม่ถูกต้อง!';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'รหัสผ่านใหม่ไม่ตรงกัน!';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 6) {
        $message = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร!';
        $messageType = 'error';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $updateStmt->bind_param('si', $hashedPassword, $_SESSION['user_id']);
        
        if ($updateStmt->execute()) {
            $message = 'เปลี่ยนรหัสผ่านสำเร็จ!';
            $messageType = 'success';
            logActivity($_SESSION['user_id'], 'เปลี่ยนรหัสผ่าน', 'Settings', 'เปลี่ยนรหัสผ่านบัญชี');
        } else {
            $message = 'เกิดข้อผิดพลาด: ' . $updateStmt->error;
            $messageType = 'error';
        }
    }
}

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่า - Romar</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #065f159c;
            color: #000000;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #10ce30 0%, #000000 100%);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgb(0, 0, 0);
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 25px 20px;
            border-bottom: 1px solid rgb(255, 255, 255);
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }

        .brand-icon { font-size: 2em; }
        .brand-name { font-size: 1.5em; font-weight: 700; }
        .brand-subtitle {
            color: #000000;
            font-size: 1em;
            opacity: 0.8;
        }

        .sidebar-nav ul { list-style: none; padding: 0; margin: 0; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: rgb(255, 255, 255);
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-nav li.active a { background: rgba(255, 243, 243, 0.15); color: white; border-left: 4px solid #000000; }
        .menu-section {
            padding: 20px 20px 10px;
            color: rgb(255, 255, 255);
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        /* Main Content */
        .main-content { 
            flex: 1; 
            margin-left: 260px; 
            padding: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .content-wrapper {
            width: 100%;
            max-width: 1000px;
        }

        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            margin-bottom: 30px;
            text-align: center;
        }

        .page-title h1 { 
            font-size: 1.8em; 
            color: #000000; 
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* Settings Grid */
        .settings-grid {
            display: grid;
            gap: 25px;
            width: 100%;
        }

        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgb(0, 0, 0);
            overflow: hidden;
        }

        .card-header {
            padding: 25px 30px;
            border-bottom: 1px solid #000000;
            background: linear-gradient(135deg, #000000 0%, #10ce30 100%);
            color: white;
        }

        .card-title {
            font-size: 1.3em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 30px;
        }

        /* Profile Info */
        .profile-info {
            display: flex;
            align-items: center;
            gap: 25px;
            padding: 25px;
            background: linear-gradient(135deg, #000000 0%, #10ce30 100%);
            border-radius: 12px;
            color: white;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            font-weight: 700;
            border: 3px solid rgba(255,255,255,0.3);
        }

        .profile-details h2 {
            font-size: 1.5em;
            margin-bottom: 5px;
        }

        .profile-meta {
            opacity: 0.9;
            font-size: 0.95em;
        }

        /* Form */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #000000;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        .form-control:disabled {
            background: #f8fafc;
            color: #94a3b8;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #000000 0%, #10ce30 100%);
            color: white;
            box-shadow: 0 4px 6px rgb(0, 0, 0);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgb(0, 0, 0);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        /* Alert */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show { display: block; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

        /* Info Box */
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #000000;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-box-title {
            font-weight: 600;
            color: #000000;
            margin-bottom: 5px;
        }

        .info-box-text {
            color: #ff0000;
            font-size: 0.95em;
        }

        /* Stats */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .stat-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #000000;
        }

        .stat-label {
            font-size: 0.85em;
            color: #000000;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.2em;
            font-weight: 600;
            color: #000000;
        }

        @media (max-width: 768px) {
            .sidebar { margin-left: -260px; }
            .main-content { 
                margin-left: 0; 
                padding: 15px; 
            }
            .content-wrapper {
                padding: 0;
            }
            .profile-info { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon">🏢</div>
                <div>
                    <div class="brand-name">Romar</div>
                    <div class="brand-subtitle">Dormitory</div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <li class="menu-section">การจัดการ</li>
                    <li><a href="users-management.php">👥 จัดการผู้ใช้</a></li>
                    <li><a href="meeting-rooms.php">🏢 จัดการห้องประชุม</a></li>
                    <li><a href="documents.php">📄 จัดการเอกสาร</a></li>
                    <?php endif; ?>
                    <li class="menu-section">ฟีเจอร์</li>
                    <li><a href="room-booking.php">📅 จองห้องประชุม</a></li>
                    <li><a href="announcements.php">📢 ข่าวสาร</a></li>
                    <li class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                        <a href="../modules/tickets.php">🎫 IT Tickets</a>
                    </li>
                    <li class="menu-section">ระบบ</li>
                    <li class="active"><a href="settings.php">⚙️ ตั้งค่า</a></li>
                    <li><a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-wrapper">
                <div class="page-header">
                    <h1>⚙️ ตั้งค่า</h1>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> show">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <!-- Profile Info Card -->
                <div class="profile-info">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                    </div>
                    <div class="profile-details">
                        <h2><?php echo htmlspecialchars($currentUser['full_name']); ?></h2>
                        <div class="profile-meta">
                            <div>👤 Username: <strong><?php echo htmlspecialchars($currentUser['username']); ?></strong></div>
                            <div>📧 Email: <strong><?php echo htmlspecialchars($currentUser['email']); ?></strong></div>
                            <div>🛡️ บทบาท: <strong><?php echo $currentUser['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน'; ?></strong></div>
                        </div>
                    </div>
                </div>

                <div class="settings-grid">
                <!-- Update Profile -->
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <span>👤</span>
                            <span>แก้ไขข้อมูลส่วนตัว</span>
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-group">
                                <label class="form-label" for="settings_username">Username</label>
                                <input type="text" id="settings_username" name="username_display" class="form-control" autocomplete="username" value="<?php echo htmlspecialchars($currentUser['username']); ?>" disabled>
                                <small style="color: #94a3b8; margin-top: 5px; display: block;">* Username ไม่สามารถเปลี่ยนแปลงได้</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="settings_fullname">ชื่อ-นามสกุล *</label>
                                <input type="text" name="full_name" id="settings_fullname" class="form-control" autocomplete="name" value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="settings_email">Email *</label>
                                <input type="email" name="email" id="settings_email" class="form-control" autocomplete="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                ✅ บันทึกการเปลี่ยนแปลง
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <span>🔒</span>
                            <span>เปลี่ยนรหัสผ่าน</span>
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="info-box">
                            <div class="info-box-title">💡 คำแนะนำ</div>
                            <div class="info-box-text">
                                • รหัสผ่านควรมีอย่างน้อย 6 ตัวอักษร<br>
                                • ใช้ตัวอักษรผสมตัวเลขเพื่อความปลอดภัย
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <input type="text" name="username" value="<?php echo htmlspecialchars($currentUser['username']); ?>" autocomplete="username" style="display:none;" aria-hidden="true" tabindex="-1">
                            
                            <div class="form-group">
                                <label class="form-label" for="current_password">รหัสผ่านปัจจุบัน *</label>
                                <input type="password" name="current_password" id="current_password" class="form-control" autocomplete="current-password" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="new_password">รหัสผ่านใหม่ *</label>
                                <input type="password" name="new_password" id="new_password" class="form-control" autocomplete="new-password" minlength="6" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="confirm_password">ยืนยันรหัสผ่านใหม่ *</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" autocomplete="new-password" minlength="6" required>
                            </div>

                            <button type="submit" class="btn btn-success">
                                🔒 เปลี่ยนรหัสผ่าน
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Account Info -->
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <span>📊</span>
                            <span>ข้อมูลบัญชี</span>
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="stats-row">
                            <div class="stat-item">
                                <div class="stat-label">สร้างบัญชีเมื่อ</div>
                                <div class="stat-value"><?php echo formatDateShort($currentUser['created_at']); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">เข้าสู่ระบบล่าสุด</div>
                                <div class="stat-value"><?php echo $currentUser['last_login'] ? formatDateShort($currentUser['last_login']) : 'ไม่มีข้อมูล'; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">สถานะบัญชี</div>
                                <div class="stat-value"><?php echo $currentUser['is_active'] ? '✅ ใช้งาน' : '❌ ปิดใช้งาน'; ?></div>
                            </div>
                        </div>

                        <?php
                        // Get user statistics
                        $userId = $_SESSION['user_id'];
                        
                        // Count bookings
                        $bookingsStmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?");
                        $bookingsStmt->bind_param('i', $userId);
                        $bookingsStmt->execute();
                        $bookingsCount = $bookingsStmt->get_result()->fetch_assoc()['count'];
                        
                        // Count documents (if uploaded by this user)
                        $docsStmt = $db->prepare("SELECT COUNT(*) as count FROM documents WHERE uploaded_by = ?");
                        $docsStmt->bind_param('i', $userId);
                        $docsStmt->execute();
                        $docsCount = $docsStmt->get_result()->fetch_assoc()['count'];
                        ?>

                        <div class="stats-row" style="margin-top: 15px;">
                            <div class="stat-item">
                                <div class="stat-label">การจองทั้งหมด</div>
                                <div class="stat-value">📅 <?php echo $bookingsCount; ?> ครั้ง</div>
                            </div>
                            <?php if ($currentUser['role'] === 'admin'): ?>
                            <div class="stat-item">
                                <div class="stat-label">เอกสารที่อัปโหลด</div>
                                <div class="stat-value">📄 <?php echo $docsCount; ?> ไฟล์</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <script>
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) alert.classList.remove('show');
        }, 5000);
    </script>
</body>
</html>