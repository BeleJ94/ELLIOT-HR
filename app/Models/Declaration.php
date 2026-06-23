<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Declaration extends Model
{
    protected string $table = 'declarations';
    protected array $fillable = [
        'company_id',
        'payroll_period_id',
        'reference',
        'period_month',
        'period_year',
        'due_date',
        'ipr_total',
        'cnss_employee_total',
        'cnss_employer_total',
        'inpp_employee_total',
        'inpp_employer_total',
        'onem_employee_total',
        'onem_employer_total',
        'salary_withheld_total',
        'employer_charges_total',
        'total_due',
        'currency',
        'payment_status',
        'paid_at',
        'proof_path',
        'proof_name',
        'proof_mime',
        'generated_at',
    ];

    public function dashboard(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'declarations');

        $rows = Database::query(
            "SELECT declarations.*,
                    companies.name AS company_name,
                    payroll_periods.name AS period_name
             FROM declarations
             INNER JOIN companies ON companies.id = declarations.company_id
             INNER JOIN payroll_periods ON payroll_periods.id = declarations.payroll_period_id
             WHERE declarations.deleted_at IS NULL
             {$scope}
             ORDER BY declarations.period_year DESC, declarations.period_month DESC",
            $params
        )->fetchAll();

        return [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'alerts' => $this->alerts($rows),
        ];
    }

    public function payrollPeriods(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'payroll_periods');

        return Database::query(
            "SELECT payroll_periods.*,
                    companies.name AS company_name,
                    COUNT(payslips.id) AS payslips_count,
                    declarations.id AS declaration_id,
                    declarations.payment_status,
                    declarations.due_date,
                    declarations.total_due
             FROM payroll_periods
             INNER JOIN companies ON companies.id = payroll_periods.company_id
             LEFT JOIN payslips ON payslips.payroll_period_id = payroll_periods.id
                AND payslips.deleted_at IS NULL
             LEFT JOIN declarations ON declarations.payroll_period_id = payroll_periods.id
                AND declarations.deleted_at IS NULL
             WHERE payroll_periods.deleted_at IS NULL
             {$scope}
             GROUP BY payroll_periods.id
             ORDER BY payroll_periods.period_year DESC, payroll_periods.period_month DESC",
            $params
        )->fetchAll();
    }

    public function generate(array $period): int
    {
        $summary = $this->summaryForPeriod((int) $period['id']);
        $reference = sprintf('RDC-%d-%04d%02d', (int) $period['company_id'], (int) $period['period_year'], (int) $period['period_month']);
        $dueDate = date('Y-m-d', strtotime(sprintf('%04d-%02d-15 +1 month', (int) $period['period_year'], (int) $period['period_month'])));
        $existing = $this->findByPeriod((int) $period['id']);
        $payload = [
            'company_id' => (int) $period['company_id'],
            'payroll_period_id' => (int) $period['id'],
            'reference' => $reference,
            'period_month' => (int) $period['period_month'],
            'period_year' => (int) $period['period_year'],
            'due_date' => $dueDate,
            'ipr_total' => $summary['ipr'],
            'cnss_employee_total' => $summary['cnss_employee'],
            'cnss_employer_total' => $summary['cnss_employer'],
            'inpp_employee_total' => $summary['inpp_employee'],
            'inpp_employer_total' => $summary['inpp_employer'],
            'onem_employee_total' => $summary['onem_employee'],
            'onem_employer_total' => $summary['onem_employer'],
            'salary_withheld_total' => $summary['salary_withheld'],
            'employer_charges_total' => $summary['employer_charges'],
            'total_due' => $summary['total_due'],
            'currency' => $summary['currency'],
            'payment_status' => $existing['payment_status'] ?? 'pending',
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->update((int) $existing['id'], $payload);
            return (int) $existing['id'];
        }

        return $this->create($payload);
    }

    public function findDetailed(int $id, ?int $companyId): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'declarations');
        $params['id'] = $id;

        $declaration = Database::query(
            "SELECT declarations.*,
                    companies.name AS company_name,
                    companies.legal_name AS company_legal_name,
                    companies.tax_number AS company_tax_number,
                    payroll_periods.name AS period_name,
                    payroll_periods.start_date,
                    payroll_periods.end_date
             FROM declarations
             INNER JOIN companies ON companies.id = declarations.company_id
             INNER JOIN payroll_periods ON payroll_periods.id = declarations.payroll_period_id
             WHERE declarations.id = :id
             AND declarations.deleted_at IS NULL
             {$scope}
             LIMIT 1",
            $params
        )->fetch();

        if (!$declaration) {
            return null;
        }

        $declaration['details'] = $this->details((int) $declaration['payroll_period_id']);
        return $declaration;
    }

    public function updatePayment(int $id, string $status, ?int $companyId): bool
    {
        $declaration = $this->findDetailed($id, $companyId);
        if (!$declaration) {
            return false;
        }

        $status = in_array($status, ['pending', 'paid', 'late'], true) ? $status : 'pending';

        return $this->update($id, [
            'payment_status' => $status,
            'paid_at' => $status === 'paid' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function attachProof(int $id, array $file, ?int $companyId): bool
    {
        $declaration = $this->findDetailed($id, $companyId);
        if (!$declaration || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }

        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Format de preuve non autorise.');
        }

        $dir = BASE_PATH . '/public/uploads/declarations';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $extension = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION) ?: ($mime === 'application/pdf' ? 'pdf' : 'jpg');
        $filename = 'declaration-' . $id . '-' . bin2hex(random_bytes(6)) . '.' . strtolower($extension);
        $target = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Upload impossible.');
        }

        return $this->update($id, [
            'proof_path' => 'uploads/declarations/' . $filename,
            'proof_name' => (string) ($file['name'] ?? $filename),
            'proof_mime' => $mime,
        ]);
    }

    public function findByPeriod(int $periodId): ?array
    {
        $row = Database::query(
            'SELECT * FROM declarations
             WHERE payroll_period_id = :period_id
             AND deleted_at IS NULL
             LIMIT 1',
            ['period_id' => $periodId]
        )->fetch();

        return $row ?: null;
    }

    private function summaryForPeriod(int $periodId): array
    {
        $rows = Database::query(
            "SELECT payslips.currency,
                    SUM(CASE WHEN payslip_lines.code = 'IPR' THEN payslip_lines.amount ELSE 0 END) AS ipr,
                    SUM(CASE WHEN payslip_lines.code = 'CNSS' AND payslip_lines.type = 'contribution' THEN payslip_lines.amount ELSE 0 END) AS cnss_employee,
                    SUM(CASE WHEN payslip_lines.code = 'CNSS_EMP' THEN payslip_lines.amount ELSE 0 END) AS cnss_employer,
                    SUM(CASE WHEN payslip_lines.code = 'INPP' AND payslip_lines.type = 'contribution' THEN payslip_lines.amount ELSE 0 END) AS inpp_employee,
                    SUM(CASE WHEN payslip_lines.code = 'INPP_EMP' THEN payslip_lines.amount ELSE 0 END) AS inpp_employer,
                    SUM(CASE WHEN payslip_lines.code = 'ONEM' AND payslip_lines.type = 'contribution' THEN payslip_lines.amount ELSE 0 END) AS onem_employee,
                    SUM(CASE WHEN payslip_lines.code = 'ONEM_EMP' THEN payslip_lines.amount ELSE 0 END) AS onem_employer
             FROM payslips
             INNER JOIN payslip_lines ON payslip_lines.payslip_id = payslips.id
             WHERE payslips.payroll_period_id = :period_id
             AND payslips.deleted_at IS NULL
             GROUP BY payslips.currency
             ORDER BY payslips.currency ASC
             LIMIT 1",
            ['period_id' => $periodId]
        )->fetch() ?: [];

        $ipr = (float) ($rows['ipr'] ?? 0);
        $cnssEmployee = (float) ($rows['cnss_employee'] ?? 0);
        $cnssEmployer = (float) ($rows['cnss_employer'] ?? 0);
        $inppEmployee = (float) ($rows['inpp_employee'] ?? 0);
        $inppEmployer = (float) ($rows['inpp_employer'] ?? 0);
        $onemEmployee = (float) ($rows['onem_employee'] ?? 0);
        $onemEmployer = (float) ($rows['onem_employer'] ?? 0);
        $salaryWithheld = $ipr + $cnssEmployee + $inppEmployee + $onemEmployee;
        $employerCharges = $cnssEmployer + $inppEmployer + $onemEmployer;

        return [
            'ipr' => round($ipr, 2),
            'cnss_employee' => round($cnssEmployee, 2),
            'cnss_employer' => round($cnssEmployer, 2),
            'inpp_employee' => round($inppEmployee, 2),
            'inpp_employer' => round($inppEmployer, 2),
            'onem_employee' => round($onemEmployee, 2),
            'onem_employer' => round($onemEmployer, 2),
            'salary_withheld' => round($salaryWithheld, 2),
            'employer_charges' => round($employerCharges, 2),
            'total_due' => round($salaryWithheld + $employerCharges, 2),
            'currency' => $rows['currency'] ?? 'USD',
        ];
    }

    private function details(int $periodId): array
    {
        return Database::query(
            "SELECT employees.employee_number,
                    employees.first_name,
                    employees.middle_name,
                    employees.last_name,
                    departments.name AS department_name,
                    payslips.gross_salary,
                    payslips.net_salary,
                    payslips.currency,
                    SUM(CASE WHEN payslip_lines.code = 'IPR' THEN payslip_lines.amount ELSE 0 END) AS ipr,
                    SUM(CASE WHEN payslip_lines.code = 'CNSS' AND payslip_lines.type = 'contribution' THEN payslip_lines.amount ELSE 0 END) AS cnss_employee,
                    SUM(CASE WHEN payslip_lines.code = 'CNSS_EMP' THEN payslip_lines.amount ELSE 0 END) AS cnss_employer,
                    SUM(CASE WHEN payslip_lines.code = 'INPP' AND payslip_lines.type = 'contribution' THEN payslip_lines.amount ELSE 0 END) AS inpp_employee,
                    SUM(CASE WHEN payslip_lines.code = 'INPP_EMP' THEN payslip_lines.amount ELSE 0 END) AS inpp_employer,
                    SUM(CASE WHEN payslip_lines.code = 'ONEM' AND payslip_lines.type = 'contribution' THEN payslip_lines.amount ELSE 0 END) AS onem_employee,
                    SUM(CASE WHEN payslip_lines.code = 'ONEM_EMP' THEN payslip_lines.amount ELSE 0 END) AS onem_employer
             FROM payslips
             INNER JOIN employees ON employees.id = payslips.employee_id
             LEFT JOIN departments ON departments.id = employees.department_id
             LEFT JOIN payslip_lines ON payslip_lines.payslip_id = payslips.id
             WHERE payslips.payroll_period_id = :period_id
             AND payslips.deleted_at IS NULL
             GROUP BY payslips.id
             ORDER BY employees.last_name ASC, employees.first_name ASC",
            ['period_id' => $periodId]
        )->fetchAll();
    }

    private function totals(array $rows): array
    {
        $totals = ['due' => 0.00, 'withheld' => 0.00, 'employer' => 0.00, 'paid' => 0, 'pending' => 0, 'late' => 0];
        foreach ($rows as $row) {
            $totals['due'] += (float) ($row['total_due'] ?? 0);
            $totals['withheld'] += (float) ($row['salary_withheld_total'] ?? 0);
            $totals['employer'] += (float) ($row['employer_charges_total'] ?? 0);
            $status = $row['payment_status'] ?? 'pending';
            if (isset($totals[$status])) {
                $totals[$status]++;
            }
        }

        return $totals;
    }

    private function alerts(array $rows): array
    {
        $alerts = [];
        $today = strtotime(date('Y-m-d'));
        foreach ($rows as $row) {
            if (($row['payment_status'] ?? 'pending') === 'paid') {
                continue;
            }
            $due = strtotime((string) ($row['due_date'] ?? ''));
            if ($due === false) {
                continue;
            }
            $days = (int) floor(($due - $today) / 86400);
            if ($days < 0 || $days <= 7) {
                $alerts[] = [
                    'severity' => $days < 0 ? 'danger' : 'warning',
                    'title' => $days < 0 ? 'Declaration en retard' : 'Echeance proche',
                    'detail' => ($row['reference'] ?? '-') . ' - ' . ($row['company_name'] ?? '-') . ' - ' . date('d/m/Y', $due),
                ];
            }
        }

        return $alerts;
    }

    private function scope(?int $companyId, string $table): array
    {
        if ($companyId === null) {
            return ['', []];
        }

        return [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }
}
