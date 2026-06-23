<?php
$period = $period ?? [];
$journal = $journal ?? [];
$anomalies = $anomalies ?? [];
$money = static fn($value, $currency = 'USD'): string => number_format((float) $value, 2, ',', ' ') . ' ' . $currency;
$totals = ['gross' => 0.00, 'deductions' => 0.00, 'net' => 0.00, 'count' => count($journal)];
foreach ($journal as $row) {
    $totals['gross'] += (float) ($row['gross_salary'] ?? 0);
    $totals['deductions'] += (float) ($row['total_deductions'] ?? 0);
    $totals['net'] += (float) ($row['net_salary'] ?? 0);
}
$status = $period['status'] ?? 'open';
$steps = [
    'open' => 'Periode ouverte',
    'processing' => 'Calcul',
    'closed' => 'Controle termine',
    'paid' => 'Payee',
];
$stepReached = ['open' => 1, 'processing' => 2, 'closed' => 3, 'paid' => 4][$status] ?? 1;
$blockingCount = count(array_filter($anomalies, static fn(array $item): bool => ($item['severity'] ?? '') === 'danger'));
?>

<div class="payroll-workspace">
    <div class="payroll-command">
        <div>
            <span class="dashboard-section-kicker">Traitement mensuel</span>
            <h1 class="page-title"><?= e($period['name'] ?? 'Paie') ?></h1>
        </div>
        <div class="payroll-command-actions">
            <a class="btn btn-outline" href="<?= e(url('/payroll')) ?>">Centre de paie</a>
            <a class="btn btn-outline" href="<?= e(url('/payroll/export?id=' . ($period['id'] ?? 0))) ?>"><?= icon('file') ?><span>Export Excel</span></a>
            <button class="btn btn-outline" type="button" data-payroll-close="<?= e(url('/payroll/close')) ?>" data-period-id="<?= e((string) ($period['id'] ?? 0)) ?>" <?= $blockingCount > 0 || $journal === [] ? 'disabled' : '' ?>>Cloturer</button>
            <button class="btn btn-primary" type="button" data-payroll-calculate="<?= e(url('/payroll/calculate')) ?>" data-period-id="<?= e((string) ($period['id'] ?? 0)) ?>">Lancer le calcul</button>
        </div>
    </div>

    <div class="payroll-stepper">
        <?php $index = 1; ?>
        <?php foreach ($steps as $key => $label): ?>
            <div class="payroll-step <?= $index <= $stepReached ? 'is-done' : '' ?>">
                <span><?= e((string) $index) ?></span>
                <strong><?= e($label) ?></strong>
            </div>
            <?php $index++; ?>
        <?php endforeach; ?>
    </div>

    <div class="payroll-kpi-grid">
        <article class="payroll-kpi payroll-kpi-net"><span>Net a payer</span><strong><?= e($money($totals['net'])) ?></strong><small><?= e((string) $totals['count']) ?> bulletin(s)</small></article>
        <article class="payroll-kpi"><span>Masse brute</span><strong><?= e($money($totals['gross'])) ?></strong><small><?= e($period['company_name'] ?? '-') ?></small></article>
        <article class="payroll-kpi"><span>Retenues</span><strong><?= e($money($totals['deductions'])) ?></strong><small>IPR et cotisations</small></article>
        <article class="payroll-kpi"><span>Anomalies</span><strong><?= e((string) count($anomalies)) ?></strong><small><?= e((string) $blockingCount) ?> bloquante(s)</small></article>
    </div>

    <div class="alert alert-danger d-none" data-payroll-error></div>

    <div class="payroll-main-grid">
        <section class="card company-table-card payroll-ledger-card">
            <div class="payroll-section-header">
                <div>
                    <span class="dashboard-section-kicker">Controle bulletins</span>
                    <h2 class="card-title">Journal de paie</h2>
                </div>
                <div class="topbar-search payroll-table-search">
                    <?= icon('search') ?>
                    <input type="search" data-payroll-search placeholder="Rechercher employe, departement, statut">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table payroll-table" id="payroll-journal-table">
                    <thead>
                        <tr>
                            <th>Employe</th>
                            <th>Departement</th>
                            <th class="text-end">Brut</th>
                            <th class="text-end">Retenues</th>
                            <th class="text-end">Net</th>
                            <th>Statut</th>
                            <th class="w-1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($journal as $row): ?>
                            <?php $employee = trim(($row['last_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['first_name'] ?? '')); ?>
                            <tr>
                                <td><strong class="company-name"><?= e($employee) ?></strong><span class="d-block text-secondary"><?= e($row['employee_number'] ?? '-') ?></span></td>
                                <td><?= e($row['department_name'] ?? '-') ?></td>
                                <td class="text-end"><?= e($money($row['gross_salary'] ?? 0, $row['currency'] ?? 'USD')) ?></td>
                                <td class="text-end"><?= e($money($row['total_deductions'] ?? 0, $row['currency'] ?? 'USD')) ?></td>
                                <td class="text-end"><strong><?= e($money($row['net_salary'] ?? 0, $row['currency'] ?? 'USD')) ?></strong></td>
                                <td><span class="badge bg-blue-lt"><?= e($row['status'] ?? '-') ?></span></td>
                                <td>
                                    <div class="btn-list flex-nowrap">
                                        <a class="btn btn-sm btn-outline" href="<?= e(url('/payroll/payslip?id=' . $row['id'])) ?>">Bulletin</a>
                                        <a class="btn btn-sm btn-outline" href="<?= e(url('/payroll/payslip/pdf?id=' . $row['id'])) ?>" target="_blank">PDF</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="payroll-control-panel">
            <div class="payroll-control-header">
                <span class="dashboard-section-kicker">Anomalies</span>
                <strong>Controle avant cloture</strong>
            </div>
            <div class="payroll-anomaly-list">
                <?php if ($anomalies === []): ?>
                    <div class="payroll-anomaly is-success"><strong>Aucune anomalie</strong><span>La periode peut etre cloturee.</span></div>
                <?php endif; ?>
                <?php foreach ($anomalies as $anomaly): ?>
                    <div class="payroll-anomaly is-<?= e($anomaly['severity'] ?? 'info') ?>">
                        <strong><?= e($anomaly['title'] ?? '-') ?></strong>
                        <span><?= e($anomaly['detail'] ?? '-') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/payroll.js')) ?>"></script>
