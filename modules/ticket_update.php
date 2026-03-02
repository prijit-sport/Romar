<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = md5(uniqid('', true));
    }
}

function validate_csrf($token) {
    return isset($_SESSION['csrf_token']) && !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$messageType = '';

if (!$ticketId) {
    header('Location: tickets.php');
    exit;
}

// Get ticket details
$stmt = $db->prepare("SELECT * FROM tickets WHERE ticket_id = ?");
$stmt->bind_param('i', $ticketId);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_success'] = 'Invalid CSRF token.';
        header('Location: tickets.php');
        exit;
    }
    $status = sanitize($_POST['status']);
    $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $priority = sanitize($_POST['priority']);
    $urgency = sanitize($_POST['urgency']);
    $impact = sanitize($_POST['impact']);
    $resolution = sanitize($_POST['resolution']);
    
    // Recalculate SLA if priority or impact changed
    $slaHours = calculateSLA($priority, $impact);
    $dueDatetime = date('Y-m-d H:i:s', strtotime($ticket['created_at'] . " +$slaHours hours"));
    
    // ✅ แก้ไขแล้ว: ลบ closed_at ออก
    $stmt = $db->prepare("UPDATE tickets SET 
        status = ?, 
        assigned_to = ?, 
        priority = ?,
        urgency = ?,
        impact = ?,
        resolution = ?,
        sla_due_date = ?,
        updated_at = NOW(),
        resolved_at = CASE WHEN ? IN ('resolved', 'closed') AND resolved_at IS NULL THEN NOW() ELSE resolved_at END
        WHERE ticket_id = ?");
    
    $stmt->bind_param('sissssssi', $status, $assignedTo, $priority, $urgency, $impact, $resolution, $dueDatetime, $status, $ticketId);
    
    if ($stmt->execute()) {
        // ✅ Log changes to timeline - แก้ไขให้ตรงกับ structure จริง
        if ($ticket['status'] != $status) {
            addTimeline($db, $ticketId, 'updated', "Status changed from {$ticket['status']} to $status");
        }
        if ($ticket['assigned_to'] != $assignedTo) {
            $assigneeName = '';
            if ($assignedTo) {
                $assigneeStmt = $db->prepare("SELECT full_name FROM users WHERE user_id = ?");
                $assigneeStmt->bind_param('i', $assignedTo);
                $assigneeStmt->execute();
                $assigneeResult = $assigneeStmt->get_result()->fetch_assoc();
                $assigneeName = $assigneeResult ? $assigneeResult['full_name'] : 'Unknown';
            }
            $description = $assignedTo ? "Ticket assigned to {$assigneeName}" : "Ticket unassigned";
            addTimeline($db, $ticketId, 'assigned', $description);
        }
        if ($ticket['priority'] != $priority) {
            addTimeline($db, $ticketId, 'updated', "Priority changed from {$ticket['priority']} to $priority");
        }
        
        $message = 'อัปเดต Ticket สำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'อัปเดต Ticket', 'Tickets', "Ticket ID: $ticketId");
        
        // Refresh ticket data
        $stmt = $db->prepare("SELECT * FROM tickets WHERE ticket_id = ?");
        $stmt->bind_param('i', $ticketId);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Get IT team members
$itTeam = $db->query("SELECT user_id, full_name FROM users WHERE role IN ('admin', 'it_support') AND status = 'active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

function calculateSLA($priority, $impact) {
    $slaMatrix = [
        'urgent' => ['critical' => 2, 'high' => 4, 'medium' => 8, 'low' => 16],
        'high' => ['critical' => 4, 'high' => 8, 'medium' => 16, 'low' => 24],
        'normal' => ['critical' => 8, 'high' => 16, 'medium' => 24, 'low' => 48],
        'low' => ['critical' => 16, 'high' => 24, 'medium' => 48, 'low' => 72]
    ];
    return $slaMatrix[$priority][$impact] ?? 24;
}

// ✅ แก้ไขให้ตรงกับ structure จริงของตาราง ticket_timeline
// Structure: id, ticket_id, event_type, description, user_id, created_at
function addTimeline($db, $ticketId, $eventType, $description) {
    $stmt = $db->prepare("INSERT INTO ticket_timeline (ticket_id, event_type, description, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('issi', $ticketId, $eventType, $description, $_SESSION['user_id']);
    return $stmt->execute();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Ticket - <?php echo htmlspecialchars($ticket['ticket_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .page-title {
            font-size: 2em;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .ticket-number {
            color: #667eea;
            font-size: 1.2em;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 1em;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1em;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 1.05em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Sarabun', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #718096;
            color: white;
        }

        .btn-secondary:hover {
            background: #4a5568;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            padding-top: 30px;
            border-top: 2px solid #e2e8f0;
        }

        .alert {
            padding: 18px 24px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #38a169;
        }

        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #e53e3e;
        }

        .info-box {
            background: #f7fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            font-size: 0.9em;
            color: #718096;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1.1em;
            color: #2d3748;
            font-weight: 500;
        }

        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .badge-status-new { background: #e3f2fd; color: #1976d2; }
        .badge-status-assigned { background: #f3e5f5; color: #7b1fa2; }
        .badge-status-in_progress { background: #fff3e0; color: #f57c00; }
        .badge-status-resolved { background: #e8f5e9; color: #388e3c; }
        .badge-status-closed { background: #eceff1; color: #455a64; }

        .help-text {
            font-size: 0.85em;
            color: #718096;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .card {
                padding: 25px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1 class="page-title">
                <i class="fas fa-edit"></i> 
                Update Ticket
            </h1>
            <p class="ticket-number"><?php echo htmlspecialchars($ticket['ticket_number']); ?></p>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <div class="info-box">
                <div class="info-label">Ticket Title</div>
                <div class="info-value"><?php echo htmlspecialchars($ticket['title']); ?></div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-circle"></i> Status *
                        </label>
                        <select name="status" class="form-control" required>
                            <option value="new" <?php echo $ticket['status'] === 'new' ? 'selected' : ''; ?>>🔵 New</option>
                            <option value="assigned" <?php echo $ticket['status'] === 'assigned' ? 'selected' : ''; ?>>🟣 Assigned</option>
                            <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>🟡 In Progress</option>
                            <option value="pending" <?php echo $ticket['status'] === 'pending' ? 'selected' : ''; ?>>🟠 Pending</option>
                            <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>🟢 Resolved</option>
                            <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>⚫ Closed</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user-check"></i> Assigned To
                        </label>
                        <select name="assigned_to" class="form-control">
                            <option value="">-- ไม่ระบุ --</option>
                            <?php foreach ($itTeam as $member): ?>
                            <option value="<?php echo $member['user_id']; ?>" 
                                <?php echo $ticket['assigned_to'] == $member['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-flag"></i> Priority *
                        </label>
                        <select name="priority" class="form-control" required>
                            <option value="low" <?php echo $ticket['priority'] === 'low' ? 'selected' : ''; ?>>🟢 Low</option>
                            <option value="normal" <?php echo $ticket['priority'] === 'normal' ? 'selected' : ''; ?>>🔵 Normal</option>
                            <option value="high" <?php echo $ticket['priority'] === 'high' ? 'selected' : ''; ?>>🟠 High</option>
                            <option value="urgent" <?php echo $ticket['priority'] === 'urgent' ? 'selected' : ''; ?>>🔴 Urgent</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-bolt"></i> Urgency *
                        </label>
                        <select name="urgency" class="form-control" required>
                            <option value="low" <?php echo $ticket['urgency'] === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $ticket['urgency'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $ticket['urgency'] === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="critical" <?php echo $ticket['urgency'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-exclamation-circle"></i> Impact *
                    </label>
                    <select name="impact" class="form-control" required>
                        <option value="low" <?php echo $ticket['impact'] === 'low' ? 'selected' : ''; ?>>Low - ส่งผลต่อตัวเอง</option>
                        <option value="medium" <?php echo $ticket['impact'] === 'medium' ? 'selected' : ''; ?>>Medium - ส่งผลต่อทีม</option>
                        <option value="high" <?php echo $ticket['impact'] === 'high' ? 'selected' : ''; ?>>High - ส่งผลต่อแผนก</option>
                        <option value="critical" <?php echo $ticket['impact'] === 'critical' ? 'selected' : ''; ?>>Critical - ส่งผลต่อองค์กร</option>
                    </select>
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i> SLA จะถูกคำนวณใหม่อัตโนมัติตาม Priority และ Impact
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-check-circle"></i> Resolution / Solution
                    </label>
                    <textarea name="resolution" class="form-control" placeholder="อธิบายวิธีการแก้ไข (สำหรับ status: Resolved หรือ Closed)"><?php echo htmlspecialchars($ticket['resolution'] ?? ''); ?></textarea>
                    <div class="help-text">
                        <i class="fas fa-lightbulb"></i> ควรระบุเมื่อเปลี่ยนสถานะเป็น Resolved หรือ Closed
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง
                    </button>
                    <a href="ticket_view.php?id=<?php echo $ticketId; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> ยกเลิก
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>