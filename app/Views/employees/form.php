<?php
$statusLabels = [
    'active' => 'Actif',
    'on_leave' => 'En conge',
    'suspended' => 'Suspendu',
    'terminated' => 'Archive',
];
$genderLabels = ['male' => 'Masculin', 'female' => 'Feminin', 'other' => 'Autre'];
$maritalLabels = ['single' => 'Celibataire', 'married' => 'Marie(e)', 'divorced' => 'Divorce(e)', 'widowed' => 'Veuf/Veuve'];
$contractLabels = ['cdi' => 'CDI', 'cdd' => 'CDD', 'consultant' => 'Consultant', 'internship' => 'Stage', 'temporary' => 'Temporaire'];
$photo = !empty($employee['photo_path']) ? url($employee['photo_path']) : null;
?>

<div class="company-form-layout employee-form-layout erp-form-layout">
    <aside class="company-form-aside erp-form-rail">
        <div class="employee-photo-box">
            <?php if ($photo): ?>
                <img src="<?= e($photo) ?>" alt="">
            <?php else: ?>
                <span><?= e(strtoupper(substr($employee['first_name'] ?? 'E', 0, 1) . substr($employee['last_name'] ?? '', 0, 1))) ?></span>
            <?php endif; ?>
            <label class="btn btn-outline w-100">
                Photo
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden>
            </label>
        </div>
        <div class="company-form-step is-active"><strong>1</strong><span>Identite</span></div>
        <div class="company-form-step"><strong>2</strong><span>Affectation</span></div>
        <div class="company-form-step"><strong>3</strong><span>Contrat</span></div>
    </aside>

    <div class="company-form-main">
        <div class="alert alert-danger d-none" data-form-error></div>

        <section class="card company-form-card erp-form-section">
            <div class="card-header"><div><span class="dashboard-section-kicker">Personnel</span><h2 class="card-title">Identite et contact</h2></div></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Matricule</label>
                        <input class="form-control" name="employee_number" value="<?= e($employee['employee_number'] ?? '') ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nom</label>
                        <input class="form-control" name="last_name" value="<?= e($employee['last_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Postnom</label>
                        <input class="form-control" name="middle_name" value="<?= e($employee['middle_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Prenom</label>
                        <input class="form-control" name="first_name" value="<?= e($employee['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sexe</label>
                        <select class="form-select" name="gender">
                            <option value="">Selectionner</option>
                            <?php foreach ($genderLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($employee['gender'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date de naissance</label>
                        <input class="form-control" type="date" name="birth_date" value="<?= e($employee['birth_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Lieu de naissance</label>
                        <input class="form-control" name="birth_place" value="<?= e($employee['birth_place'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Etat civil</label>
                        <select class="form-select" name="marital_status">
                            <option value="">Selectionner</option>
                            <?php foreach ($maritalLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($employee['marital_status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Telephone</label>
                        <input class="form-control" name="phone" value="<?= e($employee['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" value="<?= e($employee['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Personne urgence</label>
                        <input class="form-control" name="emergency_contact_name" value="<?= e($employee['emergency_contact_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Telephone urgence</label>
                        <input class="form-control" name="emergency_contact_phone" value="<?= e($employee['emergency_contact_phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Adresse</label>
                        <input class="form-control" name="address" value="<?= e($employee['address'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </section>

        <section class="card company-form-card erp-form-section">
            <div class="card-header"><div><span class="dashboard-section-kicker">Organisation</span><h2 class="card-title">Affectation</h2></div></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Entreprise</label>
                        <select class="form-select" name="company_id" required>
                            <?php foreach ($options['companies'] ?? [] as $company): ?>
                                <option value="<?= e((string) $company['id']) ?>" <?= (int) ($employee['company_id'] ?? 0) === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Site</label>
                        <select class="form-select" name="branch_id">
                            <option value="">Aucun</option>
                            <?php foreach ($options['branches'] ?? [] as $branch): ?>
                                <option value="<?= e((string) $branch['id']) ?>" <?= (int) ($employee['branch_id'] ?? 0) === (int) $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Departement</label>
                        <select class="form-select" name="department_id">
                            <option value="">Aucun</option>
                            <?php foreach ($options['departments'] ?? [] as $department): ?>
                                <option value="<?= e((string) $department['id']) ?>" <?= (int) ($employee['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Poste</label>
                        <select class="form-select" name="position_id">
                            <option value="">Aucun</option>
                            <?php foreach ($options['positions'] ?? [] as $position): ?>
                                <option value="<?= e((string) $position['id']) ?>" <?= (int) ($employee['position_id'] ?? 0) === (int) $position['id'] ? 'selected' : '' ?>><?= e($position['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Manager</label>
                        <select class="form-select" name="manager_id">
                            <option value="">Aucun</option>
                            <?php foreach ($options['managers'] ?? [] as $manager): ?>
                                <?php if ((int) ($employee['id'] ?? 0) === (int) $manager['id']) continue; ?>
                                <option value="<?= e((string) $manager['id']) ?>" <?= (int) ($employee['manager_id'] ?? 0) === (int) $manager['id'] ? 'selected' : '' ?>><?= e(($manager['last_name'] ?? '') . ' ' . ($manager['first_name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Statut</label>
                        <select class="form-select" name="employment_status">
                            <?php foreach ($statusLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($employee['employment_status'] ?? 'active') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="card company-form-card erp-form-section">
            <div class="card-header"><div><span class="dashboard-section-kicker">Contrat</span><h2 class="card-title">Embauche et remuneration</h2></div></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Date d'embauche</label>
                        <input class="form-control" type="date" name="hire_date" value="<?= e($employee['hire_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type de contrat</label>
                        <select class="form-select" name="contract_type">
                            <?php foreach ($contractLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($employee['contract_type'] ?? 'cdi') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Salaire de base</label>
                        <input class="form-control" type="number" step="0.01" min="0" name="base_salary" value="<?= e((string) ($employee['base_salary'] ?? 0)) ?>">
                    </div>
                    <input type="hidden" name="currency" value="<?= e($employee['currency'] ?? 'USD') ?>">
                </div>
            </div>
        </section>

        <div class="company-sticky-actions">
            <a class="btn btn-outline" href="<?= e(url('/employees')) ?>">Annuler</a>
            <button class="btn btn-primary" type="submit" data-submit-label>Enregistrer</button>
        </div>
    </div>
</div>
