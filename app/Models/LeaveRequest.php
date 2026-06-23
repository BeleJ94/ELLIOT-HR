<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class LeaveRequest extends Model
{
    protected string $table = 'leave_requests';
    protected array $fillable = [
        'company_id',
        'employee_id',
        'leave_type_id',
        'approved_by',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'manager_status',
        'hr_status',
        'manager_approved_by',
        'hr_approved_by',
        'manager_approved_at',
        'hr_approved_at',
        'rejection_reason',
        'status',
    ];

    public function tableRows(?int $companyId, array $filters = []): array
    {
        [$where, $params] = $this->whereClause($companyId, $filters);

        return Database::query(
            "SELECT leave_requests.*,
                    leave_types.name AS leave_type_name,
                    leave_types.code AS leave_type_code,
                    leave_types.annual_days,
                    employees.employee_number,
                    employees.first_name,
                    employees.middle_name,
                    employees.last_name,
                    departments.name AS department_name,
                    companies.name AS company_name,
                    manager_user.first_name AS manager_first_name,
                    manager_user.last_name AS manager_last_name,
                    hr_user.first_name AS hr_first_name,
                    hr_user.last_name AS hr_last_name
             FROM leave_requests
             INNER JOIN leave_types ON leave_types.id = leave_requests.leave_type_id
             INNER JOIN employees ON employees.id = leave_requests.employee_id
             INNER JOIN companies ON companies.id = leave_requests.company_id
             LEFT JOIN departments ON departments.id = employees.department_id
             LEFT JOIN users manager_user ON manager_user.id = leave_requests.manager_approved_by
             LEFT JOIN users hr_user ON hr_user.id = leave_requests.hr_approved_by
             WHERE {$where}
             ORDER BY leave_requests.created_at DESC",
            $params
        )->fetchAll();
    }

    public function pendingRows(?int $companyId, string $stage): array
    {
        $filters = ['status' => 'pending'];
        $rows = $this->tableRows($companyId, $filters);

        return array_values(array_filter($rows, static function (array $row) use ($stage): bool {
            if ($stage === 'manager') {
                return ($row['manager_status'] ?? 'pending') === 'pending';
            }

            return ($row['manager_status'] ?? '') === 'approved'
                && ($row['hr_status'] ?? 'pending') === 'pending';
        }));
    }

    public function calendarRows(?int $companyId, string $month): array
    {
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        [$scope, $params] = $this->scope($companyId, 'leave_requests');
        $params['start_date'] = $start;
        $params['end_date'] = $end;

        return Database::query(
            "SELECT leave_requests.*,
                    leave_types.name AS leave_type_name,
                    employees.employee_number,
                    employees.first_name,
                    employees.middle_name,
                    employees.last_name,
                    departments.name AS department_name
             FROM leave_requests
             INNER JOIN leave_types ON leave_types.id = leave_requests.leave_type_id
             INNER JOIN employees ON employees.id = leave_requests.employee_id
             LEFT JOIN departments ON departments.id = employees.department_id
             WHERE leave_requests.deleted_at IS NULL
             AND leave_requests.status = 'approved'
             AND leave_requests.start_date <= :end_date
             AND leave_requests.end_date >= :start_date
             {$scope}
             ORDER BY leave_requests.start_date ASC",
            $params
        )->fetchAll();
    }

    public function balanceRows(?int $companyId, ?int $employeeId = null): array
    {
        [$scope, $params] = $this->scope($companyId, 'employees');
        if ($employeeId !== null) {
            $scope .= ' AND employees.id = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        $params['year_start'] = date('Y') . '-01-01';
        $params['year_end'] = date('Y') . '-12-31';

        return Database::query(
            "SELECT employees.id AS employee_id,
                    employees.employee_number,
                    employees.first_name,
                    employees.middle_name,
                    employees.last_name,
                    leave_types.id AS leave_type_id,
                    leave_types.name AS leave_type_name,
                    leave_types.annual_days,
                    COALESCE(SUM(CASE
                        WHEN leave_requests.status = 'approved'
                        AND leave_requests.deleted_at IS NULL
                        AND leave_requests.start_date BETWEEN :year_start AND :year_end
                        THEN leave_requests.total_days
                        ELSE 0
                    END), 0) AS used_days
             FROM employees
             INNER JOIN leave_types ON leave_types.company_id = employees.company_id
                AND leave_types.deleted_at IS NULL
                AND leave_types.annual_days > 0
             LEFT JOIN leave_requests ON leave_requests.employee_id = employees.id
                AND leave_requests.leave_type_id = leave_types.id
             WHERE employees.deleted_at IS NULL
             AND employees.employment_status IN ('active', 'on_leave')
             {$scope}
             GROUP BY employees.id, leave_types.id
             ORDER BY employees.last_name ASC, employees.first_name ASC, leave_types.name ASC",
            $params
        )->fetchAll();
    }

    public function saveRequest(array $data): int
    {
        $start = $data['start_date'] ?? date('Y-m-d');
        $end = $data['end_date'] ?? $start;

        $id = $this->create([
            'company_id' => (int) ($data['company_id'] ?? 0),
            'employee_id' => (int) ($data['employee_id'] ?? 0),
            'leave_type_id' => (int) ($data['leave_type_id'] ?? 0),
            'start_date' => $start,
            'end_date' => $end,
            'total_days' => $this->businessDays($start, $end),
            'reason' => trim($data['reason'] ?? '') ?: null,
            'manager_status' => 'pending',
            'hr_status' => 'pending',
            'status' => 'pending',
        ]);

        $this->notifyPending($id, (int) ($data['company_id'] ?? 0));

        return $id;
    }

    public function approveManager(int $id, ?int $companyId, int $userId): ?array
    {
        $request = $this->findDetailed($id, $companyId);
        if (!$request || $request['status'] !== 'pending') {
            return null;
        }

        Database::query(
            "UPDATE leave_requests
             SET manager_status = 'approved',
                 manager_approved_by = :user_id,
                 manager_approved_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id",
            ['id' => $id, 'user_id' => $userId]
        );

        $this->notifyHr($id, (int) $request['company_id']);

        return $this->findDetailed($id, $companyId);
    }

    public function approveHr(int $id, ?int $companyId, int $userId): ?array
    {
        $request = $this->findDetailed($id, $companyId);
        if (!$request || $request['status'] !== 'pending' || $request['manager_status'] !== 'approved') {
            return null;
        }

        Database::query(
            "UPDATE leave_requests
             SET hr_status = 'approved',
                 hr_approved_by = :hr_user_id,
                 hr_approved_at = NOW(),
                 approved_by = :approved_user_id,
                 status = 'approved',
                 updated_at = NOW()
             WHERE id = :id",
            ['id' => $id, 'hr_user_id' => $userId, 'approved_user_id' => $userId]
        );

        $this->notifyEmployee($id, (int) $request['company_id'], (int) $request['employee_id'], 'success', 'Conge approuve', 'Votre demande de conge a ete approuvee.');

        return $this->findDetailed($id, $companyId);
    }

    public function reject(int $id, ?int $companyId, int $userId, string $reason): ?array
    {
        $request = $this->findDetailed($id, $companyId);
        if (!$request || !in_array($request['status'], ['pending', 'approved'], true)) {
            return null;
        }

        $isManagerStage = ($request['manager_status'] ?? 'pending') !== 'approved';

        Database::query(
            "UPDATE leave_requests
                 SET status = 'rejected',
                 manager_status = CASE WHEN :manager_stage_for_manager = 1 THEN 'rejected' ELSE manager_status END,
                 hr_status = CASE WHEN :manager_stage_for_hr = 1 THEN hr_status ELSE 'rejected' END,
                 approved_by = :user_id,
                 rejection_reason = :reason,
                 updated_at = NOW()
             WHERE id = :id",
            [
                'id' => $id,
                'user_id' => $userId,
                'reason' => $reason,
                'manager_stage_for_manager' => $isManagerStage ? 1 : 0,
                'manager_stage_for_hr' => $isManagerStage ? 1 : 0,
            ]
        );

        $this->notifyEmployee($id, (int) $request['company_id'], (int) $request['employee_id'], 'danger', 'Conge refuse', 'Votre demande de conge a ete refusee: ' . $reason);

        return $this->findDetailed($id, $companyId);
    }

    public function findDetailed(int $id, ?int $companyId): ?array
    {
        $rows = $this->tableRows($companyId, ['id' => $id]);

        return $rows[0] ?? null;
    }

    public function employee(int $employeeId, ?int $companyId): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'employees');
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

    public function employees(?int $companyId, ?int $employeeId = null): array
    {
        [$scope, $params] = $this->scope($companyId, 'employees');
        if ($employeeId !== null) {
            $scope .= ' AND employees.id = :employee_id';
            $params['employee_id'] = $employeeId;
        }

        return Database::query(
            "SELECT employees.id, employees.company_id, employees.employee_number, employees.first_name, employees.middle_name, employees.last_name, departments.name AS department_name
             FROM employees
             LEFT JOIN departments ON departments.id = employees.department_id
             WHERE employees.deleted_at IS NULL
             AND employees.employment_status IN ('active', 'on_leave')
             {$scope}
             ORDER BY employees.last_name ASC, employees.first_name ASC",
            $params
        )->fetchAll();
    }

    public function departments(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'departments');

        return Database::query(
            "SELECT id, company_id, name
             FROM departments
             WHERE deleted_at IS NULL
             {$scope}
             ORDER BY name ASC",
            $params
        )->fetchAll();
    }

    public function businessDays(string $start, string $end): float
    {
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        if (!$startTime || !$endTime || $endTime < $startTime) {
            return 0.00;
        }

        $days = 0;
        while ($startTime <= $endTime) {
            if ((int) date('N', $startTime) <= 5) {
                $days++;
            }
            $startTime = strtotime('+1 day', $startTime);
        }

        return (float) $days;
    }

    private function whereClause(?int $companyId, array $filters): array
    {
        $where = ['leave_requests.deleted_at IS NULL'];
        $params = [];

        if ($companyId !== null) {
            $where[] = 'leave_requests.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        foreach (['id', 'employee_id', 'leave_type_id'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $key = 'filter_' . $field;
                $where[] = "leave_requests.{$field} = :{$key}";
                $params[$key] = (int) $filters[$field];
            }
        }

        if (!empty($filters['department_id'])) {
            $where[] = 'employees.department_id = :department_id';
            $params['department_id'] = (int) $filters['department_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'leave_requests.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['from'])) {
            $where[] = 'leave_requests.end_date >= :from_date';
            $params['from_date'] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $where[] = 'leave_requests.start_date <= :to_date';
            $params['to_date'] = $filters['to'];
        }

        return [implode(' AND ', $where), $params];
    }

    private function scope(?int $companyId, string $table): array
    {
        if ($companyId === null) {
            return ['', []];
        }

        return [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }

    private function notifyPending(int $requestId, int $companyId): void
    {
        Database::query(
            'INSERT INTO notifications (company_id, title, message, type, created_at)
             VALUES (:company_id, :title, :message, :type, NOW())',
            [
                'company_id' => $companyId,
                'title' => 'Demande de conge en attente',
                'message' => 'Une nouvelle demande de conge attend une validation manager. Reference #' . $requestId,
                'type' => 'warning',
            ]
        );
    }

    private function notifyHr(int $requestId, int $companyId): void
    {
        Database::query(
            'INSERT INTO notifications (company_id, title, message, type, created_at)
             VALUES (:company_id, :title, :message, :type, NOW())',
            [
                'company_id' => $companyId,
                'title' => 'Validation RH requise',
                'message' => 'Une demande de conge validee par le manager attend la validation RH. Reference #' . $requestId,
                'type' => 'warning',
            ]
        );
    }

    private function notifyEmployee(int $requestId, int $companyId, int $employeeId, string $type, string $title, string $message): void
    {
        $userId = Database::query(
            'SELECT user_id FROM employees WHERE id = :id LIMIT 1',
            ['id' => $employeeId]
        )->fetchColumn();

        Database::query(
            'INSERT INTO notifications (company_id, user_id, title, message, type, created_at)
             VALUES (:company_id, :user_id, :title, :message, :type, NOW())',
            [
                'company_id' => $companyId,
                'user_id' => $userId ?: null,
                'title' => $title,
                'message' => $message . ' Reference #' . $requestId,
                'type' => $type,
            ]
        );
    }
}
