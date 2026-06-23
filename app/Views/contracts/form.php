<?php
$contract = $contract ?? [];
$options = $options ?? [];
$isSuperAdmin = $isSuperAdmin ?? false;
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
?>

<div class="company-form-layout employee-form-layout">
    <aside class="company-form-aside">
        <div class="company-form-step is-active"><strong>1</strong><span>Employe</span></div>
        <div class="company-form-step"><strong>2</strong><span>Contrat</span></div>
        <div class="company-form-step"><strong>3</strong><span>Remuneration</span></div>
    </aside>

    <div class="company-form-main">
        <div class="alert alert-danger d-none" data-form-error></div>

        <section class="card company-form-card">
            <div class="card-header"><div><span class="dashboard-section-kicker">Rattachement</span><h2 class="card-title">Employe concerne</h2></div></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Entreprise</label>
                        <select class="form-select" name="company_id" data-company-select <?= !$isSuperAdmin ? 'required' : '' ?>>
                            <?php if ($isSuperAdmin): ?>
                                <option value="">Toutes les entreprises</option>
                            <?php endif; ?>
                            <?php foreach ($options['companies'] ?? [] as $company): ?>
                                <option value="<?= e((string) $company['id']) ?>" <?= (int) ($contract['company_id'] ?? 0) === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Employe</label>
                        <select class="form-select" name="employee_id" data-filtered-options data-auto-company-from-employee required>
                            <option value="">Selectionner</option>
                            <?php foreach ($options['employees'] ?? [] as $employee): ?>
                                <?php $employeeName = trim(($employee['last_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['first_name'] ?? '')); ?>
                                <option value="<?= e((string) $employee['id']) ?>" data-company-id="<?= e((string) $employee['company_id']) ?>" <?= (int) ($contract['employee_id'] ?? 0) === (int) $employee['id'] ? 'selected' : '' ?>>
                                    <?= e($employeeName) ?> · <?= e($employee['employee_number'] ?? '-') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="card company-form-card">
            <div class="card-header"><div><span class="dashboard-section-kicker">Contrat</span><h2 class="card-title">Nature et calendrier</h2></div></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Numero</label>
                        <input class="form-control" name="contract_number" value="<?= e($contract['contract_number'] ?? '') ?>" placeholder="Genere automatiquement si vide">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="contract_type" data-contract-type>
                            <?php foreach ($typeLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($contract['contract_type'] ?? 'cdi') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Statut</label>
                        <select class="form-select" name="status">
                            <?php foreach ($statusLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($contract['status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Periode d'essai jusqu'au</label>
                        <input class="form-control" type="date" name="probation_ends_at" value="<?= e($contract['probation_ends_at'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date debut</label>
                        <input class="form-control" type="date" name="start_date" value="<?= e($contract['start_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date fin</label>
                        <input class="form-control" type="date" name="end_date" value="<?= e($contract['end_date'] ?? '') ?>" data-contract-end-date>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Devise</label>
                        <input class="form-control" name="currency" value="<?= e($contract['currency'] ?? 'USD') ?>" maxlength="10">
                    </div>
                </div>
            </div>
        </section>

        <section class="card company-form-card">
            <div class="card-header"><div><span class="dashboard-section-kicker">Remuneration</span><h2 class="card-title">Salaire contractuel</h2></div></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Salaire contractuel</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="base_salary" value="<?= e((string) ($contract['base_salary'] ?? 0)) ?>">
                    </div>
                </div>
            </div>
        </section>

        <div class="company-sticky-actions">
            <a class="btn btn-outline" href="<?= e(url('/contracts')) ?>">Annuler</a>
            <button class="btn btn-primary" type="submit" data-submit-label>Enregistrer</button>
        </div>
    </div>
</div>
