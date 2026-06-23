<?php
$managerRows = $managerRows ?? [];
$hrRows = $hrRows ?? [];
$canManagerApprove = !empty($canManagerApprove);
$canHrApprove = !empty($canHrApprove);
$renderRows = static function (array $rows, string $stage): void {
    if ($rows === []) {
        echo '<tr><td colspan="7" class="text-secondary">Aucune demande en attente.</td></tr>';
        return;
    }

    foreach ($rows as $row) {
        $employee = trim(($row['last_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['first_name'] ?? ''));
        echo '<tr>';
        echo '<td><strong class="company-name">' . e($employee) . '</strong><span class="d-block text-secondary">' . e($row['employee_number'] ?? '-') . '</span></td>';
        echo '<td>' . e($row['leave_type_name'] ?? '-') . '</td>';
        echo '<td>' . e($row['start_date'] ?? '-') . ' au ' . e($row['end_date'] ?? '-') . '</td>';
        echo '<td>' . e(number_format((float) ($row['total_days'] ?? 0), 2, ',', ' ')) . '</td>';
        echo '<td>' . e($row['department_name'] ?? '-') . '</td>';
        echo '<td>' . e($row['reason'] ?? '-') . '</td>';
        echo '<td><div class="btn-list flex-nowrap">';
        if ($stage === 'manager') {
            echo '<button class="btn btn-sm btn-primary" type="button" data-leave-approve="' . e(url('/leaves/approve-manager')) . '" data-leave-id="' . e((string) $row['id']) . '">Valider manager</button>';
        } else {
            echo '<button class="btn btn-sm btn-primary" type="button" data-leave-approve="' . e(url('/leaves/approve-hr')) . '" data-leave-id="' . e((string) $row['id']) . '">Valider RH</button>';
        }
        echo '<button class="btn btn-sm btn-outline-danger" type="button" data-leave-reject="' . e(url('/leaves/reject')) . '" data-leave-id="' . e((string) $row['id']) . '">Refuser</button>';
        echo '</div></td>';
        echo '</tr>';
    }
};
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Workflow</span>
        <h1 class="page-title">Validation des conges</h1>
        <p>Traitez les demandes en attente avec validation manager puis validation RH.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/leaves')) ?>"><?= icon('calendar') ?><span>Conges</span></a>
</div>

<div class="alert alert-danger d-none" data-leave-error></div>

<?php if ($canManagerApprove): ?>
    <div class="card company-table-card mb-4">
        <div class="card-header">
            <div>
                <span class="dashboard-section-kicker">Manager</span>
                <h2 class="card-title">Demandes en attente manager</h2>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Employe</th>
                        <th>Type</th>
                        <th>Periode</th>
                        <th>Jours</th>
                        <th>Departement</th>
                        <th>Motif</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
                <tbody><?php $renderRows($managerRows, 'manager'); ?></tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($canHrApprove): ?>
    <div class="card company-table-card">
        <div class="card-header">
            <div>
                <span class="dashboard-section-kicker">Ressources humaines</span>
                <h2 class="card-title">Demandes en attente RH</h2>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Employe</th>
                        <th>Type</th>
                        <th>Periode</th>
                        <th>Jours</th>
                        <th>Departement</th>
                        <th>Motif</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
                <tbody><?php $renderRows($hrRows, 'hr'); ?></tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/leaves.js')) ?>"></script>
