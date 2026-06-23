<?php
$employees = $employees ?? [];
$options = $options ?? [];
$filters = $filters ?? [];
$statusLabels = [
    'active' => 'Actif',
    'on_leave' => 'En conge',
    'suspended' => 'Suspendu',
    'terminated' => 'Archive',
];
$statusTones = [
    'active' => 'green',
    'on_leave' => 'orange',
    'suspended' => 'red',
    'terminated' => 'gray',
];
$statusCounts = array_fill_keys(array_keys($statusLabels), 0);
$departmentCount = [];
$payrollTotal = 0.00;
foreach ($employees as $employee) {
    $status = $employee['employment_status'] ?? 'active';
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
    $department = $employee['department_name'] ?? 'Non defini';
    $departmentCount[$department] = ($departmentCount[$department] ?? 0) + 1;
    $payrollTotal += (float) ($employee['base_salary'] ?? 0);
}
$topDepartment = $departmentCount === [] ? '-' : array_key_first($departmentCount);
if ($departmentCount !== []) {
    arsort($departmentCount);
    $topDepartment = (string) array_key_first($departmentCount);
}
$money = static fn($value): string => number_format((float) $value, 2, ',', ' ') . ' USD';
?>

<div class="module-header module-header-rich erp-page-header employee-erp-header">
    <div>
        <span class="dashboard-section-kicker">Registre RH</span>
        <h1 class="page-title">Employes</h1>
        <p>Registre central des dossiers du personnel, contrats, rattachements, contacts et documents.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/employees/create')) ?>"><?= icon('users') ?><span>Nouvel employe</span></a>
</div>

<div class="erp-summary-strip employee-summary-strip">
    <article class="dashboard-pulse-item"><span>Total</span><strong><?= e((string) count($employees)) ?></strong><small>Dossiers visibles</small></article>
    <article class="dashboard-pulse-item"><span>Actifs</span><strong><?= e((string) ($statusCounts['active'] ?? 0)) ?></strong><small>En service</small></article>
    <article class="dashboard-pulse-item"><span>En conge</span><strong><?= e((string) ($statusCounts['on_leave'] ?? 0)) ?></strong><small>Absences autorisees</small></article>
    <article class="dashboard-pulse-item"><span>Masse de base</span><strong><?= e($money($payrollTotal)) ?></strong><small><?= e($topDepartment) ?></small></article>
</div>

<form class="employee-filter-bar erp-filter-bar" method="get" action="<?= e(url('/employees')) ?>">
    <div class="topbar-search employee-search">
        <?= icon('search') ?>
        <input type="search" data-employee-search placeholder="Rechercher matricule, nom, email, telephone">
    </div>
    <select class="form-select" name="branch_id">
        <option value="">Tous les sites</option>
        <?php foreach ($options['branches'] ?? [] as $branch): ?>
            <option value="<?= e((string) $branch['id']) ?>" <?= (int) ($filters['branch_id'] ?? 0) === (int) $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="department_id">
        <option value="">Tous les departements</option>
        <?php foreach ($options['departments'] ?? [] as $department): ?>
            <option value="<?= e((string) $department['id']) ?>" <?= (int) ($filters['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="position_id">
        <option value="">Tous les postes</option>
        <?php foreach ($options['positions'] ?? [] as $position): ?>
            <option value="<?= e((string) $position['id']) ?>" <?= (int) ($filters['position_id'] ?? 0) === (int) $position['id'] ? 'selected' : '' ?>><?= e($position['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="employment_status">
        <option value="">Tous les statuts</option>
        <?php foreach ($statusLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['employment_status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-outline" type="submit"><?= icon('search') ?><span>Filtrer</span></button>
</form>

<div class="card company-table-card employee-table-card erp-table-card">
    <div class="erp-table-heading">
        <div>
            <span class="dashboard-section-kicker">Liste</span>
            <h2>Registre des employes</h2>
        </div>
        <span><?= e((string) count($employees)) ?> dossier(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table" id="employees-table">
            <thead>
                <tr>
                    <th>Employe</th>
                    <th>Affectation</th>
                    <th>Contact</th>
                    <th>Contrat</th>
                    <th>Embauche</th>
                    <th>Statut</th>
                    <th class="w-1">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $employee): ?>
                    <?php
                    $status = $employee['employment_status'] ?? 'active';
                    $tone = $statusTones[$status] ?? 'blue';
                    $photo = !empty($employee['photo_path']) ? url($employee['photo_path']) : null;
                    ?>
                    <tr data-employee-row="<?= e((string) $employee['id']) ?>">
                        <td>
                            <div class="employee-cell">
                                <?php if ($photo): ?>
                                    <img class="employee-avatar" src="<?= e($photo) ?>" alt="">
                                <?php else: ?>
                                    <span class="employee-avatar employee-avatar-fallback"><?= e(strtoupper(substr($employee['first_name'] ?? 'E', 0, 1) . substr($employee['last_name'] ?? '', 0, 1))) ?></span>
                                <?php endif; ?>
                                <div>
                                    <a class="company-name" href="<?= e(url('/employees/show?id=' . $employee['id'])) ?>"><?= e(($employee['last_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['first_name'] ?? '')) ?></a>
                                    <span class="d-block text-secondary"><?= e($employee['employee_number'] ?? '-') ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="d-block"><?= e($employee['department_name'] ?? '-') ?></span>
                            <span class="d-block text-secondary"><?= e($employee['position_title'] ?? '-') ?> · <?= e($employee['branch_name'] ?? '-') ?></span>
                        </td>
                        <td>
                            <span class="d-block"><?= e($employee['phone'] ?: '-') ?></span>
                            <span class="d-block text-secondary"><?= e($employee['email'] ?: '-') ?></span>
                        </td>
                        <td>
                            <span class="d-block"><?= e(strtoupper($employee['contract_type'] ?? '-')) ?></span>
                            <span class="d-block text-secondary"><?= e(number_format((float) ($employee['base_salary'] ?? 0), 2, ',', ' ')) ?> <?= e($employee['currency'] ?? 'USD') ?></span>
                        </td>
                        <td><?= e($employee['hire_date'] ?: '-') ?></td>
                        <td><span class="badge bg-<?= e($tone) ?>-lt"><?= e($statusLabels[$status] ?? $status) ?></span></td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                <a class="btn btn-icon" href="<?= e(url('/employees/show?id=' . $employee['id'])) ?>"><?= icon('file') ?></a>
                                <a class="btn btn-icon" href="<?= e(url('/employees/edit?id=' . $employee['id'])) ?>"><?= icon('settings') ?></a>
                                <button class="btn btn-icon btn-outline-danger" type="button" data-employee-archive="<?= e((string) $employee['id']) ?>"><?= icon('x') ?></button>
                            </div>
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
<script src="<?= e(asset('js/employees.js')) ?>"></script>
