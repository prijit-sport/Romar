<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/config.php';

function health_get_header_token(): string
{
    $token = '';

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strtolower((string)$key) === 'x-health-token') {
                    $token = is_string($value) ? trim($value) : '';
                    break;
                }
            }
        }
    }

    if ($token === '' && isset($_SERVER['HTTP_X_HEALTH_TOKEN'])) {
        $token = trim((string)$_SERVER['HTTP_X_HEALTH_TOKEN']);
    }

    if ($token === '' && isset($_GET['token'])) {
        $token = trim((string)$_GET['token']);
    }

    return $token;
}

$expectedToken = (string)(getenv('ROMAR_HEALTH_TOKEN') ?: '');
if ($expectedToken === '') {
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'message' => 'health token is not configured',
        'timestamp' => date(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$providedToken = health_get_header_token();
if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'unauthorized',
        'timestamp' => date(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$phpStatus = [
    'status' => 'up',
    'version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
];

$serverSoftware = isset($_SERVER['SERVER_SOFTWARE']) ? (string)$_SERVER['SERVER_SOFTWARE'] : '';
$apacheDetected = $serverSoftware !== '' && stripos($serverSoftware, 'apache') !== false;
$apacheStatus = [
    'status' => $apacheDetected ? 'up' : (PHP_SAPI === 'cli' ? 'unknown' : 'down'),
    'server_software' => $serverSoftware !== '' ? $serverSoftware : 'unknown',
    'modules_count' => function_exists('apache_get_modules') ? count((array)apache_get_modules()) : null,
];

$host = (string)(getenv('ROMAR_DB_HOST') ?: '127.0.0.1');
$user = (string)(getenv('ROMAR_DB_USER') ?: 'root');
$passEnv = getenv('ROMAR_DB_PASS');
$pass = $passEnv === false ? '' : (string)$passEnv;
$name = (string)(getenv('ROMAR_DB_NAME') ?: 'romar_dormitory');

$dbStatus = [
    'status' => 'down',
    'latency_ms' => null,
    'database' => $name,
    'host' => $host,
];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $started = microtime(true);
    $db = new mysqli($host, $user, $pass, $name);
    $db->set_charset('utf8mb4');
    $query = $db->query('SELECT 1 AS ok');
    $row = $query instanceof mysqli_result ? $query->fetch_assoc() : null;
    $db->close();

    $dbStatus['status'] = ((int)($row['ok'] ?? 0) === 1) ? 'up' : 'down';
    $dbStatus['latency_ms'] = (int)round((microtime(true) - $started) * 1000);
} catch (Throwable $e) {
    $dbStatus['status'] = 'down';
}

$overallOk = ($phpStatus['status'] === 'up') && ($dbStatus['status'] === 'up') && ($apacheStatus['status'] !== 'down');
http_response_code($overallOk ? 200 : 503);

echo json_encode([
    'status' => $overallOk ? 'ok' : 'error',
    'timestamp' => date(DATE_ATOM),
    'checks' => [
        'apache' => $apacheStatus,
        'php' => $phpStatus,
        'db' => $dbStatus,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
