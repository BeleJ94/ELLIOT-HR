<?php
$providers = $providers ?? [];
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$typeLabels = ['hospital' => 'Hopital', 'clinic' => 'Clinique', 'pharmacy' => 'Pharmacie', 'laboratory' => 'Laboratoire', 'other' => 'Autre'];
?>

<div class="module-header module-header-rich medical-hero">
    <div><span class="dashboard-section-kicker">Reseau conventionne</span><h1 class="page-title">Prestataires medicaux</h1><p>Centres autorisés, coordonnées, conventions et taux spécifiques de couverture.</p></div>
    <div class="module-header-actions"><button class="btn btn-primary" type="button" data-medical-open="provider"><?= icon('plus') ?><span>Nouveau prestataire</span></button></div>
</div>

<?php require APP_PATH . '/Views/medical/_nav.php'; ?>

<section class="card company-table-card erp-table-card">
    <div class="card-header"><div><span class="dashboard-section-kicker">Conventionnement</span><h2 class="card-title">Prestataires disponibles</h2></div><span class="badge bg-blue-lt"><?= e((string) count($providers)) ?> prestataire(s)</span></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead><tr><th>Prestataire</th><th>Type</th><th>Contact</th><th>Ville</th><th>Taux</th><th>Statut</th></tr></thead>
            <tbody>
                <?php if ($providers === []): ?><tr><td colspan="6" class="text-secondary">Aucun prestataire conventionne.</td></tr><?php endif; ?>
                <?php foreach ($providers as $provider): ?>
                    <tr>
                        <td><strong><?= e($provider['name']) ?></strong><span class="d-block text-secondary"><?= e($provider['agreement_reference'] ?: $provider['company_name']) ?></span></td>
                        <td><?= e($typeLabels[$provider['provider_type']] ?? $provider['provider_type']) ?></td>
                        <td><?= e($provider['phone'] ?: '-') ?><span class="d-block text-secondary"><?= e($provider['email'] ?: '') ?></span></td>
                        <td><?= e($provider['city'] ?: '-') ?></td>
                        <td><?= $provider['default_coverage_rate'] !== null ? e(number_format((float) $provider['default_coverage_rate'], 2, ',', ' ')) . '%' : '<span class="text-secondary">Politique</span>' ?></td>
                        <td><span class="badge bg-<?= $provider['status'] === 'active' ? 'green' : 'gray' ?>-lt"><?= e($provider['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require APP_PATH . '/Views/medical/_modals.php'; ?>
<script src="<?= e(asset('js/medical.js') . '?v=' . (string) filemtime(BASE_PATH . '/public/js/medical.js')) ?>"></script>
