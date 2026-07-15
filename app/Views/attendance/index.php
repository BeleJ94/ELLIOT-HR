<?php
$rows = $rows ?? [];
$options = $options ?? [];
$filters = $filters ?? [];
$attendance = $attendance ?? null;
$companies = $companies ?? [];
$calendarDays = $calendarDays ?? [];
$day = $day ?? ['status' => 'open'];
$anomalies = $anomalies ?? [];
$history = $history ?? [];
$canEncode = !empty($canEncode);
$canClose = !empty($canClose);
$isSuperAdmin = !empty($isSuperAdmin);
$selectedCompanyId = (int) ($selectedCompanyId ?? 0);
$selectedDate = $filters['date'] ?? date('Y-m-d');
$month = $month ?? substr($selectedDate, 0, 7);
$dayStatus = $day['status'] ?? 'open';
$isFuture = $selectedDate > date('Y-m-d');
$isEditable = $canEncode && $dayStatus === 'open' && !$isFuture;
$monthNames = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$weekdayNames = [1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
$monthTimestamp = strtotime($month . '-01');
$selectedTimestamp = strtotime($selectedDate);
$statusLabels = [
    '' => 'Non encodé',
    'present' => 'Présent',
    'late' => 'Retard',
    'absent' => 'Absent',
    'half_day' => 'Demi-journée',
    'holiday' => 'Férié',
    'leave' => 'Congé',
];
$dayLabels = ['open' => 'Ouverte', 'closed' => 'Clôturée', 'locked' => 'Verrouillée'];
$dayTones = ['open' => 'green', 'closed' => 'blue', 'locked' => 'red'];
$summary = ['present' => 0, 'late' => 0, 'absent' => 0, 'missing' => 0, 'overtime' => 0];
foreach ($rows as $row) {
    if (empty($row['attendance_id'])) {
        $summary['missing']++;
    }
    $status = $row['computed_status'] ?? '';
    if ($status === 'late') {
        $summary['late']++;
        $summary['present']++;
    } elseif ($status === 'present') {
        $summary['present']++;
    } elseif ($status === 'absent') {
        $summary['absent']++;
    }
    $summary['overtime'] += (int) ($row['overtime_minutes'] ?? 0);
}
$firstWeekday = $calendarDays ? (int) date('N', strtotime($calendarDays[0]['date'])) : 1;
$previousMonth = date('Y-m', strtotime($month . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));
$queryBase = ['company_id' => $selectedCompanyId];
?>

<div class="attendance-workspace">
    <div class="attendance-command">
        <div>
            <span class="dashboard-section-kicker">Gestion du temps</span>
            <h1 class="page-title">Encodage des présences</h1>
            <p>Sélectionnez une journée, encodez les horaires de l’équipe et sécurisez les données par clôture.</p>
        </div>
        <div class="attendance-command-actions">
            <a class="btn btn-outline" href="<?= e(url('/attendance/report')) ?>"><?= icon('chart') ?><span>Rapport mensuel</span></a>
            <span class="attendance-day-state is-<?= e($dayStatus) ?>"><i></i><?= e($dayLabels[$dayStatus] ?? $dayStatus) ?></span>
        </div>
    </div>

    <div class="attendance-layout">
        <aside class="attendance-calendar-panel">
            <div class="attendance-calendar-head">
                <a class="btn btn-icon" href="<?= e(url('/attendance?' . http_build_query(array_merge($queryBase, ['month' => $previousMonth, 'date' => $previousMonth . '-01'])))) ?>" aria-label="Mois précédent">‹</a>
                <div><span>Calendrier</span><strong><?= e(ucfirst($monthNames[(int) date('n', $monthTimestamp)]) . ' ' . date('Y', $monthTimestamp)) ?></strong></div>
                <a class="btn btn-icon" href="<?= e(url('/attendance?' . http_build_query(array_merge($queryBase, ['month' => $nextMonth, 'date' => $nextMonth . '-01'])))) ?>" aria-label="Mois suivant">›</a>
            </div>
            <?php if ($isSuperAdmin): ?>
                <form class="attendance-company-picker" method="get" action="<?= e(url('/attendance')) ?>">
                    <input type="hidden" name="month" value="<?= e($month) ?>">
                    <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
                    <label>Entreprise</label>
                    <select class="form-select" name="company_id" onchange="this.form.submit()">
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= e((string) $company['id']) ?>" <?= $selectedCompanyId === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
            <div class="attendance-weekdays">
                <?php foreach (['L', 'M', 'M', 'J', 'V', 'S', 'D'] as $weekday): ?><span><?= e($weekday) ?></span><?php endforeach; ?>
            </div>
            <div class="attendance-calendar-grid">
                <?php for ($i = 1; $i < $firstWeekday; $i++): ?><span class="is-empty"></span><?php endfor; ?>
                <?php foreach ($calendarDays as $calendarDay): ?>
                    <?php
                    $isSelected = $calendarDay['date'] === $selectedDate;
                    $isToday = $calendarDay['date'] === date('Y-m-d');
                    $classes = ['attendance-calendar-day', 'is-' . $calendarDay['status']];
                    if ($isSelected) $classes[] = 'is-selected';
                    if ($isToday) $classes[] = 'is-today';
                    if ($calendarDay['weekday'] >= 6) $classes[] = 'is-weekend';
                    if (!$calendarDay['is_complete'] && $calendarDay['recorded_count'] > 0) $classes[] = 'is-incomplete';
                    ?>
                    <a class="<?= e(implode(' ', $classes)) ?>"
                       href="<?= e(url('/attendance?' . http_build_query(array_merge($queryBase, ['month' => $month, 'date' => $calendarDay['date']])))) ?>"
                       title="<?= e(($dayLabels[$calendarDay['status']] ?? '') . ' · ' . $calendarDay['recorded_count'] . '/' . $calendarDay['employee_count'] . ' encodés') ?>">
                        <strong><?= e((string) $calendarDay['day']) ?></strong>
                        <small><?= e((string) $calendarDay['recorded_count']) ?>/<?= e((string) $calendarDay['employee_count']) ?></small>
                        <i></i>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="attendance-calendar-legend">
                <span><i class="is-open"></i>Ouverte</span>
                <span><i class="is-closed"></i>Clôturée</span>
                <span><i class="is-locked"></i>Verrouillée</span>
                <span><i class="is-incomplete"></i>À compléter</span>
            </div>
        </aside>

        <main class="attendance-day-panel">
            <div class="attendance-day-header">
                <div class="attendance-date-block">
                    <span><?= e(ucfirst($weekdayNames[(int) date('N', $selectedTimestamp)])) ?></span>
                    <strong><?= e(date('d', $selectedTimestamp)) ?></strong>
                    <small><?= e(ucfirst($monthNames[(int) date('n', $selectedTimestamp)]) . ' ' . date('Y', $selectedTimestamp)) ?></small>
                </div>
                <div class="attendance-day-title">
                    <span class="dashboard-section-kicker">Feuille journalière</span>
                    <h2><?= e(date('d/m/Y', strtotime($selectedDate))) ?></h2>
                    <p>
                        <?php if ($isFuture): ?>Cette journée est future : l’encodage sera disponible à la date concernée.
                        <?php elseif ($dayStatus === 'open'): ?>Les données peuvent être modifiées par les responsables autorisés.
                        <?php elseif ($dayStatus === 'closed'): ?>Cette journée a été validée et clôturée.
                        <?php else: ?>Cette journée est verrouillée administrativement.<?php endif; ?>
                    </p>
                </div>
                <div class="attendance-day-controls">
                    <?php if ($isEditable): ?>
                        <button class="btn btn-outline" type="button" data-attendance-fill-standard><?= icon('clock') ?><span>Horaires standards</span></button>
                        <button class="btn btn-primary" type="button" data-attendance-save><?= icon('check') ?><span>Enregistrer</span></button>
                    <?php endif; ?>
                    <?php if ($canClose && $dayStatus === 'open' && !$isFuture): ?>
                        <button class="btn btn-outline" type="button" data-attendance-close><?= icon('lock') ?><span>Clôturer</span></button>
                    <?php endif; ?>
                    <?php if ($isSuperAdmin && $dayStatus !== 'open'): ?>
                        <button class="btn btn-primary" type="button" data-attendance-reopen><?= icon('key') ?><span>Rouvrir</span></button>
                    <?php endif; ?>
                    <?php if ($isSuperAdmin && $dayStatus !== 'locked' && !$isFuture): ?>
                        <button class="btn btn-outline-danger" type="button" data-attendance-lock><?= icon('lock') ?><span>Verrouiller</span></button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($day['status_reason'])): ?>
                <div class="attendance-status-reason"><?= icon('alert') ?><div><strong>Motif administratif</strong><span><?= e($day['status_reason']) ?></span></div></div>
            <?php endif; ?>

            <div class="attendance-summary-grid attendance-summary-modern">
                <article><span class="is-green"><?= icon('check') ?></span><div><small>Présents</small><strong><?= e((string) $summary['present']) ?></strong></div></article>
                <article><span class="is-orange"><?= icon('clock') ?></span><div><small>Retards</small><strong><?= e((string) $summary['late']) ?></strong></div></article>
                <article><span class="is-red"><?= icon('alert') ?></span><div><small>Absents</small><strong><?= e((string) $summary['absent']) ?></strong></div></article>
                <article><span class="is-blue"><?= icon('file') ?></span><div><small>Non encodés</small><strong><?= e((string) $summary['missing']) ?></strong></div></article>
            </div>

            <?php if ($anomalies !== []): ?>
                <div class="attendance-anomaly-banner">
                    <div><?= icon('alert') ?><span><strong><?= e((string) count($anomalies)) ?> anomalie(s) à corriger</strong><small>La clôture restera indisponible tant que ces éléments ne sont pas résolus.</small></span></div>
                    <button class="btn btn-sm btn-outline" type="button" data-anomaly-toggle>Afficher</button>
                </div>
                <div class="attendance-anomaly-list" data-anomaly-list hidden>
                    <?php foreach ($anomalies as $anomaly): ?><span><?= e($anomaly['message']) ?></span><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="attendance-filter-strip" method="get" action="<?= e(url('/attendance')) ?>">
                <input type="hidden" name="company_id" value="<?= e((string) $selectedCompanyId) ?>">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
                <div class="topbar-search employee-search"><?= icon('search') ?><input type="search" data-attendance-search placeholder="Rechercher un agent, matricule ou département"></div>
                <select class="form-select" name="department_id" onchange="this.form.submit()">
                    <option value="">Tous les départements</option>
                    <?php foreach ($options['departments'] ?? [] as $department): ?>
                        <option value="<?= e((string) $department['id']) ?>" <?= (int) ($filters['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <div class="alert alert-danger d-none" data-attendance-error></div>
            <div class="card company-table-card attendance-entry-card">
                <div class="attendance-table-toolbar">
                    <div><strong>Liste des agents</strong><span data-attendance-result-count><?= e((string) count($rows)) ?> agent(s)</span></div>
                    <label>Afficher
                        <select class="form-select form-select-sm" data-attendance-page-size>
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                        </select>
                        lignes
                    </label>
                </div>
                <div class="table-responsive attendance-entry-scroll">
                    <table class="table table-vcenter card-table" id="attendance-table" data-no-datatable>
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Statut</th>
                                <th>Entrée</th>
                                <th>Sortie</th>
                                <th>Temps calculé</th>
                                <th>Observation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $status = !empty($row['attendance_id']) ? ($row['status'] ?? '') : '';
                                $employeeName = trim(($row['last_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['first_name'] ?? ''));
                                ?>
                                <tr data-attendance-entry data-employee-id="<?= e((string) $row['employee_id']) ?>">
                                    <td>
                                        <div class="employee-cell">
                                            <span class="employee-avatar"><?= e(strtoupper(substr($row['first_name'] ?? 'A', 0, 1) . substr($row['last_name'] ?? '', 0, 1))) ?></span>
                                            <div><strong><?= e($employeeName) ?></strong><span class="d-block text-secondary"><?= e($row['employee_number'] ?? '-') ?> · <?= e($row['department_name'] ?? '-') ?></span></div>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-select attendance-status-select" data-attendance-status <?= !$isEditable ? 'disabled' : '' ?>>
                                            <?php foreach ($statusLabels as $value => $label): ?>
                                                <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input class="form-control attendance-time" type="time" data-attendance-in value="<?= e($row['check_in'] ? substr($row['check_in'], 0, 5) : '') ?>" <?= !$isEditable ? 'disabled' : '' ?>></td>
                                    <td><input class="form-control attendance-time" type="time" data-attendance-out value="<?= e($row['check_out'] ? substr($row['check_out'], 0, 5) : '') ?>" <?= !$isEditable ? 'disabled' : '' ?>></td>
                                    <td>
                                        <div class="attendance-computed">
                                            <span>Travail <?= e($attendance->formatMinutes((int) ($row['worked_minutes'] ?? 0))) ?></span>
                                            <small>Retard <?= e($attendance->formatMinutes((int) ($row['late_minutes'] ?? 0))) ?> · Supp. <?= e($attendance->formatMinutes((int) ($row['overtime_minutes'] ?? 0))) ?></small>
                                        </div>
                                    </td>
                                    <td><input class="form-control" data-attendance-notes value="<?= e($row['notes'] ?? '') ?>" placeholder="Motif, mission, précision…" <?= !$isEditable ? 'disabled' : '' ?>></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="attendance-table-empty" data-attendance-empty hidden><?= icon('search') ?><strong>Aucun agent trouvé</strong><span>Modifiez votre recherche ou le filtre de département.</span></div>
                <div class="attendance-table-pagination" data-attendance-pagination>
                    <span data-attendance-page-info>Page 1</span>
                    <div>
                        <button class="btn btn-sm btn-outline" type="button" data-attendance-previous>Précédent</button>
                        <button class="btn btn-sm btn-outline" type="button" data-attendance-next>Suivant</button>
                    </div>
                </div>
            </div>

            <?php if ($history !== []): ?>
                <details class="attendance-history" data-attendance-history>
                    <summary class="attendance-section-head"><div><span class="dashboard-section-kicker">Audit</span><h3>Historique de la journée</h3><small>Consultez les dernières modifications et actions administratives.</small></div><div><span class="badge bg-blue-lt"><?= e((string) count($history)) ?></span><span class="attendance-history-toggle">Afficher</span></div></summary>
                    <div class="attendance-history-list attendance-history-scroll">
                        <?php foreach ($history as $event): ?>
                            <?php
                            $actor = trim(($event['first_name'] ?? '') . ' ' . ($event['last_name'] ?? ''));
                            $subject = trim(($event['employee_last_name'] ?? '') . ' ' . ($event['employee_first_name'] ?? ''));
                            ?>
                            <article><i></i><div><strong><?= e(str_replace('_', ' ', $event['action'])) ?></strong><span><?= e($subject ?: 'Journée complète') ?> · par <?= e($actor ?: 'Système') ?></span><small><?= e(date('d/m/Y H:i', strtotime($event['created_at']))) ?><?= !empty($event['reason']) ? ' · ' . e($event['reason']) : '' ?></small></div></article>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </main>
    </div>
</div>

<div class="user-modal" data-attendance-reason-modal aria-hidden="true">
    <div class="user-modal-backdrop" data-attendance-reason-close></div>
    <section class="user-modal-dialog user-password-dialog" role="dialog" aria-modal="true">
        <div class="user-modal-header">
            <div class="user-modal-heading"><span class="user-modal-heading-icon"><?= icon('key') ?></span><div><span class="dashboard-section-kicker">Action administrative</span><h2 data-attendance-reason-title>Motif requis</h2><p>Cette action sera inscrite dans l’historique d’audit.</p></div></div>
            <button class="btn btn-icon" type="button" data-attendance-reason-close><?= icon('x') ?></button>
        </div>
        <div class="user-modal-body">
            <label class="form-label">Motif</label>
            <textarea class="form-control" rows="4" data-attendance-reason placeholder="Expliquez la raison de cette action…" required></textarea>
        </div>
        <div class="user-modal-footer">
            <div></div>
            <div class="user-modal-footer-actions"><button class="btn btn-outline" type="button" data-attendance-reason-close>Annuler</button><button class="btn btn-primary" type="button" data-attendance-reason-confirm>Confirmer</button></div>
        </div>
    </section>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
window.ELLIOT_ATTENDANCE = {
    companyId: <?= e((string) $selectedCompanyId) ?>,
    date: '<?= e($selectedDate) ?>',
    urls: {
        save: '<?= e(url('/attendance/bulk-save')) ?>',
        close: '<?= e(url('/attendance/day/close')) ?>',
        lock: '<?= e(url('/attendance/day/lock')) ?>',
        reopen: '<?= e(url('/attendance/day/reopen')) ?>'
    }
};
</script>
<script src="<?= e(asset('js/attendance.js')) ?>"></script>
