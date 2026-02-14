<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login and admin role
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = getDB();
$message = '';
$messageType = '';

// Handle Update Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $settings = $_POST['setting'];
    $success_count = 0;
    
    foreach ($settings as $key => $value) {
        $stmt = $db->prepare("UPDATE system_settings SET value = ? WHERE setting_key = ?");
        $stmt->bind_param('ss', $value, $key);
        
        if ($stmt->execute()) {
            $success_count++;
        }
    }
    
    $message = "อัปเดตการตั้งค่า $success_count รายการสำเร็จ!";
    $messageType = 'success';
    logActivity($_SESSION['user_id'], 'อัปเดตการตั้งค่าระบบ', 'Settings', "อัปเดต $success_count รายการ");
}

// Get all settings
$settingsSQL = "SELECT * FROM system_settings ORDER BY setting_key";
$allSettings = $db->query($settingsSQL)->fetch_all(MYSQLI_ASSOC);

// Group settings by category
$settingsByCategory = [];
foreach ($allSettings as $setting) {
    $category = 'General';
    if (strpos($setting['setting_key'], 'email_') === 0) {
        $category = 'Email';
    } elseif (strpos($setting['setting_key'], 'notification_') === 0) {
        $category = 'Notifications';
    } elseif (strpos($setting['setting_key'], 'ticket_') === 0) {
        $category = 'Tickets';
    }
    $settingsByCategory[$category][] = $setting;
}

// Get system info
$systemInfo = [
    'php_version' => phpversion(),
    'mysql_version' => $db->server_info,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ - IT Support</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
              background: linear-gradient(180deg, #10ce30 0%, #000000 100%);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 30px 20px;
            border-bottom: 1px solid rgb(255, 255, 255);
             display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }

        .brand-title {
            font-size: 1.8em;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-subtitle {
            font-size: 0.85em;
            color: rgb(0, 0, 0);
            margin-top: 5px;
        }

        .sidebar-nav ul {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            color: rgb(255, 255, 255);
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding-left: 25px;
        }

        .sidebar-nav li.active a {
            background: linear-gradient(90deg, rgb(17, 224, 35), rgb(184, 209, 39));
            color: white;
            border-left: 4px solid #fff;
        }

        .menu-section {
            padding: 25px 20px 10px;
            color: rgb(255, 255, 255);
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .breadcrumb-nav {
            background: rgb(255, 255, 255);
            padding: 15px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgb(0, 0, 0);
        }

        .page-header h1 {
            font-size: 2em;
            color: #000000;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: #000000;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: white;
            padding: 10px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .tab {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            background: transparent;
            color: #718096;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .tab.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgb(0, 0, 0);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .card-title {
            font-size: 1.3em;
            font-weight: 600;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }

        .form-help {
            font-size: 0.85em;
            color: #718096;
            margin-top: 5px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Sarabun', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #030303;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e0;
            transition: 0.4s;
            border-radius: 30px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background: linear-gradient(135deg, #48bb78, #38a169);
        }

        input:checked + .slider:before {
            transform: translateX(30px);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-success {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 20px;
            background: #f7fafc;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            font-size: 0.85em;
            color: #000000;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1.1em;
            font-weight: 600;
            color: #2d3748;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .tabs {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <div>
                    <div class="brand-title">
                        <i class="fas fa-ticket-alt"></i>
                        IT Support
                    </div>
                    <div class="brand-subtitle">Ticket Management System</div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="../admin/dashboard.php">
                            <i class="fas fa-arrow-left"></i> กลับ Dashboard หลัก
                        </a>
                    </li>
                    
                    <li class="menu-section">หลัก</li>
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="tickets.php"><i class="fas fa-ticket-alt"></i> IT Tickets</a></li>
                    <li><a href="assets.php"><i class="fas fa-box"></i> สินทรัพย์</a></li>
                    <li><a href="knowledgebase.php"><i class="fas fa-book"></i> Knowledge Base</a></li>
                    
                    <li class="menu-section">จัดการ</li>
                    <li><a href="users.php"><i class="fas fa-users"></i> ผู้ใช้งาน</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> รายงาน</a></li>
                    <li><a href="slaconfig.php"><i class="fas fa-clock"></i> ตั้งค่า SLA</a></li>
                    
                    <li class="menu-section">ระบบ</li>
                    <li class="active"><a href="settings.php"><i class="fas fa-cog"></i> ตั้งค่า</a></li>
                    <li><a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')">
                        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                    </a></li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb-nav">
                <a href="dashboard.php" style="color: #667eea; text-decoration: none;">Dashboard</a> › 
                <span style="color: #2d3748; font-weight: 600;">ตั้งค่าระบบ</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-cog"></i> ตั้งค่าระบบ</h1>
                <p class="page-subtitle">จัดการการตั้งค่าและกำหนดค่าระบบ IT Support</p>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> show">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="switchTab('general')">
                    <i class="fas fa-cog"></i> ทั่วไป
                </button>
                <button class="tab" onclick="switchTab('email')">
                    <i class="fas fa-envelope"></i> อีเมล
                </button>
                <button class="tab" onclick="switchTab('notifications')">
                    <i class="fas fa-bell"></i> การแจ้งเตือน
                </button>
                <button class="tab" onclick="switchTab('tickets')">
                    <i class="fas fa-ticket-alt"></i> Tickets
                </button>
                <button class="tab" onclick="switchTab('system')">
                    <i class="fas fa-server"></i> ระบบ
                </button>
            </div>

            <!-- Tab Contents -->
            <form method="POST">
                <input type="hidden" name="action" value="update_settings">

                <!-- General Tab -->
                <div id="general" class="tab-content active">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-cog"></i> การตั้งค่าทั่วไป</h3>
                        </div>
                        <div class="card-body">
                            <?php if (isset($settingsByCategory['General'])): ?>
                                <?php foreach ($settingsByCategory['General'] as $setting): ?>
                                <div class="form-group">
                                    <label><?php echo ucfirst(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                    <?php if ($setting['value'] === 'true' || $setting['value'] === 'false'): ?>
                                        <label class="toggle-switch">
                                            <input type="checkbox" 
                                                   name="setting[<?php echo $setting['setting_key']; ?>]" 
                                                   value="true"
                                                   <?php echo $setting['value'] === 'true' ? 'checked' : ''; ?>
                                                   onchange="this.value = this.checked ? 'true' : 'false'">
                                            <span class="slider"></span>
                                        </label>
                                    <?php else: ?>
                                        <input type="text" 
                                               name="setting[<?php echo $setting['setting_key']; ?>]" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($setting['value']); ?>">
                                    <?php endif; ?>
                                    <div class="form-help"><?php echo htmlspecialchars($setting['description'] ?? ''); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Email Tab -->
                <div id="email" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-envelope"></i> การตั้งค่าอีเมล</h3>
                        </div>
                        <div class="card-body">
                            <?php if (isset($settingsByCategory['Email'])): ?>
                                <?php foreach ($settingsByCategory['Email'] as $setting): ?>
                                <div class="form-group">
                                    <label><?php echo ucfirst(str_replace(['email_', '_'], ['', ' '], $setting['setting_key'])); ?></label>
                                    <?php if ($setting['value'] === 'true' || $setting['value'] === 'false'): ?>
                                        <label class="toggle-switch">
                                            <input type="checkbox" 
                                                   name="setting[<?php echo $setting['setting_key']; ?>]" 
                                                   value="true"
                                                   <?php echo $setting['value'] === 'true' ? 'checked' : ''; ?>
                                                   onchange="this.value = this.checked ? 'true' : 'false'">
                                            <span class="slider"></span>
                                        </label>
                                    <?php else: ?>
                                        <input type="text" 
                                               name="setting[<?php echo $setting['setting_key']; ?>]" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($setting['value']); ?>">
                                    <?php endif; ?>
                                    <div class="form-help"><?php echo htmlspecialchars($setting['description'] ?? ''); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Notifications Tab -->
                <div id="notifications" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-bell"></i> การตั้งค่าการแจ้งเตือน</h3>
                        </div>
                        <div class="card-body">
                            <?php if (isset($settingsByCategory['Notifications'])): ?>
                                <?php foreach ($settingsByCategory['Notifications'] as $setting): ?>
                                <div class="form-group">
                                    <label><?php echo ucfirst(str_replace(['notification_', '_'], ['', ' '], $setting['setting_key'])); ?></label>
                                    <?php if ($setting['value'] === 'true' || $setting['value'] === 'false'): ?>
                                        <label class="toggle-switch">
                                            <input type="checkbox" 
                                                   name="setting[<?php echo $setting['setting_key']; ?>]" 
                                                   value="true"
                                                   <?php echo $setting['value'] === 'true' ? 'checked' : ''; ?>
                                                   onchange="this.value = this.checked ? 'true' : 'false'">
                                            <span class="slider"></span>
                                        </label>
                                    <?php else: ?>
                                        <input type="text" 
                                               name="setting[<?php echo $setting['setting_key']; ?>]" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($setting['value']); ?>">
                                    <?php endif; ?>
                                    <div class="form-help"><?php echo htmlspecialchars($setting['description'] ?? ''); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tickets Tab -->
                <div id="tickets" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-ticket-alt"></i> การตั้งค่า Tickets</h3>
                        </div>
                        <div class="card-body">
                            <?php if (isset($settingsByCategory['Tickets'])): ?>
                                <?php foreach ($settingsByCategory['Tickets'] as $setting): ?>
                                <div class="form-group">
                                    <label><?php echo ucfirst(str_replace(['ticket_', '_'], ['', ' '], $setting['setting_key'])); ?></label>
                                    <?php if ($setting['value'] === 'true' || $setting['value'] === 'false'): ?>
                                        <label class="toggle-switch">
                                            <input type="checkbox" 
                                                   name="setting[<?php echo $setting['setting_key']; ?>]" 
                                                   value="true"
                                                   <?php echo $setting['value'] === 'true' ? 'checked' : ''; ?>
                                                   onchange="this.value = this.checked ? 'true' : 'false'">
                                            <span class="slider"></span>
                                        </label>
                                    <?php else: ?>
                                        <input type="text" 
                                               name="setting[<?php echo $setting['setting_key']; ?>]" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($setting['value']); ?>">
                                    <?php endif; ?>
                                    <div class="form-help"><?php echo htmlspecialchars($setting['description'] ?? ''); ?></div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- System Tab -->
                <div id="system" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-server"></i> ข้อมูลระบบ</h3>
                        </div>
                        <div class="card-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">PHP Version</div>
                                    <div class="info-value"><?php echo $systemInfo['php_version']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">MySQL Version</div>
                                    <div class="info-value"><?php echo $systemInfo['mysql_version']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Server Software</div>
                                    <div class="info-value"><?php echo $systemInfo['server_software']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Document Root</div>
                                    <div class="info-value" style="font-size: 0.9em; word-break: break-all;">
                                        <?php echo $systemInfo['document_root']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> บันทึกการตั้งค่า
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to selected tab
            event.target.classList.add('active');
        }
    </script>
</body>
</html>