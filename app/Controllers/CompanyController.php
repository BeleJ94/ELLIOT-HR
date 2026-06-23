<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Company;
use Throwable;

class CompanyController extends Controller
{
    private Company $companies;

    public function __construct()
    {
        $this->companies = new Company();
    }

    public function index(): void
    {
        $scope = $this->companyScope();
        $this->view('companies.index', [
            'title' => $this->isSuperAdmin() ? 'Entreprises' : 'Mon entreprise',
            'companies' => $this->companies->allWithStats($scope),
            'canCreateCompany' => $this->isSuperAdmin(),
            'isSuperAdmin' => $this->isSuperAdmin(),
        ]);
    }

    public function create(): void
    {
        if (!$this->requireSuperAdmin()) {
            return;
        }

        $this->view('companies.create', [
            'title' => 'Nouvelle entreprise',
            'company' => null,
        ]);
    }

    public function store(): void
    {
        if (!$this->requireSuperAdmin(true)) {
            return;
        }

        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide. Rechargez la page puis reessayez.', 419);
            return;
        }

        $errors = $this->validateCompany($_POST);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        try {
            $id = $this->companies->saveCompany($_POST);
            Auth::log('company_created', $id, Auth::id(), ['company_id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Entreprise creee avec succes.',
                'redirect' => url('/companies/show?id=' . $id),
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Creation impossible. Verifiez les donnees puis reessayez.', 500);
        }
    }

    public function show(): void
    {
        $id = $this->requestId();
        if (!$this->canAccessCompany($id)) {
            http_response_code(403);
            echo 'Acces refuse';
            return;
        }

        $company = $this->companies->findDetailed($id);

        if (!$company) {
            http_response_code(404);
            $this->view('errors.404', ['title' => 'Entreprise introuvable'], 'auth');
            return;
        }

        $this->view('companies.show', [
            'title' => $company['name'],
            'company' => $company,
            'branches' => $this->companies->branches($id),
            'plans' => $this->companies->subscriptionPlans(),
        ]);
    }

    public function edit(): void
    {
        $id = $this->requestId();
        if (!$this->canAccessCompany($id)) {
            http_response_code(403);
            echo 'Acces refuse';
            return;
        }

        $company = $this->companies->findDetailed($id);

        if (!$company) {
            http_response_code(404);
            $this->view('errors.404', ['title' => 'Entreprise introuvable'], 'auth');
            return;
        }

        $this->view('companies.edit', [
            'title' => 'Modifier ' . $company['name'],
            'company' => $company,
        ]);
    }

    public function update(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide. Rechargez la page puis reessayez.', 419);
            return;
        }

        $id = $this->requestId();
        if (!$this->canAccessCompany($id)) {
            $this->jsonError('Acces refuse pour cette entreprise.', 403);
            return;
        }

        $errors = $this->validateCompany($_POST);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        try {
            $this->companies->saveCompany($_POST, $id);
            Auth::log('company_updated', $id, Auth::id(), ['company_id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Entreprise mise a jour.',
                'redirect' => url('/companies/show?id=' . $id),
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Mise a jour impossible.', 500);
        }
    }

    public function delete(): void
    {
        if (!$this->requireSuperAdmin(true)) {
            return;
        }

        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        if (!$this->canAccessCompany($id)) {
            $this->jsonError('Acces refuse pour cette entreprise.', 403);
            return;
        }

        $deleted = $this->companies->softDelete($id);

        if ($deleted) {
            Auth::log('company_deleted', $id, Auth::id(), ['company_id' => $id]);
        }

        $this->json([
            'success' => $deleted,
            'message' => $deleted ? 'Entreprise supprimee.' : 'Entreprise introuvable.',
        ], $deleted ? 200 : 404);
    }

    public function toggleStatus(): void
    {
        if (!$this->requireSuperAdmin(true)) {
            return;
        }

        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        if (!$this->canAccessCompany($id)) {
            $this->jsonError('Acces refuse pour cette entreprise.', 403);
            return;
        }

        $status = $_POST['status'] ?? 'active';
        $updated = $this->companies->updateStatus($id, $status);

        if ($updated) {
            Auth::log('company_status_changed', $id, Auth::id(), ['status' => $status]);
        }

        $this->json([
            'success' => $updated,
            'message' => $updated ? 'Statut mis a jour.' : 'Statut non modifie.',
            'status' => $status,
        ]);
    }

    public function storeBranch(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $companyId = $this->requestId();
        if (!$this->canAccessCompany($companyId)) {
            $this->jsonError('Acces refuse pour cette entreprise.', 403);
            return;
        }

        $name = trim($_POST['name'] ?? '');

        if ($name === '') {
            $this->jsonError('Le nom de l agence est obligatoire.', 422, ['name' => 'Obligatoire']);
            return;
        }

        try {
            $branchId = $this->companies->saveBranch($companyId, $_POST);
            Auth::log('company_branch_created', $companyId, Auth::id(), ['branch_id' => $branchId]);

            $this->json([
                'success' => true,
                'message' => 'Agence ajoutee.',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Ajout de l agence impossible.', 500);
        }
    }

    public function deleteBranch(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $branchId = (int) ($_POST['branch_id'] ?? $_GET['branch_id'] ?? 0);
        $deleted = $branchId > 0 && $this->companies->deleteBranch($branchId, $this->companyScope());

        $this->json([
            'success' => $deleted,
            'message' => $deleted ? 'Agence supprimee.' : 'Agence introuvable.',
            'reload' => $deleted,
        ], $deleted ? 200 : 404);
    }

    public function updateSubscription(): void
    {
        if (!$this->requireSuperAdmin(true)) {
            return;
        }

        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $companyId = $this->requestId();
        if (!$this->canAccessCompany($companyId)) {
            $this->jsonError('Acces refuse pour cette entreprise.', 403);
            return;
        }

        if ((int) ($_POST['subscription_plan_id'] ?? 0) <= 0) {
            $this->jsonError('Selectionnez un plan valide.', 422, ['subscription_plan_id' => 'Obligatoire']);
            return;
        }

        try {
            $subscriptionId = $this->companies->saveSubscription($companyId, $_POST);
            Auth::log('company_subscription_updated', $companyId, Auth::id(), ['subscription_id' => $subscriptionId]);

            $this->json([
                'success' => true,
                'message' => 'Abonnement mis a jour.',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Mise a jour de l abonnement impossible.', 500);
        }
    }

    private function validateCompany(array $data): array
    {
        $errors = [];

        if (trim($data['name'] ?? '') === '') {
            $errors['name'] = 'Le nom est obligatoire.';
        }

        $email = trim($data['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse email invalide.';
        }

        return $errors;
    }

    private function requestId(): int
    {
        return (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    }

    private function companyScope(): ?int
    {
        $user = Auth::user() ?? [];

        if (($user['role_slug'] ?? '') === 'super-admin') {
            return null;
        }

        return isset($user['company_id']) ? (int) $user['company_id'] : 0;
    }

    private function isSuperAdmin(): bool
    {
        $user = Auth::user() ?? [];

        return ($user['role_slug'] ?? '') === 'super-admin';
    }

    private function requireSuperAdmin(bool $json = false): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($json) {
            $this->jsonError('Cette opération est réservée au Super Administrateur.', 403);
            return false;
        }

        http_response_code(403);
        echo 'Accès refusé';
        return false;
    }

    private function canAccessCompany(int $companyId): bool
    {
        $scope = $this->companyScope();

        return $scope === null || $companyId === $scope;
    }

    private function validCsrfToken(): bool
    {
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        $submittedToken = $_POST['_csrf_token'] ?? '';

        return is_string($sessionToken)
            && is_string($submittedToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $submittedToken);
    }

    private function jsonError(string $message, int $status = 400, array $errors = []): void
    {
        $this->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
