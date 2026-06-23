-- Leave workflow extra fields
-- Compatible MySQL 5.7

ALTER TABLE leave_requests
    ADD COLUMN manager_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER reason,
    ADD COLUMN hr_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER manager_status,
    ADD COLUMN manager_approved_by INT UNSIGNED NULL AFTER hr_status,
    ADD COLUMN hr_approved_by INT UNSIGNED NULL AFTER manager_approved_by,
    ADD COLUMN manager_approved_at DATETIME NULL AFTER hr_approved_by,
    ADD COLUMN hr_approved_at DATETIME NULL AFTER manager_approved_at,
    ADD COLUMN rejection_reason TEXT NULL AFTER hr_approved_at,
    ADD KEY idx_leave_requests_manager_approver (manager_approved_by),
    ADD KEY idx_leave_requests_hr_approver (hr_approved_by),
    ADD CONSTRAINT fk_leave_requests_manager_approver
        FOREIGN KEY (manager_approved_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_leave_requests_hr_approver
        FOREIGN KEY (hr_approved_by) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO leave_types (company_id, name, code, paid, annual_days)
SELECT c.id, defaults.name, defaults.code, defaults.paid, defaults.annual_days
FROM companies c
JOIN (
    SELECT 'Conge annuel' AS name, 'ANNUAL' AS code, 1 AS paid, 26.00 AS annual_days
    UNION ALL SELECT 'Conge maladie', 'SICK', 1, 0.00
    UNION ALL SELECT 'Conge maternite', 'MATERNITY', 1, 0.00
    UNION ALL SELECT 'Conge paternite', 'PATERNITY', 1, 0.00
    UNION ALL SELECT 'Conge exceptionnel', 'EXCEPTIONAL', 1, 0.00
    UNION ALL SELECT 'Absence autorisee', 'AUTHORIZED_ABSENCE', 1, 0.00
    UNION ALL SELECT 'Absence non autorisee', 'UNAUTHORIZED_ABSENCE', 0, 0.00
) defaults
LEFT JOIN leave_types existing
    ON existing.company_id = c.id
    AND existing.code = defaults.code
    AND existing.deleted_at IS NULL
WHERE c.deleted_at IS NULL
AND existing.id IS NULL;
