<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');

require APP_PATH . '/Helpers/functions.php';

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

use App\Models\MedicalSupport;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$quote = MedicalSupport::quote(1000, 80, null, 0);
$assert($quote['covered_amount'] === 800.0, 'La part entreprise doit respecter le taux standard.');
$assert($quote['employee_share'] === 200.0, 'La quote-part employe doit completer le montant approuve.');

$quote = MedicalSupport::quote(1000, 80, 700, 200);
$assert($quote['covered_amount'] === 500.0, 'Le plafond annuel restant doit limiter la couverture.');
$assert($quote['employee_share'] === 500.0, 'La part employe doit absorber le depassement de plafond.');

$quote = MedicalSupport::quote(500, 120, null, 0);
$assert($quote['covered_amount'] === 500.0, 'Le taux doit etre borne a 100%.');

$settings = [
    'spouse_covered' => 1,
    'children_covered' => 1,
    'parents_covered' => 0,
    'max_child_age' => 18,
    'student_child_age' => 25,
];

$assert(MedicalSupport::dependentEligible([
    'status' => 'active',
    'relationship' => 'spouse',
    'coverage_start' => '2026-01-01',
], $settings, '2026-07-01'), 'Le conjoint actif doit etre eligible.');

$assert(MedicalSupport::dependentEligible([
    'status' => 'active',
    'relationship' => 'child',
    'birth_date' => '2010-06-01',
    'coverage_start' => '2026-01-01',
], $settings, '2026-07-01'), 'Un enfant mineur doit etre eligible.');

$assert(MedicalSupport::dependentEligible([
    'status' => 'active',
    'relationship' => 'child',
    'birth_date' => '2004-06-01',
    'student_until' => '2026-12-31',
    'coverage_start' => '2026-01-01',
], $settings, '2026-07-01'), 'Un enfant etudiant dans la limite d age doit etre eligible.');

$assert(!MedicalSupport::dependentEligible([
    'status' => 'active',
    'relationship' => 'mother',
    'coverage_start' => '2026-01-01',
], $settings, '2026-07-01'), 'Un parent ne doit pas etre eligible si la politique ne le couvre pas.');

$assert(!MedicalSupport::dependentEligible([
    'status' => 'suspended',
    'relationship' => 'child',
    'birth_date' => '2012-01-01',
    'coverage_start' => '2026-01-01',
], $settings, '2026-07-01'), 'Un ayant droit suspendu ne doit pas etre eligible.');

echo "Medical business rules OK\n";
