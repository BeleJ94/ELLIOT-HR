<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Department;
use Throwable;

class DepartmentController extends Controller
{
    private Department $departments;

    public function __construct()
    {
        $this->departments = new Department();
    }

    public function index(): void
    {
        $scope = $this->companyScope();

        $this->view('departments.index', [
            'title' => 'Departements',
            'options' => $this->departments->formOptions($scope),
            'orgChart' => $this->departments->orgChart($scope),
            'isSuperAdmin' => $scope === null,
            'defaultCompanyId' => $this->defaultCompanyId($this->departments->formOptions($scope)),
        ]);
    }

    public function data(): void
    {
        $rows = [];

        foreach ($this->departments->tableRows($this->companyScope()) as $department) {
            $manager = trim(($department['manager_last_name'] ?? '') . ' ' . ($department['manager_first_name'] ?? ''));
            $rows[] = [
                'id' => (int) $department['id'],
                'company_id' => (int) $department['company_id'],
                'branch_id' => $department['branch_id'] !== null ? (int) $department['branch_id'] : null,
                'manager_id' => $department['manager_id'] !== null ? (int) $department['manager_id'] : null,
                'name' => $department['name'],
                'code' => $department['code'] ?: '-',
                'company' => $department['company_name'] ?? '-',
                'branch' => $department['branch_name'] ?? '-',
                'manager' => $manager !== '' ? $manager : '-',
                'positions_count' => (int) ($department['positions_count'] ?? 0),
                'employees_count' => (int) ($department['employees_count'] ?? 0),
                'actions' => $this->actionButtons((int) $department['id']),
            ];
        }

        $this->json(['data' => $rows]);
    }

    public function store(): void
    {
        $this->persist();
    }

    public function update(): void
    {
        $this->persist($this->requestId());
    }

    public function delete(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        if (!$this->departments->findScoped($id, $this->companyScope())) {
            $this->jsonError('Departement introuvable.', 404);
            return;
        }

        $deleted = $this->departments->softDeleteScoped($id, $this->companyScope());
        if ($deleted) {
            Auth::log('department_deleted', $this->logCompanyId(), Auth::id(), ['department_id' => $id]);
        }

        $this->json([
            'success' => $deleted,
            'message' => $deleted ? 'Departement supprime.' : 'Suppression impossible.',
            'reload' => $deleted,
        ], $deleted ? 200 : 400);
    }

    private function persist(?int $id = null): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide. Rechargez la page puis reessayez.', 419);
            return;
        }

        if ($id !== null && !$this->departments->findScoped($id, $this->companyScope())) {
            $this->jsonError('Departement introuvable.', 404);
            return;
        }

        $companyId = $this->resolvedCompanyId($_POST);
        $errors = $this->validateDepartment($_POST, $companyId);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        $_POST['company_id'] = $companyId;

        try {
            $departmentId = $this->departments->saveDepartment($_POST, $id);
            Auth::log($id === null ? 'department_created' : 'department_updated', $companyId, Auth::id(), [
                'department_id' => $departmentId,
            ]);

            $this->json([
                'success' => true,
                'message' => $id === null ? 'Departement cree.' : 'Departement mis a jour.',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Enregistrement impossible. Verifiez les donnees puis reessayez.', 500);
        }
    }

    private function validateDepartment(array $data, int $companyId): array
    {
        $errors = [];
        $branchId = $this->nullableInt($data['branch_id'] ?? null);
        $managerId = $this->nullableInt($data['manager_id'] ?? null);

        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $errors['company_id'] = 'Entreprise non autorisee.';
        }

        if (trim($data['name'] ?? '') === '') {
            $errors['name'] = 'Nom obligatoire.';
        }

        if (!$this->departments->companyOwnsBranch($companyId, $branchId)) {
            $errors['branch_id'] = 'Site invalide pour cette entreprise.';
        }

        if (!$this->departments->companyOwnsManager($companyId, $managerId)) {
            $errors['manager_id'] = 'Manager invalide pour cette entreprise.';
        }

        return $errors;
    }

    private function actionButtons(int $id): string
    {
        return '<div class="btn-list flex-nowrap">'
            . '<button class="btn btn-icon" type="button" data-org-edit="' . e((string) $id) . '" title="Modifier">' . icon('settings') . '</button>'
            . '<button class="btn btn-icon btn-outline-danger" type="button" data-org-delete="' . e((string) $id) . '" title="Supprimer">' . icon('x') . '</button>'
            . '</div>';
    }

    private function resolvedCompanyId(array $data): int
    {
        $scope = $this->companyScope();
        if ($scope !== null) {
            return $scope;
        }

        return (int) ($data['company_id'] ?? 0);
    }

    private function companyScope(): ?int
    {
        $user = Auth::user() ?? [];

        if (($user['role_slug'] ?? '') === 'super-admin') {
            return null;
        }

        return isset($user['company_id']) ? (int) $user['company_id'] : 0;
    }

    private function canAccessCompany(int $companyId): bool
    {
        $scope = $this->companyScope();

        return $scope === null || $companyId === $scope;
    }

    private function defaultCompanyId(array $options): int
    {
        $scope = $this->companyScope();

        return $scope ?? (int) ($options['companies'][0]['id'] ?? 0);
    }

    private function logCompanyId(): ?int
    {
        return $this->companyScope() ?? null;
    }

    private function requestId(): int
    {
        return (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
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

    private function nullableInt($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
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
