<?php
$users = $users ?? [];
$stats = $stats ?? [];
$activity = $activity ?? [];
$options = $options ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$currentUserId = (int) ($currentUserId ?? 0);
$defaultCompanyId = (int) ($defaultCompanyId ?? 0);
$canDelegatePermissions = !empty($canDelegatePermissions);
$statusLabels = ['active' => 'Actif', 'inactive' => 'Inactif', 'blocked' => 'Bloqué'];
$statusTones = ['active' => 'green', 'inactive' => 'gray', 'blocked' => 'red'];
$actionLabels = [
    'login_success' => 'Connexion réussie',
    'login_failed' => 'Échec de connexion',
    'logout' => 'Déconnexion',
    'user_created' => 'Compte créé',
    'user_updated' => 'Compte modifié',
    'user_status_changed' => 'Statut modifié',
    'user_password_reset' => 'Mot de passe réinitialisé',
    'user_deleted' => 'Compte supprimé',
    'permission_delegation_updated' => 'Délégation modifiée',
    'user_permissions_updated' => 'Permissions modifiées',
];
$userPayload = static function (array $user): string {
    return base64_encode(json_encode([
        'id' => (int) $user['id'],
        'company_id' => $user['company_id'] !== null ? (int) $user['company_id'] : '',
        'role_id' => $user['role_id'] !== null ? (int) $user['role_id'] : '',
        'employee_id' => $user['employee_id'] !== null ? (int) $user['employee_id'] : '',
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? '',
        'email' => $user['email'] ?? '',
        'phone' => $user['phone'] ?? '',
        'status' => $user['status'] ?? 'active',
        'role_slug' => $user['role_slug'] ?? '',
    ], JSON_UNESCAPED_UNICODE));
};
?>

<div class="users-workspace">
    <div class="module-header module-header-rich">
        <div>
            <span class="dashboard-section-kicker">Administration des accès</span>
            <h1 class="page-title">Utilisateurs et sécurité</h1>
            <p>Gérez les comptes, rôles, rattachements employés et accès à la plateforme depuis un espace centralisé.</p>
        </div>
        <div class="module-header-actions">
            <span class="dashboard-status"><span></span><?= e((string) ($stats['active'] ?? 0)) ?> comptes actifs</span>
            <button class="btn btn-primary" type="button" data-user-create><?= icon('plus') ?><span>Nouvel utilisateur</span></button>
        </div>
    </div>

    <div class="user-stat-grid">
        <article class="user-stat-card is-primary">
            <span class="user-stat-icon"><?= icon('users') ?></span>
            <div><small>Comptes enregistrés</small><strong><?= e((string) ($stats['total'] ?? 0)) ?></strong><p>Utilisateurs de la plateforme</p></div>
        </article>
        <article class="user-stat-card is-success">
            <span class="user-stat-icon"><?= icon('check') ?></span>
            <div><small>Actifs</small><strong><?= e((string) ($stats['active'] ?? 0)) ?></strong><p>Accès actuellement autorisés</p></div>
        </article>
        <article class="user-stat-card is-danger">
            <span class="user-stat-icon"><?= icon('lock') ?></span>
            <div><small>Bloqués</small><strong><?= e((string) ($stats['blocked'] ?? 0)) ?></strong><p>Comptes nécessitant une revue</p></div>
        </article>
        <article class="user-stat-card is-info">
            <span class="user-stat-icon"><?= icon('clock') ?></span>
            <div><small>Actifs récemment</small><strong><?= e((string) ($stats['recent'] ?? 0)) ?></strong><p>Connexion durant les 30 derniers jours</p></div>
        </article>
    </div>

    <div class="users-main-grid">
        <section class="card company-table-card users-table-card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table" id="users-table">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Entreprise</th>
                            <th>Rôle</th>
                            <th>Employé lié</th>
                            <th>Dernière connexion</th>
                            <th>Statut</th>
                            <th class="w-1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $status = $user['status'] ?? 'inactive';
                            $tone = $statusTones[$status] ?? 'gray';
                            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                            $initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
                            $isCurrent = (int) $user['id'] === $currentUserId;
                            ?>
                            <tr data-user-row="<?= e((string) $user['id']) ?>">
                                <td>
                                    <div class="user-identity-cell">
                                        <span class="user-table-avatar"><?= e($initials) ?></span>
                                        <div>
                                            <strong><?= e($name) ?><?= $isCurrent ? ' <span class="badge bg-blue-lt">Vous</span>' : '' ?></strong>
                                            <span><?= e($user['email'] ?? '-') ?></span>
                                            <?php if (!empty($user['phone'])): ?><small><?= e($user['phone']) ?></small><?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($user['company_name'] ?? 'Plateforme globale') ?></td>
                                <td>
                                    <span class="role-badge role-<?= e($user['role_slug'] ?? 'default') ?>"><?= icon('shield') ?><?= e($user['role_name'] ?? 'Sans rôle') ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($user['employee_number'])): ?>
                                        <strong><?= e($user['employee_number']) ?></strong>
                                        <span class="d-block text-secondary"><?= e($user['position_title'] ?? $user['department_name'] ?? '-') ?></span>
                                    <?php else: ?>
                                        <span class="text-secondary">Non rattaché</span>
                                    <?php endif; ?>
                                </td>
                                <td data-order="<?= e($user['last_login_at'] ?? '') ?>">
                                    <?php if (!empty($user['last_login_at'])): ?>
                                        <strong><?= e(date('d/m/Y', strtotime($user['last_login_at']))) ?></strong>
                                        <span class="d-block text-secondary"><?= e(date('H:i', strtotime($user['last_login_at']))) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-gray-lt">Jamais connecté</span>
                                    <?php endif; ?>
                                </td>
                                <td data-search="<?= e($statusLabels[$status] ?? $status) ?>">
                                    <span class="badge bg-<?= e($tone) ?>-lt" data-user-status-badge><?= e($statusLabels[$status] ?? $status) ?></span>
                                </td>
                                <td>
                                    <div class="btn-list flex-nowrap">
                                        <button class="btn btn-icon" type="button" data-user-edit="<?= e($userPayload($user)) ?>" title="Modifier"><?= icon('settings') ?></button>
                                        <button class="btn btn-icon" type="button" data-user-password="<?= e((string) $user['id']) ?>" data-user-name="<?= e($name) ?>" title="Réinitialiser le mot de passe"><?= icon('key') ?></button>
                                        <?php if ($isSuperAdmin && ($user['role_slug'] ?? '') === 'admin-rh'): ?>
                                            <button class="btn btn-icon btn-outline-primary" type="button" data-user-access="<?= e((string) $user['id']) ?>" data-access-mode="delegation" title="Définir les permissions délégables"><?= icon('shield') ?></button>
                                        <?php endif; ?>
                                        <?php if ($canDelegatePermissions && !$isCurrent && ($user['role_slug'] ?? '') !== 'super-admin'): ?>
                                            <button class="btn btn-icon btn-outline-info" type="button" data-user-access="<?= e((string) $user['id']) ?>" data-access-mode="permissions" title="Permissions individuelles"><?= icon('settings') ?></button>
                                        <?php endif; ?>
                                        <?php if (!$isCurrent): ?>
                                            <button class="btn btn-icon <?= $status === 'blocked' ? 'btn-outline-success' : 'btn-outline-warning' ?>" type="button" data-user-toggle-status="<?= e((string) $user['id']) ?>" data-current-status="<?= e($status) ?>" title="<?= $status === 'blocked' ? 'Débloquer' : 'Bloquer' ?>"><?= $status === 'blocked' ? icon('check') : icon('lock') ?></button>
                                            <button class="btn btn-icon btn-outline-danger" type="button" data-user-delete="<?= e((string) $user['id']) ?>" data-user-name="<?= e($name) ?>" title="Supprimer"><?= icon('x') ?></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="card user-activity-card">
            <div class="card-header">
                <div><span class="dashboard-section-kicker">Traçabilité</span><h2 class="card-title">Activité récente</h2></div>
                <span class="badge bg-blue-lt"><?= e((string) count($activity)) ?></span>
            </div>
            <div class="user-activity-list">
                <?php if ($activity === []): ?>
                    <div class="dashboard-empty"><span>Aucune activité récente.</span></div>
                <?php endif; ?>
                <?php foreach ($activity as $event): ?>
                    <?php
                    $danger = in_array($event['action'] ?? '', ['login_failed', 'user_deleted'], true);
                    $actor = trim(($event['first_name'] ?? '') . ' ' . ($event['last_name'] ?? ''));
                    ?>
                    <article class="user-activity-item">
                        <span class="activity-marker <?= $danger ? 'is-danger' : '' ?>"></span>
                        <div>
                            <strong><?= e($actionLabels[$event['action'] ?? ''] ?? ($event['action'] ?? 'Activité')) ?></strong>
                            <p><?= e($actor !== '' ? $actor : ($event['email'] ?? 'Système')) ?></p>
                            <small><?= e(date('d/m/Y H:i', strtotime($event['created_at'] ?? 'now'))) ?></small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</div>

<div class="user-modal" data-user-modal aria-hidden="true">
    <div class="user-modal-backdrop" data-user-modal-close></div>
    <section class="user-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
        <div class="user-modal-header">
            <div class="user-modal-heading">
                <span class="user-modal-heading-icon"><?= icon('shield') ?></span>
                <div>
                    <span class="dashboard-section-kicker">Compte et accès</span>
                    <h2 id="user-modal-title" data-user-modal-title>Nouvel utilisateur</h2>
                    <p>Configurez l’identité, le périmètre et les droits du compte.</p>
                </div>
            </div>
            <button class="btn btn-icon" type="button" data-user-modal-close><?= icon('x') ?></button>
        </div>
        <form method="post" action="<?= e(url('/users/store')) ?>" data-user-form data-store-url="<?= e(url('/users/store')) ?>" data-update-url="<?= e(url('/users/update')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <div class="user-modal-body">
                <div class="user-form-progress" aria-label="Étapes du formulaire">
                    <span class="is-active"><b>1</b> Identité</span>
                    <i></i>
                    <span><b>2</b> Accès</span>
                    <i></i>
                    <span data-security-progress><b>3</b> Sécurité</span>
                </div>
                <div class="alert alert-danger d-none" data-form-error></div>
                <div class="user-form-section">
                    <div class="user-form-section-title"><span>1</span><div><strong>Identité</strong><small>Informations personnelles et coordonnées</small></div></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Prénom</label><input class="form-control" name="first_name" required></div>
                        <div class="col-md-6"><label class="form-label">Nom</label><input class="form-control" name="last_name" required></div>
                        <div class="col-md-7"><label class="form-label">Adresse email</label><input class="form-control" type="email" name="email" required></div>
                        <div class="col-md-5"><label class="form-label">Téléphone</label><input class="form-control" name="phone"></div>
                    </div>
                </div>
                <div class="user-form-section">
                    <div class="user-form-section-title"><span>2</span><div><strong>Périmètre d’accès</strong><small>Entreprise, rôle et rattachement employé</small></div></div>
                    <div class="row g-3">
                        <?php if ($isSuperAdmin): ?>
                            <div class="col-md-6">
                                <label class="form-label">Entreprise</label>
                                <select class="form-select" name="company_id" data-user-company>
                                    <option value="">Plateforme globale</option>
                                    <?php foreach ($options['companies'] ?? [] as $company): ?>
                                        <option value="<?= e((string) $company['id']) ?>"><?= e($company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="company_id" value="<?= e((string) $defaultCompanyId) ?>" data-user-company>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label">Rôle</label>
                            <select class="form-select" name="role_id" required data-user-role>
                                <option value="">Sélectionner un rôle</option>
                                <?php foreach ($options['roles'] ?? [] as $role): ?>
                                    <option value="<?= e((string) $role['id']) ?>"
                                            data-company-id="<?= e($role['company_id'] !== null ? (string) $role['company_id'] : '') ?>"
                                            data-role-slug="<?= e($role['slug']) ?>"
                                            data-role-description="<?= e($role['description'] ?? '') ?>"
                                            data-permissions="<?= e($role['permission_names'] ?? '') ?>"><?= e($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Employé lié <span class="text-secondary">(optionnel)</span></label>
                            <select class="form-select" name="employee_id" data-user-employee>
                                <option value="">Aucun rattachement</option>
                                <?php foreach ($options['employees'] ?? [] as $employee): ?>
                                    <option value="<?= e((string) $employee['id']) ?>" data-company-id="<?= e((string) $employee['company_id']) ?>">
                                        <?= e(trim(($employee['last_name'] ?? '') . ' ' . ($employee['middle_name'] ?? '') . ' ' . ($employee['first_name'] ?? ''))) ?> · <?= e($employee['employee_number']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Statut</label>
                            <select class="form-select" name="status">
                                <option value="active">Actif</option>
                                <option value="inactive">Inactif</option>
                                <option value="blocked">Bloqué</option>
                            </select>
                        </div>
                    </div>
                    <div class="role-permission-preview" data-role-permission-preview>
                        <div><?= icon('shield') ?><span><strong>Permissions effectives</strong><small>Sélectionnez un rôle pour afficher ses autorisations.</small></span></div>
                        <div class="permission-chip-list" data-permission-chip-list></div>
                    </div>
                </div>
                <div class="user-form-section" data-password-create-section>
                    <div class="user-form-section-title"><span>3</span><div><strong>Sécurité initiale</strong><small>Le mot de passe pourra être réinitialisé ultérieurement</small></div></div>
                    <div class="password-field">
                        <label class="form-label">Mot de passe temporaire</label>
                        <div class="input-group">
                            <input class="form-control" type="password" name="password" minlength="8" autocomplete="new-password">
                            <button class="btn btn-outline" type="button" data-generate-password>Générer</button>
                        </div>
                        <div class="password-strength"><span data-password-strength></span></div>
                        <small class="text-secondary">8 caractères minimum, idéalement avec chiffres et symboles.</small>
                    </div>
                </div>
            </div>
            <div class="user-modal-footer">
                <div class="user-modal-footer-note"><?= icon('shield') ?><span>Les accès sont appliqués immédiatement après validation.</span></div>
                <div class="user-modal-footer-actions">
                    <button class="btn btn-outline" type="button" data-user-modal-close>Annuler</button>
                    <button class="btn btn-primary" type="submit" data-submit-label>Créer l’utilisateur</button>
                </div>
            </div>
        </form>
    </section>
</div>

<div class="user-modal" data-password-modal aria-hidden="true">
    <div class="user-modal-backdrop" data-password-modal-close></div>
    <section class="user-modal-dialog user-password-dialog" role="dialog" aria-modal="true">
        <div class="user-modal-header">
            <div><span class="dashboard-section-kicker">Sécurité du compte</span><h2>Réinitialiser le mot de passe</h2></div>
            <button class="btn btn-icon" type="button" data-password-modal-close><?= icon('x') ?></button>
        </div>
        <form method="post" action="<?= e(url('/users/password')) ?>" data-password-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <div class="user-modal-body">
                <div class="password-reset-identity"><?= icon('key') ?><div><span>Compte concerné</span><strong data-password-user-name>-</strong></div></div>
                <div class="alert alert-danger d-none" data-form-error></div>
                <label class="form-label">Nouveau mot de passe</label>
                <input class="form-control" type="password" name="password" minlength="8" autocomplete="new-password" required>
                <div class="password-strength"><span data-password-strength></span></div>
                <label class="form-label mt-3">Confirmer le mot de passe</label>
                <input class="form-control" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="user-modal-footer">
                <button class="btn btn-outline" type="button" data-password-modal-close>Annuler</button>
                <button class="btn btn-primary" type="submit" data-submit-label>Réinitialiser</button>
            </div>
        </form>
    </section>
</div>

<div class="user-modal permission-modal" data-permission-modal aria-hidden="true">
    <div class="user-modal-backdrop" data-permission-modal-close></div>
    <section class="user-modal-dialog permission-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="permission-modal-title">
        <div class="user-modal-header">
            <div class="user-modal-heading">
                <span class="user-modal-heading-icon"><?= icon('shield') ?></span>
                <div>
                    <span class="dashboard-section-kicker" data-permission-kicker>Délégation sécurisée</span>
                    <h2 id="permission-modal-title" data-permission-title>Permissions</h2>
                    <p data-permission-subtitle>Chargement du périmètre autorisé…</p>
                </div>
            </div>
            <button class="btn btn-icon" type="button" data-permission-modal-close><?= icon('x') ?></button>
        </div>
        <form data-permission-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id">
            <input type="hidden" name="mode">
            <div class="user-modal-body">
                <div class="permission-security-notice"><?= icon('shield') ?><div><strong data-permission-notice-title>Périmètre contrôlé</strong><span data-permission-notice>Seules les autorisations affichées peuvent être attribuées.</span></div></div>
                <div class="alert alert-danger d-none" data-permission-error></div>
                <div class="permission-modal-toolbar">
                    <div class="topbar-search"><span>⌕</span><input type="search" placeholder="Rechercher une permission" data-permission-search></div>
                    <button class="btn btn-sm btn-outline" type="button" data-permission-select-all>Tout sélectionner</button>
                </div>
                <div class="permission-groups" data-permission-groups><div class="dashboard-empty">Chargement…</div></div>
            </div>
            <div class="user-modal-footer">
                <div class="user-modal-footer-note"><?= icon('shield') ?><span>Chaque modification est enregistrée dans le journal d’audit.</span></div>
                <div class="user-modal-footer-actions">
                    <button class="btn btn-outline" type="button" data-permission-modal-close>Annuler</button>
                    <button class="btn btn-primary" type="submit" data-permission-submit>Enregistrer</button>
                </div>
            </div>
        </form>
    </section>
</div>

<script>
window.ELLIOT_CSRF = '<?= e(csrf_token()) ?>';
window.ELLIOT_USER_URLS = {
    status: '<?= e(url('/users/status')) ?>',
    delete: '<?= e(url('/users/delete')) ?>',
    permissionData: '<?= e(url('/users/permissions/data')) ?>',
    saveDelegations: '<?= e(url('/users/delegations/save')) ?>',
    savePermissions: '<?= e(url('/users/permissions/save')) ?>'
};
</script>
<script src="<?= e(asset('js/users.js')) ?>"></script>
