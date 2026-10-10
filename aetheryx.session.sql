INSERT INTO users (full_name, handle, email, password_hash, role, status)
SELECT
    'Rhea Admin',
    'rhea',
    'rhea@aetheryx.local',
    '$2y$10$JvzQ0XHJWltUBgjpPAFnPOvBjnOnvCIf3AK9PMOe0Ytv/w1JqzDKu',
    'admin',
    'active'
WHERE NOT EXISTS (
    SELECT 1 FROM users
    WHERE handle = 'rhea' OR email = 'rhea@aetheryx.local'
);