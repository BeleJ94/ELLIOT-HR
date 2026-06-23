-- RDC fiscal and social declarations generated from payroll lines
-- Compatible MySQL 5.7

CREATE TABLE IF NOT EXISTS declarations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    payroll_period_id INT UNSIGNED NOT NULL,
    reference VARCHAR(80) NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    ipr_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cnss_employee_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cnss_employer_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    inpp_employee_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    inpp_employer_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    onem_employee_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    onem_employer_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    salary_withheld_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    employer_charges_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_due DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    payment_status ENUM('pending', 'paid', 'late') NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    proof_path VARCHAR(255) NULL,
    proof_name VARCHAR(180) NULL,
    proof_mime VARCHAR(120) NULL,
    generated_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_declarations_period (payroll_period_id),
    KEY idx_declarations_company_period (company_id, period_year, period_month),
    KEY idx_declarations_due (due_date, payment_status),
    CONSTRAINT fk_declarations_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_declarations_period
        FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (name, slug, module, description)
SELECT 'Gerer les declarations', 'declarations.manage', 'declarations', 'Declarations fiscales et sociales'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE slug = 'declarations.manage'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'declarations.manage'
LEFT JOIN role_permissions existing
    ON existing.role_id = roles.id
    AND existing.permission_id = permissions.id
WHERE roles.slug IN ('super-admin', 'admin-rh')
AND existing.role_id IS NULL;
