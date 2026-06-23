<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = [
        'company_id',
        'role_id',
        'employee_id',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'status',
        'last_login_at',
        'remember_token',
    ];

    public function findByEmail(string $email): ?array
    {
        $statement = Database::query(
            'SELECT users.*, roles.name AS role_name, roles.slug AS role_slug, companies.name AS company_name
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             LEFT JOIN companies ON companies.id = users.company_id
             WHERE users.email = :email
             AND users.deleted_at IS NULL
             LIMIT 1',
            ['email' => $email]
        );

        $user = $statement->fetch();

        return $user ?: null;
    }

    public function markLogin(int $id): void
    {
        Database::query(
            'UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id',
            ['id' => $id]
        );
    }

    public function dashboard(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'users');

        $rows = Database::query(
            "SELECT users.id,
                    users.company_id,
                    users.role_id,
                    users.employee_id,
                    users.first_name,
                    users.last_name,
                    users.email,
                    users.phone,
                    users.status,
                    users.last_login_at,
                    users.created_at,
                    roles.name AS role_name,
                    roles.slug AS role_slug,
                    companies.name AS company_name,
                    employees.employee_number,
                    departments.name AS department_name,
                    positions.title AS position_title
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id AND roles.deleted_at IS NULL
             LEFT JOIN companies ON companies.id = users.company_id AND companies.deleted_at IS NULL
             LEFT JOIN employees ON employees.id = users.employee_id AND employees.deleted_at IS NULL
             LEFT JOIN departments ON departments.id = employees.department_id
             LEFT JOIN positions ON positions.id = employees.position_id
             WHERE users.deleted_at IS NULL
             {$scope}
             ORDER BY users.created_at DESC, users.id DESC",
            $params
        )->fetchAll();

        $stats = ['total' => count($rows), 'active' => 0, 'blocked' => 0, 'inactive' => 0, 'recent' => 0];
        $recentThreshold = strtotime('-30 days');
        foreach ($rows as $row) {
            $status = $row['status'] ?? 'inactive';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
            if (!empty($row['last_login_at']) && strtotime($row['last_login_at']) >= $recentThreshold) {
                $stats['recent']++;
            }
        }

        return [
            'rows' => $rows,
            'stats' => $stats,
            'activity' => $this->recentActivity($companyId),
        ];
    }

    public function formOptions(?int $companyId, bool $isSuperAdmin): array
    {
        $companyScope = $companyId === null ? '' : ' AND companies.id = :company_id';
        $companyParams = $companyId === null ? [] : ['company_id' => $companyId];

        $rolesSql = "SELECT roles.id, roles.company_id, roles.name, roles.slug, roles.description,
                            GROUP_CONCAT(DISTINCT permissions.name ORDER BY permissions.module, permissions.name SEPARATOR '||') AS permission_names
                     FROM roles
                     LEFT JOIN role_permissions ON role_permissions.role_id = roles.id AND role_permissions.deleted_at IS NULL
                     LEFT JOIN permissions ON permissions.id = role_permissions.permission_id AND permissions.deleted_at IS NULL
                     WHERE roles.deleted_at IS NULL";
        $roleParams = [];
        if ($isSuperAdmin) {
            $rolesSql .= ' GROUP BY roles.id ORDER BY roles.company_id IS NULL DESC, roles.name ASC';
        } else {
            $rolesSql .= " AND roles.company_id = :role_company_id AND roles.slug <> 'super-admin' GROUP BY roles.id ORDER BY roles.name ASC";
            $roleParams['role_company_id'] = $companyId;
        }

        $employeeSql = "SELECT employees.id,
                               employees.company_id,
                               employees.employee_number,
                               employees.first_name,
                               employees.middle_name,
                               employees.last_name,
                               departments.name AS department_name
                        FROM employees
                        LEFT JOIN departments ON departments.id = employees.department_id
                        WHERE employees.deleted_at IS NULL
                        AND employees.employment_status IN ('active', 'on_leave')";
        $employeeParams = [];
        if ($companyId !== null) {
            $employeeSql .= ' AND employees.company_id = :employee_company_id';
            $employeeParams['employee_company_id'] = $companyId;
        }
        $employeeSql .= ' ORDER BY employees.last_name ASC, employees.first_name ASC';

        return [
            'companies' => Database::query(
                "SELECT companies.id, companies.name
                 FROM companies
                 WHERE companies.deleted_at IS NULL {$companyScope}
                 ORDER BY companies.name ASC",
                $companyParams
            )->fetchAll(),
            'roles' => Database::query($rolesSql, $roleParams)->fetchAll(),
            'employees' => Database::query($employeeSql, $employeeParams)->fetchAll(),
        ];
    }

    public function findScoped(int $id, ?int $companyId): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'users');
        $params['id'] = $id;
        $row = Database::query(
            "SELECT users.*, roles.slug AS role_slug, roles.name AS role_name
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             WHERE users.id = :id AND users.deleted_at IS NULL {$scope}
             LIMIT 1",
            $params
        )->fetch();

        return $row ?: null;
    }

    public function saveUser(array $data, ?int $id = null): int
    {
        $payload = [
            'company_id' => $this->nullableInt($data['company_id'] ?? null),
            'role_id' => $this->nullableInt($data['role_id'] ?? null),
            'employee_id' => $this->nullableInt($data['employee_id'] ?? null),
            'first_name' => trim($data['first_name'] ?? ''),
            'last_name' => trim($data['last_name'] ?? ''),
            'email' => strtolower(trim($data['email'] ?? '')),
            'phone' => trim($data['phone'] ?? '') ?: null,
            'status' => $this->normalizeStatus($data['status'] ?? 'active'),
        ];

        Database::beginTransaction();
        try {
            $previousEmployeeId = null;
            if ($id !== null) {
                $previousEmployeeId = Database::query(
                    'SELECT employee_id FROM users WHERE id = :id LIMIT 1',
                    ['id' => $id]
                )->fetchColumn();
            }

            if ($id === null) {
                $payload['password'] = password_hash((string) ($data['password'] ?? ''), PASSWORD_DEFAULT);
                $id = $this->create($payload);
            } else {
                $this->update($id, $payload);
            }

            if ($previousEmployeeId !== false && $previousEmployeeId !== null
                && (int) $previousEmployeeId !== (int) ($payload['employee_id'] ?? 0)) {
                Database::query(
                    'UPDATE employees SET user_id = NULL, updated_at = NOW()
                     WHERE id = :employee_id AND user_id = :user_id',
                    ['employee_id' => (int) $previousEmployeeId, 'user_id' => $id]
                );
            }
            if ($payload['employee_id'] !== null) {
                Database::query(
                    'UPDATE employees SET user_id = :user_id, updated_at = NOW()
                     WHERE id = :employee_id AND deleted_at IS NULL',
                    ['user_id' => $id, 'employee_id' => $payload['employee_id']]
                );
            }

            Database::commit();
            return $id;
        } catch (\Throwable $exception) {
            Database::rollBack();
            throw $exception;
        }
    }

    public function updateStatusScoped(int $id, string $status, ?int $companyId): bool
    {
        [$scope, $params] = $this->scope($companyId, 'users');
        $params['id'] = $id;
        $params['status'] = $this->normalizeStatus($status);

        return Database::query(
            "UPDATE users SET status = :status, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL {$scope}",
            $params
        )->rowCount() > 0;
    }

    public function updatePasswordScoped(int $id, string $password, ?int $companyId): bool
    {
        [$scope, $params] = $this->scope($companyId, 'users');
        $params['id'] = $id;
        $params['password'] = password_hash($password, PASSWORD_DEFAULT);

        return Database::query(
            "UPDATE users
             SET password = :password, remember_token = NULL, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL {$scope}",
            $params
        )->rowCount() > 0;
    }

    public function softDeleteScoped(int $id, ?int $companyId): bool
    {
        [$scope, $params] = $this->scope($companyId, 'users');
        $params['id'] = $id;

        $deleted = Database::query(
            "UPDATE users SET status = 'inactive', deleted_at = NOW(), updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL {$scope}",
            $params
        )->rowCount() > 0;
        if ($deleted) {
            Database::query(
                'UPDATE employees SET user_id = NULL, updated_at = NOW() WHERE user_id = :user_id',
                ['user_id' => $id]
            );
        }

        return $deleted;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email AND deleted_at IS NULL';
        $params = ['email' => strtolower(trim($email))];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }

        return (int) Database::query($sql, $params)->fetchColumn() > 0;
    }

    public function roleAllowed(int $roleId, int $companyId, bool $isSuperAdmin): bool
    {
        $role = Database::query(
            'SELECT id, company_id, slug FROM roles WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $roleId]
        )->fetch();

        if (!$role) {
            return false;
        }
        if ($isSuperAdmin) {
            return $role['company_id'] === null || (int) $role['company_id'] === $companyId;
        }

        return $role['slug'] !== 'super-admin' && (int) $role['company_id'] === $companyId;
    }

    public function employeeAllowed(?int $employeeId, int $companyId, ?int $userId = null): bool
    {
        if ($employeeId === null) {
            return true;
        }
        $sql = 'SELECT COUNT(*) FROM employees
                WHERE id = :employee_id AND company_id = :company_id AND deleted_at IS NULL';
        $params = ['employee_id' => $employeeId, 'company_id' => $companyId];
        if ($userId !== null) {
            $sql .= ' AND (employees.user_id IS NULL OR employees.user_id = :user_id)';
            $params['user_id'] = $userId;
        } else {
            $sql .= ' AND employees.user_id IS NULL';
        }
        $sql .= ' AND NOT EXISTS (
                    SELECT 1 FROM users linked_user
                    WHERE linked_user.employee_id = employees.id
                    AND linked_user.deleted_at IS NULL';
        if ($userId !== null) {
            $sql .= ' AND linked_user.id <> :user_id';
        }
        $sql .= ')';

        return (int) Database::query($sql, $params)->fetchColumn() > 0;
    }

    private function recentActivity(?int $companyId, int $limit = 10): array
    {
        $scope = '';
        $params = [];
        if ($companyId !== null) {
            $scope = ' AND audit_logs.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $statement = Database::connection()->prepare(
            "SELECT audit_logs.*, users.first_name, users.last_name, users.email
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             WHERE audit_logs.deleted_at IS NULL
             AND audit_logs.action IN (
                'login_success', 'login_failed', 'logout',
                'user_created', 'user_updated', 'user_status_changed',
                'user_password_reset', 'user_deleted'
             )
             {$scope}
             ORDER BY audit_logs.created_at DESC
             LIMIT :limit"
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, \PDO::PARAM_INT);
        }
        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    private function scope(?int $companyId, string $table): array
    {
        return $companyId === null
            ? ['', []]
            : [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }

    private function nullableInt($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['active', 'inactive', 'blocked'], true) ? $status : 'active';
    }
}
