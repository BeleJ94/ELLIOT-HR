<?php
$dashboard = $dashboard ?? [];
$requests = $requests ?? [];
$dependents = $dependents ?? [];
$providers = $providers ?? [];
$employees = $employees ?? [];
$companies = $companies ?? [];
$settings = $settings ?? [];
$filters = $filters ?? [];
$careTypes = $careTypes ?? [];
$relationships = $relationships ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$canManageMedical = !empty($canManageMedical);
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$selfEmployeeId = $selfEmployeeId ?? null;
$statusLabels = [
    'submitted' => 'A valider',
    'approved' => 'Approuvee',
    'voucher_issued' => 'Bon emis',
    'invoiced' => 'Facturee',
    'validated' => 'Liquidee',
    'paid' => 'Payee',
    'rejected' => 'Refusee',
    'cancelled' => 'Annulee',
    'expired' => 'Expiree',
];
$statusTones = [
    'submitted' => 'orange',
    'approved' => 'blue',
    'voucher_issued' => 'cyan',
    'invoiced' => 'purple',
    'validated' => 'green',
    'paid' => 'green',
    'rejected' => 'red',
    'cancelled' => 'gray',
    'expired' => 'red',
];
$employeeName = static fn(array $row): string => trim(($row['last_name'] ?? $row['employee_last_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['first_name'] ?? $row['employee_first_name'] ?? ''));
$money = static fn($amount, $currency = 'USD'): string => number_format((float) $amount, 2, ',', ' ') . ' ' . e((string) $currency);
?>

<div class="module-header module-header-rich medical-hero">
    <div>
        <span class="dashboard-section-kicker">Sante & avantages</span>
        <h1 class="page-title">Prises en charge medicales</h1>
        <p>Ayants droit, prestataires conventionnes, bons de prise en charge et liquidation des factures.</p>
    </div>
    <div class="module-header-actions">
        <button class="btn btn-outline" type="button" data-medical-open="dependent"><?= icon('users') ?><span>Ayant droit</span></button>
        <?php if ($canManageMedical): ?>
            <button class="btn btn-outline" type="button" data-medical-open="provider"><?= icon('building') ?><span>Prestataire</span></button>
            <button class="btn btn-outline" type="button" data-medical-open="settings"><?= icon('settings') ?><span>Politique</span></button>
        <?php endif; ?>
        <button class="btn btn-primary" type="button" data-medical-open="request"><?= icon('plus') ?><span>Nouvelle demande</span></button>
    </div>
</div>

<?php require APP_PATH . '/Views/medical/_nav.php'; ?>

<div class="attendance-summary-grid medical-summary-grid">
    <article class="card metric-card metric-card-modern"><div class="metric-label">Demandes</div><div class="metric-value"><?= e((string) ($dashboard['requests'] ?? 0)) ?></div></article>
    <article class="card metric-card metric-card-modern"><div class="metric-label">A valider</div><div class="metric-value"><?= e((string) ($dashboard['pending'] ?? 0)) ?></div></article>
    <article class="card metric-card metric-card-modern"><div class="metric-label">En cours</div><div class="metric-value"><?= e((string) ($dashboard['approved'] ?? 0)) ?></div></article>
    <article class="card metric-card metric-card-modern"><div class="metric-label">Part entreprise</div><div class="metric-value"><?= $money($dashboard['covered'] ?? 0, $settings['currency'] ?? 'USD') ?></div></article>
</div>

<form class="employee-filter-bar attendance-filter-bar" method="get" action="<?= e(url('/medical')) ?>">
    <div class="topbar-search employee-search"><?= icon('search') ?><input type="search" data-medical-search placeholder="Rechercher beneficiaire, reference, prestataire"></div>
    <select class="form-select" name="status">
        <option value="">Tous les statuts</option>
        <?php foreach ($statusLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="care_type">
        <option value="">Tous les soins</option>
        <?php foreach ($careTypes as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['care_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input class="form-control" type="date" name="from" value="<?= e($filters['from'] ?? '') ?>">
    <input class="form-control" type="date" name="to" value="<?= e($filters['to'] ?? '') ?>">
    <button class="btn btn-outline" type="submit"><?= icon('search') ?><span>Filtrer</span></button>
</form>

<div class="medical-layout">
    <section class="card company-table-card erp-table-card medical-requests-card">
        <div class="card-header">
            <div><span class="dashboard-section-kicker">Workflow</span><h2 class="card-title">Demandes medicales</h2></div>
            <span class="badge bg-blue-lt"><?= e((string) count($requests)) ?> dossier(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table" id="medical-requests-table">
                <thead><tr><th>Reference</th><th>Beneficiaire</th><th>Soin</th><th>Prestataire</th><th>Montants</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                    <?php if ($requests === []): ?><tr><td colspan="7" class="text-secondary">Aucune demande trouvee.</td></tr><?php endif; ?>
                    <?php foreach ($requests as $request): ?>
                        <?php
                        $tone = $statusTones[$request['status'] ?? 'submitted'] ?? 'blue';
                        $beneficiary = $employeeName($request);
                        if (!empty($request['dependent_id'])) {
                            $beneficiary = trim(($request['dependent_last_name'] ?? '') . ' ' . ($request['dependent_first_name'] ?? '')) . ' · ' . ($relationships[$request['relationship'] ?? 'other'] ?? 'Ayant droit');
                        }
                        ?>
                        <tr>
                            <td><a class="company-name" href="<?= e(url('/medical/show?id=' . $request['id'])) ?>"><?= e($request['request_number']) ?></a><span class="d-block text-secondary"><?= e($request['company_name'] ?? '') ?></span></td>
                            <td><?= e($beneficiary) ?><span class="d-block text-secondary"><?= e($request['employee_number'] ?? '') ?> · <?= e($request['department_name'] ?? '-') ?></span></td>
                            <td><?= e($careTypes[$request['care_type']] ?? $request['care_type']) ?></td>
                            <td><?= e($request['provider_name'] ?: 'A confirmer') ?></td>
                            <td><strong><?= $money($request['covered_amount'], $request['currency']) ?></strong><span class="d-block text-secondary">Employe: <?= $money($request['employee_share'], $request['currency']) ?></span></td>
                            <td><span class="badge bg-<?= e($tone) ?>-lt"><?= e($statusLabels[$request['status']] ?? $request['status']) ?></span></td>
                            <td><a class="btn btn-icon" href="<?= e(url('/medical/show?id=' . $request['id'])) ?>"><?= icon('file') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <aside class="medical-side-stack">
        <section class="card company-profile-card">
            <div class="card-header"><div><span class="dashboard-section-kicker">Ayants droit</span><h2 class="card-title">Couverture familiale</h2></div></div>
            <div class="medical-mini-list">
                <?php if ($dependents === []): ?><div class="dashboard-empty"><span>Aucun ayant droit enregistre.</span></div><?php endif; ?>
                <?php foreach (array_slice($dependents, 0, 8) as $dependent): ?>
                    <article>
                        <strong><?= e(trim($dependent['last_name'] . ' ' . $dependent['first_name'])) ?></strong>
                        <span><?= e($relationships[$dependent['relationship']] ?? $dependent['relationship']) ?> · <?= e($dependent['status']) ?></span>
                        <small><?= e(($dependent['employee_last_name'] ?? '') . ' ' . ($dependent['employee_first_name'] ?? '')) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="card company-profile-card">
            <div class="card-header"><div><span class="dashboard-section-kicker">Reseau medical</span><h2 class="card-title">Prestataires</h2></div></div>
            <div class="medical-mini-list">
                <?php if ($providers === []): ?><div class="dashboard-empty"><span>Aucun prestataire conventionne.</span></div><?php endif; ?>
                <?php foreach (array_slice($providers, 0, 8) as $provider): ?>
                    <article><strong><?= e($provider['name']) ?></strong><span><?= e($provider['city'] ?: '-') ?> · <?= e($provider['provider_type']) ?></span><small><?= e($provider['phone'] ?: $provider['status']) ?></small></article>
                <?php endforeach; ?>
            </div>
        </section>
    </aside>
</div>

<?php require APP_PATH . '/Views/medical/_modals.php'; ?>

<script src="<?= e(asset('js/medical.js') . '?v=' . (string) filemtime(BASE_PATH . '/public/js/medical.js')) ?>"></script>
