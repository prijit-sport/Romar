<?php
$activePage = $activePage ?? '';
$isAdmin = isAdmin();
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div>
            <div class="brand-title">
                <i class="fas fa-ticket-alt"></i>
                Romar IT Support
            </div>
            <div class="brand-subtitle">Ticket Management System</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <ul>

            <li class="menu-section"><?php echo ui_text('nav.section.primary'); ?></li>
            <li class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>modules/dashboard.php">
                    <i class="fas fa-home"></i> <?php echo ui_text('nav.dashboard'); ?>
                </a>
            </li>
            <li class="<?php echo $activePage === 'tickets' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>modules/tickets.php">
                    <i class="fas fa-ticket-alt"></i> <?php echo ui_text('nav.tickets'); ?>
                </a>
            </li>
            <li class="<?php echo $activePage === 'assets' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>modules/assets.php">
                    <i class="fas fa-box"></i> <?php echo ui_text('nav.assets'); ?>
                </a>
            </li>
            <li class="<?php echo $activePage === 'knowledgebase' ? 'active' : ''; ?>"> 
                <a href="<?php echo BASE_URL; ?>modules/Knowledgebase.php">
                    <i class="fas fa-book"></i> <?php echo ui_text('nav.knowledgebase'); ?>
                </a>
            </li>
            <?php if ($isAdmin): ?>
                <li class="menu-section"><?php echo ui_text('nav.section.admin'); ?></li>
                <li class="<?php echo $activePage === 'users-management' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>modules/users-management.php">
                            <i class="fas fa-users"></i> ผู้ใช้งาน
                        </a>
                </li>
                <li class="<?php echo $activePage === 'reports' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>modules/reports.php">
                        <i class="fas fa-chart-bar"></i> <?php echo ui_text('nav.reports'); ?>
                    </a>
                </li>
                <li class="<?php echo $activePage === 'assetsreports' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>modules/assetsreports.php">
                        <i class="fas fa-chart-line"></i> <?php echo ui_text('nav.assets_reports'); ?>
                    </a>
                </li>
                <li class="<?php echo $activePage === 'slaconfig' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>modules/slaconfig.php">
                        <i class="fas fa-stopwatch"></i> ศูนย์ SLA
                    </a>
                </li>
                <li class="<?php echo $activePage === 'settings' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>modules/settings.php">
                        <i class="fas fa-cog"></i> <?php echo ui_text('nav.settings'); ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="menu-section"><?php echo ui_text('nav.section.support'); ?></li>
            <li>
                <a href="<?php echo BASE_URL; ?>auth/logout.php" onclick="return confirm('คุณแน่ใจว่าจะออกจากระบบหรือไม่?');">
                    <i class="fas fa-sign-out-alt"></i> <?php echo ui_text('nav.logout'); ?>
                </a>
            </li>
        </ul>
    </nav>
</aside>
