ALTER TABLE departments
    ADD COLUMN manager_id INT UNSIGNED NULL AFTER branch_id,
    ADD KEY idx_departments_manager (manager_id),
    ADD CONSTRAINT fk_departments_manager
        FOREIGN KEY (manager_id) REFERENCES employees(id)
        ON DELETE SET NULL ON UPDATE CASCADE;
