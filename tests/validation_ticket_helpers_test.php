<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/ticket_helpers.php';

$tests = [
    [
        'name' => 'validate_batch accepts full user payload',
        'pass' => function () {
            $result = validate_batch([
                'username' => ['value' => 'tester01', 'type' => 'username'],
                'full_name' => ['value' => 'Romar Tester', 'type' => 'string', 'minLen' => 3],
                'email' => ['value' => 'tester@example.com', 'type' => 'email'],
                'role' => ['value' => 'admin', 'type' => 'role'],
                'phone' => ['value' => '0812345678', 'type' => 'phone', 'required' => false],
            ]);
            return $result['valid'] === true && empty($result['errors']);
        },
    ],
    [
        'name' => 'validate_batch rejects invalid email',
        'pass' => function () {
            $result = validate_batch([
                'email' => ['value' => 'invalid-email', 'type' => 'email'],
            ]);
            return $result['valid'] === false && isset($result['errors']['email']);
        },
    ],
    [
        'name' => 'validate_password enforces complexity',
        'pass' => function () {
            $weak = validate_password('short');
            $strong = validate_password('StrongP@ssw0rd!');
            return $weak['valid'] === false && $strong['valid'] === true;
        },
    ],
    [
        'name' => 'calculateSLA honors matrix',
        'pass' => function () {
            return calculateSLA('urgent', 'critical') === 2
                && calculateSLA('high', 'medium') === 16;
        },
    ],
    [
        'name' => 'calculateSLA defaults when unknown',
        'pass' => function () {
            return calculateSLA('missing', 'missing') === 24;
        },
    ],
];

$passed = 0;
$total = count($tests);
foreach ($tests as $test) {
    $ok = false;
    try {
        $ok = (bool)$test['pass']();
    } catch (Throwable $e) {
        $ok = false;
    }

    $status = $ok ? 'PASS' : 'FAIL';
    echo "[{$status}] {$test['name']}\n";

    if ($ok) {
        $passed++;
    }
}

$failures = $total - $passed;
echo "\nSummary: {$passed}/{$total} passed.\n";
exit($failures === 0 ? 0 : 1);
