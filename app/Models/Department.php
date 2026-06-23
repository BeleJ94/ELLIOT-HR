<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Department extends Model
{
    protected string $table = 'departments';
    protected array $fillable = [
        'company_id',
        'branch_id',
        'manager_id',
        'name',
        'code',
    ];

    public function tableRows(?int $companyId = null): array
    {
        [$scope, $params] = $this->scope($companyId, 'departments');

        return Database::query(
            "SELECT departments.*,
                    companies.name AS company_name,
                    branches.name AS branch_name,
                    managers.first_name AS manager_first_name,
                    managers.last_name AS manager_last_name,
                    (
                        SELECT COUNT(*)
                        FROM positions
                        WHERE positions.department_id = departments.id
                        AND positions.deleted_at IS NULL
                    ) AS positions_count,
                    (
                        SELECT COUNT(*)
                        FROM employees
                        WHERE employees.department_id = departments.id
                        AND employees.deleted_at IS NULL
                    ) AS employees_count
             FROM departments
             INNER JOIN companies ON companies.id = departments.company_id
             LEFT JOIN branches ON branches.id = departments.branch_id
             LEFT JOIN employees managers ON managers.id = departments.manager_id
             WHERE departments.deleted_at IS NULL {$scope}
             ORDER BY companies.name ASC, departments.name ASC",
            $params
        )->fetchAll();
    }

    public function findScoped(int $id, ?int $companyId = null): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'departments');
        $params['id'] = $id;

        $department = Database::query(
            "SELECT * FROM departments
             WHERE id = :id
             AND deleted_at IS NULL {$scope}
             LIMIT 1",
            $params
        )->fetch();

        return $department ?: null;
    }

    public function saveDepartment(array $data, ?int $id = null): int
    {
        $payload = [
            'company_id' => (int) ($data['company_id'] ?? 0),
            'branch_id' => $this->nullableInt($data['branch_id'] ?? null),
            'manager_id' => $this->nullableInt($data['manager_id'] ?? null),
            'name' => trim($data['name'] ?? ''),
            'code' => trim($data['code'] ?? '') ?: null,
        ];

        if ($id === null) {
            return $this->create($payload);
        }

        $this->update($id, $payload);
        return $id;
    }

    public function softDeleteScoped(int $id, ?int $companyId = null): bool
    {
        [$scope, $params] = $this->scope($companyId, 'departments');
        $params['id'] = $id;

        return Database::query(
            "UPDATE departments
             SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id
             AND deleted_at IS NULL {$scope}",
            $params
        )->rowCount() > 0;
    }

    public function formOptions(?int $companyId = null): array
    {
        return [
            'companies' => $this->companies($companyId),
            'branches' => $this->companyRows('branches', 'name', $companyId),
            'managers' => $this->managers($companyId),
        ];
    }

    public function orgChart(?int $companyId = null): array
    {
        $departments = $this->tableRows($companyId);
        $positions = (new Position())->tableRows($companyId);
        $groupedPositions = [];

        foreach ($positions as $position) {
            $key = (int) ($position['department_id'] ?? 0);
            $groupedPositions[$key][] = $position;
        }

        foreach ($departments as &$department) {
            $department['positions'] = $groupedPositions[(int) $department['id']] ?? [];
        }
        unset($department);

        return $departments;
    }

    public function companyOwnsBranch(int $companyId, ?int $branchId): bool
    {
        if ($branchId === null) {
            return true;
        }

        return (int) Database::query(
            'SELECT COUNT(*) FROM branches
             WHERE id = :id
             AND company_id = :company_id
             AND deleted_at IS NULL',
            ['id' => $branchId, 'company_id' => $companyId]
        )->fetchColumn() > 0;
    }

    public function companyOwnsManager(int $companyId, ?int $managerId): bool
    {
        if ($managerId === null) {
            return true;
        }

        return (int) Database::query(
            'SELECT COUNT(*) FROM employees
             WHERE id = :id
             AND company_id = :company_id
             AND deleted_at IS NULL',
            ['id' => $managerId, 'company_id' => $companyId]
        )->fetchColumn() > 0;
    }

    private function companies(?int $companyId): array
    {
        [$scope, $params] = $companyId === null ? ['', []] : [' AND id = :company_id', ['company_id' => $companyId]];

        return Database::query(
            "SELECT id, name
             FROM companies
             WHERE deleted_at IS NULL {$scope}
             ORDER BY name ASC",
            $params
        )->fetchAll();
    }

    private function companyRows(string $table, string $labelColumn, ?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, $table);

        return Database::query(
            "SELECT id, {$labelColumn} AS name, company_id
             FROM {$table}
             WHERE deleted_at IS NULL {$scope}
             ORDER BY {$labelColumn} ASC",
            $params
        )->fetchAll();
    }

    private function managers(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'employees');

        return Database::query(
            "SELECT id, first_name, middle_name, last_name, company_id
             FROM employees
             WHERE deleted_at IS NULL {$scope}
             ORDER BY last_name ASC, first_name ASC",
            $params
        )->fetchAll();
    }

    private function scope(?int $companyId, string $table): array
    {
        if ($companyId === null) {
            return ['', []];
        }

        return [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }

    private function nullableInt($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
