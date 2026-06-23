<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Creation</span>
        <h1 class="page-title">Nouvelle entreprise</h1>
        <p>Commencez par les informations legales et le point de contact principal. Les sites et l'abonnement se configurent ensuite depuis la fiche entreprise.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/companies')) ?>"><?= icon('arrow-right') ?><span>Retour</span></a>
</div>

<form method="post" action="<?= e(url('/companies/store')) ?>" data-company-form>
    <?= csrf_field() ?>
    <div class="company-form-layout">
        <aside class="company-form-aside">
            <div class="company-form-step is-active">
                <strong>1</strong>
                <span>Identite legale</span>
            </div>
            <div class="company-form-step">
                <strong>2</strong>
                <span>Contact</span>
            </div>
            <div class="company-form-step">
                <strong>3</strong>
                <span>Localisation</span>
            </div>
        </aside>

        <div class="company-form-main">
            <div class="alert alert-danger d-none" data-form-error></div>

            <section class="card company-form-card">
                <div class="card-header">
                    <div>
                        <span class="dashboard-section-kicker">Legal</span>
                        <h2 class="card-title">Identite de l'entreprise</h2>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="name">Nom</label>
                            <input id="name" class="form-control form-control-lg" name="name" placeholder="Ex. ELLIOT Services SARL" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="industry">Secteur d'activite</label>
                            <input id="industry" class="form-control form-control-lg" name="industry" placeholder="Services, mines, logistique...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="registration_number">RCCM</label>
                            <input id="registration_number" class="form-control" name="registration_number">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="national_id">ID NAT</label>
                            <input id="national_id" class="form-control" name="national_id">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tax_number">NIF</label>
                            <input id="tax_number" class="form-control" name="tax_number">
                        </div>
                    </div>
                </div>
            </section>

            <section class="card company-form-card">
                <div class="card-header">
                    <div>
                        <span class="dashboard-section-kicker">Coordonnees</span>
                        <h2 class="card-title">Contact et statut</h2>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="phone">Telephone</label>
                            <input id="phone" class="form-control" name="phone" placeholder="+243...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="email">Email</label>
                            <input id="email" class="form-control" type="email" name="email" placeholder="contact@entreprise.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status">Statut</label>
                            <select id="status" class="form-select" name="status">
                                <option value="active">Actif</option>
                                <option value="suspended">Suspendu</option>
                                <option value="inactive">Inactif</option>
                            </select>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card company-form-card">
                <div class="card-header">
                    <div>
                        <span class="dashboard-section-kicker">Adresse</span>
                        <h2 class="card-title">Localisation principale</h2>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="city">Ville</label>
                            <input id="city" class="form-control" name="city">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="province">Province</label>
                            <input id="province" class="form-control" name="province">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="country">Pays</label>
                            <input id="country" class="form-control" name="country" value="RDC">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Adresse</label>
                            <textarea id="address" class="form-control" name="address" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <div class="company-sticky-actions">
                <a class="btn btn-outline" href="<?= e(url('/companies')) ?>">Annuler</a>
                <button class="btn btn-primary" type="submit" data-submit-label>Creer l'entreprise</button>
            </div>
        </div>
    </div>
</form>

<script src="<?= e(asset('js/companies.js')) ?>"></script>
