<?php
$payslip = $payslip ?? [];
$lines = $payslip['lines'] ?? [];
$money = static fn($value): string => number_format((float) $value, 2, ',', ' ') . ' ' . ($payslip['currency'] ?? 'USD');
$employee = trim(($payslip['last_name'] ?? '') . ' ' . ($payslip['middle_name'] ?? '') . ' ' . ($payslip['first_name'] ?? ''));
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Bulletin</span>
        <h1 class="page-title"><?= e($employee) ?></h1>
        <p><?= e($payslip['period_name'] ?? '-') ?> · <?= e($payslip['company_name'] ?? '-') ?></p>
    </div>
    <div class="module-header-actions">
        <a class="btn btn-outline" href="<?= e(url('/payroll/process?id=' . ($payslip['payroll_period_id'] ?? 0))) ?>">Journal</a>
        <a class="btn btn-primary" href="<?= e(url('/payroll/payslip/pdf?id=' . ($payslip['id'] ?? 0))) ?>" target="_blank">PDF</a>
    </div>
</div>

<div class="attendance-summary-grid">
    <div class="card metric-card metric-card-modern"><div class="metric-label">Brut</div><div class="metric-value metric-value-compact"><?= e($money($payslip['gross_salary'] ?? 0)) ?></div></div>
    <div class="card metric-card metric-card-modern"><div class="metric-label">Retenues</div><div class="metric-value metric-value-compact"><?= e($money($payslip['total_deductions'] ?? 0)) ?></div></div>
    <div class="card metric-card metric-card-modern"><div class="metric-label">Net</div><div class="metric-value metric-value-compact"><?= e($money($payslip['net_salary'] ?? 0)) ?></div></div>
    <div class="card metric-card metric-card-modern"><div class="metric-label">Statut</div><div class="metric-value metric-value-compact"><?= e($payslip['status'] ?? '-') ?></div></div>
</div>

<div class="card company-table-card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Rubrique</th>
                    <th>Type</th>
                    <th>Base</th>
                    <th>Taux</th>
                    <th>Montant</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line): ?>
                    <tr>
                        <td><strong><?= e($line['name']) ?></strong><span class="d-block text-secondary"><?= e($line['code']) ?></span></td>
                        <td><?= e($line['type']) ?></td>
                        <td><?= e($money($line['base_amount'])) ?></td>
                        <td><?= e(number_format((float) $line['rate'], 4, ',', ' ')) ?>%</td>
                        <td><?= e($money($line['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
