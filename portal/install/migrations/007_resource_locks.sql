-- Migration 007: Resource Locks
-- Prevents race conditions on concurrent email/alias operations
-- Short-lived locks (30 seconds) that auto-expire

CREATE TABLE IF NOT EXISTS resource_locks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resource_type ENUM('email', 'alias', 'domain') NOT NULL,
    resource_identifier VARCHAR(255) NOT NULL,
    locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_by INT NULL,
    lock_reason VARCHAR(100) NULL,
    expires_at DATETIME NOT NULL,
    UNIQUE INDEX idx_resource (resource_type, resource_identifier),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL
);
