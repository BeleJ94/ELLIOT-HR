<?php
$sidebarUser = \App\Core\Auth::user() ?? [];
$sidebarRole = $sidebarUser['role_slug'] ?? '';
$can = static fn(array $roles): bool => in_array($sidebarRole, $roles, true);
$navGroups = [
    [
        'label' => 'Tableau de bord',
        'hint' => 'Vue globale',
        'items' => [
            ['path' => '/dashboard', 'label' => 'Vue générale', 'icon' => 'dashboard', 'roles' => ['super-admin', 'admin-rh', 'manager', 'employe']],
        ],
    ],
    [
        'label' => 'Collaborateurs',
        'hint' => 'Dossiers RH',
        'items' => [
            ['path' => '/employees', 'label' => 'Employés', 'icon' => 'users', 'roles' => ['super-admin', 'admin-rh', 'manager']],
            ['path' => '/contracts', 'label' => 'Contrats', 'icon' => 'briefcase', 'roles' => ['super-admin', 'admin-rh', 'manager']],
        ],
    ],
    [
        'label' => 'Temps & absences',
        'hint' => 'Présence et congés',
        'items' => [
            ['path' => '/attendance', 'label' => 'Présences', 'icon' => 'clock', 'roles' => ['super-admin', 'admin-rh', 'manager', 'employe']],
            ['path' => '/leaves', 'label' => 'Congés', 'icon' => 'calendar', 'roles' => ['super-admin', 'admin-rh', 'manager', 'employe']],
        ],
    ],
    [
        'label' => 'Développement RH',
        'hint' => 'Compétences',
        'items' => [
            ['path' => '/trainings', 'label' => 'Formations', 'icon' => 'file', 'roles' => ['super-admin', 'admin-rh', 'manager']],
        ],
    ],
    [
        'label' => 'Paie & obligations',
        'hint' => 'Rémunération',
        'items' => [
            ['path' => '/payroll', 'label' => 'Paie', 'icon' => 'wallet', 'roles' => ['super-admin', 'admin-rh']],
            ['path' => '/declarations', 'label' => 'Déclarations', 'icon' => 'chart', 'roles' => ['super-admin', 'admin-rh']],
        ],
    ],
    [
        'label' => 'Organisation',
        'hint' => 'Structure',
        'items' => [
            ['path' => '/companies', 'label' => $sidebarRole === 'admin-rh' ? 'Mon entreprise' : 'Entreprises', 'icon' => 'building', 'roles' => ['super-admin', 'admin-rh']],
            ['path' => '/departments', 'label' => 'Départements', 'icon' => 'layers', 'roles' => ['super-admin', 'admin-rh', 'manager']],
            ['path' => '/positions', 'label' => 'Postes', 'icon' => 'briefcase', 'roles' => ['super-admin', 'admin-rh', 'manager']],
        ],
    ],
    [
        'label' => 'Administration',
        'hint' => 'Accès et sécurité',
        'items' => [
            ['path' => '/users', 'label' => 'Utilisateurs', 'icon' => 'shield', 'roles' => ['super-admin', 'admin-rh']],
        ],
    ],
];
?>
<aside class="navbar navbar-vertical navbar-expand-lg" id="sidebar">
    <div class="container-fluid">
        <div class="sidebar-brand-row">
            <a class="navbar-brand" href="<?= e(url('/dashboard')) ?>">
                <span class="brand-mark">EH</span>
                <span class="brand-copy"><strong>ELLIOT-HR</strong><small>Human Capital Suite</small></span>
            </a>
            <button class="navbar-toggler" type="button" data-sidebar-toggle aria-label="Fermer le menu"><?= icon('x') ?></button>
        </div>
        <nav class="navbar-collapse" aria-label="Navigation principale">
            <?php foreach ($navGroups as $group): ?>
                <?php
                $visibleItems = array_values(array_filter($group['items'], static fn(array $item): bool => $can($item['roles'])));
                if ($visibleItems === []) {
                    continue;
                }
                ?>
                <div class="sidebar-group">
                    <span class="sidebar-label">
                        <span><?= e($group['label']) ?></span>
                        <?php if (!empty($group['hint'])): ?>
                            <small><?= e($group['hint']) ?></small>
                        <?php endif; ?>
                    </span>
                    <ul class="navbar-nav">
                        <?php foreach ($visibleItems as $item): ?>
                            <li class="nav-item <?= e(active_class($item['path'])) ?>">
                                <a class="nav-link" href="<?= e(url($item['path'])) ?>">
                                    <span class="nav-icon"><?= icon($item['icon']) ?></span>
                                    <span class="nav-link-title"><?= e($item['label']) ?></span>
                                    <span class="nav-chevron">›</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-support">
            <span class="sidebar-support-icon"><?= icon('check') ?></span>
            <div><strong>Environnement sécurisé</strong><small>Vos données RH sont protégées</small></div>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop" data-sidebar-close></div>
