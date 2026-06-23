<div class="auth-layout">
    <section class="auth-showcase">
        <div class="auth-showcase-brand">
            <span class="brand-mark">EH</span>
            <div><strong>ELLIOT-HR</strong><small>Human Capital Suite</small></div>
        </div>
        <div class="auth-showcase-copy">
            <span class="dashboard-section-kicker">Plateforme de gestion RH</span>
            <h2>Pilotez votre capital humain avec clarté.</h2>
            <p>Une suite unifiée pour les dossiers employés, les contrats, les présences, les congés et la paie.</p>
        </div>
        <div class="auth-feature-grid">
            <article><span><?= icon('users') ?></span><strong>Dossiers centralisés</strong><small>Une vue fiable de chaque collaborateur</small></article>
            <article><span><?= icon('chart') ?></span><strong>Décisions éclairées</strong><small>Des indicateurs RH toujours accessibles</small></article>
            <article><span><?= icon('check') ?></span><strong>Conformité maîtrisée</strong><small>Des processus structurés et auditables</small></article>
        </div>
        <div class="auth-trust"><span></span> Environnement sécurisé · Données confidentielles</div>
    </section>

    <section class="auth-card">
        <div class="auth-brand">
            <span class="brand-mark">EH</span>
            <span>ELLIOT-HR</span>
        </div>
        <div class="auth-copy">
            <p class="text-secondary mb-1">Bienvenue dans votre espace</p>
            <h1>Connexion</h1>
            <p>Utilisez vos identifiants professionnels pour continuer.</p>
        </div>
        <div class="alert alert-danger d-none" data-login-error role="alert"></div>
        <form method="post" action="<?= e(url('/login')) ?>" class="form" data-login-form>
            <?= csrf_field() ?>
            <label class="form-label" for="email">Adresse email</label>
            <div class="input-icon">
                <?= icon('mail') ?>
                <input id="email" class="form-control" type="email" name="email" placeholder="nom@entreprise.com" autocomplete="email" required>
            </div>

            <label class="form-label" for="password">Mot de passe</label>
            <div class="input-icon">
                <?= icon('lock') ?>
                <input id="password" class="form-control" type="password" name="password" placeholder="Votre mot de passe" autocomplete="current-password" required>
            </div>

            <div class="form-footer">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember">
                    <span class="form-check-label">Rester connecté</span>
                </label>
                <span class="auth-help">Accès sécurisé</span>
            </div>

            <button class="btn btn-primary w-100" type="submit" data-login-submit>
                <span data-login-label>Se connecter</span>
                <?= icon('arrow-right') ?>
            </button>
        </form>
        <p class="auth-card-footer">Besoin d’aide ? Contactez votre administrateur RH.</p>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-login-form]');
    var errorBox = document.querySelector('[data-login-error]');
    var submit = document.querySelector('[data-login-submit]');
    var label = document.querySelector('[data-login-label]');

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        submit.disabled = true;
        label.textContent = 'Connexion...';

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    return {
                        ok: response.ok,
                        payload: payload
                    };
                });
            })
            .then(function (result) {
                if (result.ok && result.payload.success) {
                    window.location.href = result.payload.redirect || '<?= e(url('/dashboard')) ?>';
                    return;
                }

                errorBox.textContent = result.payload.message || 'Connexion impossible.';
                errorBox.classList.remove('d-none');
            })
            .catch(function () {
                errorBox.textContent = 'Erreur reseau. Verifiez votre connexion puis reessayez.';
                errorBox.classList.remove('d-none');
            })
            .finally(function () {
                submit.disabled = false;
                label.textContent = 'Se connecter';
            });
    });
});
</script>
