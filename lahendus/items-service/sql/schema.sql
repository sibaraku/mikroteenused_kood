CREATE TABLE items (
    id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(100) NOT NULL,
    status ENUM('available', 'reserved', 'broken', 'maintenance') NOT NULL DEFAULT 'available',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
