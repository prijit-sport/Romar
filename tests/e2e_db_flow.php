<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

function load_env_file(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
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
    }
}

$root = realpath(__DIR__ . '/..');
if ($root !== false) {
    $configFile = getenv('ROMAR_E2E_CONFIG_FILE');
    if ($configFile === false || trim($configFile) === '') {
        $configFile = $root . '/config/e2e.internal.env';
    }
    load_env_file((string)$configFile);
}

if ((getenv('ROMAR_E2E_ENABLE') ?: '0') !== '1') {
    echo "E2E disabled by config (ROMAR_E2E_ENABLE != 1)\n";
    exit(0);
}

// Optional dedicated test DB mapping.
$map = [
    'ROMAR_TEST_DB_HOST' => 'ROMAR_DB_HOST',
    'ROMAR_TEST_DB_USER' => 'ROMAR_DB_USER',
    'ROMAR_TEST_DB_PASS' => 'ROMAR_DB_PASS',
    'ROMAR_TEST_DB_NAME' => 'ROMAR_DB_NAME',
];
foreach ($map as $from => $to) {
    $v = getenv($from);
    if ($v !== false && $v !== '') {
        putenv($to . '=' . $v);
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function has_col(mysqli $db, string $table, string $col): bool
{
    $safeCol = $db->real_escape_string($col);
    $result = $db->query("SHOW COLUMNS FROM {$table} LIKE '{$safeCol}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function insert_dynamic(mysqli $db, string $table, array $data): int
{
    $cols = [];
    $vals = [];
    $types = '';

    foreach ($data as $col => $val) {
        if (!has_col($db, $table, $col)) {
            continue;
        }
        $cols[] = $col;
        $vals[] = $val;
        $types .= is_int($val) ? 'i' : 's';
    }

    if (empty($cols)) {
        throw new RuntimeException("No compatible columns found for {$table}");
    }

    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO {$table} (" . implode(', ', $cols);
    $sql .= has_col($db, $table, 'created_at') ? ', created_at' : '';
    $sql .= ") VALUES ({$placeholders}";
    $sql .= has_col($db, $table, 'created_at') ? ', NOW()' : '';
    $sql .= ")";

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    return (int)$db->insert_id;
}

$db = getDB();
$db->begin_transaction();

try {
    $stamp = date('YmdHis');
    $username = 'e2e_' . $stamp;
    $ticketNumber = 'E2E-' . $stamp;

    $userId = insert_dynamic($db, 'users', [
        'username' => $username,
        'password' => password_hash('pass1234', PASSWORD_DEFAULT),
        'full_name' => 'E2E User',
        'email' => $username . '@local.test',
        'role' => 'admin',
        'is_active' => 1,
        'status' => 'active',
    ]);

    $ticketId = insert_dynamic($db, 'tickets', [
        'ticket_number' => $ticketNumber,
        'title' => 'E2E Test Ticket',
        'description' => 'Created by e2e flow',
        'status' => 'new',
        'created_by' => $userId,
        'category' => 'general',
        'priority' => 'normal',
        'urgency' => 'normal',
        'impact' => 'low',
        'location' => 'E2E',
    ]);

    if (has_col($db, 'ticket_comments', 'is_internal')) {
        $stmtComment = $db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) VALUES (?, ?, ?, 0, NOW())");
    } else {
        $stmtComment = $db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())");
    }
    $comment = 'E2E comment';
    $stmtComment->bind_param('iis', $ticketId, $userId, $comment);
    $stmtComment->execute();

    $allow = can_access_ticket($db, $ticketId, $userId, false);
    $deny = can_access_ticket($db, $ticketId, $userId + 999999, false);
    if (!$allow || $deny) {
        throw new RuntimeException('Ownership guard check failed.');
    }

    $status = 'in_progress';
    $stmtUpdate = $db->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE ticket_id = ?");
    $stmtUpdate->bind_param('si', $status, $ticketId);
    $stmtUpdate->execute();

    $stmtVerify = $db->prepare("SELECT status FROM tickets WHERE ticket_id = ?");
    $stmtVerify->bind_param('i', $ticketId);
    $stmtVerify->execute();
    $row = $stmtVerify->get_result()->fetch_assoc();
    if (!$row || $row['status'] !== $status) {
        throw new RuntimeException('Ticket update verification failed.');
    }

    echo "E2E flow passed: user/ticket/comment/update/authorization\n";
    $db->rollback();

    $stmtRollback = $db->prepare("SELECT COUNT(*) AS c FROM tickets WHERE ticket_number = ?");
    $stmtRollback->bind_param('s', $ticketNumber);
    $stmtRollback->execute();
    $cnt = (int)($stmtRollback->get_result()->fetch_assoc()['c'] ?? 0);
    if ($cnt !== 0) {
        throw new RuntimeException('Rollback verification failed.');
    }

    echo "Rollback passed\n";
    exit(0);
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, "E2E flow failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
