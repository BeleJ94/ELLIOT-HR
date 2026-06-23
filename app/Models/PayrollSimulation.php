<?php

namespace App\Models;

class PayrollSimulation
{
    public function simulateNetToBase(int $companyId, float $targetNet, array $options = []): array
    {
        $targetNet = round(max(0, $targetNet), 2);
        $low = 0.00;
        $high = max(100.00, $targetNet * 2);
        $best = $this->calculateFromBase($companyId, $high, $options);

        for ($i = 0; $i < 40 && $best['net_salary'] < $targetNet; $i++) {
            $low = $high;
            $high *= 2;
            $best = $this->calculateFromBase($companyId, $high, $options);
            if ($high > 1000000000) {
                break;
            }
        }

        for ($i = 0; $i < 80; $i++) {
            $mid = ($low + $high) / 2;
            $current = $this->calculateFromBase($companyId, $mid, $options);

            if (abs($current['net_salary'] - $targetNet) < abs($best['net_salary'] - $targetNet)) {
                $best = $current;
            }

            if ($current['net_salary'] < $targetNet) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        $best['target_net'] = $targetNet;
        $best['difference'] = round($best['net_salary'] - $targetNet, 2);
        $best['precision'] = abs($best['difference']) <= 0.02 ? 'exact' : 'approximate';

        return $best;
    }

    public function calculateFromBase(int $companyId, float $baseSalary, array $options = []): array
    {
        $baseSalary = round(max(0, $baseSalary), 2);
        $taxableExtra = round(max(0, (float) ($options['taxable_earnings'] ?? 0)), 2);
        $nonTaxableExtra = round(max(0, (float) ($options['non_taxable_earnings'] ?? 0)), 2);
        $fixedDeductions = round(max(0, (float) ($options['deductions'] ?? 0)), 2);

        $lines = [];
        $gross = $baseSalary;
        $taxable = $baseSalary;
        $deductions = 0.00;
        $employerCharges = 0.00;

        $lines[] = $this->line('BASE', 'Salaire de base', 'earning', $baseSalary, 0, $baseSalary, true);

        foreach ((new PayrollItem())->allForCompany($companyId) as $item) {
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

            $lines[] = $this->line(
                (string) $item['code'],
                (string) $item['name'],
                (string) $item['type'],
                $baseSalary,
                (float) $item['default_rate'],
                $amount,
                !empty($item['taxable'])
            );
        }

        if ($taxableExtra > 0) {
            $gross += $taxableExtra;
            $taxable += $taxableExtra;
            $lines[] = $this->line('SIM_TAXABLE', 'Avantages imposables saisis', 'earning', $taxableExtra, 0, $taxableExtra, true);
        }

        if ($nonTaxableExtra > 0) {
            $gross += $nonTaxableExtra;
            $lines[] = $this->line('SIM_NONTAX', 'Avantages non imposables saisis', 'earning', $nonTaxableExtra, 0, $nonTaxableExtra, false);
        }

        if ($fixedDeductions > 0) {
            $deductions += $fixedDeductions;
            $lines[] = $this->line('SIM_DED', 'Retenues fixes saisies', 'deduction', $fixedDeductions, 0, $fixedDeductions, false);
        }

        foreach ((new SocialContributionSetting())->activeForCompany($companyId) as $setting) {
            $base = $setting['ceiling_amount'] !== null ? min($gross, (float) $setting['ceiling_amount']) : $gross;
            $employeeAmount = round($base * ((float) $setting['employee_rate'] / 100), 2);
            $employerAmount = round($base * ((float) $setting['employer_rate'] / 100), 2);

            if ($employeeAmount > 0) {
                $deductions += $employeeAmount;
                $lines[] = $this->line(
                    (string) $setting['contribution_code'],
                    (string) $setting['name'] . ' salariale',
                    'contribution',
                    $base,
                    (float) $setting['employee_rate'],
                    $employeeAmount,
                    false
                );
            }

            if ($employerAmount > 0) {
                $employerCharges += $employerAmount;
                $lines[] = $this->line(
                    (string) $setting['contribution_code'] . '_EMP',
                    (string) $setting['name'] . ' patronale',
                    'employer_contribution',
                    $base,
                    (float) $setting['employer_rate'],
                    $employerAmount,
                    false
                );
            }
        }

        $ipr = (new TaxSetting())->calculate('IPR', $companyId, max(0, $taxable));
        if ($ipr > 0) {
            $deductions += $ipr;
            $lines[] = $this->line('IPR', 'Impot professionnel sur remuneration', 'tax', $taxable, 0, $ipr, false);
        }

        $net = max(0, round($gross - $deductions, 2));

        return [
            'base_salary' => round($baseSalary, 2),
            'gross_salary' => round($gross, 2),
            'taxable_salary' => round($taxable, 2),
            'total_deductions' => round($deductions, 2),
            'net_salary' => $net,
            'employer_charges' => round($employerCharges, 2),
            'total_employer_cost' => round($gross + $employerCharges, 2),
            'lines' => $lines,
        ];
    }

    private function itemAmount(array $item, float $baseSalary): float
    {
        if (($item['calculation_type'] ?? 'fixed') === 'percentage') {
            return round($baseSalary * ((float) $item['default_rate'] / 100), 2);
        }

        return round((float) ($item['default_amount'] ?? 0), 2);
    }

    private function line(string $code, string $name, string $type, float $base, float $rate, float $amount, bool $taxable): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'base_amount' => round($base, 2),
            'rate' => round($rate, 4),
            'amount' => round($amount, 2),
            'taxable' => $taxable ? 1 : 0,
        ];
    }
}
