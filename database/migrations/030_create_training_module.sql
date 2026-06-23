-- Training management module
-- Compatible MySQL 5.7

CREATE TABLE IF NOT EXISTS training_courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    code VARCHAR(80) NULL,
    category VARCHAR(120) NULL,
    objectives TEXT NULL,
    default_duration_days DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    certificate_valid_months SMALLINT UNSIGNED NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_training_courses_company (company_id),
    UNIQUE KEY uq_training_courses_company_code (company_id, code),
    CONSTRAINT fk_training_courses_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    training_course_id INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    trainer_name VARCHAR(160) NULL,
    provider VARCHAR(160) NULL,
    location VARCHAR(190) NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    budget DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    min_attendance_rate DECIMAL(5,2) NOT NULL DEFAULT 80.00,
    status ENUM('planned', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'planned',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_training_sessions_company (company_id),
    KEY idx_training_sessions_course (training_course_id),
    KEY idx_training_sessions_dates (start_date, end_date),
    CONSTRAINT fk_training_sessions_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_training_sessions_course
        FOREIGN KEY (training_course_id) REFERENCES training_courses(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_session_days (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    training_session_id INT UNSIGNED NOT NULL,
    day_date DATE NOT NULL,
    topic VARCHAR(190) NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    status ENUM('planned', 'completed', 'cancelled') NOT NULL DEFAULT 'planned',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_training_session_day (training_session_id, day_date),
    KEY idx_training_days_company (company_id),
    CONSTRAINT fk_training_days_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_training_days_session
        FOREIGN KEY (training_session_id) REFERENCES training_sessions(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    training_session_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    invitation_status ENUM('invited', 'confirmed', 'declined') NOT NULL DEFAULT 'invited',
    attendance_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    final_status ENUM('invited', 'completed', 'failed', 'absent', 'excused') NOT NULL DEFAULT 'invited',
    score DECIMAL(6,2) NULL,
    certificate_issued TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_training_participant_employee (training_session_id, employee_id),
    KEY idx_training_participants_company (company_id),
    KEY idx_training_participants_employee (employee_id),
    CONSTRAINT fk_training_participants_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_training_participants_session
        FOREIGN KEY (training_session_id) REFERENCES training_sessions(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_training_participants_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    training_session_id INT UNSIGNED NOT NULL,
    training_session_day_id INT UNSIGNED NOT NULL,
    training_participant_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_training_attendance_day_participant (training_session_day_id, training_participant_id),
    KEY idx_training_attendance_company (company_id),
    KEY idx_training_attendance_session (training_session_id),
    CONSTRAINT fk_training_attendance_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_training_attendance_session
        FOREIGN KEY (training_session_id) REFERENCES training_sessions(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_training_attendance_day
        FOREIGN KEY (training_session_day_id) REFERENCES training_session_days(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_training_attendance_participant
        FOREIGN KEY (training_participant_id) REFERENCES training_participants(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_training_attendance_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
