<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Throwable;

class LeaveController extends Controller
{
    private LeaveRequest $requests;
    private LeaveType $types;

    public function __construct()
    {
        $this->requests = new LeaveRequest();
        $this->types = new LeaveType();
    }

    public function index(): void
    {
        $scope = $this->companyScope();
        $this->types->ensureDefaults($scope);

        $filters = $this->applyEmployeeSelfScope([
            'employee_id' => $_GET['employee_id'] ?? null,
            'department_id' => $_GET['department_id'] ?? null,
            'leave_type_id' => $_GET['leave_type_id'] ?? null,
            'status' => $_GET['status'] ?? null,
            'from' => $_GET['from'] ?? date('Y-m-01'),
            'to' => $_GET['to'] ?? date('Y-m-t'),
        ]);
        $month = substr($filters['from'] ?: date('Y-m-d'), 0, 7);

        $this->view('leaves.index', [
            'title' => 'Conges et absences',
            'requests' => $this->requests->tableRows($scope, $filters),
            'types' => $this->types->allForCompany($scope),
            'balances' => $this->requests->balanceRows($scope, $this->userEmployeeId()),
            'calendarRows' => $this->requests->calendarRows($scope, $month),
            'employees' => $this->requests->employees($scope, $this->userEmployeeId()),
            'departments' => $this->userEmployeeId() === null ? $this->requests->departments($scope) : [],
            'filters' => $filters,
            'month' => $month,
            'canManageTypes' => $this->canManageTypes(),
            'isSuperAdmin' => $scope === null,
            'companies' => $this->types->companies($scope),
            'defaultCompanyId' => $this->defaultCompanyId(),
        ]);
    }

    public function create(): void
    {
        $scope = $this->companyScope();
        $this->types->ensureDefaults($scope);

        $this->view('leaves.create', [
            'title' => 'Demande de conge',
            'types' => $this->types->allForCompany($scope),
            'employees' => $this->requests->employees($scope, $this->userEmployeeId()),
            'isEmployeeSelf' => $this->userEmployeeId() !== null,
            'defaultEmployeeId' => $this->userEmployeeId(),
        ]);
    }

    public function approval(): void
    {
        $scope = $this->companyScope();

        $this->view('leaves.approval', [
            'title' => 'Validation des conges',
            'managerRows' => $this->canManagerApprove() ? $this->requests->pendingRows($scope, 'manager') : [],
            'hrRows' => $this->canHrApprove() ? $this->requests->pendingRows($scope, 'hr') : [],
            'canManagerApprove' => $this->canManagerApprove(),
            'canHrApprove' => $this->canHrApprove(),
        ]);
    }

    public function store(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $selfEmployeeId = $this->userEmployeeId();
        if ($selfEmployeeId !== null) {
            $employeeId = $selfEmployeeId;
            $_POST['employee_id'] = $employeeId;
        }

        $employee = $this->requests->employee($employeeId, $this->companyScope());
        if (!$employee) {
            $this->jsonError('Employe introuvable ou non autorise.', 422);
            return;
        }

        $_POST['company_id'] = (int) $employee['company_id'];
        $_POST['leave_type_id'] = $this->types->resolveForCompany((int) $employee['company_id'], (int) ($_POST['leave_type_id'] ?? 0)) ?? 0;
        $errors = $this->validateRequest($_POST);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        try {
            $id = $this->requests->saveRequest($_POST);
            Auth::log('leave_requested', (int) $employee['company_id'], Auth::id(), ['leave_request_id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Demande de conge enregistree.',
                'redirect' => url('/leaves'),
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Enregistrement impossible.', 500);
        }
    }

    public function storeType(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        if (!$this->canManageTypes()) {
            $this->jsonError('Acces refuse.', 403);
            return;
        }

        $companyId = $this->resolvedCompanyId($_POST);
        $errors = $this->validateType($_POST, $companyId);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        $_POST['company_id'] = $companyId;

        try {
            $id = $this->types->saveType($_POST);
            Auth::log('leave_type_created', $companyId, Auth::id(), ['leave_type_id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Type de conge ajoute.',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Creation impossible. Le code existe peut-etre deja.', 500);
        }
    }

    public function approveManager(): void
    {
        if (!$this->canManagerApprove()) {
            $this->jsonError('Acces refuse.', 403);
            return;
        }

        $this->approve('manager');
    }

    public function approveHr(): void
    {
        if (!$this->canHrApprove()) {
            $this->jsonError('Acces refuse.', 403);
            return;
        }

        $this->approve('hr');
    }

    public function reject(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        if (!$this->canManagerApprove() && !$this->canHrApprove()) {
            $this->jsonError('Acces refuse.', 403);
            return;
        }

        $id = $this->requestId();
        $reason = trim($_POST['rejection_reason'] ?? '');
        if ($reason === '') {
            $this->jsonError('Le motif de refus est obligatoire.', 422);
            return;
        }

        try {
            $request = $this->requests->reject($id, $this->companyScope(), Auth::id() ?? 0, $reason);
            if (!$request) {
                $this->jsonError('Demande introuvable.', 404);
                return;
            }

            Auth::log('leave_rejected', (int) $request['company_id'], Auth::id(), ['leave_request_id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Demande refusee.',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Refus impossible.', 500);
        }
    }

    private function approve(string $stage): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();

        try {
            $request = $stage === 'manager'
                ? $this->requests->approveManager($id, $this->companyScope(), Auth::id() ?? 0)
                : $this->requests->approveHr($id, $this->companyScope(), Auth::id() ?? 0);

            if (!$request) {
                $this->jsonError($stage === 'hr' ? 'Validation RH impossible avant validation manager.' : 'Demande introuvable.', 404);
                return;
            }

            Auth::log($stage === 'manager' ? 'leave_manager_approved' : 'leave_hr_approved', (int) $request['company_id'], Auth::id(), [
                'leave_request_id' => $id,
            ]);

            $this->json([
                'success' => true,
                'message' => $stage === 'manager' ? 'Validation manager enregistree.' : 'Validation RH enregistree.',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Validation impossible.', 500);
        }
    }

    private function validateRequest(array $data): array
    {
        $errors = [];
        $companyId = (int) ($data['company_id'] ?? 0);
        $typeId = (int) ($data['leave_type_id'] ?? 0);
        $start = $data['start_date'] ?? '';
        $end = $data['end_date'] ?? '';

        if ($companyId <= 0) {
            $errors['company_id'] = 'Entreprise obligatoire.';
        }

        if ($typeId <= 0 || ($companyId > 0 && !$this->types->companyOwnsType($companyId, $typeId))) {
            $errors['leave_type_id'] = 'Type de conge invalide.';
        }

        if (!$this->validDate($start)) {
            $errors['start_date'] = 'Date debut invalide.';
        }

        if (!$this->validDate($end)) {
            $errors['end_date'] = 'Date fin invalide.';
        }

        if ($start !== '' && $end !== '' && $end < $start) {
            $errors['end_date'] = 'La date fin doit suivre le debut.';
        }

        if (trim($data['reason'] ?? '') === '') {
            $errors['reason'] = 'Motif obligatoire.';
        }

        return $errors;
    }

    private function validateType(array $data, int $companyId): array
    {
        $errors = [];

        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $errors['company_id'] = 'Entreprise non autorisee.';
        }

        if (trim($data['name'] ?? '') === '') {
            $errors['name'] = 'Nom obligatoire.';
        }

        if (trim($data['code'] ?? '') === '') {
            $errors['code'] = 'Code obligatoire.';
        }

        if ((float) ($data['annual_days'] ?? 0) < 0) {
            $errors['annual_days'] = 'Solde annuel invalide.';
        }

        return $errors;
    }

    private function companyScope(): ?int
    {
        $user = Auth::user() ?? [];

        if (($user['role_slug'] ?? '') === 'super-admin') {
            return null;
        }

        return isset($user['company_id']) ? (int) $user['company_id'] : 0;
    }

    private function applyEmployeeSelfScope(array $filters): array
    {
        $employeeId = $this->userEmployeeId();
        if ($employeeId !== null && !$this->canManageLeaves()) {
            $filters['employee_id'] = $employeeId;
            $filters['department_id'] = null;
        }

        return $filters;
    }

    private function userEmployeeId(): ?int
    {
        $user = Auth::user() ?? [];

        if (($user['role_slug'] ?? '') !== 'employe') {
            return null;
        }

        return isset($user['employee_id']) ? (int) $user['employee_id'] : 0;
    }

    private function canManageLeaves(): bool
    {
        return Auth::hasRole(['super-admin', 'admin-rh', 'manager']);
    }

    private function canManagerApprove(): bool
    {
        return Auth::hasRole(['super-admin', 'admin-rh', 'manager']);
    }

    private function canHrApprove(): bool
    {
        return Auth::hasRole(['super-admin', 'admin-rh']);
    }

    private function canManageTypes(): bool
    {
        return Auth::hasRole(['super-admin', 'admin-rh']);
    }

    private function resolvedCompanyId(array $data): int
    {
        $scope = $this->companyScope();
        if ($scope !== null) {
            return $scope;
        }

        return (int) ($data['company_id'] ?? 0);
    }

    private function canAccessCompany(int $companyId): bool
    {
        $scope = $this->companyScope();

        return $scope === null || $companyId === $scope;
    }

    private function defaultCompanyId(): int
    {
        $scope = $this->companyScope();
        if ($scope !== null) {
            return $scope;
        }

        $companies = $this->types->companies(null);
        return (int) ($companies[0]['id'] ?? 0);
    }

    private function validDate(string $date): bool
    {
        $parsed = date_create_from_format('Y-m-d', $date);

        return $parsed && $parsed->format('Y-m-d') === $date;
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

    private function jsonError(string $message, int $status = 400, array $errors = []): void
    {
        $this->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
