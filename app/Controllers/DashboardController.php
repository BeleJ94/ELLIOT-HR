<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Company;
use App\Models\Employee;

class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user() ?? [];
        $isSuperAdmin = ($user['role_slug'] ?? '') === 'super-admin';
        $companyId = isset($user['company_id']) ? (int) $user['company_id'] : null;

        $companyModel = new Company();
        $employeeModel = new Employee();

        $companyStats = $companyModel->dashboardStats($companyId, $isSuperAdmin);
        $hrStats = $employeeModel->dashboardStats($companyId, $isSuperAdmin);

        $this->view('dashboard.index', [
            'title' => 'Tableau de bord',
            'scopeLabel' => $isSuperAdmin ? 'Statistiques globales SaaS' : 'Statistiques de votre entreprise',
            'isSuperAdmin' => $isSuperAdmin,
            'companyStats' => $companyStats,
            'hrStats' => $hrStats,
            'attendanceBreakdown' => $employeeModel->attendanceTodayBreakdown($companyId, $isSuperAdmin),
            'leaveStatusBreakdown' => $employeeModel->leaveRequestsByStatus($companyId, $isSuperAdmin),
            'contractStatusBreakdown' => $employeeModel->contractsByStatus($companyId, $isSuperAdmin),
            'companyStatusBreakdown' => $isSuperAdmin ? $companyModel->companiesByStatus() : [],
            'notifications' => $companyModel->recentNotifications($companyId, $isSuperAdmin),
        ]);
    }

    public function detail(): void
    {
        $type = $this->reportType();
        $report = $this->buildReport($type);

        if ($report === null) {
            $this->json(['success' => false, 'message' => 'Rapport introuvable.'], 404);
            return;
        }

        $this->json(['success' => true] + $report);
    }

    public function export(): void
    {
        $type = $this->reportType();
        $format = strtolower((string) ($_GET['format'] ?? 'pdf'));
        $report = $this->buildReport($type, 500);

        if ($report === null) {
            http_response_code(404);
            echo 'Rapport introuvable.';
            return;
        }

        $filename = 'dashboard-' . preg_replace('/[^a-z0-9_-]+/i', '-', $type) . '-' . date('Ymd-His');

        if ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
            echo $this->excelTable($report);
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $this->reportPdf($report);
    }

    private function buildReport(string $type, int $limit = 80): ?array
    {
        [$companyId, $isSuperAdmin] = $this->scope();
        $subtitle = $isSuperAdmin ? 'Perimetre global SaaS' : 'Perimetre entreprise';
        $params = [];
        $companyWhere = '';

        if (!$isSuperAdmin) {
            $companyWhere = ' AND c.id = :company_id';
            $params['company_id'] = $companyId;
        }

        $employeeScope = !$isSuperAdmin ? ' AND e.company_id = :company_id' : '';
        $contractScope = !$isSuperAdmin ? ' AND ct.company_id = :company_id' : '';
        $leaveScope = !$isSuperAdmin ? ' AND lr.company_id = :company_id' : '';
        $attendanceScope = !$isSuperAdmin ? ' AND a.company_id = :company_id' : '';
        $payrollScope = !$isSuperAdmin ? ' AND ps.company_id = :company_id' : '';
        $notificationScope = !$isSuperAdmin ? ' AND n.company_id = :company_id' : '';
        $subscriptionScope = !$isSuperAdmin ? ' AND s.company_id = :company_id' : '';

        $employeeColumns = ['Matricule', 'Employe', 'Entreprise', 'Departement', 'Poste', 'Statut'];
        $employeeSelect = "
            SELECT e.employee_number AS `Matricule`,
                   TRIM(CONCAT(e.last_name, ' ', COALESCE(e.middle_name, ''), ' ', e.first_name)) AS `Employe`,
                   c.name AS `Entreprise`,
                   COALESCE(d.name, '-') AS `Departement`,
                   COALESCE(p.title, '-') AS `Poste`,
                   e.employment_status AS `Statut`
            FROM employees e
            INNER JOIN companies c ON c.id = e.company_id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN positions p ON p.id = e.position_id
            WHERE e.deleted_at IS NULL{$employeeScope}
        ";

        switch ($type) {
            case 'companies':
                return $this->reportFromSql(
                    'Entreprises',
                    $subtitle,
                    ['Entreprise', 'Ville', 'Secteur', 'Statut', 'Plan', 'Cree le'],
                    "SELECT c.name AS `Entreprise`, COALESCE(c.city, '-') AS `Ville`, COALESCE(c.industry, '-') AS `Secteur`,
                            c.status AS `Statut`, COALESCE(sp.name, '-') AS `Plan`, DATE(c.created_at) AS `Cree le`
                     FROM companies c
                     LEFT JOIN subscription_plans sp ON sp.id = c.subscription_plan_id
                     WHERE c.deleted_at IS NULL{$companyWhere}
                     ORDER BY c.created_at DESC",
                    $params,
                    $limit
                );

            case 'employees':
                return $this->reportFromSql('Employes', $subtitle, $employeeColumns, $employeeSelect . ' ORDER BY e.created_at DESC', $params, $limit);

            case 'contracts_active':
                return $this->contractsReport('Contrats actifs', 'Contrats en cours', 'ct.status = "active"', $contractScope, $params, $limit);

            case 'contracts_risk':
                return $this->contractsReport('Contrats a risque', 'Expiration dans les 30 prochains jours', 'ct.status = "active" AND ct.end_date IS NOT NULL AND ct.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)', $contractScope, $params, $limit);

            case 'leaves_pending':
                return $this->leavesReport('Conges en attente', 'Demandes a valider', 'lr.status = "pending"', $leaveScope, $params, $limit);

            case 'monthly_payroll':
                return $this->reportFromSql(
                    'Masse salariale',
                    'Bulletins du mois courant',
                    ['Matricule', 'Employe', 'Entreprise', 'Periode', 'Brut', 'Retenues', 'Net', 'Statut'],
                    "SELECT e.employee_number AS `Matricule`,
                            TRIM(CONCAT(e.last_name, ' ', COALESCE(e.middle_name, ''), ' ', e.first_name)) AS `Employe`,
                            c.name AS `Entreprise`, pp.name AS `Periode`, ps.gross_salary AS `Brut`,
                            ps.total_deductions AS `Retenues`, ps.net_salary AS `Net`, ps.status AS `Statut`
                     FROM payslips ps
                     INNER JOIN payroll_periods pp ON pp.id = ps.payroll_period_id
                     INNER JOIN employees e ON e.id = ps.employee_id
                     INNER JOIN companies c ON c.id = ps.company_id
                     WHERE ps.deleted_at IS NULL AND pp.period_month = MONTH(CURDATE()) AND pp.period_year = YEAR(CURDATE()){$payrollScope}
                     ORDER BY ps.net_salary DESC",
                    $params,
                    $limit
                );

            case 'attendance_present':
                return $this->attendanceReport('Presences du jour', 'Pointage du jour', 'a.status IN ("present", "late", "half_day")', $attendanceScope, $params, $limit);

            case 'attendance_absent':
                return $this->attendanceReport('Absences du jour', 'Absences enregistrees aujourd hui', 'a.status = "absent"', $attendanceScope, $params, $limit);

            case 'priorities':
                return $this->prioritiesReport($companyId, $isSuperAdmin);

            case 'presence_rate':
            case 'attendance_today':
                return $this->attendanceReport('Presence terrain', 'Repartition des pointages du jour', '1 = 1', $attendanceScope, $params, $limit);

            case 'notifications':
                return $this->notificationsReport('Notifications recentes', 'Messages et alertes operationnels', $notificationScope, $params, $limit);

            case 'subscriptions':
                return $this->reportFromSql(
                    'Abonnements',
                    'Plans actifs, trial et echeances',
                    ['Entreprise', 'Plan', 'Statut', 'Debut', 'Fin', 'Trial fin'],
                    "SELECT c.name AS `Entreprise`, sp.name AS `Plan`, s.status AS `Statut`, s.starts_at AS `Debut`,
                            COALESCE(s.ends_at, '-') AS `Fin`, COALESCE(s.trial_ends_at, '-') AS `Trial fin`
                     FROM subscriptions s
                     INNER JOIN companies c ON c.id = s.company_id
                     INNER JOIN subscription_plans sp ON sp.id = s.subscription_plan_id
                     WHERE s.deleted_at IS NULL{$subscriptionScope}
                     ORDER BY s.updated_at DESC",
                    $params,
                    $limit
                );

            case 'contracts_status':
                return $this->groupedReport('Contrats par statut', 'Lecture du graphique contrats', 'contracts ct', 'ct.status', 'ct.deleted_at IS NULL' . $contractScope, $params, $limit);

            case 'leaves_status':
                return $this->groupedReport('Conges par statut', 'Lecture du graphique conges', 'leave_requests lr', 'lr.status', 'lr.deleted_at IS NULL' . $leaveScope, $params, $limit);

            case 'companies_status':
                return $this->groupedReport('Entreprises par statut', 'Lecture du portefeuille SaaS', 'companies c', 'c.status', 'c.deleted_at IS NULL' . $companyWhere, $params, $limit);

            default:
                if (strncmp($type, 'notification_', strlen('notification_')) === 0) {
                    $notificationId = (int) substr($type, strlen('notification_'));
                    return $this->singleNotificationReport($notificationId, $notificationScope, $params);
                }
        }

        return null;
    }

    private function reportFromSql(string $title, string $subtitle, array $columns, string $sql, array $params, int $limit): array
    {
        $rows = Database::query($sql . ' LIMIT ' . max(1, $limit), $params)->fetchAll();

        return $this->normalizeReport($title, $subtitle, $columns, $rows);
    }

    private function contractsReport(string $title, string $subtitle, string $condition, string $scope, array $params, int $limit): array
    {
        return $this->reportFromSql(
            $title,
            $subtitle,
            ['Contrat', 'Employe', 'Entreprise', 'Type', 'Debut', 'Fin', 'Salaire', 'Statut'],
            "SELECT ct.contract_number AS `Contrat`,
                    TRIM(CONCAT(e.last_name, ' ', COALESCE(e.middle_name, ''), ' ', e.first_name)) AS `Employe`,
                    c.name AS `Entreprise`, ct.contract_type AS `Type`, ct.start_date AS `Debut`,
                    COALESCE(ct.end_date, 'Indeterminee') AS `Fin`,
                    CONCAT(FORMAT(ct.base_salary, 2), ' ', ct.currency) AS `Salaire`, ct.status AS `Statut`
             FROM contracts ct
             INNER JOIN employees e ON e.id = ct.employee_id
             INNER JOIN companies c ON c.id = ct.company_id
             WHERE ct.deleted_at IS NULL AND {$condition}{$scope}
             ORDER BY ct.end_date ASC, ct.created_at DESC",
            $params,
            $limit
        );
    }

    private function leavesReport(string $title, string $subtitle, string $condition, string $scope, array $params, int $limit): array
    {
        return $this->reportFromSql(
            $title,
            $subtitle,
            ['Employe', 'Type', 'Debut', 'Fin', 'Jours', 'Manager', 'RH', 'Statut'],
            "SELECT TRIM(CONCAT(e.last_name, ' ', COALESCE(e.middle_name, ''), ' ', e.first_name)) AS `Employe`,
                    lt.name AS `Type`, lr.start_date AS `Debut`, lr.end_date AS `Fin`, lr.total_days AS `Jours`,
                    lr.manager_status AS `Manager`, lr.hr_status AS `RH`, lr.status AS `Statut`
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.deleted_at IS NULL AND {$condition}{$scope}
             ORDER BY lr.created_at DESC",
            $params,
            $limit
        );
    }

    private function attendanceReport(string $title, string $subtitle, string $condition, string $scope, array $params, int $limit): array
    {
        return $this->reportFromSql(
            $title,
            $subtitle,
            ['Matricule', 'Employe', 'Entreprise', 'Statut', 'Arrivee', 'Depart', 'Note'],
            "SELECT e.employee_number AS `Matricule`,
                    TRIM(CONCAT(e.last_name, ' ', COALESCE(e.middle_name, ''), ' ', e.first_name)) AS `Employe`,
                    c.name AS `Entreprise`, a.status AS `Statut`, COALESCE(a.check_in, '-') AS `Arrivee`,
                    COALESCE(a.check_out, '-') AS `Depart`, COALESCE(a.notes, '-') AS `Note`
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             INNER JOIN companies c ON c.id = a.company_id
             WHERE a.deleted_at IS NULL AND a.attendance_date = CURDATE() AND {$condition}{$scope}
             ORDER BY a.check_in IS NULL, a.check_in ASC, e.last_name ASC",
            $params,
            $limit
        );
    }

    private function notificationsReport(string $title, string $subtitle, string $scope, array $params, int $limit): array
    {
        return $this->reportFromSql(
            $title,
            $subtitle,
            ['Titre', 'Message', 'Type', 'Lu le', 'Cree le'],
            "SELECT n.title AS `Titre`, n.message AS `Message`, n.type AS `Type`, COALESCE(n.read_at, '-') AS `Lu le`, n.created_at AS `Cree le`
             FROM notifications n
             WHERE n.deleted_at IS NULL{$scope}
             ORDER BY n.created_at DESC",
            $params,
            $limit
        );
    }

    private function groupedReport(string $title, string $subtitle, string $from, string $field, string $where, array $params, int $limit): array
    {
        $rows = Database::query(
            "SELECT {$field} AS `Categorie`, COUNT(*) AS `Total`
             FROM {$from}
             WHERE {$where}
             GROUP BY {$field}
             ORDER BY COUNT(*) DESC
             LIMIT " . max(1, $limit),
            $params
        )->fetchAll();

        $total = max(1, array_sum(array_map(static fn (array $row): int => (int) ($row['Total'] ?? 0), $rows)));
        foreach ($rows as &$row) {
            $row['Part'] = number_format(((int) ($row['Total'] ?? 0) / $total) * 100, 1, ',', ' ') . '%';
        }
        unset($row);

        return $this->normalizeReport($title, $subtitle, ['Categorie', 'Total', 'Part'], $rows);
    }

    private function prioritiesReport(?int $companyId, bool $isSuperAdmin): array
    {
        $params = [];
        $contractScope = '';
        $leaveScope = '';
        $attendanceScope = '';

        if (!$isSuperAdmin) {
            $params['company_id'] = $companyId;
            $contractScope = ' AND company_id = :company_id';
            $leaveScope = ' AND company_id = :company_id';
            $attendanceScope = ' AND company_id = :company_id';
        }

        $contracts = (int) Database::query('SELECT COUNT(*) AS total FROM contracts WHERE deleted_at IS NULL AND status = "active" AND end_date IS NOT NULL AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)' . $contractScope, $params)->fetch()['total'];
        $leaves = (int) Database::query('SELECT COUNT(*) AS total FROM leave_requests WHERE deleted_at IS NULL AND status = "pending"' . $leaveScope, $params)->fetch()['total'];
        $absences = (int) Database::query('SELECT COUNT(*) AS total FROM attendance WHERE deleted_at IS NULL AND attendance_date = CURDATE() AND status = "absent"' . $attendanceScope, $params)->fetch()['total'];

        return $this->normalizeReport('Priorites RH', 'Points qui meritent une action', ['Priorite', 'Volume', 'Action conseillee'], [
            ['Priorite' => 'Contrats a risque', 'Volume' => $contracts, 'Action conseillee' => 'Planifier renouvellement ou cloture'],
            ['Priorite' => 'Conges en attente', 'Volume' => $leaves, 'Action conseillee' => 'Valider ou rejeter les demandes'],
            ['Priorite' => 'Absences du jour', 'Volume' => $absences, 'Action conseillee' => 'Verifier les justificatifs'],
        ]);
    }

    private function singleNotificationReport(int $id, string $scope, array $params): array
    {
        if ($id <= 0) {
            return $this->notificationsReport('Notifications recentes', 'Messages et alertes operationnels', $scope, $params, 80);
        }

        $params['id'] = $id;

        $report = $this->reportFromSql(
            'Detail notification',
            'Message selectionne',
            ['Titre', 'Message', 'Type', 'Lu le', 'Cree le'],
            "SELECT n.title AS `Titre`, n.message AS `Message`, n.type AS `Type`, COALESCE(n.read_at, '-') AS `Lu le`, n.created_at AS `Cree le`
             FROM notifications n
             WHERE n.deleted_at IS NULL AND n.id = :id{$scope}
             ORDER BY n.created_at DESC",
            $params,
            1
        );

        if ($report['rows'] === []) {
            unset($params['id']);
            return $this->notificationsReport('Notifications recentes', 'Message selectionne indisponible, liste recente affichee', $scope, $params, 80);
        }

        return $report;
    }

    private function normalizeReport(string $title, string $subtitle, array $columns, array $rows): array
    {
        $rows = array_map(static function (array $row): array {
            $clean = [];
            foreach ($row as $key => $value) {
                $clean[(string) $key] = $value === null || $value === '' ? '-' : (string) $value;
            }
            return $clean;
        }, $rows);

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'generated_at' => date('d/m/Y H:i'),
            'summary' => [
                ['label' => 'Lignes', 'value' => (string) count($rows)],
                ['label' => 'Colonnes', 'value' => (string) count($columns)],
                ['label' => 'Generation', 'value' => date('d/m/Y H:i')],
            ],
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    private function excelTable(array $report): string
    {
        $span = max(1, count($report['columns']) + 1);
        $html = '<!doctype html><html><head><meta charset="utf-8">';
        $html .= '<style>
            body{font-family:Arial,Helvetica,sans-serif;color:#1f2937;background:#fff}
            table{border-collapse:collapse;width:100%}
            .title{background:#172554;color:#fff;font-size:22px;font-weight:700;padding:18px 16px;text-align:left}
            .subtitle{background:#eff6ff;color:#334155;font-size:12px;padding:10px 16px;text-align:left}
            .summary td{background:#f8fafc;border:1px solid #cbd5e1;color:#334155;font-size:12px;padding:9px 12px}
            .summary strong{display:block;color:#0f172a;font-size:16px}
            th{background:#dbeafe;border:1px solid #93c5fd;color:#1e3a8a;font-size:11px;font-weight:700;padding:9px;text-align:left;text-transform:uppercase}
            td{border:1px solid #d7dee8;font-size:12px;padding:8px;vertical-align:top}
            tr:nth-child(even) td{background:#f8fafc}
            .idx{background:#eef2ff;color:#475569;font-weight:700;text-align:center;width:36px}
            .footer{color:#64748b;font-size:11px;padding:12px 0}
        </style></head><body>';
        $html .= '<table>';
        $html .= '<tr><th class="title" colspan="' . $span . '">' . e($report['title']) . '</th></tr>';
        $html .= '<tr><td class="subtitle" colspan="' . $span . '">' . e($report['subtitle']) . ' · Genere le ' . e($report['generated_at']) . ' · ELLIOT-HR</td></tr>';
        $html .= '<tr class="summary">';
        foreach ($report['summary'] as $item) {
            $html .= '<td colspan="' . max(1, (int) floor($span / max(1, count($report['summary'])))) . '"><strong>' . e((string) ($item['value'] ?? '-')) . '</strong>' . e((string) ($item['label'] ?? '')) . '</td>';
        }
        $html .= '</tr><tr><td colspan="' . $span . '"></td></tr>';
        $html .= '<tr><th class="idx">#</th>';
        foreach ($report['columns'] as $column) {
            $html .= '<th>' . e($column) . '</th>';
        }
        $html .= '</tr>';

        foreach ($report['rows'] as $index => $row) {
            $html .= '<tr><td class="idx">' . e((string) ($index + 1)) . '</td>';
            foreach ($report['columns'] as $column) {
                $html .= '<td>' . e((string) ($row[$column] ?? '-')) . '</td>';
            }
            $html .= '</tr>';
        }

        if ($report['rows'] === []) {
            $html .= '<tr><td colspan="' . $span . '">Aucune donnee disponible.</td></tr>';
        }

        $html .= '<tr><td class="footer" colspan="' . $span . '">Document genere automatiquement par ELLIOT-HR.</td></tr>';
        $html .= '</table></body></html>';

        return $html;
    }

    private function reportPdf(array $report): string
    {
        $columns = array_slice($report['columns'], 0, 5);
        $rows = $report['rows'];
        $pages = [];
        $page = 1;
        $rowIndex = 0;
        $rowsPerPage = 15;

        do {
            $slice = array_slice($rows, $rowIndex, $rowsPerPage);
            $pages[] = $this->reportPdfPage($report, $columns, $slice, $rowIndex, $page, 0);
            $rowIndex += $rowsPerPage;
            $page++;
        } while ($rowIndex < count($rows));

        if ($rows === []) {
            $pages = [$this->reportPdfPage($report, $columns, [], 0, 1, 1)];
        }

        $totalPages = count($pages);
        foreach ($pages as $index => $content) {
            $pages[$index] = str_replace('{TOTAL_PAGES}', (string) $totalPages, $content);
        }

        return $this->buildPdf($pages);
    }

    private function reportPdfPage(array $report, array $columns, array $rows, int $offset, int $page, int $totalPages): string
    {
        $content = '';
        $content .= "q 0.09 0.16 0.33 rg 0 760 595 82 re f Q\n";
        $content .= $this->pdfTextAt('ELLIOT-HR', 42, 808, 10, 'F2', '1 1 1');
        $content .= $this->pdfTextAt(strtoupper($report['title']), 42, 786, 18, 'F2', '1 1 1');
        $content .= $this->pdfTextAt($report['subtitle'], 42, 770, 9, 'F1', '0.86 0.92 1');
        $content .= $this->pdfTextAt('Genere le ' . $report['generated_at'], 430, 808, 9, 'F1', '1 1 1');

        $summaryX = 42;
        foreach (array_slice($report['summary'], 0, 3) as $item) {
            $content .= "q 0.97 0.98 1 rg {$summaryX} 704 150 42 re f Q\n";
            $content .= "q 0.78 0.84 0.92 RG {$summaryX} 704 150 42 re S Q\n";
            $content .= $this->pdfTextAt((string) ($item['value'] ?? '-'), $summaryX + 12, 728, 13, 'F2', '0.06 0.09 0.16');
            $content .= $this->pdfTextAt((string) ($item['label'] ?? ''), $summaryX + 12, 713, 8, 'F1', '0.39 0.45 0.55');
            $summaryX += 163;
        }

        $tableX = 42;
        $tableY = 672;
        $indexWidth = 28;
        $usableWidth = 511;
        $columnWidth = (int) floor(($usableWidth - $indexWidth) / max(1, count($columns)));
        $rowHeight = 30;

        $content .= "q 0.86 0.92 1 rg {$tableX} " . ($tableY - 18) . " {$usableWidth} 24 re f Q\n";
        $content .= "q 0.58 0.70 0.86 RG {$tableX} " . ($tableY - 18) . " {$usableWidth} 24 re S Q\n";
        $content .= $this->pdfTextAt('#', $tableX + 9, $tableY - 3, 8, 'F2', '0.12 0.23 0.45');
        $x = $tableX + $indexWidth;
        foreach ($columns as $column) {
            $content .= $this->pdfTextAt(strtoupper($column), $x + 5, $tableY - 3, 7, 'F2', '0.12 0.23 0.45');
            $x += $columnWidth;
        }

        $y = $tableY - 48;
        if ($rows === []) {
            $content .= $this->pdfTextAt('Aucune donnee disponible pour ce rapport.', $tableX + 10, $y, 10, 'F1', '0.39 0.45 0.55');
        }

        foreach ($rows as $index => $row) {
            $fill = $index % 2 === 0 ? '1 1 1' : '0.98 0.99 1';
            $content .= "q {$fill} rg {$tableX} " . ($y - 13) . " {$usableWidth} {$rowHeight} re f Q\n";
            $content .= "q 0.88 0.91 0.95 RG {$tableX} " . ($y - 13) . " {$usableWidth} {$rowHeight} re S Q\n";
            $content .= $this->pdfTextAt((string) ($offset + $index + 1), $tableX + 8, $y + 4, 8, 'F2', '0.28 0.33 0.41');

            $x = $tableX + $indexWidth;
            foreach ($columns as $column) {
                $value = (string) ($row[$column] ?? '-');
                $content .= $this->pdfTextAt($this->shortText($value, 28), $x + 5, $y + 4, 8, 'F1', '0.20 0.25 0.33');
                if (strlen($value) > 28) {
                    $content .= $this->pdfTextAt($this->shortText(substr($value, 28), 28), $x + 5, $y - 8, 7, 'F1', '0.39 0.45 0.55');
                }
                $x += $columnWidth;
            }
            $y -= $rowHeight;
        }

        if (count($report['columns']) > count($columns)) {
            $content .= $this->pdfTextAt('Note: le PDF affiche les 5 premieres colonnes. L export Excel contient toutes les colonnes.', 42, 54, 8, 'F1', '0.39 0.45 0.55');
        }

        $content .= $this->pdfTextAt('Page ' . $page . ' / ' . ($totalPages ?: '{TOTAL_PAGES}'), 470, 32, 8, 'F1', '0.39 0.45 0.55');
        $content .= $this->pdfTextAt('Document genere automatiquement par ELLIOT-HR', 42, 32, 8, 'F1', '0.39 0.45 0.55');

        return $content;
    }

    private function buildPdf(array $pages): string
    {
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        ];
        $kids = [];
        $objectNumber = 3;
        $fontObject = 3 + (count($pages) * 2);
        $boldFontObject = $fontObject + 1;

        foreach ($pages as $index => $content) {
            $pageObject = $objectNumber++;
            $contentObject = $objectNumber++;
            $kids[] = $pageObject . ' 0 R';
            $objects[] = "{$pageObject} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontObject} 0 R /F2 {$boldFontObject} 0 R >> >> /Contents {$contentObject} 0 R >>\nendobj\n";
            $objects[] = "{$contentObject} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";
        }

        array_splice($objects, 1, 0, "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($pages) . " >>\nendobj\n");
        $objects[] = "{$fontObject} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $objects[] = "{$boldFontObject} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";

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

    private function pdfTextAt(string $text, int $x, int $y, int $size, string $font, string $color): string
    {
        return "BT\n{$color} rg\n/{$font} {$size} Tf\n{$x} {$y} Td\n(" . $this->pdfText($text) . ") Tj\nET\n";
    }

    private function shortText(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

        if (strlen($text) <= $limit) {
            return $text;
        }

        return substr($text, 0, max(1, $limit - 3)) . '...';
    }

    private function pdfText(string $text): string
    {
        $text = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text) ?: $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function scope(): array
    {
        $user = Auth::user() ?? [];
        $isSuperAdmin = ($user['role_slug'] ?? '') === 'super-admin';
        $companyId = isset($user['company_id']) ? (int) $user['company_id'] : null;

        return [$companyId, $isSuperAdmin];
    }

    private function reportType(): string
    {
        return preg_replace('/[^a-z0-9_]+/i', '', (string) ($_GET['type'] ?? '')) ?: 'employees';
    }
}
