<?php
$statusLabels = ['active' => 'Actif', 'on_leave' => 'En conge', 'suspended' => 'Suspendu', 'terminated' => 'Archive'];
$genderLabels = ['male' => 'Masculin', 'female' => 'Feminin', 'other' => 'Autre'];
$maritalLabels = ['single' => 'Celibataire', 'married' => 'Marie(e)', 'divorced' => 'Divorce(e)', 'widowed' => 'Veuf/Veuve'];
$contractLabels = ['cdi' => 'CDI', 'cdd' => 'CDD', 'consultant' => 'Consultant', 'internship' => 'Stage', 'temporary' => 'Temporaire'];
$photo = !empty($employee['photo_path']) ? url($employee['photo_path']) : null;
$fullName = trim(($employee['last_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['first_name'] ?? ''));
?>

<div class="employee-form-intro">
    <div>
        <span class="dashboard-section-kicker">Saisie guidee</span>
        <h2>Informations de l'agent</h2>
        <p>Commencez par les informations indispensables. Les sections complementaires peuvent etre renseignees plus tard.</p>
    </div>
    <span class="employee-required-note"><strong>*</strong> Champs obligatoires</span>
</div>

<div class="company-form-layout employee-form-layout erp-form-layout">
    <aside class="company-form-aside erp-form-rail employee-form-rail" aria-label="Sections du formulaire">
        <div class="employee-profile-preview">
            <label class="employee-photo-box" for="employee-photo">
                <?php if ($photo): ?>
                    <img src="<?= e($photo) ?>" alt="Apercu de la photo" data-photo-preview>
                    <span hidden data-photo-initials></span>
                <?php else: ?>
                    <img hidden alt="Apercu de la photo" data-photo-preview>
                    <span data-photo-initials><?= e(strtoupper(substr($employee['first_name'] ?? 'A', 0, 1) . substr($employee['last_name'] ?? '', 0, 1))) ?></span>
                <?php endif; ?>
                <span class="employee-photo-action"><?= icon('plus') ?><span>Ajouter une photo</span></span>
                <small>JPG, PNG ou WebP · 10 Mo max.</small>
                <input id="employee-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden data-photo-input>
            </label>
            <strong data-summary-name><?= e($fullName ?: 'Nouvel agent') ?></strong>
            <small data-summary-assignment>Completez son affectation</small>
        </div>

        <nav class="employee-section-nav">
            <button class="company-form-step is-active" type="button" data-section-link="employee-essential"><strong>1</strong><span>Essentiel</span></button>
            <button class="company-form-step" type="button" data-section-link="employee-assignment"><strong>2</strong><span>Affectation</span></button>
            <button class="company-form-step" type="button" data-section-link="employee-contract"><strong>3</strong><span>Contrat</span></button>
            <button class="company-form-step" type="button" data-section-link="employee-identity"><strong>4</strong><span>Identite</span></button>
            <button class="company-form-step" type="button" data-section-link="employee-contact"><strong>5</strong><span>Coordonnees</span></button>
        </nav>
    </aside>

    <div class="company-form-main">
        <div class="alert alert-danger d-none" role="alert" data-form-error></div>

        <section class="card company-form-card erp-form-section employee-form-section" id="employee-essential" data-form-section>
            <div class="card-header"><div><span class="dashboard-section-kicker">Etape 1</span><h2 class="card-title">Informations essentielles</h2><p>Les donnees necessaires pour ouvrir le dossier RH.</p></div></div>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="employee-company">Entreprise <span class="required-mark">*</span></label>
                    <select class="form-select" id="employee-company" name="company_id" required data-company-select>
                        <?php foreach ($options['companies'] ?? [] as $company): ?>
                            <option value="<?= e((string) $company['id']) ?>" <?= (int) ($employee['company_id'] ?? 0) === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="employee-number">Matricule</label>
                    <input class="form-control" id="employee-number" name="employee_number" value="<?= e($employee['employee_number'] ?? '') ?>" readonly>
                    <small class="form-hint">Genere automatiquement pour cette entreprise.</small>
                </div>
                <div class="col-md-4"><label class="form-label" for="employee-last-name">Nom <span class="required-mark">*</span></label><input class="form-control" id="employee-last-name" name="last_name" value="<?= e($employee['last_name'] ?? '') ?>" required data-summary-source></div>
                <div class="col-md-4"><label class="form-label" for="employee-middle-name">Postnom</label><input class="form-control" id="employee-middle-name" name="middle_name" value="<?= e($employee['middle_name'] ?? '') ?>" data-summary-source></div>
                <div class="col-md-4"><label class="form-label" for="employee-first-name">Prenom <span class="required-mark">*</span></label><input class="form-control" id="employee-first-name" name="first_name" value="<?= e($employee['first_name'] ?? '') ?>" required data-summary-source></div>
                <div class="col-md-6"><label class="form-label" for="employee-hire-date">Date d'embauche <span class="required-mark">*</span></label><input class="form-control" id="employee-hire-date" type="date" name="hire_date" value="<?= e($employee['hire_date'] ?? date('Y-m-d')) ?>" required data-contract-summary-source></div>
                <div class="col-md-6"><label class="form-label" for="employee-status">Statut <span class="required-mark">*</span></label><select class="form-select" id="employee-status" name="employment_status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($employee['employment_status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            </div></div>
        </section>

        <section class="card company-form-card erp-form-section employee-form-section" id="employee-assignment" data-form-section>
            <div class="card-header"><div><span class="dashboard-section-kicker">Etape 2</span><h2 class="card-title">Affectation professionnelle</h2><p>Les choix sont filtres selon l'entreprise et la structure selectionnees.</p></div></div>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="employee-branch">Site</label><select class="form-select" id="employee-branch" name="branch_id" data-dependent-select="branch"><option value="">Aucun site</option><?php foreach ($options['branches'] ?? [] as $branch): ?><option value="<?= e((string) $branch['id']) ?>" data-company-id="<?= e((string) $branch['company_id']) ?>" <?= (int) ($employee['branch_id'] ?? 0) === (int) $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label" for="employee-department">Departement</label><select class="form-select" id="employee-department" name="department_id" data-dependent-select="department"><option value="">Aucun departement</option><?php foreach ($options['departments'] ?? [] as $department): ?><option value="<?= e((string) $department['id']) ?>" data-company-id="<?= e((string) $department['company_id']) ?>" data-branch-id="<?= e((string) ($department['branch_id'] ?? '')) ?>" <?= (int) ($employee['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label" for="employee-position">Poste</label><select class="form-select" id="employee-position" name="position_id" data-dependent-select="position"><option value="">Aucun poste</option><?php foreach ($options['positions'] ?? [] as $position): ?><option value="<?= e((string) $position['id']) ?>" data-company-id="<?= e((string) $position['company_id']) ?>" data-department-id="<?= e((string) ($position['department_id'] ?? '')) ?>" <?= (int) ($employee['position_id'] ?? 0) === (int) $position['id'] ? 'selected' : '' ?>><?= e($position['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label" for="employee-manager">Manager</label><select class="form-select" id="employee-manager" name="manager_id" data-dependent-select="manager"><option value="">Aucun manager</option><?php foreach ($options['managers'] ?? [] as $manager): ?><?php if ((int) ($employee['id'] ?? 0) === (int) $manager['id']) continue; ?><option value="<?= e((string) $manager['id']) ?>" data-company-id="<?= e((string) $manager['company_id']) ?>" data-department-id="<?= e((string) ($manager['department_id'] ?? '')) ?>" <?= (int) ($employee['manager_id'] ?? 0) === (int) $manager['id'] ? 'selected' : '' ?>><?= e(trim(($manager['last_name'] ?? '') . ' ' . ($manager['first_name'] ?? ''))) ?></option><?php endforeach; ?></select></div>
            </div></div>
        </section>

        <section class="card company-form-card erp-form-section employee-form-section" id="employee-contract" data-form-section>
            <div class="card-header"><div><span class="dashboard-section-kicker">Etape 3</span><h2 class="card-title">Contrat et remuneration</h2><p>Definissez les conditions initiales de l'engagement.</p></div></div>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="employee-contract-type">Type de contrat <span class="required-mark">*</span></label><select class="form-select" id="employee-contract-type" name="contract_type" data-contract-type data-contract-summary-source><?php foreach ($contractLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($employee['contract_type'] ?? 'cdi') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4 d-none" data-contract-end-group><label class="form-label" for="employee-contract-end">Date de fin <span class="required-mark">*</span></label><input class="form-control" id="employee-contract-end" type="date" name="contract_end_date" value="<?= e($employee['contract_end_date'] ?? '') ?>" data-contract-end data-contract-summary-source></div>
                <div class="col-md-4"><label class="form-label" for="employee-salary">Salaire de base</label><input class="form-control" id="employee-salary" type="number" step="0.01" min="0" name="base_salary" value="<?= e((string) ($employee['base_salary'] ?? 0)) ?>" data-contract-summary-source></div>
                <div class="col-md-4"><label class="form-label" for="employee-currency">Devise</label><select class="form-select" id="employee-currency" name="currency" data-contract-summary-source><?php foreach (['USD', 'CDF', 'EUR'] as $currency): ?><option value="<?= e($currency) ?>" <?= ($employee['currency'] ?? 'USD') === $currency ? 'selected' : '' ?>><?= e($currency) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><div class="employee-contract-preview"><small>Apercu du contrat</small><strong data-contract-summary></strong></div></div>
            </div></div>
        </section>

        <details class="card company-form-card erp-form-section employee-form-section employee-optional-section" id="employee-identity" data-form-section>
            <summary class="card-header"><div><span class="dashboard-section-kicker">Facultatif</span><h2 class="card-title">Identite complementaire</h2><p>Etat civil et informations personnelles.</p></div><span class="optional-toggle">Afficher</span></summary>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="employee-gender">Sexe</label><select class="form-select" id="employee-gender" name="gender"><option value="">Non renseigne</option><?php foreach ($genderLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($employee['gender'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label" for="employee-birth-date">Date de naissance</label><input class="form-control" id="employee-birth-date" type="date" name="birth_date" value="<?= e($employee['birth_date'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="employee-birth-place">Lieu de naissance</label><input class="form-control" id="employee-birth-place" name="birth_place" value="<?= e($employee['birth_place'] ?? '') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="employee-marital">Etat civil</label><select class="form-select" id="employee-marital" name="marital_status"><option value="">Non renseigne</option><?php foreach ($maritalLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($employee['marital_status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
            </div></div>
        </details>

        <details class="card company-form-card erp-form-section employee-form-section employee-optional-section" id="employee-contact" data-form-section>
            <summary class="card-header"><div><span class="dashboard-section-kicker">Facultatif</span><h2 class="card-title">Coordonnees et urgence</h2><p>Moyens de contact personnels et personne a prevenir.</p></div><span class="optional-toggle">Afficher</span></summary>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="employee-phone">Telephone</label><input class="form-control" id="employee-phone" type="tel" name="phone" value="<?= e($employee['phone'] ?? '') ?>" autocomplete="tel"></div>
                <div class="col-md-6"><label class="form-label" for="employee-email">Email</label><input class="form-control" id="employee-email" type="email" name="email" value="<?= e($employee['email'] ?? '') ?>" autocomplete="email"></div>
                <div class="col-12"><label class="form-label" for="employee-address">Adresse</label><input class="form-control" id="employee-address" name="address" value="<?= e($employee['address'] ?? '') ?>" autocomplete="street-address"></div>
                <div class="col-md-6"><label class="form-label" for="employee-emergency-name">Personne a prevenir</label><input class="form-control" id="employee-emergency-name" name="emergency_contact_name" value="<?= e($employee['emergency_contact_name'] ?? '') ?>"></div>
                <div class="col-md-6"><label class="form-label" for="employee-emergency-phone">Telephone d'urgence</label><input class="form-control" id="employee-emergency-phone" type="tel" name="emergency_contact_phone" value="<?= e($employee['emergency_contact_phone'] ?? '') ?>"></div>
            </div></div>
        </details>

        <div class="company-sticky-actions employee-form-actions">
            <span data-form-status>Verifiez les informations avant l'enregistrement.</span>
            <div><a class="btn btn-outline" href="<?= e(url('/employees')) ?>">Annuler</a><button class="btn btn-primary" type="submit" data-submit-label><?= !empty($employee['id']) ? 'Enregistrer les modifications' : 'Creer l’agent' ?></button></div>
        </div>
    </div>
</div>
