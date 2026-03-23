<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$isAdmin = $_SESSION['role'] === 'admin';

// Get Date Range
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// ===== Export Excel =====
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $exportSQL = "SELECT t.ticket_id, t.title, t.category, t.priority, t.status,
                         t.created_at, t.resolved_at,
                         u2.full_name as assigned_to_name,
                         t.description, t.resolution_notes
                  FROM tickets t
                  LEFT JOIN users u2 ON t.assigned_to = u2.user_id
                  WHERE DATE(t.created_at) BETWEEN ? AND ?
                  ORDER BY t.created_at DESC";
    $stmt = $db->prepare($exportSQL);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $exportRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="tickets_' . $startDate . '_' . $endDate . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr style="background:#2b6cb0;color:#fff;font-weight:bold;">
<th>Ticket ID</th><th>หัวข้อ</th><th>หมวดหมู่ปัญหา</th><th>Priority</th>
        <th>สถานะ</th><th>มอบหมายให้</th><th>วันที่สร้าง</th>
        <th>วันที่แก้ไข</th><th>รายละเอียดปัญหา</th><th>วิธีแก้ไข</th>
    </tr>';
    foreach ($exportRows as $r) {
        $e = fn($v) => htmlspecialchars($v ?? '', ENT_QUOTES);
        echo "<tr>
            <td>{$e($r['ticket_id'])}</td><td>{$e($r['title'])}</td>
            <td>{$e($r['category'])}</td><td>{$e($r['priority'])}</td>
            <td>{$e($r['status'])}</td><td>{$e($r['assigned_to_name'])}</td>
            <td>{$e($r['created_at'])}</td><td>{$e($r['resolved_at'])}</td>
            <td>{$e($r['description'])}</td><td>{$e($r['resolution_notes'])}</td>
        </tr>";
    }
    echo '</table></body></html>';
    exit;
}

// Summary Statistics
$summarySQL = "SELECT 
    COUNT(*) as total_tickets,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_tickets,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_tickets,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_tickets,
    SUM(CASE WHEN sla_due_date < NOW() AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as overdue_tickets,
    AVG(CASE 
        WHEN resolved_at IS NOT NULL AND created_at IS NOT NULL 
        THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) 
        ELSE NULL 
    END) as avg_resolution_time
    FROM tickets 
    WHERE DATE(created_at) BETWEEN ? AND ?";

$stmt = $db->prepare($summarySQL);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Tickets by Category
$categorySQL = "SELECT category, COUNT(*) as count 
                FROM tickets 
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY category 
                ORDER BY count DESC";
$stmt = $db->prepare($categorySQL);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$byCategory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Tickets by Priority
$prioritySQL = "SELECT priority, COUNT(*) as count 
                FROM tickets 
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY priority 
                ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low')";
$stmt = $db->prepare($prioritySQL);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$byPriority = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Tickets by Status
$statusSQL = "SELECT status, COUNT(*) as count 
              FROM tickets 
              WHERE DATE(created_at) BETWEEN ? AND ?
              GROUP BY status 
              ORDER BY count DESC";
$stmt = $db->prepare($statusSQL);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$byStatus = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Staff Performance (Top 5)
$staffSQL = "SELECT 
             u.full_name,
             COUNT(t.ticket_id) as assigned_tickets,
             SUM(CASE WHEN t.status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
             SUM(CASE WHEN t.status = 'closed' THEN 1 ELSE 0 END) as closed_tickets,
             AVG(CASE 
                 WHEN t.resolved_at IS NOT NULL AND t.created_at IS NOT NULL 
                 THEN TIMESTAMPDIFF(HOUR, t.created_at, t.resolved_at) 
                 ELSE NULL 
             END) as avg_resolution_time
             FROM users u
             LEFT JOIN tickets t ON u.user_id = t.assigned_to 
             WHERE t.created_at BETWEEN ? AND ?
             GROUP BY u.user_id, u.full_name
             HAVING assigned_tickets > 0
             ORDER BY resolved_tickets DESC
             LIMIT 5";
$stmt = $db->prepare($staffSQL);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$staffPerformance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Daily Ticket Trend (Last 30 days)
$trendSQL = "SELECT 
             DATE(created_at) as date,
             COUNT(*) as count
             FROM tickets
             WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date DESC
             LIMIT 30";
$ticketTrend = $db->query($trendSQL)->fetch_all(MYSQLI_ASSOC);

// SLA Compliance
$slaSQL = "SELECT 
           COUNT(*) as total,
           SUM(CASE WHEN sla_due_date >= resolved_at OR resolved_at IS NULL THEN 1 ELSE 0 END) as within_sla,
           SUM(CASE WHEN sla_due_date < resolved_at THEN 1 ELSE 0 END) as breached_sla
           FROM tickets
           WHERE DATE(created_at) BETWEEN ? AND ? AND sla_due_date IS NOT NULL";
$stmt = $db->prepare($slaSQL);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$slaCompliance = $stmt->get_result()->fetch_assoc();

// Calculate SLA Compliance Percentage
$slaPercentage = 0;
if ($slaCompliance['total'] > 0) {
    $slaPercentage = round(($slaCompliance['within_sla'] / $slaCompliance['total']) * 100, 2);
}

$trendLineData = array_reverse($ticketTrend);
$avgResolution = $summary['avg_resolution_time'] ?? null;
$avgResolutionDisplay = $avgResolution ? number_format($avgResolution, 1) . ' ชั่วโมง' : '-';
$baseParams = array_filter([
    'start_date' => $startDate,
    'end_date' => $endDate,
    'report_type' => $reportType,
], fn($value) => $value !== '');
$exportUrl = 'reports.php?' . http_build_query(array_merge($baseParams, ['export' => 'excel']));
$reportSubtitles = [
    'summary' => ui_text('reports.subtitle.summary'),
    'category' => ui_text('reports.subtitle.category'),
    'priority' => ui_text('reports.subtitle.priority'),
    'sla' => ui_text('reports.subtitle.sla'),
];
$reportSubtitle = $reportSubtitles[$reportType] ?? ui_text('reports.subtitle.default');
$withinSLA = (int)($slaCompliance['within_sla'] ?? 0);
$breachedSLA = (int)($slaCompliance['breached_sla'] ?? 0);
$totalSLA = (int)($slaCompliance['total'] ?? 0);
$urgentCount = (int)($summary['urgent_tickets'] ?? 0);
$highCount = (int)($summary['high_tickets'] ?? 0);
$overdueCount = (int)($summary['overdue_tickets'] ?? 0);
$pageTitle = ui_text('page.title.reports');
$activePage = 'reports';
$reportPayload = json_encode([
    'categoryData' => $byCategory,
    'priorityData' => $byPriority,
    'statusData' => $byStatus,
    'trendLineData' => $trendLineData,
], JSON_UNESCAPED_UNICODE);
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content" data-report-payload="<?php echo htmlspecialchars($reportPayload, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="breadcrumb-nav">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="../admin/dashboard.php">
                    <i class="fas fa-home"></i> <?php echo ui_text('nav.dashboard'); ?>
                </a>
            </li>
            <li class="breadcrumb-separator">&rsaquo;</li>
            <li class="breadcrumb-item active">
                <i class="fas fa-chart-line"></i> <?php echo ui_text('page.title.reports'); ?>
            </li>
        </ol>
    </div>

    <div class="page-header">
        <div>
            <h1><i class="fas fa-chart-line"></i> <?php echo ui_text('page.title.reports'); ?></h1>
            <p class="section-subtitle"><?php echo htmlspecialchars($reportSubtitle); ?></p>
        </div>
        <div class="page-actions">
            <a class="btn btn-secondary" href="<?php echo htmlspecialchars($exportUrl); ?>">
                <i class="fas fa-file-export"></i> <?php echo ui_text('button.export_excel'); ?>
            </a>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET" action="reports.php">
            <div class="filter-grid">
                <div class="form-group">
                    <label class="form-label" for="start_date"><?php echo ui_text('filter.label.start_date'); ?></label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="end_date"><?php echo ui_text('filter.label.end_date'); ?></label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="report_type"><?php echo ui_text('filter.label.report_type'); ?></label>
                    <select name="report_type" id="report_type" class="form-control">
                        <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>><?php echo ui_text('report_type.summary'); ?></option>
                        <option value="category" <?php echo $reportType === 'category' ? 'selected' : ''; ?>><?php echo ui_text('report_type.category'); ?></option>
                        <option value="priority" <?php echo $reportType === 'priority' ? 'selected' : ''; ?>><?php echo ui_text('report_type.priority'); ?></option>
                        <option value="sla" <?php echo $reportType === 'sla' ? 'selected' : ''; ?>><?php echo ui_text('report_type.sla'); ?></option>
                    </select>
                </div>
                <div class="form-group" style="align-self:flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> <?php echo ui_text('button.search'); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <section class="section">
        <div class="section-body">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #475569, #312e81);">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($summary['total_tickets'] ?? 0); ?></h3>
                        <p><?php echo ui_text('status.total_tickets'); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #60a5fa, #2563eb);">
                        <i class="fas fa-circle-plus"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($summary['new_tickets'] ?? 0); ?></h3>
                        <p><?php echo ui_text('status.new_tickets'); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #0ea5e9, #0284c7);">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($summary['in_progress_tickets'] ?? 0); ?></h3>
                        <p><?php echo ui_text('status.in_progress_tickets'); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #14b8a6, #047857);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($summary['resolved_tickets'] ?? 0); ?></h3>
                        <p><?php echo ui_text('status.resolved_tickets'); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #6d28d9, #a855f7);">
                        <i class="fas fa-door-closed"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($summary['closed_tickets'] ?? 0); ?></h3>
                        <p><?php echo ui_text('status.closed_tickets'); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444, #b91c1c);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($overdueCount); ?></h3>
                        <p><?php echo ui_text('status.overdue_tickets'); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f97316, #ea580c);">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($highCount); ?></h3>
                        <p><?php echo ui_text('status.high_priority'); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #facc15, #f97316);">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($urgentCount); ?></h3>
                        <p><?php echo ui_text('status.urgent_priority'); ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4ade80, #16a34a);">
                        <i class="fas fa-stopwatch"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($avgResolutionDisplay); ?></h3>
                        <p><?php echo ui_text('status.avg_resolution_time'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <div>
                <h2 class="section-title"><i class="fas fa-chart-pie"></i> แยกแท็กและสถานะ</h2>
                <p class="section-subtitle">วิเคราะห์หมวดหมู่ สถานะ และความเร่งด่วนของตั๋ว</p>
            </div>
        </div>
        <div class="section-body">
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-folder"></i> หมวดหมู่</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="categoryChart" height="200"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-flag"></i> Priority</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="priorityChart" height="200"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="card-header">
                        <span class="card-title"><i class="fas fa-clipboard-list"></i> Status</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-header">
            <div>
                <h2 class="section-title"><i class="fas fa-users"></i> Staff Performance</h2>
                <p class="section-subtitle">Top 5 ผู้รับผิดชอบตั๋ว</p>
            </div>
        </div>
        <div class="section-body">
            <?php if (!empty($staffPerformance)): ?>
            <div class="card">
                <div class="card-header">
                    <strong>Top Performers</strong>
                    <span class="meta-note">จัดลำดับโดยตั๋วที่ Resolve มากที่สุด</span>
                </div>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Assigned</th>
                                <th>Resolved</th>
                                <th>Closed</th>
                                <th>Avg Resolution (hrs)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffPerformance as $staff): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($staff['full_name'] ?? '-'); ?></td>
                                <td><?php echo number_format($staff['assigned_tickets'] ?? 0); ?></td>
                                <td><?php echo number_format($staff['resolved_tickets'] ?? 0); ?></td>
                                <td><?php echo number_format($staff['closed_tickets'] ?? 0); ?></td>
                                <td>
                                    <?php
                                    if (!empty($staff['avg_resolution_time'])) {
                                        echo number_format($staff['avg_resolution_time'], 1);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>ยังไม่มีข้อมูลเจ้าหน้าที่</h3>
                <p>เพิ่มการมอบหมายตั๋วเพื่อให้ข้อมูลนี้แสดงผล</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section">
        <div class="section-body">
            <div class="grid grid-cols-2 gap-4">
                <div class="chart-card">
                    <div class="card-header">
                        <strong>Trend รายวัน (30 วันล่าสุด)</strong>
                    </div>
                    <div class="chart-container">
                        <canvas id="trendChart" height="260"></canvas>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <strong>SLA Compliance</strong>
                    </div>
                    <div class="section-stack">
                        <div>
                            <div class="section-subtitle">ตั๋วที่รายงาน SLA ทั้งหมด <?php echo number_format($totalSLA); ?></div>
                            <div style="font-size: 2.6rem; font-weight: 700;"><?php echo number_format($slaPercentage, 1); ?>%</div>
                            <div class="meta-note">
                                Within SLA: <?php echo number_format($withinSLA); ?> &bull;
                                Breached: <?php echo number_format($breachedSLA); ?>
                            </div>
                            <div style="background: #e2e8f0; border-radius: 12px; height: 10px; overflow: hidden; margin-top: 12px;">
                                <div style="height: 100%; width: <?php echo max(0, min($slaPercentage, 100)); ?>%; background: linear-gradient(135deg, #22c55e, #059669);"></div>
                            </div>
                            <p class="meta-note" style="margin-top: 0.75rem;">ช่วงเวลาที่เลือก: <?php echo htmlspecialchars($startDate); ?> ถึง <?php echo htmlspecialchars($endDate); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
 </main>
<?php $pageScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script><script src="' . BASE_URL . 'assets/js/reports.js"></script>'; ?>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
