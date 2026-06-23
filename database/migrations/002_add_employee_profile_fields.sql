ALTER TABLE employees
    ADD COLUMN middle_name VARCHAR(100) NULL AFTER first_name,
    ADD COLUMN birth_place VARCHAR(160) NULL AFTER birth_date,
    ADD COLUMN marital_status ENUM('single', 'married', 'divorced', 'widowed') NULL AFTER birth_place,
    ADD COLUMN emergency_contact_name VARCHAR(160) NULL AFTER address,
    ADD COLUMN emergency_contact_phone VARCHAR(60) NULL AFTER emergency_contact_name,
    ADD COLUMN photo_path VARCHAR(255) NULL AFTER emergency_contact_phone;
