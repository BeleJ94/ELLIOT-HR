<?php
$currentUser = \App\Core\Auth::user() ?? [];
$displayName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
$displayName = $displayName !== '' ? $displayName : 'Utilisateur';
$displayRole = $currentUser['role_name'] ?? $currentUser['company_name'] ?? 'ELLIOT-HR';
$initials = strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1) . substr($currentUser['last_name'] ?? '', 0, 1));
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$topbarRole = $currentUser['role_slug'] ?? '';
$quickLinks = [
    ['label' => 'Vue générale', 'path' => '/dashboard', 'icon' => 'dashboard', 'roles' => ['super-admin', 'admin-rh', 'manager', 'employe']],
    ['label' => 'Employés', 'path' => '/employees', 'icon' => 'users', 'roles' => ['super-admin', 'admin-rh', 'manager']],
    ['label' => 'Contrats', 'path' => '/contracts', 'icon' => 'briefcase', 'roles' => ['super-admin', 'admin-rh', 'manager']],
    ['label' => 'Présences', 'path' => '/attendance', 'icon' => 'clock', 'roles' => ['super-admin', 'admin-rh', 'manager', 'employe']],
    ['label' => 'Congés', 'path' => '/leaves', 'icon' => 'calendar', 'roles' => ['super-admin', 'admin-rh', 'manager', 'employe']],
    ['label' => 'Formations', 'path' => '/trainings', 'icon' => 'file', 'roles' => ['super-admin', 'admin-rh', 'manager']],
    ['label' => 'Paie', 'path' => '/payroll', 'icon' => 'wallet', 'roles' => ['super-admin', 'admin-rh']],
    ['label' => 'Déclarations', 'path' => '/declarations', 'icon' => 'chart', 'roles' => ['super-admin', 'admin-rh']],
    ['label' => 'Organisation', 'path' => '/departments', 'icon' => 'layers', 'roles' => ['super-admin', 'admin-rh', 'manager']],
    ['label' => 'Utilisateurs', 'path' => '/users', 'icon' => 'shield', 'roles' => ['super-admin', 'admin-rh']],
];
$quickLinks = array_values(array_filter($quickLinks, static fn(array $link): bool => in_array($topbarRole, $link['roles'], true)));
?>
<header class="navbar navbar-top">
    <div class="container-xl">
        <div class="topbar-leading">
            <button class="btn btn-icon d-lg-none" type="button" data-sidebar-toggle aria-label="Ouvrir le menu"><?= icon('menu') ?></button>
            <div class="topbar-context">
                <span>Espace de travail</span>
                <strong><?= e($title ?? 'ELLIOT-HR') ?></strong>
            </div>
        </div>
        <div class="topbar-search" data-command-search>
            <?= icon('search') ?>
            <input type="search" placeholder="Rechercher un module ou une action…" autocomplete="off" data-command-input>
            <kbd>⌘ K</kbd>
            <div class="command-results" data-command-results>
                <?php foreach ($quickLinks as $link): ?>
                    <a href="<?= e(url($link['path'])) ?>" data-command-item="<?= e(strtolower($link['label'])) ?>">
                        <span><?= icon($link['icon']) ?></span>
                        <strong><?= e($link['label']) ?></strong>
                        <small>Ouvrir</small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="navbar-actions">
            <button class="btn btn-icon theme-toggle" type="button" data-theme-toggle aria-label="Changer le thème"><?= icon('sun') ?></button>
            <div class="notification-menu">
                <button class="btn btn-icon" type="button" data-notification-toggle aria-label="Notifications">
                    <?= icon('bell') ?><span class="badge-dot"></span>
                </button>
                <div class="notification-popover" data-notification-popover>
                    <div><strong>Centre de notifications</strong><span class="badge bg-blue-lt">Live</span></div>
                    <p>Retrouvez les alertes métier détaillées dans votre tableau de bord.</p>
                    <a href="<?= e(url('/dashboard')) ?>">Consulter les alertes <?= icon('arrow-right') ?></a>
                </div>
            </div>
            <div class="user-menu">
                <button class="avatar-row" type="button" data-user-toggle>
                    <span class="avatar"><?= e($initials) ?></span>
                    <span class="user-copy"><span class="user-name"><?= e($displayName) ?></span><span class="user-role"><?= e($displayRole) ?></span></span>
                    <?= icon('chevron-down') ?>
                </button>
                <div class="user-popover" data-user-popover>
                    <div class="user-popover-head"><span class="avatar"><?= e($initials) ?></span><div><strong><?= e($displayName) ?></strong><small><?= e($displayRole) ?></small></div></div>
                    <a href="<?= e(url('/dashboard')) ?>"><?= icon('dashboard') ?> Mon tableau de bord</a>
                    <a class="text-danger" href="<?= e(url('/logout')) ?>"><?= icon('logout') ?> Se déconnecter</a>
                </div>
            </div>
        </div>
    </div>
</header>
