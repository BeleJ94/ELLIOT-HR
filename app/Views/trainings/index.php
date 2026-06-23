<?php
$dashboard = $dashboard ?? [];
$sessions = $sessions ?? [];
$courses = $courses ?? [];
$employees = $employees ?? [];
$companies = $companies ?? [];
$filters = $filters ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$statusLabels = ['planned' => 'Planifiee', 'ongoing' => 'En cours', 'completed' => 'Terminee', 'cancelled' => 'Annulee'];
$statusTones = ['planned' => 'blue', 'ongoing' => 'orange', 'completed' => 'green', 'cancelled' => 'red'];
?>

<div class="module-header module-header-rich training-hero">
    <div>
        <span class="dashboard-section-kicker">Developpement RH</span>
        <h1 class="page-title">Formations</h1>
        <p>Catalogue, sessions multi-jours, participants, presences et rapports de participation.</p>
    </div>
    <div class="module-header-actions">
        <button class="btn btn-outline" type="button" data-training-open="course"><?= icon('plus') ?><span>Catalogue</span></button>
        <button class="btn btn-primary" type="button" data-training-open="session"><?= icon('calendar') ?><span>Nouvelle session</span></button>
    </div>
</div>

<div class="attendance-summary-grid training-summary-grid">
    <article class="card metric-card metric-card-modern"><div class="metric-label">Sessions</div><div class="metric-value"><?= e((string) ($dashboard['sessions'] ?? 0)) ?></div></article>
    <article class="card metric-card metric-card-modern"><div class="metric-label">En cours</div><div class="metric-value"><?= e((string) ($dashboard['ongoing'] ?? 0)) ?></div></article>
    <article class="card metric-card metric-card-modern"><div class="metric-label">Terminees</div><div class="metric-value"><?= e((string) ($dashboard['completed'] ?? 0)) ?></div></article>
    <article class="card metric-card metric-card-modern"><div class="metric-label">Participants</div><div class="metric-value"><?= e((string) ($dashboard['participants'] ?? 0)) ?></div></article>
</div>

<form class="employee-filter-bar attendance-filter-bar" method="get" action="<?= e(url('/trainings')) ?>">
    <div class="topbar-search employee-search"><?= icon('search') ?><input type="search" data-training-search placeholder="Rechercher formation, formateur, lieu"></div>
    <select class="form-select" name="status">
        <option value="">Tous les statuts</option>
        <?php foreach ($statusLabels as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input class="form-control" type="date" name="from" value="<?= e($filters['from'] ?? '') ?>">
    <input class="form-control" type="date" name="to" value="<?= e($filters['to'] ?? '') ?>">
    <button class="btn btn-outline" type="submit"><?= icon('search') ?><span>Filtrer</span></button>
</form>

<div class="training-layout">
    <section class="card company-table-card erp-table-card training-sessions-card">
        <div class="card-header">
            <div><span class="dashboard-section-kicker">Planning</span><h2 class="card-title">Sessions de formation</h2></div>
            <span class="badge bg-blue-lt"><?= e((string) count($sessions)) ?> session(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table" id="training-sessions-table">
                <thead><tr><th>Session</th><th>Periode</th><th>Formateur</th><th>Participants</th><th>Presence moy.</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                    <?php if ($sessions === []): ?>
                        <tr><td colspan="7" class="text-secondary">Aucune session trouvee.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($sessions as $session): ?>
                        <?php $tone = $statusTones[$session['status'] ?? 'planned'] ?? 'blue'; ?>
                        <tr>
                            <td>
                                <a class="company-name" href="<?= e(url('/trainings/show?id=' . $session['id'])) ?>"><?= e($session['title']) ?></a>
                                <span class="d-block text-secondary"><?= e($session['course_title']) ?> · <?= e($session['company_name'] ?? '') ?></span>
                            </td>
                            <td><?= e($session['start_date']) ?> au <?= e($session['end_date']) ?><span class="d-block text-secondary"><?= e($session['location'] ?: '-') ?></span></td>
                            <td><?= e($session['trainer_name'] ?: '-') ?><span class="d-block text-secondary"><?= e($session['provider'] ?: '-') ?></span></td>
                            <td><strong><?= e((string) ($session['participants_count'] ?? 0)) ?></strong></td>
                            <td><?= e(number_format((float) ($session['average_attendance'] ?? 0), 1, ',', ' ')) ?>%</td>
                            <td><span class="badge bg-<?= e($tone) ?>-lt"><?= e($statusLabels[$session['status']] ?? $session['status']) ?></span></td>
                            <td><a class="btn btn-icon" href="<?= e(url('/trainings/show?id=' . $session['id'])) ?>"><?= icon('file') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <aside class="card company-profile-card training-catalog-card">
        <div class="card-header"><div><span class="dashboard-section-kicker">Catalogue</span><h2 class="card-title">Formations disponibles</h2></div></div>
        <div class="training-catalog-list">
            <?php if ($courses === []): ?>
                <div class="dashboard-empty"><span>Aucune formation au catalogue.</span></div>
            <?php endif; ?>
            <?php foreach ($courses as $course): ?>
                <article>
                    <strong><?= e($course['title']) ?></strong>
                    <span><?= e($course['category'] ?: 'General') ?> · <?= e(number_format((float) $course['default_duration_days'], 1, ',', ' ')) ?> jour(s)</span>
                    <small><?= e($course['company_name'] ?? '') ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    </aside>
</div>

<div class="user-modal training-modal" data-training-modal="course" aria-hidden="true">
    <div class="user-modal-backdrop" data-training-close></div>
    <section class="user-modal-dialog">
        <div class="user-modal-header"><div><span class="dashboard-section-kicker">Catalogue</span><h2>Nouvelle formation</h2></div><button class="btn btn-icon" type="button" data-training-close><?= icon('x') ?></button></div>
        <form method="post" action="<?= e(url('/trainings/courses/store')) ?>" data-training-form>
            <?= csrf_field() ?><div class="alert alert-danger d-none" data-form-error></div>
            <div class="row g-3">
                <?php if ($isSuperAdmin): ?><div class="col-md-6"><label class="form-label">Entreprise</label><select class="form-select" name="company_id"><?php foreach ($companies as $company): ?><option value="<?= e((string) $company['id']) ?>" <?= $defaultCompanyId === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option><?php endforeach; ?></select></div><?php else: ?><input type="hidden" name="company_id" value="<?= e((string) $defaultCompanyId) ?>"><?php endif; ?>
                <div class="col-md-6"><label class="form-label">Intitule</label><input class="form-control" name="title" placeholder="Mise a niveau chauffeur" required></div>
                <div class="col-md-4"><label class="form-label">Code</label><input class="form-control" name="code" placeholder="DRV-UP"></div>
                <div class="col-md-4"><label class="form-label">Domaine</label><input class="form-control" name="category" placeholder="Securite"></div>
                <div class="col-md-4"><label class="form-label">Duree par defaut</label><input class="form-control" type="number" step="0.5" min="0.5" name="default_duration_days" value="1"></div>
                <div class="col-12"><label class="form-label">Objectifs</label><textarea class="form-control" rows="3" name="objectives"></textarea></div>
            </div>
            <div class="user-modal-footer-actions"><button class="btn btn-outline" type="button" data-training-close>Annuler</button><button class="btn btn-primary" type="submit" data-submit-label>Enregistrer</button></div>
        </form>
    </section>
</div>

<div class="user-modal training-modal" data-training-modal="session" aria-hidden="true">
    <div class="user-modal-backdrop" data-training-close></div>
    <section class="user-modal-dialog training-session-dialog">
        <div class="user-modal-header"><div><span class="dashboard-section-kicker">Session</span><h2>Planifier une formation</h2></div><button class="btn btn-icon" type="button" data-training-close><?= icon('x') ?></button></div>
        <form method="post" action="<?= e(url('/trainings/sessions/store')) ?>" data-training-form>
            <?= csrf_field() ?><div class="alert alert-danger d-none" data-form-error></div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Formation</label><select class="form-select" name="training_course_id" required><?php foreach ($courses as $course): ?><option value="<?= e((string) $course['id']) ?>"><?= e($course['title']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Titre session</label><input class="form-control" name="title" placeholder="Mise a niveau chauffeur - Juillet 2026" required></div>
                <div class="col-md-3"><label class="form-label">Debut</label><input class="form-control" type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="col-md-3"><label class="form-label">Fin</label><input class="form-control" type="date" name="end_date" value="<?= e(date('Y-m-d')) ?>" required></div>
                <div class="col-md-3"><label class="form-label">Heure debut</label><input class="form-control" type="time" name="start_time" value="08:00"></div>
                <div class="col-md-3"><label class="form-label">Heure fin</label><input class="form-control" type="time" name="end_time" value="16:00"></div>
                <div class="col-md-4"><label class="form-label">Formateur</label><input class="form-control" name="trainer_name"></div>
                <div class="col-md-4"><label class="form-label">Prestataire</label><input class="form-control" name="provider"></div>
                <div class="col-md-4"><label class="form-label">Lieu</label><input class="form-control" name="location"></div>
                <div class="col-md-3"><label class="form-label">Seuil validation (%)</label><input class="form-control" type="number" min="0" max="100" step="1" name="min_attendance_rate" value="80"></div>
                <div class="col-md-3"><label class="form-label">Budget</label><input class="form-control" type="number" step="0.01" min="0" name="budget" value="0"></div>
                <div class="col-md-2"><label class="form-label">Devise</label><input class="form-control" name="currency" value="USD"></div>
                <div class="col-md-4"><label class="form-label">Theme journalier</label><input class="form-control" name="daily_topic" placeholder="Conduite defensive"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" rows="3" name="notes"></textarea></div>
            </div>
            <div class="user-modal-footer-actions"><button class="btn btn-outline" type="button" data-training-close>Annuler</button><button class="btn btn-primary" type="submit" data-submit-label>Creer la session</button></div>
        </form>
    </section>
</div>

<script src="<?= e(asset('js/trainings.js') . '?v=' . (string) filemtime(BASE_PATH . '/public/js/trainings.js')) ?>"></script>
