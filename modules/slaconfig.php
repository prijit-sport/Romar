<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$message = '';
$messageType = '';

csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = 'การยืนยัน CSRF ล้มเหลว';
        $messageType = 'error';
    } else {
        $slaId = (int) ($_POST['sla_id'] ?? 0);
        $responseTime = max(1, (int) ($_POST['response_time_hours'] ?? 0));
        $resolutionTime = max(1, (int) ($_POST['resolution_time_hours'] ?? 0));

        $stmt = $db->prepare("UPDATE sla_rules SET response_time_hours = ?, resolution_time_hours = ? WHERE sla_id = ?");
        $stmt->bind_param('iii', $responseTime, $resolutionTime, $slaId);

        if ($stmt->execute()) {
            $message = 'บันทึก SLA เรียบร้อยแล้ว';
            $messageType = 'success';
            logActivity($_SESSION['user_id'], 'อัปเดต SLA', 'SLA', "SLA ID: {$slaId}");
        } else {
            $message = 'การอัปเดต SLA ล้มเหลว: ' . $stmt->error;
            $messageType = 'error';
        }
    }
}

$slaRulesResult = $db->query("SELECT * FROM sla_rules ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low'), FIELD(impact, 'critical', 'high', 'medium', 'low')");
$slaRules = $slaRulesResult ? $slaRulesResult->fetch_all(MYSQLI_ASSOC) : [];

$priorityLabels = [
    'urgent' => 'ด่วนที่สุด',
    'high' => 'สำคัญ',
    'normal' => 'ปกติ',
    'low' => 'ต่ำ',
];
$impactLabels = [
    'critical' => 'วิกฤต',
    'high' => 'สูง',
    'medium' => 'ปานกลาง',
    'low' => 'ต่ำ',
];
$priorityColors = [
    'urgent' => ['bg' => '#fee2e2', 'color' => '#742a2a'],
    'high' => ['bg' => '#fef3c7', 'color' => '#7c2d12'],
    'normal' => ['bg' => '#bfdbfe', 'color' => '#1d4ed8'],
    'low' => ['bg' => '#dcfce7', 'color' => '#166534'],
];

$pageTitle = 'การตั้งค่า SLA';
$activePage = 'slaconfig';
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <div class="breadcrumb-nav">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="../admin/dashboard.php"><i class="fas fa-home"></i> Romar Dashboard</a>
            </li>
            <li class="breadcrumb-separator">›</li>
            <li class="breadcrumb-item active"><i class="fas fa-stopwatch"></i> SLA Configuration</li>
        </ol>
        <a href="../admin/dashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            กลับไปยัง Romar Dashboard
        </a>
    </div>

    <div class="page-header">
        <div>
            <h1><i class="fas fa-stopwatch"></i> SLA Configuration</h1>
            <p class="page-subtitle">กำหนดเวลาในการตอบสนองและการแก้ไขให้สอดรับกับระดับความสำคัญของตั๋ว</p>
        </div>
    </div>

    <section class="section">
        <div class="section-header">
            <div>
                <h2 class="section-title"><i class="fas fa-bolt"></i> กฎ SLA</h2>
                <p class="section-subtitle">ปรับเวลาและมาตรฐาน SLA ตามระดับความรุนแรงของเหตุการณ์</p>
            </div>
        </div>
        <div class="section-body">
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> show">
                <i class="fas fa-info-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="section-grid">
                <?php foreach ($slaRules as $rule): ?>
                <?php
                    $priorityKey = $rule['priority'];
                    $impactKey = $rule['impact'];
                    $priorityLabel = $priorityLabels[$priorityKey] ?? ucwords($priorityKey);
                    $impactLabel = $impactLabels[$impactKey] ?? ucwords($impactKey);
                    $color = $priorityColors[$priorityKey] ?? ['bg' => '#e2e8f0', 'color' => '#1f2937'];
                ?>
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">
                                <i class="fas fa-stopwatch"></i>
                                <?php echo htmlspecialchars($priorityLabel); ?>
                            </h3>
                            <span class="meta-note">Impact: <?php echo htmlspecialchars($impactLabel); ?></span>
                        </div>
                        <span class="type-badge" style="background: <?php echo $color['bg']; ?>; color: <?php echo $color['color']; ?>;">
                            <?php echo htmlspecialchars($priorityLabel); ?>
                        </span>
                    </div>

                    <form method="POST">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="sla_id" value="<?php echo (int) $rule['sla_id']; ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Response Time (ชั่วโมง)</label>
                                <input type="number" name="response_time_hours" min="1" class="form-control" value="<?php echo htmlspecialchars($rule['response_time_hours']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Resolution Time (ชั่วโมง)</label>
                                <input type="number" name="resolution_time_hours" min="1" class="form-control" value="<?php echo htmlspecialchars($rule['resolution_time_hours']); ?>">
                            </div>
                        </div>

                        <div class="ticket-actions">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-save"></i> บันทึก SLA
                            </button>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
