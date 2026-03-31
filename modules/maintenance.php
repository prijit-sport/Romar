<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/ui_texts.php';

$pageTitle = ui_text('maintenance.page_title') . ' - Romar IT Support';
$activePage = 'maintenance';
$isAdmin = isAdmin();

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$db = getDB();
$message = ''; $messageType = '';

$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

// Stats
$stats = [
    'total_repairs' => $db->query("SELECT COUNT(*) c FROM asset_repairs")->fetch_assoc()['c'],
    'total_cost' => $db->query("SELECT COALESCE(SUM(repair_cost),0) c FROM asset_repairs")->fetch_assoc()['c'],
    'monthly_cost' => $db->query("SELECT COALESCE(SUM(repair_cost),0) c FROM asset_repairs WHERE YEAR(repair_date) = YEAR(NOW()) AND MONTH(repair_date) = MONTH(NOW())")->fetch_assoc()['c'],
    'open_repairs' => $db->query("SELECT COUNT(*) c FROM asset_repairs WHERE status IN ('in_progress', 'pending')")->fetch_assoc()['c'],
    'avg_cost' => $db->query("SELECT COALESCE(AVG(repair_cost),0) c FROM asset_repairs")->fetch_assoc()['c']
];

// Recent repairs with search/filter/pagination
$where = ['1=1'];
$params = [];
$types = '';

if ($search) {
    $where[] = "(a.asset_name LIKE ? OR a.asset_tag LIKE ? OR r.problem_desc LIKE ? OR r.technician LIKE ?)";
    $params = array_fill(0, 4, "%$search%");
    $types = 'ssss';
}

if ($status) {
    $where[] = 'r.status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($dateFrom) {
    $where[] = 'r.repair_date >= ?';
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo) {
    $where[] = 'r.repair_date <= ?';
    $params[] = $dateTo;
    $types .= 's';
}

$whereClause = implode(' AND ', $where);
$totalQuery = "SELECT COUNT(*) c FROM asset_repairs r JOIN assets a ON r.asset_id = a.asset_id WHERE $whereClause";
$totalStmt = $db->prepare($totalQuery);
if (!empty($params)) $totalStmt->bind_param($types, ...$params);
$totalStmt->execute();
$totalRepairs = $totalStmt->get_result()->fetch_assoc()['c'];

$repairsQuery = "SELECT r.*, a.asset_name, a.asset_tag, u.full_name as tech_name FROM asset_repairs r 
JOIN assets a ON r.asset_id = a.asset_id 
LEFT JOIN users u ON BINARY r.technician = BINARY u.full_name
WHERE $whereClause ORDER BY r.repair_date DESC LIMIT ? OFFSET ?";
$repairsStmt = $db->prepare($repairsQuery);
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';
$repairsStmt->bind_param($types, ...$params);
$repairsStmt->execute();
$repairs = $repairsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Assets for form
$assets = $db->query("SELECT asset_id, asset_name, asset_tag FROM assets WHERE status != 'disposed' ORDER BY asset_name")->fetch_all(MYSQLI_ASSOC);
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<main class="main-content">
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <header class="page-header">
        <div class="page-title">
            <h1><i class="fas fa-tools"></i> <?= ui_text('maintenance.page_title') ?></h1>
            <p class="page-subtitle"><?= ui_text('maintenance.subtitle') ?></p>
        </div>
        <div class="page-actions">
            <a href="../modules/assetsreports.php" class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i> รายงาน
            </a>
        </div>
    </header>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon gradient-blue">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-info">
                <h3>฿<?= number_format($stats['total_cost'], 2) ?></h3>
                <p><?= ui_text('maintenance.stats.total_cost') ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gradient-green">
                <i class="fas fa-tools"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($stats['total_repairs']) ?></h3>
                <p><?= ui_text('maintenance.stats.total_repairs') ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gradient-orange">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-info">
                <h3>฿<?= number_format($stats['monthly_cost'], 2) ?></h3>
                <p><?= ui_text('maintenance.stats.monthly_cost') ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gradient-red">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($stats['open_repairs']) ?></h3>
                <p><?= ui_text('maintenance.stats.open_repairs') ?></p>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <!-- New Repair Form Card -->
    <section class="section">
        <div class="card">
            <div class="card-toolbar">
                <h2 class="section-title">
                    <i class="fas fa-plus-circle"></i> <?= ui_text('maintenance.form.new_title') ?>
                </h2>
            </div>
            <form method="POST">
                <?= csrf_input(); ?>
                <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <div class="form-group">
                        <label><?= ui_text('assetsdetail.asset') ?> <span class="text-danger">*</span></label>
                        <select name="asset_id" required class="form-control">
                            <option value=""><?= ui_text('maintenance.form.select_asset') ?></option>
                            <?php foreach ($assets as $asset): ?>
                                <option value="<?= $asset['asset_id'] ?>"><?= htmlspecialchars($asset['asset_tag'] . ' - ' . $asset['asset_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= ui_text('maintenance.form.repair_date') ?> <span class="text-danger">*</span></label>
                        <input type="date" name="repair_date" required value="<?= date('Y-m-d') ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label><?= ui_text('maintenance.form.problem') ?> <span class="text-danger">*</span></label>
                        <input type="text" name="problem_desc" required placeholder="<?= ui_text('maintenance.form.problem_placeholder') ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label><?= ui_text('maintenance.form.status') ?></label>
                        <select name="status" class="form-control">
                            <option value="in_progress"><?= ui_text('maintenance.status.in_progress') ?></option>
                            <option value="completed"><?= ui_text('maintenance.status.completed') ?></option>
                            <option value="pending"><?= ui_text('maintenance.status.pending') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= ui_text('maintenance.form.technician') ?></label>
                        <input type="text" name="technician" class="form-control" placeholder="ชื่อช่างเทคนิค">
                    </div>
                    <div class="form-group">
                        <label><?= ui_text('maintenance.form.vendor') ?></label>
                        <input type="text" name="vendor" class="form-control" placeholder="ผู้ขาย / ร้านค้า">
                    </div>
                    <div class="form-group">
                        <label><?= ui_text('maintenance.form.cost') ?> (฿)</label>
                        <input type="number" name="repair_cost" step="0.01" min="0" class="form-control" placeholder="0.00">
                    </div>
                </div>
                <div style="margin-top: 1.5rem;">
                    <label class="checkbox-group">
                        <input type="checkbox" name="warranty_claim" id="warranty">
                        <span><?= ui_text('maintenance.form.warranty_claim') ?></span>
                    </label>
                </div>
                <div class="toolbar-actions">
                    <button type="reset" class="btn btn-secondary"><i class="fas fa-undo"></i> ล้าง</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= ui_text('maintenance.form.submit') ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <!-- Filters Card -->
    <section class="section">
        <div class="card">
            <div class="card-toolbar">
                <h2 class="section-title">
                    <i class="fas fa-filter"></i> ตัวกรอง (<?= number_format($totalRepairs) ?> รายการ)
                </h2>
            </div>
            <form method="GET">
                <div class="filter-bar">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label class="visually-hidden"><?= ui_text('maintenance.search.placeholder') ?></label>
                            <input type="text" name="search" placeholder="<?= ui_text('maintenance.search.placeholder') ?>" value="<?= htmlspecialchars($search) ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="visually-hidden"><?= ui_text('maintenance.search.status') ?></label>
                            <select name="status" class="form-control">
                                <option value="">ทุกสถานะ</option>
                                <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>><?= ui_text('maintenance.status.in_progress') ?></option>
                                <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>><?= ui_text('maintenance.status.completed') ?></option>
                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>><?= ui_text('maintenance.status.pending') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="visually-hidden"><?= ui_text('maintenance.search.date_from') ?></label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="visually-hidden"><?= ui_text('maintenance.search.date_to') ?></label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="form-control">
                        </div>
                    </div>
                    <div class="filter-footer">
                        <a href="?" class="btn btn-secondary"><i class="fas fa-times"></i> <?= ui_text('maintenance.search.filter_clear') ?></a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> ใช้ตัวกรอง</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Repairs Table Card -->
    <section class="section">
        <div class="card">
            <div class="card-toolbar">
                <h2 class="section-title">
                    <i class="fas fa-list"></i> รายการซ่อมทั้งหมด
                </h2>
                <div class="toolbar-actions">
                    <?php if ($isAdmin): ?>
                    <button onclick="exportCSV()" class="btn btn-success btn-sm">
                        <i class="fas fa-download"></i> ส่งออก CSV
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-formal">
                    <thead>
                        <tr>
                            <th>ทรัพย์สิน</th>
                            <th>วันที่</th>
                            <th>ปัญหา</th>
                            <th>สถานะ</th>
                            <th>ค่าใช้จ่าย</th>
                            <th>ช่าง</th>
                            <?php if ($isAdmin): ?><th>ดำเนินการ</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($repairs)): ?>
                            <tr>
                                <td colspan="<?= $isAdmin ? 7 : 6 ?>" class="text-center py-5">
                                    <i class="fas fa-search fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted mb-0"><?= ui_text('maintenance.no_results') ?></p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($repairs as $repair): ?>
                                <tr class="hover-row">
                                    <td>
                                        <strong><?= htmlspecialchars($repair['asset_tag'] ?? '') ?> - <?= htmlspecialchars($repair['asset_name']) ?></strong>
                                    </td>
                                    <td><time><?= date('d/m/Y', strtotime($repair['repair_date'])) ?></time></td>
                                    <td title="<?= htmlspecialchars($repair['problem_desc']) ?>">
                                        <?= htmlspecialchars(substr($repair['problem_desc'], 0, 60)) ?><?= strlen($repair['problem_desc']) > 60 ? '...' : '' ?>
                                    </td>
                                    <td>
                                        <span class="badge status-badge status-<?= $repair['status'] ?>">
                                            <?= ucfirst(ui_text('maintenance.status.' . $repair['status']) ?: $repair['status']) ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-danger">฿<?= number_format($repair['repair_cost'], 2) ?></td>
                                    <td><?= htmlspecialchars($repair['technician'] ?: $repair['tech_name']) ?></td>
                                    <?php if ($isAdmin): ?>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-secondary" title="แก้ไข" onclick="editRepair(<?= $repair['repair_id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" title="ลบ" onclick="deleteConfirm(<?= $repair['repair_id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalRepairs > $perPage): 
                $totalPages = ceil($totalRepairs / $perPage);
            ?>
            <div class="d-flex justify-content-center mt-4">
                <nav aria-label="Pagination">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <li class="page-item active">
                            <span class="page-link"><?= ui_text('maintenance.pagination.page', ['current' => $page, 'total' => $totalPages]) ?></span>
                        </li>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
// Delete confirm
function deleteConfirm(id) {
    if (confirm('<?= ui_text("maintenance.confirm.delete") ?>')) {
        window.location.href = `delete_repair.php?id=${id}`;
    }
}

// Edit modal (placeholder - implement as needed)
function editRepair(id) {
    alert('แก้ไขการซ่อม ID: ' + id);
}

// Export CSV
function exportCSV() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '?'+params.toString();
}
</script>

<?php include '../includes/footer.php'; ?>

