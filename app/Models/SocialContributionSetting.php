<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class SocialContributionSetting extends Model
{
    protected string $table = 'social_contribution_settings';
    protected array $fillable = [
        'company_id',
        'name',
        'contribution_code',
        'employee_rate',
        'employer_rate',
        'ceiling_amount',
        'is_active',
    ];

    private array $defaults = [
        ['name' => 'CNSS', 'contribution_code' => 'CNSS'],
        ['name' => 'INPP', 'contribution_code' => 'INPP'],
        ['name' => 'ONEM', 'contribution_code' => 'ONEM'],
    ];

    public function ensureDefaults(?int $companyId): void
    {
        $companies = (new PayrollItem())->companies($companyId);

        foreach ($companies as $company) {
            foreach ($this->defaults as $default) {
                $exists = (int) Database::query(
                    'SELECT COUNT(*) FROM social_contribution_settings
                     WHERE company_id = :company_id
                     AND contribution_code = :code',
                    ['company_id' => (int) $company['id'], 'code' => $default['contribution_code']]
                )->fetchColumn();

                if ($exists > 0) {
                    continue;
                }

                $this->create([
                    'company_id' => (int) $company['id'],
                    'name' => $default['name'],
                    'contribution_code' => $default['contribution_code'],
                    'employee_rate' => 0,
                    'employer_rate' => 0,
                    'ceiling_amount' => null,
                    'is_active' => 1,
                ]);
            }
        }
    }

    public function allForCompany(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'social_contribution_settings');

        return Database::query(
            "SELECT social_contribution_settings.*, companies.name AS company_name
             FROM social_contribution_settings
             INNER JOIN companies ON companies.id = social_contribution_settings.company_id
             WHERE social_contribution_settings.deleted_at IS NULL
             {$scope}
             ORDER BY companies.name ASC, social_contribution_settings.contribution_code ASC",
            $params
        )->fetchAll();
    }

    public function activeForCompany(int $companyId): array
    {
        return Database::query(
            "SELECT * FROM social_contribution_settings
             WHERE company_id = :company_id
             AND is_active = 1
             AND deleted_at IS NULL
             ORDER BY contribution_code ASC",
            ['company_id' => $companyId]
        )->fetchAll();
    }

    public function findScoped(int $id, ?int $companyId): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'social_contribution_settings');
        $params['id'] = $id;
        $row = Database::query(
            "SELECT social_contribution_settings.*, companies.name AS company_name
             FROM social_contribution_settings
             INNER JOIN companies ON companies.id = social_contribution_settings.company_id
             WHERE social_contribution_settings.id=:id
             AND social_contribution_settings.deleted_at IS NULL {$scope} LIMIT 1",
            $params
        )->fetch();
        return $row ?: null;
    }

    public function saveSetting(array $data, ?int $id = null): int
    {
        $payload = [
            'company_id' => (int) ($data['company_id'] ?? 0),
            'name' => trim($data['name'] ?? ''),
            'contribution_code' => strtoupper(trim($data['contribution_code'] ?? '')),
            'employee_rate' => (float) ($data['employee_rate'] ?? 0),
            'employer_rate' => (float) ($data['employer_rate'] ?? 0),
            'ceiling_amount' => ($data['ceiling_amount'] ?? '') === '' ? null : (float) $data['ceiling_amount'],
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
        if ($id !== null) {
            $this->update($id, $payload);
            return $id;
        }
        $deletedId = Database::query(
            'SELECT id FROM social_contribution_settings
             WHERE company_id=:company_id AND contribution_code=:contribution_code
             AND deleted_at IS NOT NULL LIMIT 1',
            ['company_id' => $payload['company_id'], 'contribution_code' => $payload['contribution_code']]
        )->fetchColumn();
        if ($deletedId) {
            Database::query(
                "UPDATE social_contribution_settings SET name=:name, employee_rate=:employee_rate,
                    employer_rate=:employer_rate, ceiling_amount=:ceiling_amount, is_active=:is_active,
                    deleted_at=NULL, updated_at=NOW() WHERE id=:id",
                [
                    'name' => $payload['name'],
                    'employee_rate' => $payload['employee_rate'],
                    'employer_rate' => $payload['employer_rate'],
                    'ceiling_amount' => $payload['ceiling_amount'],
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
        [$scope, $params] = $this->scope($companyId, 'social_contribution_settings');
        $params['id'] = $id;
        return Database::query(
            "UPDATE social_contribution_settings SET deleted_at=NOW(), updated_at=NOW()
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
