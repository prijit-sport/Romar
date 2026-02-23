<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; }
$db = getDB();
$isAdmin = $_SESSION['role'] === 'admin';

// ── Filter ────────────────────────────────────────────────────
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;

// ── 1. ค่าซ่อมรายเดือน (ปีที่เลือก) ─────────────────────────
$monthlyRepairs = [];
for ($m = 1; $m <= 12; $m++) {
    $r = $db->query("SELECT COALESCE(SUM(repair_cost),0) as total, COUNT(*) as cnt
                     FROM asset_repairs
                     WHERE YEAR(repair_date)=$year AND MONTH(repair_date)=$m")->fetch_assoc();
    $monthlyRepairs[$m] = $r;
}

// ── 2. Top Asset ค่าซ่อมสูงสุด ────────────────────────────────
$topRepairAssets = $db->query("
    SELECT a.asset_name, a.asset_tag, a.asset_type,
           COUNT(r.repair_id) as repair_count,
           SUM(r.repair_cost) as total_cost
    FROM asset_repairs r
    JOIN assets a ON r.asset_id = a.asset_id
    WHERE YEAR(r.repair_date) = $year
    GROUP BY r.asset_id
    ORDER BY total_cost DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── 3. ค่าซ่อมตามประเภทอุปกรณ์ ───────────────────────────────
$repairByType = $db->query("
    SELECT a.asset_type, COUNT(r.repair_id) as cnt, SUM(r.repair_cost) as total
    FROM asset_repairs r
    JOIN assets a ON r.asset_id = a.asset_id
    WHERE YEAR(r.repair_date) = $year
    GROUP BY a.asset_type ORDER BY total DESC
")->fetch_all(MYSQLI_ASSOC);

// ── 4. รายการซ่อมทั้งหมด (filter) ────────────────────────────
$repairSQL = "SELECT r.*, a.asset_name, a.asset_tag, a.asset_type
              FROM asset_repairs r JOIN assets a ON r.asset_id = a.asset_id
              WHERE YEAR(r.repair_date) = $year";
if ($month) $repairSQL .= " AND MONTH(r.repair_date) = $month";
$repairSQL .= " ORDER BY r.repair_date DESC";
$allRepairs = $db->query($repairSQL)->fetch_all(MYSQLI_ASSOC);

// ── 5. Asset Depreciation Summary ────────────────────────────
$depAssets = $db->query("
    SELECT asset_id, asset_name, asset_tag, asset_type,
           purchase_price, purchase_date, useful_life_years, salvage_value
    FROM assets
    WHERE purchase_price IS NOT NULL AND purchase_price > 0 AND purchase_date IS NOT NULL
    ORDER BY purchase_price DESC
")->fetch_all(MYSQLI_ASSOC);

// ── 6. สรุปยอดรวม ─────────────────────────────────────────────
$yearTotal = array_sum(array_column($monthlyRepairs, 'total'));
$yearCount = array_sum(array_column($monthlyRepairs, 'cnt'));
$warrantyCount = $db->query("SELECT COUNT(*) as c FROM asset_repairs WHERE warranty_claim=1 AND YEAR(repair_date)=$year")->fetch_assoc()['c'];
$borrowActive  = $db->query("SELECT COUNT(*) as c FROM asset_borrows WHERE status='borrowed'")->fetch_assoc()['c'];

$monthNames = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสินทรัพย์ IT - IT Support</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Sarabun',sans-serif; background:#065f159c; color:#000; min-height:100vh; }
        .container { display:flex; min-height:100vh; }
        .sidebar { width:280px; background:linear-gradient(180deg,#10ce30 0%,#000 100%); position:fixed; left:0; top:0; height:100vh; overflow-y:auto; box-shadow:4px 0 20px rgba(0,0,0,0.3); z-index:1000; }
        .sidebar-brand { padding:25px 20px; border-bottom:1px solid #fff; color:white; }
        .brand-title { font-size:1.8em; font-weight:700; color:white; display:flex; align-items:center; gap:12px; }
        .brand-subtitle { font-size:0.85em; color:#000; margin-top:5px; }
        .sidebar-nav ul { list-style:none; padding:20px 0; }
        .sidebar-nav a { display:flex; align-items:center; gap:15px; padding:15px 20px; color:#fff; text-decoration:none; transition:all 0.3s; }
        .sidebar-nav a:hover { background:rgba(255,255,255,0.1); padding-left:25px; }
        .sidebar-nav li.active a { background:linear-gradient(90deg,rgb(17,224,35),rgb(184,209,39)); border-left:4px solid #fff; }
        .menu-section { padding:25px 20px 10px; color:#fff; font-size:0.75em; text-transform:uppercase; letter-spacing:1.5px; font-weight:600; }
        .main-content { flex:1; margin-left:280px; padding:30px; }
        .breadcrumb-nav { background:#fff; padding:15px 30px; border-radius:12px; margin-bottom:20px; box-shadow:0 2px 10px rgba(0,0,0,0.05); display:flex; align-items:center; justify-content:space-between; }
        .breadcrumb { display:flex; align-items:center; gap:10px; list-style:none; }
        .back-button { background:linear-gradient(135deg,#10ce30,#000); color:white; border:none; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:600; display:flex; align-items:center; gap:8px; }
        .page-header { background:white; padding:30px; border-radius:16px; margin-bottom:25px; box-shadow:0 4px 20px rgba(0,0,0,0.3); display:flex; justify-content:space-between; align-items:center; }
        .page-header h1 { font-size:2em; font-weight:700; }
        /* Stats */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:25px; }
        .stat-card { background:white; padding:25px; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.2); display:flex; align-items:center; gap:20px; }
        .stat-icon { width:60px; height:60px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.8em; flex-shrink:0; }
        .stat-info h3 { font-size:1.8em; font-weight:700; }
        .stat-info p { color:#718096; font-size:0.9em; }
        /* Cards */
        .card { background:white; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.2); overflow:hidden; margin-bottom:25px; }
        .card-header { padding:20px 25px; border-bottom:2px solid #f7fafc; display:flex; justify-content:space-between; align-items:center; }
        .card-title { font-size:1.1em; font-weight:700; display:flex; align-items:center; gap:10px; }
        .card-body { padding:25px; }
        .chart-wrap { position:relative; height:280px; }
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        table { width:100%; border-collapse:collapse; }
        thead { background:linear-gradient(135deg,#10ce30,#000); color:white; }
        th { padding:12px 15px; text-align:left; font-weight:600; font-size:0.9em; }
        td { padding:12px 15px; border-bottom:1px solid #f7fafc; font-size:0.9em; vertical-align:middle; }
        tr:hover td { background:#f7fafc; }
        .no-data { text-align:center; padding:40px; color:#718096; }
        .badge { padding:4px 12px; border-radius:12px; font-size:0.8em; font-weight:600; }
        .badge-completed { background:#c6f6d5; color:#276749; }
        .badge-in_progress { background:#fef5e7; color:#d69e2e; }
        /* Filter */
        .filter-bar { background:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 2px 10px rgba(0,0,0,0.1); }
        .filter-grid { display:flex; gap:15px; align-items:center; flex-wrap:wrap; }
        .form-control { padding:10px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:0.95em; font-family:'Sarabun',sans-serif; }
        .btn { padding:10px 20px; border:none; border-radius:8px; font-size:0.95em; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:8px; font-family:'Sarabun',sans-serif; }
        .btn-primary { background:linear-gradient(135deg,#10ce30,#38a169); color:white; }
        /* Depreciation table */
        .dep-progress { background:#e2e8f0; border-radius:8px; height:8px; }
        .dep-bar-fill { background:linear-gradient(90deg,#10ce30,#e53e3e); height:8px; border-radius:8px; }
        .month-bar { display:flex; align-items:flex-end; gap:3px; height:120px; padding:0 5px; }
        .month-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; }
        .month-col-bar { width:100%; background:linear-gradient(180deg,#10ce30,#38a169); border-radius:4px 4px 0 0; min-height:2px; transition:height 0.3s; }
        .month-col span { font-size:0.7em; color:#718096; }
        .type-desktop { background:#ebf8ff; color:#2b6cb0; padding:3px 10px; border-radius:8px; font-size:0.8em; }
        .type-laptop  { background:#faf5ff; color:#553c9a; padding:3px 10px; border-radius:8px; font-size:0.8em; }
        .type-server  { background:#fff5f5; color:#c53030; padding:3px 10px; border-radius:8px; font-size:0.8em; }
        .type-printer { background:#fffaf0; color:#744210; padding:3px 10px; border-radius:8px; font-size:0.8em; }
        .type-other   { background:#e2e8f0; color:#4a5568; padding:3px 10px; border-radius:8px; font-size:0.8em; }
    </style>
</head>
<body>
<div class="container">
    <div class="sidebar">
        <div class="sidebar-brand">
            <div>
                <div class="brand-title"><i class="fas fa-ticket-alt"></i> IT Support</div>
                <div class="brand-subtitle">Ticket Management System</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="../admin/dashboard.php"><i class="fas fa-arrow-left"></i> กลับ Dashboard หลัก</a></li>
                <li class="menu-section">หลัก</li>
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="tickets.php"><i class="fas fa-ticket-alt"></i> IT Tickets</a></li>
                <li><a href="assets.php"><i class="fas fa-box"></i> สินทรัพย์</a></li>
                <li><a href="knowledgebase.php"><i class="fas fa-book"></i> Knowledge Base</a></li>
                <?php if ($isAdmin): ?>
                <li class="menu-section">จัดการ</li>
                <li><a href="users.php"><i class="fas fa-users"></i> ผู้ใช้งาน</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> รายงาน</a></li>
                <li class="active"><a href="assetsreports.php"><i class="fas fa-chart-line"></i> รายงานสินทรัพย์</a></li>
                <li><a href="slaconfig.php"><i class="fas fa-clock"></i> ตั้งค่า SLA</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> ตั้งค่า</a></li>
                <?php endif; ?>
                <li class="menu-section">ระบบ</li>
                <li><a href="../auth/logout.php" onclick="return confirm('ต้องการออกจากระบบ?')"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a></li>
            </ul>
        </nav>
    </div>

    <div class="main-content">
        <div class="breadcrumb-nav">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li>›</li>
                <li class="breadcrumb-item active"><i class="fas fa-chart-line"></i> รายงานสินทรัพย์</li>
            </ol>
            <a href="assets.php" class="back-button"><i class="fas fa-arrow-left"></i> กลับสินทรัพย์</a>
        </div>

        <div class="page-header">
            <div>
                <h1><i class="fas fa-chart-line"></i> รายงานสินทรัพย์ IT</h1>
                <p style="color:#555;margin-top:5px;">ค่าซ่อม, การยืม-คืน และการเสื่อมราคา</p>
            </div>
            <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> พิมพ์รายงาน</button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#fc8181,#e53e3e);"><i class="fas fa-tools" style="color:white;"></i></div>
                <div class="stat-info"><h3>฿<?= number_format($yearTotal,0) ?></h3><p>ค่าซ่อมรวมปี <?= $year ?></p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#f6ad55,#dd6b20);"><i class="fas fa-wrench" style="color:white;"></i></div>
                <div class="stat-info"><h3><?= $yearCount ?> ครั้ง</h3><p>จำนวนซ่อมปี <?= $year ?></p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#68d391,#38a169);"><i class="fas fa-shield-alt" style="color:white;"></i></div>
                <div class="stat-info"><h3><?= $warrantyCount ?> ครั้ง</h3><p>เบิกซ่อมประกันปีนี้</p></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#63b3ed,#3182ce);"><i class="fas fa-hand-holding" style="color:white;"></i></div>
                <div class="stat-info"><h3><?= $borrowActive ?> รายการ</h3><p>อุปกรณ์ที่ยังไม่คืน</p></div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <form method="GET">
                <div class="filter-grid">
                    <label style="font-weight:600;">กรองข้อมูล:</label>
                    <select name="year" class="form-control">
                        <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                        <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>>ปี <?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                    <select name="month" class="form-control">
                        <option value="0">ทุกเดือน</option>
                        <?php for ($m=1;$m<=12;$m++): ?>
                        <option value="<?= $m ?>" <?= $m==$month?'selected':'' ?>><?= $monthNames[$m] ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> กรองข้อมูล</button>
                </div>
            </form>
        </div>

        <!-- Charts Row -->
        <div class="two-col">
            <!-- Monthly Bar Chart -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-chart-bar"></i> ค่าซ่อมรายเดือน (ปี <?= $year ?>)</div></div>
                <div class="card-body"><div class="chart-wrap"><canvas id="monthlyChart"></canvas></div></div>
            </div>
            <!-- By Type Pie -->
            <div class="card">
                <div class="card-header"><div class="card-title"><i class="fas fa-chart-pie"></i> ค่าซ่อมตามประเภทอุปกรณ์</div></div>
                <div class="card-body"><div class="chart-wrap"><canvas id="typeChart"></canvas></div></div>
            </div>
        </div>

        <!-- Top Repair Assets -->
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-trophy"></i> อุปกรณ์ที่มีค่าซ่อมสูงสุด (ปี <?= $year ?>)</div></div>
            <table>
                <thead><tr><th>#</th><th>สินทรัพย์</th><th>ประเภท</th><th>จำนวนครั้ง</th><th>ค่าซ่อมรวม</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($topRepairAssets)): ?>
                    <tr><td colspan="6" class="no-data">ไม่มีข้อมูลการซ่อมในปีนี้</td></tr>
                <?php else: ?>
                    <?php $maxCost = max(array_column($topRepairAssets,'total_cost')); ?>
                    <?php foreach ($topRepairAssets as $i=>$a): ?>
                    <tr>
                        <td><strong style="color:<?= $i===0?'#d69e2e':($i===1?'#718096':($i===2?'#c05621':'#2d3748')) ?>"><?= $i+1 ?></strong></td>
                        <td>
                            <strong><?= htmlspecialchars($a['asset_name']) ?></strong><br>
                            <small style="color:#718096;"><?= htmlspecialchars($a['asset_tag']) ?></small>
                        </td>
                        <td><span class="type-<?= $a['asset_type'] ?>"><?= strtoupper($a['asset_type']) ?></span></td>
                        <td><?= $a['repair_count'] ?> ครั้ง</td>
                        <td>
                            <strong style="color:#e53e3e;">฿<?= number_format($a['total_cost'],2) ?></strong><br>
                            <div class="dep-progress" style="margin-top:4px;width:120px;">
                                <div class="dep-bar-fill" style="width:<?= round($a['total_cost']/$maxCost*100) ?>%;"></div>
                            </div>
                        </td>
                        <td><a href="assetsdetail.php?id=<?= $a['asset_id'] ?? '' ?>" class="btn btn-primary" style="padding:6px 14px;font-size:0.85em;"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- All Repairs -->
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-list"></i> รายการซ่อมทั้งหมด<?= $month ? " — {$monthNames[$month]} $year" : " — ปี $year" ?></div>
                <span style="font-size:0.9em;color:#718096;"><?= count($allRepairs) ?> รายการ | รวม ฿<?= number_format(array_sum(array_column($allRepairs,'repair_cost')),2) ?></span>
            </div>
            <table>
                <thead><tr><th>วันที่</th><th>สินทรัพย์</th><th>ปัญหา</th><th>ช่าง/บริษัท</th><th>ค่าใช้จ่าย</th><th>ประกัน</th><th>สถานะ</th></tr></thead>
                <tbody>
                <?php if (empty($allRepairs)): ?>
                    <tr><td colspan="7" class="no-data">ไม่มีข้อมูลการซ่อม</td></tr>
                <?php else: ?>
                    <?php foreach ($allRepairs as $r): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($r['repair_date'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r['asset_name']) ?></strong><br>
                            <small style="color:#718096;"><?= htmlspecialchars($r['asset_tag']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($r['problem_desc']) ?></td>
                        <td><?= htmlspecialchars($r['technician']??'') ?><?= $r['vendor'] ? '<br><small style="color:#718096;">'.htmlspecialchars($r['vendor']).'</small>' : '' ?></td>
                        <td><strong style="color:<?= $r['repair_cost']>0?'#e53e3e':'#718096' ?>">฿<?= number_format($r['repair_cost'],2) ?></strong></td>
                        <td><?= $r['warranty_claim'] ? '<span style="background:#c6f6d5;color:#276749;padding:3px 8px;border-radius:6px;font-size:0.8em;"><i class="fas fa-check"></i> ประกัน</span>' : '-' ?></td>
                        <td><span class="badge badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Depreciation Summary -->
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-chart-line"></i> สรุปมูลค่าเสื่อมราคาอุปกรณ์</div></div>
            <table>
                <thead><tr><th>สินทรัพย์</th><th>ราคาซื้อ</th><th>วันที่ซื้อ</th><th>อายุ(ปี)</th><th>ค่าเสื่อม/ปี</th><th>เสื่อมสะสม</th><th>มูลค่าปัจจุบัน</th><th>% เหลือ</th></tr></thead>
                <tbody>
                <?php if (empty($depAssets)): ?>
                    <tr><td colspan="8" class="no-data">ยังไม่มีข้อมูลราคาซื้อ — เพิ่ม Purchase Price ในข้อมูลสินทรัพย์</td></tr>
                <?php else: ?>
                    <?php foreach ($depAssets as $da):
                        $pp  = (float)$da['purchase_price'];
                        $sv  = (float)($da['salvage_value']??0);
                        $ul  = max((int)($da['useful_life_years']??5),1);
                        $yd  = ($pp - $sv) / $ul;
                        $yu  = date('Y') - date('Y', strtotime($da['purchase_date']));
                        $td2 = min($yd * $yu, $pp - $sv);
                        $cv  = max($pp - $td2, $sv);
                        $pct = round($cv / max($pp,1) * 100);
                    ?>
                    <tr>
                        <td>
                            <a href="assetdestail.php?id=<?= $da['asset_id'] ?>&tab=depreciation" style="color:#10ce30;text-decoration:none;font-weight:600;">
                                <?= htmlspecialchars($da['asset_name']) ?>
                            </a><br>
                            <small style="color:#718096;"><?= htmlspecialchars($da['asset_tag']) ?></small>
                        </td>
                        <td>฿<?= number_format($pp,2) ?></td>
                        <td><?= date('d/m/Y', strtotime($da['purchase_date'])) ?></td>
                        <td><?= $ul ?> ปี</td>
                        <td style="color:#e53e3e;">฿<?= number_format($yd,2) ?></td>
                        <td>฿<?= number_format($td2,2) ?></td>
                        <td><strong style="color:<?= $pct<20?'#e53e3e':($pct<50?'#d69e2e':'#276749') ?>">฿<?= number_format($cv,2) ?></strong></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="dep-progress" style="width:80px;">
                                    <div class="dep-bar-fill" style="width:<?= $pct ?>%;background:<?= $pct<20?'#e53e3e':($pct<50?'#d69e2e':'#10ce30') ?>;"></div>
                                </div>
                                <span style="font-size:0.85em;font-weight:600;"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Monthly Bar Chart
const monthLabels = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
const monthData   = [<?= implode(',', array_column($monthlyRepairs,'total')) ?>];
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [{ label: 'ค่าซ่อม (บาท)', data: monthData,
            backgroundColor: 'rgba(16,206,48,0.7)', borderColor: '#10ce30', borderWidth: 2, borderRadius: 6 }]
    },
    options: { responsive:true, maintainAspectRatio:false,
        plugins: { legend: { display:false } },
        scales: { y: { beginAtZero:true, ticks: { callback: v => '฿'+v.toLocaleString() } } }
    }
});

// Type Pie Chart
const typeLabels = [<?= implode(',', array_map(fn($t)=>'"'.strtoupper($t['asset_type']).'"', $repairByType)) ?>];
const typeData   = [<?= implode(',', array_column($repairByType,'total')) ?>];
const colors     = ['#10ce30','#4299e1','#f6ad55','#fc8181','#9f7aea','#68d391','#63b3ed'];
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: typeLabels.length ? typeLabels : ['ไม่มีข้อมูล'],
        datasets: [{ data: typeData.length ? typeData : [1],
            backgroundColor: colors, borderWidth: 2 }]
    },
    options: { responsive:true, maintainAspectRatio:false,
        plugins: { legend: { position:'right' } }
    }
});
</script>
</body>
</html>