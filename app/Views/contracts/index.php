<?php
$alerts = $alerts ?? [];
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Contrats RH</span>
        <h1 class="page-title">Contrats</h1>
        <p>Suivi des CDI, CDD, stages, consultants, renouvellements, expirations et contrats signes.</p>
    </div>
    <div class="module-header-actions">
        <button class="btn btn-outline" type="button" data-contract-expire><?= icon('check') ?><span>Expirer maintenant</span></button>
        <a class="btn btn-primary" href="<?= e(url('/contracts/create')) ?>"><?= icon('file') ?><span>Nouveau contrat</span></a>
    </div>
</div>

<?php if ($alerts !== []): ?>
    <div class="alert alert-warning">
        <strong><?= e((string) count($alerts)) ?> contrat(s) expirent bientot.</strong>
        <span>Consultez la liste pour preparer les renouvellements.</span>
    </div>
<?php endif; ?>

<div class="company-toolbar">
    <div class="topbar-search company-search">
        <?= icon('search') ?>
        <input type="search" data-contract-search placeholder="Rechercher contrat, employe, entreprise, poste">
    </div>
    <div class="company-filter-hint">
        <span class="badge bg-orange-lt"><?= e((string) count($alerts)) ?> alertes</span>
    </div>
</div>

<div class="card company-table-card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table" id="contracts-table" data-ajax-url="<?= e(url('/contracts/data')) ?>">
            <thead>
                <tr>
                    <th>Contrat</th>
                    <th>Employe</th>
                    <th>Entreprise</th>
                    <th>Type</th>
                    <th>Periode</th>
                    <th>Salaire</th>
                    <th>Statut</th>
                    <th class="w-1">Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/contracts.js')) ?>"></script>
