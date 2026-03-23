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
$userId = (int) ($_SESSION['user_id'] ?? 0);

// Get Dashboard Statistics
$statsBaseSQL = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_count,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
    SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold_count,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_count,
    SUM(CASE WHEN sla_due_date < NOW() AND status NOT IN ('resolved', 'closed') THEN 1 ELSE 0 END) as overdue_count,
    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_count,
    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_count
    FROM tickets";

if ($isAdmin) {
    $statsResult = $db->query($statsBaseSQL);
} else {
    $statsStmt = $db->prepare($statsBaseSQL . " WHERE created_by = ?");
    $statsStmt->bind_param('i', $userId);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
}
$stats = $statsResult->fetch_assoc();

// Get Recent Tickets
$recentBaseSQL = "SELECT t.*, 
              creator.full_name as creator_name,
              assignee.full_name as assignee_name
              FROM tickets t 
              LEFT JOIN users creator ON t.created_by = creator.user_id 
              LEFT JOIN users assignee ON t.assigned_to = assignee.user_id
              ";

if ($isAdmin) {
    $recentResult = $db->query($recentBaseSQL . " ORDER BY t.created_at DESC LIMIT 10");
} else {
    $recentStmt = $db->prepare($recentBaseSQL . " WHERE t.created_by = ? ORDER BY t.created_at DESC LIMIT 10");
    $recentStmt->bind_param('i', $userId);
    $recentStmt->execute();
    $recentResult = $recentStmt->get_result();
}
$recentTickets = $recentResult->fetch_all(MYSQLI_ASSOC);

// Get Tickets by Category
$categoryBaseSQL = "SELECT category, COUNT(*) as count 
                    FROM tickets";
if ($isAdmin) {
    $categoryResult = $db->query($categoryBaseSQL . " GROUP BY category ORDER BY count DESC");
} else {
    $categoryStmt = $db->prepare($categoryBaseSQL . " WHERE created_by = ? GROUP BY category ORDER BY count DESC");
    $categoryStmt->bind_param('i', $userId);
    $categoryStmt->execute();
    $categoryResult = $categoryStmt->get_result();
}
$categories = $categoryResult->fetch_all(MYSQLI_ASSOC);

// Get Assets by Type
$assetTypesSQL = "SELECT asset_type, COUNT(*) as count FROM assets GROUP BY asset_type ORDER BY count DESC";
$assetTypes = $db->query($assetTypesSQL)->fetch_all(MYSQLI_ASSOC) ?? [];

// Get Assets Summary
$assetStatsSQL = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_count,
    SUM(CASE WHEN warranty_expiry < NOW() AND warranty_expiry IS NOT NULL THEN 1 ELSE 0 END) as warranty_expired_count
    FROM assets";
$assetStats = $db->query($assetStatsSQL)->fetch_assoc();

// Get Assets by Status for chart
$assetsByStatusSQL = "SELECT status, COUNT(*) as count FROM assets GROUP BY status";
$assetsByStatus = $db->query($assetsByStatusSQL)->fetch_all(MYSQLI_ASSOC) ?? [];

// Get Tickets by Status for chart
$ticketsByStatusBaseSQL = "SELECT status, COUNT(*) as count FROM tickets";
if ($isAdmin) {
    $ticketsByStatusResult = $db->query($ticketsByStatusBaseSQL . " GROUP BY status");
} else {
    $ticketsByStatusStmt = $db->prepare($ticketsByStatusBaseSQL . " WHERE created_by = ? GROUP BY status");
    $ticketsByStatusStmt->bind_param('i', $userId);
    $ticketsByStatusStmt->execute();
    $ticketsByStatusResult = $ticketsByStatusStmt->get_result();
}
$ticketsByStatus = $ticketsByStatusResult->fetch_all(MYSQLI_ASSOC) ?? [];

// Get Tickets by Priority for chart
$ticketsByPriorityBaseSQL = "SELECT priority, COUNT(*) as count FROM tickets";
if ($isAdmin) {
    $ticketsByPriorityResult = $db->query($ticketsByPriorityBaseSQL . " GROUP BY priority ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low')");
} else {
    $ticketsByPriorityStmt = $db->prepare($ticketsByPriorityBaseSQL . " WHERE created_by = ? GROUP BY priority ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low')");
    $ticketsByPriorityStmt->bind_param('i', $userId);
    $ticketsByPriorityStmt->execute();
    $ticketsByPriorityResult = $ticketsByPriorityStmt->get_result();
}
$ticketsByPriority = $ticketsByPriorityResult->fetch_all(MYSQLI_ASSOC) ?? [];

// Get Top Asset Brands
$topBrandsSQL = "SELECT brand, COUNT(*) as count FROM assets WHERE brand IS NOT NULL AND brand != '' GROUP BY brand ORDER BY count DESC LIMIT 5";
$topBrands = $db->query($topBrandsSQL)->fetch_all(MYSQLI_ASSOC) ?? [];

// Get Warranty expiring soon (30 days)
$warrantyExpiringSQL = "SELECT a.asset_id, a.asset_tag, a.asset_name, a.warranty_expiry, u.full_name FROM assets a LEFT JOIN users u ON a.assigned_to = u.user_id WHERE a.warranty_expiry BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) ORDER BY a.warranty_expiry ASC LIMIT 5";
$warrantyExpiring = $db->query($warrantyExpiringSQL)->fetch_all(MYSQLI_ASSOC) ?? [];

// Get Overdue Tickets
$overdueTicketsBaseSQL = "SELECT t.ticket_id, t.ticket_number, t.title, t.priority, t.sla_due_date 
    FROM tickets t 
    WHERE t.sla_due_date < NOW() AND t.status NOT IN ('resolved', 'closed')";
if ($isAdmin) {
    $overdueTicketsResult = $db->query($overdueTicketsBaseSQL . " ORDER BY t.sla_due_date ASC LIMIT 5");
} else {
    $overdueTicketsStmt = $db->prepare($overdueTicketsBaseSQL . " AND t.created_by = ? ORDER BY t.sla_due_date ASC LIMIT 5");
    $overdueTicketsStmt->bind_param('i', $userId);
    $overdueTicketsStmt->execute();
    $overdueTicketsResult = $overdueTicketsStmt->get_result();
}
$overdueTickets = $overdueTicketsResult->fetch_all(MYSQLI_ASSOC) ?? [];

// Get notifications (new_ticket, new_comment)
$notifStmt = $db->prepare("
    SELECT 
        n.notif_id,
        n.type,
        n.ticket_id,
        n.message,
        n.created_at,
        t.ticket_number,
        t.title         AS ticket_title,
        t.status        AS ticket_status,
        u.full_name     AS triggered_by_name,
        nr.is_read
    FROM notifications n
    INNER JOIN notification_recipients nr 
        ON n.notif_id = nr.notif_id AND nr.user_id = ?
    LEFT JOIN tickets t ON n.ticket_id  = t.ticket_id
    LEFT JOIN users   u ON n.triggered_by = u.user_id
    WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY n.created_at DESC
    LIMIT 20
");
$notifStmt->bind_param('i', $userId);
$notifStmt->execute();
$notifications = $notifStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$unreadNotifications = count(array_filter($notifications, fn($n) => !$n['is_read']));

// Get Current User
$currentUser = getCurrentUser();
$pageTitle = ui_text('page.title.dashboard');
$activePage = 'dashboard';
include_once __DIR__ . '/../includes/header.php';
include_once __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb-nav">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../admin/dashboard.php">
                            <i class="fas fa-home"></i> Romar Dashboard
                        </a>
                    </li>
                    <li class="breadcrumb-separator">&rsaquo;</li>
                    <li class="breadcrumb-item active">
                        <i class="fas fa-chart-line"></i> <?php echo ui_text('page.title.dashboard'); ?>
                    </li>
                </ol>
                <a href="../admin/dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo ui_text('nav.back_to_dashboard'); ?>
                </a>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-chart-line"></i> <?php echo ui_text('page.title.dashboard'); ?></h1>
                    <p class="page-subtitle"><?php echo ui_text('page.subtitle.dashboard'); ?></p>
                </div>
                
                <!-- Notification Bell -->
                <div class="notification-wrapper">
                    <button class="notification-bell" onclick="toggleNotifications()">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header" style="display:flex; justify-content:space-between; align-items:center;">
<h3><i class="fas fa-bell"></i> <?php echo ui_text('notification.header'); ?></h3>
                            <button 
                                onclick="event.stopPropagation(); markAllRead(this)" 
                                id="markAllReadBtn"
                                style="font-size:0.85em; background:#667eea; border:none; color:white; cursor:pointer; 
                                       font-family:'Sarabun',sans-serif; padding:5px 12px; border-radius:6px;
                                       min-width:unset; width:auto; transition:all 0.2s;">
                                <i class="fas fa-check-double"></i> <?php echo ui_text('notification.mark_all'); ?>
                            </button>
                        </div>
                        <div class="notification-list">
                            <?php if (empty($notifications)): ?>
                            <div class="notification-empty">
                                <i class="fas fa-bell-slash"></i>
                                <p><?php echo ui_text('dashboard.notification.empty'); ?></p>
                            </div>
                            <script>
                                const timeMessages = {
'just_now': '<?php echo ui_text("time.just_now"); ?>',
                                    'minutes': '<?php echo ui_text("time.minutes_ago"); ?>',
                                    'hours': '<?php echo ui_text("time.hours_ago"); ?>',
                                    'days': '<?php echo ui_text("time.days_ago"); ?>'
                                };
                            </script>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): 
                                    $isUnread = !$notif['is_read'];
$icon = $notif['type'] === 'new_comment' ? 'fa-comment-dots' : 'fa-plus';
                                    $time_diff = time() - strtotime($notif['created_at']);
if      ($time_diff < 60)    $timeText = 'just now';
elseif  ($time_diff < 3600)  $timeText = floor($time_diff / 60) . ' minutes ago';
                                    elseif  ($time_diff < 86400) $timeText = sprintf(ui_text('time.hours_ago'), floor($time_diff / 3600));
                                    else                         $timeText = sprintf(ui_text('time.days_ago'), floor($time_diff / 86400));
                                ?>
                                <div class="notification-item <?php echo $isUnread ? 'unread' : ''; ?>" 
                                     onclick="readAndViewTicket(<?php echo $notif['notif_id']; ?>, <?php echo $notif['ticket_id']; ?>)"
                                     style="<?php echo $isUnread ? 'background:#f0f7ff; border-left:3px solid #4299e1;' : ''; ?>">
                                    <div class="notification-title">
                                        <?php echo $icon; ?>
                                        <?php echo htmlspecialchars($notif['ticket_number']); ?>
                                        <?php if ($isUnread): ?>
<span style="font-size:0.75em; color:#e53e3e; font-weight:700; margin-left:6px;">ใหม่</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-message">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </div>
                                    <?php if (!empty($notif['triggered_by_name'])): ?>
                                    <div class="notification-message" style="color:#4a5568; font-size:0.82em;">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($notif['triggered_by_name']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="notification-time">
                                        <i class="fas fa-clock"></i> <?php echo $timeText; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- IT Assets Section -->
            <section class="section">
                <div class="section-header">
                    <div>
                        <h2 class="section-title"><i class="fas fa-triangle-exclamation"></i> <?php echo ui_text('dashboard.alerts.title'); ?></h2>
<p class="section-subtitle"><?php echo ui_text('dashboard.alerts.subtitle'); ?></p>
                    </div>
                </div>
                <div class="section-body">
                    <?php if (!empty($assetTypes)): ?>
                        <div class="stats-grid asset-types">

                    <?php
                        $assetTypeIcons = [
                            'desktop' => ['icon' => 'fa-desktop', 'color' => '#f687b3'],
                            'laptop' => ['icon' => 'fa-laptop', 'color' => '#4299e1'],
                            'server' => ['icon' => 'fa-server', 'color' => '#ed8936'],
                            'monitor' => ['icon' => 'fa-tv', 'color' => '#c05621'],
                            'printer' => ['icon' => 'fa-print', 'color' => '#3182ce'],
                            'network' => ['icon' => 'fa-network-wired', 'color' => '#f6ad55'],
                            'phone' => ['icon' => 'fa-mobile-alt', 'color' => '#38b2ac'],
                            'software' => ['icon' => 'fa-compact-disc', 'color' => '#22543d'],
                            'rack' => ['icon' => 'fa-layer-group', 'color' => '#fbd38d'],
                            'enclosure' => ['icon' => 'fa-cube', 'color' => '#9ae6b4'],
                            'pdu' => ['icon' => 'fa-plug', 'color' => '#feb2b2'],
                            'mobile' => ['icon' => 'fa-mobile-alt', 'color' => '#fed8b1'],
                            'other' => ['icon' => 'fa-boxes', 'color' => '#cbd5e0'],
                        ];
                    ?>
                        <?php foreach ($assetTypes as $asset): ?>
                            <?php
                                $type = $asset['asset_type'] ?? 'other';
                                $typeMeta = $assetTypeIcons[$type] ?? ['icon' => 'fa-boxes', 'color' => '#cbd5e0'];
                                $displayLabel = match($type) {
                                    'desktop' => 'Computers',
                                    'laptop' => 'Laptops',
                                    'server' => 'Servers',
                                    'monitor' => 'Monitors',
                                    'printer' => 'Printers',
                                    'network' => 'Network Devices',
                                    'phone' => 'Phones',
                                    'software' => 'Software',
                                    'rack' => 'Racks',
                                    'enclosure' => 'Enclosure',
                                    'pdu' => 'PDUs',
                                    'mobile' => 'Mobile',
                                    default => ucfirst($type)
                                };
                            ?>
                            <div class="stat-card" style="border-left: 5px solid <?php echo $typeMeta['color']; ?>;">
                                <div class="stat-icon" style="background: <?php echo $typeMeta['color']; ?>;">
                                    <i class="fas <?php echo $typeMeta['icon']; ?>"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo number_format($asset['count']); ?></h3>
                                    <p><?php echo $displayLabel; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-boxes" style="font-size: 2rem;"></i>
<?php echo ui_text('dashboard.assets.empty'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- IT Tickets by Category -->
            <section class="section">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-ticket-alt"></i> <?php echo ui_text('dashboard.tickets.section.title'); ?>
                        </h2>
<p class="section-subtitle"><?php echo ui_text('dashboard.tickets.section.subtitle'); ?></p>
                    </div>
                </div>
                <div class="section-body">
                    <?php if (!empty($categories)): ?>
                        <?php
                            $ticketGradients = [
                                'linear-gradient(135deg, #667eea, #764ba2)',
                                'linear-gradient(135deg, #4299e1, #3182ce)',
                                'linear-gradient(135deg, #e53e3e, #dd6b20)',
                                'linear-gradient(135deg, #48bb78, #38a169)',
                                'linear-gradient(135deg, #f6ad55, #ed8936)',
                                'linear-gradient(135deg, #9f7aea, #6b46c1)',
                                'linear-gradient(135deg, #ed64a6, #d53f8c)',
                                'linear-gradient(135deg, #4fd1c5, #38b2ac)',
                            ];
                        ?>
                        <div class="stats-grid asset-types">
                            <?php foreach ($categories as $idx => $cat): ?>
                                <?php $color = $ticketGradients[$idx % count($ticketGradients)]; ?>
                                <div class="stat-card" style="background: <?php echo $color; ?>;">
                                    <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                                        <i class="fas fa-folder-open"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3><?php echo number_format($cat['count']); ?></h3>
                                        <p><?php echo ucfirst(htmlspecialchars($cat['category'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-ticket-alt" style="font-size: 2rem;"></i>
                            <h3><?php echo ui_text('dashboard.tickets.empty'); ?></h3>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Recent Tickets & Charts Section -->
            <section class="section">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-document-lines"></i> <?php echo ui_text('dashboard.recent.title'); ?>
                        </h2>
                        <p class="section-subtitle">เน€เธโฌเน€เธยเธขย เน€เธโฌเน€เธยเน€เธโ€เน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขย เน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธโ€เน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธโ€ฆเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธโ€เน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธโ€เน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธโ€ฆเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธโ€เน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธโ€”เน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธโ€เน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌโ€เน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธโ€เน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธโ€เน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธย</p>
                    </div>
                    <div class="section-actions">
                        <a class="btn btn-secondary btn-sm" href="tickets.php">
                            <i class="fas fa-arrow-right"></i> <?php echo ui_text('dashboard.view_tickets_btn'); ?>
                        </a>
                    </div>
                </div>
                <div class="section-body">
                    <div class="section-stack">
                        <div class="charts-grid charts-grid--wide">
                            <!-- Assets Status Chart -->
                            <div class="chart-card">
<h3 class="chart-title"><i class="fas fa-box"></i> <?php echo ui_text('dashboard.assets_status'); ?></h3>
                                <div class="chart-container">
                                    <canvas id="assetsStatusChart"></canvas>
                                </div>
                            </div>

                            <!-- Tickets Status Chart -->
                            <div class="chart-card">
<h3 class="chart-title"><i class="fas fa-ticket-alt"></i> <?php echo ui_text('dashboard.tickets_status'); ?></h3>
                                <div class="chart-container">
                                    <canvas id="ticketsStatusChart"></canvas>
                                </div>
                            </div>

                            <!-- Tickets Priority Chart -->
                            <div class="chart-card">
                                <h3 class="chart-title"><i class="fas fa-flag"></i> <?php echo ui_text('dashboard.priority_chart.title'); ?></h3>
                                <div class="chart-container">
                                    <canvas id="ticketsPriorityChart"></canvas>
                                </div>
                            </div>

                            <!-- Top Brands -->
                            <?php if (!empty($topBrands)): ?>
                            <div class="chart-card">
                                <h3 class="chart-title"><i class="fas fa-industry"></i> <?php echo ui_text('dashboard.top_brands.title'); ?></h3>
                                <ul class="detail-list">
                                    <?php foreach ($topBrands as $brand): ?>
                                    <li class="detail-item">
                                        <span class="detail-item-label">
                                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($brand['brand']); ?>
                                        </span>
                                        <span class="detail-item-value"><?php echo $brand['count']; ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <div class="section-subheading">
                                <i class="fas fa-list"></i> <?php echo ui_text('dashboard.tickets.section.title'); ?>
                            </div>
                            <div class="card">

                                <div class="card-header">
                                    <h2 class="card-title"><i class="fas fa-history"></i> <?php echo ui_text('dashboard.tickets.recent.card_title'); ?></h2>
                                    <a href="tickets.php"><?php echo ui_text('dashboard.view_tickets_btn'); ?></a>
                                </div>

                                <?php if (empty($recentTickets)): ?>
                                    <div class="card-placeholder">\n                                        <i class="fas fa-inbox"></i>\n                                        <p><?php echo ui_text('dashboard.tickets.empty'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentTickets as $ticket): ?>
                                        <div class="ticket-item">
                                            <div class="ticket-header">
                                                <span class="ticket-number"><?php echo htmlspecialchars($ticket['ticket_number'] ?? 0); ?></span>
                                                <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                                    <?php echo strtoupper($ticket['status'] ?? 0); ?>
                                                </span>
                                            </div>
                                            <div class="ticket-title"><?php echo htmlspecialchars($ticket['title'] ?? 0); ?></div>
                                            <div class="ticket-meta">
                                                <span class="priority-badge priority-<?php echo $ticket['priority']; ?>">
                                                    <?php echo strtoupper($ticket['priority'] ?? 0); ?>
                                                </span>
                                                | <?php echo ui_text('dashboard.ticket.created_by_label'); ?>: <?php echo htmlspecialchars($ticket['creator_name'] ?? 'N/A'); ?>
                                                |
                                                <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'] ?? 0)); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Alerts & Warnings Section -->
            <?php if (!empty($overdueTickets) || !empty($warrantyExpiring)): ?>
            <section class="section">
                <div class="section-header">
                    <div>
                        <h2 class="section-title">
                            <i class="fas fa-triangle-exclamation"></i> เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเน€เธยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌโ€เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย
                        </h2>
                        <p class="section-subtitle">เน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเน€เธโ€”เน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเธขย SLA เน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธโ€ฆเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเนโฌโ€เน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธโ€เน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเน€เธโ€เน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌโ€เน€เธโฌเน€เธยเน€เธโ€ขเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเน€เธยเน€เธโฌเน€เธยเธขย</p>
                    </div>
                </div>
                <div class="section-body">
                    <div class="charts-grid charts-grid--wide">
                        <?php if (!empty($overdueTickets)): ?>
                        <div class="chart-card" style="border-top: 4px solid #f56565;">
                            <h3 class="chart-title"><i class="fas fa-exclamation-triangle"></i> <?php echo ui_text('dashboard.alerts.overdue.title', ['count' => count($overdueTickets)]); ?></h3>
                            <ul class="detail-list">
                                <?php foreach ($overdueTickets as $ticket): ?>
                                <li class="detail-item">
                                    <span class="detail-item-label">
                                        <i class="fas fa-ticket-alt"></i>
                                        <span>
                                            <strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong><br>
                                            <small><?php echo htmlspecialchars(substr($ticket['title'], 0, 50)); ?></small>
                                        </span>
                                    </span>
                                    <span class="detail-item-value badge-<?php echo $ticket['priority']; ?>"><?php echo strtoupper($ticket['priority']); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($warrantyExpiring)): ?>
                        <div class="chart-card" style="border-top: 4px solid #ed8936;">
                            <h3 class="chart-title"><i class="fas fa-calendar"></i> <?php echo ui_text('dashboard.alerts.warranty.title', ['count' => count($warrantyExpiring)]); ?></h3>
                            <ul class="detail-list">
                                <?php foreach ($warrantyExpiring as $warranty): ?>
                                <li class="detail-item">
                                    <span class="detail-item-label">
                                        <i class="fas fa-box"></i>
                                        <span>
                                            <strong><?php echo htmlspecialchars($warranty['asset_tag']); ?></strong><br>
                                            <small><?php echo htmlspecialchars(substr($warranty['asset_name'], 0, 40)); ?></small>
                                        </span>
                                    </span>
                                    <span class="detail-item-value"><?php echo date('d/m', strtotime($warranty['warranty_expiry'])); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<?php ob_start(); ?>
    <script>
        // ===== PRG Guard =====
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย Toggle dropdown เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }

        // เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขย dropdown เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌโ€เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย (เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย)
        document.addEventListener('click', function(event) {
            const wrapper  = document.querySelector('.notification-wrapper');
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown && wrapper && !wrapper.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย notification เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขย เน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธยเนยเธเธขย mark read เน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธยเนยเธเธขย เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย ticket เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย
        function readAndViewTicket(notifId, ticketId) {
            fetch('../api/marknotificationread.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notif_id: notifId })
            }).finally(() => {
                window.location.href = 'ticket_view.php?id=' + ticketId;
            });
        }

        // เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขย เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย
        function markAllRead(btn) {
            // เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย loading เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขย
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเน€เธย...';

            fetch('../api/marknotificationread.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mark_all_read: true })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเน€เธย UI เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธ เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเน€เธยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย reload เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย
                    
                    // 1. เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย badge เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย
                    const badge = document.querySelector('.notification-badge');
                    if (badge) badge.remove();

                    // 2. เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌย highlight เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย item
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        item.style.background = '';
                        item.style.borderLeft = '';
                        // เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเนโฌยเน€เธเธเธขย NEW badge
                        const newBadge = item.querySelector('span[style*="e53e3e"]');
                        if (newBadge) newBadge.remove();
                    });

                    // 3. เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขย "เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขย"
                    btn.style.display = 'none';

                    // 4. เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย feedback เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย
                    showToast(<?= json_encode(ui_text('toast.notifications_marked')); ?>);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                    showToast(<?= json_encode(ui_text('toast.notifications_mark_error')); ?>);
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                showToast(<?= json_encode(ui_text('toast.server_unreachable')); ?>);
            });
        }

        // เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย Toast notification เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย
        function showToast(message) {
            let toast = document.getElementById('toastMsg');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'toastMsg';
                toast.style.cssText = `
                    position:fixed; bottom:30px; right:30px; z-index:9999;
                    background:#2d3748; color:white; padding:12px 20px;
                    border-radius:10px; font-family:'Sarabun',sans-serif;
                    font-size:0.95em; box-shadow:0 4px 20px rgba(0,0,0,0.3);
                    transition:opacity 0.3s; opacity:0;
                `;
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.style.opacity = '1';
            setTimeout(() => { toast.style.opacity = '0'; }, 3000);
        }

        // เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย Initialize Charts เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });

        function initializeCharts() {
            const chartColors = ['#667eea', '#4299e1', '#48bb78', '#ed8936', '#f56565', '#9f7aea'];

            // Assets Status Chart
            const assetsStatusCtx = document.getElementById('assetsStatusChart');
            if (assetsStatusCtx) {
                const assetStatusData = <?php echo json_encode($assetsByStatus); ?>;
                const statusLabels = assetStatusData.map(d => d.status.toUpperCase());
                const statusCounts = assetStatusData.map(d => d.count);
                
                new Chart(assetsStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            data: statusCounts,
                            backgroundColor: chartColors,
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { family: 'Sarabun', size: 13 } } },
                            tooltip: { bodyFont: { family: 'Sarabun', size: 13 }, titleFont: { family: 'Sarabun', size: 14 } }
                        },
                        layout: { padding: { top: 8, bottom: 8 } }
                    }
                });
            }

            // Tickets Status Chart
            const ticketsStatusCtx = document.getElementById('ticketsStatusChart');
            if (ticketsStatusCtx) {
                const ticketStatusData = <?php echo json_encode($ticketsByStatus); ?>;
                const tStatusLabels = ticketStatusData.map(d => d.status.toUpperCase());
                const tStatusCounts = ticketStatusData.map(d => d.count);
                
                new Chart(ticketsStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: tStatusLabels,
                        datasets: [{
                            data: tStatusCounts,
                            backgroundColor: chartColors,
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { family: 'Sarabun', size: 13 } } },
                            tooltip: { bodyFont: { family: 'Sarabun', size: 13 }, titleFont: { family: 'Sarabun', size: 14 } }
                        },
                        layout: { padding: { top: 8, bottom: 8 } }
                    }
                });
            }

            // Tickets Priority Chart
            const ticketsPriorityCtx = document.getElementById('ticketsPriorityChart');
            if (ticketsPriorityCtx) {
                const ticketPriorityData = <?php echo json_encode($ticketsByPriority); ?>;
                const priorityLabels = ticketPriorityData.map(d => d.priority.toUpperCase());
                const priorityCounts = ticketPriorityData.map(d => d.count);
                const priorityColors = ticketPriorityData.map(d => {
                    switch(d.priority) {
                        case 'urgent': return '#f56565';
                        case 'high': return '#ed8936';
                        case 'normal': return '#4299e1';
                        case 'low': return '#48bb78';
                        default: return '#cbd5e0';
                    }
                });
                
                new Chart(ticketsPriorityCtx, {
                    type: 'bar',
                    data: {
                        labels: priorityLabels,
                        datasets: [{
                            label: 'Tickets',
                            data: priorityCounts,
                            backgroundColor: priorityColors,
                            borderColor: priorityColors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: { display: false },
                            tooltip: { bodyFont: { family: 'Sarabun', size: 13 }, titleFont: { family: 'Sarabun', size: 13 } }
                        },
                        layout: { padding: { top: 6, bottom: 6 } },
                        scales: {
                            x: { beginAtZero: true, ticks: { font: { family: 'Sarabun', size: 12 } } },
                            y: { ticks: { font: { family: 'Sarabun', size: 12 } } }
                        }
                    }
                });
            }
        }

        // เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย Auto-refresh badge เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขย 30 เน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธเธเธขยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเนโฌยเน€เธโฌเน€เธยเนยเธเน€เธโฌเน€เธยเธขยเน€เธโฌเน€เธยเนโฌเธ เน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธยเน€เธโฌเน€เธยเธขยเน€เธยเนยเธเธขยเน€เธยเธขยเน€เธย
        setInterval(function() {
            fetch('../api/getnotificationcount.php')
                .then(r => r.json())
                .then(data => {
                    const badge = document.querySelector('.notification-badge');
                    const bell  = document.querySelector('.notification-bell');
                    if (!bell) return;
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count;
                        } else {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'notification-badge';
                            newBadge.textContent = data.count;
                            bell.appendChild(newBadge);
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                }).catch(() => {});
        }, 30000);
    </script>
<?php $pageScripts = ob_get_clean(); ?>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
