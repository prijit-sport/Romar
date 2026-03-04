<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$root = realpath(__DIR__ . '/../../');
if ($root === false) {
    fwrite(STDERR, "Cannot resolve project root.\n");
    exit(1);
}

$envFile = getenv('ROMAR_ENV_FILE');
if ($envFile === false || trim((string)$envFile) === '') {
    $envFile = $root . '/.env';
}
load_env_file((string)$envFile);

$requiredEnv = [
    'ROMAR_APP_ENV',
    'ROMAR_DB_HOST',
    'ROMAR_DB_USER',
    'ROMAR_DB_NAME',
];

$errors = [];
$warnings = [];

foreach ($requiredEnv as $key) {
    $v = getenv($key);
    if ($v === false || trim((string)$v) === '') {
        $errors[] = "Missing required env: {$key}";
    }
}

$env = strtolower((string)(getenv('ROMAR_APP_ENV') ?: 'dev'));
if (!in_array($env, ['dev', 'staging', 'prod', 'production'], true)) {
    $warnings[] = "Unexpected ROMAR_APP_ENV value: {$env}";
}

$phpVersionOk = version_compare(PHP_VERSION, '8.1.0', '>=');
if (!$phpVersionOk) {
    $errors[] = 'PHP 8.1+ is required. Current: ' . PHP_VERSION;
}

foreach (['mysqli', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Missing PHP extension: {$ext}";
    }
}

$logsDir = $root . '/logs';
if (!is_dir($logsDir) && !@mkdir($logsDir, 0755, true)) {
    $errors[] = 'Cannot create logs directory: ' . $logsDir;
}
if (is_dir($logsDir) && !is_writable($logsDir)) {
    $errors[] = 'Logs directory is not writable: ' . $logsDir;
}

$workflow = $root . '/.github/workflows/quality-gates.yml';
if (!file_exists($workflow)) {
    $warnings[] = 'CI workflow missing: .github/workflows/quality-gates.yml';
}

echo "=== Deploy Preflight ===\n";
echo "Root: {$root}\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "APP_ENV: " . ($env ?: 'not-set') . "\n";

if (!empty($warnings)) {
    echo "\nWarnings:\n";
    foreach ($warnings as $w) {
        echo "- {$w}\n";
    }
}

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $e) {
        echo "- {$e}\n";
    }
    exit(1);
}

echo "\nPreflight passed.\n";
exit(0);
