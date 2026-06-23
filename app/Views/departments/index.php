<?php
$options = $options ?? [];
$orgChart = $orgChart ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Organisation interne</span>
        <h1 class="page-title">Departements</h1>
        <p>Structurez les departements, rattachez-les aux sites et designez les managers responsables.</p>
    </div>
    <div class="module-header-actions">
        <a class="btn btn-outline" href="<?= e(url('/positions')) ?>"><?= icon('file') ?><span>Postes</span></a>
        <span class="dashboard-status"><span></span><?= e((string) count($orgChart)) ?> departements</span>
    </div>
</div>

<div class="organization-layout">
    <form class="card company-form-card organization-form-card" method="post" action="<?= e(url('/departments/store')) ?>" data-org-form data-store-url="<?= e(url('/departments/store')) ?>" data-update-url="<?= e(url('/departments/update')) ?>" data-delete-url="<?= e(url('/departments/delete')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="">
        <div class="card-header">
            <div>
                <span class="dashboard-section-kicker">Departement</span>
                <h2 class="card-title" data-org-form-title>Nouveau departement</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-danger d-none" data-form-error></div>
            <?php if ($isSuperAdmin): ?>
                <div class="mb-3">
                    <label class="form-label" for="department_company_id">Entreprise</label>
                    <select id="department_company_id" class="form-select" name="company_id" data-company-select required>
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
                <label class="form-label" for="department_name">Nom</label>
                <input id="department_name" class="form-control" name="name" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="department_code">Code</label>
                <input id="department_code" class="form-control" name="code" placeholder="RH, FIN, OPS">
            </div>
            <div class="mb-3">
                <label class="form-label" for="department_branch_id">Site</label>
                <select id="department_branch_id" class="form-select" name="branch_id" data-filtered-options>
                    <option value="">Aucun site</option>
                    <?php foreach ($options['branches'] ?? [] as $branch): ?>
                        <option value="<?= e((string) $branch['id']) ?>" data-company-id="<?= e((string) $branch['company_id']) ?>"><?= e($branch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="department_manager_id">Manager</label>
                <select id="department_manager_id" class="form-select" name="manager_id" data-filtered-options>
                    <option value="">Aucun manager</option>
                    <?php foreach ($options['managers'] ?? [] as $manager): ?>
                        <option value="<?= e((string) $manager['id']) ?>" data-company-id="<?= e((string) $manager['company_id']) ?>">
                            <?= e(trim(($manager['last_name'] ?? '') . ' ' . ($manager['middle_name'] ?? '') . ' ' . ($manager['first_name'] ?? ''))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                <input type="search" data-org-search placeholder="Rechercher departement, site, manager">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table" id="departments-table" data-ajax-url="<?= e(url('/departments/data')) ?>">
                <thead>
                    <tr>
                        <th>Departement</th>
                        <th>Entreprise</th>
                        <th>Site</th>
                        <th>Manager</th>
                        <th>Postes</th>
                        <th>Employes</th>
                        <th class="w-1">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="card organization-chart-card">
    <div class="card-header">
        <div>
            <span class="dashboard-section-kicker">Organigramme</span>
            <h2 class="card-title">Vue simple par departement</h2>
        </div>
    </div>
    <div class="organization-chart">
        <?php if ($orgChart === []): ?>
            <div class="dashboard-empty"><span>Aucun departement enregistre.</span></div>
        <?php endif; ?>
        <?php foreach ($orgChart as $department): ?>
            <?php $manager = trim(($department['manager_last_name'] ?? '') . ' ' . ($department['manager_first_name'] ?? '')); ?>
            <article class="organization-node">
                <div class="organization-node-header">
                    <span class="company-avatar"><?= e(strtoupper(substr($department['name'] ?? 'D', 0, 2))) ?></span>
                    <div>
                        <strong><?= e($department['name'] ?? '') ?></strong>
                        <span><?= e($manager !== '' ? $manager : 'Manager non defini') ?></span>
                    </div>
                </div>
                <div class="organization-branches">
                    <?php if (empty($department['positions'])): ?>
                        <span class="organization-empty-line">Aucun poste rattache</span>
                    <?php endif; ?>
                    <?php foreach ($department['positions'] as $position): ?>
                        <div class="organization-position-line">
                            <span><?= e($position['title'] ?? '') ?></span>
                            <strong><?= e((string) ($position['employees_count'] ?? 0)) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/organization.js')) ?>"></script>
