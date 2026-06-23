<?php
$companyStats = $companyStats ?? [];
$hrStats = $hrStats ?? [];
$notifications = $notifications ?? [];
$attendanceBreakdown = $attendanceBreakdown ?? [];
$leaveStatusBreakdown = $leaveStatusBreakdown ?? [];
$contractStatusBreakdown = $contractStatusBreakdown ?? [];
$companyStatusBreakdown = $companyStatusBreakdown ?? [];

$number = static function ($value): string {
    return number_format((float) $value, 0, ',', ' ');
};

$money = static function ($value): string {
    return number_format((float) $value, 2, ',', ' ') . ' USD';
};

$percentOf = static function ($value, $max): int {
    $max = max(1, (float) $max);
    return (int) max(3, min(100, round(((float) $value / $max) * 100)));
};

$sumTotals = static function (array $items): int {
    $total = 0;
    foreach ($items as $item) {
        $total += (int) ($item['total'] ?? 0);
    }

    return $total;
};

$maxMain = max(
    1,
    (int) ($companyStats['total_companies'] ?? 0),
    (int) ($hrStats['total_employees'] ?? 0),
    (int) ($hrStats['active_contracts'] ?? 0),
    (int) ($hrStats['expiring_contracts'] ?? 0),
    (int) ($hrStats['pending_leave_requests'] ?? 0),
    (int) ($hrStats['present_today'] ?? 0),
    (int) ($hrStats['absent_today'] ?? 0)
);

$attendanceTotal = max(1, (int) ($hrStats['present_today'] ?? 0) + (int) ($hrStats['absent_today'] ?? 0));
$presenceRate = round(((int) ($hrStats['present_today'] ?? 0) / $attendanceTotal) * 100);
$riskTotal = (int) ($hrStats['expiring_contracts'] ?? 0) + (int) ($hrStats['pending_leave_requests'] ?? 0) + (int) ($hrStats['absent_today'] ?? 0);

$metricCards = [
    [
        'key' => 'companies',
        'label' => 'Entreprises',
        'value' => $number($companyStats['total_companies'] ?? 0),
        'detail' => !empty($isSuperAdmin) ? 'Portefeuille SaaS' : 'Votre organisation',
        'icon' => 'building',
        'tone' => 'blue',
        'progress' => $percentOf($companyStats['total_companies'] ?? 0, $maxMain),
    ],
    [
        'key' => 'employees',
        'label' => 'Employes',
        'value' => $number($hrStats['total_employees'] ?? 0),
        'detail' => 'Effectif actif',
        'icon' => 'users',
        'tone' => 'green',
        'progress' => $percentOf($hrStats['total_employees'] ?? 0, $maxMain),
    ],
    [
        'key' => 'contracts_active',
        'label' => 'Contrats actifs',
        'value' => $number($hrStats['active_contracts'] ?? 0),
        'detail' => 'En cours',
        'icon' => 'file',
        'tone' => 'indigo',
        'progress' => $percentOf($hrStats['active_contracts'] ?? 0, $maxMain),
    ],
    [
        'key' => 'contracts_risk',
        'label' => 'Contrats a risque',
        'value' => $number($hrStats['expiring_contracts'] ?? 0),
        'detail' => 'Expiration sous 30 jours',
        'icon' => 'alert',
        'tone' => 'red',
        'progress' => $percentOf($hrStats['expiring_contracts'] ?? 0, $maxMain),
    ],
    [
        'key' => 'leaves_pending',
        'label' => 'Conges en attente',
        'value' => $number($hrStats['pending_leave_requests'] ?? 0),
        'detail' => 'A valider',
        'icon' => 'calendar',
        'tone' => 'orange',
        'progress' => $percentOf($hrStats['pending_leave_requests'] ?? 0, $maxMain),
    ],
    [
        'key' => 'monthly_payroll',
        'label' => 'Masse salariale',
        'value' => $money($hrStats['monthly_payroll'] ?? 0),
        'detail' => 'Mois courant',
        'icon' => 'file',
        'tone' => 'teal',
        'progress' => 68,
        'compact' => true,
    ],
    [
        'key' => 'attendance_present',
        'label' => 'Presences',
        'value' => $number($hrStats['present_today'] ?? 0),
        'detail' => 'Aujourd hui',
        'icon' => 'check',
        'tone' => 'green',
        'progress' => $percentOf($hrStats['present_today'] ?? 0, $maxMain),
    ],
    [
        'key' => 'attendance_absent',
        'label' => 'Absences',
        'value' => $number($hrStats['absent_today'] ?? 0),
        'detail' => 'Aujourd hui',
        'icon' => 'alert',
        'tone' => 'red',
        'progress' => $percentOf($hrStats['absent_today'] ?? 0, $maxMain),
    ],
];

$bars = static function (array $items, string $emptyLabel, string $tone = 'blue') use ($number, $percentOf): void {
    if ($items === []) {
        echo '<div class="dashboard-empty"><span>' . e($emptyLabel) . '</span></div>';
        return;
    }

    $max = 1;
    foreach ($items as $item) {
        $max = max($max, (int) ($item['total'] ?? 0));
    }

    echo '<div class="dashboard-bars">';
    foreach ($items as $item) {
        $label = $item['label'] ?? 'Non defini';
        $total = (int) ($item['total'] ?? 0);
        echo '<div class="dashboard-bar-row">';
        echo '<div class="dashboard-bar-meta">';
        echo '<span>' . e(ucfirst(str_replace('_', ' ', (string) $label))) . '</span>';
        echo '<strong>' . e($number($total)) . '</strong>';
        echo '</div>';
        echo '<div class="dashboard-bar-track"><span class="tone-' . e($tone) . '" style="width: ' . e((string) $percentOf($total, $max)) . '%"></span></div>';
        echo '</div>';
    }
    echo '</div>';
};
?>

<div class="dashboard-shell erp-dashboard">
    <div class="dashboard-hero erp-page-header">
        <div>
            <div class="dashboard-eyebrow"><?= e($scopeLabel ?? 'Vue RH') ?></div>
            <h1 class="page-title">Centre de controle RH</h1>
            <p>Vue operationnelle des effectifs, contrats, presences, conges et priorites a traiter.</p>
        </div>
        <div class="dashboard-hero-actions">
            <span class="dashboard-status">
                <span></span>
                Donnees synchronisees
            </span>
            <a class="btn btn-primary" href="<?= e(url('/employees/create')) ?>"><?= icon('users') ?><span>Nouvel employe</span></a>
        </div>
    </div>

    <div class="dashboard-pulse erp-summary-strip">
        <div class="dashboard-pulse-item dashboard-clickable" role="button" tabindex="0" data-dashboard-detail="priorities">
            <span>Priorites</span>
            <strong><?= e($number($riskTotal)) ?></strong>
            <small>Points RH a traiter</small>
        </div>
        <div class="dashboard-pulse-item dashboard-clickable" role="button" tabindex="0" data-dashboard-detail="presence_rate">
            <span>Taux de presence</span>
            <strong><?= e((string) $presenceRate) ?>%</strong>
            <small><?= e($number($hrStats['present_today'] ?? 0)) ?> presents sur <?= e($number($attendanceTotal)) ?></small>
        </div>
        <div class="dashboard-pulse-item dashboard-clickable" role="button" tabindex="0" data-dashboard-detail="notifications">
            <span>Notifications</span>
            <strong><?= e($number(count($notifications))) ?></strong>
            <small>Messages recents</small>
        </div>
        <?php if (!empty($isSuperAdmin)): ?>
            <div class="dashboard-pulse-item dashboard-clickable" role="button" tabindex="0" data-dashboard-detail="subscriptions">
                <span>Abonnements</span>
                <strong><?= e($number($companyStats['active_subscriptions'] ?? 0)) ?></strong>
                <small>Plans actifs ou trial</small>
            </div>
        <?php endif; ?>
    </div>

    <div class="erp-section-title">
        <div>
            <span class="dashboard-section-kicker">Indicateurs</span>
            <h2>Suivi executif</h2>
        </div>
        <div class="erp-view-tools">
            <a href="<?= e(url('/attendance')) ?>">Presences</a>
            <a href="<?= e(url('/payroll')) ?>">Paie</a>
            <a href="<?= e(url('/leaves/approval')) ?>">Validations</a>
        </div>
    </div>

    <div class="dashboard-metrics erp-kpi-grid">
        <?php foreach ($metricCards as $card): ?>
            <div class="card metric-card metric-card-modern erp-kpi-card dashboard-clickable" role="button" tabindex="0" data-dashboard-detail="<?= e($card['key']) ?>">
                <div class="metric-card-top">
                    <span class="metric-icon tone-<?= e($card['tone']) ?>"><?= icon($card['icon']) ?></span>
                    <span class="metric-chip"><?= e($card['detail']) ?></span>
                </div>
                <div class="metric-card-main">
                    <div class="metric-value <?= !empty($card['compact']) ? 'metric-value-compact' : '' ?>"><?= e($card['value']) ?></div>
                    <div class="metric-label"><?= e($card['label']) ?></div>
                </div>
                <div class="dashboard-bar-track">
                    <span class="tone-<?= e($card['tone']) ?>" style="width: <?= e((string) $card['progress']) ?>%"></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($isSuperAdmin)): ?>
        <div class="dashboard-saas erp-portlet">
            <div>
                <span class="dashboard-section-kicker">SaaS</span>
                <h2>Statistiques globales</h2>
            </div>
            <div class="dashboard-saas-grid">
                <div>
                    <span>Entreprises actives</span>
                    <strong><?= e($number($companyStats['active_companies'] ?? 0)) ?></strong>
                </div>
                <div>
                    <span>Utilisateurs</span>
                    <strong><?= e($number($companyStats['total_users'] ?? 0)) ?></strong>
                </div>
                <div>
                    <span>Abonnements actifs</span>
                    <strong><?= e($number($companyStats['active_subscriptions'] ?? 0)) ?></strong>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid erp-portlet-grid">
        <div class="card dashboard-panel erp-portlet dashboard-clickable" role="button" tabindex="0" data-dashboard-detail="contracts_status">
            <div class="card-header">
                <div>
                    <span class="dashboard-section-kicker">Contrats</span>
                    <h2 class="card-title">Repartition par statut</h2>
                </div>
            </div>
            <?php $bars($contractStatusBreakdown, 'Aucun contrat enregistre', 'indigo'); ?>
        </div>

        <div class="card dashboard-panel erp-portlet dashboard-clickable" role="button" tabindex="0" data-dashboard-detail="leaves_status">
            <div class="card-header">
                <div>
                    <span class="dashboard-section-kicker">Conges</span>
                    <h2 class="card-title">Demandes par statut</h2>
                </div>
            </div>
            <?php $bars($leaveStatusBreakdown, 'Aucune demande de conge', 'orange'); ?>
        </div>

        <div class="card dashboard-panel erp-portlet dashboard-clickable" role="button" tabindex="0" data-dashboard-detail="attendance_today">
            <div class="card-header">
                <div>
                    <span class="dashboard-section-kicker">Aujourd hui</span>
                    <h2 class="card-title">Presence terrain</h2>
                </div>
                <span class="dashboard-ring" style="--value: <?= e((string) $presenceRate) ?>;">
                    <?= e((string) $presenceRate) ?>%
                </span>
            </div>
            <?php $bars($attendanceBreakdown, 'Aucune presence enregistree aujourd hui', 'green'); ?>
        </div>
    </div>

    <div class="dashboard-lower-grid erp-lower-grid">
        <div class="card dashboard-panel erp-portlet dashboard-clickable" role="button" tabindex="0" data-dashboard-detail="notifications">
            <div class="card-header">
                <div>
                    <span class="dashboard-section-kicker">Alertes</span>
                    <h2 class="card-title">Notifications recentes</h2>
                </div>
                <span class="badge bg-blue-lt"><?= e($number(count($notifications))) ?></span>
            </div>
            <div class="dashboard-notifications">
                <?php if ($notifications === []): ?>
                    <div class="dashboard-empty"><span>Aucune notification recente.</span></div>
                <?php endif; ?>

                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $type = $notification['type'] ?? 'info';
                    $tone = $type === 'danger' ? 'red' : ($type === 'warning' ? 'orange' : ($type === 'success' ? 'green' : 'blue'));
                    ?>
                    <?php $notificationDetail = !empty($notification['id']) ? 'notification_' . (string) $notification['id'] : 'notifications'; ?>
                    <div class="dashboard-notification dashboard-clickable" role="button" tabindex="0" data-dashboard-detail="<?= e($notificationDetail) ?>">
                        <span class="notification-dot tone-<?= e($tone) ?>"></span>
                        <div>
                            <div class="notification-title">
                                <strong><?= e($notification['title'] ?? '') ?></strong>
                                <span><?= e($type) ?></span>
                            </div>
                            <p><?= e($notification['message'] ?? '') ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($isSuperAdmin)): ?>
            <div class="card dashboard-panel erp-portlet dashboard-clickable" role="button" tabindex="0" data-dashboard-detail="companies_status">
                <div class="card-header">
                    <div>
                        <span class="dashboard-section-kicker">Portefeuille</span>
                        <h2 class="card-title">Entreprises par statut</h2>
                    </div>
                </div>
                <?php $bars($companyStatusBreakdown, 'Aucune entreprise enregistree', 'blue'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="dashboard-detail-modal" data-dashboard-modal hidden>
    <div class="dashboard-detail-backdrop" data-dashboard-close></div>
    <section class="dashboard-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="dashboard-detail-title">
        <div class="dashboard-detail-header">
            <div>
                <span class="dashboard-section-kicker">Rapport detaille</span>
                <h2 id="dashboard-detail-title">Details</h2>
                <p data-dashboard-subtitle>Chargement des donnees...</p>
            </div>
            <button class="btn btn-icon" type="button" data-dashboard-close aria-label="Fermer"><?= icon('x') ?></button>
        </div>
        <div class="dashboard-detail-toolbar">
            <div class="dashboard-detail-summary" data-dashboard-summary></div>
            <label class="dashboard-detail-search">
                <?= icon('search') ?>
                <input type="search" data-dashboard-search placeholder="Filtrer les lignes">
            </label>
            <div class="dashboard-detail-actions">
                <a class="btn btn-outline-primary" href="#" target="_blank" rel="noopener" data-dashboard-export-pdf><?= icon('file') ?><span>PDF</span></a>
                <a class="btn btn-outline-success" href="#" data-dashboard-export-excel><?= icon('download') ?><span>Excel</span></a>
            </div>
        </div>
        <div class="dashboard-detail-table-wrap">
            <table class="dashboard-detail-table">
                <thead data-dashboard-head></thead>
                <tbody data-dashboard-body></tbody>
            </table>
            <div class="dashboard-detail-empty" data-dashboard-empty hidden>Aucune donnee disponible.</div>
        </div>
    </section>
</div>

<script>
    window.ELLIOT_DASHBOARD = {
        detailUrl: <?= json_encode(url('/dashboard/detail'), JSON_UNESCAPED_SLASHES) ?>,
        exportUrl: <?= json_encode(url('/dashboard/export'), JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<script src="<?= e(asset('js/dashboard.js') . '?v=' . (string) filemtime(BASE_PATH . '/public/js/dashboard.js')) ?>"></script>
