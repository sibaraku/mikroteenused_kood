USE laenutus;

INSERT INTO users (id, email, password_hash, role, name) VALUES
('u-7', 'student@kool.ee', '$2y$10$KBz2Ra7cffosBbs6gwc6G.vCcARArmuoTVEYfEQCgSs.vgPj2Idri', 'user', 'Mari Õpilane'),
('u-1', 'admin@kool.ee', '$2y$10$WYewKRKd39CJHB3yhCK/LOZIANhMtvNU5GMvOg5nXMMA.qXqlLU5W', 'admin', 'Admin Kasutaja');

INSERT INTO items (id, name, category, status, description) VALUES
('i-42', 'Sony A7 III kaamera', 'Kaamera', 'available', 'Täiskaader kaamera videoprojektide jaoks'),
('i-1', 'Rode NTG-3 mikrofon', 'Mikrofon', 'available', 'Suunamikrofon välitingimustes salvestamiseks'),
('i-2', 'Manfrotto statiiv', 'Statiiv', 'available', 'Stabiilne statiiv kaamera ja mikrofoni jaoks'),
('i-3', 'Zoom H6 helirekorder', 'Helitehnika', 'maintenance', 'Hoolduses – akut vahetatakse'),
('i-4', 'Canon EOS 90D', 'Kaamera', 'broken', 'Katki – vajab remonti');

INSERT INTO loans (id, user_id, item_id, start_date, end_date, status) VALUES
('l-100', 'u-7', 'i-42', '2026-10-01', '2026-10-03', 'confirmed');

UPDATE items SET status = 'reserved' WHERE id = 'i-42';

INSERT INTO notifications (id, user_id, loan_id, email, message, status, sent_at) VALUES
('n-1', 'u-7', 'l-100', 'student@kool.ee', 'Teie laenutus l-100 vahendile Sony A7 III kaamera on kinnitatud.', 'sent', NOW());
