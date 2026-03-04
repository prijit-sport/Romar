<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/config/database.php';

$db = getDB();
$options = getopt('', ['reset']);
$shouldReset = isset($options['reset']);

$columns = [];
$result = $db->query("SHOW COLUMNS FROM users");
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

if (!in_array('username', $columns, true) || !in_array('password', $columns, true)) {
    fwrite(STDERR, "users table is missing required columns (username/password)." . PHP_EOL);
    exit(1);
}

$seedUsers = [
    [
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'full_name' => 'Administrator',
        'email' => 'admin@romar.local',
        'role' => 'admin',
        'is_active' => 1,
        'status' => 'active',
    ],
    [
        'username' => 'staff1',
        'password' => password_hash('staff123', PASSWORD_DEFAULT),
        'full_name' => 'Staff 1',
        'email' => 'staff1@romar.local',
        'role' => 'staff',
        'is_active' => 1,
        'status' => 'active',
    ],
];

if ($shouldReset) {
    $stmt = $db->prepare("DELETE FROM users WHERE username IN ('admin', 'staff1')");
    $stmt->execute();
    echo "Reset existing admin/staff1 records.\n";
}

foreach ($seedUsers as $userData) {
    $insertColumns = [];
    $insertPlaceholders = [];
    $insertTypes = '';
    $insertValues = [];

    foreach ($userData as $column => $value) {
        if (!in_array($column, $columns, true)) {
            continue;
        }

        $insertColumns[] = $column;
        $insertPlaceholders[] = '?';
        $insertTypes .= is_int($value) ? 'i' : 's';
        $insertValues[] = $value;
    }

    if (in_array('created_at', $columns, true)) {
        $insertColumns[] = 'created_at';
        $insertPlaceholders[] = 'NOW()';
    }

    if (empty($insertColumns)) {
        fwrite(STDERR, "No compatible columns found for seed operation." . PHP_EOL);
        exit(1);
    }

    $updateClauses = [];
    foreach (['password', 'full_name', 'email', 'role', 'is_active', 'status'] as $column) {
        if (in_array($column, $insertColumns, true)) {
            $updateClauses[] = "{$column} = VALUES({$column})";
        }
    }
    if (in_array('updated_at', $columns, true)) {
        $updateClauses[] = "updated_at = NOW()";
    }

    $sql = sprintf(
        "INSERT INTO users (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s",
        implode(', ', $insertColumns),
        implode(', ', $insertPlaceholders),
        implode(', ', $updateClauses)
    );

    $stmt = $db->prepare($sql);
    if (!empty($insertValues)) {
        $stmt->bind_param($insertTypes, ...$insertValues);
    }
    $stmt->execute();

    echo "Upserted user: {$userData['username']}\n";
}

echo "Done.\n";
