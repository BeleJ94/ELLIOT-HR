<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class PayrollPeriod extends Model
{
    protected string $table = 'payroll_periods';
    protected array $fillable = [
        'company_id',
        'name',
        'period_month',
        'period_year',
        'start_date',
        'end_date',
        'status',
    ];

    public function allWithStats(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'payroll_periods');

        return Database::query(
            "SELECT payroll_periods.*,
                    companies.name AS company_name,
                    COUNT(payslips.id) AS payslips_count,
                    COALESCE(SUM(payslips.gross_salary), 0) AS gross_total,
                    COALESCE(SUM(payslips.total_deductions), 0) AS deductions_total,
                    COALESCE(SUM(payslips.net_salary), 0) AS net_total
             FROM payroll_periods
             INNER JOIN companies ON companies.id = payroll_periods.company_id
             LEFT JOIN payslips ON payslips.payroll_period_id = payroll_periods.id
                AND payslips.deleted_at IS NULL
             WHERE payroll_periods.deleted_at IS NULL
             {$scope}
             GROUP BY payroll_periods.id
             ORDER BY payroll_periods.period_year DESC, payroll_periods.period_month DESC",
            $params
        )->fetchAll();
    }

    public function findDetailed(int $id, ?int $companyId): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'payroll_periods');
        $params['id'] = $id;

        $period = Database::query(
            "SELECT payroll_periods.*, companies.name AS company_name
             FROM payroll_periods
             INNER JOIN companies ON companies.id = payroll_periods.company_id
             WHERE payroll_periods.id = :id
             AND payroll_periods.deleted_at IS NULL
             {$scope}
             LIMIT 1",
            $params
        )->fetch();

        return $period ?: null;
    }

    public function savePeriod(array $data): int
    {
        $year = (int) ($data['period_year'] ?? date('Y'));
        $month = (int) ($data['period_month'] ?? date('n'));
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        $name = trim($data['name'] ?? '') ?: 'Paie ' . date('m/Y', strtotime($start));

        return $this->create([
            'company_id' => (int) ($data['company_id'] ?? 0),
            'name' => $name,
            'period_month' => $month,
            'period_year' => $year,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'open',
        ]);
    }

    public function updateStatus(int $id, string $status): void
    {
        if (!in_array($status, ['open', 'processing', 'closed', 'paid'], true)) {
            return;
        }

        $this->update($id, ['status' => $status]);
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
}
