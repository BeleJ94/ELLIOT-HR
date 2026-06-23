<?php
$companies = $companies ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Paie RDC</span>
        <h1 class="page-title">Nouvelle periode de paie</h1>
        <p>Creez la periode mensuelle avant de lancer le calcul des bulletins.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/payroll')) ?>"><?= icon('file') ?><span>Journal paie</span></a>
</div>

<form class="card company-form-card" method="post" action="<?= e(url('/payroll/periods/store')) ?>" data-payroll-form>
    <?= csrf_field() ?>
    <div class="card-body">
        <div class="alert alert-danger d-none" data-form-error></div>
        <div class="row g-3">
            <?php if ($isSuperAdmin): ?>
                <div class="col-md-6">
                    <label class="form-label" for="company_id">Entreprise</label>
                    <select id="company_id" class="form-select" name="company_id" required>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= e((string) $company['id']) ?>" <?= $defaultCompanyId === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="company_id" value="<?= e((string) $defaultCompanyId) ?>">
            <?php endif; ?>
            <div class="col-md-6">
                <label class="form-label" for="name">Libelle</label>
                <input id="name" class="form-control" name="name" placeholder="Paie <?= e(date('m/Y')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="period_month">Mois</label>
                <input id="period_month" class="form-control" type="number" min="1" max="12" name="period_month" value="<?= e(date('n')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="period_year">Annee</label>
                <input id="period_year" class="form-control" type="number" min="2000" max="2100" name="period_year" value="<?= e(date('Y')) ?>" required>
            </div>
        </div>
    </div>
    <div class="card-footer organization-form-actions">
        <a class="btn btn-outline" href="<?= e(url('/payroll')) ?>">Annuler</a>
        <button class="btn btn-primary" type="submit" data-submit-label>Creer la periode</button>
    </div>
</form>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/payroll.js')) ?>"></script>
