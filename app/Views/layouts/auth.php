<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Connexion') ?> - ELLIOT-HR</title>
    <link rel="stylesheet" href="<?= e(asset('assets/tabler/css/tabler.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="theme-light auth-page erp-skin" data-page-title="<?= e($title ?? 'Connexion') ?>">
    <div class="app-loader" data-app-loader aria-hidden="true"><span></span></div>
    <main class="auth-shell">
        <?= $content ?>
    </main>
    <div class="toast-region" data-toast-region aria-live="polite" aria-atomic="true"></div>
    <script src="<?= e(asset('assets/tabler/js/tabler.min.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
