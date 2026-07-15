<?php
$requests = $requests ?? [];
$filters = $filters ?? [];
$careTypes = $careTypes ?? [];
$relationships = $relationships ?? [];
$settings = $settings ?? [];
$canManageMedical = !empty($canManageMedical);
$statusLabels = [
    'submitted' => 'A valider',
    'voucher_issued' => 'Bon emis',
    'validated' => 'Liquidee',
    'paid' => 'Payee',
    'rejected' => 'Refusee',
];
$statusTones = ['submitted' => 'orange', 'voucher_issued' => 'cyan', 'validated' => 'green', 'paid' => 'green', 'rejected' => 'red'];
$money = static fn($amount, $currency = 'USD'): string => number_format((float) $amount, 2, ',', ' ') . ' ' . e((string) $currency);
?>

<div class="module-header module-header-rich medical-hero">
    <div><span class="dashboard-section-kicker">Prises en charge</span><h1 class="page-title">Demandes & bons</h1><p>Suivi opérationnel des demandes, validations, bons PDF et liquidations.</p></div>
    <div class="module-header-actions"><button class="btn btn-primary" type="button" data-medical-open="request"><?= icon('plus') ?><span>Nouvelle demande</span></button></div>
</div>

<?php require APP_PATH . '/Views/medical/_nav.php'; ?>

<form class="employee-filter-bar attendance-filter-bar" method="get" action="<?= e(url('/medical/requests')) ?>">
    <div class="topbar-search employee-search"><?= icon('search') ?><input type="search" data-medical-search placeholder="Rechercher reference, beneficiaire, prestataire"></div>
    <select class="form-select" name="status"><option value="">Tous les statuts</option><?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
    <select class="form-select" name="care_type"><option value="">Tous les soins</option><?php foreach ($careTypes as $value => $label): ?><option value="<?= e($value) ?>" <?= ($filters['care_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
    <input class="form-control" type="date" name="from" value="<?= e($filters['from'] ?? '') ?>">
    <input class="form-control" type="date" name="to" value="<?= e($filters['to'] ?? '') ?>">
    <button class="btn btn-outline" type="submit"><?= icon('search') ?><span>Filtrer</span></button>
</form>

<section class="card company-table-card erp-table-card medical-requests-card">
    <div class="card-header"><div><span class="dashboard-section-kicker">Registre</span><h2 class="card-title">Demandes medicales</h2></div><span class="badge bg-blue-lt"><?= e((string) count($requests)) ?> dossier(s)</span></div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table" id="medical-requests-table">
            <thead><tr><th>Reference</th><th>Titulaire / beneficiaire</th><th>Soin</th><th>Prestataire</th><th>Autorisation</th><th>Statut</th><th></th></tr></thead>
            <tbody>
                <?php if ($requests === []): ?><tr><td colspan="7" class="text-secondary">Aucune demande trouvee.</td></tr><?php endif; ?>
                <?php foreach ($requests as $request): ?>
                    <?php
                    $beneficiary = trim(($request['last_name'] ?? '') . ' ' . ($request['middle_name'] ?? '') . ' ' . ($request['first_name'] ?? ''));
                    if (!empty($request['dependent_id'])) {
                        $beneficiary = trim(($request['dependent_last_name'] ?? '') . ' ' . ($request['dependent_first_name'] ?? '')) . ' · ' . ($relationships[$request['relationship'] ?? 'other'] ?? 'Ayant droit');
                    }
                    $tone = $statusTones[$request['status'] ?? 'submitted'] ?? 'blue';
                    ?>
                    <tr>
                        <td><a class="company-name" href="<?= e(url('/medical/show?id=' . $request['id'])) ?>"><?= e($request['request_number']) ?></a><span class="d-block text-secondary"><?= e(substr((string) $request['created_at'], 0, 10)) ?></span></td>
                        <td><?= e($beneficiary) ?><span class="d-block text-secondary"><?= e($request['employee_number'] ?? '') ?> · <?= e($request['department_name'] ?? '-') ?></span></td>
                        <td><?= e($careTypes[$request['care_type']] ?? $request['care_type']) ?></td>
                        <td><?= e($request['provider_name'] ?: 'A confirmer') ?></td>
                        <td><strong><?= $money($request['covered_amount'], $request['currency']) ?></strong><span class="d-block text-secondary">Part employe: <?= $money($request['employee_share'], $request['currency']) ?></span></td>
                        <td><span class="badge bg-<?= e($tone) ?>-lt"><?= e($statusLabels[$request['status']] ?? $request['status']) ?></span></td>
                        <td><a class="btn btn-icon" href="<?= e(url('/medical/show?id=' . $request['id'])) ?>"><?= icon('file') ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require APP_PATH . '/Views/medical/_modals.php'; ?>
<script src="<?= e(asset('js/medical.js') . '?v=' . (string) filemtime(BASE_PATH . '/public/js/medical.js')) ?>"></script>
