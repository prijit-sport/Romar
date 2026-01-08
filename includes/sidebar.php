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
                    <div class="user-avatar-container">
                     <?php
            // ดึงข้อมูล avatar
            $user_id = $_SESSION['user_id'] ?? null;
            $user_avatar = null;

            if ($user_id) {
                $db = getDb();
                
                // ตรวจสอบว่ามีคอลัมน์ avatar หรือไม่
                $columns_check = $db->query("PRAGMA table_info(users)");
                $has_avatar = false;
                while ($col = $columns_check->fetchArray(SQLITE3_ASSOC)) {
                    if ($col['name'] === 'avatar') {
                        $has_avatar = true;
                        break;
                    }
                }
                
                if ($has_avatar) {
                    $stmt = $db->prepare("SELECT avatar FROM users WHERE user_id = ?");
                    $stmt->bindValue(1, $user_id, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $row = $result->fetchArray(SQLITE3_ASSOC);
                    $user_avatar = $row['avatar'] ?? null;
                }
            }
            ?>
            
           <?php if ($user_avatar && file_exists(__DIR__ . '/../uploads/images/' . $user_avatar)): ?>
    <img src="<?php echo BASE_URL; ?>uploads/images/<?php echo htmlspecialchars($user_avatar); ?>" 
         alt="Avatar" 
         class="user-avatar"
         style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover;">
            <?php else: ?>
                <div class="user-avatar">
                    <?php echo mb_substr(getCurrentUserFullName(), 0, 1); ?>
                </div>
            <?php endif; ?>
            <!-- ⭐ สิ้นสุดตรงนี้! -->
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