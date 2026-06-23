<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollSimulation;
use App\Models\Payslip;
use App\Models\SocialContributionSetting;
use App\Models\TaxSetting;
use Throwable;

class PayrollController extends Controller
{
    private PayrollPeriod $periods;
    private Payslip $payslips;
    private PayrollItem $items;
    private TaxSetting $taxes;
    private SocialContributionSetting $contributions;
    private PayrollSimulation $simulation;

    public function __construct()
    {
        $this->periods = new PayrollPeriod();
        $this->payslips = new Payslip();
        $this->items = new PayrollItem();
        $this->taxes = new TaxSetting();
        $this->contributions = new SocialContributionSetting();
        $this->simulation = new PayrollSimulation();
    }

    public function index(): void
    {
        $scope = $this->companyScope();

        $this->view('payroll.index', [
            'title' => 'Paie RDC',
            'periods' => $this->periods->allWithStats($scope),
        ]);
    }

    public function create(): void
    {
        $this->view('payroll.create', [
            'title' => 'Nouvelle periode de paie',
            'companies' => $this->periods->companies($this->companyScope()),
            'isSuperAdmin' => $this->companyScope() === null,
            'defaultCompanyId' => $this->defaultCompanyId(),
        ]);
    }

    public function settings(): void
    {
        $scope = $this->companyScope();
        $this->items->ensureDefaults($scope);
        $this->contributions->ensureDefaults($scope);

        $this->view('payroll.settings', [
            'title' => 'Parametres paie',
            'items' => $this->items->allForCompany($scope),
            'taxSettings' => $this->taxes->allForCompany($scope),
            'contributionSettings' => $this->contributions->allForCompany($scope),
            'companies' => $this->periods->companies($scope),
            'isSuperAdmin' => $scope === null,
            'defaultCompanyId' => $this->defaultCompanyId(),
        ]);
    }

    public function simulation(): void
    {
        $scope = $this->companyScope();
        $this->items->ensureDefaults($scope);
        $this->contributions->ensureDefaults($scope);

        $this->view('payroll.simulation', [
            'title' => 'Simulation paie',
            'companies' => $this->periods->companies($scope),
            'isSuperAdmin' => $scope === null,
            'defaultCompanyId' => $this->defaultCompanyId(),
        ]);
    }

    public function simulate(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $companyId = $this->resolvedCompanyId($_POST);
        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $this->jsonError('Entreprise non autorisee.', 403);
            return;
        }

        $targetNet = (float) ($_POST['target_net'] ?? 0);
        if ($targetNet <= 0) {
            $this->jsonError('Indiquez un net a payer strictement positif.', 422, ['target_net' => 'Net obligatoire']);
            return;
        }

        $options = [
            'taxable_earnings' => (float) ($_POST['taxable_earnings'] ?? 0),
            'non_taxable_earnings' => (float) ($_POST['non_taxable_earnings'] ?? 0),
            'deductions' => (float) ($_POST['deductions'] ?? 0),
        ];

        try {
            $result = $this->simulation->simulateNetToBase($companyId, $targetNet, $options);
            Auth::log('payroll_simulation_calculated', $companyId, Auth::id(), [
                'target_net' => $targetNet,
                'base_salary' => $result['base_salary'],
            ]);

            if (!$this->expectsJson()) {
                $scope = $this->companyScope();
                $this->view('payroll.simulation', [
                    'title' => 'Simulation paie',
                    'companies' => $this->periods->companies($scope),
                    'isSuperAdmin' => $scope === null,
                    'defaultCompanyId' => $companyId,
                    'simulationResult' => $result,
                    'simulationInput' => $_POST,
                ]);
                return;
            }

            $this->json([
                'success' => true,
                'message' => 'Simulation calculee.',
                'result' => $result,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Simulation impossible avec les parametres actuels.', 500);
        }
    }

    public function simulationPdf(): void
    {
        if (!$this->validCsrfToken()) {
            http_response_code(419);
            echo 'Session invalide.';
            return;
        }

        $companyId = $this->resolvedCompanyId($_POST);
        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            http_response_code(403);
            echo 'Entreprise non autorisee.';
            return;
        }

        $targetNet = (float) ($_POST['target_net'] ?? 0);
        if ($targetNet <= 0) {
            http_response_code(422);
            echo 'Net a payer cible invalide.';
            return;
        }

        $options = [
            'taxable_earnings' => (float) ($_POST['taxable_earnings'] ?? 0),
            'non_taxable_earnings' => (float) ($_POST['non_taxable_earnings'] ?? 0),
            'deductions' => (float) ($_POST['deductions'] ?? 0),
        ];
        $result = $this->simulation->simulateNetToBase($companyId, $targetNet, $options);
        $company = Database::query(
            'SELECT * FROM companies WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $companyId]
        )->fetch() ?: [];

        Auth::log('payroll_simulation_pdf_exported', $companyId, Auth::id(), [
            'target_net' => $targetNet,
            'base_salary' => $result['base_salary'],
        ]);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="simulation-paie-' . date('Ymd-His') . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $this->professionalSimulationPdf($result, $company, $options);
    }

    public function storePeriod(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $companyId = $this->resolvedCompanyId($_POST);
        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $this->jsonError('Entreprise non autorisee.', 403);
            return;
        }

        $_POST['company_id'] = $companyId;

        try {
            $id = $this->periods->savePeriod($_POST);
            Auth::log('payroll_period_created', $companyId, Auth::id(), ['payroll_period_id' => $id]);

            $this->json([
                'success' => true,
                'message' => 'Periode creee.',
                'redirect' => url('/payroll/process?id=' . $id),
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Creation impossible. La periode existe peut-etre deja.', 500);
        }
    }

    public function process(): void
    {
        $period = $this->periods->findDetailed($this->requestId(), $this->companyScope());
        if (!$period) {
            http_response_code(404);
            $this->view('errors.404', ['title' => 'Periode introuvable'], 'auth');
            return;
        }

        $this->view('payroll.process', [
            'title' => 'Traitement paie',
            'period' => $period,
            'journal' => $this->payslips->journal((int) $period['id'], $this->companyScope()),
            'anomalies' => $this->payslips->anomalies($period),
        ]);
    }

    public function calculate(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $period = $this->periods->findDetailed($this->requestId(), $this->companyScope());
        if (!$period) {
            $this->jsonError('Periode introuvable.', 404);
            return;
        }

        try {
            $result = $this->payslips->processPeriod($period);
            Auth::log('payroll_period_processed', (int) $period['company_id'], Auth::id(), [
                'payroll_period_id' => (int) $period['id'],
                'processed' => $result['processed'],
            ]);

            $this->json([
                'success' => true,
                'message' => $result['processed'] . ' bulletin(s) calcule(s).',
                'reload' => true,
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Calcul impossible.', 500);
        }
    }

    public function close(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $period = $this->periods->findDetailed($this->requestId(), $this->companyScope());
        if (!$period) {
            $this->jsonError('Periode introuvable.', 404);
            return;
        }

        $journal = $this->payslips->journal((int) $period['id'], $this->companyScope());
        if ($journal === []) {
            $this->jsonError('Calculez la paie avant de cloturer la periode.', 422);
            return;
        }

        $blocking = array_filter($this->payslips->anomalies($period), static function (array $anomaly): bool {
            return ($anomaly['severity'] ?? '') === 'danger';
        });

        if ($blocking !== []) {
            $this->jsonError('Des anomalies bloquantes doivent etre corrigees avant cloture.', 422);
            return;
        }

        $this->periods->updateStatus((int) $period['id'], 'closed');
        Auth::log('payroll_period_closed', (int) $period['company_id'], Auth::id(), [
            'payroll_period_id' => (int) $period['id'],
        ]);

        $this->json([
            'success' => true,
            'message' => 'Periode cloturee.',
            'reload' => true,
        ]);
    }

    public function payslip(): void
    {
        $payslip = $this->payslips->findDetailed($this->requestId(), $this->companyScope());
        if (!$payslip) {
            http_response_code(404);
            $this->view('errors.404', ['title' => 'Bulletin introuvable'], 'auth');
            return;
        }

        $this->view('payroll.payslip', [
            'title' => 'Bulletin de paie',
            'payslip' => $payslip,
        ]);
    }

    public function pdf(): void
    {
        $payslip = $this->payslips->findDetailed($this->requestId(), $this->companyScope());
        if (!$payslip) {
            http_response_code(404);
            echo 'Bulletin introuvable';
            return;
        }

        header('Content-Type: application/pdf');
        $employeeNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($payslip['employee_number'] ?? $payslip['id']));
        $period = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($payslip['period_name'] ?? 'periode'));
        header('Content-Disposition: inline; filename="bulletin-' . $employeeNumber . '-' . $period . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $this->professionalPayslipPdf($payslip);
    }

    public function export(): void
    {
        $period = $this->periods->findDetailed($this->requestId(), $this->companyScope());
        if (!$period) {
            http_response_code(404);
            echo 'Periode introuvable';
            return;
        }

        $rows = $this->payslips->journal((int) $period['id'], $this->companyScope());

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="journal-paie-' . (int) $period['id'] . '.xls"');
        echo "<table border=\"1\">";
        echo '<tr><th>Matricule</th><th>Employe</th><th>Departement</th><th>Brut</th><th>Retenues</th><th>Net</th><th>Devise</th><th>Statut</th></tr>';
        foreach ($rows as $row) {
            $employee = trim(($row['last_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['first_name'] ?? ''));
            echo '<tr>';
            echo '<td>' . e($row['employee_number'] ?? '') . '</td>';
            echo '<td>' . e($employee) . '</td>';
            echo '<td>' . e($row['department_name'] ?? '') . '</td>';
            echo '<td>' . e((string) $row['gross_salary']) . '</td>';
            echo '<td>' . e((string) $row['total_deductions']) . '</td>';
            echo '<td>' . e((string) $row['net_salary']) . '</td>';
            echo '<td>' . e($row['currency'] ?? '') . '</td>';
            echo '<td>' . e($row['status'] ?? '') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    public function storeItem(): void
    {
        $this->storeSetting(function (array $data): int {
            return $this->items->saveItem($data);
        }, 'payroll_item_created');
    }

    public function storeTax(): void
    {
        $errors = $this->validateSetting('tax', $_POST);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signalés.', 422, $errors);
            return;
        }
        $this->storeSetting(function (array $data): int {
            return $this->taxes->saveSetting($data);
        }, 'tax_setting_created');
    }

    public function storeContribution(): void
    {
        $this->storeSetting(function (array $data): int {
            return $this->contributions->saveSetting($data);
        }, 'social_contribution_created');
    }

    public function settingDetail(): void
    {
        $type = (string) ($_GET['type'] ?? '');
        $id = (int) ($_GET['id'] ?? 0);
        [$model] = $this->settingContext($type);
        if (!$model || $id <= 0) {
            $this->jsonError('Paramètre invalide.', 422);
            return;
        }
        $record = $model->findScoped($id, $this->companyScope());
        if (!$record) {
            $this->jsonError('Paramètre introuvable ou non autorisé.', 404);
            return;
        }
        $this->json(['success' => true, 'type' => $type, 'record' => $record]);
    }

    public function updateSetting(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }
        $type = (string) ($_POST['setting_type'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        [$model, $saveMethod, $auditPrefix] = $this->settingContext($type);
        if (!$model || $id <= 0) {
            $this->jsonError('Paramètre invalide.', 422);
            return;
        }
        $current = $model->findScoped($id, $this->companyScope());
        if (!$current) {
            $this->jsonError('Paramètre introuvable ou non autorisé.', 404);
            return;
        }
        $companyId = $this->companyScope() ?? (int) ($_POST['company_id'] ?? $current['company_id']);
        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $this->jsonError('Entreprise non autorisée.', 403);
            return;
        }
        $_POST['company_id'] = $companyId;
        $errors = $this->validateSetting($type, $_POST);
        if ($errors !== []) {
            $this->jsonError('Veuillez corriger les champs signalés.', 422, $errors);
            return;
        }
        try {
            $model->{$saveMethod}($_POST, $id);
            Auth::log($auditPrefix . '_updated', $companyId, Auth::id(), ['id' => $id]);
            $this->json(['success' => true, 'message' => 'Paramètre mis à jour.', 'reload' => true]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Mise à jour impossible. Vérifiez notamment que le code est unique.', 422);
        }
    }

    public function deleteSetting(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }
        $type = (string) ($_POST['setting_type'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        [$model, , $auditPrefix] = $this->settingContext($type);
        if (!$model || $id <= 0) {
            $this->jsonError('Paramètre invalide.', 422);
            return;
        }
        $record = $model->findScoped($id, $this->companyScope());
        if (!$record) {
            $this->jsonError('Paramètre introuvable ou non autorisé.', 404);
            return;
        }
        $deleted = $model->softDeleteScoped($id, $this->companyScope());
        if ($deleted) {
            Auth::log($auditPrefix . '_deleted', (int) $record['company_id'], Auth::id(), [
                'id' => $id,
                'name' => $record['name'] ?? null,
            ]);
        }
        $this->json([
            'success' => $deleted,
            'message' => $deleted ? 'Paramètre supprimé.' : 'Suppression impossible.',
            'reload' => $deleted,
        ], $deleted ? 200 : 422);
    }

    private function storeSetting(callable $callback, string $action): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $companyId = $this->resolvedCompanyId($_POST);
        if ($companyId <= 0 || !$this->canAccessCompany($companyId)) {
            $this->jsonError('Entreprise non autorisee.', 403);
            return;
        }

        $_POST['company_id'] = $companyId;

        try {
            $id = $callback($_POST);
            Auth::log($action, $companyId, Auth::id(), ['id' => $id]);
            $this->json(['success' => true, 'message' => 'Parametre ajoute.', 'reload' => true]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $message = strpos($exception->getMessage(), 'Duplicate entry') !== false
                ? 'Une tranche avec le même code et la même borne minimale existe déjà.'
                : 'Enregistrement impossible. Vérifiez les valeurs saisies.';
            $this->jsonError($message, 422);
        }
    }

    private function settingContext(string $type): array
    {
        if ($type === 'item') {
            return [$this->items, 'saveItem', 'payroll_item'];
        }
        if ($type === 'tax') {
            return [$this->taxes, 'saveSetting', 'tax_setting'];
        }
        if ($type === 'contribution') {
            return [$this->contributions, 'saveSetting', 'social_contribution'];
        }
        return [null, null, null];
    }

    private function validateSetting(string $type, array $data): array
    {
        $errors = [];
        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = 'Le nom est obligatoire.';
        }
        $codeField = $type === 'item' ? 'code' : ($type === 'tax' ? 'tax_code' : 'contribution_code');
        if (trim((string) ($data[$codeField] ?? '')) === '') {
            $errors[$codeField] = 'Le code est obligatoire.';
        }
        if ($type === 'tax') {
            $min = (float) ($data['threshold_min'] ?? 0);
            $max = ($data['threshold_max'] ?? '') === '' ? null : (float) $data['threshold_max'];
            if ($max !== null && $max <= $min) {
                $errors['threshold_max'] = 'La borne maximale doit dépasser la borne minimale.';
            }
            if ((float) ($data['rate'] ?? 0) < 0 || (float) ($data['rate'] ?? 0) > 100) {
                $errors['rate'] = 'Le taux doit être compris entre 0 et 100.';
            }
        }
        if ($type === 'contribution') {
            foreach (['employee_rate', 'employer_rate'] as $field) {
                $value = (float) ($data[$field] ?? 0);
                if ($value < 0 || $value > 100) {
                    $errors[$field] = 'Le taux doit être compris entre 0 et 100.';
                }
            }
        }
        return $errors;
    }

    private function professionalSimulationPdf(array $result, array $company, array $options): string
    {
        $width = 595;
        $height = 842;
        $margin = 38;
        $currency = 'USD';
        $companyName = (string) (($company['legal_name'] ?? '') ?: ($company['name'] ?? 'Entreprise'));
        $reference = 'SIM-' . date('Ymd-His');
        $money = static fn($value): string => number_format((float) $value, 2, ',', ' ') . ' ' . $currency;
        $rate = static fn($value): string => (float) $value > 0 ? number_format((float) $value, 2, ',', ' ') . '%' : '-';
        $typeLabels = [
            'earning' => 'Gain',
            'deduction' => 'Retenue',
            'contribution' => 'Cotisation',
            'tax' => 'Impot',
            'employer_contribution' => 'Part patronale',
        ];
        $stream = '';

        $color = static fn(int $r, int $g, int $b): string => sprintf('%.3F %.3F %.3F', $r / 255, $g / 255, $b / 255);
        $fill = function (int $r, int $g, int $b) use (&$stream, $color): void {
            $stream .= $color($r, $g, $b) . " rg\n";
        };
        $stroke = function (int $r, int $g, int $b) use (&$stream, $color): void {
            $stream .= $color($r, $g, $b) . " RG\n";
        };
        $rect = function (float $x, float $top, float $w, float $h, bool $paint = true) use (&$stream, $height): void {
            $stream .= sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, $height - $top - $h, $w, $h, $paint ? 'B' : 'S');
        };
        $line = function (float $x1, float $top1, float $x2, float $top2) use (&$stream, $height): void {
            $stream .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $x1, $height - $top1, $x2, $height - $top2);
        };
        $text = function (string $value, float $x, float $top, float $size = 9, bool $bold = false, string $align = 'left', float $boxWidth = 0) use (&$stream, $height): void {
            $encoded = $this->pdfText($value);
            $estimate = strlen($encoded) * $size * .48;
            if ($align === 'right') {
                $x += max(0, $boxWidth - $estimate);
            } elseif ($align === 'center') {
                $x += max(0, ($boxWidth - $estimate) / 2);
            }
            $stream .= sprintf("BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $x, $height - $top - $size, $encoded);
        };

        $fill(24, 43, 77);
        $stroke(24, 43, 77);
        $rect(0, 0, $width, 116);
        $fill(255, 255, 255);
        $text('EH', $margin, 25, 23, true);
        $text($companyName, $margin + 46, 24, 15, true);
        $text(trim(implode(' · ', array_filter([$company['city'] ?? null, $company['country'] ?? null]))) ?: 'Republique Democratique du Congo', $margin + 46, 47, 8);
        $text('SIMULATION DE PAIE', $width - $margin - 245, 24, 18, true, 'right', 245);
        $fill(213, 224, 244);
        $text('Reference : ' . $reference, $width - $margin - 245, 55, 8, false, 'right', 245);
        $text('Document non comptable · Calcul a blanc', $width - $margin - 245, 76, 8, false, 'right', 245);

        $y = 140;
        $fill(245, 248, 252);
        $stroke(218, 225, 235);
        $rect($margin, $y, 250, 108);
        $rect($margin + 265, $y, 254, 108);
        $fill(47, 75, 122);
        $text('PARAMETRES', $margin + 14, $y + 14, 8, true);
        $text('RESULTAT PRINCIPAL', $margin + 279, $y + 14, 8, true);
        $fill(103, 116, 135);
        $text('Net cible', $margin + 14, $y + 39, 7.5);
        $text('Avantages imposables', $margin + 14, $y + 60, 7.5);
        $text('Avantages non imposables', $margin + 14, $y + 81, 7.5);
        $fill(37, 49, 66);
        $text($money($result['target_net'] ?? 0), $margin + 130, $y + 39, 8.5, true, 'right', 95);
        $text($money($options['taxable_earnings'] ?? 0), $margin + 130, $y + 60, 8.5, false, 'right', 95);
        $text($money($options['non_taxable_earnings'] ?? 0), $margin + 130, $y + 81, 8.5, false, 'right', 95);
        $fill(31, 121, 61);
        $text('Salaire de base estime', $margin + 279, $y + 39, 8);
        $text($money($result['base_salary'] ?? 0), $margin + 279, $y + 61, 17, true);
        $fill(103, 116, 135);
        $text('Ecart net : ' . $money($result['difference'] ?? 0), $margin + 279, $y + 88, 8);

        $y = 276;
        $cardWidth = 164;
        foreach ([
            ['SALAIRE BRUT', $money($result['gross_salary'] ?? 0), 235, 244, 255, 35, 82, 145],
            ['TOTAL RETENUES', $money($result['total_deductions'] ?? 0), 255, 246, 237, 171, 92, 28],
            ['NET CALCULE', $money($result['net_salary'] ?? 0), 232, 248, 238, 31, 121, 61],
        ] as $offset => $card) {
            $x = $margin + ($offset * ($cardWidth + 13));
            $fill($card[2], $card[3], $card[4]);
            $stroke($card[5], $card[6], $card[7]);
            $rect($x, $y, $cardWidth, 66);
            $fill($card[5], $card[6], $card[7]);
            $text($card[0], $x + 12, $y + 13, 7.5, true);
            $text($card[1], $x + 12, $y + 34, 13, true);
        }

        $y = 374;
        $fill(35, 62, 108);
        $stroke(35, 62, 108);
        $rect($margin, $y, 519, 28);
        $fill(255, 255, 255);
        $text('RUBRIQUE', $margin + 10, $y + 9, 7.5, true);
        $text('TYPE', $margin + 245, $y + 9, 7.5, true);
        $text('BASE', $margin + 330, $y + 9, 7.5, true, 'right', 72);
        $text('TAUX', $margin + 410, $y + 9, 7.5, true, 'right', 42);
        $text('MONTANT', $margin + 458, $y + 9, 7.5, true, 'right', 51);
        $y += 28;

        foreach (($result['lines'] ?? []) as $index => $item) {
            if ($y > 710) {
                break;
            }
            $fill($index % 2 === 0 ? 249 : 255, $index % 2 === 0 ? 251 : 255, $index % 2 === 0 ? 254 : 255);
            $stroke(229, 234, 241);
            $rect($margin, $y, 519, 30);
            $fill(36, 49, 67);
            $name = (string) ($item['name'] ?? '-');
            $text(strlen($name) > 38 ? substr($name, 0, 35) . '...' : $name, $margin + 10, $y + 7, 8.5, true);
            $fill(125, 137, 153);
            $text((string) ($item['code'] ?? '-'), $margin + 10, $y + 18, 6.8);
            $fill(67, 80, 98);
            $text($typeLabels[$item['type'] ?? ''] ?? (string) ($item['type'] ?? '-'), $margin + 245, $y + 10, 7.5);
            $text($money($item['base_amount'] ?? 0), $margin + 320, $y + 10, 7.5, false, 'right', 82);
            $text($rate($item['rate'] ?? 0), $margin + 402, $y + 10, 7.5, false, 'right', 50);
            $fill(31, 45, 65);
            $text($money($item['amount'] ?? 0), $margin + 452, $y + 10, 8, true, 'right', 57);
            $y += 30;
        }

        $y += 22;
        $fill(245, 248, 252);
        $stroke(218, 225, 235);
        $rect($margin, $y, 519, 66);
        $fill(47, 75, 122);
        $text('COUT EMPLOYEUR ET NOTE', $margin + 14, $y + 13, 7.5, true);
        $fill(37, 49, 66);
        $text('Charges patronales : ' . $money($result['employer_charges'] ?? 0), $margin + 14, $y + 33, 8.5, true);
        $text('Cout total employeur : ' . $money($result['total_employer_cost'] ?? 0), $margin + 280, $y + 33, 8.5, true);
        $fill(90, 104, 123);
        $text('Cette simulation est indicative et depend des parametres de paie actifs au moment de son edition.', $margin + 14, $y + 52, 7.5);

        $stroke(221, 227, 236);
        $line($margin, 796, $width - $margin, 796);
        $fill(112, 124, 142);
        $text('Document genere par ELLIOT-HR · ' . date('d/m/Y H:i'), $margin, 807, 7.5);
        $text('Page 1 / 1', $width - $margin - 70, 807, 7.5, false, 'right', 70);

        return $this->buildPdfDocument([$stream], $width, $height);
    }

    private function professionalPayslipPdf(array $payslip): string
    {
        $width = 595;
        $height = 842;
        $margin = 38;
        $currency = (string) ($payslip['currency'] ?? 'USD');
        $employee = trim(($payslip['last_name'] ?? '') . ' ' . ($payslip['middle_name'] ?? '') . ' ' . ($payslip['first_name'] ?? ''));
        $company = (string) (($payslip['company_legal_name'] ?? '') ?: ($payslip['company_name'] ?? 'Entreprise'));
        $reference = 'BP-' . str_pad((string) ($payslip['id'] ?? 0), 6, '0', STR_PAD_LEFT);
        $statusLabels = ['draft' => 'BROUILLON', 'validated' => 'VALIDE', 'paid' => 'PAYE', 'cancelled' => 'ANNULE'];
        $typeLabels = [
            'earning' => 'Gain', 'deduction' => 'Retenue', 'contribution' => 'Cotisation',
            'tax' => 'Impot', 'employer_contribution' => 'Part patronale',
        ];
        $money = static function ($value) use ($currency): string {
            return number_format((float) $value, 2, ',', ' ') . ' ' . $currency;
        };
        $pages = [''];
        $page = 0;
        $y = 0;

        $color = static fn(int $r, int $g, int $b): string => sprintf('%.3F %.3F %.3F', $r / 255, $g / 255, $b / 255);
        $fill = function (int $r, int $g, int $b) use (&$pages, &$page, $color): void {
            $pages[$page] .= $color($r, $g, $b) . " rg\n";
        };
        $stroke = function (int $r, int $g, int $b) use (&$pages, &$page, $color): void {
            $pages[$page] .= $color($r, $g, $b) . " RG\n";
        };
        $rect = function (float $x, float $top, float $w, float $h, bool $paint = true) use (&$pages, &$page, $height): void {
            $pages[$page] .= sprintf("%.2F %.2F %.2F %.2F re %s\n", $x, $height - $top - $h, $w, $h, $paint ? 'B' : 'S');
        };
        $line = function (float $x1, float $top1, float $x2, float $top2) use (&$pages, &$page, $height): void {
            $pages[$page] .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $x1, $height - $top1, $x2, $height - $top2);
        };
        $text = function (string $value, float $x, float $top, float $size = 9, bool $bold = false, string $align = 'left', float $boxWidth = 0) use (&$pages, &$page, $height): void {
            $encoded = $this->pdfText($value);
            $estimate = strlen($encoded) * $size * .48;
            if ($align === 'right') {
                $x += max(0, $boxWidth - $estimate);
            } elseif ($align === 'center') {
                $x += max(0, ($boxWidth - $estimate) / 2);
            }
            $pages[$page] .= sprintf("BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $x, $height - $top - $size, $encoded);
        };
        $wrap = function (string $value, int $max): array {
            $words = preg_split('/\s+/', trim($value)) ?: [];
            $result = [];
            $current = '';
            foreach ($words as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if (strlen($candidate) > $max && $current !== '') {
                    $result[] = $current;
                    $current = $word;
                } else {
                    $current = $candidate;
                }
            }
            if ($current !== '') {
                $result[] = $current;
            }
            return $result ?: ['-'];
        };

        $drawFooter = function (int $pageNumber, int $totalPages = 0) use (&$fill, &$stroke, &$line, &$text, $margin, $width, $reference): void {
            $stroke(221, 227, 236);
            $line($margin, 796, $width - $margin, 796);
            $fill(112, 124, 142);
            $text('Document genere par ELLIOT-HR · Reference ' . $reference, $margin, 807, 7.5);
            $text('Page ' . $pageNumber . ($totalPages > 0 ? ' / ' . $totalPages : ''), $width - $margin - 70, 807, 7.5, false, 'right', 70);
        };
        $drawContinuationHeader = function () use (&$fill, &$stroke, &$rect, &$text, $margin, $width, $company, $employee, $payslip): void {
            $fill(24, 43, 77);
            $stroke(24, 43, 77);
            $rect(0, 0, $width, 62);
            $fill(255, 255, 255);
            $text($company, $margin, 17, 13, true);
            $text('BULLETIN DE PAIE · ' . ($payslip['period_name'] ?? '-'), $margin, 37, 8);
            $text($employee, $width - $margin - 220, 20, 10, true, 'right', 220);
        };
        $newPage = function (bool $continuation = true) use (&$pages, &$page, &$y, &$drawContinuationHeader): void {
            if ($page > 0 || $pages[0] !== '') {
                $page++;
                $pages[$page] = '';
            }
            if ($continuation) {
                $drawContinuationHeader();
                $y = 82;
            }
        };

        $fill(24, 43, 77);
        $stroke(24, 43, 77);
        $rect(0, 0, $width, 118);
        $fill(255, 255, 255);
        $text('EH', $margin, 26, 23, true);
        $text($company, $margin + 46, 24, 15, true);
        $text(trim(implode(' · ', array_filter([
            $payslip['company_city'] ?? null,
            $payslip['company_country'] ?? null,
        ]))) ?: 'Republique Democratique du Congo', $margin + 46, 47, 8);
        $text('BULLETIN DE PAIE', $width - $margin - 230, 24, 18, true, 'right', 230);
        $text((string) ($payslip['period_name'] ?? '-'), $width - $margin - 230, 52, 10, false, 'right', 230);
        $fill(213, 224, 244);
        $text('Reference : ' . $reference, $width - $margin - 230, 75, 8, false, 'right', 230);
        $fill(255, 255, 255);
        $stroke(255, 255, 255);
        $rect($width - $margin - 92, 88, 92, 22);
        $fill(24, 43, 77);
        $text($statusLabels[$payslip['status'] ?? 'draft'] ?? strtoupper((string) ($payslip['status'] ?? '')), $width - $margin - 92, 94, 8, true, 'center', 92);

        $y = 140;
        $fill(245, 248, 252);
        $stroke(218, 225, 235);
        $rect($margin, $y, 250, 118);
        $rect($margin + 265, $y, 254, 118);
        $fill(47, 75, 122);
        $text('INFORMATIONS DU SALARIE', $margin + 14, $y + 14, 8, true);
        $text('PERIODE ET AFFECTATION', $margin + 279, $y + 14, 8, true);
        $fill(37, 49, 66);
        $text($employee ?: '-', $margin + 14, $y + 36, 12, true);
        $fill(103, 116, 135);
        $text('Matricule', $margin + 14, $y + 59, 7.5);
        $fill(37, 49, 66);
        $text((string) ($payslip['employee_number'] ?? '-'), $margin + 92, $y + 59, 8.5, true);
        $fill(103, 116, 135);
        $text('Email', $margin + 14, $y + 78, 7.5);
        $fill(37, 49, 66);
        $text((string) ($payslip['employee_email'] ?? '-'), $margin + 92, $y + 78, 8.5);
        $fill(103, 116, 135);
        $text('Embauche', $margin + 14, $y + 97, 7.5);
        $fill(37, 49, 66);
        $text(!empty($payslip['hire_date']) ? date('d/m/Y', strtotime($payslip['hire_date'])) : '-', $margin + 92, $y + 97, 8.5);
        $fill(103, 116, 135);
        $text('Periode', $margin + 279, $y + 38, 7.5);
        $fill(37, 49, 66);
        $text(date('d/m/Y', strtotime($payslip['start_date'])) . ' au ' . date('d/m/Y', strtotime($payslip['end_date'])), $margin + 355, $y + 38, 8.5, true);
        $fill(103, 116, 135);
        $text('Departement', $margin + 279, $y + 61, 7.5);
        $fill(37, 49, 66);
        $text((string) ($payslip['department_name'] ?? '-'), $margin + 355, $y + 61, 8.5);
        $fill(103, 116, 135);
        $text('Poste', $margin + 279, $y + 84, 7.5);
        $fill(37, 49, 66);
        $text((string) ($payslip['position_title'] ?? '-'), $margin + 355, $y + 84, 8.5);

        $y = 282;
        $drawTableHeader = function () use (&$fill, &$stroke, &$rect, &$text, &$y, $margin): void {
            $fill(35, 62, 108);
            $stroke(35, 62, 108);
            $rect($margin, $y, 519, 28);
            $fill(255, 255, 255);
            $text('RUBRIQUE', $margin + 10, $y + 9, 7.5, true);
            $text('TYPE', $margin + 245, $y + 9, 7.5, true);
            $text('BASE', $margin + 330, $y + 9, 7.5, true, 'right', 72);
            $text('TAUX', $margin + 410, $y + 9, 7.5, true, 'right', 42);
            $text('MONTANT', $margin + 458, $y + 9, 7.5, true, 'right', 51);
            $y += 28;
        };
        $drawTableHeader();

        foreach ($payslip['lines'] ?? [] as $index => $item) {
            if ($y > 690) {
                $drawFooter($page + 1);
                $newPage();
                $drawTableHeader();
            }
            $rowHeight = 30;
            $fill($index % 2 === 0 ? 249 : 255, $index % 2 === 0 ? 251 : 255, $index % 2 === 0 ? 254 : 255);
            $stroke(229, 234, 241);
            $rect($margin, $y, 519, $rowHeight);
            $fill(36, 49, 67);
            $name = (string) ($item['name'] ?? '-');
            if (strlen($name) > 38) {
                $name = substr($name, 0, 35) . '...';
            }
            $text($name, $margin + 10, $y + 7, 8.5, true);
            $fill(125, 137, 153);
            $text((string) ($item['code'] ?? '-'), $margin + 10, $y + 18, 6.8);
            $fill(67, 80, 98);
            $text($typeLabels[$item['type'] ?? ''] ?? (string) ($item['type'] ?? '-'), $margin + 245, $y + 10, 7.5);
            $text($money($item['base_amount'] ?? 0), $margin + 320, $y + 10, 7.5, false, 'right', 82);
            $rate = (float) ($item['rate'] ?? 0);
            $text($rate > 0 ? number_format($rate, 2, ',', ' ') . '%' : '-', $margin + 402, $y + 10, 7.5, false, 'right', 50);
            $fill(31, 45, 65);
            $text($money($item['amount'] ?? 0), $margin + 452, $y + 10, 8, true, 'right', 57);
            $y += $rowHeight;
        }

        if ($y > 620) {
            $drawFooter($page + 1);
            $newPage();
        } else {
            $y += 18;
        }
        $cardWidth = 164;
        foreach ([
            ['SALAIRE BRUT', $money($payslip['gross_salary'] ?? 0), 235, 244, 255, 35, 82, 145],
            ['TOTAL RETENUES', $money($payslip['total_deductions'] ?? 0), 255, 246, 237, 171, 92, 28],
            ['NET A PAYER', $money($payslip['net_salary'] ?? 0), 232, 248, 238, 31, 121, 61],
        ] as $offset => $card) {
            $x = $margin + ($offset * ($cardWidth + 13));
            $fill($card[2], $card[3], $card[4]);
            $stroke($card[5], $card[6], $card[7]);
            $rect($x, $y, $cardWidth, 66);
            $fill($card[5], $card[6], $card[7]);
            $text($card[0], $x + 12, $y + 13, 7.5, true);
            $text($card[1], $x + 12, $y + 34, 13, true);
        }
        $y += 88;
        $fill(245, 248, 252);
        $stroke(218, 225, 235);
        $rect($margin, $y, 519, 60);
        $fill(47, 75, 122);
        $text('INFORMATIONS LEGALES DE L’EMPLOYEUR', $margin + 14, $y + 12, 7.5, true);
        $fill(77, 91, 110);
        $legal = array_filter([
            !empty($payslip['company_registration_number']) ? 'RCCM : ' . $payslip['company_registration_number'] : null,
            !empty($payslip['company_national_id']) ? 'ID Nat. : ' . $payslip['company_national_id'] : null,
            !empty($payslip['company_tax_number']) ? 'NIF : ' . $payslip['company_tax_number'] : null,
        ]);
        $text(implode('   ·   ', $legal) ?: 'Identifiants legaux non renseignes', $margin + 14, $y + 30, 7.5);
        $address = trim(implode(', ', array_filter([
            $payslip['company_address'] ?? null, $payslip['company_city'] ?? null,
            $payslip['company_province'] ?? null, $payslip['company_country'] ?? null,
        ])));
        $text($address ?: 'Adresse non renseignee', $margin + 14, $y + 44, 7.5);
        $text(trim(implode(' · ', array_filter([$payslip['company_phone'] ?? null, $payslip['company_email'] ?? null]))) ?: '-', $margin + 280, $y + 44, 7.5, false, 'right', 225);
        $y += 76;
        $fill(90, 104, 123);
        foreach ($wrap('Ce bulletin est genere sur la base des informations validees dans ELLIOT-HR. Il constitue un document confidentiel destine au salarie.', 105) as $notice) {
            $text($notice, $margin, $y, 7.3);
            $y += 11;
        }

        $totalPages = count($pages);
        foreach (array_keys($pages) as $pageIndex) {
            $page = $pageIndex;
            $drawFooter($pageIndex + 1, $totalPages);
        }

        return $this->buildPdfDocument($pages, $width, $height);
    }

    private function buildPdfDocument(array $streams, int $width, int $height): string
    {
        $pageCount = count($streams);
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (5 + ($i * 2)) . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($streams as $index => $stream) {
            $pageObject = 5 + ($index * 2);
            $contentObject = $pageObject + 1;
            $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$width} {$height}] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[$contentObject] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $pdf .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 00000 f \n";
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }

    private function pdfText(string $value): string
    {
        $value = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value) ?: $value;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function companyScope(): ?int
    {
        $user = Auth::user() ?? [];
        return ($user['role_slug'] ?? '') === 'super-admin' ? null : (int) ($user['company_id'] ?? 0);
    }

    private function resolvedCompanyId(array $data): int
    {
        return $this->companyScope() ?? (int) ($data['company_id'] ?? 0);
    }

    private function canAccessCompany(int $companyId): bool
    {
        $scope = $this->companyScope();
        return $scope === null || $companyId === $scope;
    }

    private function defaultCompanyId(): int
    {
        $scope = $this->companyScope();
        if ($scope !== null) {
            return $scope;
        }
        $companies = $this->periods->companies(null);
        return (int) ($companies[0]['id'] ?? 0);
    }

    private function requestId(): int
    {
        return (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    }

    private function validCsrfToken(): bool
    {
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        $submittedToken = $_POST['_csrf_token'] ?? '';

        return is_string($sessionToken)
            && is_string($submittedToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $submittedToken);
    }

    private function expectsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return stripos($accept, 'application/json') !== false
            || strtolower($requestedWith) === 'xmlhttprequest';
    }

    private function jsonError(string $message, int $status = 400, array $errors = []): void
    {
        $this->json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }
}
