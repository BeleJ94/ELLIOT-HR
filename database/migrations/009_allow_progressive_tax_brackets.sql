ALTER TABLE tax_settings
    DROP INDEX uq_tax_settings_company_code,
    ADD UNIQUE KEY uq_tax_settings_company_code_min (company_id, tax_code, threshold_min);
