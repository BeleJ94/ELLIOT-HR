<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\User;
use Throwable;

class UserController extends Controller
{
    private User $users;

    public function __construct()
    {
        $this->users = new User();
    }

    public function index(): void
    {
        $scope = $this->companyScope();
        $dashboard = $this->users->dashboard($scope);

        $this->view('users.index', [
            'title' => 'Gestion des utilisateurs',
            'users' => $dashboard['rows'],
            'stats' => $dashboard['stats'],
            'activity' => $dashboard['activity'],
            'options' => $this->users->formOptions($scope, $this->isSuperAdmin()),
            'isSuperAdmin' => $this->isSuperAdmin(),
            'currentUserId' => Auth::id(),
            'defaultCompanyId' => $scope ?? 0,
        ]);
    }

    public function store(): void
    {
        $this->persist();
    }

    public function update(): void
    {
        $this->persist($this->requestId());
    }

    public function status(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        if ($id === Auth::id()) {
            $this->jsonError('Vous ne pouvez pas modifier le statut de votre propre compte.', 422);
            return;
        }
        $user = $this->users->findScoped($id, $this->companyScope());
        if (!$user || !$this->canManageTarget($user)) {
            $this->jsonError('Utilisateur introuvable ou non autorisé.', 404);
            return;
        }

        $status = (string) ($_POST['status'] ?? 'active');
        $updated = $this->users->updateStatusScoped($id, $status, $this->companyScope());
        if ($updated) {
            Auth::log('user_status_changed', $user['company_id'] !== null ? (int) $user['company_id'] : null, Auth::id(), [
                'target_user_id' => $id,
                'status' => $status,
            ]);
        }

        $this->json(['success' => $updated, 'message' => $updated ? 'Statut du compte mis à jour.' : 'Aucune modification.']);
    }

    public function password(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        $user = $this->users->findScoped($id, $this->companyScope());
        if (!$user || !$this->canManageTarget($user)) {
            $this->jsonError('Utilisateur introuvable ou non autorisé.', 404);
            return;
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (strlen($password) < 8) {
            $this->jsonError('Le mot de passe doit contenir au moins 8 caractères.', 422, ['password' => '8 caractères minimum']);
            return;
        }
        if ($password !== $confirmation) {
            $this->jsonError('La confirmation du mot de passe ne correspond pas.', 422, ['password_confirmation' => 'Confirmation différente']);
            return;
        }

        $updated = $this->users->updatePasswordScoped($id, $password, $this->companyScope());
        if ($updated) {
            Auth::log('user_password_reset', $user['company_id'] !== null ? (int) $user['company_id'] : null, Auth::id(), [
                'target_user_id' => $id,
            ]);
        }

        $this->json(['success' => $updated, 'message' => $updated ? 'Mot de passe réinitialisé.' : 'Réinitialisation impossible.']);
    }

    public function delete(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        if ($id === Auth::id()) {
            $this->jsonError('Vous ne pouvez pas supprimer votre propre compte.', 422);
            return;
        }
        $user = $this->users->findScoped($id, $this->companyScope());
        if (!$user || !$this->canManageTarget($user)) {
            $this->jsonError('Utilisateur introuvable ou non autorisé.', 404);
            return;
        }

        $deleted = $this->users->softDeleteScoped($id, $this->companyScope());
        if ($deleted) {
            Auth::log('user_deleted', $user['company_id'] !== null ? (int) $user['company_id'] : null, Auth::id(), [
                'target_user_id' => $id,
                'email' => $user['email'],
            ]);
        }

        $this->json(['success' => $deleted, 'message' => $deleted ? 'Compte utilisateur supprimé.' : 'Suppression impossible.']);
    }

    private function persist(?int $id = null): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide. Rechargez la page.', 419);
            return;
        }

        $current = $id !== null ? $this->users->findScoped($id, $this->companyScope()) : null;
        if ($id !== null && (!$current || !$this->canManageTarget($current))) {
            $this->jsonError('Utilisateur introuvable ou non autorisé.', 404);
            return;
        }
        if ($id !== null && $id === Auth::id() && $current) {
            $_POST['company_id'] = $current['company_id'];
            $_POST['role_id'] = $current['role_id'];
            $_POST['status'] = $current['status'];
        }

        $companyId = $this->resolvedCompanyId($_POST, $current);
        $_POST['company_id'] = $companyId > 0 ? $companyId : null;
        $errors = $this->validateUser($_POST, $id, $companyId);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signalés.', 422, $errors);
            return;
        }

        try {
            $userId = $this->users->saveUser($_POST, $id);
            Auth::log($id === null ? 'user_created' : 'user_updated', $companyId > 0 ? $companyId : null, Auth::id(), [
                'target_user_id' => $userId,
                'email' => strtolower(trim($_POST['email'] ?? '')),
                'role_id' => (int) ($_POST['role_id'] ?? 0),
            ]);

            $this->json([
                'success' => true,
                'message' => $id === null ? 'Utilisateur créé avec succès.' : 'Utilisateur mis à jour.',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Enregistrement impossible. Vérifiez les données.', 500);
        }
    }

    private function validateUser(array $data, ?int $id, int $companyId): array
    {
        $errors = [];
        $email = strtolower(trim($data['email'] ?? ''));
        $roleId = (int) ($data['role_id'] ?? 0);
        $employeeId = ($data['employee_id'] ?? '') === '' ? null : (int) $data['employee_id'];

        if (trim($data['first_name'] ?? '') === '') {
            $errors['first_name'] = 'Prénom obligatoire.';
        }
        if (trim($data['last_name'] ?? '') === '') {
            $errors['last_name'] = 'Nom obligatoire.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse email invalide.';
        } elseif ($this->users->emailExists($email, $id)) {
            $errors['email'] = 'Cette adresse email est déjà utilisée.';
        }
        if ($id === null && strlen((string) ($data['password'] ?? '')) < 8) {
            $errors['password'] = '8 caractères minimum.';
        }
        if (!$this->isSuperAdmin() && $companyId <= 0) {
            $errors['company_id'] = 'Entreprise obligatoire.';
        }
        if ($roleId <= 0 || !$this->users->roleAllowed($roleId, $companyId, $this->isSuperAdmin())) {
            $errors['role_id'] = 'Rôle non autorisé pour cette entreprise.';
        }
        if ($employeeId !== null && ($companyId <= 0 || !$this->users->employeeAllowed($employeeId, $companyId, $id))) {
            $errors['employee_id'] = 'Employé invalide ou déjà lié à un autre compte.';
        }

        return $errors;
    }

    private function resolvedCompanyId(array $data, ?array $current): int
    {
        $scope = $this->companyScope();
        if ($scope !== null) {
            return $scope;
        }

        return (int) ($data['company_id'] ?? $current['company_id'] ?? 0);
    }

    private function canManageTarget(array $user): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return ($user['role_slug'] ?? '') !== 'super-admin'
            && (int) ($user['company_id'] ?? 0) === (int) $this->companyScope();
    }

    private function companyScope(): ?int
    {
        $user = Auth::user() ?? [];
        return ($user['role_slug'] ?? '') === 'super-admin' ? null : (int) ($user['company_id'] ?? 0);
    }

    private function isSuperAdmin(): bool
    {
        $user = Auth::user() ?? [];
        return ($user['role_slug'] ?? '') === 'super-admin';
    }

    private function requestId(): int
    {
        return (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    }

    private function validCsrfToken(): bool
    {
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        $submittedToken = $_POST['_csrf_token'] ?? '';

        return is_string($sessionToken) && is_string($submittedToken)
            && $sessionToken !== '' && hash_equals($sessionToken, $submittedToken);
    }

    private function jsonError(string $message, int $status = 400, array $errors = []): void
    {
        $this->json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }
}
