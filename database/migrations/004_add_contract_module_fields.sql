-- Contract module extra fields
-- Compatible MySQL 5.7

ALTER TABLE contracts
    ADD COLUMN probation_ends_at DATE NULL AFTER end_date,
    ADD COLUMN renewed_from_id INT UNSIGNED NULL AFTER status,
    ADD COLUMN renewed_at DATETIME NULL AFTER renewed_from_id,
    ADD COLUMN pdf_path VARCHAR(255) NULL AFTER renewed_at,
    ADD COLUMN signed_contract_path VARCHAR(255) NULL AFTER pdf_path,
    ADD COLUMN signed_contract_name VARCHAR(190) NULL AFTER signed_contract_path,
    ADD COLUMN signed_contract_mime VARCHAR(100) NULL AFTER signed_contract_name,
    ADD KEY idx_contracts_renewed_from (renewed_from_id),
    ADD KEY idx_contracts_end_date (end_date),
    ADD CONSTRAINT fk_contracts_renewed_from
        FOREIGN KEY (renewed_from_id) REFERENCES contracts(id)
        ON DELETE SET NULL ON UPDATE CASCADE;
