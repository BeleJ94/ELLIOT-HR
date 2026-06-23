<?php
$contract = $contract ?? [];
$alerts = $alerts ?? [];
$employeeName = trim(($contract['last_name'] ?? '') . ' ' . ($contract['middle_name'] ?? '') . ' ' . ($contract['first_name'] ?? ''));
$typeLabels = [
    'cdi' => 'CDI',
    'cdd' => 'CDD',
    'internship' => 'Stage',
    'consultant' => 'Consultant',
    'temporary' => 'Temporaire',
];
$statusLabels = [
    'draft' => 'Brouillon',
    'active' => 'Actif',
    'expired' => 'Expire',
    'terminated' => 'Resilie',
];
$statusTones = [
    'draft' => 'gray',
    'active' => 'green',
    'expired' => 'orange',
    'terminated' => 'red',
];
$status = $contract['status'] ?? 'draft';
$endDate = $contract['end_date'] ?? null;
$expiresSoon = $status === 'active' && $endDate && strtotime($endDate) >= strtotime(date('Y-m-d')) && strtotime($endDate) <= strtotime('+30 days');
?>

<div class="employee-show-hero">
    <div class="employee-show-profile">
        <span class="employee-photo-large employee-avatar-fallback"><?= e(strtoupper(substr($employeeName ?: 'C', 0, 2))) ?></span>
        <div>
            <span class="dashboard-section-kicker">Contrat RH</span>
            <h1 class="page-title"><?= e($contract['contract_number'] ?? '-') ?></h1>
            <p><?= e($employeeName ?: '-') ?> · <?= e($contract['company_name'] ?? '-') ?> · <?= e($typeLabels[$contract['contract_type'] ?? ''] ?? '-') ?></p>
        </div>
    </div>
    <div class="page-actions">
        <span class="badge bg-<?= e($statusTones[$status] ?? 'blue') ?>-lt"><?= e($statusLabels[$status] ?? $status) ?></span>
        <a class="btn btn-outline" href="<?= e(url('/contracts')) ?>"><?= icon('arrow-right') ?><span>Liste</span></a>
        <a class="btn btn-primary" href="<?= e(url('/contracts/edit?id=' . ($contract['id'] ?? 0))) ?>"><?= icon('settings') ?><span>Modifier</span></a>
    </div>
</div>

<?php if ($expiresSoon): ?>
    <div class="alert alert-warning">
        <strong>Contrat expirant bientot.</strong>
        <span>La date de fin est le <?= e($endDate) ?>. Preparez un renouvellement si necessaire.</span>
    </div>
<?php endif; ?>

<div class="company-kpi-grid">
    <div class="company-kpi-card"><span>Type</span><strong><?= e($typeLabels[$contract['contract_type'] ?? ''] ?? '-') ?></strong></div>
    <div class="company-kpi-card"><span>Debut</span><strong><?= e($contract['start_date'] ?: '-') ?></strong></div>
    <div class="company-kpi-card"><span>Fin</span><strong><?= e($contract['end_date'] ?: 'Indeterminee') ?></strong></div>
    <div class="company-kpi-card"><span>Salaire</span><strong><?= e(number_format((float) ($contract['base_salary'] ?? 0), 2, ',', ' ')) ?> <?= e($contract['currency'] ?? 'USD') ?></strong></div>
</div>

<div class="company-detail-grid">
    <div class="card company-profile-card">
        <div class="card-header"><h2 class="card-title">Informations contrat</h2></div>
        <div class="card-body">
            <dl class="company-definition-list">
                <div><dt>Employe</dt><dd><?= e($employeeName ?: '-') ?></dd></div>
                <div><dt>Matricule</dt><dd><?= e($contract['employee_number'] ?? '-') ?></dd></div>
                <div><dt>Poste</dt><dd><?= e($contract['position_title'] ?? '-') ?></dd></div>
                <div><dt>Departement</dt><dd><?= e($contract['department_name'] ?? '-') ?></dd></div>
                <div><dt>Periode d'essai</dt><dd><?= e($contract['probation_ends_at'] ?: '-') ?></dd></div>
                <div><dt>Renouvelle depuis</dt><dd><?= e($contract['renewed_from_number'] ?: '-') ?></dd></div>
            </dl>
        </div>
    </div>

    <div class="card company-profile-card">
        <div class="card-header"><h2 class="card-title">Documents</h2></div>
        <div class="card-body">
            <dl class="company-definition-list">
                <div>
                    <dt>PDF genere</dt>
                    <dd>
                        <?php if (!empty($contract['pdf_path'])): ?>
                            <a href="<?= e(url($contract['pdf_path'])) ?>" target="_blank">Ouvrir le PDF</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt>Contrat signe</dt>
                    <dd>
                        <?php if (!empty($contract['signed_contract_path'])): ?>
                            <a href="<?= e(url($contract['signed_contract_path'])) ?>" target="_blank"><?= e($contract['signed_contract_name'] ?: 'Ouvrir') ?></a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
            <div class="btn-list mt-3">
                <button class="btn btn-outline" type="button" data-contract-pdf="<?= e((string) ($contract['id'] ?? 0)) ?>"><?= icon('file') ?><span>Generer PDF</span></button>
            </div>
        </div>
    </div>
</div>

<div class="company-detail-grid mt-3">
    <div class="card company-profile-card">
        <div class="card-header"><h2 class="card-title">Renouvellement</h2></div>
        <form method="post" action="<?= e(url('/contracts/renew?id=' . ($contract['id'] ?? 0))) ?>" data-contract-form>
            <?= csrf_field() ?>
            <div class="card-body">
                <div class="alert alert-danger d-none" data-form-error></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nouveau debut</label>
                        <input class="form-control" type="date" name="start_date" value="<?= e(!empty($contract['end_date']) ? date('Y-m-d', strtotime($contract['end_date'] . ' +1 day')) : date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nouvelle fin</label>
                        <input class="form-control" type="date" name="end_date">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Salaire</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="base_salary" value="<?= e((string) ($contract['base_salary'] ?? 0)) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="contract_type">
                            <?php foreach ($typeLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($contract['contract_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="currency" value="<?= e($contract['currency'] ?? 'USD') ?>">
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary" type="submit" data-submit-label>Renouveler</button>
            </div>
        </form>
    </div>

    <div class="card company-profile-card">
        <div class="card-header"><h2 class="card-title">Contrat signe</h2></div>
        <form method="post" action="<?= e(url('/contracts/signed/upload?id=' . ($contract['id'] ?? 0))) ?>" enctype="multipart/form-data" data-contract-form>
            <?= csrf_field() ?>
            <div class="card-body">
                <div class="alert alert-danger d-none" data-form-error></div>
                <label class="form-label">Fichier signe</label>
                <input class="form-control" type="file" name="signed_contract" accept="application/pdf,image/jpeg,image/png" required>
                <p class="text-secondary mt-2 mb-0">Formats acceptes: PDF, JPG, PNG.</p>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary" type="submit" data-submit-label>Uploader</button>
            </div>
        </form>
    </div>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/contracts.js')) ?>"></script>
