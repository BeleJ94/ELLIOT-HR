<?php
$activeMedicalModule = $activeMedicalModule ?? 'dashboard';
$canManageMedical = !empty($canManageMedical);
$medicalNav = [
    ['key' => 'dashboard', 'path' => '/medical', 'label' => 'Vue generale', 'icon' => 'dashboard', 'visible' => true],
    ['key' => 'requests', 'path' => '/medical/requests', 'label' => 'Demandes & bons', 'icon' => 'file', 'visible' => true],
    ['key' => 'dependents', 'path' => '/medical/dependents', 'label' => 'Ayants droit', 'icon' => 'users', 'visible' => true],
    ['key' => 'providers', 'path' => '/medical/providers', 'label' => 'Prestataires', 'icon' => 'building', 'visible' => $canManageMedical],
    ['key' => 'settings', 'path' => '/medical/settings', 'label' => 'Politique', 'icon' => 'settings', 'visible' => $canManageMedical],
];
?>
<nav class="medical-module-tabs" aria-label="Sous-modules medical">
    <?php foreach ($medicalNav as $item): ?>
        <?php if (!$item['visible']) { continue; } ?>
        <a class="<?= $activeMedicalModule === $item['key'] ? 'active' : '' ?>" href="<?= e(url($item['path'])) ?>">
            <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
