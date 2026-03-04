<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Cannot resolve root path.\n");
    exit(1);
}

/**
 * Load KEY=VALUE config file into environment (without overriding existing env vars)
 */
function load_env_file(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

$configFile = getenv('ROMAR_ALERT_CONFIG_FILE');
if ($configFile === false || trim($configFile) === '') {
    $configFile = $root . '/config/alerts.internal.env';
}
load_env_file((string)$configFile);

$logFile = $root . '/logs/security.log';
if (!file_exists($logFile)) {
    echo "Security log not found, no alerts.\n";
    exit(0);
}

$windowMinutes = (int)(getenv('ROMAR_ALERT_WINDOW_MIN') ?: 15);
$thresholdRate = (int)(getenv('ROMAR_ALERT_RATE_LIMIT') ?: 20);
$thresholdDenied = (int)(getenv('ROMAR_ALERT_ACCESS_DENIED') ?: 20);
$thresholdLoginFail = (int)(getenv('ROMAR_ALERT_LOGIN_FAILED') ?: 30);
$failOnAlert = (getenv('ROMAR_ALERT_FAIL') ?: '0') === '1';
$channel = strtolower(trim((string)(getenv('ROMAR_ALERT_CHANNEL') ?: 'generic')));
$webhookUrl = trim((string)(getenv('ROMAR_ALERT_WEBHOOK_URL') ?: ''));
$emailTo = trim((string)(getenv('ROMAR_ALERT_EMAIL_TO') ?: ''));
$outputJson = trim((string)(getenv('ROMAR_ALERT_OUTPUT_JSON') ?: ''));
$incidentOwner = trim((string)(getenv('ROMAR_INCIDENT_OWNER') ?: 'unassigned'));

$fromTs = time() - ($windowMinutes * 60);
$counts = [
    'rate_limit_blocked' => 0,
    'access_denied' => 0,
    'login_failed' => 0,
];

$lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
foreach ($lines as $line) {
    $row = json_decode($line, true);
    if (!is_array($row)) {
        continue;
    }

    $ts = isset($row['ts']) ? strtotime((string)$row['ts']) : false;
    if ($ts === false || $ts < $fromTs) {
        continue;
    }

    $event = (string)($row['event'] ?? '');
    if (array_key_exists($event, $counts)) {
        $counts[$event]++;
    }
}

$alerts = [];
if ($counts['rate_limit_blocked'] >= $thresholdRate) {
    $alerts[] = "rate_limit_blocked={$counts['rate_limit_blocked']} (threshold {$thresholdRate})";
}
if ($counts['access_denied'] >= $thresholdDenied) {
    $alerts[] = "access_denied={$counts['access_denied']} (threshold {$thresholdDenied})";
}
if ($counts['login_failed'] >= $thresholdLoginFail) {
    $alerts[] = "login_failed={$counts['login_failed']} (threshold {$thresholdLoginFail})";
}

echo "=== Security Alert Check ===\n";
echo "Window: {$windowMinutes} minutes\n";
foreach ($counts as $event => $count) {
    echo "{$event}: {$count}\n";
}

if (empty($alerts)) {
    echo "No alert thresholds reached.\n";
    if ($outputJson !== '') {
        @file_put_contents($outputJson, json_encode([
            'ok' => true,
            'window_minutes' => $windowMinutes,
            'counts' => $counts,
            'alerts' => [],
            'ts' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    exit(0);
}

echo "ALERTS:\n";
foreach ($alerts as $a) {
    echo "- {$a}\n";
}

if ($outputJson !== '') {
    @file_put_contents($outputJson, json_encode([
        'ok' => false,
        'window_minutes' => $windowMinutes,
        'counts' => $counts,
        'alerts' => $alerts,
        'ts' => date('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

if ($webhookUrl !== '') {
    $base = [
        'project' => 'Romar',
        'severity' => 'warning',
        'message' => 'Security alert thresholds reached',
        'incident_owner' => $incidentOwner,
        'window_minutes' => $windowMinutes,
        'counts' => $counts,
        'alerts' => $alerts,
        'ts' => date('c'),
    ];

    if ($channel === 'slack') {
        $payload = json_encode([
            'text' => '*Romar Security Alert*',
            'blocks' => [
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*Romar Security Alert*']],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => implode("\n", $alerts)]],
            ],
            'metadata' => $base,
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($channel === 'teams') {
        $payload = json_encode([
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'summary' => 'Romar Security Alert',
            'themeColor' => 'FFA500',
            'title' => 'Romar Security Alert',
            'text' => implode("\n\n", $alerts),
            'sections' => [[
                'facts' => [
                    ['name' => 'rate_limit_blocked', 'value' => (string)$counts['rate_limit_blocked']],
                    ['name' => 'access_denied', 'value' => (string)$counts['access_denied']],
                    ['name' => 'login_failed', 'value' => (string)$counts['login_failed']],
                ],
            ]],
            'potentialAction' => [],
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $payload = json_encode($base, JSON_UNESCAPED_UNICODE);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 10,
        ],
    ]);
    $response = @file_get_contents($webhookUrl, false, $context);
    if ($response === false) {
        echo "Webhook notification failed.\n";
    } else {
        echo "Webhook notification sent.\n";
    }
}

if ($emailTo !== '') {
    $subject = '[Romar] Security Alert Threshold Reached';
    $body = "Romar security alert was triggered.\n\n";
    $body .= "Window: {$windowMinutes} minutes\n";
    $body .= "Incident owner: {$incidentOwner}\n";
    $body .= "rate_limit_blocked: {$counts['rate_limit_blocked']}\n";
    $body .= "access_denied: {$counts['access_denied']}\n";
    $body .= "login_failed: {$counts['login_failed']}\n\n";
    $body .= "Alerts:\n- " . implode("\n- ", $alerts) . "\n";
    $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
    if (@mail($emailTo, $subject, $body, $headers)) {
        echo "Email notification sent.\n";
    } else {
        echo "Email notification failed.\n";
    }
}

exit($failOnAlert ? 1 : 0);
