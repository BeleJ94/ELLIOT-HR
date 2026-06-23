<?php
$employee = $employee ?? [];
$options = $options ?? [];
?>

<div class="module-header module-header-rich erp-page-header">
    <div>
        <span class="dashboard-section-kicker">Dossier RH</span>
        <h1 class="page-title">Nouvel employe</h1>
        <p>Creer le dossier administratif, l'affectation et le contrat initial.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/employees')) ?>"><?= icon('arrow-right') ?><span>Retour</span></a>
</div>

<form method="post" action="<?= e(url('/employees/store')) ?>" enctype="multipart/form-data" data-employee-form>
    <?= csrf_field() ?>
    <?php require APP_PATH . '/Views/employees/form.php'; ?>
</form>

<script src="<?= e(asset('js/employees.js')) ?>"></script>
