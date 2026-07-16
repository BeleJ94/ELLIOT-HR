<?php
$reportRows = $reportRows ?? [];
$options = $options ?? [];
$filters = $filters ?? [];
$calendarDays = $calendarDays ?? [];
$attendance = $attendance ?? null;
$month = $filters['month'] ?? date('Y-m');
$totals = ['present_days' => 0, 'late_days' => 0, 'absent_days' => 0, 'unrecorded_days' => 0, 'overtime_minutes' => 0];
foreach ($reportRows as $row) {
    $totals['present_days'] += (int) ($row['present_days'] ?? 0);
    $totals['late_days'] += (int) ($row['late_days'] ?? 0);
    $totals['absent_days'] += (int) ($row['absent_days'] ?? 0);
    $totals['unrecorded_days'] += (int) ($row['unrecorded_days'] ?? 0);
    $totals['overtime_minutes'] += (int) ($row['overtime_minutes'] ?? 0);
}
$statusMeta = [
    'present' => ['code' => 'P', 'label' => 'Présent', 'tone' => 'present'],
    'late' => ['code' => 'R', 'label' => 'Retard', 'tone' => 'late'],
    'absent' => ['code' => 'A', 'label' => 'Absent', 'tone' => 'absent'],
    'half_day' => ['code' => '½', 'label' => 'Demi-journée', 'tone' => 'half-day'],
    'leave' => ['code' => 'C', 'label' => 'Congé', 'tone' => 'leave'],
    'holiday' => ['code' => 'F', 'label' => 'Jour férié', 'tone' => 'holiday'],
];
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
        <div class="metric-label">Non encodés</div>
        <div class="metric-value"><?= e((string) $totals['unrecorded_days']) ?></div>
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

<div class="attendance-report-toolbar">
    <div class="attendance-report-tabs" role="tablist" aria-label="Mode d'affichage du rapport">
        <button class="is-active" type="button" role="tab" aria-selected="true" data-report-view="calendar"><?= icon('calendar') ?><span>Vue calendrier</span></button>
        <button type="button" role="tab" aria-selected="false" data-report-view="summary"><?= icon('chart') ?><span>Vue synthèse</span></button>
    </div>
    <div class="attendance-report-legend" aria-label="Légende des statuts">
        <?php foreach ($statusMeta as $meta): ?>
            <span><i class="attendance-day-badge is-<?= e($meta['tone']) ?>"><?= e($meta['code']) ?></i><?= e($meta['label']) ?></span>
        <?php endforeach; ?>
        <span><i class="attendance-day-badge is-unrecorded">?</i>Non encodé</span>
    </div>
</div>

<div class="card attendance-matrix-card" data-report-panel="calendar">
    <div class="attendance-matrix-scroll">
        <table class="table table-vcenter attendance-matrix" id="attendance-calendar-table">
            <thead>
                <tr>
                    <th class="matrix-sticky matrix-agent-column">Agent</th>
                    <th class="matrix-sticky matrix-number-column">Matricule</th>
                    <th class="matrix-sticky matrix-department-column">Département</th>
                    <?php foreach ($calendarDays as $day): ?>
                        <th class="matrix-day-heading <?= $day['is_weekend'] ? 'is-weekend' : '' ?> <?= $day['is_today'] ? 'is-today' : '' ?>" title="<?= e($day['date']) ?>">
                            <span><?= e($day['weekday_label']) ?></span>
                            <strong><?= e(str_pad((string) $day['day'], 2, '0', STR_PAD_LEFT)) ?></strong>
                        </th>
                    <?php endforeach; ?>
                    <th class="matrix-total-heading" title="Présences">P</th>
                    <th class="matrix-total-heading" title="Retards">R</th>
                    <th class="matrix-total-heading" title="Absences">A</th>
                    <th class="matrix-total-heading" title="Taux de présence">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportRows as $row): ?>
                    <?php
                    $employeeName = trim(($row['last_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['first_name'] ?? ''));
                    $employeeDays = $row['days'] ?? [];
                    ?>
                    <tr data-report-calendar-row>
                        <td class="matrix-sticky matrix-agent-column"><strong><?= e($employeeName) ?></strong><small><?= e($row['position_title'] ?? '-') ?></small></td>
                        <td class="matrix-sticky matrix-number-column"><?= e($row['employee_number'] ?? '-') ?></td>
                        <td class="matrix-sticky matrix-department-column"><?= e($row['department_name'] ?? '-') ?></td>
                        <?php foreach ($calendarDays as $day): ?>
                            <?php
                            $entry = $employeeDays[$day['date']] ?? null;
                            $status = $entry['status'] ?? null;
                            $meta = $statusMeta[$status] ?? null;
                            $cellTone = $meta['tone'] ?? ($day['is_future'] ? 'future' : ($day['is_weekend'] ? 'weekend' : 'unrecorded'));
                            $cellCode = $meta['code'] ?? ($day['is_future'] || $day['is_weekend'] ? '—' : '?');
                            $detail = $meta['label'] ?? ($day['is_future'] ? 'Date future' : ($day['is_weekend'] ? 'Week-end' : 'Journée non encodée'));
                            if ($entry && !empty($entry['check_in'])) {
                                $detail .= ' · Entrée ' . substr((string) $entry['check_in'], 0, 5);
                            }
                            if ($entry && !empty($entry['check_out'])) {
                                $detail .= ' · Sortie ' . substr((string) $entry['check_out'], 0, 5);
                            }
                            if ($entry && !empty($entry['notes'])) {
                                $detail .= ' · ' . $entry['notes'];
                            }
                            ?>
                            <td class="matrix-day-cell <?= $day['is_today'] ? 'is-today' : '' ?>">
                                <span class="attendance-day-badge is-<?= e($cellTone) ?>" title="<?= e($day['weekday_label'] . ' ' . str_pad((string) $day['day'], 2, '0', STR_PAD_LEFT) . ' : ' . $detail) ?>" aria-label="<?= e($detail) ?>"><?= e($cellCode) ?></span>
                            </td>
                        <?php endforeach; ?>
                        <td class="matrix-total-cell is-present"><?= e((string) ($row['present_days'] ?? 0)) ?></td>
                        <td class="matrix-total-cell is-late"><?= e((string) ($row['late_days'] ?? 0)) ?></td>
                        <td class="matrix-total-cell is-absent"><?= e((string) ($row['absent_days'] ?? 0)) ?></td>
                        <td class="matrix-rate-cell"><?= e((string) ($row['presence_rate'] ?? 0)) ?>%</td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($reportRows === []): ?>
                    <tr><td class="attendance-matrix-empty" colspan="<?= e((string) (count($calendarDays) + 7)) ?>">Aucun agent ne correspond aux filtres sélectionnés.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card company-table-card employee-table-card d-none" data-report-panel="summary">
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
                    <th>Non encodés</th>
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
                        <td><span class="badge bg-secondary-lt"><?= e((string) ($row['unrecorded_days'] ?? 0)) ?></span></td>
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
