<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\MedicalSupport;
use Throwable;

class MedicalController extends Controller
{
    private MedicalSupport $medical;

    public function __construct()
    {
        $this->medical = new MedicalSupport();
    }

    public function index(): void
    {
        $data = $this->pageData([
            'status' => $_GET['status'] ?? '',
            'care_type' => $_GET['care_type'] ?? '',
            'from' => $_GET['from'] ?? date('Y-m-01'),
            'to' => $_GET['to'] ?? date('Y-m-t'),
        ]);

        $this->view('medical.index', $data + [
            'title' => 'Prises en charge medicales',
            'activeMedicalModule' => 'dashboard',
        ]);
    }

    public function requests(): void
    {
        $filters = [
            'status' => $_GET['status'] ?? '',
            'care_type' => $_GET['care_type'] ?? '',
            'from' => $_GET['from'] ?? date('Y-m-01'),
            'to' => $_GET['to'] ?? date('Y-m-t'),
        ];

        $this->view('medical.requests', $this->pageData($filters) + [
            'title' => 'Demandes medicales',
            'activeMedicalModule' => 'requests',
        ]);
    }

    public function dependents(): void
    {
        $this->view('medical.dependents', $this->pageData() + [
            'title' => 'Ayants droit medicaux',
            'activeMedicalModule' => 'dependents',
        ]);
    }

    public function providers(): void
    {
        if (!$this->canManageMedical()) {
            http_response_code(403);
            echo 'Acces refuse';
            return;
        }

        $this->view('medical.providers', $this->pageData() + [
            'title' => 'Prestataires medicaux',
            'activeMedicalModule' => 'providers',
        ]);
    }

    public function settings(): void
    {
        if (!$this->canManageMedical()) {
            http_response_code(403);
            echo 'Acces refuse';
            return;
        }

        $this->view('medical.settings', $this->pageData() + [
            'title' => 'Politique medicale',
            'activeMedicalModule' => 'settings',
        ]);
    }

    public function show(): void
    {
        if ($this->requestId() <= 0) {
            $this->redirect('/medical/requests');
        }

        $request = $this->medical->findDetailed($this->requestId(), $this->companyScope(), $this->selfEmployeeScope());
        if (!$request) {
            http_response_code(404);
            $this->view('errors.404', ['title' => 'Demande medicale introuvable'], 'auth');
            return;
        }

        $this->view('medical.show', [
            'title' => $request['request_number'],
            'request' => $request,
            'claims' => $this->medical->claims((int) $request['id']),
            'careTypes' => MedicalSupport::CARE_TYPES,
            'relationships' => MedicalSupport::RELATIONSHIPS,
            'canManageMedical' => $this->canManageMedical(),
        ]);
    }

    public function storeSettings(): void
    {
        if (!$this->guardPost(true)) {
            return;
        }

        $companyId = $this->resolvedCompanyId($_POST);
        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $this->jsonError('Entreprise non autorisee.', 403);
            return;
        }

        $_POST['company_id'] = $companyId;

        try {
            $id = $this->medical->saveSettings($_POST);
            Auth::log('medical_settings_saved', $companyId, Auth::id(), ['medical_coverage_setting_id' => $id]);
            $this->json(['success' => true, 'message' => 'Politique medicale enregistree.', 'reload' => true]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Enregistrement impossible.', 500);
        }
    }

    public function storeDependent(): void
    {
        if (!$this->guardPost(false)) {
            return;
        }

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $self = $this->selfEmployeeScope();
        if ($self !== null) {
            $employeeId = $self;
            $_POST['employee_id'] = $employeeId;
        }

        $employee = $this->medical->employee($employeeId, $this->companyScope());
        if (!$employee) {
            $this->jsonError('Employe introuvable ou non autorise.', 422);
            return;
        }

        $errors = $this->validateDependent($_POST);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        $_POST['company_id'] = (int) $employee['company_id'];
        $_POST['status'] = $this->canManageMedical() ? ($_POST['status'] ?? 'active') : 'pending';

        try {
            $id = $this->medical->saveDependent($_POST, Auth::id());
            Auth::log('medical_dependent_saved', (int) $employee['company_id'], Auth::id(), ['medical_dependent_id' => $id]);
            $this->json(['success' => true, 'message' => 'Ayant droit enregistre.', 'reload' => true]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Enregistrement impossible.', 500);
        }
    }

    public function storeProvider(): void
    {
        if (!$this->guardPost(true)) {
            return;
        }

        $companyId = $this->resolvedCompanyId($_POST);
        $errors = $this->validateProvider($_POST, $companyId);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        $_POST['company_id'] = $companyId;

        try {
            $id = $this->medical->saveProvider($_POST);
            Auth::log('medical_provider_saved', $companyId, Auth::id(), ['medical_provider_id' => $id]);
            $this->json(['success' => true, 'message' => 'Prestataire medical enregistre.', 'reload' => true]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Creation impossible. Verifiez que le prestataire n existe pas deja.', 500);
        }
    }

    public function storeRequest(): void
    {
        if (!$this->guardPost(false)) {
            return;
        }

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $self = $this->selfEmployeeScope();
        if ($self !== null) {
            $employeeId = $self;
            $_POST['employee_id'] = $employeeId;
        }

        $employee = $this->medical->employee($employeeId, $this->companyScope());
        if (!$employee) {
            $this->jsonError('Employe introuvable ou non autorise.', 422);
            return;
        }

        $_POST['company_id'] = (int) $employee['company_id'];
        $errors = $this->validateRequest($_POST);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        try {
            $id = $this->medical->saveRequest($_POST, Auth::id() ?? 0);
            Auth::log('medical_request_created', (int) $employee['company_id'], Auth::id(), ['medical_request_id' => $id]);
            $this->json(['success' => true, 'message' => 'Demande de prise en charge creee.', 'redirect' => url('/medical/show?id=' . $id)]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError($exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'Creation impossible.', 422);
        }
    }

    public function approve(): void
    {
        if (!$this->guardPost(true)) {
            return;
        }

        try {
            $request = $this->medical->approve($this->requestId(), $this->companyScope(), Auth::id() ?? 0, $this->nullableAmount($_POST['approved_amount'] ?? null));
            if (!$request) {
                $this->jsonError('Demande introuvable ou deja traitee.', 404);
                return;
            }

            Auth::log('medical_request_approved', (int) $request['company_id'], Auth::id(), ['medical_request_id' => (int) $request['id']]);
            $this->json(['success' => true, 'message' => 'Bon de prise en charge emis.', 'reload' => true]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Validation impossible.', 500);
        }
    }

    public function reject(): void
    {
        if (!$this->guardPost(true)) {
            return;
        }

        $reason = trim($_POST['rejection_reason'] ?? '');
        if ($reason === '') {
            $this->jsonError('Motif de refus obligatoire.', 422);
            return;
        }

        $request = $this->medical->reject($this->requestId(), $this->companyScope(), Auth::id() ?? 0, $reason);
        if (!$request) {
            $this->jsonError('Demande introuvable ou deja traitee.', 404);
            return;
        }

        Auth::log('medical_request_rejected', (int) $request['company_id'], Auth::id(), ['medical_request_id' => (int) $request['id']]);
        $this->json(['success' => true, 'message' => 'Demande refusee.', 'reload' => true]);
    }

    public function storeClaim(): void
    {
        if (!$this->guardPost(true)) {
            return;
        }

        $errors = [];
        if (!$this->validDate($_POST['invoice_date'] ?? date('Y-m-d'))) {
            $errors['invoice_date'] = 'Date facture invalide.';
        }
        if ((float) ($_POST['billed_amount'] ?? 0) <= 0) {
            $errors['billed_amount'] = 'Montant facture obligatoire.';
        }
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        try {
            $id = $this->medical->saveClaim($this->requestId(), $this->companyScope(), $_POST);
            if ($id === null) {
                $this->jsonError('Bon introuvable ou non facturable.', 404);
                return;
            }

            Auth::log('medical_claim_validated', null, Auth::id(), ['medical_claim_id' => $id]);
            $this->json(['success' => true, 'message' => 'Facture medicale liquidee.', 'reload' => true]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Liquidation impossible.', 500);
        }
    }

    public function payClaim(): void
    {
        if (!$this->guardPost(true)) {
            return;
        }

        if (!$this->medical->payClaim($this->requestId(), $this->companyScope())) {
            $this->jsonError('Facture introuvable ou deja payee.', 404);
            return;
        }

        $this->json(['success' => true, 'message' => 'Facture marquee payee.', 'reload' => true]);
    }

    public function voucherPdf(): void
    {
        $request = $this->medical->findDetailed($this->requestId(), $this->companyScope(), $this->selfEmployeeScope());
        if (!$request || !in_array($request['status'], ['voucher_issued', 'invoiced', 'validated', 'paid'], true)) {
            http_response_code(404);
            echo 'Bon introuvable';
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="bon-medical-' . preg_replace('/[^a-z0-9_-]+/i', '-', $request['request_number']) . '.pdf"');
        echo $this->simplePdf($this->voucherLines($request));
    }

    private function validateDependent(array $data): array
    {
        $errors = [];
        if ((int) ($data['employee_id'] ?? 0) <= 0) {
            $errors['employee_id'] = 'Employe obligatoire.';
        }
        if (!array_key_exists($data['relationship'] ?? '', MedicalSupport::RELATIONSHIPS)) {
            $errors['relationship'] = 'Lien familial invalide.';
        }
        if (trim($data['first_name'] ?? '') === '') {
            $errors['first_name'] = 'Prenom obligatoire.';
        }
        if (trim($data['last_name'] ?? '') === '') {
            $errors['last_name'] = 'Nom obligatoire.';
        }
        if (!empty($data['birth_date']) && !$this->validDate($data['birth_date'])) {
            $errors['birth_date'] = 'Date de naissance invalide.';
        }
        if (!empty($data['coverage_start']) && !$this->validDate($data['coverage_start'])) {
            $errors['coverage_start'] = 'Debut couverture invalide.';
        }

        return $errors;
    }

    private function pageData(array $filters = []): array
    {
        $scope = $this->companyScope();
        $selfEmployeeId = $this->selfEmployeeScope();
        $filters = array_replace([
            'status' => '',
            'care_type' => '',
            'from' => date('Y-m-01'),
            'to' => date('Y-m-t'),
        ], $filters);
        $defaultCompanyId = $this->defaultCompanyId();
        $settings = $defaultCompanyId > 0 ? $this->medical->settingsForCompany($defaultCompanyId) : [];

        return [
            'dashboard' => $this->medical->dashboard($scope, $selfEmployeeId),
            'requests' => $this->medical->requests($scope, $filters, $selfEmployeeId),
            'dependents' => $this->medical->dependents($scope, $selfEmployeeId),
            'providers' => $this->medical->providers($scope),
            'employees' => $this->medical->employees($scope, $selfEmployeeId),
            'companies' => $this->medical->companies($scope),
            'settings' => $settings,
            'filters' => $filters,
            'careTypes' => MedicalSupport::CARE_TYPES,
            'relationships' => MedicalSupport::RELATIONSHIPS,
            'isSuperAdmin' => $scope === null,
            'canManageMedical' => $this->canManageMedical(),
            'defaultCompanyId' => $defaultCompanyId,
            'selfEmployeeId' => $selfEmployeeId,
        ];
    }

    private function validateProvider(array $data, int $companyId): array
    {
        $errors = [];
        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $errors['company_id'] = 'Entreprise non autorisee.';
        }
        if (trim($data['name'] ?? '') === '') {
            $errors['name'] = 'Nom du prestataire obligatoire.';
        }

        return $errors;
    }

    private function validateRequest(array $data): array
    {
        $errors = [];
        if ((int) ($data['employee_id'] ?? 0) <= 0) {
            $errors['employee_id'] = 'Employe obligatoire.';
        }
        if (!array_key_exists($data['care_type'] ?? '', MedicalSupport::CARE_TYPES)) {
            $errors['care_type'] = 'Type de soin invalide.';
        }
        if ((float) ($data['requested_amount'] ?? 0) <= 0) {
            $errors['requested_amount'] = 'Estimation obligatoire.';
        }

        return $errors;
    }

    private function guardPost(bool $managerOnly): bool
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return false;
        }
        if ($managerOnly && !$this->canManageMedical()) {
            $this->jsonError('Acces refuse.', 403);
            return false;
        }

        return true;
    }

    private function voucherLines(array $request): array
    {
        $employee = trim(($request['last_name'] ?? '') . ' ' . ($request['middle_name'] ?? '') . ' ' . ($request['first_name'] ?? ''));
        $beneficiary = $employee;
        if (!empty($request['dependent_id'])) {
            $beneficiary = trim(($request['dependent_last_name'] ?? '') . ' ' . ($request['dependent_first_name'] ?? ''))
                . ' (' . (MedicalSupport::RELATIONSHIPS[$request['relationship'] ?? 'other'] ?? 'Ayant droit') . ')';
        }

        return [
            'BON DE PRISE EN CHARGE MEDICALE',
            'Reference: ' . ($request['request_number'] ?? '-'),
            'Entreprise: ' . (($request['company_legal_name'] ?? '') ?: ($request['company_name'] ?? '-')),
            'Employe titulaire: ' . $employee . ' / ' . ($request['employee_number'] ?? '-'),
            'Beneficiaire: ' . $beneficiary,
            'Prestataire: ' . ($request['provider_name'] ?? 'Prestataire a confirmer'),
            'Type de soin: ' . (MedicalSupport::CARE_TYPES[$request['care_type'] ?? 'other'] ?? 'Soin medical'),
            'Montant autorise: ' . number_format((float) $request['approved_amount'], 2, ',', ' ') . ' ' . ($request['currency'] ?? 'USD'),
            'Part entreprise: ' . number_format((float) $request['covered_amount'], 2, ',', ' ') . ' ' . ($request['currency'] ?? 'USD'),
            'Part employe: ' . number_format((float) $request['employee_share'], 2, ',', ' ') . ' ' . ($request['currency'] ?? 'USD'),
            'Validite: jusqu au ' . ($request['voucher_expires_at'] ?? '-'),
            'Approbateur: ' . trim(($request['approver_first_name'] ?? '') . ' ' . ($request['approver_last_name'] ?? '')),
            'Observation: ce bon couvre uniquement les soins autorises et reste soumis a la liquidation de facture.',
        ];
    }

    private function simplePdf(array $lines): string
    {
        $content = "BT\n/F1 20 Tf\n50 790 Td\n(" . $this->pdfText($lines[0] ?? 'Bon medical') . ") Tj\n/F1 11 Tf\n0 -34 Td\n";
        foreach (array_slice($lines, 1) as $line) {
            $content .= '(' . $this->pdfText((string) $line) . ") Tj\n0 -22 Td\n";
        }
        $content .= "ET";
        $stream = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        $objects = [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj",
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj",
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj",
            "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj",
            "5 0 obj " . $stream . " endobj",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value) ?: $value);
    }

    private function companyScope(): ?int
    {
        $user = Auth::user() ?? [];
        return ($user['role_slug'] ?? '') === 'super-admin' ? null : (int) ($user['company_id'] ?? 0);
    }

    private function selfEmployeeScope(): ?int
    {
        $user = Auth::user() ?? [];
        if (($user['role_slug'] ?? '') !== 'employe') {
            return null;
        }

        return isset($user['employee_id']) ? (int) $user['employee_id'] : 0;
    }

    private function canManageMedical(): bool
    {
        return Auth::hasRole(['super-admin', 'admin-rh', 'manager']);
    }

    private function resolvedCompanyId(array $data): int
    {
        $scope = $this->companyScope();
        return $scope !== null ? $scope : (int) ($data['company_id'] ?? 0);
    }

    private function canAccessCompany(int $companyId): bool
    {
        $scope = $this->companyScope();
        return $scope === null || $scope === $companyId;
    }

    private function defaultCompanyId(): int
    {
        $scope = $this->companyScope();
        if ($scope !== null) {
            return $scope;
        }

        $companies = $this->medical->companies(null);
        return (int) ($companies[0]['id'] ?? 0);
    }

    private function requestId(): int
    {
        return (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    }

    private function validDate(string $date): bool
    {
        $parsed = date_create_from_format('Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }

    private function nullableAmount($value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
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
