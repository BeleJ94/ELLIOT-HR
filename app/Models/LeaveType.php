<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class LeaveType extends Model
{
    protected string $table = 'leave_types';
    protected array $fillable = [
        'company_id',
        'name',
        'code',
        'paid',
        'annual_days',
    ];

    private array $defaults = [
        ['name' => 'Conge annuel', 'code' => 'ANNUAL', 'paid' => 1, 'annual_days' => 26.00],
        ['name' => 'Conge maladie', 'code' => 'SICK', 'paid' => 1, 'annual_days' => 0.00],
        ['name' => 'Conge maternite', 'code' => 'MATERNITY', 'paid' => 1, 'annual_days' => 0.00],
        ['name' => 'Conge paternite', 'code' => 'PATERNITY', 'paid' => 1, 'annual_days' => 0.00],
        ['name' => 'Conge exceptionnel', 'code' => 'EXCEPTIONAL', 'paid' => 1, 'annual_days' => 0.00],
        ['name' => 'Absence autorisee', 'code' => 'AUTHORIZED_ABSENCE', 'paid' => 1, 'annual_days' => 0.00],
        ['name' => 'Absence non autorisee', 'code' => 'UNAUTHORIZED_ABSENCE', 'paid' => 0, 'annual_days' => 0.00],
    ];

    public function allForCompany(?int $companyId): array
    {
        [$scope, $params] = $this->companyScope($companyId, 'leave_types');

        return Database::query(
            "SELECT leave_types.*, companies.name AS company_name
             FROM leave_types
             INNER JOIN companies ON companies.id = leave_types.company_id
             WHERE leave_types.deleted_at IS NULL
             {$scope}
             ORDER BY companies.name ASC, leave_types.name ASC",
            $params
        )->fetchAll();
    }

    public function ensureDefaults(?int $companyId): void
    {
        $companies = $this->companies($companyId);

        foreach ($companies as $company) {
            foreach ($this->defaults as $default) {
                $exists = (int) Database::query(
                    'SELECT COUNT(*) FROM leave_types
                     WHERE company_id = :company_id
                     AND code = :code
                     AND deleted_at IS NULL',
                    ['company_id' => (int) $company['id'], 'code' => $default['code']]
                )->fetchColumn();

                if ($exists > 0) {
                    continue;
                }

                $this->create([
                    'company_id' => (int) $company['id'],
                    'name' => $default['name'],
                    'code' => $default['code'],
                    'paid' => $default['paid'],
                    'annual_days' => $default['annual_days'],
                ]);
            }
        }
    }

    public function saveType(array $data, ?int $id = null): int
    {
        $payload = [
            'company_id' => (int) ($data['company_id'] ?? 0),
            'name' => trim($data['name'] ?? ''),
            'code' => strtoupper(trim($data['code'] ?? '')),
            'paid' => !empty($data['paid']) ? 1 : 0,
            'annual_days' => (float) ($data['annual_days'] ?? 0),
        ];

        if ($id === null) {
            return $this->create($payload);
        }

        $this->update($id, $payload);
        return $id;
    }

    public function findScoped(int $id, ?int $companyId): ?array
    {
        [$scope, $params] = $this->companyScope($companyId, 'leave_types');
        $params['id'] = $id;

        $row = Database::query(
            "SELECT * FROM leave_types
             WHERE id = :id
             AND deleted_at IS NULL
             {$scope}
             LIMIT 1",
            $params
        )->fetch();

        return $row ?: null;
    }

    public function companyOwnsType(int $companyId, int $typeId): bool
    {
        return (int) Database::query(
            'SELECT COUNT(*) FROM leave_types
             WHERE id = :id
             AND company_id = :company_id
             AND deleted_at IS NULL',
            ['id' => $typeId, 'company_id' => $companyId]
        )->fetchColumn() > 0;
    }

    public function resolveForCompany(int $companyId, int $typeId): ?int
    {
        if ($this->companyOwnsType($companyId, $typeId)) {
            return $typeId;
        }

        $type = Database::query(
            'SELECT code FROM leave_types
             WHERE id = :id
             AND deleted_at IS NULL
             LIMIT 1',
            ['id' => $typeId]
        )->fetch();

        if (!$type || empty($type['code'])) {
            return null;
        }

        $equivalent = Database::query(
            'SELECT id FROM leave_types
             WHERE company_id = :company_id
             AND code = :code
             AND deleted_at IS NULL
             LIMIT 1',
            ['company_id' => $companyId, 'code' => $type['code']]
        )->fetchColumn();

        return $equivalent ? (int) $equivalent : null;
    }

    public function companies(?int $companyId): array
    {
        [$scope, $params] = $companyId === null ? ['', []] : [' AND id = :company_id', ['company_id' => $companyId]];

        return Database::query(
            "SELECT id, name
             FROM companies
             WHERE deleted_at IS NULL
             {$scope}
             ORDER BY name ASC",
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
}
