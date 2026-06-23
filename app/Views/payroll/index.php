<?php
$periods = $periods ?? [];
$money = static fn($value): string => number_format((float) $value, 2, ',', ' ');

$totals = [
    'periods' => count($periods),
    'payslips' => 0,
    'gross' => 0.00,
    'deductions' => 0.00,
    'net' => 0.00,
    'closed' => 0,
    'open' => 0,
];

foreach ($periods as $period) {
    $totals['payslips'] += (int) ($period['payslips_count'] ?? 0);
    $totals['gross'] += (float) ($period['gross_total'] ?? 0);
    $totals['deductions'] += (float) ($period['deductions_total'] ?? 0);
    $totals['net'] += (float) ($period['net_total'] ?? 0);
    if (($period['status'] ?? '') === 'closed') {
        $totals['closed']++;
    }
    if (($period['status'] ?? '') === 'open') {
        $totals['open']++;
    }
}

$statusLabels = [
    'open' => 'Ouverte',
    'processing' => 'Traitement',
    'closed' => 'Cloturee',
    'paid' => 'Payee',
];
$statusTones = [
    'open' => 'blue',
    'processing' => 'orange',
    'closed' => 'green',
    'paid' => 'teal',
];

$latestPeriod = $periods[0] ?? null;
?>

<div class="payroll-workspace">
    <div class="payroll-command">
        <div>
            <span class="dashboard-section-kicker">Paie RDC</span>
            <h1 class="page-title">Centre de paie</h1>
        </div>
        <div class="payroll-command-actions">
            <?php if ($latestPeriod): ?>
                <span class="payroll-period-chip"><?= e(sprintf('%02d/%04d', $latestPeriod['period_month'], $latestPeriod['period_year'])) ?></span>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?= e(url('/payroll/settings')) ?>"><?= icon('settings') ?><span>Configuration paie</span></a>
            <a class="btn btn-outline" href="<?= e(url('/payroll/simulation')) ?>"><?= icon('chart') ?><span>Simulation</span></a>
            <a class="btn btn-primary" href="<?= e(url('/payroll/create')) ?>"><?= icon('file') ?><span>Nouvelle periode</span></a>
        </div>
    </div>

    <div class="payroll-kpi-grid">
        <article class="payroll-kpi payroll-kpi-net">
            <span>Net a payer</span>
            <strong><?= e($money($totals['net'])) ?></strong>
            <small><?= e((string) $totals['payslips']) ?> bulletin(s)</small>
        </article>
        <article class="payroll-kpi">
            <span>Masse brute</span>
            <strong><?= e($money($totals['gross'])) ?></strong>
            <small><?= e((string) $totals['periods']) ?> periode(s)</small>
        </article>
        <article class="payroll-kpi">
            <span>Retenues</span>
            <strong><?= e($money($totals['deductions'])) ?></strong>
            <small>IPR et cotisations salariales</small>
        </article>
        <article class="payroll-kpi">
            <span>Statut</span>
            <strong><?= e((string) $totals['closed']) ?>/<?= e((string) max(1, $totals['periods'])) ?></strong>
            <small><?= e((string) $totals['open']) ?> ouverte(s)</small>
        </article>
    </div>

    <section class="card company-table-card payroll-ledger-card">
        <div class="payroll-section-header">
            <div>
                <span class="dashboard-section-kicker">Journal mensuel</span>
                <h2 class="card-title">Periodes de paie</h2>
            </div>
            <?php if ($latestPeriod): ?>
                <a class="btn btn-outline" href="<?= e(url('/payroll/process?id=' . $latestPeriod['id'])) ?>">Ouvrir la derniere</a>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table payroll-table" id="payroll-periods-table">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th>Entreprise</th>
                        <th class="text-end">Bulletins</th>
                        <th class="text-end">Brut</th>
                        <th class="text-end">Retenues</th>
                        <th class="text-end">Net</th>
                        <th>Statut</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($periods as $period): ?>
                        <?php
                        $status = $period['status'] ?? 'open';
                        $tone = $statusTones[$status] ?? 'gray';
                        ?>
                        <tr>
                            <td>
                                <strong class="company-name"><?= e($period['name']) ?></strong>
                                <span class="d-block text-secondary"><?= e(sprintf('%02d/%04d', $period['period_month'], $period['period_year'])) ?></span>
                            </td>
                            <td><?= e($period['company_name'] ?? '-') ?></td>
                            <td class="text-end"><?= e((string) ($period['payslips_count'] ?? 0)) ?></td>
                            <td class="text-end"><?= e($money($period['gross_total'] ?? 0)) ?></td>
                            <td class="text-end"><?= e($money($period['deductions_total'] ?? 0)) ?></td>
                            <td class="text-end"><strong><?= e($money($period['net_total'] ?? 0)) ?></strong></td>
                            <td><span class="badge bg-<?= e($tone) ?>-lt"><?= e($statusLabels[$status] ?? $status) ?></span></td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    <a class="btn btn-sm btn-outline" href="<?= e(url('/payroll/process?id=' . $period['id'])) ?>">Traiter</a>
                                    <a class="btn btn-sm btn-outline" href="<?= e(url('/payroll/export?id=' . $period['id'])) ?>">Excel</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/payroll.js')) ?>"></script>
