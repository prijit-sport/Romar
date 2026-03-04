<?php
declare(strict_types=1);

// Quarantined legacy script: allow diagnostics from CLI only.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/config/database.php';

echo "Romar Diagnostic (CLI only)\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Timestamp: " . date('c') . "\n";

try {
    $db = getDB();
    $counts = [
        'users' => 'SELECT COUNT(*) AS cnt FROM users',
        'tickets' => 'SELECT COUNT(*) AS cnt FROM tickets',
        'documents' => 'SELECT COUNT(*) AS cnt FROM documents',
    ];

    foreach ($counts as $label => $sql) {
        $result = $db->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        $count = $row['cnt'] ?? 0;
        echo strtoupper($label) . ": {$count}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Database diagnostic failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "OK\n";
