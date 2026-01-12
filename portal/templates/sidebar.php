<!-- Sidebar -->
<div class="sidebar<?php echo isClientAdmin() ? ' client-admin' : ''; ?>">
    <div class="sidebar-nav">
        <div class="nav-section">
            <?php echo renderNavigation(); ?>
        </div>

        <?php if (isClientAdmin() && !empty($_SESSION['allowed_domains'])): ?>
        <div class="nav-section">
            <div class="nav-section-title">Your Domains</div>
            <ul class="nav flex-column domain-list">
                <?php foreach ($_SESSION['allowed_domains'] as $domain_id => $domain_name): ?>
                <li class="nav-item">
                    <span class="nav-link domain-item">
                        <i class="bi bi-globe2"></i>
                        <span><?php echo sanitize($domain_name); ?></span>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?>
        </div>
        <div class="user-info">
            <span class="user-name"><?php echo sanitize($_SESSION['name']); ?></span>
            <span class="user-role"><?php echo isSuperAdmin() ? 'Super Admin' : 'Client Admin'; ?></span>
        </div>
        <a href="logout.php" class="logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>
