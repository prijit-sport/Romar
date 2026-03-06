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

// Get notifications จากระบบใหม่ (รองรับทั้ง new_ticket และ new_comment)
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Support Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background:  #065f159c;
            color: #000000;
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #10ce30 0%, #000000 100%);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgb(0, 0, 0);
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
            font-size: 1em;
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

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        /* Breadcrumb */
        .breadcrumb-nav {
            background: rgb(255, 255, 255);
            padding: 15px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgb(0, 0, 0);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            list-style: none;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95em;
        }

        .breadcrumb-item a {
            color: #000000;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb-item.active {
            color: #2d3748;
            font-weight: 600;
        }

        .back-button {
            background: linear-gradient(135deg, #10ce30 0%, #000000 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 206, 48, 0.4);
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 16px rgb(0, 0, 0), 0 8px 24px rgb(0, 0, 0);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .page-header:hover {
            box-shadow: 0 8px 24px rgb(0, 0, 0), 0 12px 32px rgb(0, 0, 0);
        }

        .page-header h1 {
            font-size: 2em;
            color: #000000;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .page-subtitle {
            color: #000000;
            font-size: 0.95em;
        }

        /* Notification Bell */
        .notification-wrapper {
            position: relative;
            display: inline-block;
        }

        .notification-bell {
            background: linear-gradient(135deg, #10ce30 0%, #000000 100%);
            color: white;
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2em;
            position: relative;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(16, 206, 48, 0.3);
        }

        .notification-bell:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(16, 206, 48, 0.5);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #f56565;
            color: white;
            font-size: 0.75em;
            font-weight: 700;
            padding: 3px 7px;
            border-radius: 12px;
            min-width: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(245, 101, 101, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            background: white;
            width: 380px;
            max-height: 500px;
            overflow-y: auto;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgb(0, 0, 0);
            display: none;
            z-index: 1000;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 20px;
            border-bottom: 2px solid #e2e8f0;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-radius: 16px 16px 0 0;
        }

        .notification-header h3 {
            font-size: 1.2em;
            color: #2d3748;
            font-weight: 700;
            margin: 0;
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.2s;
            cursor: pointer;
        }

        .notification-item:hover {
            background: #f7fafc;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
            font-size: 0.95em;
        }

        .notification-message {
            color: #718096;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .notification-time {
            color: #a0aec0;
            font-size: 0.8em;
        }

        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: #a0aec0;
        }

        .notification-empty i {
            font-size: 3em;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .stats-grid.tickets-top {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }

        .stats-grid.asset-summary {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }

        .stats-grid.asset-types {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }

        @media (min-width: 1200px) {
            .stats-grid.tickets-top { grid-template-columns: repeat(5, 1fr); }
            .stats-grid.asset-summary { grid-template-columns: repeat(4, 1fr); }
            .stats-grid.asset-types { grid-template-columns: repeat(5, 1fr); }
        }

        @media (min-width: 768px) and (max-width: 1199px) {
            .stats-grid.tickets-top { grid-template-columns: repeat(3, 1fr); }
            .stats-grid.asset-summary { grid-template-columns: repeat(2, 1fr); }
            .stats-grid.asset-types { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 767px) {
            .stats-grid.tickets-top { grid-template-columns: 1fr; }
            .stats-grid.asset-summary { grid-template-columns: 1fr; }
            .stats-grid.asset-types { grid-template-columns: 1fr; }
        }

        .stat-card {
            background: white;
            padding: 28px;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgb(0, 0, 0), 0 8px 24px rgb(0, 0, 0);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgb(0, 0, 0), 0 12px 32px rgb(0, 0, 0);
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
            margin-bottom: 5px;
            color: #000000;
        }

        .stat-info p {
            color: #000000;
            font-size: 0.9em;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgb(0, 0, 0), 0 8px 24px rgb(0, 0, 0);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 8px 24px rgb(0, 0, 0), 0 12px 32px rgb(0, 0, 0);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #000000;
        }

        .card-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #030303;
        }

        /* Ticket Item */
        .ticket-item {
            padding: 15px;
            border-left: 4px solid #000000;
            margin-bottom: 12px;
            background: #ffffff;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .ticket-item:hover {
            background: #ece925;
            transform: translateX(5px);
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .ticket-number {
            font-weight: 600;
            color: #1b40e4;
        }

        .ticket-title {
            font-weight: 500;
            color: #000000;
            margin-bottom: 5px;
        }

        .ticket-meta {
            font-size: 0.85em;
            color: #000000;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-new { background: #bee3f8; color: #2c5282; }
        .status-assigned { background: #fef5e7; color: #d69e2e; }
        .status-in_progress { background: #fed7d7; color: #e53e3e; }
        .status-resolved { background: #c6f6d5; color: #2f855a; }

        .priority-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .priority-urgent { background: #fed7d7; color: #c53030; }
        .priority-high { background: #feebc8; color: #c05621; }
        .priority-normal { background: #bee3f8; color: #2c5282; }
        .priority-low { background: #e6fffa; color: #285e61; }

        /* Category List */
        .category-list {
            list-style: none;
        }

        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #000000;
        }

        .category-name {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #000000;
        }

        .category-count {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .action-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.2);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(102, 126, 234, 0.4);
        }

        .action-btn i {
            display: block;
            font-size: 1.5em;
            margin-bottom: 8px;
        }

        /* Charts Grid - improved: tighter, consistent cards */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 30px;
            align-items: start;
        }

        .chart-card {
            background: white;
            padding: 22px;
            border-radius: 14px;
            box-shadow: 0 4px 16px rgb(0, 0, 0), 0 8px 24px rgb(0, 0, 0);
            min-height: 320px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        
        .chart-card:hover {
            box-shadow: 0 8px 24px rgb(0, 0, 0), 0 12px 32px rgb(0, 0, 0);
            transform: translateY(-2px);
        }

        .chart-title {
            font-size: 1em;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-container {
            flex: 1;
            min-height: 260px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Detail lists inside chart-cards */
        .detail-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 8px;
            border-bottom: 1px solid #eef2f6;
            color: #2d3748;
            font-size: 0.95em;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        /* Detail Lists */
        .detail-list {
            list-style: none;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 12px;
            border-bottom: 1px solid #e8eef5;
            font-size: 0.95em;
            gap: 12px;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-item-label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            flex: 1;
            color: #2d3748;
            min-width: 0;
        }
        
        .detail-item-label i {
            flex-shrink: 0;
            margin-top: 2px;
            color: #667eea;
        }

        .detail-item-value {
            font-weight: 600;
            background: #f0f6ff;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.9em;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .badge-urgent {
            background: #fed7d7;
            color: #c53030;
        }

        .badge-high {
            background: #feebc8;
            color: #c05621;
        }

        .badge-normal {
            background: #bee3f8;
            color: #2c5282;
        }

        .badge-low {
            background: #e6fffa;
            color: #285e61;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .charts-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .charts-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .breadcrumb-nav {
                flex-direction: column;
                gap: 15px;
            }

            .charts-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .chart-card {
                padding: 16px;
                min-height: 280px;
            }
        }

        /* Larger screens: increase chart heights for readability */
        @media (min-width: 1200px) {
            .chart-container { min-height: 280px; }
            .charts-grid { gap: 28px; }
        }
        
        /* Extra large screens */
        @media (min-width: 1600px) {
            .main-content {
                padding: 40px;
            }
            .stats-grid {
                gap: 28px;
            }
            .charts-grid {
                gap: 32px;
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
                    <li class="active"><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="tickets.php"><i class="fas fa-ticket-alt"></i> IT Tickets</a></li>
                    <li><a href="assets.php"><i class="fas fa-box"></i> สินทรัพย์</a></li>
                    <li><a href="Knowledgebase.php"><i class="fas fa-book"></i> Knowledge Base</a></li>
                    <?php if ($isAdmin): ?>
                    <li class="menu-section">จัดการ</li>
                    <li><a href="users.php"><i class="fas fa-users"></i> ผู้ใช้งาน</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> รายงาน</a></li>
                    <li><a href="slaconfig.php"><i class="fas fa-clock"></i> ตั้งค่า SLA</a></li>
                    <?php endif; ?>
                    
                    <li class="menu-section">ระบบ</li>
                    <?php if ($isAdmin): ?>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> ตั้งค่า</a></li>
                    <?php endif; ?>
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
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="../admin/dashboard.php">
                            <i class="fas fa-home"></i> Romar Dashboard
                        </a>
                    </li>
                    <li>›</li>
                    <li class="breadcrumb-item active">
                        <i class="fas fa-chart-line"></i> IT Support Dashboard
                    </li>
                </ol>
                <a href="../admin/dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    กลับ Romar
                </a>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-chart-line"></i> IT Support Dashboard</h1>
                    <p class="page-subtitle">ภาพรวมระบบจัดการ IT Tickets</p>
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
                            <h3><i class="fas fa-bell"></i> การแจ้งเตือน</h3>
                            <button 
                                onclick="event.stopPropagation(); markAllRead(this)" 
                                id="markAllReadBtn"
                                style="font-size:0.85em; background:#667eea; border:none; color:white; cursor:pointer; 
                                       font-family:'Sarabun',sans-serif; padding:5px 12px; border-radius:6px;
                                       min-width:unset; width:auto; transition:all 0.2s;">
                                <i class="fas fa-check-double"></i> อ่านทั้งหมด
                            </button>
                        </div>
                        <div class="notification-list">
                            <?php if (empty($notifications)): ?>
                            <div class="notification-empty">
                                <i class="fas fa-bell-slash"></i>
                                <p>ไม่มีการแจ้งเตือน</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): 
                                    $isUnread = !$notif['is_read'];
                                    $icon = $notif['type'] === 'new_comment' ? '💬' : '🎫';
                                    $time_diff = time() - strtotime($notif['created_at']);
                                    if      ($time_diff < 60)    $timeText = 'เมื่อสักครู่';
                                    elseif  ($time_diff < 3600)  $timeText = floor($time_diff / 60) . ' นาทีที่แล้ว';
                                    elseif  ($time_diff < 86400) $timeText = floor($time_diff / 3600) . ' ชั่วโมงที่แล้ว';
                                    else                         $timeText = floor($time_diff / 86400) . ' วันที่แล้ว';
                                ?>
                                <div class="notification-item <?php echo $isUnread ? 'unread' : ''; ?>" 
                                     onclick="readAndViewTicket(<?php echo $notif['notif_id']; ?>, <?php echo $notif['ticket_id']; ?>)"
                                     style="<?php echo $isUnread ? 'background:#f0f7ff; border-left:3px solid #4299e1;' : ''; ?>">
                                    <div class="notification-title">
                                        <?php echo $icon; ?>
                                        <?php echo htmlspecialchars($notif['ticket_number']); ?>
                                        <?php if ($isUnread): ?>
                                        <span style="font-size:0.75em; color:#e53e3e; font-weight:700; margin-left:6px;">● NEW</span>
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
            <div style="margin-top: 50px;">
                <h2 style="font-size: 1.6em; font-weight: 700; color: #000000; margin-bottom: 30px; display: flex; align-items: center; gap: 12px; text-transform: capitalize;">
                    <i class="fas fa-boxes" style="color: #ffffff; font-size: 1.4em;"></i> IT Assets ตามประเภท
                </h2>

                <!-- Assets by Type Tiles -->
                <?php if (!empty($assetTypes)): ?>
                <div style="margin-top: 35px;">
                    <h3 style="font-size: 1.1em; font-weight: 600; color: #000000; margin-bottom: 20px; text-transform: capitalize;">ประเภทสินทรัพย์</h3>
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
                        <div class="stat-card" style="background: white; border-left: 5px solid <?php echo $typeMeta['color']; ?>;">
                            <div class="stat-icon" style="background: <?php echo $typeMeta['color']; ?>;">
                                <i class="fas <?php echo $typeMeta['icon']; ?>" style="color: white;"></i>
                            </div>
                            <div class="stat-info">
                                <h3 style="color: #1a202c;"><?php echo number_format($asset['count']); ?></h3>
                                <p style="color: #4a5568;"><?php echo $displayLabel; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- IT Tickets by Category -->
            <div style="margin-top: 40px;">
                <h2 style="font-size: 1.5em; color: #000; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-ticket-alt" style="color: #ffffff;"></i> IT Tickets ตามหมวดหมู่
                </h2>
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
                                <i class="fas fa-folder-open" style="color: white;"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($cat['count']); ?></h3>
                                <p><?php echo ucfirst(htmlspecialchars($cat['category'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            <!-- Recent Tickets & Charts Section -->
            <div style="margin-top: 50px;">
                <h2 style="font-size: 1.6em; font-weight: 700; color: #000000; margin-bottom: 35px; display: flex; align-items: center; gap: 12px; text-transform: capitalize;">
                    <i class="fas fa-document-lines" style="color: #667eea; font-size: 1.4em;"></i> ข้อมูล & การวิเคราะห์
                </h2>
                
                <!-- Charts Grid Section -->
                <div class="charts-grid">
                    <!-- Assets Status Chart -->
                    <div class="chart-card">
                        <h3 class="chart-title"><i class="fas fa-box"></i> สถานะสินทรัพย์</h3>
                        <div class="chart-container">
                            <canvas id="assetsStatusChart"></canvas>
                        </div>
                    </div>

                    <!-- Tickets Status Chart -->
                    <div class="chart-card">
                        <h3 class="chart-title"><i class="fas fa-ticket-alt"></i> สถานะ Tickets</h3>
                        <div class="chart-container">
                            <canvas id="ticketsStatusChart"></canvas>
                        </div>
                    </div>

                    <!-- Tickets Priority Chart -->
                    <div class="chart-card">
                        <h3 class="chart-title"><i class="fas fa-flag"></i> ลำดับความสำคัญ</h3>
                        <div class="chart-container">
                            <canvas id="ticketsPriorityChart"></canvas>
                        </div>
                    </div>

                    <!-- Top Brands -->
                    <?php if (!empty($topBrands)): ?>
                    <div class="chart-card">
                        <h3 class="chart-title"><i class="fas fa-industry"></i> แบรนด์ยอดนิยม</h3>
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
                
                <!-- Recent Tickets Section -->
                <div style="margin-top: 40px;">
                    <h3 style="font-size: 1.2em; font-weight: 700; color: #000000; margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-list" style="color: #ffffff;"></i> Tickets ล่าสุด
                    </h3>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title" style="margin-bottom: 0;"><i class="fas fa-history"></i> ประวัติล่าสุด</h2>
                            <a href="tickets.php" style="color: #000000; text-decoration: none; font-weight: 600; transition: all 0.3s;">
                                ดูทั้งหมด →
                            </a>
                        </div>
                        
                        <?php if (empty($recentTickets)): ?>
                            <p style="text-align: center; color: #718096; padding: 40px;">
                                <i class="fas fa-inbox" style="font-size: 3em; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                                ยังไม่มี Tickets
                            </p>
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
                                        | 
                                        สร้างโดย: <?php echo htmlspecialchars($ticket['creator_name'] ?? 'N/A'); ?>
                                        |
                                        <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'] ?? 0)); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Alerts & Warnings Section -->
            <?php if (!empty($overdueTickets) || !empty($warrantyExpiring)): ?>
            <div style="margin-top: 50px;">
                <h2 style="font-size: 1.6em; font-weight: 700; color: #1a202c; margin-bottom: 30px; display: flex; align-items: center; gap: 12px; text-transform: capitalize;">
                    <i class="fas fa-triangle-exclamation" style="color: #f56565; font-size: 1.4em;"></i> การแจ้งเตือนและข้อมูลสำคัญ
                </h2>

                <div class="charts-grid" style="grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));">
                    <!-- Overdue Tickets -->
                    <?php if (!empty($overdueTickets)): ?>
                    <div class="chart-card" style="border-top: 4px solid #f56565;">
                        <h3 class="chart-title"><i class="fas fa-exclamation-triangle"></i> Tickets ที่เกิน SLA (<?php echo count($overdueTickets); ?>)</h3>
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

                    <!-- Warranty Expiring -->
                    <?php if (!empty($warrantyExpiring)): ?>
                    <div class="chart-card" style="border-top: 4px solid #ed8936;">
                        <h3 class="chart-title"><i class="fas fa-calendar"></i> ประกันจะหมด 30 วัน (<?php echo count($warrantyExpiring); ?>)</h3>
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
            <?php endif; ?>
        </div>
    </div>

    <script>
        // ===== PRG Guard =====
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // ── Toggle dropdown ──────────────────────────────────────────────
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }

        // ปิด dropdown เมื่อคลิกนอก (ไม่ปิดถ้าคลิกข้างใน)
        document.addEventListener('click', function(event) {
            const wrapper  = document.querySelector('.notification-wrapper');
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown && wrapper && !wrapper.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // ── คลิก notification รายการ → mark read → ไป ticket ──────────
        function readAndViewTicket(notifId, ticketId) {
            fetch('../api/marknotificationread.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notif_id: notifId })
            }).finally(() => {
                window.location.href = 'ticket_view.php?id=' + ticketId;
            });
        }

        // ── อ่านทั้งหมด ────────────────────────────────────────────────
        function markAllRead(btn) {
            // แสดง loading บนปุ่ม
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังอัปเดต...';

            fetch('../api/marknotificationread.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mark_all_read: true })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // ── อัปเดต UI ทันที ไม่ต้อง reload ──
                    
                    // 1. ลบ badge กระดิ่ง
                    const badge = document.querySelector('.notification-badge');
                    if (badge) badge.remove();

                    // 2. เอา highlight สีฟ้าออกจากทุก item
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        item.style.background = '';
                        item.style.borderLeft = '';
                        // ลบ ● NEW badge
                        const newBadge = item.querySelector('span[style*="e53e3e"]');
                        if (newBadge) newBadge.remove();
                    });

                    // 3. ซ่อนปุ่ม "อ่านทั้งหมด"
                    btn.style.display = 'none';

                    // 4. แสดง feedback สั้นๆ
                    showToast('✅ อ่านทั้งหมดแล้ว');
                } else {
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                    showToast('❌ เกิดข้อผิดพลาด กรุณาลองใหม่');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                showToast('❌ ไม่สามารถเชื่อมต่อได้');
            });
        }

        // ── Toast notification ────────────────────────────────────────
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

        // ── Initialize Charts ────────────────────────────────────────
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

        // ── Auto-refresh badge ทุก 30 วินาที ─────────────────────────
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
</body>
</html>
