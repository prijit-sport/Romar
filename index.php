ำ?php
// Emergency Recovery: Robust index.php - bypasses broken require chain
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inline security headers (safe)
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Inline critical functions (bypass functions.php dependency)
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? 'user';

// Graceful redirect logic
$target = '';
if ($isLoggedIn) {
    $target = ($userRole === 'admin') ? 'admin/dashboard.php' : 'modules/dashboard.php';
} else {
    $target = 'auth/login.php';
}

// Use BASE_URL if defined, fallback to relative
if (defined('BASE_URL')) {
    header("Location: " . BASE_URL . ltrim($target, '/'));
} else {
    header("Location: " . $target);
}
exit;
?>

