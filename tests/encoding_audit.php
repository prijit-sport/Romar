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

$extensions = ['php', 'js', 'css', 'html'];
$invalid = [];
$total = 0;

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($rii as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions, true)) {
        continue;
    }

    $total++;
    $path = $file->getPathname();
    $content = @file_get_contents($path);
    if ($content === false) {
        $invalid[] = $path . ' (read failed)';
        continue;
    }

    if (!mb_check_encoding($content, 'UTF-8')) {
        $invalid[] = $path;
    }
}

echo "=== Encoding Audit (UTF-8) ===\n";
echo "Scanned files: {$total}\n";
echo "Invalid files: " . count($invalid) . "\n";

if (!empty($invalid)) {
    foreach ($invalid as $path) {
        echo "[INVALID] {$path}\n";
    }
    exit(1);
}

echo "All scanned files are valid UTF-8.\n";
exit(0);
