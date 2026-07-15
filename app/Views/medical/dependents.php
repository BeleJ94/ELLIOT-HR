<?php
$dependents = $dependents ?? [];
$relationships = $relationships ?? [];
$settings = $settings ?? [];
$canManageMedical = !empty($canManageMedical);
$statusTones = ['active' => 'green', 'pending' => 'orange', 'suspended' => 'red', 'expired' => 'gray', 'rejected' => 'red'];
$statusLabels = ['active' => 'Actif', 'pending' => 'A verifier', 'suspended' => 'Suspendu', 'expired' => 'Expire', 'rejected' => 'Rejete'];
$today = date('Y-m-d');
$stats = ['total' => count($dependents), 'active' => 0, 'pending' => 0, 'expiring' => 0, 'missing_docs' => 0];
$relationshipCounts = [];
foreach ($dependents as $dependent) {
    $status = $dependent['status'] ?? 'pending';
    if ($status === 'active') {
        $stats['active']++;
    }
    if ($status === 'pending') {
        $stats['pending']++;
    }
    if (empty($dependent['document_type']) && empty($dependent['document_reference'])) {
        $stats['missing_docs']++;
    }
    if (!empty($dependent['coverage_end'])) {
        $daysLeft = (int) floor((strtotime($dependent['coverage_end']) - strtotime($today)) / 86400);
        if ($daysLeft >= 0 && $daysLeft <= 45) {
            $stats['expiring']++;
        }
    }
    $relationship = $dependent['relationship'] ?? 'other';
    $relationshipCounts[$relationship] = ($relationshipCounts[$relationship] ?? 0) + 1;
}
$age = static function (?string $birthDate): string {
    if (!$birthDate) {
        return '-';
    }
    try {
        return (string) (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable())->y . ' ans';
    } catch (Throwable $exception) {
        return '-';
    }
};
$formatDate = static fn($date): string => $date ? date('d/m/Y', strtotime((string) $date)) : '-';
?>

<div class="module-header module-header-rich medical-hero">
    <div><span class="dashboard-section-kicker">Couverture familiale</span><h1 class="page-title">Ayants droit</h1><p>Registre des bénéficiaires rattachés aux employés et statut de leur couverture médicale.</p></div>
    <div class="module-header-actions"><button class="btn btn-primary" type="button" data-medical-open="dependent"><?= icon('plus') ?><span>Ajouter un ayant droit</span></button></div>
</div>

<?php require APP_PATH . '/Views/medical/_nav.php'; ?>

<section class="medical-dependent-kpis">
    <article class="card"><span>Total</span><strong><?= e((string) $stats['total']) ?></strong><small>Personnes rattachées</small></article>
    <article class="card"><span>Actifs</span><strong><?= e((string) $stats['active']) ?></strong><small>Couverture utilisable</small></article>
    <article class="card"><span>A vérifier</span><strong><?= e((string) $stats['pending']) ?></strong><small>Dossier incomplet</small></article>
    <article class="card"><span>Échéance proche</span><strong><?= e((string) $stats['expiring']) ?></strong><small>Dans 45 jours</small></article>
</section>

<section class="medical-dependent-workbench medical-dependent-workbench-full">
    <div class="medical-dependent-main">
        <form class="medical-dependent-toolbar" data-dependent-filters>
            <div class="topbar-search employee-search"><?= icon('search') ?><input type="search" name="q" placeholder="Rechercher nom, titulaire, document" data-dependent-search></div>
            <select class="form-select" name="status" data-dependent-status>
                <option value="">Tous les statuts</option>
                <?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select" name="relationship" data-dependent-relationship>
                <option value="">Tous les liens</option>
                <?php foreach ($relationships as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-outline" type="button" data-dependent-reset><?= icon('x') ?><span>Réinitialiser</span></button>
        </form>

        <section class="card company-table-card erp-table-card medical-dependent-table-card">
            <div class="card-header">
                <div><span class="dashboard-section-kicker">Registre</span><h2 class="card-title">Ayants droit enregistrés</h2></div>
                <span class="badge bg-blue-lt"><?= e((string) count($dependents)) ?> bénéficiaire(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table medical-dependent-table" id="medical-dependents-table" data-dependent-table>
                    <thead>
                        <tr>
                            <th>Bénéficiaire</th>
                            <th>Titulaire</th>
                            <th>Lien</th>
                            <th>Âge</th>
                            <th>Couverture</th>
                            <th>Justificatif</th>
                            <th>Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($dependents === []): ?><tr><td colspan="8" class="text-secondary">Aucun ayant droit enregistre.</td></tr><?php endif; ?>
                        <?php foreach ($dependents as $dependent): ?>
                            <?php
                            $status = $dependent['status'] ?? 'pending';
                            $relationship = $dependent['relationship'] ?? 'other';
                            $tone = $statusTones[$status] ?? 'blue';
                            $fullName = trim($dependent['last_name'] . ' ' . $dependent['first_name']);
                            $holderName = trim(($dependent['employee_last_name'] ?? '') . ' ' . ($dependent['employee_first_name'] ?? ''));
                            $docLabel = trim((string) ($dependent['document_type'] ?: $dependent['document_reference'] ?: ''));
                            $coverageEnd = $dependent['coverage_end'] ?? null;
                            $coverageClass = 'is-current';
                            $coverageText = 'Active';
                            if ($status !== 'active') {
                                $coverageClass = 'is-muted';
                                $coverageText = $statusLabels[$status] ?? $status;
                            } elseif ($coverageEnd && $coverageEnd < $today) {
                                $coverageClass = 'is-danger';
                                $coverageText = 'Expirée';
                            } elseif ($coverageEnd && ((strtotime($coverageEnd) - strtotime($today)) / 86400) <= 45) {
                                $coverageClass = 'is-warning';
                                $coverageText = 'Échéance proche';
                            }
                            ?>
                            <tr data-status="<?= e($status) ?>" data-relationship="<?= e($relationship) ?>">
                                <td>
                                    <div class="medical-table-person">
                                        <div class="medical-avatar"><?= e(strtoupper(substr((string) $dependent['last_name'], 0, 1) . substr((string) $dependent['first_name'], 0, 1))) ?></div>
                                        <div><strong><?= e($fullName) ?></strong><span><?= e($dependent['phone'] ?: 'Aucun téléphone') ?></span></div>
                                    </div>
                                </td>
                                <td><strong><?= e($holderName ?: '-') ?></strong><span class="d-block text-secondary"><?= e($dependent['employee_number'] ?? '') ?></span></td>
                                <td><?= e($relationships[$relationship] ?? $relationship) ?></td>
                                <td><?= e($age($dependent['birth_date'] ?? null)) ?></td>
                                <td>
                                    <span class="medical-table-coverage <?= e($coverageClass) ?>"><?= e($coverageText) ?></span>
                                    <span class="d-block text-secondary"><?= e($formatDate($dependent['coverage_start'] ?? null)) ?> → <?= e($formatDate($coverageEnd)) ?></span>
                                </td>
                                <td>
                                    <?php if ($docLabel !== ''): ?>
                                        <strong><?= e($dependent['document_type'] ?: 'Document') ?></strong>
                                        <span class="d-block text-secondary"><?= e($dependent['document_reference'] ?: '-') ?></span>
                                    <?php else: ?>
                                        <span class="medical-chip is-warning">Manquant</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?= e($tone) ?>-lt"><?= e($statusLabels[$status] ?? $status) ?></span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline" type="button" data-medical-open="request"><?= icon('plus') ?><span>Demande</span></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>

<?php require APP_PATH . '/Views/medical/_modals.php'; ?>
<script src="<?= e(asset('js/medical.js') . '?v=' . (string) filemtime(BASE_PATH . '/public/js/medical.js')) ?>"></script>
