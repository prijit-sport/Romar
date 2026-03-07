<?php
/**
 * Logout Script
 * ออกจากระบบและทำลาย session
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log activity ก่อน logout
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    
    // บันทึก activity
    logActivity($_SESSION['user_id'], 'ออกจากระบบ', 'Authentication', 'User logged out');
}

// ทำลาย session
session_unset();
session_destroy();

// ลบ session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect ไป login
header('Location: ../auth/login.php');
exit;
?>