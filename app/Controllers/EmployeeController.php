<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Employee;
use App\Models\Training;
use Throwable;

class EmployeeController extends Controller
{
    private Employee $employees;
    private Training $trainings;

    public function __construct()
    {
        $this->employees = new Employee();
        $this->trainings = new Training();
    }

    public function index(): void
    {
        $scope = $this->companyScope();
        $filters = [
            'company_id' => $_GET['company_id'] ?? null,
            'branch_id' => $_GET['branch_id'] ?? null,
            'department_id' => $_GET['department_id'] ?? null,
            'position_id' => $_GET['position_id'] ?? null,
            'employment_status' => $_GET['employment_status'] ?? null,
        ];

        $this->view('employees.index', [
            'title' => 'Employes',
            'employees' => $this->employees->allWithDetails($scope, $filters),
            'options' => $this->employees->formOptions($scope),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $scope = $this->companyScope();
        $options = $this->employees->formOptions($scope);
        $defaultCompanyId = $scope ?? (int) ($options['companies'][0]['id'] ?? 0);

        $this->view('employees.create', [
            'title' => 'Nouvel employe',
            'employee' => [
                'company_id' => $defaultCompanyId,
                'employee_number' => $defaultCompanyId > 0 ? $this->employees->generateEmployeeNumber($defaultCompanyId) : '',
                'hire_date' => date('Y-m-d'),
                'employment_status' => 'active',
                'contract_type' => 'cdi',
                'currency' => 'USD',
            ],
            'options' => $options,
        ]);
    }

    public function store(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide. Rechargez la page puis reessayez.', 419);
            return;
        }

        $companyId = (int) ($_POST['company_id'] ?? 0);
        $errors = $this->validateEmployee($_POST, null, $companyId);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        if (!$this->canAccessCompany($companyId)) {
            $this->jsonError('Acces refuse pour cette entreprise.', 403);
            return;
        }

        try {
            $photoPath = $this->uploadFile('photo', 'photos', ['image/jpeg', 'image/png', 'image/webp']);
            $id = $this->employees->saveEmployee($_POST, null, $photoPath);
            Auth::log('employee_created', $companyId, Auth::id(), ['employee_id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Employe cree avec succes.',
                'redirect' => url('/employees/show?id=' . $id),
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Creation impossible. Verifiez les donnees puis reessayez.', 500);
        }
    }

    public function show(): void
    {
        $id = $this->requestId();
        $employee = $this->employees->findDetailed($id, $this->companyScope());

        if (!$employee) {
            http_response_code(404);
            $this->view('errors.404', ['title' => 'Employe introuvable'], 'auth');
            return;
        }

        $this->view('employees.show', [
            'title' => $employee['first_name'] . ' ' . $employee['last_name'],
            'employee' => $employee,
            'documents' => $this->employees->documents($id),
            'trainingHistory' => $this->trainings->employeeHistory($id, $this->companyScope()),
        ]);
    }

    public function edit(): void
    {
        $id = $this->requestId();
        $employee = $this->employees->findDetailed($id, $this->companyScope());

        if (!$employee) {
            http_response_code(404);
            $this->view('errors.404', ['title' => 'Employe introuvable'], 'auth');
            return;
        }

        $this->view('employees.edit', [
            'title' => 'Modifier employe',
            'employee' => $employee,
            'options' => $this->employees->formOptions($this->companyScope()),
        ]);
    }

    public function update(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide. Rechargez la page puis reessayez.', 419);
            return;
        }

        $id = $this->requestId();
        $current = $this->employees->findDetailed($id, $this->companyScope());
        if (!$current) {
            $this->jsonError('Employe introuvable.', 404);
            return;
        }

        $companyId = (int) ($_POST['company_id'] ?? 0);
        if (!$this->canAccessCompany($companyId)) {
            $this->jsonError('Acces refuse pour cette entreprise.', 403);
            return;
        }

        $errors = $this->validateEmployee($_POST, $id, $companyId);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        try {
            $photoPath = $this->uploadFile('photo', 'photos', ['image/jpeg', 'image/png', 'image/webp']);
            $this->employees->saveEmployee($_POST, $id, $photoPath);
            Auth::log('employee_updated', $companyId, Auth::id(), ['employee_id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Employe mis a jour.',
                'redirect' => url('/employees/show?id=' . $id),
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Mise a jour impossible.', 500);
        }
    }

    public function archive(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        $current = $this->employees->findDetailed($id, $this->companyScope());
        if (!$current) {
            $this->jsonError('Employe introuvable.', 404);
            return;
        }

        $archived = $this->employees->archive($id);
        if ($archived) {
            Auth::log('employee_archived', (int) $current['company_id'], Auth::id(), ['employee_id' => $id]);
        }

        $this->json([
            'success' => $archived,
            'message' => $archived ? 'Employe archive.' : 'Archivage impossible.',
        ], $archived ? 200 : 400);
    }

    public function uploadDocument(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        $employee = $this->employees->findDetailed($id, $this->companyScope());
        if (!$employee) {
            $this->jsonError('Employe introuvable.', 404);
            return;
        }

        try {
            $path = $this->uploadFile('document', 'documents', [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);

            if ($path === null) {
                $this->jsonError('Selectionnez un fichier.', 422);
                return;
            }

            $file = $_FILES['document'];
            $documentId = $this->employees->addDocument((int) $employee['company_id'], $id, [
                'document_type' => $_POST['document_type'] ?? 'document',
                'title' => $_POST['title'] ?? $file['name'],
                'file_name' => $file['name'],
                'mime_type' => $file['type'] ?? null,
                'expires_at' => $_POST['expires_at'] ?? null,
            ], $path);

            Auth::log('employee_document_uploaded', (int) $employee['company_id'], Auth::id(), ['document_id' => $documentId]);

            $this->json([
                'success' => true,
                'message' => 'Document ajoute.',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Upload impossible.', 500);
        }
    }

    private function validateEmployee(array $data, ?int $employeeId = null, ?int $companyId = null): array
    {
        $errors = [];
        $companyId ??= (int) ($data['company_id'] ?? 0);

        if ($companyId <= 0) {
            $errors['company_id'] = 'Entreprise obligatoire.';
        }

        if (trim($data['first_name'] ?? '') === '') {
            $errors['first_name'] = 'Prenom obligatoire.';
        }

        if (trim($data['last_name'] ?? '') === '') {
            $errors['last_name'] = 'Nom obligatoire.';
        }

        $email = trim($data['email'] ?? '');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalide.';
        }

        if ($companyId > 0) {
            $branchId = $this->nullableInt($data['branch_id'] ?? null);
            $departmentId = $this->nullableInt($data['department_id'] ?? null);
            $positionId = $this->nullableInt($data['position_id'] ?? null);
            $managerId = $this->nullableInt($data['manager_id'] ?? null);

            if (!$this->employees->companyOwnsBranch($companyId, $branchId)) {
                $errors['branch_id'] = 'Site invalide pour cette entreprise.';
            }

            if (!$this->employees->companyOwnsDepartment($companyId, $departmentId)) {
                $errors['department_id'] = 'Departement invalide pour cette entreprise.';
            }

            if (!$this->employees->companyOwnsPosition($companyId, $positionId)) {
                $errors['position_id'] = 'Poste invalide pour cette entreprise.';
            }

            if (!$this->employees->companyOwnsManager($companyId, $managerId, $employeeId)) {
                $errors['manager_id'] = 'Manager invalide pour cette entreprise.';
            }
        }

        return $errors;
    }

    private function uploadFile(string $field, string $folder, array $allowedTypes): ?string
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES[$field];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Erreur upload.');
        }

        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new \RuntimeException('Fichier trop volumineux.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']) ?: '';
        $originalExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $docxMimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        if ($mimeType === 'application/zip' && $originalExtension === 'docx' && in_array($docxMimeType, $allowedTypes, true)) {
            $mimeType = $docxMimeType;
        }

        if (!in_array($mimeType, $allowedTypes, true)) {
            throw new \RuntimeException('Type de fichier non autorise.');
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            $docxMimeType => 'docx',
        ];

        $extension = $extensions[$mimeType] ?? $originalExtension;
        $filename = uniqid($folder . '_', true) . ($extension ? '.' . strtolower($extension) : '');
        $relative = 'uploads/employees/' . $folder . '/' . $filename;
        $target = BASE_PATH . '/public/' . $relative;
        $targetDir = dirname($target);

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Impossible de creer le dossier upload.');
        }

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Impossible de deplacer le fichier.');
        }

        return 'public/' . $relative;
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
