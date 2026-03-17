<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; }
$db = getDB();
$isAdmin = $_SESSION['role'] === 'admin';

// โ”€โ”€ Filter โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;

// โ…เน Prepared Statements เนเธ เธชเธณเธซเธฃเธฑเธ year เนเธฅเธฐ month (cast เน€เธเนเธ int เนเธฅเนเธง)
$yearSafe = (int)$year;
$monthSafe = $month > 0 ? (int)$month : 0;

// โ”€โ”€ Export Excel โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $monthFilter = $month ? " AND MONTH(r.repair_date) = $month" : '';
    $exportRows = $db->query("
        SELECT r.repair_date, a.asset_tag, a.asset_name, a.asset_type,
               r.problem_desc, r.repair_cost, r.technician, r.vendor,
               IF(r.warranty_claim=1,'เนเธเน','เนเธกเน') as warranty_claim,
               r.status
        FROM asset_repairs r
        JOIN assets a ON r.asset_id = a.asset_id
        WHERE YEAR(r.repair_date) = $year $monthFilter
        ORDER BY r.repair_date DESC
    ")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="repair_report_' . $year . ($month ? "_$month" : '') . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr style="background:#2d6a4f;color:#fff;font-weight:bold;">
        <th>เธงเธฑเธเธ—เธตเนเธเนเธญเธก</th><th>Asset Tag</th><th>เธเธทเนเธญเธญเธธเธเธเธฃเธ“เน</th><th>เธเธฃเธฐเน€เธ เธ—</th>
        <th>เธฃเธฒเธขเธฅเธฐเน€เธญเธตเธขเธ”เธเธฑเธเธซเธฒ</th><th>เธเนเธฒเธเนเธญเธก (เธฟ)</th><th>เธเนเธฒเธเน€เธ—เธเธเธดเธ</th>
        <th>เธเธนเนเธฃเธฑเธเธเนเธฒเธ</th><th>เน€เธเธดเธเธเธฃเธฐเธเธฑเธ</th><th>เธชเธ–เธฒเธเธฐ</th>
    </tr>';
    foreach ($exportRows as $r) {
        $e = fn($v) => htmlspecialchars($v ?? '', ENT_QUOTES);
        echo "<tr>
            <td>{$e($r['repair_date'])}</td><td>{$e($r['asset_tag'])}</td>
            <td>{$e($r['asset_name'])}</td><td>{$e($r['asset_type'])}</td>
            <td>{$e($r['problem_desc'])}</td><td>{$e($r['repair_cost'])}</td>
            <td>{$e($r['technician'])}</td><td>{$e($r['vendor'])}</td>
            <td>{$e($r['warranty_claim'])}</td><td>{$e($r['status'])}</td>
        </tr>";
    }
    echo '</table></body></html>';
    exit;
}

// โ”€โ”€ 1. เธเนเธฒเธเนเธญเธกเธฃเธฒเธขเน€เธ”เธทเธญเธ (เธเธตเธ—เธตเนเน€เธฅเธทเธญเธ) โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
$monthlyRepairs = [];
for ($m = 1; $m <= 12; $m++) {
    // โ… เนเธเน Prepared Statements เธซเธฃเธทเธญ Cast เน€เธเนเธ int เนเธฅเนเธง
    $stmt = $db->prepare("SELECT COALESCE(SUM(repair_cost),0) as total, COUNT(*) as cnt FROM asset_repairs WHERE YEAR(repair_date)=? AND MONTH(repair_date)=?");
    $stmt->bind_param('ii', $yearSafe, $m);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $monthlyRepairs[$m] = $r;
}

// โ”€โ”€ 2. Top Asset เธเนเธฒเธเนเธญเธกเธชเธนเธเธชเธธเธ” โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
$stmt = $db->prepare("
    SELECT a.asset_name, a.asset_tag, a.asset_type,
           COUNT(r.repair_id) as repair_count,
           SUM(r.repair_cost) as total_cost
    FROM asset_repairs r
    JOIN assets a ON r.asset_id = a.asset_id
    WHERE YEAR(r.repair_date) = ?
    GROUP BY r.asset_id
    ORDER BY total_cost DESC LIMIT 10
");
$stmt->bind_param('i', $yearSafe);
$stmt->execute();
$topRepairAssets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// โ”€โ”€ 3. เธเนเธฒเธเนเธญเธกเธ•เธฒเธกเธเธฃเธฐเน€เธ เธ—เธญเธธเธเธเธฃเธ“เน โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
$stmt = $db->prepare("
    SELECT a.asset_type, COUNT(r.repair_id) as cnt, SUM(r.repair_cost) as total
    FROM asset_repairs r
    JOIN assets a ON r.asset_id = a.asset_id
    WHERE YEAR(r.repair_date) = ?
    GROUP BY a.asset_type ORDER BY total DESC
");
$stmt->bind_param('i', $yearSafe);
$stmt->execute();
$repairByType = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// โ”€โ”€ 4. เธฃเธฒเธขเธเธฒเธฃเธเนเธญเธกเธ—เธฑเนเธเธซเธกเธ” (filter) โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
if ($monthSafe > 0) {
    $stmt = $db->prepare("SELECT r.*, a.asset_name, a.asset_tag, a.asset_type
              FROM asset_repairs r JOIN assets a ON r.asset_id = a.asset_id
              WHERE YEAR(r.repair_date) = ? AND MONTH(r.repair_date) = ?
              ORDER BY r.repair_date DESC");
    $stmt->bind_param('ii', $yearSafe, $monthSafe);
} else {
    $stmt = $db->prepare("SELECT r.*, a.asset_name, a.asset_tag, a.asset_type
              FROM asset_repairs r JOIN assets a ON r.asset_id = a.asset_id
              WHERE YEAR(r.repair_date) = ?
              ORDER BY r.repair_date DESC");
    $stmt->bind_param('i', $yearSafe);
}
$stmt->execute();
$allRepairs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// โ”€โ”€ 5. Asset Depreciation Summary โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
$depAssets = $db->query("
    SELECT asset_id, asset_name, asset_tag, asset_type,
           purchase_price, purchase_date, useful_life_years, salvage_value
    FROM assets
    WHERE purchase_price IS NOT NULL AND purchase_price > 0 AND purchase_date IS NOT NULL
    ORDER BY purchase_price DESC
")->fetch_all(MYSQLI_ASSOC);

// โ”€โ”€ 6. เธชเธฃเธธเธเธขเธญเธ”เธฃเธงเธก โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
$yearTotal = array_sum(array_column($monthlyRepairs, 'total'));
$yearCount = array_sum(array_column($monthlyRepairs, 'cnt'));
// โ… เนเธเน Prepared Statements
$stmt = $db->prepare("SELECT COUNT(*) as c FROM asset_repairs WHERE warranty_claim=1 AND YEAR(repair_date)=?");
$stmt->bind_param('i', $yearSafe);
$stmt->execute();
$warrantyCount = $stmt->get_result()->fetch_assoc()['c'];
$borrowActive  = $db->query("SELECT COUNT(*) as c FROM asset_borrows WHERE status='borrowed'")->fetch_assoc()['c'];

$monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$pageTitle = ui_text('page.title.assets_reports');
$activePage = 'assetsreports';
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <div class="breadcrumb-nav">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="dashboard.php"><i class="fas fa-home"></i> <?php echo ui_text('nav.dashboard'); ?></a>
            </li>
            <li class="breadcrumb-separator">&rsaquo;</li>
            <li class="breadcrumb-item active">
                <i class="fas fa-chart-line"></i> <?php echo ui_text('page.title.assets_reports'); ?>
            </li>
        </ol>
        <div class="page-actions">
            <button type="button" class="btn btn-secondary" onclick="printReport()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="page-header">
        <div>
            <h1><i class="fas fa-chart-line"></i> <?php echo ui_text('page.title.assets_reports'); ?></h1>
            <p class="page-subtitle"><?php echo ui_text('page.subtitle.assets_reports'); ?></p>
        </div>
    </div>

    <div class="stats-grid" aria-label="Summary metrics">
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#667eea,#764ba2);">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($yearTotal, 0); ?></h3>
                <p><?php echo ui_text('assetsreports.stats.total_cost'); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#4299e1,#0ea5e9);">
                <i class="fas fa-tools"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($yearCount); ?></h3>
                <p><?php echo ui_text('assetsreports.stats.total_repairs'); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#38a169,#16a34a);">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($warrantyCount); ?></h3>
                <p><?php echo ui_text('assetsreports.stats.warranty_claims'); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
                <i class="fas fa-hand-holding"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($borrowActive); ?></h3>
                <p><?php echo ui_text('assetsreports.stats.borrowed_assets'); ?></p>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="section-header">
            <div>
                <h2 class="section-title"><i class="fas fa-filter"></i> <?php echo ui_text('assetsreports.section.filters'); ?></h2>
                <p class="section-subtitle"><?php echo ui_text('assetsreports.section.filters.subtitle'); ?></p>
            </div>
        </div>
        <div class="section-body">
            <form method="GET">
                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label class="form-label" for="filter_year"><?php echo ui_text('assetsreports.filter.year_label'); ?></label>
                        <select id="filter_year" name="year" class="form-control">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label class="form-label" for="filter_month"><?php echo ui_text('assetsreports.filter.month_label'); ?></label>
                        <select id="filter_month" name="month" class="form-control">
                            <option value="0"><?php echo ui_text('assetsreports.filter.month_label'); ?></option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= $monthNames[$m] ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="align-self:flex-end;">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-filter"></i> <?php echo ui_text('assetsreports.filter.apply'); ?>
                        </button>
                    </div>
                    <div class="form-group" style="align-self:flex-end;">
                        <a href="?export=excel&year=<?= $year ?>&month=<?= $month ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-file-excel"></i> <?php echo ui_text('button.export_excel'); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <div class="charts-grid">
        <div class="chart-card">
            <h3 class="chart-title"><i class="fas fa-chart-bar"></i> <?php echo sprintf(ui_text('assetsreports.charts.monthly'), $year); ?></h3>
            <div class="chart-container"><canvas id="monthlyChart" role="img" aria-label="Monthly repair spend"></canvas></div>
        </div>
        <div class="chart-card">
            <h3 class="chart-title"><i class="fas fa-chart-pie"></i> <?php echo ui_text('assetsreports.charts.by_type'); ?></h3>
            <div class="chart-container"><canvas id="typeChart" role="img" aria-label="Repair spend by type"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-trophy"></i> <?php echo ui_text('assetsreports.section.top_assets'); ?> (<?= $year ?>)</div>
            <span class="meta-note"><?php echo count($topRepairAssets); ?> assets</span>
        </div>
        <div class="card-body">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th><?php echo ui_text('assetsreports.table.top_assets.asset'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.top_assets.type'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.top_assets.repairs'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.top_assets.cost'); ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($topRepairAssets)): ?>
                    <tr>
                        <td colspan="6" class="no-data">No repair history yet</td>
                    </tr>
                <?php else: ?>
                    <?php $maxCost = max(array_column($topRepairAssets, 'total_cost')) ?: 1; ?>
                    <?php foreach ($topRepairAssets as $i => $asset): ?>
                        <tr>
                            <td><strong><?= $i + 1 ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($asset['asset_name']) ?></strong><br>
                                <small class="meta-note"><?= htmlspecialchars($asset['asset_tag']) ?></small>
                            </td>
                            <td><span class="type-badge"><?= strtoupper($asset['asset_type']) ?></span></td>
                            <td><?= htmlspecialchars($asset['repair_count']) ?></td>
                            <td>
                                <strong style="color:#e53e3e;">฿<?= number_format($asset['total_cost'], 2) ?></strong>
                                <div class="dep-progress" style="margin-top:4px;">
                                    <div class="dep-bar-fill" style="width:<?= min(100, round($asset['total_cost'] / $maxCost * 100)); ?>%;"></div>
                                </div>
                            </td>
                            <td>
                                <a href="assetsdetail.php?id=<?= $asset['asset_id'] ?? '' ?>" class="btn btn-secondary" style="padding:6px 12px; font-size:0.85em;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-list"></i> <?php echo ui_text('assetsreports.section.all_repairs'); ?></div>
            <span class="meta-note"><?php echo count($allRepairs); ?> records • ฿<?= number_format(array_sum(array_column($allRepairs, 'repair_cost')), 2) ?></span>
        </div>
        <div class="card-body">
            <table>
                <thead>
                <tr>
                    <th><?php echo ui_text('assetsreports.table.repairs.date'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.repairs.asset'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.repairs.issue'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.repairs.tech_vendor'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.repairs.cost'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.repairs.warranty'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.repairs.status'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($allRepairs)): ?>
                    <tr>
                        <td colspan="7" class="no-data">No repairs found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allRepairs as $repair): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($repair['repair_date'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($repair['asset_name']) ?></strong><br>
                                <small class="meta-note"><?= htmlspecialchars($repair['asset_tag']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($repair['problem_desc']) ?></td>
                            <td>
                                <?= htmlspecialchars($repair['technician'] ?? '') ?>
                                <?php if (!empty($repair['vendor'])): ?>
                                    <br><small class="meta-note"><?= htmlspecialchars($repair['vendor']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:#e53e3e;">฿<?= number_format($repair['repair_cost'], 2) ?></strong>
                            </td>
                            <td>
                                <?php if ($repair['warranty_claim']): ?>
                                    <span class="badge badge-success">Claimed</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-info"><?= ucfirst($repair['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-line"></i> <?php echo ui_text('assetsreports.section.depreciation'); ?></div>
        </div>
        <div class="card-body">
            <table>
                <thead>
                <tr>
                    <th><?php echo ui_text('assetsreports.table.depreciation.asset'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.depreciation.purchase_price'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.depreciation.purchase_date'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.depreciation.useful_life'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.depreciation.annual'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.depreciation.ytd'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.depreciation.current_value'); ?></th>
                    <th><?php echo ui_text('assetsreports.table.depreciation.remaining'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($depAssets)): ?>
                    <tr>
                        <td colspan="8" class="no-data">No depreciation data available</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($depAssets as $asset):
                        $pp = (float)$asset['purchase_price'];
                        $sv = (float) ($asset['salvage_value'] ?? 0);
                        $ul = max((int) ($asset['useful_life_years'] ?? 5), 1);
                        $yd = ($pp - $sv) / $ul;
                        $yearsPassed = max(0, date('Y') - date('Y', strtotime($asset['purchase_date'] ?? 'now')));
                        $depreciated = min($yd * $yearsPassed, max(0, $pp - $sv));
                        $currentValue = max($pp - $depreciated, $sv);
                        $remainingPct = round(($currentValue / max($pp, 1)) * 100);
                    ?>
                        <tr>
                            <td>
                                <a href="assetsdetail.php?id=<?= $asset['asset_id'] ?? '' ?>" class="meta-note" style="font-weight:600; color:#1d4ed8;">
                                    <?= htmlspecialchars($asset['asset_name']) ?>
                                </a><br>
                                <small class="meta-note"><?= htmlspecialchars($asset['asset_tag']) ?></small>
                            </td>
                            <td>฿<?= number_format($pp, 2) ?></td>
                            <td><?= $asset['purchase_date'] ? date('d/m/Y', strtotime($asset['purchase_date'])) : '—' ?></td>
                            <td><?= $ul ?> yrs</td>
                            <td>฿<?= number_format($yd, 2) ?></td>
                            <td>฿<?= number_format($depreciated, 2) ?></td>
                            <td><strong>฿<?= number_format($currentValue, 2) ?></strong></td>
                            <td>
                                <div class="dep-progress" style="width:100%;">
                                    <div class="dep-bar-fill" style="width:<?= min(100, $remainingPct) ?>%;"></div>
                                </div>
                                <span class="meta-note"><?= $remainingPct ?>%</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<?php ob_start(); ?>
<script>
const monthLabels = [<?php echo implode(',', array_map(fn($m) => json_encode(substr($monthNames[$m], 0, 6)), range(1, 12))); ?>];
const monthData = [<?php echo implode(',', array_map(fn($m) => $monthlyRepairs[$m]['total'], array_keys($monthlyRepairs))); ?>];
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [{
            label: <?php echo json_encode(sprintf(ui_text('assetsreports.charts.monthly'), $year)); ?>,
            data: monthData,
            backgroundColor: 'rgba(16,206,48,0.7)',
            borderColor: '#10ce30',
            borderWidth: 2,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => '฿' + value.toLocaleString('en-US')
                }
            }
        }
    }
});
const typeLabels = [<?php echo implode(',', array_map(fn($row) => json_encode(strtoupper($row['asset_type'])), $repairByType)); ?>];
const typeData = [<?php echo implode(',', array_column($repairByType, 'total')); ?>];
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: typeLabels.length ? typeLabels : ['No data'],
        datasets: [{
            data: typeData.length ? typeData : [1],
            backgroundColor: ['#10ce30','#4299e1','#f6ad55','#fc8181','#9f7aea','#68d391','#63b3ed'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right' }
        }
    }
});
function printReport() {
    const dateStr = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    document.body.dataset.printDate = dateStr;
    window.print();
}
</script>
<?php $pageScripts = ob_get_clean(); ?>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
