<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Contract extends Model
{
    protected string $table = 'contracts';
    protected array $fillable = [
        'company_id',
        'employee_id',
        'contract_number',
        'contract_type',
        'start_date',
        'end_date',
        'base_salary',
        'currency',
        'status',
        'probation_ends_at',
        'renewed_from_id',
        'renewed_at',
        'pdf_path',
        'signed_contract_path',
        'signed_contract_name',
        'signed_contract_mime',
    ];

    public function allWithDetails(?int $companyId = null): array
    {
        $this->expireOverdue($companyId);
        [$scope, $params] = $this->scope($companyId, 'contracts');

        return Database::query(
            "SELECT contracts.*,
                    companies.name AS company_name,
                    employees.employee_number,
                    employees.first_name,
                    employees.middle_name,
                    employees.last_name,
                    departments.name AS department_name,
                    positions.title AS position_title
             FROM contracts
             INNER JOIN companies ON companies.id = contracts.company_id
             INNER JOIN employees ON employees.id = contracts.employee_id
             LEFT JOIN departments ON departments.id = employees.department_id
             LEFT JOIN positions ON positions.id = employees.position_id
             WHERE contracts.deleted_at IS NULL {$scope}
             ORDER BY contracts.created_at DESC",
            $params
        )->fetchAll();
    }

    public function findDetailed(int $id, ?int $companyId = null): ?array
    {
        $this->expireOverdue($companyId);
        [$scope, $params] = $this->scope($companyId, 'contracts');
        $params['id'] = $id;

        $contract = Database::query(
            "SELECT contracts.*,
                    companies.name AS company_name,
                    companies.legal_name AS company_legal_name,
                    companies.address AS company_address,
                    companies.city AS company_city,
                    companies.province AS company_province,
                    companies.country AS company_country,
                    companies.registration_number,
                    companies.tax_number,
                    employees.employee_number,
                    employees.first_name,
                    employees.middle_name,
                    employees.last_name,
                    employees.email,
                    employees.phone,
                    employees.address AS employee_address,
                    departments.name AS department_name,
                    positions.title AS position_title,
                    renewed.contract_number AS renewed_from_number
             FROM contracts
             INNER JOIN companies ON companies.id = contracts.company_id
             INNER JOIN employees ON employees.id = contracts.employee_id
             LEFT JOIN departments ON departments.id = employees.department_id
             LEFT JOIN positions ON positions.id = employees.position_id
             LEFT JOIN contracts renewed ON renewed.id = contracts.renewed_from_id
             WHERE contracts.id = :id
             AND contracts.deleted_at IS NULL {$scope}
             LIMIT 1",
            $params
        )->fetch();

        return $contract ?: null;
    }

    public function expiringSoon(?int $companyId = null, int $days = 30): array
    {
        $this->expireOverdue($companyId);
        [$scope, $params] = $this->scope($companyId, 'contracts');
        $params['days'] = max(1, min(180, $days));

        return Database::query(
            "SELECT contracts.*,
                    companies.name AS company_name,
                    employees.employee_number,
                    employees.first_name,
                    employees.middle_name,
                    employees.last_name
             FROM contracts
             INNER JOIN companies ON companies.id = contracts.company_id
             INNER JOIN employees ON employees.id = contracts.employee_id
             WHERE contracts.deleted_at IS NULL
             AND contracts.status = 'active'
             AND contracts.end_date IS NOT NULL
             AND contracts.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
             {$scope}
             ORDER BY contracts.end_date ASC",
            $params
        )->fetchAll();
    }

    public function formOptions(?int $companyId = null): array
    {
        [$companyScope, $companyParams] = $companyId === null ? ['', []] : [' AND id = :company_id', ['company_id' => $companyId]];
        [$employeeScope, $employeeParams] = $this->scope($companyId, 'employees');

        return [
            'companies' => Database::query(
                "SELECT id, name FROM companies WHERE deleted_at IS NULL {$companyScope} ORDER BY name ASC",
                $companyParams
            )->fetchAll(),
            'employees' => Database::query(
                "SELECT employees.id,
                        employees.company_id,
                        employees.employee_number,
                        employees.first_name,
                        employees.middle_name,
                        employees.last_name,
                        companies.name AS company_name
                 FROM employees
                 INNER JOIN companies ON companies.id = employees.company_id
                 WHERE employees.deleted_at IS NULL {$employeeScope}
                 ORDER BY employees.last_name ASC, employees.first_name ASC",
                $employeeParams
            )->fetchAll(),
        ];
    }

    public function saveContract(array $data, ?int $id = null): int
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $payload = [
            'company_id' => $companyId,
            'employee_id' => (int) ($data['employee_id'] ?? 0),
            'contract_number' => trim($data['contract_number'] ?? '') ?: $this->generateContractNumber($companyId),
            'contract_type' => $this->normalizeType($data['contract_type'] ?? 'cdi'),
            'start_date' => $data['start_date'] ?: date('Y-m-d'),
            'end_date' => $data['end_date'] ?: null,
            'probation_ends_at' => $data['probation_ends_at'] ?: null,
            'base_salary' => (float) ($data['base_salary'] ?? 0),
            'currency' => trim($data['currency'] ?? 'USD') ?: 'USD',
            'status' => $this->normalizeStatus($data['status'] ?? 'active'),
        ];

        if ($id === null) {
            return $this->create($payload);
        }

        $this->update($id, $payload);
        return $id;
    }

    public function renew(int $id, array $data, ?int $companyId = null): ?int
    {
        $contract = $this->findDetailed($id, $companyId);
        if (!$contract) {
            return null;
        }

        $startDate = $data['start_date'] ?? '';
        if ($startDate === '' && !empty($contract['end_date'])) {
            $startDate = date('Y-m-d', strtotime($contract['end_date'] . ' +1 day'));
        }

        $newId = $this->create([
            'company_id' => (int) $contract['company_id'],
            'employee_id' => (int) $contract['employee_id'],
            'contract_number' => trim($data['contract_number'] ?? '') ?: $this->generateContractNumber((int) $contract['company_id']),
            'contract_type' => $this->normalizeType($data['contract_type'] ?? $contract['contract_type']),
            'start_date' => $startDate ?: date('Y-m-d'),
            'end_date' => ($data['end_date'] ?? '') ?: null,
            'probation_ends_at' => ($data['probation_ends_at'] ?? '') ?: null,
            'base_salary' => (float) ($data['base_salary'] ?? $contract['base_salary']),
            'currency' => $data['currency'] ?? $contract['currency'] ?? 'USD',
            'status' => 'active',
            'renewed_from_id' => $id,
            'renewed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->update($id, ['status' => 'expired']);

        return $newId;
    }

    public function expireOverdue(?int $companyId = null): int
    {
        [$scope, $params] = $this->scope($companyId, 'contracts');

        return Database::query(
            "UPDATE contracts
             SET status = 'expired', updated_at = NOW()
             WHERE deleted_at IS NULL
             AND status = 'active'
             AND end_date IS NOT NULL
             AND end_date < CURDATE()
             {$scope}",
            $params
        )->rowCount();
    }

    public function updatePdfPath(int $id, string $path): bool
    {
        return $this->update($id, ['pdf_path' => $path]);
    }

    public function updateSignedContract(int $id, string $path, string $name, string $mime): bool
    {
        return $this->update($id, [
            'signed_contract_path' => $path,
            'signed_contract_name' => $name,
            'signed_contract_mime' => $mime,
        ]);
    }

    public function companyOwnsEmployee(int $companyId, int $employeeId): bool
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM employees
             WHERE id = :employee_id
             AND company_id = :company_id
             AND deleted_at IS NULL',
            ['employee_id' => $employeeId, 'company_id' => $companyId]
        )->fetchColumn() > 0;
    }

    public function employeeCompanyId(int $employeeId): ?int
    {
        $companyId = Database::query(
            'SELECT company_id FROM employees
             WHERE id = :employee_id
             AND deleted_at IS NULL
             LIMIT 1',
            ['employee_id' => $employeeId]
        )->fetchColumn();

        return $companyId !== false ? (int) $companyId : null;
    }

    public function generateContractNumber(int $companyId): string
    {
        $year = date('Y');
        $count = (int) Database::query(
            'SELECT COUNT(*) FROM contracts WHERE company_id = :company_id AND YEAR(created_at) = :year',
            ['company_id' => $companyId, 'year' => $year]
        )->fetchColumn();

        do {
            $number = sprintf('CTR-%s-%04d', $year, ++$count);
            $exists = Database::query(
                'SELECT COUNT(*) FROM contracts WHERE company_id = :company_id AND contract_number = :number',
                ['company_id' => $companyId, 'number' => $number]
            )->fetchColumn();
        } while ((int) $exists > 0);

        return $number;
    }

    private function scope(?int $companyId, string $table): array
    {
        if ($companyId === null) {
            return ['', []];
        }

        return [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }

    private function normalizeType(string $type): string
    {
        return in_array($type, ['cdi', 'cdd', 'internship', 'consultant', 'temporary'], true) ? $type : 'cdi';
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['draft', 'active', 'expired', 'terminated'], true) ? $status : 'active';
    }
}
