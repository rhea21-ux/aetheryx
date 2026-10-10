INSERT INTO users (full_name, handle, email, password_hash, role, status)
SELECT
    'Rhea Admin',
    'rhea',
    'rhea@aetheryx.local',
    '$2y$10$muNhJxpEJG1.pGJTNILKre5fjY93y79VTV01ZLasiFby5dVeWfY5G',
    'admin',
    'active'
WHERE NOT EXISTS (
    SELECT 1 FROM users
    WHERE handle = 'rhea' OR email = 'rhea@aetheryx.local'
);