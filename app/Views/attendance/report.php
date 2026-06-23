<?php
$reportRows = $reportRows ?? [];
$options = $options ?? [];
$filters = $filters ?? [];
$attendance = $attendance ?? null;
$month = $filters['month'] ?? date('Y-m');
$totals = ['present_days' => 0, 'late_days' => 0, 'absent_days' => 0, 'overtime_minutes' => 0];
foreach ($reportRows as $row) {
    $totals['present_days'] += (int) ($row['present_days'] ?? 0);
    $totals['late_days'] += (int) ($row['late_days'] ?? 0);
    $totals['absent_days'] += (int) ($row['absent_days'] ?? 0);
    $totals['overtime_minutes'] += (int) ($row['overtime_minutes'] ?? 0);
}
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Rapport</span>
        <h1 class="page-title">Rapport mensuel de presence</h1>
        <p>Synthese mensuelle des presences, retards, absences et heures supplementaires.</p>
    </div>
    <div class="module-header-actions">
        <a class="btn btn-outline" href="<?= e(url('/attendance')) ?>"><?= icon('calendar') ?><span>Pointage journalier</span></a>
        <span class="dashboard-status"><span></span><?= e(date('m/Y', strtotime($month . '-01'))) ?></span>
    </div>
</div>

<div class="attendance-summary-grid">
    <div class="card metric-card metric-card-modern">
        <div class="metric-label">Jours presents</div>
        <div class="metric-value"><?= e((string) $totals['present_days']) ?></div>
    </div>
    <div class="card metric-card metric-card-modern">
        <div class="metric-label">Retards</div>
        <div class="metric-value"><?= e((string) $totals['late_days']) ?></div>
    </div>
    <div class="card metric-card metric-card-modern">
        <div class="metric-label">Absences</div>
        <div class="metric-value"><?= e((string) $totals['absent_days']) ?></div>
    </div>
    <div class="card metric-card metric-card-modern">
        <div class="metric-label">Heures supp.</div>
        <div class="metric-value metric-value-compact"><?= e($attendance ? $attendance->formatMinutes($totals['overtime_minutes']) : '0h00') ?></div>
    </div>
</div>

<form class="employee-filter-bar attendance-filter-bar" method="get" action="<?= e(url('/attendance/report')) ?>">
    <div class="topbar-search employee-search">
        <?= icon('search') ?>
        <input type="search" data-attendance-search placeholder="Rechercher employe, matricule, departement">
    </div>
    <input class="form-control" type="month" name="month" value="<?= e($month) ?>">
    <select class="form-select" name="employee_id">
        <option value="">Tous les employes</option>
        <?php foreach ($options['employees'] ?? [] as $employee): ?>
            <?php $name = trim(($employee['last_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['first_name'] ?? '')); ?>
            <option value="<?= e((string) $employee['id']) ?>" <?= (int) ($filters['employee_id'] ?? 0) === (int) $employee['id'] ? 'selected' : '' ?>>
                <?= e($name . ' - ' . ($employee['employee_number'] ?? '')) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="department_id">
        <option value="">Tous les departements</option>
        <?php foreach ($options['departments'] ?? [] as $department): ?>
            <option value="<?= e((string) $department['id']) ?>" <?= (int) ($filters['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-outline" type="submit"><?= icon('search') ?><span>Filtrer</span></button>
</form>

<div class="card company-table-card employee-table-card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table" id="attendance-report-table">
            <thead>
                <tr>
                    <th>Employe</th>
                    <th>Departement</th>
                    <th>Jours ouvrables</th>
                    <th>Presents</th>
                    <th>Retards</th>
                    <th>Absences</th>
                    <th>Heures travaillees</th>
                    <th>Heures supp.</th>
                    <th>Taux</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportRows as $row): ?>
                    <?php $employeeName = trim(($row['last_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['first_name'] ?? '')); ?>
                    <tr>
                        <td>
                            <strong class="company-name"><?= e($employeeName) ?></strong>
                            <span class="d-block text-secondary"><?= e($row['employee_number'] ?? '-') ?></span>
                        </td>
                        <td>
                            <span class="d-block"><?= e($row['department_name'] ?? '-') ?></span>
                            <span class="d-block text-secondary"><?= e($row['position_title'] ?? '-') ?></span>
                        </td>
                        <td><?= e((string) ($row['work_days'] ?? 0)) ?></td>
                        <td><span class="badge bg-green-lt"><?= e((string) ($row['present_days'] ?? 0)) ?></span></td>
                        <td><span class="badge bg-orange-lt"><?= e((string) ($row['late_days'] ?? 0)) ?></span></td>
                        <td><span class="badge bg-red-lt"><?= e((string) ($row['absent_days'] ?? 0)) ?></span></td>
                        <td><?= e($attendance ? $attendance->formatMinutes((int) ($row['worked_minutes'] ?? 0)) : '0h00') ?></td>
                        <td><?= e($attendance ? $attendance->formatMinutes((int) ($row['overtime_minutes'] ?? 0)) : '0h00') ?></td>
                        <td>
                            <div class="attendance-rate">
                                <span><?= e((string) ($row['presence_rate'] ?? 0)) ?>%</span>
                                <div class="dashboard-bar-track"><span class="tone-green" style="width: <?= e((string) ($row['presence_rate'] ?? 0)) ?>%"></span></div>
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
<script src="<?= e(asset('js/attendance.js')) ?>"></script>
