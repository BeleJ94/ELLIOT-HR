<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use DateTimeImmutable;
use Throwable;

class MedicalSupport extends Model
{
    protected string $table = 'medical_requests';

    public const CARE_TYPES = [
        'consultation' => 'Consultation',
        'pharmacy' => 'Pharmacie',
        'laboratory' => 'Laboratoire',
        'hospitalization' => 'Hospitalisation',
        'maternity' => 'Maternite',
        'dental' => 'Dentaire',
        'optical' => 'Optique',
        'emergency' => 'Urgence',
        'other' => 'Autre soin',
    ];

    public const RELATIONSHIPS = [
        'spouse' => 'Conjoint(e)',
        'child' => 'Enfant',
        'father' => 'Pere',
        'mother' => 'Mere',
        'other' => 'Autre ayant droit',
    ];

    public function dashboard(?int $companyId, ?int $employeeId = null): array
    {
        [$scope, $params] = $this->scope($companyId, 'mr');
        if ($employeeId !== null) {
            $scope .= ' AND mr.employee_id = :employee_id';
            $params['employee_id'] = $employeeId;
        }

        return [
            'requests' => (int) Database::query("SELECT COUNT(*) FROM medical_requests mr WHERE mr.deleted_at IS NULL {$scope}", $params)->fetchColumn(),
            'pending' => (int) Database::query("SELECT COUNT(*) FROM medical_requests mr WHERE mr.deleted_at IS NULL AND mr.status = 'submitted' {$scope}", $params)->fetchColumn(),
            'approved' => (int) Database::query("SELECT COUNT(*) FROM medical_requests mr WHERE mr.deleted_at IS NULL AND mr.status IN ('approved', 'voucher_issued', 'invoiced', 'validated') {$scope}", $params)->fetchColumn(),
            'covered' => (float) Database::query("SELECT COALESCE(SUM(mr.covered_amount), 0) FROM medical_requests mr WHERE mr.deleted_at IS NULL {$scope}", $params)->fetchColumn(),
        ];
    }

    public function requests(?int $companyId, array $filters = [], ?int $employeeId = null): array
    {
        [$scope, $params] = $this->scope($companyId, 'mr');
        $where = ["mr.deleted_at IS NULL {$scope}"];

        if ($employeeId !== null) {
            $where[] = 'mr.employee_id = :self_employee_id';
            $params['self_employee_id'] = $employeeId;
        }
        if (!empty($filters['status'])) {
            $where[] = 'mr.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['care_type'])) {
            $where[] = 'mr.care_type = :care_type';
            $params['care_type'] = $filters['care_type'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'DATE(mr.created_at) >= :from_date';
            $params['from_date'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(mr.created_at) <= :to_date';
            $params['to_date'] = $filters['to'];
        }

        return Database::query(
            "SELECT mr.*, c.name AS company_name,
                    e.employee_number, e.first_name, e.middle_name, e.last_name,
                    dpt.name AS department_name,
                    md.first_name AS dependent_first_name, md.last_name AS dependent_last_name, md.relationship,
                    mp.name AS provider_name, mp.provider_type,
                    mc.id AS claim_id, mc.status AS claim_status, mc.billed_amount, mc.accepted_amount
             FROM medical_requests mr
             INNER JOIN companies c ON c.id = mr.company_id
             INNER JOIN employees e ON e.id = mr.employee_id
             LEFT JOIN departments dpt ON dpt.id = e.department_id
             LEFT JOIN medical_dependents md ON md.id = mr.dependent_id
             LEFT JOIN medical_providers mp ON mp.id = mr.provider_id
             LEFT JOIN medical_claims mc ON mc.medical_request_id = mr.id AND mc.deleted_at IS NULL
             WHERE " . implode(' AND ', $where) . "
             ORDER BY mr.created_at DESC, mr.id DESC",
            $params
        )->fetchAll();
    }

    public function findDetailed(int $id, ?int $companyId, ?int $employeeId = null): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'mr');
        $params['id'] = $id;
        $selfScope = '';
        if ($employeeId !== null) {
            $selfScope = ' AND mr.employee_id = :employee_id';
            $params['employee_id'] = $employeeId;
        }

        $row = Database::query(
            "SELECT mr.*, c.name AS company_name, c.legal_name AS company_legal_name, c.address AS company_address, c.city AS company_city,
                    e.employee_number, e.first_name, e.middle_name, e.last_name, e.phone AS employee_phone,
                    dep.name AS department_name, pos.title AS position_title,
                    md.first_name AS dependent_first_name, md.last_name AS dependent_last_name, md.relationship, md.birth_date AS dependent_birth_date,
                    mp.name AS provider_name, mp.provider_type, mp.phone AS provider_phone, mp.address AS provider_address,
                    approver.first_name AS approver_first_name, approver.last_name AS approver_last_name
             FROM medical_requests mr
             INNER JOIN companies c ON c.id = mr.company_id
             INNER JOIN employees e ON e.id = mr.employee_id
             LEFT JOIN departments dep ON dep.id = e.department_id
             LEFT JOIN positions pos ON pos.id = e.position_id
             LEFT JOIN medical_dependents md ON md.id = mr.dependent_id
             LEFT JOIN medical_providers mp ON mp.id = mr.provider_id
             LEFT JOIN users approver ON approver.id = mr.approved_by
             WHERE mr.id = :id AND mr.deleted_at IS NULL {$scope} {$selfScope}
             LIMIT 1",
            $params
        )->fetch();

        return $row ?: null;
    }

    public function claims(int $requestId): array
    {
        return Database::query(
            'SELECT * FROM medical_claims
             WHERE medical_request_id = :request_id AND deleted_at IS NULL
             ORDER BY created_at DESC',
            ['request_id' => $requestId]
        )->fetchAll();
    }

    public function settingsForCompany(int $companyId): array
    {
        $settings = Database::query(
            'SELECT * FROM medical_coverage_settings
             WHERE company_id = :company_id AND deleted_at IS NULL
             LIMIT 1',
            ['company_id' => $companyId]
        )->fetch();

        if ($settings) {
            return $settings;
        }

        Database::query(
            'INSERT INTO medical_coverage_settings (company_id, created_at)
             VALUES (:company_id, NOW())',
            ['company_id' => $companyId]
        );

        return $this->settingsForCompany($companyId);
    }

    public function saveSettings(array $data): int
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $payload = [
            'company_id' => $companyId,
            'default_coverage_rate' => max(0, min(100, (float) ($data['default_coverage_rate'] ?? 80))),
            'annual_employee_ceiling' => $this->nullableMoney($data['annual_employee_ceiling'] ?? null),
            'annual_dependent_ceiling' => $this->nullableMoney($data['annual_dependent_ceiling'] ?? null),
            'voucher_valid_days' => max(1, min(90, (int) ($data['voucher_valid_days'] ?? 7))),
            'max_child_age' => max(0, min(30, (int) ($data['max_child_age'] ?? 18))),
            'student_child_age' => max(0, min(35, (int) ($data['student_child_age'] ?? 25))),
            'spouse_covered' => !empty($data['spouse_covered']) ? 1 : 0,
            'children_covered' => !empty($data['children_covered']) ? 1 : 0,
            'parents_covered' => !empty($data['parents_covered']) ? 1 : 0,
            'payroll_recovery_enabled' => !empty($data['payroll_recovery_enabled']) ? 1 : 0,
            'currency' => trim($data['currency'] ?? 'USD') ?: 'USD',
            'notes' => trim($data['notes'] ?? '') ?: null,
        ];

        $existing = Database::query(
            'SELECT id FROM medical_coverage_settings WHERE company_id = :company_id AND deleted_at IS NULL LIMIT 1',
            ['company_id' => $companyId]
        )->fetch();

        if ($existing) {
            $sets = [];
            $updatePayload = $payload;
            unset($updatePayload['company_id']);
            foreach ($updatePayload as $key => $value) {
                if ($key !== 'company_id') {
                    $sets[] = $key . ' = :' . $key;
                }
            }
            $updatePayload['id'] = (int) $existing['id'];
            Database::query('UPDATE medical_coverage_settings SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id', $updatePayload);
            return (int) $existing['id'];
        }

        Database::query(
            'INSERT INTO medical_coverage_settings
                (company_id, default_coverage_rate, annual_employee_ceiling, annual_dependent_ceiling, voucher_valid_days, max_child_age, student_child_age, spouse_covered, children_covered, parents_covered, payroll_recovery_enabled, currency, notes, created_at)
             VALUES
                (:company_id, :default_coverage_rate, :annual_employee_ceiling, :annual_dependent_ceiling, :voucher_valid_days, :max_child_age, :student_child_age, :spouse_covered, :children_covered, :parents_covered, :payroll_recovery_enabled, :currency, :notes, NOW())',
            $payload
        );

        return (int) Database::connection()->lastInsertId();
    }

    public function saveDependent(array $data, ?int $userId = null): int
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        $status = $this->normalize($data['status'] ?? 'active', ['pending', 'active', 'suspended', 'expired', 'rejected']) ?: 'active';

        Database::query(
            'INSERT INTO medical_dependents
                (company_id, employee_id, relationship, first_name, last_name, gender, birth_date, national_id, phone, document_type, document_reference, student_until, coverage_start, coverage_end, status, verified_by, verified_at, notes, created_at)
             VALUES
                (:company_id, :employee_id, :relationship, :first_name, :last_name, :gender, :birth_date, :national_id, :phone, :document_type, :document_reference, :student_until, :coverage_start, :coverage_end, :status, :verified_by, :verified_at, :notes, NOW())',
            [
                'company_id' => $companyId,
                'employee_id' => (int) ($data['employee_id'] ?? 0),
                'relationship' => $this->normalize($data['relationship'] ?? 'child', array_keys(self::RELATIONSHIPS)) ?: 'child',
                'first_name' => trim($data['first_name'] ?? ''),
                'last_name' => trim($data['last_name'] ?? ''),
                'gender' => $this->normalize($data['gender'] ?? null, ['male', 'female', 'other']),
                'birth_date' => $data['birth_date'] ?: null,
                'national_id' => trim($data['national_id'] ?? '') ?: null,
                'phone' => trim($data['phone'] ?? '') ?: null,
                'document_type' => trim($data['document_type'] ?? '') ?: null,
                'document_reference' => trim($data['document_reference'] ?? '') ?: null,
                'student_until' => ($data['student_until'] ?? '') ?: null,
                'coverage_start' => ($data['coverage_start'] ?? '') ?: date('Y-m-d'),
                'coverage_end' => ($data['coverage_end'] ?? '') ?: null,
                'status' => $status,
                'verified_by' => $status === 'active' ? $userId : null,
                'verified_at' => $status === 'active' ? date('Y-m-d H:i:s') : null,
                'notes' => trim($data['notes'] ?? '') ?: null,
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    public function saveProvider(array $data): int
    {
        Database::query(
            'INSERT INTO medical_providers
                (company_id, name, provider_type, contact_name, phone, email, address, city, agreement_reference, default_coverage_rate, status, created_at)
             VALUES
                (:company_id, :name, :provider_type, :contact_name, :phone, :email, :address, :city, :agreement_reference, :default_coverage_rate, :status, NOW())',
            [
                'company_id' => (int) ($data['company_id'] ?? 0),
                'name' => trim($data['name'] ?? ''),
                'provider_type' => $this->normalize($data['provider_type'] ?? 'clinic', ['hospital', 'clinic', 'pharmacy', 'laboratory', 'other']) ?: 'clinic',
                'contact_name' => trim($data['contact_name'] ?? '') ?: null,
                'phone' => trim($data['phone'] ?? '') ?: null,
                'email' => trim($data['email'] ?? '') ?: null,
                'address' => trim($data['address'] ?? '') ?: null,
                'city' => trim($data['city'] ?? '') ?: null,
                'agreement_reference' => trim($data['agreement_reference'] ?? '') ?: null,
                'default_coverage_rate' => $this->nullableRate($data['default_coverage_rate'] ?? null),
                'status' => $this->normalize($data['status'] ?? 'active', ['active', 'inactive']) ?: 'active',
            ]
        );

        return (int) Database::connection()->lastInsertId();
    }

    public function saveRequest(array $data, int $userId): int
    {
        $employee = $this->employee((int) ($data['employee_id'] ?? 0), (int) ($data['company_id'] ?? 0));
        if (!$employee) {
            throw new \InvalidArgumentException('Employe introuvable.');
        }

        $companyId = (int) $employee['company_id'];
        $settings = $this->settingsForCompany($companyId);
        $dependentId = $this->nullableInt($data['dependent_id'] ?? null);
        if ($dependentId !== null && !$this->dependentBelongsToEmployee($dependentId, (int) $employee['id'], $companyId)) {
            throw new \InvalidArgumentException('Ayant droit non autorise.');
        }

        $provider = $this->provider($this->nullableInt($data['provider_id'] ?? null), $companyId);
        $rate = $provider && $provider['default_coverage_rate'] !== null
            ? (float) $provider['default_coverage_rate']
            : (float) $settings['default_coverage_rate'];
        $requested = max(0, (float) ($data['requested_amount'] ?? 0));
        $usage = $this->annualUsage($companyId, (int) $employee['id'], $dependentId);
        $ceiling = $dependentId === null ? $settings['annual_employee_ceiling'] : $settings['annual_dependent_ceiling'];
        $quote = self::quote($requested, $rate, $ceiling !== null ? (float) $ceiling : null, $usage);

        Database::query(
            'INSERT INTO medical_requests
                (company_id, employee_id, dependent_id, provider_id, request_number, care_type, requested_amount, approved_amount, covered_amount, employee_share, coverage_rate, currency, medical_reason, status, requested_by, created_at)
             VALUES
                (:company_id, :employee_id, :dependent_id, :provider_id, :request_number, :care_type, :requested_amount, 0, 0, 0, :coverage_rate, :currency, :medical_reason, "submitted", :requested_by, NOW())',
            [
                'company_id' => $companyId,
                'employee_id' => (int) $employee['id'],
                'dependent_id' => $dependentId,
                'provider_id' => $provider ? (int) $provider['id'] : null,
                'request_number' => $this->generateRequestNumber($companyId),
                'care_type' => $this->normalize($data['care_type'] ?? 'consultation', array_keys(self::CARE_TYPES)) ?: 'consultation',
                'requested_amount' => $requested,
                'coverage_rate' => $quote['rate'],
                'currency' => trim($settings['currency'] ?? 'USD') ?: 'USD',
                'medical_reason' => trim($data['medical_reason'] ?? '') ?: null,
                'requested_by' => $userId,
            ]
        );

        $id = (int) Database::connection()->lastInsertId();
        $this->notifyHr($companyId, $id);

        return $id;
    }

    public function approve(int $id, ?int $companyId, int $userId, ?float $approvedAmount = null): ?array
    {
        $request = $this->findDetailed($id, $companyId);
        if (!$request || !in_array($request['status'], ['submitted', 'approved'], true)) {
            return null;
        }

        $settings = $this->settingsForCompany((int) $request['company_id']);
        $amount = $approvedAmount !== null ? max(0, $approvedAmount) : (float) $request['requested_amount'];
        $usage = $this->annualUsage((int) $request['company_id'], (int) $request['employee_id'], isset($request['dependent_id']) ? (int) $request['dependent_id'] : null, $id);
        $ceiling = $request['dependent_id'] === null ? $settings['annual_employee_ceiling'] : $settings['annual_dependent_ceiling'];
        $quote = self::quote($amount, (float) $request['coverage_rate'], $ceiling !== null ? (float) $ceiling : null, $usage);
        $expiresAt = (new DateTimeImmutable())->modify('+' . max(1, (int) $settings['voucher_valid_days']) . ' days')->format('Y-m-d');

        Database::query(
            "UPDATE medical_requests
             SET approved_amount = :approved_amount,
                 covered_amount = :covered_amount,
                 employee_share = :employee_share,
                 status = 'voucher_issued',
                 approved_by = :approved_by,
                 approved_at = NOW(),
                 voucher_issued_at = NOW(),
                 voucher_expires_at = :voucher_expires_at,
                 updated_at = NOW()
             WHERE id = :id",
            [
                'id' => $id,
                'approved_amount' => $quote['approved_amount'],
                'covered_amount' => $quote['covered_amount'],
                'employee_share' => $quote['employee_share'],
                'approved_by' => $userId,
                'voucher_expires_at' => $expiresAt,
            ]
        );

        $this->notifyEmployee((int) $request['company_id'], (int) $request['employee_id'], 'success', 'Bon medical emis', 'Votre prise en charge medicale a ete approuvee.');

        return $this->findDetailed($id, $companyId);
    }

    public function reject(int $id, ?int $companyId, int $userId, string $reason): ?array
    {
        $request = $this->findDetailed($id, $companyId);
        if (!$request || !in_array($request['status'], ['submitted', 'approved', 'voucher_issued'], true)) {
            return null;
        }

        Database::query(
            "UPDATE medical_requests
             SET status = 'rejected', approved_by = :user_id, rejection_reason = :reason, updated_at = NOW()
             WHERE id = :id",
            ['id' => $id, 'user_id' => $userId, 'reason' => $reason]
        );

        $this->notifyEmployee((int) $request['company_id'], (int) $request['employee_id'], 'danger', 'Prise en charge refusee', $reason);

        return $this->findDetailed($id, $companyId);
    }

    public function saveClaim(int $requestId, ?int $companyId, array $data): ?int
    {
        $request = $this->findDetailed($requestId, $companyId);
        if (!$request || !in_array($request['status'], ['voucher_issued', 'invoiced', 'validated'], true)) {
            return null;
        }

        $accepted = max(0, (float) ($data['accepted_amount'] ?? $data['billed_amount'] ?? 0));
        $billed = max($accepted, (float) ($data['billed_amount'] ?? 0));
        $quote = self::quote($accepted, (float) $request['coverage_rate'], null, 0);

        Database::beginTransaction();
        try {
            Database::query(
                'INSERT INTO medical_claims
                    (company_id, medical_request_id, invoice_number, invoice_date, billed_amount, accepted_amount, rejected_amount, covered_amount, employee_share, status, notes, created_at)
                 VALUES
                    (:company_id, :request_id, :invoice_number, :invoice_date, :billed_amount, :accepted_amount, :rejected_amount, :covered_amount, :employee_share, "validated", :notes, NOW())',
                [
                    'company_id' => (int) $request['company_id'],
                    'request_id' => $requestId,
                    'invoice_number' => trim($data['invoice_number'] ?? '') ?: null,
                    'invoice_date' => $data['invoice_date'] ?: date('Y-m-d'),
                    'billed_amount' => $billed,
                    'accepted_amount' => $accepted,
                    'rejected_amount' => max(0, $billed - $accepted),
                    'covered_amount' => $quote['covered_amount'],
                    'employee_share' => $quote['employee_share'],
                    'notes' => trim($data['notes'] ?? '') ?: null,
                ]
            );
            $claimId = (int) Database::connection()->lastInsertId();
            Database::query(
                "UPDATE medical_requests
                 SET status = 'validated', approved_amount = :approved, covered_amount = :covered, employee_share = :share, updated_at = NOW()
                 WHERE id = :id",
                ['id' => $requestId, 'approved' => $accepted, 'covered' => $quote['covered_amount'], 'share' => $quote['employee_share']]
            );
            Database::commit();
            return $claimId;
        } catch (Throwable $exception) {
            Database::rollBack();
            throw $exception;
        }
    }

    public function payClaim(int $claimId, ?int $companyId): bool
    {
        [$scope, $params] = $this->scope($companyId, 'medical_claims');
        $params['id'] = $claimId;
        $claim = Database::query("SELECT * FROM medical_claims WHERE id = :id AND deleted_at IS NULL {$scope} LIMIT 1", $params)->fetch();
        if (!$claim || !in_array($claim['status'], ['received', 'validated'], true)) {
            return false;
        }

        Database::beginTransaction();
        try {
            Database::query("UPDATE medical_claims SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE id = :id", ['id' => $claimId]);
            Database::query("UPDATE medical_requests SET status = 'paid', updated_at = NOW() WHERE id = :id", ['id' => (int) $claim['medical_request_id']]);
            Database::commit();
            return true;
        } catch (Throwable $exception) {
            Database::rollBack();
            throw $exception;
        }
    }

    public function dependents(?int $companyId, ?int $employeeId = null): array
    {
        [$scope, $params] = $this->scope($companyId, 'md');
        if ($employeeId !== null) {
            $scope .= ' AND md.employee_id = :employee_id';
            $params['employee_id'] = $employeeId;
        }

        return Database::query(
            "SELECT md.*, e.employee_number, e.first_name AS employee_first_name, e.last_name AS employee_last_name, c.name AS company_name
             FROM medical_dependents md
             INNER JOIN employees e ON e.id = md.employee_id
             INNER JOIN companies c ON c.id = md.company_id
             WHERE md.deleted_at IS NULL {$scope}
             ORDER BY md.created_at DESC",
            $params
        )->fetchAll();
    }

    public function providers(?int $companyId): array
    {
        [$scope, $params] = $this->scope($companyId, 'mp');

        return Database::query(
            "SELECT mp.*, c.name AS company_name
             FROM medical_providers mp
             INNER JOIN companies c ON c.id = mp.company_id
             WHERE mp.deleted_at IS NULL {$scope}
             ORDER BY mp.status ASC, mp.name ASC",
            $params
        )->fetchAll();
    }

    public function employees(?int $companyId, ?int $employeeId = null): array
    {
        [$scope, $params] = $this->scope($companyId, 'e');
        if ($employeeId !== null) {
            $scope .= ' AND e.id = :employee_id';
            $params['employee_id'] = $employeeId;
        }

        return Database::query(
            "SELECT e.id, e.company_id, e.employee_number, e.first_name, e.middle_name, e.last_name,
                    d.name AS department_name, p.title AS position_title
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN positions p ON p.id = e.position_id
             WHERE e.deleted_at IS NULL AND e.employment_status IN ('active', 'on_leave') {$scope}
             ORDER BY e.last_name ASC, e.first_name ASC",
            $params
        )->fetchAll();
    }

    public function companies(?int $companyId): array
    {
        $scope = '';
        $params = [];
        if ($companyId !== null) {
            $scope = ' AND id = :company_id';
            $params['company_id'] = $companyId;
        }

        return Database::query("SELECT id, name FROM companies WHERE deleted_at IS NULL {$scope} ORDER BY name ASC", $params)->fetchAll();
    }

    public function employee(int $employeeId, ?int $companyId): ?array
    {
        [$scope, $params] = $this->scope($companyId, 'employees');
        $params['id'] = $employeeId;

        $row = Database::query(
            "SELECT * FROM employees
             WHERE id = :id AND deleted_at IS NULL AND employment_status IN ('active', 'on_leave') {$scope}
             LIMIT 1",
            $params
        )->fetch();

        return $row ?: null;
    }

    public static function quote(float $amount, float $rate, ?float $ceiling, float $alreadyUsed): array
    {
        $amount = max(0, round($amount, 2));
        $rate = max(0, min(100, $rate));
        $covered = round($amount * ($rate / 100), 2);

        if ($ceiling !== null) {
            $remaining = max(0, round($ceiling - max(0, $alreadyUsed), 2));
            $covered = min($covered, $remaining);
        }

        $covered = round($covered, 2);

        return [
            'approved_amount' => $amount,
            'rate' => $rate,
            'covered_amount' => $covered,
            'employee_share' => round(max(0, $amount - $covered), 2),
        ];
    }

    public static function dependentEligible(array $dependent, array $settings, ?string $today = null): bool
    {
        if (($dependent['status'] ?? 'active') !== 'active') {
            return false;
        }

        $date = $today ?: date('Y-m-d');
        if (!empty($dependent['coverage_start']) && $dependent['coverage_start'] > $date) {
            return false;
        }
        if (!empty($dependent['coverage_end']) && $dependent['coverage_end'] < $date) {
            return false;
        }

        $relationship = $dependent['relationship'] ?? '';
        if ($relationship === 'spouse') {
            return !empty($settings['spouse_covered']);
        }
        if (in_array($relationship, ['father', 'mother'], true)) {
            return !empty($settings['parents_covered']);
        }
        if ($relationship !== 'child') {
            return true;
        }
        if (empty($settings['children_covered'])) {
            return false;
        }
        if (empty($dependent['birth_date'])) {
            return true;
        }

        $birth = new DateTimeImmutable($dependent['birth_date']);
        $age = $birth->diff(new DateTimeImmutable($date))->y;
        if ($age <= (int) ($settings['max_child_age'] ?? 18)) {
            return true;
        }

        return !empty($dependent['student_until'])
            && $dependent['student_until'] >= $date
            && $age <= (int) ($settings['student_child_age'] ?? 25);
    }

    public function annualUsage(int $companyId, int $employeeId, ?int $dependentId, ?int $exceptRequestId = null): float
    {
        $params = [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'year_start' => date('Y-01-01'),
            'year_end' => date('Y-12-31'),
        ];
        $dependentWhere = $dependentId === null ? 'AND dependent_id IS NULL' : 'AND dependent_id = :dependent_id';
        if ($dependentId !== null) {
            $params['dependent_id'] = $dependentId;
        }
        $except = '';
        if ($exceptRequestId !== null) {
            $except = ' AND id <> :except_id';
            $params['except_id'] = $exceptRequestId;
        }

        return (float) Database::query(
            "SELECT COALESCE(SUM(covered_amount), 0)
             FROM medical_requests
             WHERE company_id = :company_id
             AND employee_id = :employee_id
             {$dependentWhere}
             {$except}
             AND deleted_at IS NULL
             AND status IN ('approved', 'voucher_issued', 'invoiced', 'validated', 'paid')
             AND DATE(created_at) BETWEEN :year_start AND :year_end",
            $params
        )->fetchColumn();
    }

    private function provider(?int $providerId, int $companyId): ?array
    {
        if ($providerId === null) {
            return null;
        }

        $row = Database::query(
            'SELECT * FROM medical_providers
             WHERE id = :id AND company_id = :company_id AND deleted_at IS NULL AND status = "active"
             LIMIT 1',
            ['id' => $providerId, 'company_id' => $companyId]
        )->fetch();

        return $row ?: null;
    }

    private function dependentBelongsToEmployee(int $dependentId, int $employeeId, int $companyId): bool
    {
        $dependent = Database::query(
            'SELECT md.*, mcs.spouse_covered, mcs.children_covered, mcs.parents_covered, mcs.max_child_age, mcs.student_child_age
             FROM medical_dependents md
             LEFT JOIN medical_coverage_settings mcs ON mcs.company_id = md.company_id AND mcs.deleted_at IS NULL
             WHERE md.id = :id AND md.employee_id = :employee_id AND md.company_id = :company_id AND md.deleted_at IS NULL
             LIMIT 1',
            ['id' => $dependentId, 'employee_id' => $employeeId, 'company_id' => $companyId]
        )->fetch();

        return $dependent ? self::dependentEligible($dependent, $dependent) : false;
    }

    private function generateRequestNumber(int $companyId): string
    {
        $prefix = 'MED-' . date('Ym') . '-';
        $count = (int) Database::query(
            'SELECT COUNT(*) FROM medical_requests WHERE company_id = :company_id AND request_number LIKE :prefix',
            ['company_id' => $companyId, 'prefix' => $prefix . '%']
        )->fetchColumn();

        do {
            $number = $prefix . str_pad((string) (++$count), 4, '0', STR_PAD_LEFT);
            $exists = (int) Database::query(
                'SELECT COUNT(*) FROM medical_requests WHERE company_id = :company_id AND request_number = :number',
                ['company_id' => $companyId, 'number' => $number]
            )->fetchColumn();
        } while ($exists > 0);

        return $number;
    }

    private function notifyHr(int $companyId, int $requestId): void
    {
        Database::query(
            "INSERT INTO notifications (company_id, user_id, title, message, type, created_at)
             SELECT :company_id_value, users.id, 'Demande medicale a valider', CONCAT('Une demande de prise en charge #', :request_id_value, ' attend une decision.'), 'warning', NOW()
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE users.deleted_at IS NULL AND users.status = 'active'
             AND (users.company_id = :company_id_filter OR roles.slug = 'super-admin')
             AND roles.slug IN ('super-admin', 'admin-rh')",
            ['company_id_value' => $companyId, 'company_id_filter' => $companyId, 'request_id_value' => $requestId]
        );
    }

    private function notifyEmployee(int $companyId, int $employeeId, string $type, string $title, string $message): void
    {
        Database::query(
            "INSERT INTO notifications (company_id, user_id, title, message, type, created_at)
             SELECT :company_id, users.id, :title, :message, :type, NOW()
             FROM users
             WHERE users.employee_id = :employee_id AND users.deleted_at IS NULL AND users.status = 'active'",
            ['company_id' => $companyId, 'employee_id' => $employeeId, 'title' => $title, 'message' => $message, 'type' => $type]
        );
    }

    private function scope(?int $companyId, string $table): array
    {
        if ($companyId === null) {
            return ['', []];
        }

        return [" AND {$table}.company_id = :company_id", ['company_id' => $companyId]];
    }

    private function normalize($value, array $allowed): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function nullableInt($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, round((float) $value, 2));
    }

    private function nullableRate($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(100, (float) $value));
    }
}
