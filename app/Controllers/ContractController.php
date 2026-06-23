<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Contract;
use Throwable;

class ContractController extends Controller
{
    private Contract $contracts;

    public function __construct()
    {
        $this->contracts = new Contract();
    }

    public function index(): void
    {
        $scope = $this->companyScope();

        $this->view('contracts.index', [
            'title' => 'Contrats RH',
            'alerts' => $this->contracts->expiringSoon($scope),
        ]);
    }

    public function data(): void
    {
        $rows = [];

        foreach ($this->contracts->allWithDetails($this->companyScope()) as $contract) {
            $employee = trim(($contract['last_name'] ?? '') . ' ' . ($contract['middle_name'] ?? '') . ' ' . ($contract['first_name'] ?? ''));
            $rows[] = [
                'id' => (int) $contract['id'],
                'contract_number' => $contract['contract_number'],
                'employee' => $employee,
                'employee_number' => $contract['employee_number'] ?? '-',
                'company' => $contract['company_name'] ?? '-',
                'position' => $contract['position_title'] ?? '-',
                'contract_type' => $contract['contract_type'],
                'contract_type_label' => $this->typeLabel($contract['contract_type'] ?? ''),
                'start_date' => $contract['start_date'] ?: '-',
                'end_date' => $contract['end_date'] ?: '-',
                'probation_ends_at' => $contract['probation_ends_at'] ?: '-',
                'base_salary' => number_format((float) ($contract['base_salary'] ?? 0), 2, ',', ' ') . ' ' . ($contract['currency'] ?? 'USD'),
                'status' => $contract['status'],
                'status_label' => $this->statusLabel($contract['status'] ?? ''),
                'status_tone' => $this->statusTone($contract['status'] ?? ''),
                'expires_soon' => $this->expiresSoon($contract),
                'actions' => $this->actionButtons((int) $contract['id']),
            ];
        }

        $this->json(['data' => $rows]);
    }

    public function create(): void
    {
        $scope = $this->companyScope();
        $options = $this->contracts->formOptions($scope);
        $defaultCompanyId = $scope ?? 0;

        $this->view('contracts.create', [
            'title' => 'Nouveau contrat',
            'contract' => [
                'company_id' => $defaultCompanyId,
                'employee_id' => (int) ($_GET['employee_id'] ?? 0),
                'contract_number' => $defaultCompanyId > 0 ? $this->contracts->generateContractNumber($defaultCompanyId) : '',
                'contract_type' => 'cdi',
                'start_date' => date('Y-m-d'),
                'status' => 'active',
                'currency' => 'USD',
            ],
            'options' => $options,
            'isSuperAdmin' => $scope === null,
        ]);
    }

    public function store(): void
    {
        $this->persist();
    }

    public function edit(): void
    {
        $id = $this->requestId();
        $contract = $this->contracts->findDetailed($id, $this->companyScope());

        if (!$contract) {
            http_response_code(404);
            $this->view('errors.404', ['title' => 'Contrat introuvable'], 'auth');
            return;
        }

        $this->view('contracts.edit', [
            'title' => 'Modifier contrat',
            'contract' => $contract,
            'options' => $this->contracts->formOptions($this->companyScope()),
            'isSuperAdmin' => $this->companyScope() === null,
        ]);
    }

    public function update(): void
    {
        $this->persist($this->requestId());
    }

    public function show(): void
    {
        $id = $this->requestId();
        $contract = $this->contracts->findDetailed($id, $this->companyScope());

        if (!$contract) {
            http_response_code(404);
            $this->view('errors.404', ['title' => 'Contrat introuvable'], 'auth');
            return;
        }

        $this->view('contracts.show', [
            'title' => $contract['contract_number'],
            'contract' => $contract,
            'alerts' => $this->contracts->expiringSoon($this->companyScope()),
        ]);
    }

    public function renew(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();

        try {
            $newId = $this->contracts->renew($id, $_POST, $this->companyScope());
            if ($newId === null) {
                $this->jsonError('Contrat introuvable.', 404);
                return;
            }

            Auth::log('contract_renewed', $this->logCompanyId(), Auth::id(), ['contract_id' => $id, 'new_contract_id' => $newId]);
            $this->json([
                'success' => true,
                'message' => 'Contrat renouvele.',
                'redirect' => url('/contracts/show?id=' . $newId),
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Renouvellement impossible.', 500);
        }
    }

    public function expire(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $count = $this->contracts->expireOverdue($this->companyScope());
        $this->json([
            'success' => true,
            'message' => $count . ' contrat(s) expire(s) automatiquement.',
            'reload' => true,
        ]);
    }

    public function generatePdf(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        $contract = $this->contracts->findDetailed($id, $this->companyScope());

        if (!$contract) {
            $this->jsonError('Contrat introuvable.', 404);
            return;
        }

        try {
            $path = $this->writeContractPdf($contract);
            $this->contracts->updatePdfPath($id, $path);
            Auth::log('contract_pdf_generated', (int) $contract['company_id'], Auth::id(), ['contract_id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'PDF genere.',
                'pdf_url' => url($path),
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Generation PDF impossible.', 500);
        }
    }

    public function uploadSigned(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        $contract = $this->contracts->findDetailed($id, $this->companyScope());

        if (!$contract) {
            $this->jsonError('Contrat introuvable.', 404);
            return;
        }

        try {
            [$path, $name, $mime] = $this->uploadFile('signed_contract', 'signed', [
                'application/pdf',
                'image/jpeg',
                'image/png',
            ]);

            $this->contracts->updateSignedContract($id, $path, $name, $mime);
            Auth::log('contract_signed_uploaded', (int) $contract['company_id'], Auth::id(), ['contract_id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Contrat signe ajoute.',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Upload impossible.', 500);
        }
    }

    private function persist(?int $id = null): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide. Rechargez la page puis reessayez.', 419);
            return;
        }

        if ($id !== null && !$this->contracts->findDetailed($id, $this->companyScope())) {
            $this->jsonError('Contrat introuvable.', 404);
            return;
        }

        $companyId = $this->resolvedCompanyId($_POST);
        $_POST['company_id'] = $companyId;
        $errors = $this->validateContract($_POST);

        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        try {
            $contractId = $this->contracts->saveContract($_POST, $id);
            Auth::log($id === null ? 'contract_created' : 'contract_updated', $companyId, Auth::id(), ['contract_id' => $contractId]);

            $this->json([
                'success' => true,
                'message' => $id === null ? 'Contrat cree.' : 'Contrat mis a jour.',
                'redirect' => url('/contracts/show?id=' . $contractId),
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Enregistrement impossible. Verifiez les donnees puis reessayez.', 500);
        }
    }

    private function validateContract(array $data): array
    {
        $errors = [];
        $companyId = (int) ($data['company_id'] ?? 0);
        $employeeId = (int) ($data['employee_id'] ?? 0);
        $type = $data['contract_type'] ?? '';
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $probationEndsAt = $data['probation_ends_at'] ?? '';

        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $errors['company_id'] = 'Entreprise non autorisee.';
        }

        if ($employeeId <= 0 || ($companyId > 0 && !$this->contracts->companyOwnsEmployee($companyId, $employeeId))) {
            $errors['employee_id'] = 'Employe invalide pour cette entreprise.';
        }

        if (!in_array($type, ['cdi', 'cdd', 'internship', 'consultant', 'temporary'], true)) {
            $errors['contract_type'] = 'Type de contrat invalide.';
        }

        if ($startDate === '') {
            $errors['start_date'] = 'Date debut obligatoire.';
        }

        if (in_array($type, ['cdd', 'internship', 'consultant', 'temporary'], true) && $endDate === '') {
            $errors['end_date'] = 'Date fin obligatoire pour ce type de contrat.';
        }

        if ($startDate !== '' && $endDate !== '' && $endDate < $startDate) {
            $errors['end_date'] = 'La date fin doit etre posterieure au debut.';
        }

        if ($probationEndsAt !== '' && $startDate !== '' && $probationEndsAt < $startDate) {
            $errors['probation_ends_at'] = 'La fin de periode d essai doit suivre le debut.';
        }

        if ((float) ($data['base_salary'] ?? 0) < 0) {
            $errors['base_salary'] = 'Salaire invalide.';
        }

        return $errors;
    }

    private function writeContractPdf(array $contract): string
    {
        $dir = BASE_PATH . '/public/uploads/contracts/generated';
        $this->ensureWritableDirectory($dir);

        $filename = 'contract_' . (int) $contract['id'] . '_' . date('YmdHis') . '.pdf';
        $relative = 'public/uploads/contracts/generated/' . $filename;
        $target = BASE_PATH . '/' . $relative;
        $lines = $this->contractPdfLines($contract);

        if (file_put_contents($target, $this->simplePdf($lines)) === false) {
            throw new \RuntimeException('Impossible d ecrire le fichier PDF.');
        }

        @chmod($target, 0664);

        return $relative;
    }

    private function contractPdfLines(array $contract): array
    {
        $employee = trim(($contract['last_name'] ?? '') . ' ' . ($contract['middle_name'] ?? '') . ' ' . ($contract['first_name'] ?? ''));

        return [
            'CONTRAT DE TRAVAIL',
            'Numero: ' . ($contract['contract_number'] ?? '-'),
            'Entreprise: ' . ($contract['company_legal_name'] ?: $contract['company_name']),
            'Adresse entreprise: ' . trim(($contract['company_address'] ?? '') . ' ' . ($contract['company_city'] ?? '')),
            'Employe: ' . $employee,
            'Matricule: ' . ($contract['employee_number'] ?? '-'),
            'Poste: ' . ($contract['position_title'] ?? '-'),
            'Departement: ' . ($contract['department_name'] ?? '-'),
            'Type: ' . $this->typeLabel($contract['contract_type'] ?? ''),
            'Date debut: ' . ($contract['start_date'] ?? '-'),
            'Date fin: ' . ($contract['end_date'] ?: 'Indeterminee'),
            'Periode d essai jusqu au: ' . ($contract['probation_ends_at'] ?: '-'),
            'Salaire contractuel: ' . number_format((float) ($contract['base_salary'] ?? 0), 2, ',', ' ') . ' ' . ($contract['currency'] ?? 'USD'),
            '',
            'Les parties reconnaissent avoir pris connaissance des clauses essentielles du present contrat.',
            'Ce document est genere automatiquement par ELLIOT-HR.',
            '',
            'Signature employeur: __________________________',
            'Signature employe: ____________________________',
        ];
    }

    private function simplePdf(array $lines): string
    {
        $content = "BT\n/F1 18 Tf\n50 790 Td\n";
        foreach ($lines as $index => $line) {
            if ($index === 1) {
                $content .= "/F1 11 Tf\n0 -26 Td\n";
            } elseif ($index > 1) {
                $content .= "0 -18 Td\n";
            }
            $content .= '(' . $this->pdfText($line) . ") Tj\n";
        }
        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function uploadFile(string $field, string $folder, array $allowedTypes): array
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Selectionnez un fichier.');
        }

        $file = $_FILES[$field];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Erreur upload.');
        }

        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new \RuntimeException('Fichier trop volumineux.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';

        if (!in_array($mime, $allowedTypes, true)) {
            throw new \RuntimeException('Type de fichier non autorise.');
        }

        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        $extension = $extensions[$mime] ?? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid($folder . '_', true) . '.' . $extension;
        $relative = 'uploads/contracts/' . $folder . '/' . $filename;
        $target = BASE_PATH . '/public/' . $relative;
        $targetDir = dirname($target);
        $this->ensureWritableDirectory($targetDir);

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Impossible de deplacer le fichier.');
        }

        @chmod($target, 0664);

        return ['public/' . $relative, $file['name'], $mime];
    }

    private function ensureWritableDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de creer le dossier: ' . $dir);
        }

        @chmod($dir, 0777);

        if (!is_writable($dir)) {
            throw new \RuntimeException('Dossier non inscriptible: ' . $dir);
        }
    }

    private function actionButtons(int $id): string
    {
        return '<div class="btn-list flex-nowrap">'
            . '<a class="btn btn-icon" href="' . e(url('/contracts/show?id=' . $id)) . '" title="Details">' . icon('file') . '</a>'
            . '<a class="btn btn-icon" href="' . e(url('/contracts/edit?id=' . $id)) . '" title="Modifier">' . icon('settings') . '</a>'
            . '<button class="btn btn-icon btn-outline" type="button" data-contract-pdf="' . e((string) $id) . '" title="Generer PDF">' . icon('file') . '</button>'
            . '</div>';
    }

    private function expiresSoon(array $contract): bool
    {
        if (($contract['status'] ?? '') !== 'active' || empty($contract['end_date'])) {
            return false;
        }

        $today = strtotime(date('Y-m-d'));
        $end = strtotime($contract['end_date']);

        return $end >= $today && $end <= strtotime('+30 days', $today);
    }

    private function pdfText(string $value): string
    {
        $value = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value) ?: $value;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function resolvedCompanyId(array $data): int
    {
        $scope = $this->companyScope();
        if ($scope !== null) {
            return $scope;
        }

        $employeeId = (int) ($data['employee_id'] ?? 0);
        $employeeCompanyId = $employeeId > 0 ? $this->contracts->employeeCompanyId($employeeId) : null;

        return $employeeCompanyId ?? (int) ($data['company_id'] ?? 0);
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

    private function typeLabel(string $type): string
    {
        return [
            'cdi' => 'CDI',
            'cdd' => 'CDD',
            'internship' => 'Stage',
            'consultant' => 'Consultant',
            'temporary' => 'Temporaire',
        ][$type] ?? $type;
    }

    private function statusLabel(string $status): string
    {
        return [
            'draft' => 'Brouillon',
            'active' => 'Actif',
            'expired' => 'Expire',
            'terminated' => 'Resilie',
        ][$status] ?? $status;
    }

    private function statusTone(string $status): string
    {
        return [
            'draft' => 'gray',
            'active' => 'green',
            'expired' => 'orange',
            'terminated' => 'red',
        ][$status] ?? 'blue';
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
