<?php
$declaration = $declaration ?? [];
$details = $declaration['details'] ?? [];
$currency = $declaration['currency'] ?? 'USD';
$money = static fn($value): string => number_format((float) $value, 2, ',', ' ') . ' ' . $currency;
$statusLabels = ['pending' => 'En attente', 'paid' => 'Payee', 'late' => 'En retard'];
$statusTones = ['pending' => 'orange', 'paid' => 'green', 'late' => 'red'];
$status = $declaration['payment_status'] ?? 'pending';
$proofUrl = !empty($declaration['proof_path']) ? asset($declaration['proof_path']) : null;
?>

<div class="payroll-workspace declarations-workspace">
    <div class="payroll-command">
        <div>
            <span class="dashboard-section-kicker">Declaration RDC</span>
            <h1 class="page-title"><?= e($declaration['reference'] ?? '-') ?></h1>
        </div>
        <div class="payroll-command-actions">
            <a class="btn btn-outline" href="<?= e(url('/declarations')) ?>">Historique</a>
            <a class="btn btn-outline" href="<?= e(url('/declarations/export?id=' . ($declaration['id'] ?? 0))) ?>"><?= icon('file') ?><span>Excel</span></a>
            <a class="btn btn-primary" target="_blank" href="<?= e(url('/declarations/pdf?id=' . ($declaration['id'] ?? 0))) ?>"><?= icon('file') ?><span>PDF</span></a>
        </div>
    </div>

    <div class="payroll-kpi-grid">
        <article class="payroll-kpi payroll-kpi-net"><span>Total a payer</span><strong><?= e($money($declaration['total_due'] ?? 0)) ?></strong><small><?= e($statusLabels[$status] ?? $status) ?></small></article>
        <article class="payroll-kpi"><span>Retenues salariales</span><strong><?= e($money($declaration['salary_withheld_total'] ?? 0)) ?></strong><small>IPR et cotisations</small></article>
        <article class="payroll-kpi"><span>Charges patronales</span><strong><?= e($money($declaration['employer_charges_total'] ?? 0)) ?></strong><small>CNSS, INPP, ONEM</small></article>
        <article class="payroll-kpi"><span>Echeance</span><strong><?= e(date('d/m/Y', strtotime((string) ($declaration['due_date'] ?? 'now')))) ?></strong><small><?= e(sprintf('%02d/%04d', $declaration['period_month'] ?? 0, $declaration['period_year'] ?? 0)) ?></small></article>
    </div>

    <div class="alert alert-danger d-none" data-declaration-error></div>

    <div class="payroll-main-grid">
        <section class="card company-table-card payroll-ledger-card">
            <div class="payroll-section-header">
                <div>
                    <span class="dashboard-section-kicker">Etats mensuels</span>
                    <h2 class="card-title">Synthese fiscale et sociale</h2>
                </div>
                <span class="badge bg-<?= e($statusTones[$status] ?? 'blue') ?>-lt"><?= e($statusLabels[$status] ?? $status) ?></span>
            </div>
            <div class="declaration-statement-grid">
                <article><span>IPR mensuel</span><strong><?= e($money($declaration['ipr_total'] ?? 0)) ?></strong><small>Retenue salariale</small></article>
                <article><span>CNSS</span><strong><?= e($money(((float) ($declaration['cnss_employee_total'] ?? 0)) + ((float) ($declaration['cnss_employer_total'] ?? 0)))) ?></strong><small>Sal. <?= e($money($declaration['cnss_employee_total'] ?? 0)) ?> / Pat. <?= e($money($declaration['cnss_employer_total'] ?? 0)) ?></small></article>
                <article><span>INPP</span><strong><?= e($money(((float) ($declaration['inpp_employee_total'] ?? 0)) + ((float) ($declaration['inpp_employer_total'] ?? 0)))) ?></strong><small>Sal. <?= e($money($declaration['inpp_employee_total'] ?? 0)) ?> / Pat. <?= e($money($declaration['inpp_employer_total'] ?? 0)) ?></small></article>
                <article><span>ONEM</span><strong><?= e($money(((float) ($declaration['onem_employee_total'] ?? 0)) + ((float) ($declaration['onem_employer_total'] ?? 0)))) ?></strong><small>Sal. <?= e($money($declaration['onem_employee_total'] ?? 0)) ?> / Pat. <?= e($money($declaration['onem_employer_total'] ?? 0)) ?></small></article>
            </div>
        </section>

        <aside class="payroll-control-panel">
            <div class="payroll-control-header">
                <span class="dashboard-section-kicker">Paiement</span>
                <strong>Suivi</strong>
            </div>
            <form method="post" action="<?= e(url('/declarations/payment')) ?>" data-declaration-form>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) ($declaration['id'] ?? 0)) ?>">
                <label class="form-label">Statut paiement</label>
                <select class="form-select" name="payment_status">
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>En attente</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Payee</option>
                    <option value="late" <?= $status === 'late' ? 'selected' : '' ?>>En retard</option>
                </select>
                <button class="btn btn-primary w-100 mt-3" type="submit" data-submit-label>Mettre a jour</button>
            </form>

            <form class="mt-4" method="post" action="<?= e(url('/declarations/proof')) ?>" enctype="multipart/form-data" data-declaration-form>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) ($declaration['id'] ?? 0)) ?>">
                <label class="form-label">Preuve de paiement</label>
                <input class="form-control" type="file" name="proof" accept="application/pdf,image/png,image/jpeg" required>
                <button class="btn btn-outline w-100 mt-3" type="submit" data-submit-label>Uploader</button>
            </form>

            <?php if ($proofUrl): ?>
                <a class="declaration-proof-link" target="_blank" href="<?= e($proofUrl) ?>">
                    <?= icon('file') ?>
                    <span><?= e($declaration['proof_name'] ?? 'Preuve de paiement') ?></span>
                </a>
            <?php endif; ?>
        </aside>
    </div>

    <section class="card company-table-card payroll-ledger-card">
        <div class="payroll-section-header">
            <div>
                <span class="dashboard-section-kicker">Detail employes</span>
                <h2 class="card-title">Base des declarations</h2>
            </div>
            <div class="topbar-search payroll-table-search">
                <?= icon('search') ?>
                <input type="search" data-declaration-search placeholder="Rechercher employe, departement">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table payroll-table" id="declaration-details-table">
                <thead>
                    <tr>
                        <th>Employe</th>
                        <th>Departement</th>
                        <th class="text-end">Brut</th>
                        <th class="text-end">IPR</th>
                        <th class="text-end">CNSS sal.</th>
                        <th class="text-end">CNSS pat.</th>
                        <th class="text-end">INPP sal.</th>
                        <th class="text-end">INPP pat.</th>
                        <th class="text-end">ONEM sal.</th>
                        <th class="text-end">ONEM pat.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details as $row): ?>
                        <?php $employee = trim(($row['last_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['first_name'] ?? '')); ?>
                        <tr>
                            <td><strong class="company-name"><?= e($employee) ?></strong><span class="d-block text-secondary"><?= e($row['employee_number'] ?? '-') ?></span></td>
                            <td><?= e($row['department_name'] ?? '-') ?></td>
                            <td class="text-end"><?= e($money($row['gross_salary'] ?? 0)) ?></td>
                            <td class="text-end"><?= e($money($row['ipr'] ?? 0)) ?></td>
                            <td class="text-end"><?= e($money($row['cnss_employee'] ?? 0)) ?></td>
                            <td class="text-end"><?= e($money($row['cnss_employer'] ?? 0)) ?></td>
                            <td class="text-end"><?= e($money($row['inpp_employee'] ?? 0)) ?></td>
                            <td class="text-end"><?= e($money($row['inpp_employer'] ?? 0)) ?></td>
                            <td class="text-end"><?= e($money($row['onem_employee'] ?? 0)) ?></td>
                            <td class="text-end"><?= e($money($row['onem_employer'] ?? 0)) ?></td>
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
