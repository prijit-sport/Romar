<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
csrf_token();
$isValidCsrf = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf($_POST['csrf_token'] ?? '')) {
    $isValidCsrf = false;
    $message = 'Invalid CSRF token.';
    $messageType = 'error';
}

// Handle Update Profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile' && $isValidCsrf) {
    $fullName = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    
    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
    $stmt->bind_param('ssi', $fullName, $email, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $message = 'อัปเดตโปรไฟล์สำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'อัปเดตโปรไฟล์', 'Settings', 'อัปเดตข้อมูลส่วนตัว');
    } else {
        $message = 'เกิดข้อผิดพลาดระหว่างการอัปเดตโปรไฟล์: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Handle Change Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password' && $isValidCsrf) {
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
        $message = 'รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน!';
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
            logActivity($_SESSION['user_id'], 'เปลี่ยนรหัสผ่าน', 'Settings', 'อัปเดตรหัสผ่าน');
        } else {
            $message = 'เกิดข้อผิดพลาดขณะเปลี่ยนรหัสผ่าน: ' . $updateStmt->error;
            $messageType = 'error';
        }
    }
}

$currentUser = getCurrentUser();

$userId = $_SESSION['user_id'];

// Count bookings
$bookingsStmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?");
$bookingsStmt->bind_param('i', $userId);
$bookingsStmt->execute();
$bookingsCount = $bookingsStmt->get_result()->fetch_assoc()['count'];

// Count documents uploaded by the user
$docsStmt = $db->prepare("SELECT COUNT(*) as count FROM documents WHERE uploaded_by = ?");
$docsStmt->bind_param('i', $userId);
$docsStmt->execute();
$docsCount = $docsStmt->get_result()->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่า - Romar</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --card-radius: 1.25rem;
            --card-shadow: 0 25px 45px rgba(15, 23, 42, 0.15);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(180deg, #f5f7ff 0%, #e2e8fb 60%, #dbeafe 100%);
            color: #0f172a;
            min-height: 100vh;
        }

        .container {
            min-height: 100vh;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1a3edc 0%, #0b2c73 100%);
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 1000;
            box-shadow: 2px 0 35px rgba(15, 23, 42, 0.25);
            overflow: hidden;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand {
            padding: 1.5rem 1.4rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            border-bottom: 1px solid rgba(248, 250, 252, 0.3);
            color: #f2f6ff;
        }

        .brand-icon {
            font-size: 2rem;
        }

        .brand-subtitle {
            font-size: 0.85rem;
            color: rgba(248, 250, 252, 0.7);
            letter-spacing: 0.01em;
        }

        .nav-wrapper {
            flex: 1;
            padding: 1rem 1.25rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            overflow-y: auto;
        }

        .sidebar-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .sidebar-nav li {
            margin: 0;
            border-left: 4px solid transparent;
            transition: border-color 0.3s ease;
        }

        .sidebar-nav a {
            color: rgba(226, 232, 240, 0.95);
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.95rem 1.1rem;
            border-radius: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.01em;
            font-size: 0.95rem;
            text-decoration: none;
            position: relative;
        }

        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: white;
        }

        .sidebar-nav li.active {
            border-color: rgba(255, 255, 255, 0.8);
        }

        .sidebar-nav li.active a {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.25), rgba(255, 255, 255, 0.05));
            color: white;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35), 0 12px 25px rgba(0, 0, 0, 0.2);
        }

        .menu-section {
            color: rgba(229, 231, 235, 0.85);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            padding: 0.65rem 0.75rem;
            margin-top: 0.8rem;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 0.75rem;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: clamp(1.25rem, 3vw, 2.75rem);
            min-height: 100vh;
            display: flex;
            justify-content: center;
        }

        .content-wrapper {
            width: 100%;
            max-width: 1260px;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .page-header {
            background: #ffffff;
            border-radius: var(--card-radius);
            padding: 1.35rem 1.75rem;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .page-title-block {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .page-icon {
            width: 60px;
            height: 60px;
            border-radius: 1rem;
            background: linear-gradient(135deg, #1a3edc, #0b2c73);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 2rem;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.25);
        }

        .page-title-block h1 {
            margin: 0;
            font-size: clamp(1.8rem, 2.2vw, 2.3rem);
            font-weight: 700;
        }

        .page-title-block .page-description {
            margin: 0.25rem 0 0;
            color: #475569;
            font-weight: 500;
            line-height: 1.4;
        }

        .page-profile-chip {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.2), rgba(59, 130, 246, 0.3));
            padding: 0.75rem 1.25rem;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            min-width: 250px;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.5);
        }

        .chip-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            border: 2px solid rgba(255, 255, 255, 0.6);
        }

        .chip-details {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
            color: #0f172a;
        }

        .chip-name {
            font-size: 1rem;
            font-weight: 700;
        }

        .chip-role,
        .chip-meta {
            font-size: 0.85rem;
            color: rgba(15, 23, 42, 0.8);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1.25rem;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 1rem;
            padding: 1.35rem;
            box-shadow: var(--card-shadow);
            border-left: 5px solid rgba(26, 62, 220, 0.45);
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .stat-card-label {
            font-size: 0.75rem;
            color: #475569;
        }

        .stat-card-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f172a;
        }

        .stat-card-subtext {
            font-size: 0.85rem;
            color: #64748b;
        }

        .stat-card.blue {
            border-color: #1a3edc;
        }

        .stat-card.green {
            border-color: #059669;
        }

        .stat-card.orange {
            border-color: #ea580c;
        }

        .stat-card.purple {
            border-color: #7c3aed;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.25rem;
        }

        .settings-card {
            background: #fff;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-radius: var(--card-radius) var(--card-radius) 0 0;
            background: linear-gradient(135deg, #0c1a33 0%, #1a3edc 100%);
            color: #f4f6ff;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-weight: 600;
            font-size: 1.05rem;
        }

        .card-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .form-label {
            font-weight: 600;
        }

        .form-control {
            border-radius: 0.75rem;
            border: 1px solid #d6dcf3;
            padding: 0.85rem 1rem;
            font-size: 1rem;
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            border-color: #1a3edc;
            outline: none;
            box-shadow: 0 0 0 3px rgba(26, 62, 220, 0.15);
        }

        .btn {
            border: none;
            border-radius: 0.75rem;
            padding: 0.9rem 1.25rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            color: #fff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1a3edc, #0b2c73);
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(15, 23, 42, 0.25);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.25);
        }

        .info-box {
            padding: 1.1rem 1.25rem;
            border-radius: 0.9rem;
            background: #eef6ff;
            border-left: 4px solid #1a3edc;
            color: #0f172a;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.05);
        }

        .info-box-title {
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .info-box-text {
            margin: 0;
            font-size: 0.9rem;
            color: #1e293b;
            line-height: 1.4;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .stats-row + .stats-row {
            margin-top: 1rem;
        }

        .stats-row .stat-item {
            padding: 1rem;
            border-radius: 1rem;
            background: #f8fafc;
            border-left: 4px solid #1a3edc;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.04);
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #475569;
        }

        .stat-value {
            font-size: 1.3rem;
            font-weight: 600;
            color: #0f172a;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #1a3edc;
            color: #0f172a;
        }

        .alert-error {
            background: rgba(248, 113, 113, 0.1);
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        @media (max-width: 1024px) {
            .page-profile-chip {
                width: 100%;
                justify-content: space-between;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                justify-content: center;
            }
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

            <div class="nav-wrapper">
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <a href="dashboard.php">📊 Dashboard</a>
                    </li>

                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <li class="menu-section">การจัดการ</li>
                    <li class="<?php echo $current_page == 'meeting-rooms.php' ? 'active' : ''; ?>">
                        <a href="meeting-rooms.php">🏢 จัดการห้องประชุม</a>
                    </li>
                    <li class="<?php echo $current_page == 'documents.php' ? 'active' : ''; ?>">
                        <a href="documents.php">📄 จัดการเอกสาร</a>
                    </li>
                    <?php endif; ?>

                    <li class="menu-section">ฟีเจอร์</li>
                    <li class="<?php echo $current_page == 'room-booking.php' ? 'active' : ''; ?>">
                        <a href="room-booking.php">📅 จองห้องประชุม</a>
                    </li>
                    <li class="<?php echo $current_page == 'announcements.php' ? 'active' : ''; ?>">
                        <a href="announcements.php">📢 ข่าวสาร</a>
                    </li>
                    <li class="<?php echo $current_page == 'tickets.php' ? 'active' : ''; ?>">
                        <a href="../modules/tickets.php">🎫 IT Tickets</a>
                    </li>
                    <?php if ($currentUser['role'] !== 'admin'): ?>
                    <li class="<?php echo $current_page == 'userdocuments.php' ? 'active' : ''; ?>">
                        <a href="userdocuments.php">📄 เอกสารของฉัน</a>
                    </li>
                    <?php endif; ?>

                    <li class="menu-section">ระบบ</li>
                    <li class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                        <a href="settings.php">⚙️ ตั้งค่า</a>
                    </li>
                    <li>
                        <a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">🚪 ออกจากระบบ</a>
                    </li>
                </ul>
            </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-wrapper">
                <div class="page-header">
                    <div class="page-title-block">
                        <div class="page-icon">⚙️</div>
                        <div>
                            <h1>ตั้งค่า</h1>
                            <p class="page-description">จัดการข้อมูลส่วนตัว รหัสผ่าน และการเข้าถึงระบบของคุณ</p>
                        </div>
                    </div>
                    <div class="page-profile-chip">
                        <div class="chip-avatar">
                            <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                        </div>
                        <div class="chip-details">
                            <div class="chip-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                            <div class="chip-role"><?php echo $currentUser['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งาน'; ?></div>
                            <div class="chip-meta"><?php echo htmlspecialchars($currentUser['username']); ?> · <?php echo htmlspecialchars($currentUser['email']); ?></div>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> show">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-card-label">รายการจองทั้งหมด</div>
                        <div class="stat-card-value"><?php echo $bookingsCount; ?></div>
                        <div class="stat-card-subtext">กิจกรรมหรือห้องที่คุณดูแลอยู่</div>
                    </div>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <div class="stat-card purple">
                        <div class="stat-card-label">เอกสารที่อัปโหลด</div>
                        <div class="stat-card-value"><?php echo $docsCount; ?></div>
                        <div class="stat-card-subtext">ไฟล์ที่คุณจัดการในระบบ</div>
                    </div>
                    <?php endif; ?>
                    <div class="stat-card green">
                        <div class="stat-card-label">สถานะบัญชี</div>
                        <div class="stat-card-value"><?php echo $currentUser['is_active'] ? 'ใช้งานอยู่' : 'ระงับ'; ?></div>
                        <div class="stat-card-subtext"><?php echo $currentUser['role'] === 'admin' ? 'สิทธิ์ผู้ดูแลระบบ' : 'สิทธิ์ผู้ใช้งาน'; ?></div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-card-label">เข้าสู่ระบบล่าสุด</div>
                        <div class="stat-card-value"><?php echo $currentUser['last_login'] ? formatDateShort($currentUser['last_login']) : 'ไม่เคยเข้าสู่ระบบ'; ?></div>
                        <div class="stat-card-subtext">บันทึกเวลาก่อนหน้า</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-card-label">สร้างบัญชีเมื่อ</div>
                        <div class="stat-card-value"><?php echo formatDateShort($currentUser['created_at']); ?></div>
                        <div class="stat-card-subtext">ข้อมูลพื้นฐานของบัญชี</div>
                    </div>
                </div>

                <div class="settings-grid">
                <!-- Update Profile -->
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <span>👤</span>
                            <span>ข้อมูลส่วนตัว</span>
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            <?php echo csrf_input(); ?>
                            
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
                                บันทึกการเปลี่ยนแปลง
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
                            <div class="info-box-title">คำแนะนำ</div>
                            <div class="info-box-text">
                                รหัสผ่านควรมีอย่างน้อย 6 ตัวอักษรและใช้ตัวอักษรขนาดใหญ่, เล็ก, ตัวเลข หรือสัญลักษณ์เพื่อความปลอดภัย
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <?php echo csrf_input(); ?>
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
                                เปลี่ยนรหัสผ่าน
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Account Info -->
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <span>ℹ️</span>
                            <span>ข้อมูลบัญชี</span>
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="stats-row">
                            <div class="stat-item">
                                <div class="stat-label">วันที่สร้างบัญชี</div>
                                <div class="stat-value"><?php echo formatDateShort($currentUser['created_at']); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">เข้าสู่ระบบล่าสุด</div>
                                <div class="stat-value"><?php echo $currentUser['last_login'] ? formatDateShort($currentUser['last_login']) : 'ไม่เคยเข้าสู่ระบบ'; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">สถานะบัญชี</div>
                                <div class="stat-value"><?php echo $currentUser['is_active'] ? 'ใช้งานอยู่' : 'ระงับ'; ?></div>
                            </div>
                        </div>

                        <div class="stats-row" style="margin-top: 15px;">
                            <div class="stat-item">
                                <div class="stat-label">รายการจองของคุณ</div>
                                <div class="stat-value"><?php echo $bookingsCount; ?> รายการ</div>
                            </div>
                            <?php if ($currentUser['role'] === 'admin'): ?>
                            <div class="stat-item">
                                <div class="stat-label">เอกสารที่อัปโหลด</div>
                                <div class="stat-value"><?php echo $docsCount; ?> ไฟล์</div>
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

