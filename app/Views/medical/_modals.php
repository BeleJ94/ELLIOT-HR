<?php
$employees = $employees ?? [];
$dependents = $dependents ?? [];
$providers = $providers ?? [];
$companies = $companies ?? [];
$settings = $settings ?? [];
$careTypes = $careTypes ?? [];
$relationships = $relationships ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$canManageMedical = !empty($canManageMedical);
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$selfEmployeeId = $selfEmployeeId ?? null;
$employeeName = static fn(array $row): string => trim(($row['last_name'] ?? $row['employee_last_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['first_name'] ?? $row['employee_first_name'] ?? ''));
?>

<div class="user-modal medical-modal" data-medical-modal="request" aria-hidden="true">
    <div class="user-modal-backdrop" data-medical-close></div>
    <section class="user-modal-dialog medical-dialog medical-dialog-pro">
        <div class="user-modal-header"><div><span class="dashboard-section-kicker">Demande</span><h2>Nouvelle prise en charge</h2><p>Encodez le bénéficiaire, le type de soin et l'estimation à valider.</p></div><button class="btn btn-icon" type="button" data-medical-close><?= icon('x') ?></button></div>
        <form method="post" action="<?= e(url('/medical/requests/store')) ?>" data-medical-form>
            <?= csrf_field() ?><div class="alert alert-danger d-none" data-form-error></div>
            <div class="medical-form-grid">
                <div class="medical-form-section is-wide"><span>1</span><div><strong>Bénéficiaire</strong><small>Titulaire et ayant droit concerné</small></div></div>
                <div class="medical-field is-wide"><label class="form-label">Employe titulaire</label><select class="form-select" name="employee_id" required <?php if ($selfEmployeeId !== null): ?>disabled<?php endif; ?>><?php foreach ($employees as $employee): ?><option value="<?= e((string) $employee['id']) ?>"><?= e($employee['employee_number'] . ' · ' . $employeeName($employee)) ?></option><?php endforeach; ?></select><?php if ($selfEmployeeId !== null): ?><input type="hidden" name="employee_id" value="<?= e((string) $selfEmployeeId) ?>"><?php endif; ?></div>
                <div class="medical-field is-wide"><label class="form-label">Beneficiaire</label><select class="form-select" name="dependent_id"><option value="">Employe lui-meme</option><?php foreach ($dependents as $dependent): ?><option value="<?= e((string) $dependent['id']) ?>"><?= e(trim($dependent['last_name'] . ' ' . $dependent['first_name']) . ' · ' . ($relationships[$dependent['relationship']] ?? $dependent['relationship'])) ?></option><?php endforeach; ?></select></div>
                <div class="medical-form-section is-wide"><span>2</span><div><strong>Soin demandé</strong><small>Prestataire, catégorie et enveloppe estimée</small></div></div>
                <div class="medical-field"><label class="form-label">Type de soin</label><select class="form-select" name="care_type" required><?php foreach ($careTypes as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="medical-field"><label class="form-label">Prestataire</label><select class="form-select" name="provider_id"><option value="">A confirmer</option><?php foreach ($providers as $provider): ?><option value="<?= e((string) $provider['id']) ?>"><?= e($provider['name']) ?></option><?php endforeach; ?></select></div>
                <div class="medical-field"><label class="form-label">Estimation</label><input class="form-control" type="number" step="0.01" min="0" name="requested_amount" required></div>
                <div class="medical-field is-wide"><label class="form-label">Motif medical / observation</label><textarea class="form-control" rows="3" name="medical_reason"></textarea></div>
            </div>
            <div class="user-modal-footer-actions"><button class="btn btn-outline" type="button" data-medical-close>Annuler</button><button class="btn btn-primary" type="submit" data-submit-label>Soumettre</button></div>
        </form>
    </section>
</div>

<div class="user-modal medical-modal" data-medical-modal="dependent" aria-hidden="true">
    <div class="user-modal-backdrop" data-medical-close></div>
    <section class="user-modal-dialog medical-dialog medical-dialog-pro">
        <div class="user-modal-header"><div><span class="dashboard-section-kicker">Ayant droit</span><h2>Ajouter un beneficiaire</h2><p>Les justificatifs et limites d'âge restent contrôlés par la politique médicale.</p></div><button class="btn btn-icon" type="button" data-medical-close><?= icon('x') ?></button></div>
        <form method="post" action="<?= e(url('/medical/dependents/store')) ?>" data-medical-form>
            <?= csrf_field() ?><div class="alert alert-danger d-none" data-form-error></div>
            <div class="medical-form-grid">
                <div class="medical-form-section is-wide"><span>1</span><div><strong>Rattachement</strong><small>Employé titulaire et lien familial</small></div></div>
                <div class="medical-field"><label class="form-label">Employe titulaire</label><select class="form-select" name="employee_id" required <?php if ($selfEmployeeId !== null): ?>disabled<?php endif; ?>><?php foreach ($employees as $employee): ?><option value="<?= e((string) $employee['id']) ?>"><?= e($employee['employee_number'] . ' · ' . $employeeName($employee)) ?></option><?php endforeach; ?></select><?php if ($selfEmployeeId !== null): ?><input type="hidden" name="employee_id" value="<?= e((string) $selfEmployeeId) ?>"><?php endif; ?></div>
                <div class="medical-field"><label class="form-label">Lien familial</label><select class="form-select" name="relationship" required><?php foreach ($relationships as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="medical-form-section is-wide"><span>2</span><div><strong>Identité</strong><small>Informations et justificatifs</small></div></div>
                <div class="medical-field"><label class="form-label">Nom</label><input class="form-control" name="last_name" required></div>
                <div class="medical-field"><label class="form-label">Prenom</label><input class="form-control" name="first_name" required></div>
                <div class="medical-field"><label class="form-label">Sexe</label><select class="form-select" name="gender"><option value="">-</option><option value="female">Femme</option><option value="male">Homme</option><option value="other">Autre</option></select></div>
                <div class="medical-field"><label class="form-label">Naissance</label><input class="form-control" type="date" name="birth_date"></div>
                <div class="medical-field"><label class="form-label">Debut couverture</label><input class="form-control" type="date" name="coverage_start" value="<?= e(date('Y-m-d')) ?>"></div>
                <div class="medical-field"><label class="form-label">Fin couverture</label><input class="form-control" type="date" name="coverage_end"></div>
                <div class="medical-field"><label class="form-label">Document</label><input class="form-control" name="document_type" placeholder="Acte naissance, mariage..."></div>
                <div class="medical-field"><label class="form-label">Reference document</label><input class="form-control" name="document_reference"></div>
                <?php if ($canManageMedical): ?><div class="medical-field"><label class="form-label">Statut</label><select class="form-select" name="status"><option value="active">Actif</option><option value="pending">A verifier</option><option value="suspended">Suspendu</option></select></div><?php endif; ?>
                <div class="medical-field is-wide"><label class="form-label">Notes</label><textarea class="form-control" rows="2" name="notes"></textarea></div>
            </div>
            <div class="user-modal-footer-actions"><button class="btn btn-outline" type="button" data-medical-close>Annuler</button><button class="btn btn-primary" type="submit" data-submit-label>Enregistrer</button></div>
        </form>
    </section>
</div>

<?php if ($canManageMedical): ?>
<div class="user-modal medical-modal" data-medical-modal="provider" aria-hidden="true">
    <div class="user-modal-backdrop" data-medical-close></div>
    <section class="user-modal-dialog medical-dialog medical-dialog-pro">
        <div class="user-modal-header"><div><span class="dashboard-section-kicker">Prestataire</span><h2>Centre medical conventionne</h2><p>Définissez les coordonnées et un taux spécifique si la convention le prévoit.</p></div><button class="btn btn-icon" type="button" data-medical-close><?= icon('x') ?></button></div>
        <form method="post" action="<?= e(url('/medical/providers/store')) ?>" data-medical-form>
            <?= csrf_field() ?><div class="alert alert-danger d-none" data-form-error></div>
            <div class="medical-form-grid">
                <?php if ($isSuperAdmin): ?><div class="medical-field is-wide"><label class="form-label">Entreprise</label><select class="form-select" name="company_id"><?php foreach ($companies as $company): ?><option value="<?= e((string) $company['id']) ?>" <?= $defaultCompanyId === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option><?php endforeach; ?></select></div><?php else: ?><input type="hidden" name="company_id" value="<?= e((string) $defaultCompanyId) ?>"><?php endif; ?>
                <div class="medical-field is-wide"><label class="form-label">Nom</label><input class="form-control" name="name" required></div>
                <div class="medical-field"><label class="form-label">Type</label><select class="form-select" name="provider_type"><option value="clinic">Clinique</option><option value="hospital">Hopital</option><option value="pharmacy">Pharmacie</option><option value="laboratory">Laboratoire</option><option value="other">Autre</option></select></div>
                <div class="medical-field"><label class="form-label">Ville</label><input class="form-control" name="city"></div>
                <div class="medical-field"><label class="form-label">Taux specifique (%)</label><input class="form-control" type="number" min="0" max="100" step="0.01" name="default_coverage_rate"></div>
                <div class="medical-field"><label class="form-label">Telephone</label><input class="form-control" name="phone"></div>
                <div class="medical-field"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div>
                <div class="medical-field is-wide"><label class="form-label">Adresse</label><textarea class="form-control" rows="2" name="address"></textarea></div>
            </div>
            <div class="user-modal-footer-actions"><button class="btn btn-outline" type="button" data-medical-close>Annuler</button><button class="btn btn-primary" type="submit" data-submit-label>Enregistrer</button></div>
        </form>
    </section>
</div>

<div class="user-modal medical-modal" data-medical-modal="settings" aria-hidden="true">
    <div class="user-modal-backdrop" data-medical-close></div>
    <section class="user-modal-dialog medical-dialog medical-dialog-pro">
        <div class="user-modal-header"><div><span class="dashboard-section-kicker">Politique medicale</span><h2>Regles de couverture</h2><p>Les règles restent paramétrables par entreprise pour refléter la pratique interne.</p></div><button class="btn btn-icon" type="button" data-medical-close><?= icon('x') ?></button></div>
        <form method="post" action="<?= e(url('/medical/settings/store')) ?>" data-medical-form>
            <?= csrf_field() ?><div class="alert alert-danger d-none" data-form-error></div>
            <div class="medical-form-grid">
                <?php if ($isSuperAdmin): ?><div class="medical-field is-wide"><label class="form-label">Entreprise</label><select class="form-select" name="company_id"><?php foreach ($companies as $company): ?><option value="<?= e((string) $company['id']) ?>" <?= $defaultCompanyId === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option><?php endforeach; ?></select></div><?php else: ?><input type="hidden" name="company_id" value="<?= e((string) $defaultCompanyId) ?>"><?php endif; ?>
                <div class="medical-field"><label class="form-label">Taux standard (%)</label><input class="form-control" type="number" min="0" max="100" step="0.01" name="default_coverage_rate" value="<?= e((string) ($settings['default_coverage_rate'] ?? 80)) ?>"></div>
                <div class="medical-field"><label class="form-label">Plafond employe/an</label><input class="form-control" type="number" step="0.01" min="0" name="annual_employee_ceiling" value="<?= e((string) ($settings['annual_employee_ceiling'] ?? '')) ?>"></div>
                <div class="medical-field"><label class="form-label">Plafond ayant droit/an</label><input class="form-control" type="number" step="0.01" min="0" name="annual_dependent_ceiling" value="<?= e((string) ($settings['annual_dependent_ceiling'] ?? '')) ?>"></div>
                <div class="medical-field"><label class="form-label">Validite bon</label><input class="form-control" type="number" min="1" max="90" name="voucher_valid_days" value="<?= e((string) ($settings['voucher_valid_days'] ?? 7)) ?>"></div>
                <div class="medical-field"><label class="form-label">Age enfant</label><input class="form-control" type="number" min="0" max="30" name="max_child_age" value="<?= e((string) ($settings['max_child_age'] ?? 18)) ?>"></div>
                <div class="medical-field"><label class="form-label">Age etudiant</label><input class="form-control" type="number" min="0" max="35" name="student_child_age" value="<?= e((string) ($settings['student_child_age'] ?? 25)) ?>"></div>
                <div class="medical-field"><label class="form-label">Devise</label><input class="form-control" name="currency" value="<?= e($settings['currency'] ?? 'USD') ?>"></div>
                <div class="medical-checks is-wide">
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="spouse_covered" value="1" <?= !empty($settings['spouse_covered']) ? 'checked' : '' ?>><span class="form-check-label">Conjoint</span></label>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="children_covered" value="1" <?= !empty($settings['children_covered']) ? 'checked' : '' ?>><span class="form-check-label">Enfants</span></label>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="parents_covered" value="1" <?= !empty($settings['parents_covered']) ? 'checked' : '' ?>><span class="form-check-label">Parents</span></label>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="payroll_recovery_enabled" value="1" <?= !empty($settings['payroll_recovery_enabled']) ? 'checked' : '' ?>><span class="form-check-label">Retenue paie</span></label>
                </div>
                <div class="medical-field is-wide"><label class="form-label">Notes internes</label><textarea class="form-control" rows="3" name="notes"><?= e($settings['notes'] ?? '') ?></textarea></div>
            </div>
            <div class="user-modal-footer-actions"><button class="btn btn-outline" type="button" data-medical-close>Annuler</button><button class="btn btn-primary" type="submit" data-submit-label>Enregistrer</button></div>
        </form>
    </section>
</div>
<?php endif; ?>
