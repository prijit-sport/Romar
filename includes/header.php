<?php
if (!isset($_SESSION['user_id'])) {
    redirect('auth/login.php');
}
$contentTypeHeader = 'text/html; charset=UTF-8';
if (!headers_sent()) {
    header("Content-Type: $contentTypeHeader");
}
$pageTitle = $pageTitle ?? SITE_NAME;
?><!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="format-detection" content="telephone=no">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>includes/styles.css">
    <link rel="stylesheet" href="../assets/css/font-awesome/6.4.0/all.min.css" nonce="<?php echo htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<?php echo $pageStyles ?? ''; ?>
    <?php if (isLoggedIn()): ?>
    <script nonce="<?php echo htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8'); ?>" src="<?php echo BASE_URL; ?>assets/js/notificationsystem.js"></script>
    <?php endif; ?>
    <script nonce="<?php echo htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8'); ?>" src="<?php echo BASE_URL; ?>assets/js/nav-toggle.js?v=1"></script>
    <script nonce="<?php echo htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
    function isLoggedIn() {
        return <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    }
    </script>
</head>
<body>

    <div class="layout">
        <button class="mobile-sidebar-toggle" aria-label="Toggle Navigation">
            <i class="fas fa-bars"></i>
        </button>
        <!-- Global Notification Bell ถูกย้ายไป dashboard/module pages เท่านั้น -->

