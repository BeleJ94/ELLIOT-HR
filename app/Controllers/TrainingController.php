<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Training;
use Throwable;

class TrainingController extends Controller
{
    private Training $trainings;

    public function __construct()
    {
        $this->trainings = new Training();
    }

    public function index(): void
    {
        $scope = $this->companyScope();
        $filters = [
            'status' => $_GET['status'] ?? '',
            'from' => $_GET['from'] ?? date('Y-01-01'),
            'to' => $_GET['to'] ?? date('Y-12-31'),
        ];

        $this->view('trainings.index', [
            'title' => 'Formations',
            'dashboard' => $this->trainings->dashboard($scope),
            'sessions' => $this->trainings->sessions($scope, $filters),
            'courses' => $this->trainings->courses($scope),
            'employees' => $this->trainings->employees($scope),
            'companies' => $this->trainings->companies($scope),
            'filters' => $filters,
            'isSuperAdmin' => $scope === null,
            'defaultCompanyId' => $this->defaultCompanyId(),
        ]);
    }

    public function show(): void
    {
        $session = $this->trainings->findSession($this->requestId(), $this->companyScope());
        if (!$session) {
            http_response_code(404);
            $this->view('errors.404', ['title' => 'Session introuvable'], 'auth');
            return;
        }

        $days = $this->trainings->days((int) $session['id']);
        $selectedDayId = (int) ($_GET['day_id'] ?? ($days[0]['id'] ?? 0));

        $this->view('trainings.show', [
            'title' => $session['title'],
            'session' => $session,
            'days' => $days,
            'selectedDayId' => $selectedDayId,
            'participants' => $this->trainings->participants((int) $session['id']),
            'attendance' => $selectedDayId > 0 ? $this->trainings->attendanceByDay($selectedDayId) : [],
            'employees' => $this->trainings->employees((int) $session['company_id']),
        ]);
    }

    public function storeCourse(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $companyId = $this->resolvedCompanyId($_POST);
        $errors = $this->validateCourse($_POST, $companyId);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        $_POST['company_id'] = $companyId;

        try {
            $id = $this->trainings->saveCourse($_POST);
            Auth::log('training_course_created', $companyId, Auth::id(), ['training_course_id' => $id]);
            $this->json(['success' => true, 'message' => 'Formation ajoutee au catalogue.', 'reload' => true]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Creation impossible. Verifiez le code formation.', 500);
        }
    }

    public function storeSession(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $course = $this->course((int) ($_POST['training_course_id'] ?? 0));
        $companyId = (int) ($course['company_id'] ?? 0);
        $errors = $this->validateSession($_POST, $companyId);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signales.', 422, $errors);
            return;
        }

        $_POST['company_id'] = $companyId;

        try {
            $id = $this->trainings->saveSession($_POST);
            Auth::log('training_session_created', $companyId, Auth::id(), ['training_session_id' => $id]);
            $this->json(['success' => true, 'message' => 'Session de formation creee.', 'redirect' => url('/trainings/show?id=' . $id)]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Creation de la session impossible.', 500);
        }
    }

    public function addParticipants(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $sessionId = $this->requestId();
        $employeeIds = $_POST['employee_ids'] ?? [];
        if (!is_array($employeeIds) || $employeeIds === []) {
            $this->jsonError('Selectionnez au moins un employe.', 422);
            return;
        }

        $count = $this->trainings->addParticipants($sessionId, $employeeIds, $this->companyScope());
        $this->json(['success' => true, 'message' => $count . ' participant(s) ajoute(s).', 'reload' => true]);
    }

    public function saveAttendance(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $dayId = (int) ($_POST['day_id'] ?? 0);
        $rows = $_POST['attendance'] ?? [];
        if ($dayId <= 0 || !is_array($rows)) {
            $this->jsonError('Feuille de presence invalide.', 422);
            return;
        }

        if (!$this->trainings->saveAttendance($dayId, $rows, $this->companyScope())) {
            $this->jsonError('Journee de formation introuvable.', 404);
            return;
        }

        $this->json(['success' => true, 'message' => 'Presences enregistrees.', 'reload' => true]);
    }

    public function finalize(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        if (!$this->trainings->finalize($this->requestId(), $this->companyScope())) {
            $this->jsonError('Session introuvable.', 404);
            return;
        }

        $this->json(['success' => true, 'message' => 'Session finalisee.', 'reload' => true]);
    }

    public function export(): void
    {
        $session = $this->trainings->findSession($this->requestId(), $this->companyScope());
        if (!$session) {
            http_response_code(404);
            echo 'Session introuvable';
            return;
        }

        $participants = $this->trainings->participants((int) $session['id']);
        $format = strtolower((string) ($_GET['format'] ?? 'pdf'));
        $filename = 'formation-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $session['title']) . '-' . date('Ymd-His');

        if ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
            echo $this->excel($session, $participants);
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '.pdf"');
        echo $this->pdf($session, $participants);
    }

    private function validateCourse(array $data, int $companyId): array
    {
        $errors = [];
        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $errors['company_id'] = 'Entreprise non autorisee.';
        }
        if (trim($data['title'] ?? '') === '') {
            $errors['title'] = 'Intitule obligatoire.';
        }
        if ((float) ($data['default_duration_days'] ?? 1) <= 0) {
            $errors['default_duration_days'] = 'Duree invalide.';
        }

        return $errors;
    }

    private function validateSession(array $data, int $companyId): array
    {
        $errors = [];
        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $errors['training_course_id'] = 'Formation non autorisee.';
        }
        if (trim($data['title'] ?? '') === '') {
            $errors['title'] = 'Titre session obligatoire.';
        }
        if (!$this->validDate($data['start_date'] ?? '')) {
            $errors['start_date'] = 'Date debut invalide.';
        }
        if (!$this->validDate($data['end_date'] ?? '')) {
            $errors['end_date'] = 'Date fin invalide.';
        }
        if (!empty($data['start_date']) && !empty($data['end_date']) && $data['end_date'] < $data['start_date']) {
            $errors['end_date'] = 'La date fin doit suivre le debut.';
        }

        return $errors;
    }

    private function course(int $id): ?array
    {
        foreach ($this->trainings->courses($this->companyScope()) as $course) {
            if ((int) $course['id'] === $id) {
                return $course;
            }
        }

        return null;
    }

    private function excel(array $session, array $participants): string
    {
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:Arial;color:#1f2937}table{border-collapse:collapse;width:100%}.title{background:#14325c;color:#fff;font-size:22px;padding:16px;text-align:left}.meta{background:#eff6ff;color:#334155;padding:9px 12px}th{background:#dbeafe;color:#1e3a8a;border:1px solid #93c5fd;padding:8px;text-transform:uppercase;font-size:11px}td{border:1px solid #d7dee8;padding:8px;font-size:12px}tr:nth-child(even) td{background:#f8fafc}</style></head><body><table>';
        $html .= '<tr><th class="title" colspan="8">' . e($session['title']) . '</th></tr>';
        $html .= '<tr><td class="meta" colspan="8">' . e($session['course_title']) . ' · ' . e($session['start_date']) . ' au ' . e($session['end_date']) . ' · ' . e($session['location'] ?: '-') . '</td></tr>';
        $html .= '<tr><th>#</th><th>Matricule</th><th>Employe</th><th>Departement</th><th>Poste</th><th>Presence</th><th>Statut final</th><th>Certificat</th></tr>';
        foreach ($participants as $index => $participant) {
            $name = trim(($participant['last_name'] ?? '') . ' ' . ($participant['middle_name'] ?? '') . ' ' . ($participant['first_name'] ?? ''));
            $html .= '<tr><td>' . ($index + 1) . '</td><td>' . e($participant['employee_number']) . '</td><td>' . e($name) . '</td><td>' . e($participant['department_name'] ?? '-') . '</td><td>' . e($participant['position_title'] ?? '-') . '</td><td>' . e(number_format((float) $participant['attendance_rate'], 2, ',', ' ')) . '%</td><td>' . e($participant['final_status']) . '</td><td>' . ((int) $participant['certificate_issued'] === 1 ? 'Oui' : 'Non') . '</td></tr>';
        }
        $html .= '</table></body></html>';

        return $html;
    }

    private function pdf(array $session, array $participants): string
    {
        $lines = [
            'RAPPORT DE FORMATION',
            $session['title'],
            'Formation: ' . $session['course_title'],
            'Periode: ' . $session['start_date'] . ' au ' . $session['end_date'],
            'Lieu: ' . ($session['location'] ?: '-'),
            'Formateur: ' . ($session['trainer_name'] ?: '-'),
            'Participants: ' . count($participants),
            '',
        ];

        foreach (array_slice($participants, 0, 32) as $participant) {
            $name = trim(($participant['last_name'] ?? '') . ' ' . ($participant['middle_name'] ?? '') . ' ' . ($participant['first_name'] ?? ''));
            $lines[] = $participant['employee_number'] . ' - ' . $name . ' - ' . number_format((float) $participant['attendance_rate'], 2, ',', ' ') . '% - ' . $participant['final_status'];
        }

        return $this->simplePdf($lines);
    }

    private function simplePdf(array $lines): string
    {
        $content = "BT\n/F1 17 Tf\n50 790 Td\n";
        foreach ($lines as $index => $line) {
            if ($index === 1) {
                $content .= "/F1 12 Tf\n0 -24 Td\n";
            } elseif ($index > 1) {
                $content .= "0 -16 Td\n";
            }
            $content .= '(' . $this->pdfText(substr((string) $line, 0, 105)) . ") Tj\n";
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
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $text): string
    {
        $text = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text) ?: $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function companyScope(): ?int
    {
        $user = Auth::user() ?? [];

        return ($user['role_slug'] ?? '') === 'super-admin' ? null : (int) ($user['company_id'] ?? 0);
    }

    private function resolvedCompanyId(array $data): int
    {
        return $this->companyScope() ?? (int) ($data['company_id'] ?? 0);
    }

    private function canAccessCompany(int $companyId): bool
    {
        $scope = $this->companyScope();

        return $companyId > 0 && ($scope === null || $companyId === $scope);
    }

    private function defaultCompanyId(): int
    {
        $scope = $this->companyScope();
        if ($scope !== null) {
            return $scope;
        }

        $companies = $this->trainings->companies(null);
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

        return is_string($sessionToken) && is_string($submittedToken) && $sessionToken !== '' && hash_equals($sessionToken, $submittedToken);
    }

    private function jsonError(string $message, int $status = 400, array $errors = []): void
    {
        $this->json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }
}
