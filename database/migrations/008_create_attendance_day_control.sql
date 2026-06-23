CREATE TABLE IF NOT EXISTS attendance_days (
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
    CONSTRAINT fk_attendance_days_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_days_closed_by
        FOREIGN KEY (closed_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_days_locked_by
        FOREIGN KEY (locked_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_days_reopened_by
        FOREIGN KEY (reopened_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_changes (
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
    CONSTRAINT fk_attendance_changes_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_changes_day
        FOREIGN KEY (attendance_day_id) REFERENCES attendance_days(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_changes_attendance
        FOREIGN KEY (attendance_id) REFERENCES attendance(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_changes_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_changes_user
        FOREIGN KEY (changed_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
