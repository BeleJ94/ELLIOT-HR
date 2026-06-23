<?php
$employee = $employee ?? [];
$documents = $documents ?? [];
$trainingHistory = $trainingHistory ?? [];
$photo = !empty($employee['photo_path']) ? url($employee['photo_path']) : null;
$fullName = trim(($employee['last_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['first_name'] ?? ''));
$statusLabels = [
    'active' => 'Actif',
    'on_leave' => 'En conge',
    'suspended' => 'Suspendu',
    'terminated' => 'Archive',
];
?>

<div class="employee-show-hero erp-page-header employee-profile-header">
    <div class="employee-show-profile">
        <?php if ($photo): ?>
            <img class="employee-photo-large" src="<?= e($photo) ?>" alt="">
        <?php else: ?>
            <span class="employee-photo-large employee-avatar-fallback"><?= e(strtoupper(substr($employee['first_name'] ?? 'E', 0, 1) . substr($employee['last_name'] ?? '', 0, 1))) ?></span>
        <?php endif; ?>
        <div>
            <span class="dashboard-section-kicker">Dossier employe</span>
            <h1 class="page-title"><?= e($fullName) ?></h1>
            <p><?= e($employee['employee_number'] ?? '-') ?> · <?= e($employee['position_title'] ?? 'Poste non defini') ?> · <?= e($employee['department_name'] ?? 'Departement non defini') ?></p>
        </div>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('/employees')) ?>"><?= icon('arrow-right') ?><span>Liste</span></a>
        <a class="btn btn-primary" href="<?= e(url('/employees/edit?id=' . ($employee['id'] ?? 0))) ?>"><?= icon('settings') ?><span>Modifier</span></a>
    </div>
</div>

<div class="company-kpi-grid employee-kpi-grid erp-summary-strip">
    <div class="company-kpi-card"><span>Statut</span><strong><?= e($statusLabels[$employee['employment_status'] ?? 'active'] ?? '-') ?></strong></div>
    <div class="company-kpi-card"><span>Contrat</span><strong><?= e(strtoupper($employee['contract_type'] ?? '-')) ?></strong></div>
    <div class="company-kpi-card"><span>Salaire</span><strong><?= e(number_format((float) ($employee['base_salary'] ?? 0), 2, ',', ' ')) ?> <?= e($employee['currency'] ?? 'USD') ?></strong></div>
    <div class="company-kpi-card"><span>Embauche</span><strong><?= e($employee['hire_date'] ?? '-') ?></strong></div>
</div>

<div class="company-detail-grid employee-detail-grid">
    <div class="card company-profile-card erp-portlet">
        <div class="card-header"><h2 class="card-title">Informations personnelles</h2></div>
        <div class="card-body">
            <dl class="company-definition-list">
                <div><dt>Telephone</dt><dd><?= e($employee['phone'] ?: '-') ?></dd></div>
                <div><dt>Email</dt><dd><?= e($employee['email'] ?: '-') ?></dd></div>
                <div><dt>Naissance</dt><dd><?= e($employee['birth_date'] ?: '-') ?> · <?= e($employee['birth_place'] ?: '-') ?></dd></div>
                <div><dt>Adresse</dt><dd><?= e($employee['address'] ?: '-') ?></dd></div>
                <div><dt>Urgence</dt><dd><?= e($employee['emergency_contact_name'] ?: '-') ?></dd></div>
                <div><dt>Tel. urgence</dt><dd><?= e($employee['emergency_contact_phone'] ?: '-') ?></dd></div>
            </dl>
        </div>
    </div>

    <div class="card company-profile-card erp-portlet">
        <div class="card-header"><h2 class="card-title">Affectation</h2></div>
        <div class="card-body">
            <dl class="company-definition-list">
                <div><dt>Entreprise</dt><dd><?= e($employee['company_name'] ?? '-') ?></dd></div>
                <div><dt>Site</dt><dd><?= e($employee['branch_name'] ?? '-') ?></dd></div>
                <div><dt>Departement</dt><dd><?= e($employee['department_name'] ?? '-') ?></dd></div>
                <div><dt>Poste</dt><dd><?= e($employee['position_title'] ?? '-') ?></dd></div>
                <div><dt>Manager</dt><dd><?= e(trim(($employee['manager_last_name'] ?? '') . ' ' . ($employee['manager_first_name'] ?? '')) ?: '-') ?></dd></div>
                <div><dt>Contrat no</dt><dd><?= e($employee['contract_number'] ?? '-') ?></dd></div>
            </dl>
        </div>
    </div>
</div>

<div class="card mt-3 company-sites-card erp-table-card">
    <div class="card-header">
        <div>
            <span class="dashboard-section-kicker">Developpement</span>
            <h2 class="card-title">Historique formations</h2>
        </div>
        <a class="btn btn-outline" href="<?= e(url('/trainings')) ?>"><?= icon('file') ?><span>Formations</span></a>
    </div>
    <div class="table-responsive">
        <table class="table card-table">
            <thead><tr><th>Formation</th><th>Periode</th><th>Presence</th><th>Resultat</th><th>Certificat</th></tr></thead>
            <tbody>
                <?php if ($trainingHistory === []): ?>
                    <tr><td colspan="5" class="text-secondary">Aucune formation suivie.</td></tr>
                <?php endif; ?>
                <?php foreach ($trainingHistory as $row): ?>
                    <tr>
                        <td><strong><?= e($row['course_title']) ?></strong><span class="d-block text-secondary"><?= e($row['session_title']) ?> · <?= e($row['category'] ?: '-') ?></span></td>
                        <td><?= e($row['start_date']) ?> au <?= e($row['end_date']) ?></td>
                        <td><?= e(number_format((float) $row['attendance_rate'], 2, ',', ' ')) ?>%</td>
                        <td><?= e($row['final_status']) ?></td>
                        <td><?= (int) $row['certificate_issued'] === 1 ? '<span class="badge bg-green-lt">Oui</span>' : '<span class="badge bg-gray-lt">Non</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3 company-sites-card erp-table-card">
    <div class="card-header">
        <div>
            <span class="dashboard-section-kicker">Pieces</span>
            <h2 class="card-title">Documents employe</h2>
        </div>
    </div>
    <div class="card-body">
        <form class="employee-document-form" method="post" action="<?= e(url('/employees/documents/upload?id=' . ($employee['id'] ?? 0))) ?>" enctype="multipart/form-data" data-employee-form>
            <?= csrf_field() ?>
            <div class="alert alert-danger d-none" data-form-error></div>
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label class="form-label">Titre</label><input class="form-control" name="title" required></div>
                <div class="col-md-3"><label class="form-label">Type</label><input class="form-control" name="document_type" value="document"></div>
                <div class="col-md-3"><label class="form-label">Expiration</label><input class="form-control" type="date" name="expires_at"></div>
                <div class="col-md-3"><label class="form-label">Fichier</label><input class="form-control" type="file" name="document" required></div>
                <div class="col-12 text-end"><button class="btn btn-primary" type="submit" data-submit-label>Ajouter le document</button></div>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table card-table">
            <thead><tr><th>Document</th><th>Type</th><th>Expiration</th><th>Fichier</th></tr></thead>
            <tbody>
                <?php if ($documents === []): ?>
                    <tr><td colspan="4" class="text-secondary">Aucun document ajoute.</td></tr>
                <?php endif; ?>
                <?php foreach ($documents as $document): ?>
                    <tr>
                        <td><strong><?= e($document['title']) ?></strong><span class="d-block text-secondary"><?= e($document['created_at'] ?? '') ?></span></td>
                        <td><?= e($document['document_type']) ?></td>
                        <td><?= e($document['expires_at'] ?: '-') ?></td>
                        <td><a href="<?= e(url($document['file_path'])) ?>" target="_blank">Ouvrir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
</script>
<script src="<?= e(asset('js/employees.js')) ?>"></script>
