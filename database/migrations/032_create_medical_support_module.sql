CREATE TABLE IF NOT EXISTS medical_coverage_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    default_coverage_rate DECIMAL(5,2) NOT NULL DEFAULT 80.00,
    annual_employee_ceiling DECIMAL(14,2) NULL,
    annual_dependent_ceiling DECIMAL(14,2) NULL,
    voucher_valid_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
    max_child_age TINYINT UNSIGNED NOT NULL DEFAULT 18,
    student_child_age TINYINT UNSIGNED NOT NULL DEFAULT 25,
    spouse_covered TINYINT(1) NOT NULL DEFAULT 1,
    children_covered TINYINT(1) NOT NULL DEFAULT 1,
    parents_covered TINYINT(1) NOT NULL DEFAULT 0,
    payroll_recovery_enabled TINYINT(1) NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_medical_settings_company (company_id),
    CONSTRAINT fk_medical_settings_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS medical_dependents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    relationship ENUM('spouse', 'child', 'father', 'mother', 'other') NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('male', 'female', 'other') NULL,
    birth_date DATE NULL,
    national_id VARCHAR(100) NULL,
    phone VARCHAR(60) NULL,
    document_type VARCHAR(120) NULL,
    document_reference VARCHAR(160) NULL,
    student_until DATE NULL,
    coverage_start DATE NOT NULL,
    coverage_end DATE NULL,
    status ENUM('pending', 'active', 'suspended', 'expired', 'rejected') NOT NULL DEFAULT 'active',
    verified_by INT UNSIGNED NULL,
    verified_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_medical_dependents_company (company_id),
    KEY idx_medical_dependents_employee (employee_id),
    KEY idx_medical_dependents_status (company_id, status),
    CONSTRAINT fk_medical_dependents_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_medical_dependents_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_medical_dependents_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS medical_providers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    provider_type ENUM('hospital', 'clinic', 'pharmacy', 'laboratory', 'other') NOT NULL DEFAULT 'clinic',
    contact_name VARCHAR(140) NULL,
    phone VARCHAR(60) NULL,
    email VARCHAR(190) NULL,
    address TEXT NULL,
    city VARCHAR(120) NULL,
    agreement_reference VARCHAR(120) NULL,
    default_coverage_rate DECIMAL(5,2) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_medical_providers_company (company_id),
    UNIQUE KEY uq_medical_provider_company_name (company_id, name),
    CONSTRAINT fk_medical_providers_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS medical_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    dependent_id INT UNSIGNED NULL,
    provider_id INT UNSIGNED NULL,
    request_number VARCHAR(80) NOT NULL,
    care_type ENUM('consultation', 'pharmacy', 'laboratory', 'hospitalization', 'maternity', 'dental', 'optical', 'emergency', 'other') NOT NULL DEFAULT 'consultation',
    requested_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    approved_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    covered_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    employee_share DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    coverage_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    medical_reason TEXT NULL,
    status ENUM('submitted', 'approved', 'rejected', 'voucher_issued', 'invoiced', 'validated', 'paid', 'cancelled', 'expired') NOT NULL DEFAULT 'submitted',
    requested_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    voucher_issued_at DATETIME NULL,
    voucher_expires_at DATE NULL,
    rejection_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_medical_requests_number (company_id, request_number),
    KEY idx_medical_requests_company (company_id),
    KEY idx_medical_requests_employee (employee_id),
    KEY idx_medical_requests_status (company_id, status),
    CONSTRAINT fk_medical_requests_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_medical_requests_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_medical_requests_dependent FOREIGN KEY (dependent_id) REFERENCES medical_dependents(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_medical_requests_provider FOREIGN KEY (provider_id) REFERENCES medical_providers(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_medical_requests_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_medical_requests_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS medical_claims (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    medical_request_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(120) NULL,
    invoice_date DATE NOT NULL,
    billed_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    accepted_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    rejected_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    covered_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    employee_share DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('received', 'validated', 'paid', 'rejected') NOT NULL DEFAULT 'received',
    paid_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_medical_claims_company (company_id),
    KEY idx_medical_claims_request (medical_request_id),
    CONSTRAINT fk_medical_claims_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_medical_claims_request FOREIGN KEY (medical_request_id) REFERENCES medical_requests(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (name, slug, module, description)
VALUES ('Gerer les prises en charge medicales', 'medical.manage', 'medical', 'Ayants droit, bons de prise en charge et factures medicales');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'medical.manage'
WHERE roles.slug IN ('super-admin', 'admin-rh', 'manager', 'employe');
