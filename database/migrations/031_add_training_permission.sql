-- Grant training module access to HR administration roles.
-- Compatible MySQL 5.7

INSERT INTO permissions (name, slug, module, description)
SELECT 'Gerer les formations', 'trainings.manage', 'trainings', 'Catalogue, sessions et presences de formation'
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE slug = 'trainings.manage'
);

UPDATE permissions
SET deleted_at = NULL,
    updated_at = NOW()
WHERE slug = 'trainings.manage';

UPDATE role_permissions
INNER JOIN roles ON roles.id = role_permissions.role_id
INNER JOIN permissions ON permissions.id = role_permissions.permission_id
SET role_permissions.deleted_at = NULL,
    role_permissions.updated_at = NOW()
WHERE permissions.slug = 'trainings.manage'
AND roles.slug IN ('super-admin', 'admin-rh', 'manager');

INSERT INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions ON permissions.slug = 'trainings.manage'
LEFT JOIN role_permissions existing
    ON existing.role_id = roles.id
    AND existing.permission_id = permissions.id
WHERE roles.slug IN ('super-admin', 'admin-rh', 'manager')
AND roles.deleted_at IS NULL
AND existing.id IS NULL;
