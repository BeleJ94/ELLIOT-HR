<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Employee extends Model
{
    protected string $table = 'employees';
    protected array $fillable = [
        'company_id',
        'branch_id',
        'department_id',
        'position_id',
        'manager_id',
        'user_id',
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'birth_date',
        'birth_place',
        'marital_status',
        'hire_date',
        'termination_date',
        'email',
        'phone',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'photo_path',
        'employment_status',
    ];

    public function allWithDetails(?int $companyId = null, array $filters = []): array
    {
        [$where, $params] = $this->employeeWhere($companyId, $filters, 'employees');

        return Database::query(
            "SELECT employees.*,
                    companies.name AS company_name,
                    branches.name AS branch_name,
                    departments.name AS department_name,
                    positions.title AS position_title,
                    managers.first_name AS manager_first_name,
                    managers.last_name AS manager_last_name,
                    contracts.contract_type,
                    contracts.base_salary,
                    contracts.currency
             FROM employees
             INNER JOIN companies ON companies.id = employees.company_id
             LEFT JOIN branches ON branches.id = employees.branch_id
             LEFT JOIN departments ON departments.id = employees.department_id
             LEFT JOIN positions ON positions.id = employees.position_id
             LEFT JOIN employees managers ON managers.id = employees.manager_id
             LEFT JOIN contracts ON contracts.id = (
                SELECT c.id FROM contracts c
                WHERE c.employee_id = employees.id
                AND c.deleted_at IS NULL
                ORDER BY c.start_date DESC, c.id DESC
                LIMIT 1
             )
             WHERE {$where}
             ORDER BY employees.created_at DESC",
            $params
        )->fetchAll();
    }

    public function findDetailed(int $id, ?int $companyId = null): ?array
    {
        $filters = ['id' => $id];
        [$where, $params] = $this->employeeWhere($companyId, $filters, 'employees');

        $employee = Database::query(
            "SELECT employees.*,
                    companies.name AS company_name,
                    branches.name AS branch_name,
                    departments.name AS department_name,
                    positions.title AS position_title,
                    managers.first_name AS manager_first_name,
                    managers.last_name AS manager_last_name,
                    contracts.id AS contract_id,
                    contracts.contract_number,
                    contracts.contract_type,
                    contracts.base_salary,
                    contracts.currency,
                    contracts.status AS contract_status,
                    contracts.start_date AS contract_start_date,
                    contracts.end_date AS contract_end_date
             FROM employees
             INNER JOIN companies ON companies.id = employees.company_id
             LEFT JOIN branches ON branches.id = employees.branch_id
             LEFT JOIN departments ON departments.id = employees.department_id
             LEFT JOIN positions ON positions.id = employees.position_id
             LEFT JOIN employees managers ON managers.id = employees.manager_id
             LEFT JOIN contracts ON contracts.id = (
                SELECT c.id FROM contracts c
                WHERE c.employee_id = employees.id
                AND c.deleted_at IS NULL
                ORDER BY c.start_date DESC, c.id DESC
                LIMIT 1
             )
             WHERE {$where}
             LIMIT 1",
            $params
        )->fetch();

        return $employee ?: null;
    }

    public function formOptions(?int $companyId = null): array
    {
        $companyScope = '';
        $companyParams = [];

        if ($companyId !== null) {
            $companyScope = ' AND id = :company_id';
            $companyParams['company_id'] = $companyId;
        }

        return [
            'companies' => Database::query(
                "SELECT id, name FROM companies WHERE deleted_at IS NULL {$companyScope} ORDER BY name ASC",
                $companyParams
            )->fetchAll(),
            'branches' => $this->optionRows('branches', 'name', $companyId),
            'departments' => $this->optionRows('departments', 'name', $companyId),
            'positions' => $this->optionRows('positions', 'title', $companyId),
            'managers' => $this->managerRows($companyId),
        ];
    }

    public function documents(int $employeeId): array
    {
        return Database::query(
            'SELECT * FROM employee_documents
             WHERE employee_id = :employee_id
             AND deleted_at IS NULL
             ORDER BY created_at DESC',
            ['employee_id' => $employeeId]
        )->fetchAll();
    }

    public function generateEmployeeNumber(int $companyId): string
    {
        $company = Database::query(
            'SELECT id, name FROM companies WHERE id = :id LIMIT 1',
            ['id' => $companyId]
        )->fetch();

        $prefix = 'EMP';
        if ($company && !empty($company['name'])) {
            $letters = preg_replace('/[^A-Z]/', '', strtoupper($company['name']));
            $prefix = substr($letters ?: 'EMP', 0, 3);
        }

        $count = (int) Database::query(
            'SELECT COUNT(*) FROM employees WHERE company_id = :company_id',
            ['company_id' => $companyId]
        )->fetchColumn();

        do {
            $number = sprintf('%s-%04d', $prefix, ++$count);
            $exists = Database::query(
                'SELECT COUNT(*) FROM employees WHERE company_id = :company_id AND employee_number = :number',
                ['company_id' => $companyId, 'number' => $number]
            )->fetchColumn();
        } while ((int) $exists > 0);

        return $number;
    }

    public function saveEmployee(array $data, ?int $id = null, ?string $photoPath = null): int
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $payload = [
            'company_id' => $companyId,
            'branch_id' => $this->nullableInt($data['branch_id'] ?? null),
            'department_id' => $this->nullableInt($data['department_id'] ?? null),
            'position_id' => $this->nullableInt($data['position_id'] ?? null),
            'manager_id' => $this->nullableInt($data['manager_id'] ?? null),
            'employee_number' => trim($data['employee_number'] ?? '') ?: $this->generateEmployeeNumber($companyId),
            'first_name' => trim($data['first_name'] ?? ''),
            'middle_name' => trim($data['middle_name'] ?? ''),
            'last_name' => trim($data['last_name'] ?? ''),
            'gender' => $this->normalize($data['gender'] ?? null, ['male', 'female', 'other']),
            'birth_date' => $data['birth_date'] ?: null,
            'birth_place' => trim($data['birth_place'] ?? ''),
            'marital_status' => $this->normalize($data['marital_status'] ?? null, ['single', 'married', 'divorced', 'widowed']),
            'hire_date' => $data['hire_date'] ?: date('Y-m-d'),
            'email' => trim($data['email'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'address' => trim($data['address'] ?? ''),
            'emergency_contact_name' => trim($data['emergency_contact_name'] ?? ''),
            'emergency_contact_phone' => trim($data['emergency_contact_phone'] ?? ''),
            'employment_status' => $this->normalize($data['employment_status'] ?? 'active', ['active', 'on_leave', 'suspended', 'terminated']) ?: 'active',
        ];

        if ($photoPath !== null) {
            $payload['photo_path'] = $photoPath;
        }

        Database::beginTransaction();

        try {
            if ($id === null) {
                $id = $this->create($payload);
            } else {
                $this->update($id, $payload);
            }

            $this->saveContract($id, $companyId, $data);
            Database::commit();

            return $id;
        } catch (\Throwable $exception) {
            Database::rollBack();
            throw $exception;
        }
    }

    public function archive(int $id): bool
    {
        return Database::query(
            "UPDATE employees
             SET employment_status = 'terminated', termination_date = COALESCE(termination_date, CURDATE()), deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL",
            ['id' => $id]
        )->rowCount() > 0;
    }

    public function addDocument(int $companyId, int $employeeId, array $data, string $path): int
    {
        Database::query(
            'INSERT INTO employee_documents
                (company_id, employee_id, document_type, title, file_path, file_name, mime_type, expires_at, created_at)
             VALUES
                (:company_id, :employee_id, :document_type, :title, :file_path, :file_name, :mime_type, :expires_at, NOW())',
            [
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'document_type' => trim($data['document_type'] ?? 'document'),
                'title' => trim($data['title'] ?? 'Document employe'),
                'file_path' => $path,
                'file_name' => $data['file_name'] ?? null,
                'mime_type' => $data['mime_type'] ?? null,
                'expires_at' => $data['expires_at'] ?: null,
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    public function companyOwnsBranch(int $companyId, ?int $branchId): bool
    {
        return $this->companyOwnsRecord('branches', $companyId, $branchId);
    }

    public function companyOwnsDepartment(int $companyId, ?int $departmentId): bool
    {
        return $this->companyOwnsRecord('departments', $companyId, $departmentId);
    }

    public function companyOwnsPosition(int $companyId, ?int $positionId): bool
    {
        return $this->companyOwnsRecord('positions', $companyId, $positionId);
    }

    public function companyOwnsManager(int $companyId, ?int $managerId, ?int $employeeId = null): bool
    {
        if ($managerId === null) {
            return true;
        }

        if ($employeeId !== null && $managerId === $employeeId) {
            return false;
        }

        return $this->companyOwnsRecord('employees', $companyId, $managerId);
    }

    public function dashboardStats(?int $companyId, bool $global): array
    {
        return [
            'total_employees' => $this->countEmployees($companyId, $global),
            'active_contracts' => $this->countActiveContracts($companyId, $global),
            'expiring_contracts' => $this->countExpiringContracts($companyId, $global),
            'pending_leave_requests' => $this->countPendingLeaveRequests($companyId, $global),
            'monthly_payroll' => $this->monthlyPayroll($companyId, $global),
            'present_today' => $this->countAttendanceToday($companyId, $global, ['present', 'late', 'half_day']),
            'absent_today' => $this->countAttendanceToday($companyId, $global, ['absent']),
        ];
    }

    public function attendanceTodayBreakdown(?int $companyId, bool $global): array
    {
        return $this->groupedCount(
            'attendance',
            'status',
            'attendance.deleted_at IS NULL AND attendance_date = CURDATE()',
            $companyId,
            $global
        );
    }

    public function leaveRequestsByStatus(?int $companyId, bool $global): array
    {
        return $this->groupedCount(
            'leave_requests',
            'status',
            'leave_requests.deleted_at IS NULL',
            $companyId,
            $global
        );
    }

    public function contractsByStatus(?int $companyId, bool $global): array
    {
        return $this->groupedCount(
            'contracts',
            'status',
            'contracts.deleted_at IS NULL',
            $companyId,
            $global
        );
    }

    private function countEmployees(?int $companyId, bool $global): int
    {
        [$scope, $params] = $this->scope($companyId, $global, 'employees');

        return (int) Database::query(
            "SELECT COUNT(*) FROM employees WHERE employees.deleted_at IS NULL {$scope}",
            $params
        )->fetchColumn();
    }

    private function countActiveContracts(?int $companyId, bool $global): int
    {
        [$scope, $params] = $this->scope($companyId, $global, 'contracts');

        return (int) Database::query(
            "SELECT COUNT(*) FROM contracts
             WHERE contracts.deleted_at IS NULL
             AND contracts.status = 'active'
             AND contracts.start_date <= CURDATE()
             AND (contracts.end_date IS NULL OR contracts.end_date >= CURDATE())
             {$scope}",
            $params
        )->fetchColumn();
    }

    private function countExpiringContracts(?int $companyId, bool $global): int
    {
        [$scope, $params] = $this->scope($companyId, $global, 'contracts');

        return (int) Database::query(
            "SELECT COUNT(*) FROM contracts
             WHERE contracts.deleted_at IS NULL
             AND contracts.status = 'active'
             AND contracts.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             {$scope}",
            $params
        )->fetchColumn();
    }

    private function countPendingLeaveRequests(?int $companyId, bool $global): int
    {
        [$scope, $params] = $this->scope($companyId, $global, 'leave_requests');

        return (int) Database::query(
            "SELECT COUNT(*) FROM leave_requests
             WHERE leave_requests.deleted_at IS NULL
             AND leave_requests.status = 'pending'
             {$scope}",
            $params
        )->fetchColumn();
    }

    private function monthlyPayroll(?int $companyId, bool $global): float
    {
        [$scope, $params] = $this->scope($companyId, $global, 'payroll_periods');

        return (float) Database::query(
            "SELECT COALESCE(SUM(payslips.net_salary), 0)
             FROM payslips
             INNER JOIN payroll_periods ON payroll_periods.id = payslips.payroll_period_id
             WHERE payslips.deleted_at IS NULL
             AND payroll_periods.deleted_at IS NULL
             AND payroll_periods.period_month = MONTH(CURDATE())
             AND payroll_periods.period_year = YEAR(CURDATE())
             {$scope}",
            $params
        )->fetchColumn();
    }

    private function countAttendanceToday(?int $companyId, bool $global, array $statuses): int
    {
        [$scope, $params] = $this->scope($companyId, $global, 'attendance');
        $placeholders = [];

        foreach ($statuses as $index => $status) {
            $key = 'status_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }

        return (int) Database::query(
            sprintf(
                'SELECT COUNT(*) FROM attendance
                 WHERE attendance.deleted_at IS NULL
                 AND attendance.attendance_date = CURDATE()
                 AND attendance.status IN (%s)
                 %s',
                implode(', ', $placeholders),
                $scope
            ),
            $params
        )->fetchColumn();
    }

    private function groupedCount(string $table, string $column, string $baseWhere, ?int $companyId, bool $global): array
    {
        [$scope, $params] = $this->scope($companyId, $global, $table);

        $sql = "SELECT {$table}.{$column} AS label, COUNT(*) AS total
                FROM {$table}
                WHERE {$baseWhere}
                {$scope}
                GROUP BY {$table}.{$column}
                ORDER BY total DESC";

        return Database::query($sql, $params)->fetchAll();
    }

    private function employeeWhere(?int $companyId, array $filters, string $table): array
    {
        $where = ["{$table}.deleted_at IS NULL"];
        $params = [];

        if ($companyId !== null) {
            $where[] = "{$table}.company_id = :scope_company_id";
            $params['scope_company_id'] = $companyId;
        }

        foreach (['id', 'company_id', 'branch_id', 'department_id', 'position_id'] as $field) {
            if (!empty($filters[$field])) {
                $key = 'filter_' . $field;
                $where[] = "{$table}.{$field} = :{$key}";
                $params[$key] = (int) $filters[$field];
            }
        }

        if (!empty($filters['employment_status'])) {
            $where[] = "{$table}.employment_status = :employment_status";
            $params['employment_status'] = $filters['employment_status'];
        }

        return [implode(' AND ', $where), $params];
    }

    private function optionRows(string $table, string $labelColumn, ?int $companyId): array
    {
        [$scope, $params] = $this->companyScope($companyId, $table);

        return Database::query(
            "SELECT id, {$labelColumn} AS name, company_id
             FROM {$table}
             WHERE deleted_at IS NULL {$scope}
             ORDER BY {$labelColumn} ASC",
            $params
        )->fetchAll();
    }

    private function managerRows(?int $companyId): array
    {
        [$scope, $params] = $this->companyScope($companyId, 'employees');

        return Database::query(
            "SELECT id, first_name, middle_name, last_name, company_id
             FROM employees
             WHERE deleted_at IS NULL {$scope}
             ORDER BY last_name ASC, first_name ASC",
            $params
        )->fetchAll();
    }

    private function companyScope(?int $companyId, string $table): array
    {
        if ($companyId === null) {
            return ['', []];
        }

        return [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }

    private function saveContract(int $employeeId, int $companyId, array $data): void
    {
        $contractType = $this->normalize($data['contract_type'] ?? 'cdi', ['cdi', 'cdd', 'consultant', 'internship', 'temporary']) ?: 'cdi';
        $baseSalary = (float) ($data['base_salary'] ?? 0);

        $existing = Database::query(
            'SELECT id FROM contracts
             WHERE employee_id = :employee_id
             AND deleted_at IS NULL
             ORDER BY start_date DESC, id DESC
             LIMIT 1',
            ['employee_id' => $employeeId]
        )->fetch();

        if ($existing) {
            Database::query(
                'UPDATE contracts
                 SET contract_type = :contract_type,
                     start_date = :start_date,
                     base_salary = :base_salary,
                     status = :status,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    'contract_type' => $contractType,
                    'start_date' => $data['hire_date'] ?: date('Y-m-d'),
                    'base_salary' => $baseSalary,
                    'status' => $this->contractStatus($data['employment_status'] ?? 'active'),
                    'id' => (int) $existing['id'],
                ]
            );
            return;
        }

        Database::query(
            'INSERT INTO contracts
                (company_id, employee_id, contract_number, contract_type, start_date, base_salary, currency, status, created_at)
             VALUES
                (:company_id, :employee_id, :contract_number, :contract_type, :start_date, :base_salary, :currency, :status, NOW())',
            [
                'company_id' => $companyId,
                'employee_id' => $employeeId,
                'contract_number' => 'CTR-' . $employeeId . '-' . date('YmdHis'),
                'contract_type' => $contractType,
                'start_date' => $data['hire_date'] ?: date('Y-m-d'),
                'base_salary' => $baseSalary,
                'currency' => $data['currency'] ?? 'USD',
                'status' => $this->contractStatus($data['employment_status'] ?? 'active'),
            ]
        );
    }

    private function nullableInt($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function companyOwnsRecord(string $table, int $companyId, ?int $id): bool
    {
        if ($id === null) {
            return true;
        }

        if (!in_array($table, ['branches', 'departments', 'positions', 'employees'], true)) {
            return false;
        }

        return (int) Database::query(
            "SELECT COUNT(*) FROM {$table}
             WHERE id = :id
             AND company_id = :company_id
             AND deleted_at IS NULL",
            ['id' => $id, 'company_id' => $companyId]
        )->fetchColumn() > 0;
    }

    private function normalize($value, array $allowed): ?string
    {
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function contractStatus(string $employmentStatus): string
    {
        return $employmentStatus === 'terminated' ? 'terminated' : 'active';
    }

    private function scope(?int $companyId, bool $global, string $table): array
    {
        if ($global) {
            return ['', []];
        }

        if ($companyId === null) {
            return [' AND 1 = 0', []];
        }

        return [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }
}
