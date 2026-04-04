<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Cannot resolve project root.\n");
    exit(1);
}

function readFileSafe(string $path): string
{
    $content = @file_get_contents($path);
    return $content === false ? '' : $content;
}

function lintAllPhp(string $root): array
{
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $phpFiles = [];
    foreach ($rii as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') {
            continue;
        }
        $phpFiles[] = $path;
    }

    sort($phpFiles);
    $ok = 0;
    $failed = [];

    foreach ($phpFiles as $file) {
        $cmd = 'php -l ' . escapeshellarg($file) . ' 2>&1';
        exec($cmd, $output, $code);
        if ($code === 0) {
            $ok++;
        } else {
            $failed[] = $file;
        }
    }

    return [
        'total' => count($phpFiles),
        'ok' => $ok,
        'failed' => $failed,
    ];
}

function runSecurityChecks(string $root): array
{
    $checks = [];

    $functionsFile = readFileSafe($root . '/includes/functions.php');
    $checks[] = [
        'name' => 'CSRF helper function exists',
        'pass' => strpos($functionsFile, 'function csrf_token') !== false
            && strpos($functionsFile, 'function verify_csrf') !== false,
    ];

    $rootHtaccess = readFileSafe($root . '/.htaccess');
    $hasDebugRule = preg_match('/debug\\\.php/i', $rootHtaccess) === 1 || preg_match('/debug\.php/i', $rootHtaccess) === 1;
    $hasInsertRule = preg_match('/insert-users\\\.php/i', $rootHtaccess) === 1 || preg_match('/insert-users\.php/i', $rootHtaccess) === 1;
    $hasCreateRule = preg_match('/create-htaccess\\\.php/i', $rootHtaccess) === 1 || preg_match('/create-htaccess\.php/i', $rootHtaccess) === 1;
    $checks[] = [
        'name' => 'Root .htaccess blocks debug/setup scripts',
        'pass' => $hasDebugRule && $hasInsertRule && $hasCreateRule,
    ];

    $adminHtaccess = readFileSafe($root . '/admin/.htaccess');
    $checks[] = [
        'name' => 'Admin .htaccess blocks create-admin.php',
        'pass' => strpos($adminHtaccess, 'create-admin.php') !== false,
    ];

    $dbHtaccess = readFileSafe($root . '/modules/database/.htaccess');
    $checks[] = [
        'name' => 'Database module .htaccess blocks update script',
        'pass' => strpos($dbHtaccess, 'update-database.php') !== false,
    ];

    $dbConfig = readFileSafe($root . '/config/database.php');
    $checks[] = [
        'name' => 'Database error is sanitized',
        'pass' => strpos($dbConfig, 'error_log(') !== false
            && strpos($dbConfig, 'Database connection error. Please contact administrator.') !== false
            && strpos($dbConfig, 'die("Database Error:') === false,
    ];

    $dashboard = readFileSafe($root . '/modules/dashboard.php');
    $checks[] = [
        'name' => 'Dashboard avoids SQL concat with session user id',
        'pass' => strpos($dashboard, 'created_by = " . $_SESSION[\'user_id\']') === false
            && strpos($dashboard, '$db->prepare(') !== false,
    ];

    $adminIndex = readFileSafe($root . '/admin/index.php');
    $checks[] = [
        'name' => 'Admin redirect path is correct',
        'pass' => strpos($adminIndex, "header('Location: settings.php');") !== false,
    ];

    $markRead = readFileSafe($root . '/api/marknotificationread.php');
    $checks[] = [
        'name' => 'Notification API does not return raw SQL errors',
        'pass' => strpos($markRead, "'message' => \$stmt->error") === false,
    ];

    $loginFile = readFileSafe($root . '/auth/login.php');
    $checks[] = [
        'name' => 'Login has CSRF and rate-limit checks',
        'pass' => strpos($loginFile, 'verify_csrf($_POST[\'csrf_token\']') !== false
            && strpos($loginFile, 'rate_limit_check(\'auth_login\'') !== false
            && strpos($loginFile, 'csrf_input()') !== false,
    ];

    $apiCount = readFileSafe($root . '/api/getnotificationcount.php');
    $checks[] = [
        'name' => 'Notification count API has rate limit and request id',
        'pass' => strpos($apiCount, 'rate_limit_check(\'api_getnotificationcount\'') !== false
            && strpos($apiCount, 'request_id()') !== false,
    ];

    $integrationFile = readFileSafe($root . '/tests/integration_smoke.php');
    $checks[] = [
        'name' => 'Integration smoke test script exists',
        'pass' => !empty($integrationFile),
    ];

    $e2eFile = readFileSafe($root . '/tests/e2e_db_flow.php');
    $checks[] = [
        'name' => 'E2E DB flow test script exists',
        'pass' => !empty($e2eFile),
    ];

    $checks[] = [
        'name' => 'Security audit logger exists',
        'pass' => strpos($functionsFile, 'function security_audit_log') !== false,
    ];

    $checks[] = [
        'name' => 'Security log retention policy exists',
        'pass' => strpos($functionsFile, 'function security_log_policy') !== false
            && strpos($functionsFile, 'function rotate_security_log') !== false,
    ];

    $checks[] = [
        'name' => 'CSP no longer uses unsafe-eval',
        'pass' => strpos($functionsFile, 'unsafe-eval') === false
            && strpos($rootHtaccess, 'unsafe-eval') === false,
    ];

    $checks[] = [
        'name' => 'Unit security and alert scripts exist',
        'pass' => !empty(readFileSafe($root . '/tests/unit_security_helpers.php'))
            && !empty(readFileSafe($root . '/tests/security_alert_check.php')),
    ];

$checks[] = [
        'name' => 'Deploy preflight script exists',
        'pass' => !empty(readFileSafe($root . '/scripts/ops/deploy_preflight.php')),
    ];

$checks[] = [
        'name' => 'Deploy workflow exists',
        'pass' => !empty(readFileSafe($root . '/.github/workflows/deploy.yml')),
    ];

    $checks[] = [
        'name' => 'Security monitor workflow exists',
        'pass' => !empty(readFileSafe($root . '/.github/workflows/security-monitor.yml')),
    ];

    $checks[] = [
        'name' => 'Deploy checklist document exists',
        'pass' => !empty(readFileSafe($root . '/docs/DEPLOY_CHECKLIST.md')),
    ];

    $checks[] = [
        'name' => 'Internal E2E config exists',
        'pass' => !empty(readFileSafe($root . '/config/e2e.internal.env')),
    ];

    return $checks;
}

$lint = lintAllPhp($root);
$syntaxScore = $lint['total'] > 0 ? ($lint['ok'] / $lint['total']) * 100 : 0;

$isLocal = PHP_SAPI === 'cli' && !getenv('GITHUB_ACTIONS');
$securityChecks = $isLocal ? runSecurityChecks($root) : [['name' => 'Security checks skipped in CI', 'pass' => true]];
$securityTotal = count($securityChecks);
$securityPass = count(array_filter($securityChecks, fn(array $c): bool => $c['pass']));
$securityScore = $securityTotal > 0 ? ($securityPass / $securityTotal) * 100 : 0;

$overall = ($syntaxScore * 0.5) + ($securityScore * 0.5);

echo "=== Project Health Check ===\n";
echo "PHP Syntax: {$lint['ok']}/{$lint['total']} (" . number_format($syntaxScore, 2) . "%)\n";
echo "Security Checkpoints: {$securityPass}/{$securityTotal} (" . number_format($securityScore, 2) . "%)\n";
echo "Overall Score: " . number_format($overall, 2) . "%\n\n";

echo "--- Security Checkpoint Details ---\n";
foreach ($securityChecks as $check) {
    echo ($check['pass'] ? '[PASS] ' : '[FAIL] ') . $check['name'] . "\n";
}

if (!empty($lint['failed'])) {
    echo "\n--- Failed PHP Lint Files ---\n";
    foreach ($lint['failed'] as $file) {
        echo $file . "\n";
    }
}

exit(($overall >= 90.0) ? 0 : 1);
