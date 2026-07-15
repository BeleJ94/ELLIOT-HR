<?php
$employee = $employee ?? [];
$options = $options ?? [];
?>

<div class="module-header module-header-rich erp-page-header">
    <div>
        <span class="dashboard-section-kicker">Dossier RH</span>
        <h1 class="page-title">Nouvel agent</h1>
        <p>Creez son dossier RH, son affectation et son contrat initial.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/employees')) ?>"><?= icon('arrow-right') ?><span>Retour</span></a>
</div>

<form method="post" action="<?= e(url('/employees/store')) ?>" enctype="multipart/form-data" data-employee-form>
    <?= csrf_field() ?>
    <?php require APP_PATH . '/Views/employees/form.php'; ?>
</form>

<script src="<?= e(asset('js/employees.js')) ?>"></script>
