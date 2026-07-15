<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');

require APP_PATH . '/Helpers/functions.php';
load_env(BASE_PATH . '/.env');

spl_autoload_register(static function (string $className): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $className, strlen($prefix)) !== 0) {
        return;
    }

    $file = APP_PATH . '/' . str_replace('\\', '/', substr($className, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Core\Database;
use App\Models\MedicalSupport;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$medical = new MedicalSupport();
$companyId = 1;
$employeeId = 1;
$userId = 2;
$providerId = null;
$dependentId = null;
$requestId = null;
$claimId = null;

try {
    $medical->saveSettings([
        'company_id' => $companyId,
        'default_coverage_rate' => 80,
        'annual_employee_ceiling' => 5000,
        'annual_dependent_ceiling' => 1000,
        'voucher_valid_days' => 7,
        'max_child_age' => 18,
        'student_child_age' => 25,
        'spouse_covered' => 1,
        'children_covered' => 1,
        'parents_covered' => 0,
        'currency' => 'USD',
    ]);

    $providerId = $medical->saveProvider([
        'company_id' => $companyId,
        'name' => 'Clinique Test Medical Flow ' . date('His'),
        'provider_type' => 'clinic',
        'city' => 'Lubumbashi',
        'default_coverage_rate' => 75,
    ]);

    $dependentId = $medical->saveDependent([
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'relationship' => 'child',
        'first_name' => 'Enfant Test',
        'last_name' => 'Medical Flow',
        'birth_date' => '2018-01-01',
        'coverage_start' => date('Y-m-d'),
        'status' => 'active',
    ], $userId);

    $requestId = $medical->saveRequest([
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'dependent_id' => $dependentId,
        'provider_id' => $providerId,
        'care_type' => 'consultation',
        'requested_amount' => 200,
        'medical_reason' => 'Test systeme automatise',
    ], $userId);

    $approved = $medical->approve($requestId, $companyId, $userId, 200);
    $assert($approved !== null, 'La demande doit etre approuvee.');
    $assert($approved['status'] === 'voucher_issued', 'Le statut doit passer a bon emis.');
    $assert((float) $approved['covered_amount'] === 150.0, 'Le taux prestataire de 75% doit etre applique.');

    $claimId = $medical->saveClaim($requestId, $companyId, [
        'invoice_number' => 'TEST-MED-' . date('His'),
        'invoice_date' => date('Y-m-d'),
        'billed_amount' => 220,
        'accepted_amount' => 200,
        'notes' => 'Liquidation test',
    ]);
    $assert($claimId !== null, 'La facture doit etre creee.');
    $assert($medical->payClaim((int) $claimId, $companyId), 'La facture doit passer payee.');

    $paid = $medical->findDetailed($requestId, $companyId);
    $assert($paid !== null && $paid['status'] === 'paid', 'La demande doit passer payee.');

    echo "Medical system flow OK\n";
} finally {
    if ($claimId !== null) {
        Database::query('DELETE FROM medical_claims WHERE id = :id', ['id' => $claimId]);
    }
    if ($requestId !== null) {
        Database::query('DELETE FROM medical_requests WHERE id = :id', ['id' => $requestId]);
    }
    if ($dependentId !== null) {
        Database::query('DELETE FROM medical_dependents WHERE id = :id', ['id' => $dependentId]);
    }
    if ($providerId !== null) {
        Database::query('DELETE FROM medical_providers WHERE id = :id', ['id' => $providerId]);
    }
    Database::query(
        "DELETE FROM notifications WHERE title IN ('Demande medicale a valider', 'Bon medical emis') AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
    );
}
