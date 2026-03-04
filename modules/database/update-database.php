<?php
declare(strict_types=1);

// Quarantined legacy migration script: CLI only.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../../config/database.php';

$db = getDB();

$requiredColumns = [
    'avatar' => 'VARCHAR(255) NULL',
    'phone' => 'VARCHAR(50) NULL',
    'department' => 'VARCHAR(255) NULL',
];

$columns = [];
$result = $db->query("SHOW COLUMNS FROM users");
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

foreach ($requiredColumns as $column => $definition) {
    if (in_array($column, $columns, true)) {
        echo "Column already exists: {$column}\n";
        continue;
    }

    $sql = "ALTER TABLE users ADD COLUMN {$column} {$definition}";
    if ($db->query($sql)) {
        echo "Added column: {$column}\n";
    } else {
        fwrite(STDERR, "Failed to add {$column}: {$db->error}\n");
        exit(1);
    }
}

echo "Migration complete.\n";
