INSERT INTO users (id, email, password_hash, role, name) VALUES
('u-7', 'student@kool.ee', '$2y$10$KBz2Ra7cffosBbs6gwc6G.vCcARArmuoTVEYfEQCgSs.vgPj2Idri', 'user', 'Mari Õpilane'),
('u-1', 'admin@kool.ee', '$2y$10$WYewKRKd39CJHB3yhCK/LOZIANhMtvNU5GMvOg5nXMMA.qXqlLU5W', 'admin', 'Admin Kasutaja');

INSERT INTO loans (id, user_id, item_id, start_date, end_date, status) VALUES
('l-100', 'u-7', 'i-42', '2026-10-01', '2026-10-03', 'confirmed');
