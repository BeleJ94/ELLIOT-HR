<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class PayrollItem extends Model
{
    protected string $table = 'payroll_items';
    protected array $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'calculation_type',
        'default_amount',
        'default_rate',
        'taxable',
    ];

    private array $defaults = [
        ['code' => 'BASE', 'name' => 'Salaire de base', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_amount' => 0.00, 'default_rate' => 0.0000, 'taxable' => 1],
        ['code' => 'IPR', 'name' => 'Impot professionnel sur remuneration', 'type' => 'tax', 'calculation_type' => 'percentage', 'default_amount' => 0.00, 'default_rate' => 0.0000, 'taxable' => 0],
        ['code' => 'CNSS', 'name' => 'Cotisation sociale', 'type' => 'contribution', 'calculation_type' => 'percentage', 'default_amount' => 0.00, 'default_rate' => 0.0000, 'taxable' => 0],
        ['code' => 'BONUS', 'name' => 'Prime', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_amount' => 0.00, 'default_rate' => 0.0000, 'taxable' => 1],
        ['code' => 'INDEMNITY', 'name' => 'Indemnite', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_amount' => 0.00, 'default_rate' => 0.0000, 'taxable' => 0],
        ['code' => 'ADVANCE', 'name' => 'Avance sur salaire', 'type' => 'deduction', 'calculation_type' => 'fixed', 'default_amount' => 0.00, 'default_rate' => 0.0000, 'taxable' => 0],
        ['code' => 'LOAN', 'name' => 'Remboursement pret', 'type' => 'deduction', 'calculation_type' => 'fixed', 'default_amount' => 0.00, 'default_rate' => 0.0000, 'taxable' => 0],
    ];

    public function ensureDefaults(?int $companyId): void
    {
        foreach ($this->companies($companyId) as $company) {
            foreach ($this->defaults as $default) {
                $exists = (int) Database::query(
                    'SELECT COUNT(*) FROM payroll_items
                     WHERE company_id = :company_id
                     AND code = :code',
                    ['company_id' => (int) $company['id'], 'code' => $default['code']]
                )->fetchColumn();

                if ($exists > 0) {
                    continue;
                }

                $default['company_id'] = (int) $company['id'];
                $this->create($default);
            }
        }
    }

    public function allForCompany(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'payroll_items');

        return Database::query(
            "SELECT payroll_items.*, companies.name AS company_name
             FROM payroll_items
             INNER JOIN companies ON companies.id = payroll_items.company_id
             WHERE payroll_items.deleted_at IS NULL
             {$scope}
             ORDER BY companies.name ASC, payroll_items.type ASC, payroll_items.name ASC",
            $params
        )->fetchAll();
    }

    public function findScoped(int $id, ?int $companyId): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'payroll_items');
        $params['id'] = $id;
        $row = Database::query(
            "SELECT payroll_items.*, companies.name AS company_name
             FROM payroll_items
             INNER JOIN companies ON companies.id = payroll_items.company_id
             WHERE payroll_items.id = :id AND payroll_items.deleted_at IS NULL {$scope}
             LIMIT 1",
            $params
        )->fetch();
        return $row ?: null;
    }

    public function saveItem(array $data, ?int $id = null): int
    {
        $payload = [
            'company_id' => (int) ($data['company_id'] ?? 0),
            'code' => strtoupper(trim($data['code'] ?? '')),
            'name' => trim($data['name'] ?? ''),
            'type' => $this->normalize($data['type'] ?? 'earning', ['earning', 'deduction', 'tax', 'contribution']),
            'calculation_type' => $this->normalize($data['calculation_type'] ?? 'fixed', ['fixed', 'percentage']),
            'default_amount' => (float) ($data['default_amount'] ?? 0),
            'default_rate' => (float) ($data['default_rate'] ?? 0),
            'taxable' => !empty($data['taxable']) ? 1 : 0,
        ];
        if ($id !== null) {
            $this->update($id, $payload);
            return $id;
        }
        $deletedId = Database::query(
            'SELECT id FROM payroll_items WHERE company_id = :company_id AND code = :code AND deleted_at IS NOT NULL LIMIT 1',
            ['company_id' => $payload['company_id'], 'code' => $payload['code']]
        )->fetchColumn();
        if ($deletedId) {
            Database::query(
                "UPDATE payroll_items SET name=:name, type=:type, calculation_type=:calculation_type,
                    default_amount=:default_amount, default_rate=:default_rate, taxable=:taxable,
                    deleted_at=NULL, updated_at=NOW() WHERE id=:id",
                [
                    'name' => $payload['name'],
                    'type' => $payload['type'],
                    'calculation_type' => $payload['calculation_type'],
                    'default_amount' => $payload['default_amount'],
                    'default_rate' => $payload['default_rate'],
                    'taxable' => $payload['taxable'],
                    'id' => (int) $deletedId,
                ]
            );
            return (int) $deletedId;
        }
        return $this->create($payload);
    }

    public function softDeleteScoped(int $id, ?int $companyId): bool
    {
        [$scope, $params] = $this->scope($companyId, 'payroll_items');
        $params['id'] = $id;
        return Database::query(
            "UPDATE payroll_items SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL {$scope}",
            $params
        )->rowCount() > 0;
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

    private function scope(?int $companyId, string $table): array
    {
        if ($companyId === null) {
            return ['', []];
        }

        return [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }

    private function normalize(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : $allowed[0];
    }
}
