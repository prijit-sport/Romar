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

// Handle Update SLA Rule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $slaId = (int)$_POST['sla_id'];
    $responseTime = (int)$_POST['response_time_hours'];
    $resolutionTime = (int)$_POST['resolution_time_hours'];
    
    $stmt = $db->prepare("UPDATE sla_rules SET response_time_hours = ?, resolution_time_hours = ? WHERE sla_id = ?");
    $stmt->bind_param('iii', $responseTime, $resolutionTime, $slaId);
    
    if ($stmt->execute()) {
        $message = 'อัปเดต SLA Rule สำเร็จ!';
        $messageType = 'success';
        logActivity($_SESSION['user_id'], 'อัปเดต SLA Rule', 'SLA', "SLA ID: $slaId");
    } else {
        $message = 'เกิดข้อผิดพลาด: ' . $stmt->error;
        $messageType = 'error';
    }
}

// Get all SLA rules
$slaRules = $db->query("SELECT * FROM sla_rules ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low'), FIELD(impact, 'critical', 'high', 'medium', 'low')")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLA Configuration - IT Support</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient( #065f159c);
            padding: 30px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 2em;
            font-weight: 700;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-secondary {
            background: #718096;
            color: white;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .card-title {
            font-size: 1.5em;
            font-weight: 700;
            margin-bottom: 20px;
            color: #1a202c;
        }

        .alert {
            padding: 18px 24px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #38a169;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 700;
        }

        tr:hover {
            background: #f7fafc;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
        }

        .badge-urgent { background: #fed7d7; color: #742a2a; }
        .badge-high { background: #feebc8; color: #7c2d12; }
        .badge-normal { background: #bee3f8; color: #2c5282; }
        .badge-low { background: #c6f6d5; color: #22543d; }

        .badge-critical { background: #fed7d7; color: #742a2a; }
        .badge-medium { background: #feebc8; color: #7c2d12; }

        input[type="number"] {
            width: 80px;
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95em;
            font-family: 'Sarabun', sans-serif;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9em;
        }

        .info-box {
            background: #e6fffa;
            border-left: 4px solid #319795;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .info-title {
            font-weight: 700;
            color: #234e52;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .matrix-table {
            overflow-x: auto;
        }

        .matrix-table table {
            min-width: 800px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-clock"></i> SLA Configuration
            </h1>
            <a href="tickets.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> กลับ
            </a>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas fa-check-circle"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <div class="info-box">
            <div class="info-title">
                <i class="fas fa-info-circle"></i> เกี่ยวกับ SLA (Service Level Agreement)
            </div>
            <p style="line-height: 1.7; color: #2d3748;">
                SLA กำหนดระยะเวลาที่ทีม IT ต้องตอบสนองและแก้ไขปัญหา โดยคำนวณจาก <strong>Priority</strong> และ <strong>Impact</strong> ของ Ticket<br>
                • <strong>Response Time</strong>: เวลาที่ต้องเริ่มดำเนินการ (มอบหมายหรือตอบกลับ)<br>
                • <strong>Resolution Time</strong>: เวลาที่ต้องแก้ไขเสร็จสิ้น<br>
                • เวลาคำนวณเป็นชั่วโมง (Business Hours)
            </p>
        </div>

        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-table"></i> SLA Matrix (Priority × Impact)
            </h2>
            
            <div class="matrix-table">
                <table>
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Impact</th>
                            <th>Response Time (hours)</th>
                            <th>Resolution Time (hours)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slaRules as $rule): ?>
                        <tr>
                            <td>
                                <span class="badge badge-<?php echo $rule['priority']; ?>">
                                    <?php echo strtoupper($rule['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $rule['impact']; ?>">
                                    <?php echo strtoupper($rule['impact']); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="sla_id" value="<?php echo $rule['sla_id']; ?>">
                                    <input type="number" name="response_time_hours" value="<?php echo $rule['response_time_hours']; ?>" min="1" max="168" required>
                            </td>
                            <td>
                                    <input type="number" name="resolution_time_hours" value="<?php echo $rule['resolution_time_hours']; ?>" min="1" max="720" required>
                            </td>
                            <td>
                                    <button type="submit" class="btn-save">
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-lightbulb"></i> Best Practices
            </h2>
            <ul style="line-height: 2; color: #4a5568; padding-left: 25px;">
                <li><strong>Urgent + Critical</strong>: ปัญหาร้ายแรงที่ส่งผลกระทบทั้งองค์กร (ระบบล่ม, Security breach)</li>
                <li><strong>High + High</strong>: ปัญหาที่ส่งผลต่อหลายแผนก (Email server down, Network outage)</li>
                <li><strong>Normal + Medium</strong>: ปัญหาทั่วไปที่ส่งผลต่อทีมงาน (Printer จาม, Software error)</li>
                <li><strong>Low + Low</strong>: คำขอธรรมดา (Password reset, Permission request)</li>
                <li>ควรตั้งค่า Response Time ให้สั้นกว่า Resolution Time เสมอ</li>
                <li>พิจารณา Business Hours ของบริษัท (8x5, 12x5, 24x7)</li>
                <li>ทบทวน SLA ทุก 3-6 เดือนตามข้อมูลจริง</li>
            </ul>
        </div>

        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-calculator"></i> SLA Calculation Example
            </h2>
            <div style="background: #f7fafc; padding: 20px; border-radius: 10px; font-family: monospace;">
                <strong>Scenario:</strong> Ticket สร้างเวลา 09:00 น. วันจันทร์<br>
                Priority: <span class="badge badge-high">HIGH</span> 
                Impact: <span class="badge badge-critical">CRITICAL</span><br><br>
                
                <strong>SLA Rules:</strong><br>
                • Response Time: 2 hours → ต้องตอบสนองภายใน 11:00 น.<br>
                • Resolution Time: 4 hours → ต้องแก้ไขเสร็จภายใน 13:00 น.<br><br>
                
                <strong>Status Indicators:</strong><br>
                • 🟢 Green: เหลือเวลามากกว่า 2 ชม.<br>
                • 🟡 Yellow: เหลือเวลาน้อยกว่า 2 ชม.<br>
                • 🔴 Red: เกินเวลาที่กำหนด (Overdue)
            </div>
        </div>
    </div>

    <script>
        // Auto-submit forms are disabled to prevent accidental changes
        // Users must click Save button explicitly
    </script>
</body>
</html>