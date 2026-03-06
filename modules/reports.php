<?php
session_start();
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

// ── Export Excel ──────────────────────────────────────────────
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
        <th>Ticket ID</th><th>หัวข้อ</th><th>หมวดหมู่</th><th>Priority</th>
        <th>สถานะ</th><th>ผู้รับผิดชอบ</th><th>วันที่สร้าง</th>
        <th>วันที่แก้ไข</th><th>รายละเอียด</th><th>วิธีแก้ไข</th>
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน - IT Support</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient( #065f159c);
            color: #2d3748;
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #10ce30 0%, #000000 100%
            );
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
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
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.8), rgba(118, 75, 162, 0.8));
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

        /* Main Content */
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .page-header h1 {
            font-size: 2em;
            color: #1a202c;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #718096;
        }

        /* Date Filter */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: auto auto auto auto 1fr;
            gap: 15px;
            align-items: end;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }

        .form-control {
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Sarabun', sans-serif;
            width: 100%;
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

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
        }

        .stat-info h3 {
            font-size: 2em;
            font-weight: 700;
            color: #1a202c;
        }

        .stat-info p {
            color: #718096;
            font-size: 0.9em;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .chart-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f7fafc;
        }

        .chart-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #1a202c;
        }

        /* Table */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f7fafc;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f7fafc;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #48bb78, #38a169);
            transition: width 0.3s;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-success { background: #c6f6d5; color: #2f855a; }
        .badge-warning { background: #feebc8; color: #c05621; }
        .badge-danger { background: #fed7d7; color: #c53030; }
        .badge-info { background: #bee3f8; color: #2c5282; }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .charts-grid {
                grid-template-columns: 1fr;
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
                    <li><a href="Knowledgebase.php"><i class="fas fa-book"></i> Knowledge Base</a></li>
                    <li class="menu-section">จัดการ</li>
                    <li><a href="users.php"><i class="fas fa-users"></i> ผู้ใช้งาน</a></li>
                    <li class="active"><a href="reports.php"><i class="fas fa-chart-bar"></i> รายงาน</a></li>
                    <li><a href="slaconfig.php"><i class="fas fa-clock"></i> ตั้งค่า SLA</a></li>
                    <li class="menu-section">ระบบ</li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> ตั้งค่า</a></li>
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
                <span style="color: #2d3748; font-weight: 600;">รายงาน</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-chart-bar"></i> รายงานและสถิติ</h1>
                <p>ภาพรวมและวิเคราะห์ข้อมูล IT Tickets</p>
            </div>

            <!-- Date Filter -->
            <div class="filter-section">
                <form method="GET">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="start_date">วันที่เริ่มต้น</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date">วันที่สิ้นสุด</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $endDate; ?>">
                        </div>
                        <div class="form-group">
                            <label for="report_type">ประเภทรายงาน</label>
                            <select name="report_type" id="report_type" class="form-control">
                                <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>สรุปภาพรวม</option>
                                <option value="detailed" <?php echo $reportType === 'detailed' ? 'selected' : ''; ?>>รายละเอียด</option>
                            </select>
                        </div>
                        <button type="submit" id="btn_search" name="btn_search" class="btn btn-primary">
                            <i class="fas fa-search"></i> ดูรายงาน
                        </button>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a href="?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&report_type=<?= $reportType ?>&export=excel"
                               style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;background:#38a169;color:#fff;border-radius:8px;text-decoration:none;font-size:0.9em;font-weight:600;">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </a>
                            <button type="button" id="btn_pdf" name="btn_pdf" onclick="setTimeout(()=>window.print(),100)"
                                    style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;background:#e53e3e;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:0.9em;font-weight:600;font-family:'Sarabun',sans-serif;">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Summary Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                        <i class="fas fa-ticket-alt" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($summary['total_tickets'] ?? 0); ?></h3>
                        <p>Tickets ทั้งหมด</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                        <i class="fas fa-check-circle" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($summary['resolved_tickets'] ?? 0); ?></h3>
                        <p>แก้ไขแล้ว</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936, #dd6b20);">
                        <i class="fas fa-tasks" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($summary['in_progress_tickets'] ?? 0); ?></h3>
                        <p>กำลังดำเนินการ</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f56565, #e53e3e);">
                        <i class="fas fa-exclamation-triangle" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($summary['overdue_tickets'] ?? 0); ?></h3>
                        <p>เกิน SLA</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                        <i class="fas fa-clock" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($summary['avg_resolution_time'] ?? 0, 1); ?></h3>
                        <p>เวลาเฉลี่ย (ชม.)</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #9f7aea, #805ad5);">
                        <i class="fas fa-percentage" style="color: white;"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($slaPercentage, 1); ?>%</h3>
                        <p>SLA Compliance</p>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-grid">
                <!-- Category Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title"><i class="fas fa-folder"></i> Tickets ตามหมวดหมู่</h3>
                    </div>
                    <canvas id="categoryChart" role="img" aria-label="กราฟ Tickets ตามหมวดหมู่"></canvas>
                </div>

                <!-- Priority Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title"><i class="fas fa-exclamation-circle"></i> Tickets ตาม Priority</h3>
                    </div>
                    <canvas id="priorityChart" role="img" aria-label="กราฟ Tickets ตาม Priority"></canvas>
                </div>

                <!-- Status Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title"><i class="fas fa-tasks"></i> Tickets ตาม Status</h3>
                    </div>
                    <canvas id="statusChart" role="img" aria-label="กราฟ Tickets ตาม Status"></canvas>
                </div>

                <!-- Trend Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title"><i class="fas fa-chart-line"></i> แนวโน้ม 30 วันล่าสุด</h3>
                    </div>
                    <canvas id="trendChart" role="img" aria-label="กราฟแนวโน้ม Tickets 30 วันล่าสุด"></canvas>
                </div>
            </div>

            <!-- Staff Performance Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-tie"></i> ประสิทธิภาพทีมงาน (Top 5)</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ชื่อ-นามสกุล</th>
                            <th>Tickets ที่ได้รับ</th>
                            <th>แก้ไขแล้ว</th>
                            <th>ปิดแล้ว</th>
                            <th>เวลาเฉลี่ย (ชม.)</th>
                            <th>อัตราความสำเร็จ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staffPerformance)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #718096;">
                                <i class="fas fa-user-clock" style="font-size: 3em; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                                ไม่มีข้อมูลในช่วงเวลานี้
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($staffPerformance as $staff): ?>
                            <?php
                                $successRate = 0;
                                if ($staff['assigned_tickets'] > 0) {
                                    $successRate = round(($staff['resolved_tickets'] / $staff['assigned_tickets']) * 100, 1);
                                }
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($staff['full_name']); ?></strong></td>
                                <td><?php echo number_format($staff['assigned_tickets']); ?></td>
                                <td><?php echo number_format($staff['resolved_tickets']); ?></td>
                                <td><?php echo number_format($staff['closed_tickets']); ?></td>
                                <td><?php echo number_format($staff['avg_resolution_time'] ?? 0, 1); ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="progress-bar" style="flex: 1;">
                                            <div class="progress-fill" style="width: <?php echo $successRate; ?>%"></div>
                                        </div>
                                        <span style="font-weight: 600;"><?php echo $successRate; ?>%</span>
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
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($byCategory, 'category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($byCategory, 'count')); ?>,
                    backgroundColor: [
                        '#667eea', '#48bb78', '#ed8936', '#f56565', '#4299e1', '#9f7aea'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' },
                    title: { display: false, text: 'Tickets ตามหมวดหมู่' }
                }
            }
        });

        // Priority Chart
        const priorityCtx = document.getElementById('priorityChart').getContext('2d');
        new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map('strtoupper', array_column($byPriority, 'priority'))); ?>,
                datasets: [{
                    label: 'จำนวน Tickets',
                    data: <?php echo json_encode(array_column($byPriority, 'count')); ?>,
                    backgroundColor: ['#f56565', '#ed8936', '#4299e1', '#48bb78']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    title: { display: false, text: 'Tickets ตาม Priority' }
                },
                scales: { y: { beginAtZero: true } }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_map('strtoupper', array_column($byStatus, 'status'))); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($byStatus, 'count')); ?>,
                    backgroundColor: ['#4299e1', '#ed8936', '#9f7aea', '#48bb78', '#718096']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' },
                    title: { display: false, text: 'Tickets ตาม Status' }
                }
            }
        });

        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse(array_column($ticketTrend, 'date'))); ?>,
                datasets: [{
                    label: 'Tickets',
                    data: <?php echo json_encode(array_reverse(array_column($ticketTrend, 'count'))); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    title: { display: false, text: 'แนวโน้ม Tickets 30 วันล่าสุด' }
                },
                scales: { y: { beginAtZero: true } }
            }
        });
    </script>
    <style>
    @media print {
        /* ซ่อนส่วนที่ไม่ต้องพิมพ์ */
        .sidebar,
        .breadcrumb-nav,
        .filter-section,
        nav, form button, form a { display: none !important; }

        /* Reset layout - ลบ margin-left ของ sidebar ออก */
        body {
            background: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .container {
            display: block !important;
        }

        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        /* ตั้งค่า Page */
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }

        /* Card & Chart */
        .card, .chart-card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            page-break-inside: avoid;
            margin-bottom: 15px !important;
        }

        /* Stats Grid - 2 คอลัมน์ใน A4 */
        .stats-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 10px !important;
            margin-bottom: 15px !important;
        }

        /* Charts Grid - 2 คอลัมน์ */
        .charts-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 10px !important;
            margin-bottom: 15px !important;
        }

        /* Page Header */
        .page-header {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            padding: 15px !important;
            margin-bottom: 15px !important;
        }

        h1 { color: #1a202c !important; font-size: 1.4em !important; }
        .card-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    </style>
</body>
</html>
