-- Migration 006: Failed Operations Tracking
-- Tracks operations where Google API succeeded but database operation failed
-- Allows for automatic recovery and admin visibility

CREATE TABLE IF NOT EXISTS failed_operations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    operation_type ENUM('create_email', 'delete_email', 'create_alias', 'delete_alias', 'update_status') NOT NULL,
    resource_type ENUM('email', 'alias') NOT NULL,
    resource_identifier VARCHAR(255) NOT NULL,
    google_user_id VARCHAR(100) NULL,
    related_email VARCHAR(255) NULL,
    domain_id INT NULL,
    operation_data JSON NULL,
    error_message TEXT NULL,
    error_code VARCHAR(50) NULL,
    status ENUM('pending', 'auto_resolved', 'manual_resolved', 'failed_permanent') DEFAULT 'pending',
    resolution_attempts INT DEFAULT 0,
    last_attempt_at DATETIME NULL,
    resolved_at DATETIME NULL,
    resolved_by INT NULL,
    resolution_notes TEXT NULL,
    initiated_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_resource (resource_identifier),
    INDEX idx_type_status (operation_type, status),
    INDEX idx_created (created_at),
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE SET NULL,
    FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
);
