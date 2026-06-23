<?php
$requests = $requests ?? [];
$types = $types ?? [];
$balances = $balances ?? [];
$calendarRows = $calendarRows ?? [];
$employees = $employees ?? [];
$departments = $departments ?? [];
$filters = $filters ?? [];
$companies = $companies ?? [];
$month = $month ?? date('Y-m');
$canManageTypes = !empty($canManageTypes);
$isSuperAdmin = !empty($isSuperAdmin);
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$statusLabels = [
    'pending' => 'En attente',
    'approved' => 'Approuve',
    'rejected' => 'Refuse',
    'cancelled' => 'Annule',
];
$statusTones = [
    'pending' => 'orange',
    'approved' => 'green',
    'rejected' => 'red',
    'cancelled' => 'gray',
];
$summary = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'days' => 0];
foreach ($requests as $request) {
    $status = $request['status'] ?? 'pending';
    if (isset($summary[$status])) {
        $summary[$status]++;
    }
    if ($status === 'approved') {
        $summary['days'] += (float) ($request['total_days'] ?? 0);
    }
}
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Conges et absences</span>
        <h1 class="page-title">Conges</h1>
        <p>Demandes, soldes, validations, refus motives et calendrier des absences.</p>
    </div>
    <div class="module-header-actions">
        <a class="btn btn-outline" href="<?= e(url('/leaves/approval')) ?>"><?= icon('check') ?><span>Validation</span></a>
        <a class="btn btn-primary" href="<?= e(url('/leaves/create')) ?>"><?= icon('calendar') ?><span>Nouvelle demande</span></a>
    </div>
</div>

<div class="attendance-summary-grid">
    <div class="card metric-card metric-card-modern">
        <div class="metric-label">En attente</div>
        <div class="metric-value"><?= e((string) $summary['pending']) ?></div>
    </div>
    <div class="card metric-card metric-card-modern">
        <div class="metric-label">Approuves</div>
        <div class="metric-value"><?= e((string) $summary['approved']) ?></div>
    </div>
    <div class="card metric-card metric-card-modern">
        <div class="metric-label">Refuses</div>
        <div class="metric-value"><?= e((string) $summary['rejected']) ?></div>
    </div>
    <div class="card metric-card metric-card-modern">
        <div class="metric-label">Jours approuves</div>
        <div class="metric-value metric-value-compact"><?= e(number_format($summary['days'], 2, ',', ' ')) ?></div>
    </div>
</div>

<form class="employee-filter-bar attendance-filter-bar" method="get" action="<?= e(url('/leaves')) ?>">
    <div class="topbar-search employee-search">
        <?= icon('search') ?>
        <input type="search" data-leave-search placeholder="Rechercher employe, type, statut">
    </div>
    <select class="form-select" name="employee_id">
        <option value="">Tous les employes</option>
        <?php foreach ($employees as $employee): ?>
            <?php $name = trim(($employee['last_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['first_name'] ?? '')); ?>
            <option value="<?= e((string) $employee['id']) ?>" <?= (int) ($filters['employee_id'] ?? 0) === (int) $employee['id'] ? 'selected' : '' ?>>
                <?= e($name . ' - ' . ($employee['employee_number'] ?? '')) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="department_id">
        <option value="">Tous les departements</option>
        <?php foreach ($departments as $department): ?>
            <option value="<?= e((string) $department['id']) ?>" <?= (int) ($filters['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="leave_type_id">
        <option value="">Tous les types</option>
        <?php foreach ($types as $type): ?>
            <option value="<?= e((string) $type['id']) ?>" <?= (int) ($filters['leave_type_id'] ?? 0) === (int) $type['id'] ? 'selected' : '' ?>><?= e($type['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="status">
        <option value="">Tous les statuts</option>
        <?php foreach ($statusLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input class="form-control" type="date" name="from" value="<?= e($filters['from'] ?? '') ?>">
    <input class="form-control" type="date" name="to" value="<?= e($filters['to'] ?? '') ?>">
    <button class="btn btn-outline" type="submit"><?= icon('search') ?><span>Filtrer</span></button>
</form>

<div class="leave-grid">
    <div class="card company-table-card">
        <div class="card-header">
            <div>
                <span class="dashboard-section-kicker">Solde <?= e(date('Y')) ?></span>
                <h2 class="card-title">Soldes de conge</h2>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Employe</th>
                        <th>Type</th>
                        <th>Droit</th>
                        <th>Pris</th>
                        <th>Restant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($balances === []): ?>
                        <tr><td colspan="5" class="text-secondary">Aucun solde configure.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($balances as $balance): ?>
                        <?php $employee = trim(($balance['last_name'] ?? '') . ' ' . ($balance['middle_name'] ?? '') . ' ' . ($balance['first_name'] ?? '')); ?>
                        <?php $remaining = (float) ($balance['annual_days'] ?? 0) - (float) ($balance['used_days'] ?? 0); ?>
                        <tr>
                            <td><?= e($employee) ?></td>
                            <td><?= e($balance['leave_type_name'] ?? '-') ?></td>
                            <td><?= e(number_format((float) ($balance['annual_days'] ?? 0), 2, ',', ' ')) ?></td>
                            <td><?= e(number_format((float) ($balance['used_days'] ?? 0), 2, ',', ' ')) ?></td>
                            <td><span class="badge bg-green-lt"><?= e(number_format($remaining, 2, ',', ' ')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card company-table-card">
        <div class="card-header">
            <div>
                <span class="dashboard-section-kicker">Calendrier</span>
                <h2 class="card-title">Absences approuvees</h2>
            </div>
        </div>
        <div class="leave-calendar-list">
            <?php if ($calendarRows === []): ?>
                <div class="dashboard-empty"><span>Aucune absence approuvee ce mois.</span></div>
            <?php endif; ?>
            <?php foreach ($calendarRows as $row): ?>
                <?php $employee = trim(($row['last_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['first_name'] ?? '')); ?>
                <article class="leave-calendar-item">
                    <strong><?= e($employee) ?></strong>
                    <span><?= e($row['leave_type_name'] ?? '-') ?> · <?= e($row['start_date']) ?> au <?= e($row['end_date']) ?></span>
                    <small><?= e($row['department_name'] ?? '-') ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($canManageTypes): ?>
    <div class="card company-form-card mb-4">
        <div class="card-header">
            <div>
                <span class="dashboard-section-kicker">Types</span>
                <h2 class="card-title">Types de conges</h2>
            </div>
        </div>
        <form method="post" action="<?= e(url('/leaves/types/store')) ?>" data-leave-form>
            <?= csrf_field() ?>
            <div class="card-body">
                <div class="alert alert-danger d-none" data-form-error></div>
                <div class="row g-3">
                    <?php if ($isSuperAdmin): ?>
                        <div class="col-md-3">
                            <label class="form-label" for="leave_type_company">Entreprise</label>
                            <select id="leave_type_company" class="form-select" name="company_id" required>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?= e((string) $company['id']) ?>" <?= $defaultCompanyId === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="company_id" value="<?= e((string) $defaultCompanyId) ?>">
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label" for="leave_type_name">Nom</label>
                        <input id="leave_type_name" class="form-control" name="name" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="leave_type_code">Code</label>
                        <input id="leave_type_code" class="form-control" name="code" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="leave_type_days">Jours/an</label>
                        <input id="leave_type_days" class="form-control" type="number" step="0.5" min="0" name="annual_days" value="0">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="paid" value="1" checked>
                            <span class="form-check-label">Paye</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary" type="submit" data-submit-label>Ajouter</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Code</th>
                        <th>Entreprise</th>
                        <th>Jours/an</th>
                        <th>Paye</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($types as $type): ?>
                        <tr>
                            <td><?= e($type['name']) ?></td>
                            <td><?= e($type['code']) ?></td>
                            <td><?= e($type['company_name'] ?? '-') ?></td>
                            <td><?= e(number_format((float) ($type['annual_days'] ?? 0), 2, ',', ' ')) ?></td>
                            <td><span class="badge bg-<?= !empty($type['paid']) ? 'green' : 'red' ?>-lt"><?= !empty($type['paid']) ? 'Oui' : 'Non' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="card company-table-card employee-table-card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table" id="leaves-table">
            <thead>
                <tr>
                    <th>Employe</th>
                    <th>Type</th>
                    <th>Periode</th>
                    <th>Jours</th>
                    <th>Workflow</th>
                    <th>Statut</th>
                    <th>Motif / Refus</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $request): ?>
                    <?php
                    $employee = trim(($request['last_name'] ?? '') . ' ' . ($request['middle_name'] ?? '') . ' ' . ($request['first_name'] ?? ''));
                    $status = $request['status'] ?? 'pending';
                    $tone = $statusTones[$status] ?? 'blue';
                    ?>
                    <tr>
                        <td>
                            <strong class="company-name"><?= e($employee) ?></strong>
                            <span class="d-block text-secondary"><?= e($request['employee_number'] ?? '-') ?></span>
                        </td>
                        <td><?= e($request['leave_type_name'] ?? '-') ?></td>
                        <td><?= e($request['start_date'] ?? '-') ?> au <?= e($request['end_date'] ?? '-') ?></td>
                        <td><?= e(number_format((float) ($request['total_days'] ?? 0), 2, ',', ' ')) ?></td>
                        <td>
                            <span class="badge bg-<?= ($request['manager_status'] ?? '') === 'approved' ? 'green' : (($request['manager_status'] ?? '') === 'rejected' ? 'red' : 'orange') ?>-lt">Manager: <?= e($request['manager_status'] ?? 'pending') ?></span>
                            <span class="badge bg-<?= ($request['hr_status'] ?? '') === 'approved' ? 'green' : (($request['hr_status'] ?? '') === 'rejected' ? 'red' : 'orange') ?>-lt">RH: <?= e($request['hr_status'] ?? 'pending') ?></span>
                        </td>
                        <td><span class="badge bg-<?= e($tone) ?>-lt"><?= e($statusLabels[$status] ?? $status) ?></span></td>
                        <td>
                            <span class="d-block"><?= e($request['reason'] ?: '-') ?></span>
                            <?php if (!empty($request['rejection_reason'])): ?>
                                <span class="d-block text-danger"><?= e($request['rejection_reason']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/leaves.js')) ?>"></script>
