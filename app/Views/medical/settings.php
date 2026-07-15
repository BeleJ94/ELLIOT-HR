<?php
$settings = $settings ?? [];
$companies = $companies ?? [];
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$money = static fn($amount): string => $amount === null || $amount === '' ? 'Illimite' : number_format((float) $amount, 2, ',', ' ');
?>

<div class="module-header module-header-rich medical-hero">
    <div><span class="dashboard-section-kicker">Configuration</span><h1 class="page-title">Politique medicale</h1><p>Règles de couverture, plafonds annuels, validité des bons et périmètre des ayants droit.</p></div>
    <div class="module-header-actions"><button class="btn btn-primary" type="button" data-medical-open="settings"><?= icon('settings') ?><span>Modifier la politique</span></button></div>
</div>

<?php require APP_PATH . '/Views/medical/_nav.php'; ?>

<section class="medical-policy-grid">
    <article class="card medical-policy-card">
        <span>Taux standard</span>
        <strong><?= e(number_format((float) ($settings['default_coverage_rate'] ?? 0), 2, ',', ' ')) ?>%</strong>
        <p>Appliqué sauf convention spécifique du prestataire.</p>
    </article>
    <article class="card medical-policy-card">
        <span>Plafond employe</span>
        <strong><?= e($money($settings['annual_employee_ceiling'] ?? null)) ?></strong>
        <p>Limite annuelle pour le titulaire.</p>
    </article>
    <article class="card medical-policy-card">
        <span>Plafond ayant droit</span>
        <strong><?= e($money($settings['annual_dependent_ceiling'] ?? null)) ?></strong>
        <p>Limite annuelle par bénéficiaire rattaché.</p>
    </article>
    <article class="card medical-policy-card">
        <span>Validite bon</span>
        <strong><?= e((string) ($settings['voucher_valid_days'] ?? 7)) ?> jours</strong>
        <p>Durée après approbation RH.</p>
    </article>
</section>

<section class="card company-profile-card">
    <div class="card-header"><div><span class="dashboard-section-kicker">Ayants droit couverts</span><h2 class="card-title">Périmètre familial</h2></div></div>
    <div class="medical-coverage-matrix">
        <article class="<?= !empty($settings['spouse_covered']) ? 'is-on' : '' ?>"><strong>Conjoint</strong><span><?= !empty($settings['spouse_covered']) ? 'Couvert' : 'Non couvert' ?></span></article>
        <article class="<?= !empty($settings['children_covered']) ? 'is-on' : '' ?>"><strong>Enfants</strong><span>Jusqu'à <?= e((string) ($settings['max_child_age'] ?? 18)) ?> ans, <?= e((string) ($settings['student_child_age'] ?? 25)) ?> ans si étudiant</span></article>
        <article class="<?= !empty($settings['parents_covered']) ? 'is-on' : '' ?>"><strong>Parents</strong><span><?= !empty($settings['parents_covered']) ? 'Couverts' : 'Non couverts' ?></span></article>
        <article class="<?= !empty($settings['payroll_recovery_enabled']) ? 'is-on' : '' ?>"><strong>Retenue paie</strong><span><?= !empty($settings['payroll_recovery_enabled']) ? 'Activée' : 'Inactive' ?></span></article>
    </div>
</section>

<?php require APP_PATH . '/Views/medical/_modals.php'; ?>
<script src="<?= e(asset('js/medical.js') . '?v=' . (string) filemtime(BASE_PATH . '/public/js/medical.js')) ?>"></script>
