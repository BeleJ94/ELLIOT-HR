<?php
$companies = $companies ?? [];
$canCreateCompany = $canCreateCompany ?? false;
$isSuperAdmin = !empty($isSuperAdmin);
$statusLabels = [
    'active' => 'Actif',
    'suspended' => 'Suspendu',
    'inactive' => 'Inactif',
];
$statusTones = [
    'active' => 'green',
    'suspended' => 'orange',
    'inactive' => 'red',
];

$activeCount = 0;
$suspendedCount = 0;
$inactiveCount = 0;
$branchesCount = 0;

foreach ($companies as $company) {
    $status = $company['status'] ?? 'inactive';
    $branchesCount += (int) ($company['branches_count'] ?? 0);

    if ($status === 'active') {
        $activeCount++;
    } elseif ($status === 'suspended') {
        $suspendedCount++;
    } else {
        $inactiveCount++;
    }
}
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker"><?= $isSuperAdmin ? 'Portefeuille clients' : 'Organisation' ?></span>
        <h1 class="page-title"><?= $isSuperAdmin ? 'Entreprises clientes' : 'Mon entreprise' ?></h1>
        <p><?= $isSuperAdmin
            ? 'Centralisez les sociétés clientes, leurs identifiants administratifs, sites, contacts et abonnements.'
            : 'Consultez et maintenez les informations administratives, coordonnées et sites de votre organisation.' ?></p>
    </div>
    <div class="module-header-actions">
        <span class="dashboard-status"><span></span><?= e((string) $activeCount) ?> actives</span>
        <?php if ($canCreateCompany): ?>
            <a class="btn btn-primary" href="<?= e(url('/companies/create')) ?>"><?= icon('building') ?><span>Nouvelle entreprise</span></a>
        <?php endif; ?>
    </div>
</div>

<div class="company-insights">
    <div class="company-insight">
        <span>Total</span>
        <strong><?= e((string) count($companies)) ?></strong>
        <small>entreprises clientes</small>
    </div>
    <div class="company-insight">
        <span>Actives</span>
        <strong><?= e((string) $activeCount) ?></strong>
        <small>operationnelles</small>
    </div>
    <div class="company-insight">
        <span>Suspendues</span>
        <strong><?= e((string) $suspendedCount) ?></strong>
        <small>a regulariser</small>
    </div>
    <div class="company-insight">
        <span>Sites</span>
        <strong><?= e((string) $branchesCount) ?></strong>
        <small>agences rattachees</small>
    </div>
</div>

<div class="company-toolbar">
    <div class="topbar-search company-search">
        <?= icon('search') ?>
        <input type="search" data-company-search placeholder="Rechercher une entreprise, RCCM, ville, secteur">
    </div>
    <div class="company-filter-hint">
        <span class="badge bg-green-lt"><?= e((string) $activeCount) ?> actifs</span>
        <span class="badge bg-orange-lt"><?= e((string) $suspendedCount) ?> suspendus</span>
        <span class="badge bg-red-lt"><?= e((string) $inactiveCount) ?> inactifs</span>
    </div>
</div>

<div class="card company-table-card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table" id="companies-table">
            <thead>
                <tr>
                    <th>Entreprise</th>
                    <th>Identification</th>
                    <th>Localisation</th>
                    <th>Contact</th>
                    <th>Abonnement</th>
                    <th>Statut</th>
                    <th class="w-1">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                    <?php
                    $status = $company['status'] ?? 'inactive';
                    $tone = $statusTones[$status] ?? 'red';
                    ?>
                    <tr data-company-row="<?= e((string) $company['id']) ?>">
                        <td>
                            <a class="company-name" href="<?= e(url('/companies/show?id=' . $company['id'])) ?>"><?= e($company['name'] ?? '') ?></a>
                            <span class="d-block text-secondary"><?= e($company['industry'] ?? 'Secteur non defini') ?></span>
                        </td>
                        <td>
                            <span class="d-block">RCCM: <?= e($company['registration_number'] ?: '-') ?></span>
                            <span class="d-block text-secondary">NIF: <?= e($company['tax_number'] ?: '-') ?></span>
                        </td>
                        <td>
                            <span class="d-block"><?= e($company['city'] ?: '-') ?></span>
                            <span class="d-block text-secondary"><?= e($company['province'] ?: '-') ?></span>
                        </td>
                        <td>
                            <span class="d-block"><?= e($company['phone'] ?: '-') ?></span>
                            <span class="d-block text-secondary"><?= e($company['email'] ?: '-') ?></span>
                        </td>
                        <td>
                            <span class="d-block"><?= e($company['plan_name'] ?? 'Aucun plan') ?></span>
                            <span class="d-block text-secondary"><?= e((string) ($company['employees_count'] ?? 0)) ?> employes · <?= e((string) ($company['branches_count'] ?? 0)) ?> sites</span>
                        </td>
                        <td data-search="<?= e($statusLabels[$status] ?? $status) ?>">
                            <?php if ($isSuperAdmin): ?>
                                <select class="form-select form-select-sm company-status-select" data-company-status="<?= e((string) $company['id']) ?>">
                                    <?php foreach ($statusLabels as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="badge bg-<?= e($tone) ?>-lt mt-2" data-status-badge><?= e($statusLabels[$status] ?? $status) ?></span>
                            <?php else: ?>
                                <span class="badge bg-<?= e($tone) ?>-lt" data-status-badge><?= e($statusLabels[$status] ?? $status) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                <a class="btn btn-icon" href="<?= e(url('/companies/show?id=' . $company['id'])) ?>" title="Details"><?= icon('file') ?></a>
                                <a class="btn btn-icon" href="<?= e(url('/companies/edit?id=' . $company['id'])) ?>" title="Modifier"><?= icon('settings') ?></a>
                                <?php if ($isSuperAdmin): ?>
                                    <button class="btn btn-icon btn-outline-danger" type="button" data-company-delete="<?= e((string) $company['id']) ?>" title="Supprimer"><?= icon('x') ?></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/companies.js')) ?>"></script>
