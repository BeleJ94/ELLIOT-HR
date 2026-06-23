<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Position extends Model
{
    protected string $table = 'positions';
    protected array $fillable = [
        'company_id',
        'department_id',
        'title',
        'code',
        'description',
    ];

    public function tableRows(?int $companyId = null): array
    {
        [$scope, $params] = $this->scope($companyId, 'positions');

        return Database::query(
            "SELECT positions.*,
                    companies.name AS company_name,
                    departments.name AS department_name,
                    (
                        SELECT COUNT(*)
                        FROM employees
                        WHERE employees.position_id = positions.id
                        AND employees.deleted_at IS NULL
                    ) AS employees_count
             FROM positions
             INNER JOIN companies ON companies.id = positions.company_id
             LEFT JOIN departments ON departments.id = positions.department_id
             WHERE positions.deleted_at IS NULL {$scope}
             ORDER BY companies.name ASC, departments.name ASC, positions.title ASC",
            $params
        )->fetchAll();
    }

    public function findScoped(int $id, ?int $companyId = null): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'positions');
        $params['id'] = $id;

        $position = Database::query(
            "SELECT * FROM positions
             WHERE id = :id
             AND deleted_at IS NULL {$scope}
             LIMIT 1",
            $params
        )->fetch();

        return $position ?: null;
    }

    public function savePosition(array $data, ?int $id = null): int
    {
        $payload = [
            'company_id' => (int) ($data['company_id'] ?? 0),
            'department_id' => $this->nullableInt($data['department_id'] ?? null),
            'title' => trim($data['title'] ?? ''),
            'code' => trim($data['code'] ?? '') ?: null,
            'description' => trim($data['description'] ?? ''),
        ];

        if ($id === null) {
            return $this->create($payload);
        }

        $this->update($id, $payload);
        return $id;
    }

    public function softDeleteScoped(int $id, ?int $companyId = null): bool
    {
        [$scope, $params] = $this->scope($companyId, 'positions');
        $params['id'] = $id;

        return Database::query(
            "UPDATE positions
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
            'departments' => $this->departments($companyId),
        ];
    }

    public function companyOwnsDepartment(int $companyId, ?int $departmentId): bool
    {
        if ($departmentId === null) {
            return true;
        }

        return (int) Database::query(
            'SELECT COUNT(*) FROM departments
             WHERE id = :id
             AND company_id = :company_id
             AND deleted_at IS NULL',
            ['id' => $departmentId, 'company_id' => $companyId]
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

    private function departments(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'departments');

        return Database::query(
            "SELECT id, name, company_id
             FROM departments
             WHERE deleted_at IS NULL {$scope}
             ORDER BY name ASC",
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
