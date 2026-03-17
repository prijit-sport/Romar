<?php
$activePage = $activePage ?? '';
$isAdmin = isAdmin();
require_once __DIR__ . '/asset_categories.php';
$assetCategories = getAssetCategories();
$activeAssetCategory = $activeAssetCategory ?? '';
$assetCategoryCounts = $catCounts ?? [];
$assetParentClasses = trim('nav-parent ' . ($activePage === 'assets' ? 'active ' : '') . 'open');
$assetSubmenuClasses = 'nav-submenu open';
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
            <li class="nav-back">
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo ui_text('nav.back_to_dashboard'); ?>
                </a>
            </li>
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
            <li class="<?php echo $assetParentClasses; ?>">
                <div class="nav-parent-main">
                    <a class="<?php echo $activePage === 'assets' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/assets.php">
                        <i class="fas fa-box"></i> <?php echo ui_text('nav.assets'); ?>
                    </a>
                    <button type="button"
                            class="nav-parent-toggle"
                            aria-label="Toggle asset categories"
                            aria-expanded="true">
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
                <ul class="<?php echo $assetSubmenuClasses; ?>">
                    <?php foreach ($assetCategories as $key => $category): ?>
                    <li class="<?php echo ($activePage === 'assets' && $activeAssetCategory === $key) ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_URL; ?>modules/assets.php?cat=<?php echo urlencode($key); ?>">
                            <span class="nav-submenu-label">
                                <i class="fas <?php echo $category['icon']; ?>"></i>
                                <?php echo htmlspecialchars($category['label']); ?>
                            </span>
                            <?php if (!empty($assetCategoryCounts[$key])): ?>
                                <span class="submenu-badge"><?php echo number_format($assetCategoryCounts[$key]); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <li class="<?php echo $activePage === 'knowledgebase' ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>modules/Knowledgebase.php">
                    <i class="fas fa-book"></i> <?php echo ui_text('nav.knowledgebase'); ?>
                </a>
            </li>
            <?php if ($isAdmin): ?>
                <li class="menu-section"><?php echo ui_text('nav.section.admin'); ?></li>
                <li class="<?php echo $activePage === 'users' ? 'active' : ''; ?>">
                    <a href="<?php echo BASE_URL; ?>modules/users.php">
                        <i class="fas fa-users"></i> <?php echo ui_text('nav.users'); ?>
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
                        <i class="fas fa-clock"></i> <?php echo ui_text('nav.sla'); ?>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.nav-parent-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(event) {
            event.preventDefault();
            const parent = toggle.closest('.nav-parent');
            const submenu = parent ? parent.querySelector('.nav-submenu') : null;
            if (!submenu) {
                return;
            }
            const isOpen = submenu.classList.toggle('open');
            parent.classList.toggle('open', isOpen);
            const icon = toggle.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-chevron-up', isOpen);
                icon.classList.toggle('fa-chevron-down', !isOpen);
            }
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });
});
</script>

