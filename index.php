<?php
require_once __DIR__ . '/config/config.php';

// Strict headers for entry page.
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'self'");

// ถ้า Login แล้ว ไป Dashboard
if (isLoggedIn()) {
    redirect('admin/dashboard.php');
} else {
    // ถ้ายังไม่ Login ไปหน้า Login
    redirect('auth/login.php');
}
?>
