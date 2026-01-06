<?php
require_once __DIR__ . '/config/config.php';

// ถ้า Login แล้ว ไป Dashboard
if (isLoggedIn()) {
    redirect('admin/dashboard.php');
} else {
    // ถ้ายังไม่ Login ไปหน้า Login
    redirect('auth/login.php');
}

?>