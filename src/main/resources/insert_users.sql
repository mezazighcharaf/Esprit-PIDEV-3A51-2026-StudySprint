-- Supprimer si existants
DELETE FROM etudiant WHERE email IN ('user@studysprint.com', 'admin@studysprint.com');

-- user@studysprint.com  / user123
INSERT INTO etudiant (email, roles, password) VALUES (
    'user@studysprint.com',
    '["ROLE_USER"]',
    '$2a$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj/RK.s5uIyu'
);

-- admin@studysprint.com / admin123
INSERT INTO etudiant (email, roles, password) VALUES (
    'admin@studysprint.com',
    '["ROLE_USER","ROLE_ADMIN"]',
    '$2a$12$ixH.Lk0GGjFMFGMFGMFGMeK8Kj3Kj3Kj3Kj3Kj3Kj3Kj3Kj3Kj3K'
);
