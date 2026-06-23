<?php
$employee = $employee ?? [];
$options = $options ?? [];
?>

<div class="module-header module-header-rich erp-page-header">
    <div>
        <span class="dashboard-section-kicker">Modification RH</span>
        <h1 class="page-title"><?= e(($employee['last_name'] ?? '') . ' ' . ($employee['first_name'] ?? '')) ?></h1>
        <p>Mettre a jour les informations personnelles, l'affectation et le contrat.</p>
    </div>
    <div class="module-header-actions">
        <a class="btn btn-outline" href="<?= e(url('/employees')) ?>"><?= icon('arrow-right') ?><span>Liste</span></a>
        <a class="btn btn-primary" href="<?= e(url('/employees/show?id=' . ($employee['id'] ?? 0))) ?>"><?= icon('file') ?><span>Dossier</span></a>
    </div>
</div>

<form method="post" action="<?= e(url('/employees/update?id=' . ($employee['id'] ?? 0))) ?>" enctype="multipart/form-data" data-employee-form>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($employee['id'] ?? 0)) ?>">
    <?php require APP_PATH . '/Views/employees/form.php'; ?>
</form>

<script src="<?= e(asset('js/employees.js')) ?>"></script>
