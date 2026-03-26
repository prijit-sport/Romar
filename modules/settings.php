<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

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
$success_count = 0;

csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = ui_text('settings.csrf_error');
        $messageType = 'error';
    } else {
        $settings = $_POST['setting'] ?? [];
        foreach ($settings as $key => $value) {
            $value = trim((string) $value);
            $stmt = $db->prepare("UPDATE system_settings SET value = ? WHERE setting_key = ?");
            $stmt->bind_param('ss', $value, $key);
            if ($stmt->execute()) {
                $success_count++;
            }
        }
        if ($success_count > 0) {
            $message = sprintf(ui_text('settings.updated'), ['count' => $success_count]);
            $messageType = 'success';
            logActivity($_SESSION['user_id'], 'อัปเดตการตั้งค่าระบบ', 'Settings', "อัปเดตการตั้งค่า {$success_count} รายการเรียบร้อยแล้ว");
        } else {
            $message = ui_text('settings.failed');
            $messageType = 'info';
        }
    }
}

$settingsSQL = "SELECT * FROM system_settings ORDER BY setting_key";
$allSettings = $db->query($settingsSQL)->fetch_all(MYSQLI_ASSOC);

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

$categoryOrder = ['General', 'Email', 'Notifications', 'Tickets'];
$orderedSettings = [];
foreach ($categoryOrder as $cat) {
    if (!empty($settingsByCategory[$cat])) {
        $orderedSettings[$cat] = $settingsByCategory[$cat];
    }
}
foreach ($settingsByCategory as $cat => $entries) {
    if (!isset($orderedSettings[$cat])) {
        $orderedSettings[$cat] = $entries;
    }
}

$categoryLabels = [
    'General' => 'ทั่วไป',
    'Email' => 'อีเมล',
    'Notifications' => 'การแจ้งเตือน',
    'Tickets' => 'การตั้งค่า SLA ตั๋วสนับสนุน',
];

$categoryDescriptions = [
    'General' => 'จัดการการตั้งค่าทั่วไปของระบบ สามารถปรับแต่งการทำงานพื้นฐานของโปรแกรมได้ที่นี่ เช่น ชื่อระบบ เวอร์ชัน และการตั้งค่าหลักอื่นๆ',
    'Email' => 'ตั้งค่าเซิร์ฟเวอร์ส่งอีเมลของระบบ สามารถกำหนดค่า SMTP Server รายละเอียดการเชื่อมต่อ พอร์ต และข้อมูลการยืนยันตัวตนได้ที่นี่ เพื่อให้ระบบสามารถส่งอีเมลแจ้งเตือนได้ถูกต้อง',
    'Notifications' => 'กำหนดค่าการแจ้งเตือนของระบบ รวมถึงการแจ้งเตือนทางอีเมล SMS และการแสดงผลแจ้งเตือนในหน้าแดชบอร์ด',
    'Tickets' => 'การตั้งค่า SLA สำหรับระบบตั๋วสนับสนุน - กำหนดเวลาตอบสนอง ระดับความสำคัญ การแจ้งเตือนอัตโนมัติ และขั้นตอนการจัดการตั้งแต่รับแจ้งปัญหา ส่งต่อผู้เชี่ยวชาญ ตรวจสอบ และปิดงาน',
];

$categoryIcons = [
    'General' => 'fas fa-sliders-h',
    'Email' => 'fas fa-envelope',
    'Notifications' => 'fas fa-bell',
    'Tickets' => 'fas fa-ticket-alt',
];

$systemInfo = [
    'php_version' => phpversion(),
    'mysql_version' => $db->server_info,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
];

$systemInfoCards = [
    [
        'label' => 'PHP Version',
        'value' => $systemInfo['php_version'],
        'icon' => 'fas fa-code',
        'gradient' => 'gradient-purple',
    ],
    [
        'label' => 'MySQL Version',
        'value' => $systemInfo['mysql_version'],
        'icon' => 'fas fa-database',
        'gradient' => 'gradient-blue',
    ],
    [
        'label' => 'Server Software',
        'value' => $systemInfo['server_software'],
        'icon' => 'fas fa-server',
        'gradient' => 'gradient-gold',
    ],
    [
        'label' => 'Document Root',
        'value' => $systemInfo['document_root'],
        'icon' => 'fas fa-folder-open',
        'gradient' => 'gradient-green',
    ],
];

if (!function_exists('settings_should_use_textarea')) {
    function settings_should_use_textarea(string $key, string $value): bool {
        $value = trim($value);
        return str_contains($value, "\n")
            || mb_strlen($value) > 60
            || str_contains($key, 'message')
            || str_contains($key, 'body')
            || str_contains($key, 'note')
            || str_contains($key, 'description');
    }
}

if (!function_exists('settings_format_label')) {
    function settings_format_label(string $key, ?string $label): string {
        if (!empty($label)) {
            return $label;
        }
        $key = str_replace('_', ' ', $key);
        return ucwords($key);
    }
}

$pageTitle = ui_text('page.title.settings');
$activePage = 'settings';
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <div class="breadcrumb-nav">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="../admin/dashboard.php"><i class="fas fa-home"></i> <?php echo ui_text('nav.dashboard'); ?></a>
            </li>
            <li class="breadcrumb-separator">&rsaquo;</li>
            <li class="breadcrumb-item active"><i class="fas fa-cogs"></i> <?php echo ui_text('page.title.settings'); ?></li>
        </ol>
        <a href="../admin/dashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            <?php echo ui_text('nav.back_to_dashboard'); ?>
        </a>
    </div>

    <div class="page-header">
        <div>
            <h1><i class="fas fa-cogs"></i> <?php echo ui_text('page.title.settings'); ?></h1>
            <p class="page-subtitle"><?php echo ui_text('page.subtitle.settings'); ?></p>
        </div>
    </div>

    <section class="section">
        <div class="section-header">
            <div>
                <h2 class="section-title"><i class="fas fa-sliders-h"></i> <?php echo ui_text('settings.section_title'); ?></h2>
                <p class="section-subtitle"><?php echo ui_text('settings.section_subtitle'); ?></p>
            </div>
        </div>
        <div class="section-body">
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> show">
                <i class="fas fa-info-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="update_settings">

                <?php foreach ($orderedSettings as $category => $settingsList): ?>
                <?php
                    $categoryLabel = $categoryLabels[$category] ?? ucwords($category);
                    $categoryDescription = $categoryDescriptions[$category] ?? 'การตั้งค่าทั่วไป';
                    $categoryIcon = $categoryIcons[$category] ?? 'fas fa-cog';
                ?>
                <div class="section-stack">
                    <div class="section-subheading">
                        <i class="<?php echo $categoryIcon; ?>"></i>
                        <?php echo htmlspecialchars($categoryLabel); ?>
                    </div>
                    <p class="section-subtitle"><?php echo htmlspecialchars($categoryDescription); ?></p>
                    <div class="form-row">
                        <?php foreach ($settingsList as $setting): ?>
                        <?php
                            $settingKey = $setting['setting_key'];
                            $settingValue = $setting['value'] ?? '';
                            $label = settings_format_label($settingKey, $setting['label'] ?? $setting['name'] ?? null);
                        ?>
                        <div class="form-group">
                            <label class="form-label"><?php echo htmlspecialchars($label); ?></label>
                            <?php if (settings_should_use_textarea($settingKey, $settingValue)): ?>
                            <textarea name="setting[<?php echo htmlspecialchars($settingKey); ?>]" class="form-control" rows="3"><?php echo htmlspecialchars($settingValue); ?></textarea>
                            <?php else: ?>
                            <input type="text" name="setting[<?php echo htmlspecialchars($settingKey); ?>]" class="form-control" value="<?php echo htmlspecialchars($settingValue); ?>">
                            <?php endif; ?>
                            <?php if (!empty($setting['description'])): ?>
                            <span class="form-note"><?php echo htmlspecialchars($setting['description']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="section-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo ui_text('button.save_settings'); ?>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <div>
                <h2 class="section-title"><i class="fas fa-info-circle"></i> <?php echo ui_text('system.section_title'); ?></h2>
                <p class="section-subtitle">ข้อมูลระบบและเซิร์ฟเวอร์ปัจจุบัน</p>
            </div>
        </div>
        <div class="section-body">
            <div class="stats-grid">
                <?php foreach ($systemInfoCards as $card): ?>
                <div class="stat-card">
                    <div class="stat-icon <?php echo htmlspecialchars($card['gradient']); ?>">
                        <i class="<?php echo htmlspecialchars($card['icon']); ?>"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($card['value']); ?></h3>
                        <p><?php echo htmlspecialchars($card['label']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>
<?php include_once __DIR__ . '/../includes/footer.php'; ?> 
