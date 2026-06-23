<?php
$declarations = $declarations ?? [];
$periods = $periods ?? [];
$totals = $totals ?? [];
$alerts = $alerts ?? [];
$money = static fn($value, $currency = 'USD'): string => number_format((float) $value, 2, ',', ' ') . ' ' . $currency;
$statusLabels = ['pending' => 'En attente', 'paid' => 'Payee', 'late' => 'En retard'];
$statusTones = ['pending' => 'orange', 'paid' => 'green', 'late' => 'red'];
?>

<div class="payroll-workspace declarations-workspace">
    <div class="payroll-command">
        <div>
            <span class="dashboard-section-kicker">Fiscal et social RDC</span>
            <h1 class="page-title">Declarations</h1>
        </div>
        <div class="payroll-command-actions">
            <a class="btn btn-outline" href="<?= e(url('/payroll')) ?>"><?= icon('file') ?><span>Centre de paie</span></a>
        </div>
    </div>

    <div class="payroll-kpi-grid">
        <article class="payroll-kpi payroll-kpi-net"><span>Total a payer</span><strong><?= e($money($totals['due'] ?? 0)) ?></strong><small><?= e((string) count($declarations)) ?> declaration(s)</small></article>
        <article class="payroll-kpi"><span>Retenues salariales</span><strong><?= e($money($totals['withheld'] ?? 0)) ?></strong><small>IPR, CNSS, INPP, ONEM</small></article>
        <article class="payroll-kpi"><span>Charges patronales</span><strong><?= e($money($totals['employer'] ?? 0)) ?></strong><small>CNSS, INPP, ONEM</small></article>
        <article class="payroll-kpi"><span>Suivi paiement</span><strong><?= e((string) ($totals['paid'] ?? 0)) ?>/<?= e((string) max(1, count($declarations))) ?></strong><small><?= e((string) ($totals['late'] ?? 0)) ?> en retard</small></article>
    </div>

    <?php if ($alerts !== []): ?>
        <div class="declaration-alert-grid">
            <?php foreach ($alerts as $alert): ?>
                <div class="payroll-anomaly is-<?= e($alert['severity'] ?? 'warning') ?>">
                    <strong><?= e($alert['title'] ?? '-') ?></strong>
                    <span><?= e($alert['detail'] ?? '-') ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="payroll-main-grid">
        <section class="card company-table-card payroll-ledger-card">
            <div class="payroll-section-header">
                <div>
                    <span class="dashboard-section-kicker">Generation</span>
                    <h2 class="card-title">Periodes de paie</h2>
                </div>
            </div>
            <div class="alert alert-danger d-none" data-declaration-error></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table payroll-table" id="declaration-periods-table">
                    <thead>
                        <tr>
                            <th>Periode</th>
                            <th>Entreprise</th>
                            <th class="text-end">Bulletins</th>
                            <th>Declaration</th>
                            <th class="text-end">Total du</th>
                            <th class="w-1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($periods as $period): ?>
                            <?php $status = $period['payment_status'] ?? null; ?>
                            <tr>
                                <td><strong class="company-name"><?= e($period['name']) ?></strong><span class="d-block text-secondary"><?= e(sprintf('%02d/%04d', $period['period_month'], $period['period_year'])) ?></span></td>
                                <td><?= e($period['company_name'] ?? '-') ?></td>
                                <td class="text-end"><?= e((string) ($period['payslips_count'] ?? 0)) ?></td>
                                <td>
                                    <?php if (!empty($period['declaration_id'])): ?>
                                        <span class="badge bg-<?= e($statusTones[$status] ?? 'blue') ?>-lt"><?= e($statusLabels[$status] ?? '-') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-gray-lt">A generer</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= e($money($period['total_due'] ?? 0)) ?></td>
                                <td>
                                    <div class="btn-list flex-nowrap">
                                        <?php if (!empty($period['declaration_id'])): ?>
                                            <a class="btn btn-sm btn-outline" href="<?= e(url('/declarations/show?id=' . $period['declaration_id'])) ?>">Ouvrir</a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-primary" type="button" data-declaration-generate="<?= e(url('/declarations/generate')) ?>" data-period-id="<?= e((string) $period['id']) ?>">Generer</button>
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
                <span class="dashboard-section-kicker">Echeances</span>
                <strong>Alertes</strong>
            </div>
            <div class="payroll-anomaly-list">
                <?php if ($alerts === []): ?>
                    <div class="payroll-anomaly is-success"><strong>Aucune alerte</strong><span>Les declarations suivies sont a jour.</span></div>
                <?php endif; ?>
                <?php foreach (array_slice($alerts, 0, 5) as $alert): ?>
                    <div class="payroll-anomaly is-<?= e($alert['severity'] ?? 'warning') ?>">
                        <strong><?= e($alert['title'] ?? '-') ?></strong>
                        <span><?= e($alert['detail'] ?? '-') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>

    <section class="card company-table-card payroll-ledger-card">
        <div class="payroll-section-header">
            <div>
                <span class="dashboard-section-kicker">Historique</span>
                <h2 class="card-title">Declarations generees</h2>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table payroll-table" id="declarations-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Entreprise</th>
                        <th>Periode</th>
                        <th>Echeance</th>
                        <th class="text-end">Retenues</th>
                        <th class="text-end">Charges</th>
                        <th class="text-end">Total</th>
                        <th>Statut</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($declarations as $declaration): ?>
                        <?php $status = $declaration['payment_status'] ?? 'pending'; ?>
                        <tr>
                            <td><strong class="company-name"><?= e($declaration['reference']) ?></strong></td>
                            <td><?= e($declaration['company_name'] ?? '-') ?></td>
                            <td><?= e(sprintf('%02d/%04d', $declaration['period_month'], $declaration['period_year'])) ?></td>
                            <td><?= e(date('d/m/Y', strtotime($declaration['due_date']))) ?></td>
                            <td class="text-end"><?= e($money($declaration['salary_withheld_total'] ?? 0, $declaration['currency'] ?? 'USD')) ?></td>
                            <td class="text-end"><?= e($money($declaration['employer_charges_total'] ?? 0, $declaration['currency'] ?? 'USD')) ?></td>
                            <td class="text-end"><strong><?= e($money($declaration['total_due'] ?? 0, $declaration['currency'] ?? 'USD')) ?></strong></td>
                            <td><span class="badge bg-<?= e($statusTones[$status] ?? 'blue') ?>-lt"><?= e($statusLabels[$status] ?? $status) ?></span></td>
                            <td><a class="btn btn-sm btn-outline" href="<?= e(url('/declarations/show?id=' . $declaration['id'])) ?>">Voir</a></td>
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
<script src="<?= e(asset('js/declarations.js')) ?>"></script>
