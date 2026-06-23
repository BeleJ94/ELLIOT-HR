ALTER TABLE companies
    ADD COLUMN national_id VARCHAR(100) NULL AFTER registration_number,
    ADD COLUMN province VARCHAR(120) NULL AFTER city,
    ADD COLUMN industry VARCHAR(160) NULL AFTER country;
