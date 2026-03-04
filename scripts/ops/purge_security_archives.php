<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

$root = realpath(__DIR__ . '/../../');
if ($root === false) {
    fwrite(STDERR, "Cannot resolve project root.\n");
    exit(1);
}

$logsDir = $root . '/logs';
$retentionDays = (int)(getenv('ROMAR_LOG_ARCHIVE_RETENTION_DAYS') ?: 30);
$retentionDays = max(1, $retentionDays);
$cutoff = time() - ($retentionDays * 86400);

if (!is_dir($logsDir)) {
    echo "Logs directory not found. Nothing to purge.\n";
    exit(0);
}

$files = glob($logsDir . '/security-*.log') ?: [];
$deleted = 0;

foreach ($files as $file) {
    $mtime = @filemtime($file);
    if ($mtime === false) {
        continue;
    }
    if ($mtime < $cutoff) {
        if (@unlink($file)) {
            $deleted++;
        }
    }
}

echo "Purged {$deleted} security archive file(s) older than {$retentionDays} day(s).\n";
exit(0);
