<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Company extends Model
{
    protected string $table = 'companies';
    protected array $fillable = [
        'subscription_plan_id',
        'name',
        'legal_name',
        'registration_number',
        'national_id',
        'tax_number',
        'email',
        'phone',
        'address',
        'city',
        'province',
        'country',
        'industry',
        'status',
    ];

    public function allWithStats(?int $companyId = null): array
    {
        $scope = '';
        $params = [];

        if ($companyId !== null) {
            $scope = ' AND companies.id = :company_id';
            $params['company_id'] = $companyId;
        }

        return Database::query(
            "SELECT companies.*,
                    subscription_plans.name AS plan_name,
                    subscriptions.status AS subscription_status,
                    (
                        SELECT COUNT(*)
                        FROM employees
                        WHERE employees.company_id = companies.id
                        AND employees.deleted_at IS NULL
                    ) AS employees_count,
                    (
                        SELECT COUNT(*)
                        FROM branches
                        WHERE branches.company_id = companies.id
                        AND branches.deleted_at IS NULL
                    ) AS branches_count
             FROM companies
             LEFT JOIN subscriptions ON subscriptions.id = (
                SELECT s.id
                FROM subscriptions s
                WHERE s.company_id = companies.id
                AND s.deleted_at IS NULL
                ORDER BY s.id DESC
                LIMIT 1
             )
                AND subscriptions.deleted_at IS NULL
             LEFT JOIN subscription_plans ON subscription_plans.id = subscriptions.subscription_plan_id
             WHERE companies.deleted_at IS NULL
             {$scope}
             ORDER BY companies.created_at DESC",
            $params
        )->fetchAll();
    }

    public function findDetailed(int $id): ?array
    {
        $company = Database::query(
            "SELECT companies.*,
                    subscription_plans.name AS plan_name,
                    subscription_plans.code AS plan_code,
                    subscriptions.id AS subscription_id,
                    subscriptions.subscription_plan_id AS current_subscription_plan_id,
                    subscriptions.status AS subscription_status,
                    subscriptions.starts_at,
                    subscriptions.ends_at,
                    subscriptions.trial_ends_at
             FROM companies
             LEFT JOIN subscriptions ON subscriptions.id = (
                SELECT s.id
                FROM subscriptions s
                WHERE s.company_id = companies.id
                AND s.deleted_at IS NULL
                ORDER BY s.id DESC
                LIMIT 1
             )
                AND subscriptions.deleted_at IS NULL
             LEFT JOIN subscription_plans ON subscription_plans.id = subscriptions.subscription_plan_id
             WHERE companies.id = :id
             AND companies.deleted_at IS NULL
             LIMIT 1",
            ['id' => $id]
        )->fetch();

        return $company ?: null;
    }

    public function branches(int $companyId): array
    {
        return Database::query(
            'SELECT * FROM branches
             WHERE company_id = :company_id
             AND deleted_at IS NULL
             ORDER BY is_head_office DESC, name ASC',
            ['company_id' => $companyId]
        )->fetchAll();
    }

    public function subscriptionPlans(): array
    {
        return Database::query(
            'SELECT * FROM subscription_plans
             WHERE deleted_at IS NULL
             AND is_active = 1
             ORDER BY monthly_price ASC, name ASC'
        )->fetchAll();
    }

    public function saveCompany(array $data, ?int $id = null): int
    {
        $payload = [
            'name' => trim($data['name'] ?? ''),
            'legal_name' => trim($data['legal_name'] ?? $data['name'] ?? ''),
            'registration_number' => trim($data['registration_number'] ?? ''),
            'national_id' => trim($data['national_id'] ?? ''),
            'tax_number' => trim($data['tax_number'] ?? ''),
            'address' => trim($data['address'] ?? ''),
            'city' => trim($data['city'] ?? ''),
            'province' => trim($data['province'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'industry' => trim($data['industry'] ?? ''),
            'country' => trim($data['country'] ?? 'RDC'),
            'status' => $this->normalizeStatus($data['status'] ?? 'active'),
        ];

        if ($id === null) {
            return $this->create($payload);
        }

        $this->update($id, $payload);
        return $id;
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $this->normalizeStatus($status)]);
    }

    public function saveBranch(int $companyId, array $data): int
    {
        Database::query(
            'INSERT INTO branches
                (company_id, name, code, email, phone, address, city, is_head_office, created_at)
             VALUES
                (:company_id, :name, :code, :email, :phone, :address, :city, :is_head_office, NOW())',
            [
                'company_id' => $companyId,
                'name' => trim($data['name'] ?? ''),
                'code' => trim($data['code'] ?? '') ?: null,
                'email' => trim($data['email'] ?? ''),
                'phone' => trim($data['phone'] ?? ''),
                'address' => trim($data['address'] ?? ''),
                'city' => trim($data['city'] ?? ''),
                'is_head_office' => !empty($data['is_head_office']) ? 1 : 0,
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    public function deleteBranch(int $branchId, ?int $companyId = null): bool
    {
        $scope = '';
        $params = ['id' => $branchId];

        if ($companyId !== null) {
            $scope = ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        return Database::query(
            'UPDATE branches SET deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL' . $scope,
            $params
        )->rowCount() > 0;
    }

    public function saveSubscription(int $companyId, array $data): int
    {
        $planId = (int) ($data['subscription_plan_id'] ?? 0);
        $status = $this->normalizeSubscriptionStatus($data['status'] ?? 'trial');

        $existing = Database::query(
            'SELECT id FROM subscriptions
             WHERE company_id = :company_id
             AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1',
            ['company_id' => $companyId]
        )->fetch();

        if ($existing) {
            Database::query(
                'UPDATE companies SET subscription_plan_id = :plan_id, updated_at = NOW() WHERE id = :company_id',
                ['plan_id' => $planId, 'company_id' => $companyId]
            );

            Database::query(
                'UPDATE subscriptions
                 SET subscription_plan_id = :plan_id,
                     status = :status,
                     starts_at = :starts_at,
                     ends_at = :ends_at,
                     trial_ends_at = :trial_ends_at,
                     updated_at = NOW()
                 WHERE id = :id',
                [
                    'plan_id' => $planId,
                    'status' => $status,
                    'starts_at' => $data['starts_at'] ?: date('Y-m-d'),
                    'ends_at' => $data['ends_at'] ?: null,
                    'trial_ends_at' => $data['trial_ends_at'] ?: null,
                    'id' => (int) $existing['id'],
                ]
            );

            return (int) $existing['id'];
        }

        Database::query(
            'UPDATE companies SET subscription_plan_id = :plan_id, updated_at = NOW() WHERE id = :company_id',
            ['plan_id' => $planId, 'company_id' => $companyId]
        );

        Database::query(
            'INSERT INTO subscriptions
                (company_id, subscription_plan_id, status, starts_at, ends_at, trial_ends_at, created_at)
             VALUES
                (:company_id, :plan_id, :status, :starts_at, :ends_at, :trial_ends_at, NOW())',
            [
                'company_id' => $companyId,
                'plan_id' => $planId,
                'status' => $status,
                'starts_at' => $data['starts_at'] ?: date('Y-m-d'),
                'ends_at' => $data['ends_at'] ?: null,
                'trial_ends_at' => $data['trial_ends_at'] ?: null,
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['active', 'suspended', 'inactive'], true) ? $status : 'active';
    }

    private function normalizeSubscriptionStatus(string $status): string
    {
        return in_array($status, ['trial', 'active', 'past_due', 'cancelled', 'expired'], true) ? $status : 'trial';
    }

    public function dashboardStats(?int $companyId, bool $global): array
    {
        return [
            'total_companies' => $global ? $this->countCompanies() : $this->countCurrentCompany($companyId),
            'active_companies' => $global ? $this->countCompanies('active') : $this->countCurrentCompany($companyId),
            'active_subscriptions' => $global ? $this->countActiveSubscriptions() : $this->countCompanyActiveSubscriptions($companyId),
            'total_users' => $this->countUsers($companyId, $global),
        ];
    }

    public function recentNotifications(?int $companyId, bool $global, int $limit = 5): array
    {
        $where = 'notifications.deleted_at IS NULL';
        $params = [];

        if (!$global) {
            $where .= ' AND notifications.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $sql = sprintf(
            'SELECT notifications.*, users.first_name, users.last_name
             FROM notifications
             LEFT JOIN users ON users.id = notifications.user_id
             WHERE %s
             ORDER BY notifications.created_at DESC
             LIMIT %d',
            $where,
            max(1, min(10, $limit))
        );

        return Database::query($sql, $params)->fetchAll();
    }

    public function companiesByStatus(): array
    {
        return Database::query(
            'SELECT status, COUNT(*) AS total
             FROM companies
             WHERE deleted_at IS NULL
             GROUP BY status
             ORDER BY total DESC'
        )->fetchAll();
    }

    private function countCompanies(?string $status = null): int
    {
        $sql = 'SELECT COUNT(*) FROM companies WHERE deleted_at IS NULL';
        $params = [];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        return (int) Database::query($sql, $params)->fetchColumn();
    }

    private function countCurrentCompany(?int $companyId): int
    {
        if ($companyId === null) {
            return 0;
        }

        return (int) Database::query(
            'SELECT COUNT(*) FROM companies WHERE id = :id AND deleted_at IS NULL',
            ['id' => $companyId]
        )->fetchColumn();
    }

    private function countActiveSubscriptions(): int
    {
        return (int) Database::query(
            "SELECT COUNT(*) FROM subscriptions
             WHERE deleted_at IS NULL
             AND status IN ('trial', 'active')"
        )->fetchColumn();
    }

    private function countCompanyActiveSubscriptions(?int $companyId): int
    {
        if ($companyId === null) {
            return 0;
        }

        return (int) Database::query(
            "SELECT COUNT(*) FROM subscriptions
             WHERE company_id = :company_id
             AND deleted_at IS NULL
             AND status IN ('trial', 'active')",
            ['company_id' => $companyId]
        )->fetchColumn();
    }

    private function countUsers(?int $companyId, bool $global): int
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL';
        $params = [];

        if (!$global) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        return (int) Database::query($sql, $params)->fetchColumn();
    }
}
