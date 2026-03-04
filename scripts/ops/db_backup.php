<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../../config/config.php';

$root = realpath(__DIR__ . '/../../');
if ($root === false) {
    fwrite(STDERR, "Cannot resolve project root.\n");
    exit(1);
}

$host = (string)(getenv('ROMAR_DB_HOST') ?: '127.0.0.1');
$user = (string)(getenv('ROMAR_DB_USER') ?: 'root');
$passEnv = getenv('ROMAR_DB_PASS');
$pass = $passEnv === false ? '' : (string)$passEnv;
$dbName = (string)(getenv('ROMAR_DB_NAME') ?: 'romar_dormitory');

$backupDir = (string)(getenv('ROMAR_DB_BACKUP_DIR') ?: ($root . '/database/backups'));
if (!preg_match('~^[A-Za-z]:[\\/]|^/|^\\\\~', $backupDir)) {
    $backupDir = $root . '/' . ltrim(str_replace('\\', '/', $backupDir), '/');
}

$retentionDays = (int)(getenv('ROMAR_DB_BACKUP_RETENTION_DAYS') ?: '14');
$mysqldump = (string)(getenv('ROMAR_MYSQLDUMP_BIN') ?: 'C:/xampp/mysql/bin/mysqldump.exe');

if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Cannot create backup directory: {$backupDir}\n");
    exit(1);
}

$timestamp = date('Ymd_His');
$filename = sprintf('%s_%s.sql', preg_replace('/[^A-Za-z0-9_-]/', '_', $dbName), $timestamp);
$targetPath = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

$cmdParts = [
    escapeshellarg($mysqldump),
    '--single-transaction',
    '--routines',
    '--triggers',
    '--events',
    '--host=' . escapeshellarg($host),
    '--user=' . escapeshellarg($user),
];
if ($pass !== '') {
    $cmdParts[] = '--password=' . escapeshellarg($pass);
}
$cmdParts[] = escapeshellarg($dbName);
$cmd = implode(' ', $cmdParts) . ' > ' . escapeshellarg($targetPath);

$output = [];
$exitCode = 1;
exec($cmd, $output, $exitCode);
if ($exitCode !== 0 || !file_exists($targetPath) || filesize($targetPath) === 0) {
    if (file_exists($targetPath) && filesize($targetPath) === 0) {
        @unlink($targetPath);
    }
    fwrite(STDERR, "Backup failed. Command exit code: {$exitCode}\n");
    exit(1);
}

$deleted = 0;
if ($retentionDays > 0) {
    $threshold = time() - ($retentionDays * 86400);
    $pattern = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9_-]/', '_', $dbName) . '_*.sql';
    foreach (glob($pattern) ?: [] as $file) {
        if (is_file($file) && filemtime($file) !== false && filemtime($file) < $threshold) {
            if (@unlink($file)) {
                $deleted++;
            }
        }
    }
}

$result = [
    'status' => 'ok',
    'file' => $targetPath,
    'size_bytes' => filesize($targetPath),
    'sha256' => hash_file('sha256', $targetPath),
    'deleted_old_files' => $deleted,
    'timestamp' => date(DATE_ATOM),
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
