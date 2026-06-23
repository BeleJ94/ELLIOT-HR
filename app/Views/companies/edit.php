<?php $company = $company ?? []; ?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Modification</span>
        <h1 class="page-title"><?= e($company['name'] ?? 'Entreprise') ?></h1>
        <p>Mettez a jour les donnees legales, le contact et la localisation principale.</p>
    </div>
    <div class="module-header-actions">
        <a class="btn btn-outline" href="<?= e(url('/companies')) ?>"><?= icon('arrow-right') ?><span>Liste</span></a>
        <a class="btn btn-primary" href="<?= e(url('/companies/show?id=' . ($company['id'] ?? 0))) ?>"><?= icon('file') ?><span>Dossier</span></a>
    </div>
</div>

<form method="post" action="<?= e(url('/companies/update?id=' . ($company['id'] ?? 0))) ?>" data-company-form>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($company['id'] ?? 0)) ?>">
    <div class="company-form-layout">
        <aside class="company-form-aside">
            <div class="company-edit-summary">
                <span class="company-avatar"><?= e(strtoupper(substr($company['name'] ?? 'E', 0, 2))) ?></span>
                <strong><?= e($company['name'] ?? 'Entreprise') ?></strong>
                <small><?= e($company['registration_number'] ?: 'RCCM non renseigne') ?></small>
            </div>
            <div class="company-form-step is-active"><strong>1</strong><span>Identite</span></div>
            <div class="company-form-step"><strong>2</strong><span>Contact</span></div>
            <div class="company-form-step"><strong>3</strong><span>Adresse</span></div>
        </aside>

        <div class="company-form-main">
            <div class="alert alert-danger d-none" data-form-error></div>

            <section class="card company-form-card">
                <div class="card-header"><div><span class="dashboard-section-kicker">Legal</span><h2 class="card-title">Identite de l'entreprise</h2></div></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="name">Nom</label>
                            <input id="name" class="form-control form-control-lg" name="name" value="<?= e($company['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="industry">Secteur d'activite</label>
                            <input id="industry" class="form-control form-control-lg" name="industry" value="<?= e($company['industry'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="registration_number">RCCM</label>
                            <input id="registration_number" class="form-control" name="registration_number" value="<?= e($company['registration_number'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="national_id">ID NAT</label>
                            <input id="national_id" class="form-control" name="national_id" value="<?= e($company['national_id'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tax_number">NIF</label>
                            <input id="tax_number" class="form-control" name="tax_number" value="<?= e($company['tax_number'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </section>

            <section class="card company-form-card">
                <div class="card-header"><div><span class="dashboard-section-kicker">Coordonnees</span><h2 class="card-title">Contact et statut</h2></div></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="phone">Telephone</label>
                            <input id="phone" class="form-control" name="phone" value="<?= e($company['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="email">Email</label>
                            <input id="email" class="form-control" type="email" name="email" value="<?= e($company['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status">Statut</label>
                            <select id="status" class="form-select" name="status">
                                <option value="active" <?= ($company['status'] ?? '') === 'active' ? 'selected' : '' ?>>Actif</option>
                                <option value="suspended" <?= ($company['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspendu</option>
                                <option value="inactive" <?= ($company['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                            </select>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card company-form-card">
                <div class="card-header"><div><span class="dashboard-section-kicker">Adresse</span><h2 class="card-title">Localisation principale</h2></div></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="city">Ville</label>
                            <input id="city" class="form-control" name="city" value="<?= e($company['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="province">Province</label>
                            <input id="province" class="form-control" name="province" value="<?= e($company['province'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="country">Pays</label>
                            <input id="country" class="form-control" name="country" value="<?= e($company['country'] ?? 'RDC') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Adresse</label>
                            <textarea id="address" class="form-control" name="address" rows="3"><?= e($company['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <div class="company-sticky-actions">
                <a class="btn btn-outline" href="<?= e(url('/companies/show?id=' . ($company['id'] ?? 0))) ?>">Annuler</a>
                <button class="btn btn-primary" type="submit" data-submit-label>Enregistrer</button>
            </div>
        </div>
    </div>
</form>

<script src="<?= e(asset('js/companies.js')) ?>"></script>
