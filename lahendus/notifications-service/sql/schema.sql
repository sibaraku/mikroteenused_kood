CREATE TABLE notifications (
    id VARCHAR(20) PRIMARY KEY,
    user_id VARCHAR(20) NOT NULL,
    loan_id VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_notifications_loan ON notifications(loan_id);
