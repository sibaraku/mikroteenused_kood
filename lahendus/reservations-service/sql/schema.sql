CREATE TABLE reservations (
    id VARCHAR(20) PRIMARY KEY,
    user_id VARCHAR(20) NOT NULL,
    item_id VARCHAR(20) NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    status ENUM('pending', 'notified', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notified_at TIMESTAMP NULL
);

CREATE INDEX idx_reservations_queue ON reservations(item_id, status, created_at);
CREATE INDEX idx_reservations_user ON reservations(user_id, created_at);