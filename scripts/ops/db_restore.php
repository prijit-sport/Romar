<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../../config/config.php';

$options = getopt('', ['file:', 'yes', 'drop-existing']);
$file = isset($options['file']) ? (string)$options['file'] : '';
$approved = array_key_exists('yes', $options);
$dropExisting = array_key_exists('drop-existing', $options);

if ($file === '' || !$approved) {
    fwrite(STDERR, "Usage: php scripts/ops/db_restore.php --file=PATH_TO_SQL --yes [--drop-existing]\n");
    exit(1);
}

if (!is_file($file) || !is_readable($file)) {
    fwrite(STDERR, "Backup file not found or unreadable: {$file}\n");
    exit(1);
}

$host = (string)(getenv('ROMAR_DB_HOST') ?: '127.0.0.1');
$user = (string)(getenv('ROMAR_DB_USER') ?: 'root');
$passEnv = getenv('ROMAR_DB_PASS');
$pass = $passEnv === false ? '' : (string)$passEnv;
$dbName = (string)(getenv('ROMAR_DB_NAME') ?: 'romar_dormitory');
$mysqlBin = (string)(getenv('ROMAR_MYSQL_BIN') ?: 'C:/xampp/mysql/bin/mysql.exe');

$baseCmd = [
    escapeshellarg($mysqlBin),
    '--host=' . escapeshellarg($host),
    '--user=' . escapeshellarg($user),
];
if ($pass !== '') {
    $baseCmd[] = '--password=' . escapeshellarg($pass);
}

if ($dropExisting) {
    $sql = sprintf(
        'DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
        str_replace('`', '``', $dbName),
        str_replace('`', '``', $dbName)
    );

    $dropCmd = implode(' ', $baseCmd) . ' -e ' . escapeshellarg($sql);
    $dropOutput = [];
    $dropExit = 1;
    exec($dropCmd, $dropOutput, $dropExit);
    if ($dropExit !== 0) {
        fwrite(STDERR, "Failed to recreate database. Exit code: {$dropExit}\n");
        exit(1);
    }
}

$importCmd = implode(' ', $baseCmd) . ' ' . escapeshellarg($dbName) . ' < ' . escapeshellarg($file);
$importOutput = [];
$importExit = 1;
exec($importCmd, $importOutput, $importExit);

if ($importExit !== 0) {
    fwrite(STDERR, "Restore failed. Exit code: {$importExit}\n");
    exit(1);
}

echo json_encode([
    'status' => 'ok',
    'restored_file' => realpath($file) ?: $file,
    'database' => $dbName,
    'timestamp' => date(DATE_ATOM),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
