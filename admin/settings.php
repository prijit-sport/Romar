<?php
session_start();
require_once '../config/database.php';

// ตรวจสอบการ login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$db = getDb();
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// ตรวจสอบว่ามีคอลัมน์ที่จำเป็นหรือไม่
$columns_result = $db->query("PRAGMA table_info(users)");
$existing_columns = [];
while ($col = $columns_result->fetchArray(SQLITE3_ASSOC)) {
    $existing_columns[] = $col['name'];
}

$has_avatar_column = in_array('avatar', $existing_columns);
$has_phone_column = in_array('phone', $existing_columns);
$has_department_column = in_array('department', $existing_columns);

// ดึงข้อมูลผู้ใช้
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

// จัดการอัปเดตโปรไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // อัปเดตข้อมูลส่วนตัว
    if ($_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        
        // สร้าง SQL แบบ dynamic ตามคอลัมน์ที่มี
        $update_fields = ['full_name = ?', 'email = ?'];
        $bind_values = [$full_name, $email];
        
        if ($has_phone_column) {
            $update_fields[] = 'phone = ?';
            $bind_values[] = $phone;
        }
        
        if ($has_department_column) {
            $update_fields[] = 'department = ?';
            $bind_values[] = $department;
        }
        
        $bind_values[] = $user_id; // WHERE user_id = ?
        
        $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE user_id = ?";
        $stmt = $db->prepare($sql);
        
        if ($stmt) {
            foreach ($bind_values as $index => $value) {
                $stmt->bindValue($index + 1, $value, SQLITE3_TEXT);
            }
            
            if ($stmt->execute()) {
                // Log activity
                $log = $db->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'update_profile', 'อัปเดตข้อมูลโปรไฟล์', datetime('now'))");
                $log->bindValue(1, $user_id, SQLITE3_INTEGER);
                $log->execute();
                
                Database::checkpoint();
                
                $_SESSION['full_name'] = $full_name;
                $success_message = "อัปเดตข้อมูลส่วนตัวสำเร็จ!";
                
                // Reload user data
                $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $user = $result->fetchArray(SQLITE3_ASSOC);
            } else {
                $error_message = "ไม่สามารถอัปเดตข้อมูลได้";
            }
        } else {
            $error_message = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL";
        }
    }
    
    // เปลี่ยนรหัสผ่าน
    if ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // ตรวจสอบรหัสผ่านปัจจุบัน
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                    $stmt->bindValue(1, $hashed_password, SQLITE3_TEXT);
                    $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
                    
                    if ($stmt->execute()) {
                        // Log activity
                        $log = $db->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'change_password', 'เปลี่ยนรหัสผ่าน', datetime('now'))");
                        $log->bindValue(1, $user_id, SQLITE3_INTEGER);
                        $log->execute();
                        
                        Database::checkpoint();
                        
                        $success_message = "เปลี่ยนรหัสผ่านสำเร็จ!";
                    } else {
                        $error_message = "ไม่สามารถเปลี่ยนรหัสผ่านได้";
                    }
                } else {
                    $error_message = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
                }
            } else {
                $error_message = "รหัสผ่านใหม่ไม่ตรงกัน";
            }
        } else {
            $error_message = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
        }
    }
    
    // อัปโหลดรูปโปรไฟล์
    if ($_POST['action'] === 'upload_avatar') {
        // ตรวจสอบว่ามีคอลัมน์ avatar หรือไม่
        if (!$has_avatar_column) {
            $error_message = "โปรดอัปเดตฐานข้อมูลก่อนใช้งานฟีเจอร์นี้ <a href='update-database.php' style='color: #667eea;'>คลิกที่นี่เพื่ออัปเดต</a>";
        } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_ext, $allowed_ext)) {
                $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_ext;
                $upload_path = '../uploads/images/' . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // ลบรูปเก่า (ถ้ามี)
                    if (isset($user['avatar']) && $user['avatar'] && file_exists('../uploads/images/' . $user['avatar'])) {
                        unlink('../uploads/images/' . $user['avatar']);
                    }
                    
                    $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE user_id = ?");
                    $stmt->bindValue(1, $new_filename, SQLITE3_TEXT);
                    $stmt->bindValue(2, $user_id, SQLITE3_INTEGER);
                    
                    if ($stmt->execute()) {
                        // Log activity
                        $log = $db->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'upload_avatar', 'อัปโหลดรูปโปรไฟล์', datetime('now'))");
                        $log->bindValue(1, $user_id, SQLITE3_INTEGER);
                        $log->execute();
                        
                        Database::checkpoint();
                        
                        $success_message = "อัปโหลดรูปโปรไฟล์สำเร็จ!";
                        
                        // Reload user data
                        $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
                        $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
                        $result = $stmt->execute();
                        $user = $result->fetchArray(SQLITE3_ASSOC);
                    }
                } else {
                    $error_message = "ไม่สามารถอัปโหลดไฟล์ได้";
                }
            } else {
                $error_message = "อนุญาตเฉพาะไฟล์รูปภาพเท่านั้น (jpg, jpeg, png, gif)";
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
    <title>ตั้งค่า - Romar Dormitory Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 2em;
            font-weight: 700;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px;
        }

        .sidebar-menu {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            height: fit-content;
        }

        .menu-item {
            padding: 15px 20px;
            margin-bottom: 8px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #555;
            font-weight: 500;
        }

        .menu-item:hover {
            background: #f8f9fa;
            color: #667eea;
        }

        .menu-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .menu-icon {
            font-size: 1.3em;
        }

        .content-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }

        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

        .section-title {
            font-size: 1.8em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .avatar-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .avatar-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #667eea;
            margin-bottom: 15px;
        }

        .avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 4em;
            color: white;
            margin-bottom: 15px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .info-value {
            color: #2c3e50;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .password-note {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-top: 5px;
        }

        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>⚙️ ตั้งค่า</h1>
            <a href="dashboard.php" class="btn btn-secondary">← กลับหน้าหลัก</a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Sidebar Menu -->
            <div class="sidebar-menu">
                <div class="menu-item active" onclick="showSection('profile')">
                    <span class="menu-icon">👤</span>
                    <span>ข้อมูลส่วนตัว</span>
                </div>
                <div class="menu-item" onclick="showSection('password')">
                    <span class="menu-icon">🔒</span>
                    <span>เปลี่ยนรหัสผ่าน</span>
                </div>
                <div class="menu-item" onclick="showSection('avatar')">
                    <span class="menu-icon">📷</span>
                    <span>รูปโปรไฟล์</span>
                </div>
                <div class="menu-item" onclick="showSection('account')">
                    <span class="menu-icon">📋</span>
                    <span>ข้อมูลบัญชี</span>
                </div>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <div class="menu-item" onclick="showSection('system')">
                    <span class="menu-icon">⚙️</span>
                    <span>ตั้งค่าระบบ</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Main Content -->
            <div class="content-box">
                <!-- Profile Section -->
                <div id="profile" class="section active">
                    <h2 class="section-title">ข้อมูลส่วนตัว</h2>
                    
                    <?php if (!$has_phone_column || !$has_department_column): ?>
                        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                            <h3 style="color: #856404; margin-bottom: 10px;">⚠️ ต้องอัปเดตฐานข้อมูลก่อน</h3>
                            <p style="color: #856404; margin-bottom: 15px;">
                                บางฟีเจอร์ต้องการให้อัปเดตฐานข้อมูลก่อนใช้งาน
                            </p>
                            <a href="update-database.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px;">
                                🔧 อัปเดตฐานข้อมูล
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label>ชื่อ-นามสกุล *</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>อีเมล *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>เบอร์โทรศัพท์</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>แผนก</label>
                            <input type="text" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                        </div>

                        <button type="submit" class="btn btn-primary">💾 บันทึกการเปลี่ยนแปลง</button>
                    </form>
                </div>

                <!-- Password Section -->
                <div id="password" class="section">
                    <h2 class="section-title">เปลี่ยนรหัสผ่าน</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label>รหัสผ่านปัจจุบัน *</label>
                            <input type="password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label>รหัสผ่านใหม่ *</label>
                            <input type="password" name="new_password" required>
                            <div class="password-note">รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร</div>
                        </div>

                        <div class="form-group">
                            <label>ยืนยันรหัสผ่านใหม่ *</label>
                            <input type="password" name="confirm_password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">🔒 เปลี่ยนรหัสผ่าน</button>
                    </form>
                </div>

                <!-- Avatar Section -->
                <div id="avatar" class="section">
                    <h2 class="section-title">รูปโปรไฟล์</h2>
                    
                    <?php if (!$has_avatar_column): ?>
                        <div style="background: #fff3cd; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                            <h3 style="color: #856404; margin-bottom: 10px;">⚠️ ต้องอัปเดตฐานข้อมูลก่อน</h3>
                            <p style="color: #856404; margin-bottom: 15px;">
                                ฟีเจอร์รูปโปรไฟล์ต้องการให้อัปเดตฐานข้อมูลก่อนใช้งาน
                            </p>
                            <a href="update-database.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px;">
                                🔧 อัปเดตฐานข้อมูล
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="avatar-container">
                        <?php if (isset($user['avatar']) && $user['avatar']): ?>
                            <img src="../uploads/images/<?php echo htmlspecialchars($user['avatar']); ?>" class="avatar-preview" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo mb_substr($user['full_name'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_avatar">
                        
                        <div class="form-group">
                            <label>เลือกรูปภาพใหม่</label>
                            <input type="file" name="avatar" accept="image/*" required>
                            <div class="password-note">รองรับ: JPG, JPEG, PNG, GIF (ขนาดไม่เกิน 5MB)</div>
                        </div>

                        <button type="submit" class="btn btn-primary">📷 อัปโหลดรูป</button>
                    </form>
                </div>

                <!-- Account Section -->
                <div id="account" class="section">
                    <h2 class="section-title">ข้อมูลบัญชี</h2>
                    
                    <div class="info-card">
                        <div class="info-row">
                            <span class="info-label">ชื่อผู้ใช้:</span>
                            <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">สถานะ:</span>
                            <span class="info-value">
                                <?php 
                                if ($user['role'] === 'admin') {
                                    echo '👑 ผู้ดูแลระบบ';
                                } else {
                                    echo '👤 ผู้ใช้ทั่วไป';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">สถานะบัญชี:</span>
                            <span class="info-value">
                                <?php echo $user['is_active'] ? '✅ ใช้งานอยู่' : '❌ ปิดการใช้งาน'; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">วันที่สร้างบัญชี:</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">เข้าสู่ระบบล่าสุด:</span>
                            <span class="info-value">
                                <?php 
                                echo $user['last_login'] 
                                    ? date('d/m/Y H:i', strtotime($user['last_login'])) 
                                    : 'ยังไม่เคยเข้าสู่ระบบ';
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- System Settings (Admin Only) -->
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <div id="system" class="section">
                    <h2 class="section-title">ตั้งค่าระบบ</h2>
                    
                    <div class="info-card">
                        <h3 style="color: #667eea; margin-bottom: 15px;">🔧 ตัวเลือกการตั้งค่า</h3>
                        
                        <div style="margin: 20px 0;">
                            <a href="users-management.php" class="btn btn-primary" style="display: block; text-align: center; margin-bottom: 10px;">
                                👥 จัดการผู้ใช้
                            </a>
                            
                            <a href="documents.php" class="btn btn-primary" style="display: block; text-align: center; margin-bottom: 10px;">
                                📁 จัดการเอกสาร
                            </a>
                            
                            <a href="dashboard.php" class="btn btn-primary" style="display: block; text-align: center;">
                                📊 ดูสถิติระบบ
                            </a>
                        </div>

                        <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
                            <strong style="color: #856404;">⚠️ ข้อมูลระบบ</strong>
                            <div style="margin-top: 10px; color: #856404;">
                                <div>เวอร์ชัน: 1.0.0</div>
                                <div>PHP Version: <?php echo PHP_VERSION; ?></div>
                                <div>Database: SQLite 3</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });

            // Remove active class from all menu items
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });

            // Show selected section
            document.getElementById(sectionId).classList.add('active');

            // Add active class to clicked menu item
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>