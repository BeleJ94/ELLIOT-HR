<?php
$items = $items ?? [];
$taxSettings = $taxSettings ?? [];
$contributionSettings = $contributionSettings ?? [];
$companies = $companies ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$money = static fn($value): string => number_format((float) $value, 2, ',', ' ');
$rate = static fn($value): string => number_format((float) $value, 4, ',', ' ') . '%';
$activeTaxes = count(array_filter($taxSettings, static fn(array $row): bool => !empty($row['is_active'])));
$activeContributions = count(array_filter($contributionSettings, static fn(array $row): bool => !empty($row['is_active'])));
$taxableItems = count(array_filter($items, static fn(array $row): bool => !empty($row['taxable'])));
$configurationReady = count($items) > 0 && $activeTaxes > 0 && $activeContributions > 0;
$typeLabels = ['earning' => 'Gain', 'deduction' => 'Retenue', 'tax' => 'Taxe', 'contribution' => 'Cotisation'];
$typeTones = ['earning' => 'green', 'deduction' => 'orange', 'tax' => 'blue', 'contribution' => 'purple'];
$companyOptions = static function (array $companies, int $defaultCompanyId): void {
    foreach ($companies as $company) {
        echo '<option value="' . e((string) $company['id']) . '" ' . ($defaultCompanyId === (int) $company['id'] ? 'selected' : '') . '>' . e($company['name']) . '</option>';
    }
};
?>

<div class="payroll-settings-workspace" data-payroll-settings>
    <header class="payroll-settings-hero">
        <div class="payroll-settings-hero-copy">
            <span class="dashboard-section-kicker">Gouvernance de la paie</span>
            <h1 class="page-title">Configuration paie RDC</h1>
            <p>Centralisez les rubriques, le barème IPR et les cotisations sociales utilisés lors du calcul des bulletins.</p>
        </div>
        <div class="payroll-settings-hero-actions">
            <span class="payroll-config-status <?= $configurationReady ? '' : 'is-warning' ?>"><i></i><span><strong><?= e($configurationReady ? 'Configuration active' : 'Configuration à compléter') ?></strong><small><?= e($configurationReady ? 'Prête pour le prochain calcul' : 'Vérifiez les référentiels obligatoires') ?></small></span></span>
            <a class="btn btn-outline" href="<?= e(url('/payroll')) ?>"><?= icon('arrow-right') ?><span>Retour au centre de paie</span></a>
        </div>
    </header>

    <section class="payroll-settings-kpis" aria-label="Résumé de la configuration">
        <article>
            <span class="payroll-settings-kpi-icon is-blue"><?= icon('layers') ?></span>
            <div><small>Rubriques disponibles</small><strong><?= e((string) count($items)) ?></strong><p><?= e((string) $taxableItems) ?> soumise(s) à l’IPR</p></div>
        </article>
        <article>
            <span class="payroll-settings-kpi-icon is-indigo"><?= icon('chart') ?></span>
            <div><small>Tranches fiscales</small><strong><?= e((string) $activeTaxes) ?></strong><p>Barème(s) IPR actif(s)</p></div>
        </article>
        <article>
            <span class="payroll-settings-kpi-icon is-green"><?= icon('shield') ?></span>
            <div><small>Cotisations sociales</small><strong><?= e((string) $activeContributions) ?></strong><p>CNSS, INPP, ONEM et autres</p></div>
        </article>
        <article>
            <span class="payroll-settings-kpi-icon is-orange"><?= icon('building') ?></span>
            <div><small>Périmètre</small><strong><?= e($isSuperAdmin ? (string) count($companies) : '1') ?></strong><p><?= e($isSuperAdmin ? 'Entreprises configurables' : ($companies[0]['name'] ?? 'Entreprise courante')) ?></p></div>
        </article>
    </section>

    <div class="payroll-settings-notice">
        <?= icon('alert') ?>
        <div><strong>Impact sur les calculs</strong><span>Les nouveaux paramètres s’appliqueront aux prochains traitements. Les périodes déjà clôturées ne sont pas modifiées.</span></div>
    </div>

    <nav class="payroll-settings-tabs" role="tablist" aria-label="Domaines de configuration">
        <button class="is-active" type="button" data-payroll-settings-tab="items">
            <span><?= icon('wallet') ?></span><div><strong>Rubriques de paie</strong><small>Gains, retenues et avantages</small></div><b><?= e((string) count($items)) ?></b>
        </button>
        <button type="button" data-payroll-settings-tab="taxes">
            <span><?= icon('chart') ?></span><div><strong>Barème IPR</strong><small>Tranches et taux fiscaux</small></div><b><?= e((string) count($taxSettings)) ?></b>
        </button>
        <button type="button" data-payroll-settings-tab="contributions">
            <span><?= icon('shield') ?></span><div><strong>Cotisations sociales</strong><small>Parts salariale et patronale</small></div><b><?= e((string) count($contributionSettings)) ?></b>
        </button>
    </nav>

    <section class="payroll-settings-panel is-active" data-payroll-settings-panel="items">
        <form class="payroll-settings-form" method="post" action="<?= e(url('/payroll/items/store')) ?>" data-payroll-form>
            <?= csrf_field() ?>
            <div class="payroll-settings-form-head">
                <span class="payroll-settings-form-icon"><?= icon('plus') ?></span>
                <div><span class="dashboard-section-kicker">Nouvelle configuration</span><h2>Ajouter une rubrique</h2><p>Créez un élément qui pourra entrer dans la composition d’un bulletin.</p></div>
            </div>
            <div class="alert alert-danger d-none" data-form-error></div>
            <?php if ($isSuperAdmin): ?>
                <div class="payroll-settings-field is-wide"><label class="form-label">Entreprise concernée <em>Obligatoire</em></label><select class="form-select" name="company_id" data-payroll-company required><?php $companyOptions($companies, $defaultCompanyId); ?></select><small>La rubrique sera isolée dans le référentiel de cette entreprise.</small></div>
            <?php else: ?><input type="hidden" name="company_id" value="<?= e((string) $defaultCompanyId) ?>"><?php endif; ?>
            <div class="payroll-settings-fields">
                <div class="payroll-settings-field is-wide"><label class="form-label">Libellé de la rubrique <em>Obligatoire</em></label><input class="form-control" name="name" placeholder="Ex. Prime de performance" autocomplete="off" required><small>Utilisez un nom immédiatement compréhensible sur le bulletin.</small></div>
                <div class="payroll-settings-field"><label class="form-label">Code interne <em>Obligatoire</em></label><input class="form-control text-uppercase" name="code" placeholder="PRIME_PERF" maxlength="60" autocomplete="off" required></div>
                <div class="payroll-settings-field"><label class="form-label">Nature</label><select class="form-select" name="type"><option value="earning">Gain</option><option value="deduction">Retenue</option><option value="tax">Taxe</option><option value="contribution">Cotisation</option></select></div>
                <div class="payroll-settings-field is-wide"><label class="form-label">Mode de calcul</label><div class="payroll-calculation-choice"><label><input type="radio" name="calculation_type" value="fixed" checked data-payroll-calculation><span><b>Montant fixe</b><small>Valeur monétaire constante</small></span></label><label><input type="radio" name="calculation_type" value="percentage" data-payroll-calculation><span><b>Pourcentage</b><small>Taux appliqué à une base</small></span></label></div></div>
                <div class="payroll-settings-field" data-payroll-amount-field><label class="form-label">Montant par défaut</label><div class="payroll-input-affix"><input class="form-control" type="number" min="0" step="0.01" name="default_amount" placeholder="0,00"><span>USD</span></div></div>
                <div class="payroll-settings-field" data-payroll-rate-field hidden><label class="form-label">Taux par défaut</label><div class="payroll-input-affix"><input class="form-control" type="number" min="0" step="0.0001" name="default_rate" placeholder="0,0000"><span>%</span></div></div>
                <label class="payroll-settings-switch is-wide"><input type="checkbox" name="taxable" value="1"><span></span><div><strong>Soumettre cette rubrique à l’IPR</strong><small>Sa valeur sera intégrée dans la base imposable professionnelle.</small></div></label>
            </div>
            <div class="payroll-settings-form-footer"><span><?= icon('shield') ?> Enregistrement sécurisé et traçable</span><button class="btn btn-primary" type="submit" data-submit-label><?= icon('plus') ?><span>Ajouter la rubrique</span></button></div>
        </form>

        <div class="payroll-settings-reference">
            <div class="payroll-settings-reference-head"><div><span class="dashboard-section-kicker">Référentiel actuel</span><h2>Rubriques configurées</h2><p>Éléments disponibles pour la génération des bulletins.</p></div></div>
            <div class="payroll-settings-table-toolbar">
                <div class="payroll-settings-search"><?= icon('search') ?><input type="search" data-settings-search="items" placeholder="Rechercher par nom, code, nature…" aria-label="Rechercher une rubrique"><kbd>⌘ K</kbd></div>
                <label class="payroll-settings-page-size"><span>Afficher</span><select class="form-select" data-settings-page-size="items"><option value="5" selected>5 lignes</option><option value="10">10 lignes</option><option value="25">25 lignes</option></select></label>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table payroll-settings-table" data-settings-table="items" data-no-datatable>
                    <thead><tr><th>Rubrique</th><th>Nature</th><th>Calcul</th><th class="text-end">Valeur</th><th>Fiscalité</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><div class="payroll-reference-name"><span><?= e(strtoupper(substr($item['name'], 0, 2))) ?></span><div><strong><?= e($item['name']) ?></strong><small><?= e($item['code']) ?><?= $isSuperAdmin ? ' · ' . e($item['company_name'] ?? '-') : '' ?></small></div></div></td>
                            <td><span class="payroll-type-badge is-<?= e($typeTones[$item['type']] ?? 'blue') ?>"><?= e($typeLabels[$item['type']] ?? $item['type']) ?></span></td>
                            <td><?= e(($item['calculation_type'] ?? 'fixed') === 'percentage' ? 'Pourcentage' : 'Montant fixe') ?></td>
                            <td class="text-end"><strong class="payroll-value"><?= e(($item['calculation_type'] ?? 'fixed') === 'percentage' ? $rate($item['default_rate']) : $money($item['default_amount']) . ' USD') ?></strong></td>
                            <td><?= !empty($item['taxable']) ? '<span class="payroll-active-badge"><i></i>Taxable</span>' : '<span class="text-secondary">Non taxable</span>' ?></td>
                            <td class="text-end"><div class="payroll-row-actions"><button class="btn btn-icon" type="button" data-payroll-setting-view data-type="item" data-id="<?= e((string) $item['id']) ?>" title="Voir les détails"><?= icon('file') ?></button><button class="btn btn-icon" type="button" data-payroll-setting-edit data-type="item" data-id="<?= e((string) $item['id']) ?>" title="Modifier"><?= icon('settings') ?></button><button class="btn btn-icon btn-outline-danger" type="button" data-payroll-setting-delete data-type="item" data-id="<?= e((string) $item['id']) ?>" data-name="<?= e($item['name']) ?>" title="Supprimer"><?= icon('x') ?></button></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="payroll-settings-table-footer"><span data-settings-info="items">Chargement…</span><nav data-settings-pagination="items" aria-label="Pagination des rubriques"></nav></div>
        </div>
    </section>

    <section class="payroll-settings-panel" data-payroll-settings-panel="taxes" hidden>
        <form class="payroll-settings-form" method="post" action="<?= e(url('/payroll/taxes/store')) ?>" data-payroll-form>
            <?= csrf_field() ?>
            <div class="payroll-settings-form-head"><span class="payroll-settings-form-icon is-indigo"><?= icon('chart') ?></span><div><span class="dashboard-section-kicker">Fiscalité professionnelle</span><h2>Ajouter une tranche IPR</h2><p>Définissez les bornes et le taux progressif appliqué à la base imposable.</p></div></div>
            <div class="alert alert-danger d-none" data-form-error></div>
            <?php if ($isSuperAdmin): ?><div class="payroll-settings-field is-wide"><label class="form-label">Entreprise concernée <em>Obligatoire</em></label><select class="form-select" name="company_id" data-payroll-company required><?php $companyOptions($companies, $defaultCompanyId); ?></select></div><?php else: ?><input type="hidden" name="company_id" value="<?= e((string) $defaultCompanyId) ?>"><?php endif; ?>
            <div class="payroll-settings-fields">
                <div class="payroll-settings-field is-wide"><label class="form-label">Nom de la tranche <em>Obligatoire</em></label><input class="form-control" name="name" placeholder="Ex. Tranche 1 — revenus initiaux" required><small>Un libellé distinct facilite les contrôles et les audits.</small></div>
                <div class="payroll-settings-field"><label class="form-label">Code fiscal</label><input class="form-control text-uppercase" name="tax_code" value="IPR" required></div>
                <div class="payroll-settings-field"><label class="form-label">Taux applicable</label><div class="payroll-input-affix"><input class="form-control" type="number" min="0" max="100" step="0.0001" name="rate" placeholder="0,0000"><span>%</span></div></div>
                <div class="payroll-settings-field"><label class="form-label">Borne minimale</label><div class="payroll-input-affix"><input class="form-control" type="number" min="0" step="0.01" name="threshold_min" placeholder="0,00"><span>USD</span></div></div>
                <div class="payroll-settings-field"><label class="form-label">Borne maximale</label><div class="payroll-input-affix"><input class="form-control" type="number" min="0" step="0.01" name="threshold_max" placeholder="Sans plafond"><span>USD</span></div><small>Laissez vide pour une tranche sans plafond supérieur.</small></div>
                <label class="payroll-settings-switch is-wide"><input type="checkbox" name="is_active" value="1" checked><span></span><div><strong>Activer immédiatement cette tranche</strong><small>Elle sera utilisée lors du prochain calcul de paie.</small></div></label>
            </div>
            <div class="payroll-settings-form-footer"><span><?= icon('alert') ?> Vérifiez l’absence de chevauchement entre les tranches</span><button class="btn btn-primary" type="submit" data-submit-label><?= icon('plus') ?><span>Ajouter la tranche</span></button></div>
        </form>

        <div class="payroll-settings-reference">
            <div class="payroll-settings-reference-head"><div><span class="dashboard-section-kicker">Barème progressif</span><h2>Tranches fiscales IPR</h2><p>Lecture ordonnée des seuils utilisés par le moteur de calcul.</p></div></div>
            <div class="payroll-settings-table-toolbar">
                <div class="payroll-settings-search"><?= icon('search') ?><input type="search" data-settings-search="taxes" placeholder="Rechercher une tranche, un code…" aria-label="Rechercher une tranche IPR"><kbd>⌘ K</kbd></div>
                <label class="payroll-settings-page-size"><span>Afficher</span><select class="form-select" data-settings-page-size="taxes"><option value="5" selected>5 lignes</option><option value="10">10 lignes</option><option value="25">25 lignes</option></select></label>
            </div>
            <div class="table-responsive"><table class="table table-vcenter card-table payroll-settings-table" data-settings-table="taxes" data-no-datatable><thead><tr><th>Tranche</th><th>Base imposable</th><th class="text-end">Taux</th><th>État</th><th class="text-end">Actions</th></tr></thead><tbody>
                <?php foreach ($taxSettings as $tax): ?><tr><td><strong><?= e($tax['name']) ?></strong><small class="d-block text-secondary"><?= e($tax['tax_code']) ?><?= $isSuperAdmin ? ' · ' . e($tax['company_name'] ?? '-') : '' ?></small></td><td><span class="payroll-threshold"><?= e($money($tax['threshold_min'])) ?></span><i>→</i><span class="payroll-threshold"><?= e($tax['threshold_max'] === null ? 'Sans plafond' : $money($tax['threshold_max'])) ?></span></td><td class="text-end"><strong class="payroll-value"><?= e($rate($tax['rate'])) ?></strong></td><td><?= !empty($tax['is_active']) ? '<span class="payroll-active-badge"><i></i>Active</span>' : '<span class="payroll-inactive-badge">Inactive</span>' ?></td><td class="text-end"><div class="payroll-row-actions"><button class="btn btn-icon" type="button" data-payroll-setting-view data-type="tax" data-id="<?= e((string) $tax['id']) ?>" title="Voir les détails"><?= icon('file') ?></button><button class="btn btn-icon" type="button" data-payroll-setting-edit data-type="tax" data-id="<?= e((string) $tax['id']) ?>" title="Modifier"><?= icon('settings') ?></button><button class="btn btn-icon btn-outline-danger" type="button" data-payroll-setting-delete data-type="tax" data-id="<?= e((string) $tax['id']) ?>" data-name="<?= e($tax['name']) ?>" title="Supprimer"><?= icon('x') ?></button></div></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <div class="payroll-settings-table-footer"><span data-settings-info="taxes">Chargement…</span><nav data-settings-pagination="taxes" aria-label="Pagination des tranches fiscales"></nav></div>
        </div>
    </section>

    <section class="payroll-settings-panel" data-payroll-settings-panel="contributions" hidden>
        <form class="payroll-settings-form" method="post" action="<?= e(url('/payroll/contributions/store')) ?>" data-payroll-form>
            <?= csrf_field() ?>
            <div class="payroll-settings-form-head"><span class="payroll-settings-form-icon is-green"><?= icon('shield') ?></span><div><span class="dashboard-section-kicker">Protection sociale</span><h2>Ajouter une cotisation</h2><p>Renseignez les parts salariale et patronale ainsi que le plafond éventuel.</p></div></div>
            <div class="alert alert-danger d-none" data-form-error></div>
            <?php if ($isSuperAdmin): ?><div class="payroll-settings-field is-wide"><label class="form-label">Entreprise concernée <em>Obligatoire</em></label><select class="form-select" name="company_id" data-payroll-company required><?php $companyOptions($companies, $defaultCompanyId); ?></select></div><?php else: ?><input type="hidden" name="company_id" value="<?= e((string) $defaultCompanyId) ?>"><?php endif; ?>
            <div class="payroll-settings-fields">
                <div class="payroll-settings-field is-wide"><label class="form-label">Organisme ou cotisation <em>Obligatoire</em></label><input class="form-control" name="name" placeholder="Ex. Caisse Nationale de Sécurité Sociale" required></div>
                <div class="payroll-settings-field"><label class="form-label">Code</label><input class="form-control text-uppercase" name="contribution_code" placeholder="CNSS" required></div>
                <div class="payroll-settings-field"><label class="form-label">Part salariale</label><div class="payroll-input-affix"><input class="form-control" type="number" min="0" max="100" step="0.0001" name="employee_rate" placeholder="0,0000"><span>%</span></div></div>
                <div class="payroll-settings-field"><label class="form-label">Part patronale</label><div class="payroll-input-affix"><input class="form-control" type="number" min="0" max="100" step="0.0001" name="employer_rate" placeholder="0,0000"><span>%</span></div></div>
                <div class="payroll-settings-field"><label class="form-label">Plafond de cotisation</label><div class="payroll-input-affix"><input class="form-control" type="number" min="0" step="0.01" name="ceiling_amount" placeholder="Sans plafond"><span>USD</span></div></div>
                <label class="payroll-settings-switch is-wide"><input type="checkbox" name="is_active" value="1" checked><span></span><div><strong>Activer immédiatement cette cotisation</strong><small>Les deux parts seront intégrées aux prochains calculs.</small></div></label>
            </div>
            <div class="payroll-settings-form-footer"><span><?= icon('shield') ?> Paramètre social enregistré dans le journal d’audit</span><button class="btn btn-primary" type="submit" data-submit-label><?= icon('plus') ?><span>Ajouter la cotisation</span></button></div>
        </form>

        <div class="payroll-settings-reference">
            <div class="payroll-settings-reference-head"><div><span class="dashboard-section-kicker">Référentiel social</span><h2>Cotisations configurées</h2><p>Répartition des charges entre salarié et employeur.</p></div></div>
            <div class="payroll-settings-table-toolbar">
                <div class="payroll-settings-search"><?= icon('search') ?><input type="search" data-settings-search="contributions" placeholder="Rechercher une cotisation, un organisme…" aria-label="Rechercher une cotisation"><kbd>⌘ K</kbd></div>
                <label class="payroll-settings-page-size"><span>Afficher</span><select class="form-select" data-settings-page-size="contributions"><option value="5" selected>5 lignes</option><option value="10">10 lignes</option><option value="25">25 lignes</option></select></label>
            </div>
            <div class="table-responsive"><table class="table table-vcenter card-table payroll-settings-table" data-settings-table="contributions" data-no-datatable><thead><tr><th>Cotisation</th><th class="text-end">Salariale</th><th class="text-end">Patronale</th><th class="text-end">Plafond</th><th>État</th><th class="text-end">Actions</th></tr></thead><tbody>
                <?php foreach ($contributionSettings as $contribution): ?><tr><td><strong><?= e($contribution['name']) ?></strong><small class="d-block text-secondary"><?= e($contribution['contribution_code']) ?><?= $isSuperAdmin ? ' · ' . e($contribution['company_name'] ?? '-') : '' ?></small></td><td class="text-end"><strong class="payroll-value"><?= e($rate($contribution['employee_rate'])) ?></strong></td><td class="text-end"><strong class="payroll-value"><?= e($rate($contribution['employer_rate'])) ?></strong></td><td class="text-end"><?= e($contribution['ceiling_amount'] === null ? 'Sans plafond' : $money($contribution['ceiling_amount']) . ' USD') ?></td><td><?= !empty($contribution['is_active']) ? '<span class="payroll-active-badge"><i></i>Active</span>' : '<span class="payroll-inactive-badge">Inactive</span>' ?></td><td class="text-end"><div class="payroll-row-actions"><button class="btn btn-icon" type="button" data-payroll-setting-view data-type="contribution" data-id="<?= e((string) $contribution['id']) ?>" title="Voir les détails"><?= icon('file') ?></button><button class="btn btn-icon" type="button" data-payroll-setting-edit data-type="contribution" data-id="<?= e((string) $contribution['id']) ?>" title="Modifier"><?= icon('settings') ?></button><button class="btn btn-icon btn-outline-danger" type="button" data-payroll-setting-delete data-type="contribution" data-id="<?= e((string) $contribution['id']) ?>" data-name="<?= e($contribution['name']) ?>" title="Supprimer"><?= icon('x') ?></button></div></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <div class="payroll-settings-table-footer"><span data-settings-info="contributions">Chargement…</span><nav data-settings-pagination="contributions" aria-label="Pagination des cotisations"></nav></div>
        </div>
    </section>
</div>

<div class="user-modal payroll-setting-modal" data-payroll-setting-modal aria-hidden="true">
    <div class="user-modal-backdrop" data-payroll-setting-close></div>
    <section class="user-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="payroll-setting-modal-title">
        <div class="user-modal-header">
            <div class="user-modal-heading"><span class="user-modal-heading-icon"><?= icon('wallet') ?></span><div><span class="dashboard-section-kicker">Référentiel de paie</span><h2 id="payroll-setting-modal-title" data-payroll-setting-title>Détails du paramètre</h2><p data-payroll-setting-subtitle>Consultez les informations enregistrées.</p></div></div>
            <button class="btn btn-icon" type="button" data-payroll-setting-close><?= icon('x') ?></button>
        </div>
        <div class="payroll-setting-loader" data-payroll-setting-loader><span class="attendance-button-loader"></span><strong>Chargement du paramètre…</strong></div>
        <div class="user-modal-body payroll-setting-modal-body" data-payroll-setting-content hidden>
            <div class="alert alert-danger d-none" data-payroll-setting-error></div>
            <div class="payroll-setting-details" data-payroll-setting-details></div>
            <form data-payroll-setting-form hidden>
                <?= csrf_field() ?>
                <input type="hidden" name="id">
                <input type="hidden" name="setting_type">
                <?php if ($isSuperAdmin): ?>
                    <div class="payroll-settings-field"><label class="form-label">Entreprise</label><select class="form-select" name="company_id"><?php $companyOptions($companies, $defaultCompanyId); ?></select></div>
                <?php endif; ?>
                <div class="payroll-setting-edit-fields" data-payroll-edit-fields="item">
                    <div class="payroll-settings-field is-wide"><label class="form-label">Libellé</label><input class="form-control" name="name" required></div>
                    <div class="payroll-settings-field"><label class="form-label">Code</label><input class="form-control text-uppercase" name="code" required></div>
                    <div class="payroll-settings-field"><label class="form-label">Nature</label><select class="form-select" name="type"><option value="earning">Gain</option><option value="deduction">Retenue</option><option value="tax">Taxe</option><option value="contribution">Cotisation</option></select></div>
                    <div class="payroll-settings-field"><label class="form-label">Mode de calcul</label><select class="form-select" name="calculation_type"><option value="fixed">Montant fixe</option><option value="percentage">Pourcentage</option></select></div>
                    <div class="payroll-settings-field"><label class="form-label">Montant par défaut</label><input class="form-control" type="number" min="0" step="0.01" name="default_amount"></div>
                    <div class="payroll-settings-field"><label class="form-label">Taux par défaut (%)</label><input class="form-control" type="number" min="0" step="0.0001" name="default_rate"></div>
                    <label class="payroll-settings-switch is-wide"><input type="checkbox" name="taxable" value="1"><span></span><div><strong>Soumise à l’IPR</strong><small>Inclure cette rubrique dans la base imposable.</small></div></label>
                </div>
                <div class="payroll-setting-edit-fields" data-payroll-edit-fields="tax" hidden>
                    <div class="payroll-settings-field is-wide"><label class="form-label">Nom de la tranche</label><input class="form-control" name="name" required></div>
                    <div class="payroll-settings-field"><label class="form-label">Code fiscal</label><input class="form-control text-uppercase" name="tax_code" required></div>
                    <div class="payroll-settings-field"><label class="form-label">Taux (%)</label><input class="form-control" type="number" min="0" max="100" step="0.0001" name="rate"></div>
                    <div class="payroll-settings-field"><label class="form-label">Borne minimale</label><input class="form-control" type="number" min="0" step="0.01" name="threshold_min"></div>
                    <div class="payroll-settings-field"><label class="form-label">Borne maximale</label><input class="form-control" type="number" min="0" step="0.01" name="threshold_max"></div>
                    <label class="payroll-settings-switch is-wide"><input type="checkbox" name="is_active" value="1"><span></span><div><strong>Tranche active</strong><small>Utiliser cette tranche dans les prochains calculs.</small></div></label>
                </div>
                <div class="payroll-setting-edit-fields" data-payroll-edit-fields="contribution" hidden>
                    <div class="payroll-settings-field is-wide"><label class="form-label">Nom de la cotisation</label><input class="form-control" name="name" required></div>
                    <div class="payroll-settings-field"><label class="form-label">Code</label><input class="form-control text-uppercase" name="contribution_code" required></div>
                    <div class="payroll-settings-field"><label class="form-label">Part salariale (%)</label><input class="form-control" type="number" min="0" max="100" step="0.0001" name="employee_rate"></div>
                    <div class="payroll-settings-field"><label class="form-label">Part patronale (%)</label><input class="form-control" type="number" min="0" max="100" step="0.0001" name="employer_rate"></div>
                    <div class="payroll-settings-field"><label class="form-label">Plafond</label><input class="form-control" type="number" min="0" step="0.01" name="ceiling_amount"></div>
                    <label class="payroll-settings-switch is-wide"><input type="checkbox" name="is_active" value="1"><span></span><div><strong>Cotisation active</strong><small>Appliquer cette cotisation aux prochains calculs.</small></div></label>
                </div>
            </form>
        </div>
        <div class="user-modal-footer" data-payroll-setting-footer hidden>
            <div class="user-modal-footer-note"><?= icon('shield') ?><span>Chaque modification est inscrite dans le journal d’audit.</span></div>
            <div class="user-modal-footer-actions"><button class="btn btn-outline" type="button" data-payroll-setting-close>Fermer</button><button class="btn btn-outline" type="button" data-payroll-setting-switch-edit><?= icon('settings') ?><span>Modifier</span></button><button class="btn btn-primary" type="button" data-payroll-setting-save hidden><?= icon('check') ?><span>Enregistrer</span></button></div>
        </div>
    </section>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
window.ELLIOT_PAYROLL_SETTING_URLS = {
    detail: '<?= e(url('/payroll/settings/detail')) ?>',
    update: '<?= e(url('/payroll/settings/update')) ?>',
    delete: '<?= e(url('/payroll/settings/delete')) ?>'
};
</script>
<script src="<?= e(asset('js/payroll.js')) ?>"></script>
