<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Declaration;
use App\Models\PayrollPeriod;
use Throwable;

class DeclarationController extends Controller
{
    private Declaration $declarations;
    private PayrollPeriod $periods;

    public function __construct()
    {
        $this->declarations = new Declaration();
        $this->periods = new PayrollPeriod();
    }

    public function index(): void
    {
        $dashboard = $this->declarations->dashboard($this->companyScope());

        $this->view('declarations.index', [
            'title' => 'Declarations RDC',
            'declarations' => $dashboard['rows'],
            'totals' => $dashboard['totals'],
            'alerts' => $dashboard['alerts'],
            'periods' => $this->declarations->payrollPeriods($this->companyScope()),
        ]);
    }

    public function show(): void
    {
        $declaration = $this->declarations->findDetailed($this->requestId(), $this->companyScope());
        if (!$declaration) {
            http_response_code(404);
            $this->view('errors.404', ['title' => 'Declaration introuvable'], 'auth');
            return;
        }

        $this->view('declarations.show', [
            'title' => 'Declaration ' . ($declaration['reference'] ?? ''),
            'declaration' => $declaration,
        ]);
    }

    public function generate(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $period = $this->periods->findDetailed($this->requestId(), $this->companyScope());
        if (!$period) {
            $this->jsonError('Periode de paie introuvable.', 404);
            return;
        }

        try {
            $id = $this->declarations->generate($period);
            Auth::log('declaration_generated', (int) $period['company_id'], Auth::id(), [
                'declaration_id' => $id,
                'payroll_period_id' => (int) $period['id'],
            ]);

            $this->json([
                'success' => true,
                'message' => 'Declaration generee.',
                'redirect' => url('/declarations/show?id=' . $id),
            ]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError('Generation impossible. Verifiez que la paie est calculee.', 500);
        }
    }

    public function payment(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        $status = (string) ($_POST['payment_status'] ?? 'pending');

        if (!$this->declarations->updatePayment($id, $status, $this->companyScope())) {
            $this->jsonError('Declaration introuvable.', 404);
            return;
        }

        Auth::log('declaration_payment_updated', null, Auth::id(), [
            'declaration_id' => $id,
            'payment_status' => $status,
        ]);

        $this->json(['success' => true, 'message' => 'Suivi paiement mis a jour.', 'reload' => true]);
    }

    public function uploadProof(): void
    {
        if (!$this->validCsrfToken()) {
            $this->jsonError('Session invalide.', 419);
            return;
        }

        $id = $this->requestId();
        if (empty($_FILES['proof'])) {
            $this->jsonError('Veuillez choisir une preuve de paiement.', 422);
            return;
        }

        try {
            if (!$this->declarations->attachProof($id, $_FILES['proof'], $this->companyScope())) {
                $this->jsonError('Declaration introuvable.', 404);
                return;
            }

            Auth::log('declaration_proof_uploaded', null, Auth::id(), ['declaration_id' => $id]);
            $this->json(['success' => true, 'message' => 'Preuve de paiement ajoutee.', 'reload' => true]);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->jsonError($exception->getMessage() ?: 'Upload impossible.', 422);
        }
    }

    public function pdf(): void
    {
        $declaration = $this->declarations->findDetailed($this->requestId(), $this->companyScope());
        if (!$declaration) {
            http_response_code(404);
            echo 'Declaration introuvable';
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="declaration-' . (int) $declaration['id'] . '.pdf"');
        echo $this->simplePdf($this->pdfLines($declaration));
    }

    public function export(): void
    {
        $declaration = $this->declarations->findDetailed($this->requestId(), $this->companyScope());
        if (!$declaration) {
            http_response_code(404);
            echo 'Declaration introuvable';
            return;
        }

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="declaration-' . (int) $declaration['id'] . '.xls"');
        echo '<table border="1">';
        echo '<tr><th>Matricule</th><th>Employe</th><th>Departement</th><th>Brut</th><th>Net</th><th>IPR</th><th>CNSS sal.</th><th>CNSS pat.</th><th>INPP sal.</th><th>INPP pat.</th><th>ONEM sal.</th><th>ONEM pat.</th><th>Devise</th></tr>';
        foreach ($declaration['details'] as $row) {
            $employee = trim(($row['last_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['first_name'] ?? ''));
            echo '<tr>';
            echo '<td>' . e($row['employee_number'] ?? '') . '</td>';
            echo '<td>' . e($employee) . '</td>';
            echo '<td>' . e($row['department_name'] ?? '') . '</td>';
            echo '<td>' . e((string) ($row['gross_salary'] ?? 0)) . '</td>';
            echo '<td>' . e((string) ($row['net_salary'] ?? 0)) . '</td>';
            echo '<td>' . e((string) ($row['ipr'] ?? 0)) . '</td>';
            echo '<td>' . e((string) ($row['cnss_employee'] ?? 0)) . '</td>';
            echo '<td>' . e((string) ($row['cnss_employer'] ?? 0)) . '</td>';
            echo '<td>' . e((string) ($row['inpp_employee'] ?? 0)) . '</td>';
            echo '<td>' . e((string) ($row['inpp_employer'] ?? 0)) . '</td>';
            echo '<td>' . e((string) ($row['onem_employee'] ?? 0)) . '</td>';
            echo '<td>' . e((string) ($row['onem_employer'] ?? 0)) . '</td>';
            echo '<td>' . e($row['currency'] ?? '') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    private function pdfLines(array $declaration): array
    {
        $currency = $declaration['currency'] ?? 'USD';
        $money = static fn($value): string => number_format((float) $value, 2, ',', ' ') . ' ' . $currency;

        return [
            'DECLARATION FISCALE ET SOCIALE RDC',
            'Reference: ' . ($declaration['reference'] ?? '-'),
            'Entreprise: ' . (($declaration['company_legal_name'] ?? '') ?: ($declaration['company_name'] ?? '-')),
            'Periode: ' . sprintf('%02d/%04d', (int) ($declaration['period_month'] ?? 0), (int) ($declaration['period_year'] ?? 0)),
            'Echeance: ' . date('d/m/Y', strtotime((string) ($declaration['due_date'] ?? 'now'))),
            '',
            'Etat IPR mensuel: ' . $money($declaration['ipr_total'] ?? 0),
            'Etat CNSS salarial: ' . $money($declaration['cnss_employee_total'] ?? 0),
            'Etat CNSS patronal: ' . $money($declaration['cnss_employer_total'] ?? 0),
            'Etat INPP salarial: ' . $money($declaration['inpp_employee_total'] ?? 0),
            'Etat INPP patronal: ' . $money($declaration['inpp_employer_total'] ?? 0),
            'Etat ONEM salarial: ' . $money($declaration['onem_employee_total'] ?? 0),
            'Etat ONEM patronal: ' . $money($declaration['onem_employer_total'] ?? 0),
            '',
            'Total retenues salariales: ' . $money($declaration['salary_withheld_total'] ?? 0),
            'Total charges patronales: ' . $money($declaration['employer_charges_total'] ?? 0),
            'Total a payer: ' . $money($declaration['total_due'] ?? 0),
        ];
    }

    private function simplePdf(array $lines): string
    {
        $content = "BT\n/F1 15 Tf\n50 790 Td\n";
        foreach ($lines as $index => $line) {
            if ($index === 1) {
                $content .= "/F1 10 Tf\n0 -24 Td\n";
            } elseif ($index > 1) {
                $content .= "0 -16 Td\n";
            }
            $content .= '(' . $this->pdfText($line) . ") Tj\n";
        }
        $content .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

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

    private function jsonError(string $message, int $status = 400, array $errors = []): void
    {
        $this->json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }
}
