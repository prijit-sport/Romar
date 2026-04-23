<?php
/**
 * Romar System Health Dashboard
 * ตรวจสอบสถานะระบบแบบเรียลไทม์
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/logger.php';

class SystemHealth {
    public static function getStatus(): array {
        $status = [
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => APP_ENV,
            'uptime' => self::getUptime(),
            'checks' => []
        ];

        // Database
        $dbStatus = self::checkDatabase();
        $status['checks']['database'] = $dbStatus;

        // Disk usage
        $status['checks']['disk'] = self::checkDisk();

        // PHP version & memory
        $status['checks']['php'] = [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'used_memory' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB'
        ];

        // Session store
        $status['checks']['session'] = self::checkSession();

        // File permissions
        $status['checks']['permissions'] = self::checkPermissions();

        // Security logs (recent)
        $status['checks']['security_logs'] = self::checkSecurityLogs();

        // Overall score
        $score = 0;
        $total = count($status['checks']);
        foreach ($status['checks'] as $check) {
            $score += $check['healthy'] ? 1 : 0;
        }
        $status['health_score'] = round(($score / $total) * 100, 1);

        return $status;
    }

    private static function getUptime(): string {
        $uptime = @shell_exec('uptime -p');
        return $uptime ?: 'N/A';
    }

    private static function checkDatabase(): array {
        try {
            $db = getDB();
            $result = $db->query("SELECT 1");
            return [
                'healthy' => $result !== false,
                'message' => $result ? 'Connected' : 'Query failed',
                'response_time' => 'OK'
            ];
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }

    private static function checkDisk(): array {
        $disk = disk_free_space('.');
        $total = disk_total_space('.');
        $used_pct = (($total - $disk) / $total) * 100;
        
        return [
            'healthy' => $used_pct < 85,
            'used_percent' => round($used_pct, 1),
            'free_gb' => round($disk / 1024 / 1024 / 1024, 1),
            'message' => $used_pct < 85 ? 'OK' : 'High usage'
        ];
    }

    private static function checkSession(): array {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return ['healthy' => true, 'message' => 'Active'];
        }
        session_start();
        $_SESSION['health_check'] = time();
        return [
            'healthy' => isset($_SESSION['health_check']),
            'message' => 'Writable'
        ];
    }

    private static function checkPermissions(): array {
        $critical = ['uploads/', 'logs/', 'config/'];
        $issues = [];
        
        foreach ($critical as $path) {
            if (!is_writable($path)) {
                $issues[] = $path;
            }
        }
        
        return [
            'healthy' => empty($issues),
            'issues' => $issues,
            'message' => empty($issues) ? 'OK' : implode(', ', $issues)
        ];
    }

    private static function checkSecurityLogs(): array {
        $logFile = __DIR__ . '/../logs/security.log';
        if (file_exists($logFile)) {
            $recent = [];
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach (array_slice($lines, -10) as $line) {
                $recent[] = json_decode($line, true);
            }
            return [
                'healthy' => true,
                'recent_events' => count($recent),
                'last_entry' => end($recent)['ts'] ?? 'N/A'
            ];
        }
        return ['healthy' => true, 'message' => 'No logs (fresh install?)'];
    }
}

// API endpoint
if (isset($_GET['json'])) {
    header('Content-Type: application/json');
    echo json_encode(SystemHealth::getStatus(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// HTML Dashboard
$status = SystemHealth::getStatus();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Romar System Health</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; text-align: center; }
        .score { font-size: 3em; font-weight: 300; margin: 0; }
        .healthy { color: #28a745; }
        .warning { color: #ffc107; }
        .critical { color: #dc3545; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-left: 5px solid #dee2e6; }
        .card.healthy { border-left-color: #28a745; }
        .card.warning { border-left-color: #ffc107; }
        .card.critical { border-left-color: #dc3545; }
        .metric { font-size: 2.5em; font-weight: bold; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        .json-btn { position: absolute; top: 20px; right: 20px; background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>
    <button class="json-btn" onclick="fetchJSON()">📊 JSON API</button>
    <div class="container">
        <div class="header">
            <h1>Romar System Health Dashboard</h1>
            <div class="score <?= $status['health_score'] >= 90 ? 'healthy' : ($status['health_score'] >= 70 ? 'warning' : 'critical') ?>">
                <?= $status['health_score'] ?>%
            </div>
            <p><?= $status['timestamp'] ?> | Uptime: <?= $status['uptime'] ?></p>
        </div>

        <div class="grid">
            <?php foreach ($status['checks'] as $name => $check): ?>
            <div class="card <?= $check['healthy'] ? 'healthy' : ($name === 'disk' && isset($check['used_percent']) && $check['used_percent'] < 90 ? 'warning' : 'critical') ?>">
                <h2><?= ucwords(str_replace('_', ' ', $name)) ?></h2>
                <?php if ($check['healthy']): ?>
                    <div class="metric">✅ OK</div>
                <?php else: ?>
                    <div class="metric">❌ Issue</div>
                <?php endif; ?>
                <p><?= $check['message'] ?? 'OK' ?></p>
                <?php if (isset($check['used_percent'])): ?>
                    <div style="width: 100%; height: 20px; background: #eee; border-radius: 10px; overflow: hidden;">
                        <div style="width: <?= $check['used_percent'] ?>%; height: 100%; background: <?= $check['used_percent'] > 85 ? '#dc3545' : ($check['used_percent'] > 70 ? '#ffc107' : '#28a745') ?>;"></div>
                    </div>
                    <small><?= $check['free_gb'] ?> GB free</small>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function fetchJSON() {
            fetch('?json')
                .then(r => r.json())
                .then(data => {
                    console.log('System Status:', data);
                    alert('Health Score: ' + data.health_score + '%');
                });
        }
    </script>
</body>
</html>
