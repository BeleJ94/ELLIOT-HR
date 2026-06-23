<?php
$contract = $contract ?? [];
$options = $options ?? [];
?>

<div class="module-header module-header-rich">
    <div>
        <span class="dashboard-section-kicker">Contrats RH</span>
        <h1 class="page-title"><?= e($contract['contract_number'] ?? 'Contrat') ?></h1>
        <p>Mettre a jour la nature du contrat, les dates, la periode d'essai et la remuneration.</p>
    </div>
    <div class="module-header-actions">
        <a class="btn btn-outline" href="<?= e(url('/contracts')) ?>"><?= icon('arrow-right') ?><span>Liste</span></a>
        <a class="btn btn-primary" href="<?= e(url('/contracts/show?id=' . ($contract['id'] ?? 0))) ?>"><?= icon('file') ?><span>Dossier</span></a>
    </div>
</div>

<form method="post" action="<?= e(url('/contracts/update?id=' . ($contract['id'] ?? 0))) ?>" data-contract-form>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e((string) ($contract['id'] ?? 0)) ?>">
    <?php require APP_PATH . '/Views/contracts/form.php'; ?>
</form>

<script src="<?= e(asset('js/contracts.js')) ?>"></script>
