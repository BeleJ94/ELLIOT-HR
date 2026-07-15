<?php
$request = $request ?? [];
$claims = $claims ?? [];
$careTypes = $careTypes ?? [];
$relationships = $relationships ?? [];
$canManageMedical = !empty($canManageMedical);
$statusLabels = [
    'submitted' => 'A valider',
    'approved' => 'Approuvee',
    'voucher_issued' => 'Bon emis',
    'invoiced' => 'Facturee',
    'validated' => 'Liquidee',
    'paid' => 'Payee',
    'rejected' => 'Refusee',
    'cancelled' => 'Annulee',
    'expired' => 'Expiree',
];
$statusTones = [
    'submitted' => 'orange',
    'approved' => 'blue',
    'voucher_issued' => 'cyan',
    'invoiced' => 'blue',
    'validated' => 'green',
    'paid' => 'green',
    'rejected' => 'red',
    'cancelled' => 'red',
    'expired' => 'orange',
];
$claimLabels = ['submitted' => 'Soumise', 'validated' => 'Liquidee', 'paid' => 'Payee'];
$employeeName = trim(($request['last_name'] ?? '') . ' ' . ($request['middle_name'] ?? '') . ' ' . ($request['first_name'] ?? ''));
$beneficiary = $employeeName;
if (!empty($request['dependent_id'])) {
    $beneficiary = trim(($request['dependent_last_name'] ?? '') . ' ' . ($request['dependent_first_name'] ?? '')) . ' · ' . ($relationships[$request['relationship'] ?? 'other'] ?? 'Ayant droit');
}
$money = static fn($amount, $currency = 'USD'): string => number_format((float) $amount, 2, ',', ' ') . ' ' . e((string) $currency);
$dateValue = static fn($value): string => !empty($value) ? date('d/m/Y', strtotime((string) $value)) : '-';
$status = (string) ($request['status'] ?? 'submitted');
$tone = $statusTones[$status] ?? 'blue';
$workflow = [
    'submitted' => 'Demande',
    'approved' => 'Validation',
    'voucher_issued' => 'Bon',
    'validated' => 'Liquidation',
    'paid' => 'Paiement',
];
$workflowOrder = array_keys($workflow);
$currentStep = array_search($status, $workflowOrder, true);
if ($status === 'invoiced') {
    $currentStep = array_search('voucher_issued', $workflowOrder, true);
}
if ($currentStep === false) {
    $currentStep = in_array($status, ['rejected', 'cancelled', 'expired'], true) ? 0 : 0;
}
$canPrintVoucher = in_array($status, ['voucher_issued', 'invoiced', 'validated', 'paid'], true);
$canLiquidate = $canManageMedical && in_array($status, ['voucher_issued', 'invoiced', 'validated'], true);
?>

<div class="medical-case-shell">
    <header class="medical-case-header">
        <div class="medical-case-title">
            <span class="dashboard-section-kicker">Dossier de prise en charge</span>
            <h1 class="page-title"><?= e($request['request_number'] ?? '-') ?></h1>
            <div class="medical-case-meta">
                <span><?= e($beneficiary) ?></span>
                <span><?= e($careTypes[$request['care_type'] ?? 'other'] ?? 'Soin medical') ?></span>
                <span><?= e($request['provider_name'] ?: 'Prestataire a confirmer') ?></span>
            </div>
        </div>
        <div class="medical-case-actions">
            <span class="badge bg-<?= e($tone) ?>-lt"><?= e($statusLabels[$status] ?? $status) ?></span>
            <a class="btn btn-outline" href="<?= e(url('/medical')) ?>"><?= icon('arrow-right') ?><span>Registre</span></a>
            <?php if ($canPrintVoucher): ?>
                <a class="btn btn-primary" target="_blank" href="<?= e(url('/medical/voucher/pdf?id=' . ($request['id'] ?? 0))) ?>"><?= icon('download') ?><span>Bon PDF</span></a>
            <?php endif; ?>
        </div>
    </header>

    <section class="medical-progress-card" aria-label="Progression du dossier">
        <?php foreach ($workflow as $stepKey => $stepLabel): ?>
            <?php
            $stepIndex = array_search($stepKey, $workflowOrder, true);
            $stepClass = $stepIndex < $currentStep ? 'is-done' : ($stepIndex === $currentStep ? 'is-current' : '');
            ?>
            <article class="medical-progress-step <?= e($stepClass) ?>">
                <span><?= e((string) ($stepIndex + 1)) ?></span>
                <strong><?= e($stepLabel) ?></strong>
            </article>
        <?php endforeach; ?>
    </section>

    <?php if ($canManageMedical && $status === 'submitted'): ?>
    <section class="medical-decision-panel">
        <div>
            <span class="dashboard-section-kicker">Validation RH</span>
            <h2>Decision du dossier</h2>
        </div>
        <div class="medical-decision-actions">
            <form method="post" action="<?= e(url('/medical/requests/approve')) ?>" data-medical-form>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) ($request['id'] ?? 0)) ?>">
                <label class="form-label">Montant autorise</label>
                <div class="input-group">
                    <input class="form-control" type="number" step="0.01" min="0" name="approved_amount" value="<?= e((string) ($request['requested_amount'] ?? 0)) ?>">
                    <button class="btn btn-primary" type="submit" data-submit-label>Emettre le bon</button>
                </div>
                <div class="alert alert-danger d-none mt-2" data-form-error></div>
            </form>
            <form method="post" action="<?= e(url('/medical/requests/reject')) ?>" data-medical-form>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) ($request['id'] ?? 0)) ?>">
                <label class="form-label">Motif de refus</label>
                <div class="input-group">
                    <input class="form-control" name="rejection_reason" placeholder="Raison documentee" required>
                    <button class="btn btn-danger" type="submit" data-submit-label>Refuser</button>
                </div>
                <div class="alert alert-danger d-none mt-2" data-form-error></div>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <div class="medical-case-grid">
        <section class="medical-case-card">
            <div class="medical-case-card-header">
                <div>
                    <span class="dashboard-section-kicker">Synthese</span>
                    <h2>Dossier patient</h2>
                </div>
            </div>
            <dl class="medical-case-list">
                <div><dt>Entreprise</dt><dd><?= e(($request['company_legal_name'] ?? '') ?: ($request['company_name'] ?? '-')) ?></dd></div>
                <div><dt>Titulaire</dt><dd><?= e($employeeName ?: '-') ?> · <?= e($request['employee_number'] ?? '-') ?></dd></div>
                <div><dt>Beneficiaire</dt><dd><?= e($beneficiary ?: '-') ?></dd></div>
                <div><dt>Lien</dt><dd><?= !empty($request['dependent_id']) ? e($relationships[$request['relationship'] ?? 'other'] ?? 'Ayant droit') : 'Employe' ?></dd></div>
                <div><dt>Prestataire</dt><dd><?= e($request['provider_name'] ?: 'A confirmer') ?></dd></div>
                <div><dt>Validite du bon</dt><dd><?= e($dateValue($request['voucher_expires_at'] ?? null)) ?></dd></div>
                <div class="is-wide"><dt>Motif medical</dt><dd><?= e($request['medical_reason'] ?: '-') ?></dd></div>
            </dl>
        </section>

        <aside class="medical-case-card medical-finance-card">
            <div class="medical-case-card-header">
                <div>
                    <span class="dashboard-section-kicker">Decision financiere</span>
                    <h2>Quote-part</h2>
                </div>
                <strong><?= e(number_format((float) ($request['coverage_rate'] ?? 0), 2, ',', ' ')) ?>%</strong>
            </div>
            <div class="medical-case-metrics">
                <article><span>Demande</span><strong><?= $money($request['requested_amount'] ?? 0, $request['currency'] ?? 'USD') ?></strong></article>
                <article><span>Autorise</span><strong><?= $money($request['approved_amount'] ?? 0, $request['currency'] ?? 'USD') ?></strong></article>
                <article><span>Entreprise</span><strong><?= $money($request['covered_amount'] ?? 0, $request['currency'] ?? 'USD') ?></strong></article>
                <article><span>Employe</span><strong><?= $money($request['employee_share'] ?? 0, $request['currency'] ?? 'USD') ?></strong></article>
            </div>
        </aside>
    </div>

    <div class="medical-liquidation-grid">
        <section class="medical-case-card medical-table-card">
            <div class="medical-case-card-header">
                <div>
                    <span class="dashboard-section-kicker">Liquidation</span>
                    <h2>Factures medicales</h2>
                </div>
                <span class="badge bg-blue-lt"><?= e((string) count($claims)) ?> facture(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table medical-case-table">
                    <thead>
                        <tr>
                            <th>Facture</th>
                            <th>Date</th>
                            <th class="text-end">Montant facture</th>
                            <th class="text-end">Accepte</th>
                            <th class="text-end">Entreprise</th>
                            <th>Statut</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($claims === []): ?>
                            <tr><td colspan="7"><div class="medical-empty-line">Aucune facture liquidee pour ce dossier.</div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($claims as $claim): ?>
                            <tr>
                                <td><strong><?= e($claim['invoice_number'] ?: '-') ?></strong></td>
                                <td><?= e($dateValue($claim['invoice_date'] ?? null)) ?></td>
                                <td class="text-end"><?= $money($claim['billed_amount'] ?? 0, $request['currency'] ?? 'USD') ?></td>
                                <td class="text-end"><?= $money($claim['accepted_amount'] ?? 0, $request['currency'] ?? 'USD') ?></td>
                                <td class="text-end"><strong><?= $money($claim['covered_amount'] ?? 0, $request['currency'] ?? 'USD') ?></strong></td>
                                <td><span class="badge bg-<?= ($claim['status'] ?? '') === 'paid' ? 'green' : 'blue' ?>-lt"><?= e($claimLabels[$claim['status'] ?? 'submitted'] ?? ($claim['status'] ?? '-')) ?></span></td>
                                <td class="text-end">
                                    <?php if ($canManageMedical && ($claim['status'] ?? '') !== 'paid'): ?>
                                        <form method="post" action="<?= e(url('/medical/claims/pay')) ?>" data-medical-form>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) ($claim['id'] ?? 0)) ?>">
                                            <button class="btn btn-sm btn-outline" type="submit" data-submit-label>Payer</button>
                                            <div class="alert alert-danger d-none mt-2" data-form-error></div>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($canLiquidate): ?>
        <aside class="medical-case-card medical-claim-form">
            <div class="medical-case-card-header">
                <div>
                    <span class="dashboard-section-kicker">Facture</span>
                    <h2>Liquider</h2>
                </div>
            </div>
            <form method="post" action="<?= e(url('/medical/claims/store')) ?>" data-medical-form>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) ($request['id'] ?? 0)) ?>">
                <div class="alert alert-danger d-none" data-form-error></div>
                <div class="medical-claim-fields">
                    <div><label class="form-label">Numero facture</label><input class="form-control" name="invoice_number"></div>
                    <div><label class="form-label">Date</label><input class="form-control" type="date" name="invoice_date" value="<?= e(date('Y-m-d')) ?>"></div>
                    <div><label class="form-label">Montant facture</label><input class="form-control" type="number" step="0.01" min="0" name="billed_amount" required></div>
                    <div><label class="form-label">Montant accepte</label><input class="form-control" type="number" step="0.01" min="0" name="accepted_amount"></div>
                    <div class="is-wide"><label class="form-label">Notes</label><textarea class="form-control" rows="3" name="notes"></textarea></div>
                </div>
                <div class="user-modal-footer-actions"><button class="btn btn-primary" type="submit" data-submit-label>Liquider</button></div>
            </form>
        </aside>
        <?php endif; ?>
    </div>
</div>

<script src="<?= e(asset('js/medical.js') . '?v=' . (string) filemtime(BASE_PATH . '/public/js/medical.js')) ?>"></script>
