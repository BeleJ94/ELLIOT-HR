<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Position;
use Throwable;

class PositionController extends Controller
{
    private Position $positions;

    public function __construct()
    {
        $this->positions = new Position();
    }

    public function index(): void
    {
        $scope = $this->companyScope();
        $options = $this->positions->formOptions($scope);

        $this->view('positions.index', [
            'title' => 'Postes',
            'options' => $options,
            'isSuperAdmin' => $scope === null,
            'defaultCompanyId' => $scope ?? (int) ($options['companies'][0]['id'] ?? 0),
        ]);
    }

    public function data(): void
    {
        $rows = [];

        foreach ($this->positions->tableRows($this->companyScope()) as $position) {
            $rows[] = [
                'id' => (int) $position['id'],
                'company_id' => (int) $position['company_id'],
                'department_id' => $position['department_id'] !== null ? (int) $position['department_id'] : null,
                'title' => $position['title'],
                'code' => $position['code'] ?: '-',
                'company' => $position['company_name'] ?? '-',
                'department' => $position['department_name'] ?? '-',
                'description' => $position['description'] ?: '-',
                'employees_count' => (int) ($position['employees_count'] ?? 0),
                'actions' => $this->actionButtons((int) $position['id']),
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
        if (!$this->positions->findScoped($id, $this->companyScope())) {
            $this->jsonError('Poste introuvable.', 404);
            return;
        }

        $deleted = $this->positions->softDeleteScoped($id, $this->companyScope());
        if ($deleted) {
            Auth::log('position_deleted', $this->companyScope() ?? null, Auth::id(), ['position_id' => $id]);
        }

        $this->json([
            'success' => $deleted,
            'message' => $deleted ? 'Poste supprime.' : 'Suppression impossible.',
            'reload' => $deleted,
        ], $deleted ? 200 : 400);
    }

    private function persist(?int $id = null): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide. Rechargez la page puis reessayez.', 419);
            return;
        }

        if ($id !== null && !$this->positions->findScoped($id, $this->companyScope())) {
            $this->jsonError('Poste introuvable.', 404);
            return;
        }

        $companyId = $this->resolvedCompanyId($_POST);
        $errors = $this->validatePosition($_POST, $companyId);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        $_POST['company_id'] = $companyId;

        try {
            $positionId = $this->positions->savePosition($_POST, $id);
            Auth::log($id === null ? 'position_created' : 'position_updated', $companyId, Auth::id(), [
                'position_id' => $positionId,
            ]);

            $this->json([
                'success' => true,
                'message' => $id === null ? 'Poste cree.' : 'Poste mis a jour.',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Enregistrement impossible. Verifiez les donnees puis reessayez.', 500);
        }
    }

    private function validatePosition(array $data, int $companyId): array
    {
        $errors = [];
        $departmentId = $this->nullableInt($data['department_id'] ?? null);

        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $errors['company_id'] = 'Entreprise non autorisee.';
        }

        if (trim($data['title'] ?? '') === '') {
            $errors['title'] = 'Titre obligatoire.';
        }

        if (!$this->positions->companyOwnsDepartment($companyId, $departmentId)) {
            $errors['department_id'] = 'Departement invalide pour cette entreprise.';
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
