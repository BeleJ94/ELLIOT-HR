<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Attendance;
use Throwable;

class AttendanceController extends Controller
{
    private Attendance $attendance;

    public function __construct()
    {
        $this->attendance = new Attendance();
    }

    public function index(): void
    {
        $selectedCompanyId = $this->selectedCompanyId();
        $month = $_GET['month'] ?? substr((string) ($_GET['date'] ?? date('Y-m-d')), 0, 7);
        $filters = [
            'date' => $_GET['date'] ?? date('Y-m-d'),
            'employee_id' => $_GET['employee_id'] ?? null,
            'department_id' => $_GET['department_id'] ?? null,
        ];
        $filters = $this->applyEmployeeSelfScope($filters);
        $day = $this->attendance->dayState($selectedCompanyId, $filters['date']);
        $anomalies = $this->attendance->anomalies($selectedCompanyId, $filters['date']);

        $this->view('attendance.index', [
            'title' => 'Gestion des présences',
            'rows' => $this->attendance->dailyRows($selectedCompanyId, $filters),
            'options' => $this->options($selectedCompanyId),
            'filters' => $filters,
            'attendance' => $this->attendance,
            'companies' => $this->attendance->companies($this->companyScope()),
            'selectedCompanyId' => $selectedCompanyId,
            'month' => $month,
            'calendarDays' => $this->attendance->calendar($selectedCompanyId, $month),
            'day' => $day,
            'anomalies' => $anomalies,
            'history' => $this->attendance->history($selectedCompanyId, $filters['date']),
            'canEncode' => $this->canEncode(),
            'canClose' => $this->canClose(),
            'isSuperAdmin' => $this->isSuperAdmin(),
        ]);
    }

    public function report(): void
    {
        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            $month = date('Y-m');
        }
        $filters = [
            'month' => $month,
            'employee_id' => $_GET['employee_id'] ?? null,
            'department_id' => $_GET['department_id'] ?? null,
        ];
        $filters = $this->applyEmployeeSelfScope($filters);

        $this->view('attendance.report', [
            'title' => 'Rapport mensuel de presence',
            'reportRows' => $this->attendance->monthlyReport($this->companyScope(), $filters),
            'calendarDays' => $this->attendance->reportCalendarDays($month),
            'options' => $this->options(),
            'filters' => $filters,
            'attendance' => $this->attendance,
        ]);
    }

    public function checkIn(): void
    {
        $this->checkpoint('in');
    }

    public function checkOut(): void
    {
        $this->checkpoint('out');
    }

    public function markAbsent(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $date = $_POST['date'] ?? date('Y-m-d');

        if ($employeeId <= 0 || !$this->validDate($date)) {
            $this->jsonError('Employe ou date invalide.', 422);
            return;
        }
        if ($date > date('Y-m-d')) {
            $this->jsonError('Une présence ne peut pas être encodée dans le futur.', 422);
            return;
        }

        if (!$this->canAccessEmployee($employeeId)) {
            $this->jsonError('Acces refuse pour cet employe.', 403);
            return;
        }
        if (!$this->dayIsEditableForEmployee($employeeId, $date)) {
            $this->jsonError('Cette journée est clôturée ou verrouillée.', 423);
            return;
        }

        try {
            $row = $this->attendance->markAbsent($employeeId, $date, $this->companyScope(), trim($_POST['notes'] ?? ''));

            if (!$row) {
                $this->jsonError('Employe introuvable.', 404);
                return;
            }

            Auth::log('attendance_absent_marked', (int) $row['company_id'], Auth::id(), [
                'employee_id' => $employeeId,
                'attendance_date' => $date,
            ]);

            $this->json([
                'success' => true,
                'message' => 'Absence enregistree.',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Enregistrement impossible.', 500);
        }
    }

    public function bulkSave(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }
        if (!$this->canEncode()) {
            $this->jsonError('Vous n’êtes pas autorisé à encoder les présences.', 403);
            return;
        }
        $companyId = $this->postedCompanyId();
        $date = (string) ($_POST['date'] ?? '');
        $items = json_decode((string) ($_POST['items'] ?? '[]'), true);
        if ($companyId <= 0 || !$this->validDate($date) || !is_array($items)) {
            $this->jsonError('Entreprise, date ou données invalides.', 422);
            return;
        }
        if (!$this->companyIsAllowed($companyId)) {
            $this->jsonError('Entreprise non autorisée.', 403);
            return;
        }
        if ($date > date('Y-m-d')) {
            $this->jsonError('Une présence ne peut pas être encodée dans le futur.', 422);
            return;
        }
        try {
            $result = $this->attendance->saveBulk($companyId, $date, $items, Auth::id() ?? 0, trim($_POST['reason'] ?? ''));
            Auth::log('attendance_bulk_saved', $companyId, Auth::id(), [
                'attendance_date' => $date, 'saved' => $result['saved'],
            ]);
            $this->json([
                'success' => true,
                'message' => $result['saved'] . ' ligne(s) enregistrée(s).',
                'saved' => $result['saved'],
                'anomalies' => $result['anomalies'],
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError($exception->getMessage() ?: 'Enregistrement impossible.', 422);
        }
    }

    public function closeDay(): void
    {
        if (!$this->canClose()) {
            $this->jsonError('La clôture est réservée au responsable RH.', 403);
            return;
        }
        $this->transition('closed', true);
    }

    public function lockDay(): void
    {
        if (!$this->isSuperAdmin()) {
            $this->jsonError('Le verrouillage est réservé au Super Administrateur.', 403);
            return;
        }
        $this->transition('locked');
    }

    public function reopenDay(): void
    {
        if (!$this->isSuperAdmin()) {
            $this->jsonError('La réouverture est réservée au Super Administrateur.', 403);
            return;
        }
        $this->transition('open');
    }

    private function transition(string $status, bool $blockOnAnomalies = false): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }
        $companyId = $this->postedCompanyId();
        $date = (string) ($_POST['date'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($companyId <= 0 || !$this->validDate($date)) {
            $this->jsonError('Entreprise ou date invalide.', 422);
            return;
        }
        if (!$this->companyIsAllowed($companyId)) {
            $this->jsonError('Entreprise non autorisée.', 403);
            return;
        }
        if ($date > date('Y-m-d')) {
            $this->jsonError('Une journée future ne peut pas être clôturée ou verrouillée.', 422);
            return;
        }
        if ($status === 'open' && $reason === '') {
            $this->jsonError('Le motif de réouverture est obligatoire.', 422);
            return;
        }
        $anomalies = $this->attendance->anomalies($companyId, $date);
        if ($blockOnAnomalies && $anomalies !== []) {
            $this->jsonError('Corrigez les anomalies avant de clôturer la journée.', 422, ['anomalies' => $anomalies]);
            return;
        }
        try {
            $day = $this->attendance->transitionDay($companyId, $date, $status, Auth::id() ?? 0, $reason);
            Auth::log('attendance_day_' . $status, $companyId, Auth::id(), [
                'attendance_date' => $date, 'reason' => $reason,
            ]);
            $labels = ['open' => 'Journée rouverte.', 'closed' => 'Journée clôturée.', 'locked' => 'Journée verrouillée.'];
            $this->json(['success' => true, 'message' => $labels[$status], 'day' => $day, 'reload' => true]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError($exception->getMessage() ?: 'Changement d’état impossible.', 422);
        }
    }

    private function checkpoint(string $direction): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            $this->jsonError('Selectionnez un employe.', 422);
            return;
        }

        if (!$this->canAccessEmployee($employeeId)) {
            $this->jsonError('Acces refuse pour cet employe.', 403);
            return;
        }
        if (!$this->dayIsEditableForEmployee($employeeId, date('Y-m-d'))) {
            $this->jsonError('Cette journée est clôturée ou verrouillée.', 423);
            return;
        }

        try {
            $row = $direction === 'in'
                ? $this->attendance->checkIn($employeeId, $this->companyScope())
                : $this->attendance->checkOut($employeeId, $this->companyScope());

            if (!$row) {
                $this->jsonError('Employe introuvable ou non autorise.', 404);
                return;
            }

            Auth::log($direction === 'in' ? 'attendance_check_in' : 'attendance_check_out', (int) $row['company_id'], Auth::id(), [
                'employee_id' => $employeeId,
                'attendance_date' => $row['attendance_date'] ?? date('Y-m-d'),
            ]);

            $this->json([
                'success' => true,
                'message' => $direction === 'in' ? 'Entree enregistree.' : 'Sortie enregistree.',
                'attendance' => [
                    'check_in' => $row['check_in'] ?? null,
                    'check_out' => $row['check_out'] ?? null,
                    'status' => $row['status'] ?? null,
                ],
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Pointage impossible.', 500);
        }
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
        if ($employeeId !== null) {
            $filters['employee_id'] = $employeeId;
            $filters['department_id'] = null;
        }

        return $filters;
    }

    private function options(?int $companyId = null): array
    {
        $options = $this->attendance->options($companyId ?? $this->companyScope());
        $employeeId = $this->userEmployeeId();

        if ($employeeId === null) {
            return $options;
        }

        $options['employees'] = array_values(array_filter($options['employees'] ?? [], static function (array $employee) use ($employeeId): bool {
            return (int) ($employee['id'] ?? 0) === $employeeId;
        }));
        $options['departments'] = [];

        return $options;
    }

    private function selectedCompanyId(): int
    {
        $scope = $this->companyScope();
        if ($scope !== null) {
            return $scope;
        }
        $requested = (int) ($_GET['company_id'] ?? 0);
        $companies = $this->attendance->companies(null);
        foreach ($companies as $company) {
            if ((int) $company['id'] === $requested) {
                return $requested;
            }
        }
        return (int) ($companies[0]['id'] ?? 0);
    }

    private function postedCompanyId(): int
    {
        $scope = $this->companyScope();
        if ($scope !== null) {
            return $scope;
        }
        return (int) ($_POST['company_id'] ?? 0);
    }

    private function companyIsAllowed(int $companyId): bool
    {
        $scope = $this->companyScope();
        if ($scope !== null) {
            return $companyId === $scope;
        }

        foreach ($this->attendance->companies(null) as $company) {
            if ((int) $company['id'] === $companyId) {
                return true;
            }
        }

        return false;
    }

    private function dayIsEditableForEmployee(int $employeeId, string $date): bool
    {
        $employee = $this->attendance->employee($employeeId, $this->companyScope());
        if (!$employee) {
            return false;
        }

        $day = $this->attendance->dayState((int) $employee['company_id'], $date);

        return ($day['status'] ?? 'open') === 'open';
    }

    private function canEncode(): bool
    {
        return Auth::hasRole(['super-admin', 'admin-rh', 'manager']);
    }

    private function canClose(): bool
    {
        return Auth::hasRole(['super-admin', 'admin-rh']);
    }

    private function isSuperAdmin(): bool
    {
        return Auth::hasRole(['super-admin']);
    }

    private function canAccessEmployee(int $employeeId): bool
    {
        $ownEmployeeId = $this->userEmployeeId();

        return $ownEmployeeId === null || $employeeId === $ownEmployeeId;
    }

    private function userEmployeeId(): ?int
    {
        $user = Auth::user() ?? [];

        if (($user['role_slug'] ?? '') !== 'employe') {
            return null;
        }

        return isset($user['employee_id']) ? (int) $user['employee_id'] : 0;
    }

    private function validDate(string $date): bool
    {
        $parsed = date_create_from_format('Y-m-d', $date);

        return $parsed && $parsed->format('Y-m-d') === $date;
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
