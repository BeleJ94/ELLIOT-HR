<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Attendance extends Model
{
    protected string $table = 'attendance';
    protected array $fillable = [
        'company_id',
        'employee_id',
        'attendance_date',
        'check_in',
        'check_out',
        'status',
        'notes',
    ];

    private const WORK_START = '08:00:00';
    private const LATE_AFTER = '08:15:00';
    private const WORK_END = '17:00:00';

    public function companies(?int $companyId): array
    {
        return Database::query(
            'SELECT id, name FROM companies
             WHERE deleted_at IS NULL ' . ($companyId === null ? '' : 'AND id = :company_id') . '
             ORDER BY name ASC',
            $companyId === null ? [] : ['company_id' => $companyId]
        )->fetchAll();
    }

    public function calendar(int $companyId, string $month): array
    {
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $controls = Database::query(
            "SELECT attendance_days.*,
                    closer.first_name AS closed_first_name, closer.last_name AS closed_last_name,
                    locker.first_name AS locked_first_name, locker.last_name AS locked_last_name
             FROM attendance_days
             LEFT JOIN users closer ON closer.id = attendance_days.closed_by
             LEFT JOIN users locker ON locker.id = attendance_days.locked_by
             WHERE attendance_days.company_id = :company_id
             AND attendance_days.attendance_date BETWEEN :start_date AND :end_date
             AND attendance_days.deleted_at IS NULL",
            ['company_id' => $companyId, 'start_date' => $start, 'end_date' => $end]
        )->fetchAll();
        $controlMap = [];
        foreach ($controls as $control) {
            $controlMap[$control['attendance_date']] = $control;
        }

        $counts = Database::query(
            "SELECT attendance_date,
                    COUNT(*) AS recorded_count,
                    SUM(status = 'absent') AS absent_count,
                    SUM(check_in IS NOT NULL AND check_out IS NULL) AS incomplete_count
             FROM attendance
             WHERE company_id = :company_id
             AND attendance_date BETWEEN :start_date AND :end_date
             AND deleted_at IS NULL
             GROUP BY attendance_date",
            ['company_id' => $companyId, 'start_date' => $start, 'end_date' => $end]
        )->fetchAll();
        $countMap = [];
        foreach ($counts as $count) {
            $countMap[$count['attendance_date']] = $count;
        }

        $employeeCount = (int) Database::query(
            "SELECT COUNT(*) FROM employees
             WHERE company_id = :company_id AND deleted_at IS NULL
             AND employment_status IN ('active', 'on_leave')",
            ['company_id' => $companyId]
        )->fetchColumn();

        $days = [];
        $cursor = strtotime($start);
        $last = strtotime($end);
        while ($cursor <= $last) {
            $date = date('Y-m-d', $cursor);
            $control = $controlMap[$date] ?? [];
            $count = $countMap[$date] ?? [];
            $days[] = [
                'date' => $date,
                'day' => (int) date('j', $cursor),
                'weekday' => (int) date('N', $cursor),
                'status' => $control['status'] ?? 'open',
                'reason' => $control['status_reason'] ?? null,
                'recorded_count' => (int) ($count['recorded_count'] ?? 0),
                'absent_count' => (int) ($count['absent_count'] ?? 0),
                'incomplete_count' => (int) ($count['incomplete_count'] ?? 0),
                'employee_count' => $employeeCount,
                'is_complete' => $employeeCount > 0 && (int) ($count['recorded_count'] ?? 0) >= $employeeCount
                    && (int) ($count['incomplete_count'] ?? 0) === 0,
            ];
            $cursor = strtotime('+1 day', $cursor);
        }

        return $days;
    }

    public function dayState(int $companyId, string $date): array
    {
        $day = Database::query(
            "SELECT attendance_days.*,
                    closer.first_name AS closed_first_name, closer.last_name AS closed_last_name,
                    locker.first_name AS locked_first_name, locker.last_name AS locked_last_name,
                    reopener.first_name AS reopened_first_name, reopener.last_name AS reopened_last_name
             FROM attendance_days
             LEFT JOIN users closer ON closer.id = attendance_days.closed_by
             LEFT JOIN users locker ON locker.id = attendance_days.locked_by
             LEFT JOIN users reopener ON reopener.id = attendance_days.reopened_by
             WHERE attendance_days.company_id = :company_id
             AND attendance_days.attendance_date = :attendance_date
             AND attendance_days.deleted_at IS NULL
             LIMIT 1",
            ['company_id' => $companyId, 'attendance_date' => $date]
        )->fetch();

        return $day ?: [
            'id' => null,
            'company_id' => $companyId,
            'attendance_date' => $date,
            'status' => 'open',
            'status_reason' => null,
        ];
    }

    public function anomalies(int $companyId, string $date): array
    {
        $rows = $this->dailyRows($companyId, ['date' => $date]);
        $anomalies = [];
        foreach ($rows as $row) {
            $name = trim(($row['last_name'] ?? '') . ' ' . ($row['first_name'] ?? ''));
            if (empty($row['attendance_id'])) {
                $anomalies[] = ['type' => 'missing', 'employee_id' => (int) $row['employee_id'], 'message' => $name . ' sans encodage'];
                continue;
            }
            if (!in_array($row['status'], ['absent', 'leave', 'holiday'], true)
                && (empty($row['check_in']) || empty($row['check_out']))) {
                $missing = empty($row['check_in']) && empty($row['check_out'])
                    ? 'sans heures d’entrée et de sortie'
                    : (empty($row['check_in']) ? 'sans heure d’entrée' : 'sans heure de sortie');
                $anomalies[] = ['type' => 'incomplete', 'employee_id' => (int) $row['employee_id'], 'message' => $name . ' ' . $missing];
            }
            if (!empty($row['check_in']) && !empty($row['check_out']) && $row['check_out'] <= $row['check_in']) {
                $anomalies[] = ['type' => 'invalid_time', 'employee_id' => (int) $row['employee_id'], 'message' => $name . ' avec horaires incohérents'];
            }
            if (($row['status'] ?? '') === 'absent' && trim((string) ($row['notes'] ?? '')) === '') {
                $anomalies[] = ['type' => 'missing_note', 'employee_id' => (int) $row['employee_id'], 'message' => $name . ' absent sans observation'];
            }
        }

        return $anomalies;
    }

    public function saveBulk(int $companyId, string $date, array $items, int $userId, ?string $reason = null): array
    {
        $day = $this->dayState($companyId, $date);
        if (($day['status'] ?? 'open') !== 'open') {
            throw new \RuntimeException('Cette journée est clôturée ou verrouillée.');
        }

        $dayId = $this->ensureDay($companyId, $date);
        Database::beginTransaction();
        try {
            $saved = 0;
            foreach ($items as $item) {
                $employeeId = (int) ($item['employee_id'] ?? 0);
                $employee = $this->employee($employeeId, $companyId);
                if (!$employee) {
                    continue;
                }
                $old = $this->findForDate($employeeId, $date);
                $status = $this->normalizeStatus((string) ($item['status'] ?? ''));
                $checkIn = $this->normalizeTime($item['check_in'] ?? null);
                $checkOut = $this->normalizeTime($item['check_out'] ?? null);
                $notes = trim((string) ($item['notes'] ?? '')) ?: null;

                if ($status === '') {
                    if ($old) {
                        Database::query('UPDATE attendance SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id', ['id' => $old['id']]);
                        $this->logChange($companyId, $dayId, (int) $old['id'], $employeeId, $date, 'attendance_cleared', $old, null, $reason, $userId);
                        $saved++;
                    }
                    continue;
                }
                if (in_array($status, ['absent', 'leave', 'holiday'], true)) {
                    $checkIn = null;
                    $checkOut = null;
                } elseif ($status === 'present' && $checkIn && $checkIn > self::LATE_AFTER) {
                    $status = 'late';
                }

                $payload = [
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'attendance_date' => $date,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'status' => $status,
                    'notes' => $notes,
                ];
                if ($old) {
                    Database::query(
                        "UPDATE attendance SET check_in = :check_in, check_out = :check_out,
                            status = :status, notes = :notes, deleted_at = NULL, updated_at = NOW()
                         WHERE id = :id",
                        [
                            'check_in' => $checkIn, 'check_out' => $checkOut,
                            'status' => $status, 'notes' => $notes, 'id' => $old['id'],
                        ]
                    );
                    $attendanceId = (int) $old['id'];
                } else {
                    $attendanceId = $this->create($payload);
                }
                $this->logChange($companyId, $dayId, $attendanceId, $employeeId, $date, $old ? 'attendance_updated' : 'attendance_created', $old, $payload, $reason, $userId);
                $saved++;
            }
            Database::commit();
            return ['saved' => $saved, 'anomalies' => $this->anomalies($companyId, $date)];
        } catch (\Throwable $exception) {
            Database::rollBack();
            throw $exception;
        }
    }

    public function transitionDay(int $companyId, string $date, string $status, int $userId, string $reason = ''): array
    {
        $allowed = ['open', 'closed', 'locked'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('État de journée invalide.');
        }
        $dayId = $this->ensureDay($companyId, $date);
        $old = $this->dayState($companyId, $date);
        $currentStatus = $old['status'] ?? 'open';
        if ($currentStatus === $status) {
            throw new \RuntimeException('Cette journée possède déjà cet état.');
        }
        if ($status === 'closed' && $currentStatus !== 'open') {
            throw new \RuntimeException('Seule une journée ouverte peut être clôturée.');
        }
        if ($status === 'open' && !in_array($currentStatus, ['closed', 'locked'], true)) {
            throw new \RuntimeException('Cette journée est déjà ouverte.');
        }
        $sets = ['status = :status', 'status_reason = :reason', 'updated_at = NOW()'];
        $params = ['id' => $dayId, 'status' => $status, 'reason' => $reason ?: null];
        if ($status === 'closed') {
            $sets[] = 'closed_by = :actor';
            $sets[] = 'closed_at = NOW()';
            $params['actor'] = $userId;
        } elseif ($status === 'locked') {
            $sets[] = 'locked_by = :actor';
            $sets[] = 'locked_at = NOW()';
            $params['actor'] = $userId;
        } else {
            $sets[] = 'reopened_by = :actor';
            $sets[] = 'reopened_at = NOW()';
            $params['actor'] = $userId;
        }
        Database::query('UPDATE attendance_days SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
        $new = $this->dayState($companyId, $date);
        $this->logChange($companyId, $dayId, null, null, $date, 'day_' . $status, $old, $new, $reason, $userId);
        return $new;
    }

    public function history(int $companyId, string $date, int $limit = 20): array
    {
        $statement = Database::connection()->prepare(
            "SELECT attendance_changes.*, users.first_name, users.last_name,
                    employees.employee_number, employees.first_name AS employee_first_name,
                    employees.last_name AS employee_last_name
             FROM attendance_changes
             LEFT JOIN users ON users.id = attendance_changes.changed_by
             LEFT JOIN employees ON employees.id = attendance_changes.employee_id
             WHERE attendance_changes.company_id = :company_id
             AND attendance_changes.attendance_date = :attendance_date
             AND attendance_changes.deleted_at IS NULL
             ORDER BY attendance_changes.created_at DESC
             LIMIT :limit"
        );
        $statement->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $statement->bindValue(':attendance_date', $date);
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function dailyRows(?int $companyId, array $filters): array
    {
        $date = $filters['date'] ?: date('Y-m-d');
        [$scope, $params] = $this->employeeScope($companyId);
        $params['attendance_date'] = $date;

        foreach (['employee_id' => 'employees.id', 'department_id' => 'employees.department_id'] as $filter => $column) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $key = 'filter_' . $filter;
                $scope .= " AND {$column} = :{$key}";
                $params[$key] = (int) $filters[$filter];
            }
        }

        $rows = Database::query(
            "SELECT employees.id AS employee_id,
                    employees.company_id,
                    employees.employee_number,
                    employees.first_name,
                    employees.middle_name,
                    employees.last_name,
                    companies.name AS company_name,
                    departments.name AS department_name,
                    positions.title AS position_title,
                    attendance.id AS attendance_id,
                    attendance.attendance_date,
                    attendance.check_in,
                    attendance.check_out,
                    attendance.status,
                    attendance.notes
             FROM employees
             INNER JOIN companies ON companies.id = employees.company_id
             LEFT JOIN departments ON departments.id = employees.department_id
             LEFT JOIN positions ON positions.id = employees.position_id
             LEFT JOIN attendance ON attendance.employee_id = employees.id
                AND attendance.attendance_date = :attendance_date
                AND attendance.deleted_at IS NULL
             WHERE employees.deleted_at IS NULL
             AND employees.employment_status IN ('active', 'on_leave')
             {$scope}
             ORDER BY departments.name ASC, employees.last_name ASC, employees.first_name ASC",
            $params
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['attendance_date'] = $row['attendance_date'] ?: $date;
            $row['computed_status'] = $this->computedStatus($row);
            $row['worked_minutes'] = $this->workedMinutes($row['check_in'] ?? null, $row['check_out'] ?? null);
            $row['overtime_minutes'] = $this->overtimeMinutes($row['check_out'] ?? null);
            $row['late_minutes'] = $this->lateMinutes($row['check_in'] ?? null);
        }
        unset($row);

        return $rows;
    }

    public function monthlyReport(?int $companyId, array $filters): array
    {
        $month = $filters['month'] ?: date('Y-m');
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        [$scope, $params] = $this->employeeScope($companyId);

        foreach (['employee_id' => 'employees.id', 'department_id' => 'employees.department_id'] as $filter => $column) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $key = 'filter_' . $filter;
                $scope .= " AND {$column} = :{$key}";
                $params[$key] = (int) $filters[$filter];
            }
        }

        $employees = Database::query(
            "SELECT employees.id,
                    employees.company_id,
                    employees.employee_number,
                    employees.first_name,
                    employees.middle_name,
                    employees.last_name,
                    companies.name AS company_name,
                    departments.name AS department_name,
                    positions.title AS position_title
             FROM employees
             INNER JOIN companies ON companies.id = employees.company_id
             LEFT JOIN departments ON departments.id = employees.department_id
             LEFT JOIN positions ON positions.id = employees.position_id
             WHERE employees.deleted_at IS NULL
             AND employees.employment_status IN ('active', 'on_leave')
             {$scope}
             ORDER BY departments.name ASC, employees.last_name ASC, employees.first_name ASC",
            $params
        )->fetchAll();

        $attendanceParams = $params;
        $attendanceParams['start_date'] = $start;
        $attendanceParams['end_date'] = $end;

        $attendanceRows = Database::query(
            "SELECT attendance.*
             FROM attendance
             INNER JOIN employees ON employees.id = attendance.employee_id
             WHERE attendance.deleted_at IS NULL
             AND attendance.attendance_date BETWEEN :start_date AND :end_date
             {$scope}",
            $attendanceParams
        )->fetchAll();

        $byEmployee = [];
        foreach ($attendanceRows as $row) {
            $byEmployee[(int) $row['employee_id']][] = $row;
        }

        $workDays = $this->workDaysInMonth($start, $end);
        $report = [];

        foreach ($employees as $employee) {
            $items = $byEmployee[(int) $employee['id']] ?? [];
            $summary = [
                'present_days' => 0,
                'late_days' => 0,
                'absent_days' => 0,
                'half_days' => 0,
                'leave_days' => 0,
                'worked_minutes' => 0,
                'overtime_minutes' => 0,
            ];

            foreach ($items as $item) {
                $status = $item['status'] ?? 'present';
                if ($status === 'late') {
                    $summary['late_days']++;
                    $summary['present_days']++;
                } elseif ($status === 'present') {
                    $summary['present_days']++;
                } elseif ($status === 'absent') {
                    $summary['absent_days']++;
                } elseif ($status === 'half_day') {
                    $summary['half_days']++;
                } elseif ($status === 'leave') {
                    $summary['leave_days']++;
                }

                $summary['worked_minutes'] += $this->workedMinutes($item['check_in'] ?? null, $item['check_out'] ?? null);
                $summary['overtime_minutes'] += $this->overtimeMinutes($item['check_out'] ?? null);
            }

            $recordedDays = $summary['present_days'] + $summary['half_days'] + $summary['leave_days'] + $summary['absent_days'];
            $summary['absent_days'] += max(0, $workDays - $recordedDays);
            $summary['work_days'] = $workDays;
            $summary['presence_rate'] = $workDays > 0 ? round(($summary['present_days'] / $workDays) * 100) : 0;

            $report[] = array_merge($employee, $summary);
        }

        return $report;
    }

    public function options(?int $companyId): array
    {
        [$scope, $params] = $this->employeeScope($companyId);

        return [
            'employees' => Database::query(
                "SELECT id, company_id, employee_number, first_name, middle_name, last_name
                 FROM employees
                 WHERE deleted_at IS NULL
                 AND employment_status IN ('active', 'on_leave')
                 {$scope}
                 ORDER BY last_name ASC, first_name ASC",
                $params
            )->fetchAll(),
            'departments' => Database::query(
                "SELECT departments.id, departments.company_id, departments.name
                 FROM departments
                 WHERE departments.deleted_at IS NULL
                 " . ($companyId === null ? '' : 'AND departments.company_id = :company_id') . "
                 ORDER BY departments.name ASC",
                $companyId === null ? [] : ['company_id' => $companyId]
            )->fetchAll(),
        ];
    }

    public function checkIn(int $employeeId, ?int $companyId = null): ?array
    {
        $employee = $this->employee($employeeId, $companyId);
        if (!$employee) {
            return null;
        }

        $date = date('Y-m-d');
        $time = date('H:i:s');
        $status = $time > self::LATE_AFTER ? 'late' : 'present';
        $existing = $this->findForDate($employeeId, $date);

        if ($existing) {
            if (!empty($existing['check_in'])) {
                return $existing;
            }

            Database::query(
                "UPDATE attendance
                 SET check_in = :check_in, status = :status, updated_at = NOW()
                 WHERE id = :id",
                ['check_in' => $time, 'status' => $status, 'id' => (int) $existing['id']]
            );

            return $this->findForDate($employeeId, $date);
        }

        $id = $this->create([
            'company_id' => (int) $employee['company_id'],
            'employee_id' => $employeeId,
            'attendance_date' => $date,
            'check_in' => $time,
            'status' => $status,
        ]);

        return $this->find($id);
    }

    public function checkOut(int $employeeId, ?int $companyId = null): ?array
    {
        $employee = $this->employee($employeeId, $companyId);
        if (!$employee) {
            return null;
        }

        $date = date('Y-m-d');
        $time = date('H:i:s');
        $existing = $this->findForDate($employeeId, $date);

        if (!$existing) {
            $id = $this->create([
                'company_id' => (int) $employee['company_id'],
                'employee_id' => $employeeId,
                'attendance_date' => $date,
                'check_out' => $time,
                'status' => 'half_day',
                'notes' => 'Sortie enregistree sans pointage entree.',
            ]);

            return $this->find($id);
        }

        Database::query(
            "UPDATE attendance
             SET check_out = :check_out,
                 status = CASE
                    WHEN check_in IS NULL THEN 'half_day'
                    WHEN check_in > :late_after THEN 'late'
                    ELSE status
                 END,
                 updated_at = NOW()
             WHERE id = :id",
            ['check_out' => $time, 'late_after' => self::LATE_AFTER, 'id' => (int) $existing['id']]
        );

        return $this->findForDate($employeeId, $date);
    }

    public function markAbsent(int $employeeId, string $date, ?int $companyId = null, string $notes = ''): ?array
    {
        $employee = $this->employee($employeeId, $companyId);
        if (!$employee) {
            return null;
        }

        $existing = $this->findForDate($employeeId, $date);
        if ($existing) {
            Database::query(
                "UPDATE attendance
                 SET check_in = NULL, check_out = NULL, status = 'absent', notes = :notes, updated_at = NOW()
                 WHERE id = :id",
                ['notes' => $notes ?: null, 'id' => (int) $existing['id']]
            );

            return $this->findForDate($employeeId, $date);
        }

        $id = $this->create([
            'company_id' => (int) $employee['company_id'],
            'employee_id' => $employeeId,
            'attendance_date' => $date,
            'status' => 'absent',
            'notes' => $notes ?: null,
        ]);

        return $this->find($id);
    }

    public function employee(int $employeeId, ?int $companyId = null): ?array
    {
        [$scope, $params] = $this->employeeScope($companyId);
        $params['id'] = $employeeId;

        $employee = Database::query(
            "SELECT * FROM employees
             WHERE id = :id
             AND deleted_at IS NULL
             {$scope}
             LIMIT 1",
            $params
        )->fetch();

        return $employee ?: null;
    }

    public function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h00';
        }

        return sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function findForDate(int $employeeId, string $date): ?array
    {
        $row = Database::query(
            'SELECT * FROM attendance
             WHERE employee_id = :employee_id
             AND attendance_date = :attendance_date
             AND deleted_at IS NULL
             LIMIT 1',
            ['employee_id' => $employeeId, 'attendance_date' => $date]
        )->fetch();

        return $row ?: null;
    }

    private function ensureDay(int $companyId, string $date): int
    {
        Database::query(
            "INSERT INTO attendance_days (company_id, attendance_date, status, created_at)
             VALUES (:company_id, :attendance_date, 'open', NOW())
             ON DUPLICATE KEY UPDATE deleted_at = NULL",
            ['company_id' => $companyId, 'attendance_date' => $date]
        );
        return (int) Database::query(
            'SELECT id FROM attendance_days WHERE company_id = :company_id AND attendance_date = :attendance_date LIMIT 1',
            ['company_id' => $companyId, 'attendance_date' => $date]
        )->fetchColumn();
    }

    private function logChange(int $companyId, int $dayId, ?int $attendanceId, ?int $employeeId, string $date, string $action, ?array $old, ?array $new, ?string $reason, int $userId): void
    {
        Database::query(
            "INSERT INTO attendance_changes
                (company_id, attendance_day_id, attendance_id, employee_id, attendance_date,
                 action, old_values, new_values, reason, changed_by, created_at)
             VALUES
                (:company_id, :day_id, :attendance_id, :employee_id, :attendance_date,
                 :action, :old_values, :new_values, :reason, :changed_by, NOW())",
            [
                'company_id' => $companyId, 'day_id' => $dayId,
                'attendance_id' => $attendanceId, 'employee_id' => $employeeId,
                'attendance_date' => $date, 'action' => $action,
                'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
                'reason' => $reason ?: null, 'changed_by' => $userId,
            ]
        );
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['present', 'absent', 'late', 'half_day', 'holiday', 'leave'], true) ? $status : '';
    }

    private function normalizeTime($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
            throw new \InvalidArgumentException('Format horaire invalide.');
        }
        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private function employeeScope(?int $companyId): array
    {
        if ($companyId === null) {
            return ['', []];
        }

        return [' AND employees.company_id = :company_id', ['company_id' => $companyId]];
    }

    private function computedStatus(array $row): string
    {
        if (!empty($row['status'])) {
            return $row['status'];
        }

        return 'absent';
    }

    private function workedMinutes(?string $checkIn, ?string $checkOut): int
    {
        if (!$checkIn || !$checkOut) {
            return 0;
        }

        $start = strtotime(date('Y-m-d') . ' ' . $checkIn);
        $end = strtotime(date('Y-m-d') . ' ' . $checkOut);

        return $end > $start ? (int) floor(($end - $start) / 60) : 0;
    }

    private function lateMinutes(?string $checkIn): int
    {
        if (!$checkIn || $checkIn <= self::WORK_START) {
            return 0;
        }

        return max(0, (int) floor((strtotime(date('Y-m-d') . ' ' . $checkIn) - strtotime(date('Y-m-d') . ' ' . self::WORK_START)) / 60));
    }

    private function overtimeMinutes(?string $checkOut): int
    {
        if (!$checkOut || $checkOut <= self::WORK_END) {
            return 0;
        }

        return max(0, (int) floor((strtotime(date('Y-m-d') . ' ' . $checkOut) - strtotime(date('Y-m-d') . ' ' . self::WORK_END)) / 60));
    }

    private function workDaysInMonth(string $start, string $end): int
    {
        $days = 0;
        $cursor = strtotime($start);
        $last = strtotime($end);

        while ($cursor <= $last) {
            if ((int) date('N', $cursor) <= 5) {
                $days++;
            }
            $cursor = strtotime('+1 day', $cursor);
        }

        return $days;
    }
}
