<?php
$contract = $contract ?? [];
$options = $options ?? [];
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Contrats RH</span>
        <h1 class="page-title">Nouveau contrat</h1>
        <p>Creer un CDI, CDD, stage ou contrat consultant avec periode d'essai et salaire contractuel.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('/contracts')) ?>"><?= icon('arrow-right') ?><span>Retour</span></a>
</div>

<form method="post" action="<?= e(url('/contracts/store')) ?>" data-contract-form>
    <?= csrf_field() ?>
    <?php require APP_PATH . '/Views/contracts/form.php'; ?>
</form>

<script src="<?= e(asset('js/contracts.js')) ?>"></script>
