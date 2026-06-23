<?php
$company = $company ?? [];
$branches = $branches ?? [];
$plans = $plans ?? [];
$statusLabels = [
    'active' => 'Actif',
    'suspended' => 'Suspendu',
    'inactive' => 'Inactif',
];
$subscriptionLabels = [
    'trial' => 'Essai',
    'active' => 'Actif',
    'past_due' => 'En retard',
    'cancelled' => 'Annule',
    'expired' => 'Expire',
];
$status = $company['status'] ?? 'inactive';
$statusTone = $status === 'active' ? 'green' : ($status === 'suspended' ? 'orange' : 'red');
$isSuperAdmin = (\App\Core\Auth::user()['role_slug'] ?? '') === 'super-admin';
?>

<div class="company-show-hero">
    <div>
        <div class="company-show-title">
            <span class="company-avatar company-avatar-lg"><?= e(strtoupper(substr($company['name'] ?? 'E', 0, 2))) ?></span>
            <div>
                <span class="dashboard-section-kicker">Dossier entreprise</span>
                <h1 class="page-title"><?= e($company['name'] ?? '') ?></h1>
                <p><?= e($company['industry'] ?: 'Secteur non defini') ?> · <?= e($company['city'] ?: '-') ?>, <?= e($company['province'] ?: '-') ?></p>
            </div>
        </div>
    </div>
    <div class="page-actions">
        <span class="badge bg-<?= e($statusTone) ?>-lt company-hero-badge"><?= e($statusLabels[$status] ?? '-') ?></span>
        <a class="btn btn-outline" href="<?= e(url('/companies')) ?>"><?= icon('arrow-right') ?><span>Liste</span></a>
        <a class="btn btn-primary" href="<?= e(url('/companies/edit?id=' . ($company['id'] ?? 0))) ?>"><?= icon('settings') ?><span>Modifier</span></a>
    </div>
</div>

<div class="company-kpi-grid">
    <div class="company-kpi-card">
        <span>RCCM</span>
        <strong><?= e($company['registration_number'] ?: '-') ?></strong>
    </div>
    <div class="company-kpi-card">
        <span>NIF</span>
        <strong><?= e($company['tax_number'] ?: '-') ?></strong>
    </div>
    <div class="company-kpi-card">
        <span>Sites</span>
        <strong><?= e((string) count($branches)) ?></strong>
    </div>
    <div class="company-kpi-card">
        <span>Plan</span>
        <strong><?= e($company['plan_name'] ?: 'Aucun') ?></strong>
    </div>
</div>

<div class="company-detail-grid">
    <div class="card company-profile-card">
        <div class="card-header">
            <h2 class="card-title">Informations generales</h2>
            <span class="badge bg-<?= e($statusTone) ?>-lt"><?= e($statusLabels[$status] ?? '-') ?></span>
        </div>
        <div class="card-body">
            <dl class="company-definition-list">
                <div><dt>RCCM</dt><dd><?= e($company['registration_number'] ?: '-') ?></dd></div>
                <div><dt>ID NAT</dt><dd><?= e($company['national_id'] ?: '-') ?></dd></div>
                <div><dt>NIF</dt><dd><?= e($company['tax_number'] ?: '-') ?></dd></div>
                <div><dt>Telephone</dt><dd><?= e($company['phone'] ?: '-') ?></dd></div>
                <div><dt>Email</dt><dd><?= e($company['email'] ?: '-') ?></dd></div>
                <div><dt>Adresse</dt><dd><?= e($company['address'] ?: '-') ?></dd></div>
            </dl>
        </div>
    </div>

    <?php if ($isSuperAdmin): ?>
    <div class="card company-profile-card">
        <div class="card-header">
            <div>
                <span class="dashboard-section-kicker">SaaS</span>
                <h2 class="card-title">Abonnement</h2>
            </div>
            <span class="badge bg-green-lt"><?= e($subscriptionLabels[$company['subscription_status'] ?? 'trial'] ?? 'Non defini') ?></span>
        </div>
        <form method="post" action="<?= e(url('/companies/subscription/update?id=' . ($company['id'] ?? 0))) ?>" data-company-form>
            <?= csrf_field() ?>
            <div class="card-body">
                <div class="alert alert-danger d-none" data-form-error></div>
                <div class="mb-3">
                    <label class="form-label" for="subscription_plan_id">Plan</label>
                    <select id="subscription_plan_id" class="form-select" name="subscription_plan_id" required>
                        <option value="">Selectionner</option>
                        <?php foreach ($plans as $plan): ?>
                            <option value="<?= e((string) $plan['id']) ?>" <?= (int) ($company['current_subscription_plan_id'] ?? $company['subscription_plan_id'] ?? 0) === (int) $plan['id'] ? 'selected' : '' ?>>
                                <?= e($plan['name']) ?> · <?= e(number_format((float) $plan['monthly_price'], 2, ',', ' ')) ?> <?= e($plan['currency']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label" for="sub_status">Statut</label>
                        <select id="sub_status" class="form-select" name="status">
                            <?php foreach ($subscriptionLabels as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($company['subscription_status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="starts_at">Debut</label>
                        <input id="starts_at" class="form-control" type="date" name="starts_at" value="<?= e($company['starts_at'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ends_at">Fin</label>
                        <input id="ends_at" class="form-control" type="date" name="ends_at" value="<?= e($company['ends_at'] ?? '') ?>">
                    </div>
                </div>
                <input type="hidden" name="trial_ends_at" value="<?= e($company['trial_ends_at'] ?? '') ?>">
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary" type="submit" data-submit-label>Mettre a jour</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="card mt-3 company-sites-card">
    <div class="card-header">
        <div>
            <span class="dashboard-section-kicker">Implantations</span>
            <h2 class="card-title">Sites et agences</h2>
        </div>
    </div>
    <div class="card-body">
        <form class="company-branch-form" method="post" action="<?= e(url('/companies/branches/store?id=' . ($company['id'] ?? 0))) ?>" data-company-form>
            <?= csrf_field() ?>
            <div class="alert alert-danger d-none" data-form-error></div>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Nom</label>
                    <input class="form-control" name="name" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Code</label>
                    <input class="form-control" name="code">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ville</label>
                    <input class="form-control" name="city">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Telephone</label>
                    <input class="form-control" name="phone">
                </div>
                <div class="col-md-2">
                    <label class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_head_office" value="1">
                        <span class="form-check-label">Siege</span>
                    </label>
                    <button class="btn btn-primary w-100" type="submit" data-submit-label>Ajouter</button>
                </div>
                <div class="col-12">
                    <input class="form-control" name="address" placeholder="Adresse">
                </div>
            </div>
        </form>
    </div>
    <div class="company-site-grid">
        <?php if ($branches === []): ?>
            <div class="dashboard-empty"><span>Aucun site enregistre.</span></div>
        <?php endif; ?>
        <?php foreach ($branches as $branch): ?>
            <div class="company-site-card">
                <div>
                    <span class="metric-icon tone-blue"><?= icon('building') ?></span>
                    <div>
                        <strong><?= e($branch['name']) ?></strong>
                        <span><?= e($branch['code'] ?: 'Code non defini') ?><?= !empty($branch['is_head_office']) ? ' · Siege' : '' ?></span>
                    </div>
                </div>
                <p><?= e($branch['address'] ?: 'Adresse non renseignee') ?></p>
                <div class="company-site-footer">
                    <span><?= e($branch['city'] ?: '-') ?></span>
                    <span><?= e($branch['phone'] ?: '-') ?></span>
                    <button class="btn btn-icon btn-outline-danger" type="button" data-branch-delete="<?= e((string) $branch['id']) ?>"><?= icon('x') ?></button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/companies.js')) ?>"></script>
