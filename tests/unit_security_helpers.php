<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

putenv('ROMAR_SKIP_DB_BOOT=1');
require_once __DIR__ . '/../includes/functions.php';

function assert_true(bool $cond, string $message): void
{
    if (!$cond) {
        throw new RuntimeException($message);
    }
}

// request_id should be stable per request once generated
$rid1 = request_id();
$rid2 = request_id();
assert_true($rid1 !== '', 'request_id empty');
assert_true($rid1 === $rid2, 'request_id is not stable');

// csp_nonce should be stable per request once generated
$nonce1 = csp_nonce();
$nonce2 = csp_nonce();
assert_true($nonce1 !== '', 'csp_nonce empty');
assert_true($nonce1 === $nonce2, 'csp_nonce is not stable');

// csrf token and verification
$token = csrf_token();
assert_true(verify_csrf($token) === true, 'verify_csrf failed for valid token');
assert_true(verify_csrf('invalid-token') === false, 'verify_csrf should fail for invalid token');

// rate limiter should block after threshold
$key = 'unit_test_rate_limit_' . bin2hex(random_bytes(4));
$r1 = rate_limit_check($key, 2, 60);
$r2 = rate_limit_check($key, 2, 60);
$r3 = rate_limit_check($key, 2, 60);
assert_true($r1['allowed'] === true, 'first attempt should be allowed');
assert_true($r2['allowed'] === true, 'second attempt should be allowed');
assert_true($r3['allowed'] === false, 'third attempt should be blocked');
assert_true($r3['retry_after'] > 0, 'retry_after should be positive when blocked');

// rotate log helper with temp file
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0755, true);
}
$tmpLog = $tmpDir . '/security-test.log';
file_put_contents($tmpLog, str_repeat('x', 2048));
rotate_security_log($tmpLog, 1024, 3);
$archives = glob($tmpDir . '/security-test-*.log') ?: [];
assert_true(count($archives) >= 1, 'rotate_security_log did not rotate oversized file');

echo "Unit security helper tests passed\n";
exit(0);
