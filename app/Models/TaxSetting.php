<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class TaxSetting extends Model
{
    protected string $table = 'tax_settings';
    protected array $fillable = [
        'company_id',
        'name',
        'tax_code',
        'rate',
        'threshold_min',
        'threshold_max',
        'is_active',
    ];

    public function allForCompany(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'tax_settings');

        return Database::query(
            "SELECT tax_settings.*, companies.name AS company_name
             FROM tax_settings
             INNER JOIN companies ON companies.id = tax_settings.company_id
             WHERE tax_settings.deleted_at IS NULL
             {$scope}
             ORDER BY companies.name ASC, tax_settings.tax_code ASC, tax_settings.threshold_min ASC",
            $params
        )->fetchAll();
    }

    public function calculate(string $code, int $companyId, float $taxableAmount): float
    {
        $settings = Database::query(
            "SELECT * FROM tax_settings
             WHERE company_id = :company_id
             AND tax_code = :code
             AND is_active = 1
             AND deleted_at IS NULL
             ORDER BY threshold_min ASC",
            ['company_id' => $companyId, 'code' => $code]
        )->fetchAll();

        $total = 0.00;
        foreach ($settings as $setting) {
            $min = (float) $setting['threshold_min'];
            $max = $setting['threshold_max'] !== null ? (float) $setting['threshold_max'] : null;
            if ($taxableAmount <= $min) {
                continue;
            }

            $base = $max === null ? $taxableAmount - $min : min($taxableAmount, $max) - $min;
            if ($base > 0) {
                $total += $base * ((float) $setting['rate'] / 100);
            }
        }

        return round($total, 2);
    }

    public function findScoped(int $id, ?int $companyId): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'tax_settings');
        $params['id'] = $id;
        $row = Database::query(
            "SELECT tax_settings.*, companies.name AS company_name
             FROM tax_settings INNER JOIN companies ON companies.id = tax_settings.company_id
             WHERE tax_settings.id = :id AND tax_settings.deleted_at IS NULL {$scope} LIMIT 1",
            $params
        )->fetch();
        return $row ?: null;
    }

    public function saveSetting(array $data, ?int $id = null): int
    {
        $payload = [
            'company_id' => (int) ($data['company_id'] ?? 0),
            'name' => trim($data['name'] ?? ''),
            'tax_code' => strtoupper(trim($data['tax_code'] ?? 'IPR')),
            'rate' => (float) ($data['rate'] ?? 0),
            'threshold_min' => (float) ($data['threshold_min'] ?? 0),
            'threshold_max' => ($data['threshold_max'] ?? '') === '' ? null : (float) $data['threshold_max'],
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
        if ($id !== null) {
            $this->update($id, $payload);
            return $id;
        }
        $deletedId = Database::query(
            'SELECT id FROM tax_settings
             WHERE company_id=:company_id AND tax_code=:tax_code
             AND threshold_min=:threshold_min AND deleted_at IS NOT NULL LIMIT 1',
            [
                'company_id' => $payload['company_id'],
                'tax_code' => $payload['tax_code'],
                'threshold_min' => $payload['threshold_min'],
            ]
        )->fetchColumn();
        if ($deletedId) {
            Database::query(
                "UPDATE tax_settings SET name=:name, rate=:rate, threshold_min=:threshold_min,
                    threshold_max=:threshold_max, is_active=:is_active, deleted_at=NULL, updated_at=NOW()
                 WHERE id=:id",
                [
                    'name' => $payload['name'],
                    'rate' => $payload['rate'],
                    'threshold_min' => $payload['threshold_min'],
                    'threshold_max' => $payload['threshold_max'],
                    'is_active' => $payload['is_active'],
                    'id' => (int) $deletedId,
                ]
            );
            return (int) $deletedId;
        }
        return $this->create($payload);
    }

    public function softDeleteScoped(int $id, ?int $companyId): bool
    {
        [$scope, $params] = $this->scope($companyId, 'tax_settings');
        $params['id'] = $id;
        return Database::query(
            "UPDATE tax_settings SET deleted_at=NOW(), updated_at=NOW()
             WHERE id=:id AND deleted_at IS NULL {$scope}",
            $params
        )->rowCount() > 0;
    }

    private function scope(?int $companyId, string $table): array
    {
        if ($companyId === null) {
            return ['', []];
        }

        return [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }
}
