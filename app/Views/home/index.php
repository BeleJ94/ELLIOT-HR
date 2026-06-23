<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'ELLIOT-HR') ?> - ELLIOT-HR</title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
    <main class="page">
        <section class="shell">
            <p class="eyebrow">SaaS RH multi-entreprises</p>
            <h1>ELLIOT-HR</h1>
            <p class="lead">Socle MVC PHP pret pour les modules RH, AJAX, DataTables et une UI inspiree de Tabler.</p>
            <div class="status">Route active : <strong>/dashboard</strong></div>
        </section>
    </main>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
