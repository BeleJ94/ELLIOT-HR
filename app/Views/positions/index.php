<?php
$options = $options ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Organisation interne</span>
        <h1 class="page-title">Postes</h1>
        <p>Administrez les postes et rattachez chaque fonction au departement correspondant.</p>
    </div>
    <div class="module-header-actions">
        <a class="btn btn-outline" href="<?= e(url('/departments')) ?>"><?= icon('building') ?><span>Departements</span></a>
    </div>
</div>

<div class="organization-layout">
    <form class="card company-form-card organization-form-card" method="post" action="<?= e(url('/positions/store')) ?>" data-org-form data-store-url="<?= e(url('/positions/store')) ?>" data-update-url="<?= e(url('/positions/update')) ?>" data-delete-url="<?= e(url('/positions/delete')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="">
        <div class="card-header">
            <div>
                <span class="dashboard-section-kicker">Poste</span>
                <h2 class="card-title" data-org-form-title>Nouveau poste</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-danger d-none" data-form-error></div>
            <?php if ($isSuperAdmin): ?>
                <div class="mb-3">
                    <label class="form-label" for="position_company_id">Entreprise</label>
                    <select id="position_company_id" class="form-select" name="company_id" data-company-select required>
                        <option value="">Selectionner</option>
                        <?php foreach ($options['companies'] ?? [] as $company): ?>
                            <option value="<?= e((string) $company['id']) ?>" <?= $defaultCompanyId === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="company_id" value="<?= e((string) $defaultCompanyId) ?>" data-company-select>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label" for="position_title">Titre</label>
                <input id="position_title" class="form-control" name="title" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="position_code">Code</label>
                <input id="position_code" class="form-control" name="code" placeholder="HR-MGR, ACC-01">
            </div>
            <div class="mb-3">
                <label class="form-label" for="position_department_id">Departement</label>
                <select id="position_department_id" class="form-select" name="department_id" data-filtered-options>
                    <option value="">Aucun departement</option>
                    <?php foreach ($options['departments'] ?? [] as $department): ?>
                        <option value="<?= e((string) $department['id']) ?>" data-company-id="<?= e((string) $department['company_id']) ?>"><?= e($department['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="position_description">Description</label>
                <textarea id="position_description" class="form-control" name="description" rows="4"></textarea>
            </div>
        </div>
        <div class="card-footer organization-form-actions">
            <button class="btn btn-outline" type="button" data-org-reset>Reinitialiser</button>
            <button class="btn btn-primary" type="submit" data-submit-label>Enregistrer</button>
        </div>
    </form>

    <div class="card company-table-card organization-table-card">
        <div class="company-toolbar organization-toolbar">
            <div class="topbar-search company-search">
                <?= icon('search') ?>
                <input type="search" data-org-search placeholder="Rechercher poste, code, departement">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table" id="positions-table" data-ajax-url="<?= e(url('/positions/data')) ?>">
                <thead>
                    <tr>
                        <th>Poste</th>
                        <th>Entreprise</th>
                        <th>Departement</th>
                        <th>Description</th>
                        <th>Employes</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/organization.js')) ?>"></script>
