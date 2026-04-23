<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Ensure every user-facing module (admin/modules/api/index) starts sessions safely
 * and every POST form includes a CSRF token.
 */

$directories = array_merge(
    ['index.php'],
    glob('admin/*.php') ?: array(),
    glob('modules/*.php') ?: array(),
    glob('api/*.php') ?: array(),
    glob('auth/*.php') ?: array()
);

$sessionPatterns = array(
    'session_status() === PHP_SESSION_NONE',
    'session_status() !== PHP_SESSION_ACTIVE',
);

$sessionFailures = array();
$csrfFailures = array();

foreach ($directories as $path) {
    if (!is_file($path)) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    if (stripos($content, 'PHP_SAPI !== "cli"') !== false) {
        // CLI-only helpers skip
        continue;
    }

    $shouldCheckSession = preg_match('#auth[/\\\\]+#i', $path) !== 1;
    if ($shouldCheckSession) {
        $hasSessionGuard = false;
        foreach ($sessionPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $hasSessionGuard = true;
                break;
            }
        }
        if (!$hasSessionGuard) {
            $sessionFailures[] = $path;
        }
    }

    // Check POST forms for CSRF token
    if (preg_match_all('~<form\\b[^>]*method=["\']post["\']~i', $content) > 0) {
        $hasCsrf = stripos($content, 'csrf_input') !== false
            || stripos($content, 'csrf_token') !== false
            || stripos($content, 'name="csrf_token"') !== false
            || stripos($content, "name='csrf_token'") !== false;
        if (!$hasCsrf) {
            $csrfFailures[] = $path;
        }
    }
}

$exitCode = 0;

echo "SESSION CHECK:\n";
if ($sessionFailures === array()) {
    echo "  All entry files guard session_start().\n";
} else {
    $exitCode = 1;
    echo "  Missing session guard in:\n";
    foreach ($sessionFailures as $miss) {
        echo "    - $miss\n";
    }
}

echo "\nCSRF FORM CHECK (POST):\n";
if ($csrfFailures === array()) {
    echo "  Every POST form references csrf_input()/csrf_token.\n";
} else {
    $exitCode = 1;
    echo "  POST forms missing CSRF token in:\n";
    foreach ($csrfFailures as $miss) {
        echo "    - $miss\n";
    }
}

exit($exitCode);
?>

