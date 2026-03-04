<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

function file_has(string $path, string $needle): bool
{
    $content = @file_get_contents($path);
    return $content !== false && strpos($content, $needle) !== false;
}

$checks = [
    [
        'name' => 'Login form includes CSRF input',
        'pass' => file_has($root . '/auth/login.php', 'csrf_input()'),
    ],
    [
        'name' => 'Login has rate limit control',
        'pass' => file_has($root . '/auth/login.php', "rate_limit_check('auth_login'"),
    ],
    [
        'name' => 'Tickets update action has ownership guard',
        'pass' => file_has($root . '/modules/tickets.php', 'can_access_ticket($db, $ticketId'),
    ],
    [
        'name' => 'Notification count API has rate limit',
        'pass' => file_has($root . '/api/getnotificationcount.php', "rate_limit_check('api_getnotificationcount'"),
    ],
    [
        'name' => 'Notification list API has request id',
        'pass' => file_has($root . '/api/getnotifications.php', 'request_id()'),
    ],
    [
        'name' => 'Notification mark-read API sanitizes failures',
        'pass' => file_has($root . '/api/marknotificationread.php', "json_error('Invalid request'"),
    ],
    [
        'name' => 'Database config reads env credentials',
        'pass' => file_has($root . '/config/database.php', "getenv('ROMAR_DB_HOST')"),
    ],
    [
        'name' => 'E2E DB flow test script exists',
        'pass' => file_exists($root . '/tests/e2e_db_flow.php'),
    ],
    [
        'name' => 'Unit security helper test script exists',
        'pass' => file_exists($root . '/tests/unit_security_helpers.php'),
    ],
    [
        'name' => 'Security alert check script exists',
        'pass' => file_exists($root . '/tests/security_alert_check.php'),
    ],
    [
        'name' => 'Deploy preflight script exists',
        'pass' => file_exists($root . '/scripts/ops/deploy_preflight.php'),
    ],
    [
        'name' => 'Security monitor workflow exists',
        'pass' => file_exists($root . '/.github/workflows/security-monitor.yml'),
    ],
    [
        'name' => 'Deploy checklist document exists',
        'pass' => file_exists($root . '/docs/DEPLOY_CHECKLIST.md'),
    ],
    [
        'name' => 'Internal E2E config exists',
        'pass' => file_exists($root . '/config/e2e.internal.env'),
    ],
];

$total = count($checks);
$pass = count(array_filter($checks, fn(array $c): bool => $c['pass']));
$score = $total > 0 ? ($pass / $total) * 100 : 0;

echo "=== Integration Smoke Checks ===\n";
foreach ($checks as $check) {
    echo ($check['pass'] ? '[PASS] ' : '[FAIL] ') . $check['name'] . "\n";
}
echo "Score: {$pass}/{$total} (" . number_format($score, 2) . "%)\n";

exit($pass === $total ? 0 : 1);
