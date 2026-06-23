<?php
$session = $session ?? [];
$days = $days ?? [];
$participants = $participants ?? [];
$employees = $employees ?? [];
$attendance = $attendance ?? [];
$selectedDayId = (int) ($selectedDayId ?? 0);
$statusLabels = ['planned' => 'Planifiee', 'ongoing' => 'En cours', 'completed' => 'Terminee', 'cancelled' => 'Annulee'];
$finalLabels = ['invited' => 'Invite', 'completed' => 'Valide', 'failed' => 'Non valide', 'absent' => 'Absent', 'excused' => 'Excuse'];
$attendanceLabels = ['present' => 'Present', 'late' => 'Retard', 'absent' => 'Absent', 'excused' => 'Excuse'];
?>

<div class="module-header module-header-rich training-hero">
    <div>
        <span class="dashboard-section-kicker">Session de formation</span>
        <h1 class="page-title"><?= e($session['title']) ?></h1>
        <p><?= e($session['course_title']) ?> · <?= e($session['start_date']) ?> au <?= e($session['end_date']) ?> · <?= e($session['location'] ?: 'Lieu non defini') ?></p>
    </div>
    <div class="module-header-actions">
        <a class="btn btn-outline" href="<?= e(url('/trainings')) ?>"><?= icon('arrow-right') ?><span>Retour</span></a>
        <a class="btn btn-outline-primary" target="_blank" href="<?= e(url('/trainings/export?id=' . $session['id'] . '&format=pdf')) ?>"><?= icon('file') ?><span>PDF</span></a>
        <a class="btn btn-outline-success" href="<?= e(url('/trainings/export?id=' . $session['id'] . '&format=excel')) ?>"><?= icon('download') ?><span>Excel</span></a>
    </div>
</div>

<div class="company-kpi-grid employee-kpi-grid erp-summary-strip">
    <div class="company-kpi-card"><span>Statut</span><strong><?= e($statusLabels[$session['status']] ?? $session['status']) ?></strong></div>
    <div class="company-kpi-card"><span>Participants</span><strong><?= e((string) count($participants)) ?></strong></div>
    <div class="company-kpi-card"><span>Jours</span><strong><?= e((string) count($days)) ?></strong></div>
    <div class="company-kpi-card"><span>Seuil</span><strong><?= e(number_format((float) $session['min_attendance_rate'], 0, ',', ' ')) ?>%</strong></div>
</div>

<div class="training-session-grid">
    <section class="card company-profile-card">
        <div class="card-header"><div><span class="dashboard-section-kicker">Informations</span><h2 class="card-title">Cadre de la formation</h2></div></div>
        <div class="card-body">
            <dl class="company-definition-list">
                <div><dt>Entreprise</dt><dd><?= e($session['company_name'] ?? '-') ?></dd></div>
                <div><dt>Domaine</dt><dd><?= e($session['category'] ?: '-') ?></dd></div>
                <div><dt>Formateur</dt><dd><?= e($session['trainer_name'] ?: '-') ?></dd></div>
                <div><dt>Prestataire</dt><dd><?= e($session['provider'] ?: '-') ?></dd></div>
                <div><dt>Budget</dt><dd><?= e(number_format((float) $session['budget'], 2, ',', ' ')) ?> <?= e($session['currency']) ?></dd></div>
                <div><dt>Objectifs</dt><dd><?= e($session['objectives'] ?: '-') ?></dd></div>
            </dl>
        </div>
    </section>

    <section class="card company-profile-card">
        <div class="card-header"><div><span class="dashboard-section-kicker">Jours</span><h2 class="card-title">Programme multi-jours</h2></div></div>
        <div class="training-days">
            <?php foreach ($days as $index => $day): ?>
                <a class="<?= $selectedDayId === (int) $day['id'] ? 'is-active' : '' ?>" href="<?= e(url('/trainings/show?id=' . $session['id'] . '&day_id=' . $day['id'])) ?>">
                    <strong>Jour <?= e((string) ($index + 1)) ?></strong>
                    <span><?= e($day['day_date']) ?></span>
                    <small><?= e($day['topic'] ?: 'Theme a preciser') ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<section class="card company-table-card training-attendance-card">
    <div class="card-header">
        <div><span class="dashboard-section-kicker">Feuille de presence</span><h2 class="card-title">Presence par journee</h2></div>
        <button class="btn btn-outline" type="button" data-training-open="participants"><?= icon('users') ?><span>Ajouter participants</span></button>
    </div>
    <form method="post" action="<?= e(url('/trainings/attendance/save?id=' . $session['id'])) ?>" data-training-form>
        <?= csrf_field() ?>
        <input type="hidden" name="day_id" value="<?= e((string) $selectedDayId) ?>">
        <div class="alert alert-danger d-none" data-form-error></div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead><tr><th>Employe</th><th>Departement</th><th>Presence journee</th><th>Note</th><th>Taux global</th><th>Resultat</th></tr></thead>
                <tbody>
                    <?php if ($participants === []): ?>
                        <tr><td colspan="6" class="text-secondary">Ajoutez les employes invites pour commencer l appel.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($participants as $participant): ?>
                        <?php
                        $name = trim(($participant['last_name'] ?? '') . ' ' . ($participant['middle_name'] ?? '') . ' ' . ($participant['first_name'] ?? ''));
                        $row = $attendance[(int) $participant['id']] ?? [];
                        $currentStatus = $row['status'] ?? 'present';
                        ?>
                        <tr>
                            <td><strong><?= e($name) ?></strong><span class="d-block text-secondary"><?= e($participant['employee_number']) ?> · <?= e($participant['position_title'] ?? '-') ?></span></td>
                            <td><?= e($participant['department_name'] ?? '-') ?></td>
                            <td>
                                <select class="form-select" name="attendance[<?= e((string) $participant['id']) ?>][status]">
                                    <?php foreach ($attendanceLabels as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $currentStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="form-control" name="attendance[<?= e((string) $participant['id']) ?>][notes]" value="<?= e($row['notes'] ?? '') ?>" placeholder="Observation"></td>
                            <td><span class="training-rate"><?= e(number_format((float) $participant['attendance_rate'], 1, ',', ' ')) ?>%</span></td>
                            <td><span class="badge bg-blue-lt"><?= e($finalLabels[$participant['final_status']] ?? $participant['final_status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer training-actions">
            <button class="btn btn-primary" type="submit" data-submit-label><?= icon('check') ?><span>Enregistrer l'appel</span></button>
        </div>
    </form>
</section>

<section class="card company-table-card mt-3">
    <div class="card-header">
        <div><span class="dashboard-section-kicker">Synthese</span><h2 class="card-title">Participants et resultats</h2></div>
        <form method="post" action="<?= e(url('/trainings/finalize?id=' . $session['id'])) ?>" data-training-form>
            <?= csrf_field() ?>
            <button class="btn btn-outline-success" type="submit" data-submit-label><?= icon('check') ?><span>Finaliser</span></button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table" id="training-participants-table">
            <thead><tr><th>Participant</th><th>Poste</th><th>Presence</th><th>Statut final</th><th>Certificat</th></tr></thead>
            <tbody>
                <?php foreach ($participants as $participant): ?>
                    <?php $name = trim(($participant['last_name'] ?? '') . ' ' . ($participant['middle_name'] ?? '') . ' ' . ($participant['first_name'] ?? '')); ?>
                    <tr>
                        <td><strong><?= e($name) ?></strong><span class="d-block text-secondary"><?= e($participant['employee_number']) ?></span></td>
                        <td><?= e($participant['position_title'] ?? '-') ?><span class="d-block text-secondary"><?= e($participant['department_name'] ?? '-') ?></span></td>
                        <td><?= e(number_format((float) $participant['attendance_rate'], 2, ',', ' ')) ?>%</td>
                        <td><?= e($finalLabels[$participant['final_status']] ?? $participant['final_status']) ?></td>
                        <td><?= (int) $participant['certificate_issued'] === 1 ? '<span class="badge bg-green-lt">Oui</span>' : '<span class="badge bg-gray-lt">Non</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="user-modal training-modal" data-training-modal="participants" aria-hidden="true">
    <div class="user-modal-backdrop" data-training-close></div>
    <section class="user-modal-dialog training-session-dialog">
        <div class="user-modal-header"><div><span class="dashboard-section-kicker">Participants</span><h2>Ajouter des employes</h2></div><button class="btn btn-icon" type="button" data-training-close><?= icon('x') ?></button></div>
        <form method="post" action="<?= e(url('/trainings/participants/add?id=' . $session['id'])) ?>" data-training-form>
            <?= csrf_field() ?><div class="alert alert-danger d-none" data-form-error></div>
            <div class="training-participant-picker">
                <?php foreach ($employees as $employee): ?>
                    <?php $name = trim(($employee['last_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['first_name'] ?? '')); ?>
                    <label>
                        <input type="checkbox" name="employee_ids[]" value="<?= e((string) $employee['id']) ?>">
                        <span><strong><?= e($name) ?></strong><small><?= e($employee['employee_number']) ?> · <?= e($employee['position_title'] ?? '-') ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="user-modal-footer-actions"><button class="btn btn-outline" type="button" data-training-close>Annuler</button><button class="btn btn-primary" type="submit" data-submit-label>Ajouter</button></div>
        </form>
    </section>
</div>

<script src="<?= e(asset('js/trainings.js') . '?v=' . (string) filemtime(BASE_PATH . '/public/js/trainings.js')) ?>"></script>
