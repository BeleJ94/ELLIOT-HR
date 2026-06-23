<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'ELLIOT-HR') ?> - ELLIOT-HR</title>
    <link rel="stylesheet" href="<?= e(asset('assets/tabler/css/tabler.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/datatables/dataTables.bootstrap5.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="theme-light app-shell erp-skin" data-page-title="<?= e($title ?? 'ELLIOT-HR') ?>">
    <div class="app-loader" data-app-loader aria-hidden="true"><span></span></div>
    <div class="page">
        <?php require APP_PATH . '/Views/partials/sidebar.php'; ?>
        <div class="page-wrapper">
            <?php require APP_PATH . '/Views/partials/topbar.php'; ?>
            <main class="page-body">
                <div class="container-xl">
                    <?= $content ?>
                </div>
            </main>
            <?php require APP_PATH . '/Views/partials/footer.php'; ?>
        </div>
    </div>
    <div class="toast-region" data-toast-region aria-live="polite" aria-atomic="true"></div>
    <div class="elliot-modal" data-confirm-modal aria-hidden="true">
        <div class="elliot-modal-backdrop" data-modal-close></div>
        <section class="elliot-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
            <div class="elliot-modal-icon"><?= icon('alert') ?></div>
            <div>
                <span class="dashboard-section-kicker">Confirmation</span>
                <h2 id="confirm-title">Confirmer cette action</h2>
                <p data-confirm-message>Cette opération nécessite votre confirmation.</p>
            </div>
            <div class="elliot-modal-actions">
                <button class="btn btn-outline" type="button" data-modal-close>Annuler</button>
                <button class="btn btn-danger" type="button" data-confirm-accept>Confirmer</button>
            </div>
        </section>
    </div>
    <script src="<?= e(asset('assets/tabler/js/tabler.min.js')) ?>"></script>
    <script src="<?= e(asset('assets/vendor/jquery/jquery-3.7.1.min.js')) ?>"></script>
    <script src="<?= e(asset('assets/vendor/datatables/jquery.dataTables.min.js')) ?>"></script>
    <script src="<?= e(asset('assets/vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
