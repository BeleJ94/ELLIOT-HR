<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Payslip extends Model
{
    protected string $table = 'payslips';
    protected array $fillable = [
        'company_id',
        'payroll_period_id',
        'employee_id',
        'gross_salary',
        'total_earnings',
        'total_deductions',
        'net_salary',
        'currency',
        'status',
        'paid_at',
    ];

    public function processPeriod(array $period): array
    {
        Database::beginTransaction();

        try {
            (new PayrollPeriod())->updateStatus((int) $period['id'], 'processing');

            $employees = $this->eligibleEmployees((int) $period['company_id']);
            $processed = 0;

            foreach ($employees as $employee) {
                $this->processEmployee($period, $employee);
                $processed++;
            }

            (new PayrollPeriod())->updateStatus((int) $period['id'], 'closed');
            Database::commit();

            return ['processed' => $processed];
        } catch (\Throwable $exception) {
            Database::rollBack();
            (new PayrollPeriod())->updateStatus((int) $period['id'], 'open');
            throw $exception;
        }
    }

    public function processEmployee(array $period, array $employee): int
    {
        $companyId = (int) $period['company_id'];
        $baseSalary = (float) ($employee['base_salary'] ?? 0);
        $currency = $employee['currency'] ?? 'USD';
        $workDays = $this->businessDays($period['start_date'], $period['end_date']);
        $dailyRate = $workDays > 0 ? $baseSalary / $workDays : 0;
        $hourlyRate = $dailyRate / 8;
        $unpaidAbsenceDays = $this->unpaidAbsenceDays((int) $employee['id'], $period);
        $absenceDeduction = round($unpaidAbsenceDays * $dailyRate, 2);
        $overtimeMinutes = $this->overtimeMinutes((int) $employee['id'], $period);
        $overtimeAmount = round(($overtimeMinutes / 60) * $hourlyRate * 1.3, 2);

        $items = (new PayrollItem())->allForCompany($companyId);
        $tax = new TaxSetting();
        $social = new SocialContributionSetting();
        $lines = [];
        $taxable = $baseSalary;
        $gross = $baseSalary;
        $deductions = 0.00;
        $employerContributions = [];

        $lines[] = $this->line(null, 'BASE', 'Salaire de base', 'earning', $baseSalary, 0, $baseSalary, 1);

        foreach ($items as $item) {
            if (in_array($item['code'], ['BASE', 'IPR', 'CNSS'], true)) {
                continue;
            }

            $amount = $this->itemAmount($item, $baseSalary);
            if ($amount <= 0) {
                continue;
            }

            if ($item['type'] === 'earning') {
                $gross += $amount;
                if (!empty($item['taxable'])) {
                    $taxable += $amount;
                }
            } elseif ($item['type'] === 'deduction') {
                $deductions += $amount;
            }

            $lines[] = $this->line((int) $item['id'], $item['code'], $item['name'], $item['type'], $baseSalary, (float) $item['default_rate'], $amount, (int) $item['taxable']);
        }

        if ($overtimeAmount > 0) {
            $gross += $overtimeAmount;
            $taxable += $overtimeAmount;
            $lines[] = $this->line(null, 'OVERTIME', 'Heures supplementaires', 'earning', $hourlyRate, 1.3, $overtimeAmount, 1);
        }

        if ($absenceDeduction > 0) {
            $deductions += $absenceDeduction;
            $lines[] = $this->line(null, 'ABSENCE', 'Absences deductibles', 'deduction', $dailyRate, 0, $absenceDeduction, 0);
        }

        foreach ($social->activeForCompany($companyId) as $setting) {
            $base = $setting['ceiling_amount'] !== null ? min($gross, (float) $setting['ceiling_amount']) : $gross;
            $employeeAmount = round($base * ((float) $setting['employee_rate'] / 100), 2);
            $employerAmount = round($base * ((float) $setting['employer_rate'] / 100), 2);

            if ($employeeAmount > 0) {
                $deductions += $employeeAmount;
                $lines[] = $this->line(null, $setting['contribution_code'], $setting['name'] . ' salariale', 'contribution', $base, (float) $setting['employee_rate'], $employeeAmount, 0);
            }

            if ($employerAmount > 0) {
                $employerContributions[] = $this->line(null, $setting['contribution_code'] . '_EMP', $setting['name'] . ' patronale', 'employer_contribution', $base, (float) $setting['employer_rate'], $employerAmount, 0);
            }
        }

        $ipr = $tax->calculate('IPR', $companyId, max(0, $taxable));
        if ($ipr > 0) {
            $deductions += $ipr;
            $lines[] = $this->line(null, 'IPR', 'Impot professionnel sur remuneration', 'tax', $taxable, 0, $ipr, 0);
        }

        foreach ($employerContributions as $line) {
            $lines[] = $line;
        }

        $net = max(0, round($gross - $deductions, 2));
        $existing = $this->findForPeriodEmployee((int) $period['id'], (int) $employee['id']);
        $payload = [
            'company_id' => $companyId,
            'payroll_period_id' => (int) $period['id'],
            'employee_id' => (int) $employee['id'],
            'gross_salary' => round($gross, 2),
            'total_earnings' => round($gross, 2),
            'total_deductions' => round($deductions, 2),
            'net_salary' => $net,
            'currency' => $currency,
            'status' => 'draft',
        ];

        if ($existing) {
            $this->update((int) $existing['id'], $payload);
            $payslipId = (int) $existing['id'];
            Database::query('DELETE FROM payslip_lines WHERE payslip_id = :id', ['id' => $payslipId]);
        } else {
            $payslipId = $this->create($payload);
        }

        foreach ($lines as $line) {
            $line['payslip_id'] = $payslipId;
            Database::query(
                'INSERT INTO payslip_lines
                    (payslip_id, payroll_item_id, code, name, type, base_amount, rate, amount, taxable, created_at)
                 VALUES
                    (:payslip_id, :payroll_item_id, :code, :name, :type, :base_amount, :rate, :amount, :taxable, NOW())',
                $line
            );
        }

        return $payslipId;
    }

    public function anomalies(array $period): array
    {
        $companyId = (int) $period['company_id'];
        $anomalies = [];

        $withoutContract = Database::query(
            "SELECT employees.employee_number, employees.first_name, employees.last_name
             FROM employees
             LEFT JOIN contracts ON contracts.id = (
                SELECT c.id FROM contracts c
                WHERE c.employee_id = employees.id
                AND c.company_id = employees.company_id
                AND c.deleted_at IS NULL
                AND c.status = 'active'
                ORDER BY c.start_date DESC, c.id DESC
                LIMIT 1
             )
             WHERE employees.company_id = :company_id
             AND employees.deleted_at IS NULL
             AND employees.employment_status IN ('active', 'on_leave')
             AND contracts.id IS NULL",
            ['company_id' => $companyId]
        )->fetchAll();

        foreach ($withoutContract as $employee) {
            $anomalies[] = [
                'severity' => 'danger',
                'title' => 'Contrat actif manquant',
                'detail' => trim(($employee['last_name'] ?? '') . ' ' . ($employee['first_name'] ?? '')) . ' - ' . ($employee['employee_number'] ?? '-'),
            ];
        }

        $zeroSalary = Database::query(
            "SELECT employees.employee_number, employees.first_name, employees.last_name
             FROM employees
             INNER JOIN contracts ON contracts.id = (
                SELECT c.id FROM contracts c
                WHERE c.employee_id = employees.id
                AND c.company_id = employees.company_id
                AND c.deleted_at IS NULL
                AND c.status = 'active'
                ORDER BY c.start_date DESC, c.id DESC
                LIMIT 1
             )
             WHERE employees.company_id = :company_id
             AND employees.deleted_at IS NULL
             AND employees.employment_status IN ('active', 'on_leave')
             AND contracts.base_salary <= 0",
            ['company_id' => $companyId]
        )->fetchAll();

        foreach ($zeroSalary as $employee) {
            $anomalies[] = [
                'severity' => 'danger',
                'title' => 'Salaire de base manquant',
                'detail' => trim(($employee['last_name'] ?? '') . ' ' . ($employee['first_name'] ?? '')) . ' - ' . ($employee['employee_number'] ?? '-'),
            ];
        }

        $iprActive = (int) Database::query(
            "SELECT COUNT(*) FROM tax_settings
             WHERE company_id = :company_id
             AND tax_code = 'IPR'
             AND is_active = 1
             AND deleted_at IS NULL",
            ['company_id' => $companyId]
        )->fetchColumn();

        if ($iprActive === 0) {
            $anomalies[] = ['severity' => 'warning', 'title' => 'IPR non configure', 'detail' => 'Aucune tranche IPR active pour cette entreprise.'];
        }

        $zeroContributions = Database::query(
            "SELECT contribution_code FROM social_contribution_settings
             WHERE company_id = :company_id
             AND is_active = 1
             AND deleted_at IS NULL
             AND employee_rate = 0
             AND employer_rate = 0
             ORDER BY contribution_code ASC",
            ['company_id' => $companyId]
        )->fetchAll();

        foreach ($zeroContributions as $setting) {
            $anomalies[] = [
                'severity' => 'warning',
                'title' => 'Taux a zero',
                'detail' => ($setting['contribution_code'] ?? '-') . ' est actif avec taux salarial et patronal a zero.',
            ];
        }

        $pendingLeaves = (int) Database::query(
            "SELECT COUNT(*) FROM leave_requests
             WHERE company_id = :company_id
             AND status = 'pending'
             AND deleted_at IS NULL
             AND start_date <= :end_date
             AND end_date >= :start_date",
            [
                'company_id' => $companyId,
                'start_date' => $period['start_date'],
                'end_date' => $period['end_date'],
            ]
        )->fetchColumn();

        if ($pendingLeaves > 0) {
            $anomalies[] = ['severity' => 'warning', 'title' => 'Conges en attente', 'detail' => $pendingLeaves . ' demande(s) couvrent la periode de paie.'];
        }

        $journalCount = (int) Database::query(
            "SELECT COUNT(*) FROM payslips
             WHERE payroll_period_id = :period_id
             AND deleted_at IS NULL",
            ['period_id' => (int) $period['id']]
        )->fetchColumn();

        if ($journalCount === 0) {
            $anomalies[] = ['severity' => 'info', 'title' => 'Paie non calculee', 'detail' => 'Lancez le calcul pour generer les bulletins.'];
        }

        return $anomalies;
    }

    public function journal(int $periodId, ?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'payslips');
        $params['period_id'] = $periodId;

        return Database::query(
            "SELECT payslips.*,
                    employees.employee_number,
                    employees.first_name,
                    employees.middle_name,
                    employees.last_name,
                    departments.name AS department_name,
                    positions.title AS position_title
             FROM payslips
             INNER JOIN employees ON employees.id = payslips.employee_id
             LEFT JOIN departments ON departments.id = employees.department_id
             LEFT JOIN positions ON positions.id = employees.position_id
             WHERE payslips.deleted_at IS NULL
             AND payslips.payroll_period_id = :period_id
             {$scope}
             ORDER BY employees.last_name ASC, employees.first_name ASC",
            $params
        )->fetchAll();
    }

    public function historyByEmployee(int $employeeId, ?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'payslips');
        $params['employee_id'] = $employeeId;

        return Database::query(
            "SELECT payslips.*, payroll_periods.name AS period_name, payroll_periods.period_month, payroll_periods.period_year
             FROM payslips
             INNER JOIN payroll_periods ON payroll_periods.id = payslips.payroll_period_id
             WHERE payslips.deleted_at IS NULL
             AND payslips.employee_id = :employee_id
             {$scope}
             ORDER BY payroll_periods.period_year DESC, payroll_periods.period_month DESC",
            $params
        )->fetchAll();
    }

    public function findDetailed(int $id, ?int $companyId): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'payslips');
        $params['id'] = $id;

        $payslip = Database::query(
            "SELECT payslips.*,
                    payroll_periods.name AS period_name,
                    payroll_periods.start_date,
                    payroll_periods.end_date,
                    companies.name AS company_name,
                    companies.legal_name AS company_legal_name,
                    companies.registration_number AS company_registration_number,
                    companies.national_id AS company_national_id,
                    companies.tax_number AS company_tax_number,
                    companies.email AS company_email,
                    companies.phone AS company_phone,
                    companies.address AS company_address,
                    companies.city AS company_city,
                    companies.province AS company_province,
                    companies.country AS company_country,
                    employees.employee_number,
                    employees.first_name,
                    employees.middle_name,
                    employees.last_name,
                    employees.email AS employee_email,
                    employees.phone AS employee_phone,
                    employees.hire_date,
                    departments.name AS department_name,
                    positions.title AS position_title
             FROM payslips
             INNER JOIN payroll_periods ON payroll_periods.id = payslips.payroll_period_id
             INNER JOIN companies ON companies.id = payslips.company_id
             INNER JOIN employees ON employees.id = payslips.employee_id
             LEFT JOIN departments ON departments.id = employees.department_id
             LEFT JOIN positions ON positions.id = employees.position_id
             WHERE payslips.id = :id
             AND payslips.deleted_at IS NULL
             {$scope}
             LIMIT 1",
            $params
        )->fetch();

        if (!$payslip) {
            return null;
        }

        $payslip['lines'] = $this->lines((int) $payslip['id']);

        return $payslip;
    }

    public function lines(int $payslipId): array
    {
        return Database::query(
            'SELECT * FROM payslip_lines
             WHERE payslip_id = :id
             ORDER BY FIELD(type, "earning", "deduction", "contribution", "tax", "employer_contribution"), id ASC',
            ['id' => $payslipId]
        )->fetchAll();
    }

    private function eligibleEmployees(int $companyId): array
    {
        return Database::query(
            "SELECT employees.*,
                    contracts.base_salary,
                    contracts.currency
             FROM employees
             INNER JOIN contracts ON contracts.id = (
                SELECT c.id FROM contracts c
                WHERE c.employee_id = employees.id
                AND c.company_id = employees.company_id
                AND c.deleted_at IS NULL
                AND c.status = 'active'
                ORDER BY c.start_date DESC, c.id DESC
                LIMIT 1
             )
             WHERE employees.company_id = :company_id
             AND employees.deleted_at IS NULL
             AND employees.employment_status IN ('active', 'on_leave')
             ORDER BY employees.last_name ASC, employees.first_name ASC",
            ['company_id' => $companyId]
        )->fetchAll();
    }

    private function findForPeriodEmployee(int $periodId, int $employeeId): ?array
    {
        $row = Database::query(
            'SELECT * FROM payslips
             WHERE payroll_period_id = :period_id
             AND employee_id = :employee_id
             AND deleted_at IS NULL
             LIMIT 1',
            ['period_id' => $periodId, 'employee_id' => $employeeId]
        )->fetch();

        return $row ?: null;
    }

    private function itemAmount(array $item, float $baseSalary): float
    {
        if ($item['calculation_type'] === 'percentage') {
            return round($baseSalary * ((float) $item['default_rate'] / 100), 2);
        }

        return round((float) $item['default_amount'], 2);
    }

    private function unpaidAbsenceDays(int $employeeId, array $period): float
    {
        $attendanceDays = (float) Database::query(
            "SELECT COUNT(*) FROM attendance
             WHERE employee_id = :employee_id
             AND attendance_date BETWEEN :start_date AND :end_date
             AND status = 'absent'
             AND deleted_at IS NULL",
            ['employee_id' => $employeeId, 'start_date' => $period['start_date'], 'end_date' => $period['end_date']]
        )->fetchColumn();

        $leaveDays = (float) Database::query(
            "SELECT COALESCE(SUM(leave_requests.total_days), 0)
             FROM leave_requests
             INNER JOIN leave_types ON leave_types.id = leave_requests.leave_type_id
             WHERE leave_requests.employee_id = :employee_id
             AND leave_requests.status = 'approved'
             AND leave_types.paid = 0
             AND leave_requests.deleted_at IS NULL
             AND leave_requests.start_date <= :end_date
             AND leave_requests.end_date >= :start_date",
            ['employee_id' => $employeeId, 'start_date' => $period['start_date'], 'end_date' => $period['end_date']]
        )->fetchColumn();

        return $attendanceDays + $leaveDays;
    }

    private function overtimeMinutes(int $employeeId, array $period): int
    {
        $rows = Database::query(
            "SELECT check_out FROM attendance
             WHERE employee_id = :employee_id
             AND attendance_date BETWEEN :start_date AND :end_date
             AND check_out IS NOT NULL
             AND deleted_at IS NULL",
            ['employee_id' => $employeeId, 'start_date' => $period['start_date'], 'end_date' => $period['end_date']]
        )->fetchAll();

        $minutes = 0;
        foreach ($rows as $row) {
            if (($row['check_out'] ?? '') > '17:00:00') {
                $minutes += max(0, (int) floor((strtotime('2000-01-01 ' . $row['check_out']) - strtotime('2000-01-01 17:00:00')) / 60));
            }
        }

        return $minutes;
    }

    private function businessDays(string $start, string $end): int
    {
        $days = 0;
        $cursor = strtotime($start);
        $last = strtotime($end);

        while ($cursor <= $last) {
            if ((int) date('N', $cursor) <= 5) {
                $days++;
            }
            $cursor = strtotime('+1 day', $cursor);
        }

        return max(1, $days);
    }

    private function line(?int $itemId, string $code, string $name, string $type, float $base, float $rate, float $amount, int $taxable): array
    {
        return [
            'payroll_item_id' => $itemId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'base_amount' => round($base, 2),
            'rate' => round($rate, 4),
            'amount' => round($amount, 2),
            'taxable' => $taxable,
        ];
    }

    private function scope(?int $companyId, string $table): array
    {
        if ($companyId === null) {
            return ['', []];
        }

        return [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }
}
