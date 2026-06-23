-- Payroll detail lines and configurable RDC defaults
-- Compatible MySQL 5.7

CREATE TABLE IF NOT EXISTS payslip_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payslip_id INT UNSIGNED NOT NULL,
    payroll_item_id INT UNSIGNED NULL,
    code VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,
    type ENUM('earning', 'deduction', 'tax', 'contribution', 'employer_contribution') NOT NULL,
    base_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    rate DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    taxable TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_payslip_lines_payslip (payslip_id),
    KEY idx_payslip_lines_item (payroll_item_id),
    CONSTRAINT fk_payslip_lines_payslip
        FOREIGN KEY (payslip_id) REFERENCES payslips(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payslip_lines_item
        FOREIGN KEY (payroll_item_id) REFERENCES payroll_items(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO payroll_items (company_id, code, name, type, calculation_type, default_amount, default_rate, taxable)
SELECT c.id, defaults.code, defaults.name, defaults.type, defaults.calculation_type, defaults.default_amount, defaults.default_rate, defaults.taxable
FROM companies c
JOIN (
    SELECT 'BASE' AS code, 'Salaire de base' AS name, 'earning' AS type, 'fixed' AS calculation_type, 0.00 AS default_amount, 0.0000 AS default_rate, 1 AS taxable
    UNION ALL SELECT 'IPR', 'Impot professionnel sur remuneration', 'tax', 'percentage', 0.00, 0.0000, 0
    UNION ALL SELECT 'CNSS', 'Cotisation sociale', 'contribution', 'percentage', 0.00, 0.0000, 0
    UNION ALL SELECT 'BONUS', 'Prime', 'earning', 'fixed', 0.00, 0.0000, 1
    UNION ALL SELECT 'INDEMNITY', 'Indemnite', 'earning', 'fixed', 0.00, 0.0000, 0
    UNION ALL SELECT 'ADVANCE', 'Avance sur salaire', 'deduction', 'fixed', 0.00, 0.0000, 0
    UNION ALL SELECT 'LOAN', 'Remboursement pret', 'deduction', 'fixed', 0.00, 0.0000, 0
) defaults
LEFT JOIN payroll_items existing
    ON existing.company_id = c.id
    AND existing.code = defaults.code
    AND existing.deleted_at IS NULL
WHERE c.deleted_at IS NULL
AND existing.id IS NULL;

INSERT INTO social_contribution_settings (company_id, name, contribution_code, employee_rate, employer_rate, ceiling_amount, is_active)
SELECT c.id, defaults.name, defaults.code, 0.0000, 0.0000, NULL, 1
FROM companies c
JOIN (
    SELECT 'CNSS' AS code, 'CNSS' AS name
    UNION ALL SELECT 'INPP', 'INPP'
    UNION ALL SELECT 'ONEM', 'ONEM'
) defaults
LEFT JOIN social_contribution_settings existing
    ON existing.company_id = c.id
    AND existing.contribution_code = defaults.code
    AND existing.deleted_at IS NULL
WHERE c.deleted_at IS NULL
AND existing.id IS NULL;
