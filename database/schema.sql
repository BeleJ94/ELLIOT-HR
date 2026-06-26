-- ELLIOT-HR database schema
-- Compatible MySQL 5.7

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS attendance_changes;
DROP TABLE IF EXISTS attendance_days;
DROP TABLE IF EXISTS declarations;
DROP TABLE IF EXISTS payslips;
DROP TABLE IF EXISTS payroll_items;
DROP TABLE IF EXISTS payroll_periods;
DROP TABLE IF EXISTS leave_requests;
DROP TABLE IF EXISTS leave_types;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS contracts;
DROP TABLE IF EXISTS employee_documents;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS social_contribution_settings;
DROP TABLE IF EXISTS tax_settings;
DROP TABLE IF EXISTS positions;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS branches;
DROP TABLE IF EXISTS subscriptions;
DROP TABLE IF EXISTS subscription_plans;
DROP TABLE IF EXISTS companies;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE subscription_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(60) NOT NULL,
    description TEXT NULL,
    max_companies INT UNSIGNED NULL,
    max_employees INT UNSIGNED NULL,
    monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_subscription_plans_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_plan_id INT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    legal_name VARCHAR(190) NULL,
    registration_number VARCHAR(100) NULL,
    national_id VARCHAR(100) NULL,
    tax_number VARCHAR(100) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    address TEXT NULL,
    city VARCHAR(120) NULL,
    province VARCHAR(120) NULL,
    country VARCHAR(120) NOT NULL DEFAULT 'RDC',
    industry VARCHAR(160) NULL,
    status ENUM('active', 'suspended', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_companies_plan (subscription_plan_id),
    CONSTRAINT fk_companies_subscription_plan
        FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    subscription_plan_id INT UNSIGNED NOT NULL,
    status ENUM('trial', 'active', 'past_due', 'cancelled', 'expired') NOT NULL DEFAULT 'trial',
    starts_at DATE NOT NULL,
    ends_at DATE NULL,
    trial_ends_at DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_subscriptions_company (company_id),
    KEY idx_subscriptions_plan (subscription_plan_id),
    CONSTRAINT fk_subscriptions_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_subscriptions_plan
        FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE branches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    code VARCHAR(60) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    address TEXT NULL,
    city VARCHAR(120) NULL,
    is_head_office TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_branches_company (company_id),
    UNIQUE KEY uq_branches_company_code (company_id, code),
    CONSTRAINT fk_branches_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    manager_id INT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    code VARCHAR(60) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_departments_company (company_id),
    KEY idx_departments_branch (branch_id),
    KEY idx_departments_manager (manager_id),
    UNIQUE KEY uq_departments_company_code (company_id, code),
    CONSTRAINT fk_departments_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_departments_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE positions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    code VARCHAR(60) NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_positions_company (company_id),
    KEY idx_positions_department (department_id),
    UNIQUE KEY uq_positions_company_code (company_id, code),
    CONSTRAINT fk_positions_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_positions_department
        FOREIGN KEY (department_id) REFERENCES departments(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    description TEXT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_roles_company (company_id),
    UNIQUE KEY uq_roles_company_slug (company_id, slug),
    CONSTRAINT fk_roles_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    module VARCHAR(80) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_permissions_slug (slug),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_role_permissions (role_id, permission_id),
    KEY idx_role_permissions_permission (permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    role_id INT UNSIGNED NULL,
    employee_id INT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(60) NULL,
    status ENUM('active', 'inactive', 'blocked') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_company (company_id),
    KEY idx_users_role (role_id),
    KEY idx_users_employee (employee_id),
    CONSTRAINT fk_users_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    position_id INT UNSIGNED NULL,
    manager_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    employee_number VARCHAR(60) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('male', 'female', 'other') NULL,
    birth_date DATE NULL,
    birth_place VARCHAR(160) NULL,
    marital_status ENUM('single', 'married', 'divorced', 'widowed') NULL,
    hire_date DATE NOT NULL,
    termination_date DATE NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    address TEXT NULL,
    emergency_contact_name VARCHAR(160) NULL,
    emergency_contact_phone VARCHAR(60) NULL,
    photo_path VARCHAR(255) NULL,
    employment_status ENUM('active', 'on_leave', 'suspended', 'terminated') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_employees_company_number (company_id, employee_number),
    KEY idx_employees_company (company_id),
    KEY idx_employees_branch (branch_id),
    KEY idx_employees_department (department_id),
    KEY idx_employees_position (position_id),
    KEY idx_employees_manager (manager_id),
    KEY idx_employees_user (user_id),
    CONSTRAINT fk_employees_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_employees_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_employees_department
        FOREIGN KEY (department_id) REFERENCES departments(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_employees_position
        FOREIGN KEY (position_id) REFERENCES positions(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_employees_manager
        FOREIGN KEY (manager_id) REFERENCES employees(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_employees_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD CONSTRAINT fk_users_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE departments
    ADD CONSTRAINT fk_departments_manager
        FOREIGN KEY (manager_id) REFERENCES employees(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE employee_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    title VARCHAR(190) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(190) NULL,
    mime_type VARCHAR(100) NULL,
    expires_at DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_employee_documents_company (company_id),
    KEY idx_employee_documents_employee (employee_id),
    CONSTRAINT fk_employee_documents_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_employee_documents_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    contract_number VARCHAR(80) NOT NULL,
    contract_type ENUM('cdi', 'cdd', 'consultant', 'internship', 'temporary') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    base_salary DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    status ENUM('draft', 'active', 'expired', 'terminated') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_contracts_company_number (company_id, contract_number),
    KEY idx_contracts_employee (employee_id),
    KEY idx_contracts_company (company_id),
    CONSTRAINT fk_contracts_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_contracts_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    check_in TIME NULL,
    check_out TIME NULL,
    status ENUM('present', 'absent', 'late', 'half_day', 'holiday', 'leave') NOT NULL DEFAULT 'present',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_attendance_employee_date (employee_id, attendance_date),
    KEY idx_attendance_company_date (company_id, attendance_date),
    CONSTRAINT fk_attendance_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_days (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('open', 'closed', 'locked') NOT NULL DEFAULT 'open',
    closed_by INT UNSIGNED NULL,
    closed_at DATETIME NULL,
    locked_by INT UNSIGNED NULL,
    locked_at DATETIME NULL,
    reopened_by INT UNSIGNED NULL,
    reopened_at DATETIME NULL,
    status_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_attendance_days_company_date (company_id, attendance_date),
    KEY idx_attendance_days_status (company_id, status, attendance_date),
    CONSTRAINT fk_attendance_days_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_days_closed_by FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_days_locked_by FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_days_reopened_by FOREIGN KEY (reopened_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendance_changes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    attendance_day_id INT UNSIGNED NULL,
    attendance_id INT UNSIGNED NULL,
    employee_id INT UNSIGNED NULL,
    attendance_date DATE NOT NULL,
    action VARCHAR(80) NOT NULL,
    old_values TEXT NULL,
    new_values TEXT NULL,
    reason TEXT NULL,
    changed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_attendance_changes_company_date (company_id, attendance_date),
    KEY idx_attendance_changes_day (attendance_day_id),
    KEY idx_attendance_changes_employee (employee_id),
    CONSTRAINT fk_attendance_changes_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_changes_day FOREIGN KEY (attendance_day_id) REFERENCES attendance_days(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_changes_attendance FOREIGN KEY (attendance_id) REFERENCES attendance(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_changes_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_changes_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    code VARCHAR(60) NOT NULL,
    paid TINYINT(1) NOT NULL DEFAULT 1,
    annual_days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_leave_types_company_code (company_id, code),
    KEY idx_leave_types_company (company_id),
    CONSTRAINT fk_leave_types_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    leave_type_id INT UNSIGNED NOT NULL,
    approved_by INT UNSIGNED NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    reason TEXT NULL,
    manager_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    hr_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    manager_approved_by INT UNSIGNED NULL,
    hr_approved_by INT UNSIGNED NULL,
    manager_approved_at DATETIME NULL,
    hr_approved_at DATETIME NULL,
    rejection_reason TEXT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_leave_requests_company (company_id),
    KEY idx_leave_requests_employee (employee_id),
    KEY idx_leave_requests_type (leave_type_id),
    KEY idx_leave_requests_approver (approved_by),
    KEY idx_leave_requests_manager_approver (manager_approved_by),
    KEY idx_leave_requests_hr_approver (hr_approved_by),
    CONSTRAINT fk_leave_requests_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_leave_requests_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_leave_requests_type
        FOREIGN KEY (leave_type_id) REFERENCES leave_types(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_leave_requests_approver
        FOREIGN KEY (approved_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leave_requests_manager_approver
        FOREIGN KEY (manager_approved_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leave_requests_hr_approver
        FOREIGN KEY (hr_approved_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('open', 'processing', 'closed', 'paid') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_payroll_periods_company_month (company_id, period_year, period_month),
    KEY idx_payroll_periods_company (company_id),
    CONSTRAINT fk_payroll_periods_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    code VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,
    type ENUM('earning', 'deduction', 'tax', 'contribution') NOT NULL,
    calculation_type ENUM('fixed', 'percentage') NOT NULL DEFAULT 'fixed',
    default_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    default_rate DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    taxable TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_payroll_items_company_code (company_id, code),
    KEY idx_payroll_items_company (company_id),
    CONSTRAINT fk_payroll_items_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payslips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    payroll_period_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    gross_salary DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_earnings DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_deductions DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    net_salary DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    status ENUM('draft', 'validated', 'paid', 'cancelled') NOT NULL DEFAULT 'draft',
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_payslips_period_employee (payroll_period_id, employee_id),
    KEY idx_payslips_company (company_id),
    KEY idx_payslips_employee (employee_id),
    CONSTRAINT fk_payslips_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payslips_period
        FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_payslips_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payslip_lines (
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

CREATE TABLE tax_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    tax_code VARCHAR(60) NOT NULL,
    rate DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    threshold_min DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    threshold_max DECIMAL(14,2) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_tax_settings_company_code_min (company_id, tax_code, threshold_min),
    KEY idx_tax_settings_company (company_id),
    CONSTRAINT fk_tax_settings_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE social_contribution_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    contribution_code VARCHAR(60) NOT NULL,
    employee_rate DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    employer_rate DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    ceiling_amount DECIMAL(14,2) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_social_settings_company_code (company_id, contribution_code),
    KEY idx_social_settings_company (company_id),
    CONSTRAINT fk_social_settings_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE declarations (
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

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') NOT NULL DEFAULT 'info',
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_notifications_company (company_id),
    KEY idx_notifications_user (user_id),
    CONSTRAINT fk_notifications_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(120) NULL,
    entity_id INT UNSIGNED NULL,
    old_values TEXT NULL,
    new_values TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_audit_logs_company (company_id),
    KEY idx_audit_logs_user (user_id),
    KEY idx_audit_logs_entity (entity_type, entity_id),
    CONSTRAINT fk_audit_logs_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Minimal seed data

INSERT INTO subscription_plans
    (id, name, code, description, max_companies, max_employees, monthly_price, currency, is_active)
VALUES
    (1, 'Demo', 'demo', 'Plan de demonstration ELLIOT-HR', 1, 50, 0.00, 'USD', 1);

INSERT INTO companies
    (id, subscription_plan_id, name, legal_name, registration_number, national_id, tax_number, email, phone, city, province, country, industry, status)
VALUES
    (1, 1, 'Entreprise Demo', 'Entreprise Demo SARL', 'DEMO-RCCM-001', 'DEMO-IDNAT-001', 'DEMO-TAX-001', 'rh@demo.test', '+243000000000', 'Lubumbashi', 'Haut-Katanga', 'RDC', 'Services RH', 'active');

INSERT INTO subscriptions
    (id, company_id, subscription_plan_id, status, starts_at, trial_ends_at)
VALUES
    (1, 1, 1, 'trial', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY));

INSERT INTO branches
    (id, company_id, name, code, city, is_head_office)
VALUES
    (1, 1, 'Siege Demo', 'HQ', 'Lubumbashi', 1);

INSERT INTO departments
    (id, company_id, branch_id, name, code)
VALUES
    (1, 1, 1, 'Ressources Humaines', 'RH');

INSERT INTO positions
    (id, company_id, department_id, title, code)
VALUES
    (1, 1, 1, 'Administrateur RH', 'ADMIN_RH');

INSERT INTO roles
    (id, company_id, name, slug, description, is_system)
VALUES
    (1, NULL, 'Super Admin', 'super-admin', 'Acces global a la plateforme', 1),
    (2, 1, 'Admin RH', 'admin-rh', 'Administration RH de l entreprise', 1),
    (3, 1, 'Manager', 'manager', 'Gestion d equipe et validations', 1),
    (4, 1, 'Employe', 'employe', 'Acces employe standard', 1);

INSERT INTO permissions
    (id, name, slug, module, description)
VALUES
    (1, 'Gerer la plateforme', 'platform.manage', 'platform', 'Administration globale SaaS'),
    (2, 'Gerer les entreprises', 'companies.manage', 'companies', 'Creation et gestion des entreprises'),
    (3, 'Gerer les employes', 'employees.manage', 'employees', 'Creation et gestion des employes'),
    (4, 'Gerer les contrats', 'contracts.manage', 'contracts', 'Creation et suivi des contrats'),
    (5, 'Gerer les presences', 'attendance.manage', 'attendance', 'Suivi des presences'),
    (6, 'Gerer les conges', 'leaves.manage', 'leaves', 'Validation et suivi des conges'),
    (7, 'Gerer la paie', 'payroll.manage', 'payroll', 'Preparation et validation de la paie'),
    (8, 'Consulter son espace', 'self.view', 'self-service', 'Acces au portail personnel'),
    (9, 'Gerer les declarations', 'declarations.manage', 'declarations', 'Declarations fiscales et sociales'),
    (10, 'Gerer les formations', 'trainings.manage', 'trainings', 'Catalogue, sessions et presences de formation');

INSERT INTO role_permissions
    (role_id, permission_id)
VALUES
    (1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9),
    (1, 10),
    (2, 3), (2, 4), (2, 5), (2, 6), (2, 7), (2, 8), (2, 9), (2, 10),
    (3, 3), (3, 5), (3, 6), (3, 8), (3, 10),
    (4, 8);

INSERT INTO users
    (id, company_id, role_id, first_name, last_name, email, password, phone, status)
VALUES
    (1, NULL, 1, 'Super', 'Admin', 'superadmin@elliot-hr.test', '$2y$10$lT5Vp5bKlLQz0OOiq8OKR.RI5XGFHJ7P6h60TP4RBZiiFUsWq4Tce', NULL, 'active'),
    (2, 1, 2, 'Admin', 'RH Demo', 'admin@demo.test', '$2y$10$lT5Vp5bKlLQz0OOiq8OKR.RI5XGFHJ7P6h60TP4RBZiiFUsWq4Tce', '+243000000001', 'active');

INSERT INTO employees
    (id, company_id, branch_id, department_id, position_id, user_id, employee_number, first_name, middle_name, last_name, gender, hire_date, email, phone, employment_status)
VALUES
    (1, 1, 1, 1, 1, 2, 'EMP-0001', 'Admin', NULL, 'RH Demo', 'other', CURDATE(), 'admin@demo.test', '+243000000001', 'active');

UPDATE users SET employee_id = 1 WHERE id = 2;

INSERT INTO leave_types
    (id, company_id, name, code, paid, annual_days)
VALUES
    (1, 1, 'Conge annuel', 'ANNUAL', 1, 26.00),
    (2, 1, 'Conge maladie', 'SICK', 1, 0.00),
    (3, 1, 'Conge maternite', 'MATERNITY', 1, 0.00),
    (4, 1, 'Conge paternite', 'PATERNITY', 1, 0.00),
    (5, 1, 'Conge exceptionnel', 'EXCEPTIONAL', 1, 0.00),
    (6, 1, 'Absence autorisee', 'AUTHORIZED_ABSENCE', 1, 0.00),
    (7, 1, 'Absence non autorisee', 'UNAUTHORIZED_ABSENCE', 0, 0.00);

INSERT INTO payroll_items
    (id, company_id, code, name, type, calculation_type, default_amount, default_rate, taxable)
VALUES
    (1, 1, 'BASE', 'Salaire de base', 'earning', 'fixed', 0.00, 0.0000, 1),
    (2, 1, 'IPR', 'Impot professionnel sur remuneration', 'tax', 'percentage', 0.00, 0.0000, 0),
    (3, 1, 'CNSS', 'Cotisation sociale', 'contribution', 'percentage', 0.00, 0.0000, 0),
    (4, 1, 'BONUS', 'Prime', 'earning', 'fixed', 0.00, 0.0000, 1),
    (5, 1, 'INDEMNITY', 'Indemnite', 'earning', 'fixed', 0.00, 0.0000, 0),
    (6, 1, 'ADVANCE', 'Avance sur salaire', 'deduction', 'fixed', 0.00, 0.0000, 0),
    (7, 1, 'LOAN', 'Remboursement pret', 'deduction', 'fixed', 0.00, 0.0000, 0);

INSERT INTO tax_settings
    (id, company_id, name, tax_code, rate, threshold_min, threshold_max, is_active)
VALUES
    (1, 1, 'IPR Demo', 'IPR', 0.0000, 0.00, NULL, 1);

INSERT INTO social_contribution_settings
    (id, company_id, name, contribution_code, employee_rate, employer_rate, ceiling_amount, is_active)
VALUES
    (1, 1, 'CNSS Demo', 'CNSS', 0.0000, 0.0000, NULL, 1),
    (2, 1, 'INPP Demo', 'INPP', 0.0000, 0.0000, NULL, 1),
    (3, 1, 'ONEM Demo', 'ONEM', 0.0000, 0.0000, NULL, 1);

INSERT INTO notifications
    (company_id, user_id, title, message, type)
VALUES
    (1, 2, 'Bienvenue sur ELLIOT-HR', 'Votre environnement demo est pret.', 'success');
