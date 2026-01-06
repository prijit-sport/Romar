<aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo SITE_NAME; ?></h2>
                <p><?php echo SITE_NAME_EN; ?></p>
            </div>

            <nav class="sidebar-menu">
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                    <i>📊</i> Dashboard
                </a>

                <div class="menu-section">ระบบหลัก</div>

                <a href="<?php echo BASE_URL; ?>modules/tickets/index.php" class="menu-item">
                    <i>🎫</i> IT Tickets
                </a>

                <a href="<?php echo BASE_URL; ?>modules/rooms/index.php" class="menu-item">
                    <i>🏢</i> จองห้องประชุม
                </a>

                <a href="<?php echo BASE_URL; ?>modules/conversations/index.php" class="menu-item">
                    <i>💬</i> บันทึกสนทนา
                </a>

                <a href="<?php echo BASE_URL; ?>modules/announcements/index.php" class="menu-item">
                    <i>📢</i> ประกาศข่าวสาร
                </a>

                <a href="<?php echo BASE_URL; ?>modules/documents/index.php" class="menu-item">
                    <i>📁</i> เอกสาร
                </a>

                <?php if (isAdmin()): ?>
                <div class="menu-section">การจัดการ</div>

                <a href="<?php echo BASE_URL; ?>admin/users-management.php" class="menu-item">
                    <i>👥</i> จัดการผู้ใช้
                </a>

                <a href="<?php echo BASE_URL; ?>admin/settings.php" class="menu-item">
                    <i>⚙️</i> ตั้งค่า
                </a>
                <?php endif; ?>

                <div class="menu-section">บัญชี</div>

                <a href="<?php echo BASE_URL; ?>auth/logout.php" class="menu-item">
                    <i>🚪</i> ออกจากระบบ
                </a>
            </nav>
        </aside>

        <main class="main-content">
            <div class="topbar">
                <h1><?php echo $pageTitle ?? 'Dashboard'; ?></h1>
                <div class="user-info">
                    <div class="user-details">
                        <div class="user-name"><?php echo getCurrentUserFullName(); ?></div>
                        <div class="user-role"><?php echo isAdmin() ? 'ผู้ดูแลระบบ' : 'พนักงาน'; ?></div>
                    </div>
                    <div class="user-avatar">
                        <?php echo mb_substr(getCurrentUserFullName(), 0, 1); ?>
                    </div>
                </div>
            </div>

            <div class="content-area">
                <?php 
                // Show flash message if exists
                $flashMessage = getFlashMessage();
                if ($flashMessage): 
                ?>
                    <div class="alert alert-<?php echo $flashMessage['type']; ?>">
                        <?php echo $flashMessage['message']; ?>
                    </div>
                <?php endif; ?>