<?php
$companies = $companies ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$simulationResult = $simulationResult ?? null;
$simulationInput = $simulationInput ?? [];
$money = static fn($value): string => number_format((float) $value, 2, ',', ' ');
$rate = static fn($value): string => (float) $value > 0 ? number_format((float) $value, 4, ',', ' ') . ' %' : '-';
$typeLabels = [
    'earning' => 'Gain',
    'deduction' => 'Retenue',
    'contribution' => 'Cotisation',
    'employer_contribution' => 'Charge patronale',
    'tax' => 'Taxe',
];
$typeTones = [
    'earning' => 'green',
    'deduction' => 'orange',
    'contribution' => 'purple',
    'employer_contribution' => 'blue',
    'tax' => 'blue',
];
?>

<div class="payroll-simulation-workspace" data-payroll-simulation>
    <header class="payroll-simulation-hero">
        <div>
            <span class="dashboard-section-kicker">Simulation inverse</span>
            <h1 class="page-title">Retrouver le salaire de base depuis un net</h1>
            <p>Calculez a blanc le brut, les retenues, l’IPR, les cotisations et le salaire de base necessaire pour atteindre un net cible.</p>
        </div>
        <div class="payroll-command-actions">
            <a class="btn btn-outline" href="<?= e(url('/payroll')) ?>"><?= icon('arrow-right') ?><span>Centre de paie</span></a>
            <a class="btn btn-outline" href="<?= e(url('/payroll/settings')) ?>"><?= icon('settings') ?><span>Configuration</span></a>
        </div>
    </header>

    <div class="payroll-simulation-grid">
        <form class="payroll-simulation-form" method="post" action="<?= e(url('/payroll/simulation/calculate')) ?>" data-payroll-simulation-form>
            <?= csrf_field() ?>
            <div class="payroll-settings-form-head">
                <span class="payroll-settings-form-icon"><?= icon('wallet') ?></span>
                <div>
                    <span class="dashboard-section-kicker">Parametres de calcul</span>
                    <h2>Net souhaite</h2>
                    <p>La simulation utilise les rubriques, cotisations et tranches IPR actives de l’entreprise.</p>
                </div>
            </div>
            <div class="alert alert-danger d-none" data-form-error></div>
            <div class="payroll-settings-fields">
                <?php if ($isSuperAdmin): ?>
                    <div class="payroll-settings-field is-wide">
                        <label class="form-label" for="simulation_company_id">Entreprise <em>Obligatoire</em></label>
                        <select id="simulation_company_id" class="form-select" name="company_id" required>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= e((string) $company['id']) ?>" <?= $defaultCompanyId === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="company_id" value="<?= e((string) $defaultCompanyId) ?>">
                <?php endif; ?>

                <div class="payroll-settings-field is-wide">
                    <label class="form-label" for="target_net">Net a payer cible <em>Obligatoire</em></label>
                    <div class="payroll-input-affix">
                        <input id="target_net" class="form-control" type="number" min="0.01" step="0.01" name="target_net" placeholder="Ex. 1000.00" value="<?= e((string) ($simulationInput['target_net'] ?? '')) ?>" required autofocus>
                        <span>USD</span>
                    </div>
                </div>
                <div class="payroll-settings-field">
                    <label class="form-label" for="taxable_earnings">Avantages imposables</label>
                    <div class="payroll-input-affix"><input id="taxable_earnings" class="form-control" type="number" min="0" step="0.01" name="taxable_earnings" value="<?= e((string) ($simulationInput['taxable_earnings'] ?? 0)) ?>"><span>USD</span></div>
                </div>
                <div class="payroll-settings-field">
                    <label class="form-label" for="non_taxable_earnings">Avantages non imposables</label>
                    <div class="payroll-input-affix"><input id="non_taxable_earnings" class="form-control" type="number" min="0" step="0.01" name="non_taxable_earnings" value="<?= e((string) ($simulationInput['non_taxable_earnings'] ?? 0)) ?>"><span>USD</span></div>
                </div>
                <div class="payroll-settings-field">
                    <label class="form-label" for="deductions">Retenues fixes</label>
                    <div class="payroll-input-affix"><input id="deductions" class="form-control" type="number" min="0" step="0.01" name="deductions" value="<?= e((string) ($simulationInput['deductions'] ?? 0)) ?>"><span>USD</span></div>
                </div>
            </div>
            <div class="payroll-settings-form-footer">
                <span><?= icon('shield') ?> Aucun bulletin n’est cree, la paie historique reste intacte</span>
                <button class="btn btn-primary" type="submit" data-submit-label><?= icon('chart') ?><span>Calculer</span></button>
            </div>
        </form>

        <section class="payroll-simulation-result" data-payroll-simulation-result <?= $simulationResult ? '' : 'hidden' ?>>
            <div class="payroll-section-header">
                <div>
                    <span class="dashboard-section-kicker">Resultat</span>
                    <h2 class="card-title">Structure estimee du salaire</h2>
                </div>
                <div class="payroll-simulation-actions">
                    <span class="payroll-config-status <?= $simulationResult && abs((float) ($simulationResult['difference'] ?? 0)) > 0.02 ? 'is-warning' : '' ?>" data-simulation-precision><i></i><span><strong><?= e($simulationResult ? (abs((float) ($simulationResult['difference'] ?? 0)) <= 0.02 ? 'Net atteint' : 'Approximation') : 'Simulation') ?></strong><small><?= e($simulationResult ? 'Ecart: ' . $money($simulationResult['difference'] ?? 0) . ' USD' : 'Precision au centime') ?></small></span></span>
                    <form method="post" action="<?= e(url('/payroll/simulation/pdf')) ?>" target="_blank" data-simulation-pdf-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="company_id" value="<?= e((string) ($simulationInput['company_id'] ?? $defaultCompanyId)) ?>">
                        <input type="hidden" name="target_net" value="<?= e((string) ($simulationInput['target_net'] ?? '')) ?>">
                        <input type="hidden" name="taxable_earnings" value="<?= e((string) ($simulationInput['taxable_earnings'] ?? 0)) ?>">
                        <input type="hidden" name="non_taxable_earnings" value="<?= e((string) ($simulationInput['non_taxable_earnings'] ?? 0)) ?>">
                        <input type="hidden" name="deductions" value="<?= e((string) ($simulationInput['deductions'] ?? 0)) ?>">
                        <button class="btn btn-outline" type="submit"><?= icon('download') ?><span>PDF</span></button>
                    </form>
                </div>
            </div>
            <div class="payroll-simulation-kpis" data-simulation-kpis>
                <?php if ($simulationResult): ?>
                    <article class="is-primary"><span>Salaire de base</span><strong><?= e($money($simulationResult['base_salary'] ?? 0)) ?></strong><small>Montant a encoder</small></article>
                    <article><span>Salaire brut</span><strong><?= e($money($simulationResult['gross_salary'] ?? 0)) ?></strong><small>Avant retenues</small></article>
                    <article><span>Base imposable</span><strong><?= e($money($simulationResult['taxable_salary'] ?? 0)) ?></strong><small>Soumise a l’IPR</small></article>
                    <article><span>Retenues salariales</span><strong><?= e($money($simulationResult['total_deductions'] ?? 0)) ?></strong><small>IPR et cotisations</small></article>
                    <article class="is-primary"><span>Net calcule</span><strong><?= e($money($simulationResult['net_salary'] ?? 0)) ?></strong><small>Cible: <?= e($money($simulationResult['target_net'] ?? 0)) ?></small></article>
                    <article><span>Cout employeur</span><strong><?= e($money($simulationResult['total_employer_cost'] ?? 0)) ?></strong><small>Brut + charges patronales</small></article>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table payroll-table payroll-simulation-table">
                    <thead>
                        <tr>
                            <th>Rubrique</th>
                            <th>Type</th>
                            <th class="text-end">Base</th>
                            <th class="text-end">Taux</th>
                            <th class="text-end">Montant</th>
                        </tr>
                    </thead>
                    <tbody data-simulation-lines>
                        <?php foreach (($simulationResult['lines'] ?? []) as $line): ?>
                            <?php $tone = $typeTones[$line['type'] ?? ''] ?? 'blue'; ?>
                            <tr>
                                <td><strong><?= e($line['name'] ?? '-') ?></strong><small class="d-block text-secondary"><?= e($line['code'] ?? '-') ?><?= !empty($line['taxable']) ? ' · taxable' : '' ?></small></td>
                                <td><span class="payroll-type-badge is-<?= e($tone) ?>"><?= e($typeLabels[$line['type'] ?? ''] ?? ($line['type'] ?? '-')) ?></span></td>
                                <td class="text-end"><?= e($money($line['base_amount'] ?? 0)) ?></td>
                                <td class="text-end"><?= e($rate($line['rate'] ?? 0)) ?></td>
                                <td class="text-end"><strong class="payroll-value"><?= e($money($line['amount'] ?? 0)) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="payroll-simulation-empty" data-payroll-simulation-empty <?= $simulationResult ? 'hidden' : '' ?>>
            <?= icon('chart') ?>
            <strong>Renseignez un net cible</strong>
            <span>Le resultat affichera le salaire de base estime, le brut, la base imposable, les retenues salariales et le cout employeur.</span>
        </section>
    </div>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/payroll.js') . '?v=' . (string) filemtime(BASE_PATH . '/public/js/payroll.js')) ?>"></script>
