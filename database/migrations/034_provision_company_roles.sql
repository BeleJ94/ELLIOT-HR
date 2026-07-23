-- Provision the standard tenant roles for companies created after the initial seed.
-- Safe to execute more than once. Compatible with MySQL 5.7.

INSERT IGNORE INTO roles (company_id, name, slug, description, is_system, created_at)
SELECT companies.id, templates.name, templates.slug, templates.description, 1, NOW()
FROM companies
CROSS JOIN (
    SELECT 'Admin RH' AS name, 'admin-rh' AS slug, 'Administration RH de l entreprise' AS description
    UNION ALL
    SELECT 'Manager', 'manager', 'Gestion d equipe et validations'
    UNION ALL
    SELECT 'Employe', 'employe', 'Acces employe standard'
) AS templates
WHERE companies.deleted_at IS NULL;

UPDATE roles
SET deleted_at = NULL,
    is_system = 1,
    updated_at = NOW()
WHERE company_id IS NOT NULL
AND slug IN ('admin-rh', 'manager', 'employe');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT roles.id, permissions.id, NOW()
FROM roles
INNER JOIN permissions
    ON permissions.deleted_at IS NULL
    AND (
        (roles.slug = 'admin-rh' AND permissions.slug IN (
            'employees.manage', 'contracts.manage', 'attendance.manage',
            'leaves.manage', 'medical.manage', 'trainings.manage',
            'payroll.manage', 'self.view', 'declarations.manage'
        ))
        OR
        (roles.slug = 'manager' AND permissions.slug IN (
            'employees.manage', 'attendance.manage', 'leaves.manage',
            'medical.manage', 'trainings.manage', 'self.view'
        ))
        OR
        (roles.slug = 'employe' AND permissions.slug IN (
            'medical.manage', 'self.view'
        ))
    )
WHERE roles.company_id IS NOT NULL
AND roles.deleted_at IS NULL
AND roles.slug IN ('admin-rh', 'manager', 'employe');

UPDATE role_permissions
INNER JOIN roles ON roles.id = role_permissions.role_id
INNER JOIN permissions ON permissions.id = role_permissions.permission_id
SET role_permissions.deleted_at = NULL,
    role_permissions.updated_at = NOW()
WHERE roles.company_id IS NOT NULL
AND permissions.deleted_at IS NULL
AND (
    (roles.slug = 'admin-rh' AND permissions.slug IN (
        'employees.manage', 'contracts.manage', 'attendance.manage',
        'leaves.manage', 'medical.manage', 'trainings.manage',
        'payroll.manage', 'self.view', 'declarations.manage'
    ))
    OR
    (roles.slug = 'manager' AND permissions.slug IN (
        'employees.manage', 'attendance.manage', 'leaves.manage',
        'medical.manage', 'trainings.manage', 'self.view'
    ))
    OR
    (roles.slug = 'employe' AND permissions.slug IN (
        'medical.manage', 'self.view'
    ))
);
