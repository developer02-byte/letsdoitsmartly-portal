-- Migration 010: Sync Sessions Summary Table
-- Provides high-level view of sync operations with summary statistics

CREATE TABLE IF NOT EXISTS sync_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sync_type ENUM('manual', 'auto', 'cron') NOT NULL,
    initiated_by INT NULL,
    initiated_by_name VARCHAR(100) NULL,

    status ENUM('in_progress', 'completed', 'failed', 'partial') DEFAULT 'in_progress',
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    duration_seconds INT NULL,

    -- Summary stats
    total_domains_processed INT DEFAULT 0,
    total_users_fetched INT DEFAULT 0,
    total_users_imported INT DEFAULT 0,
    total_users_updated INT DEFAULT 0,
    total_aliases_added INT DEFAULT 0,
    total_aliases_removed INT DEFAULT 0,

    had_errors BOOLEAN DEFAULT FALSE,
    error_summary TEXT NULL,

    INDEX idx_sync_type (sync_type),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),

    FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Link import_logs to sync_sessions
ALTER TABLE import_logs
    ADD COLUMN sync_session_id INT NULL AFTER id,
    ADD INDEX idx_sync_session_id (sync_session_id),
    ADD FOREIGN KEY (sync_session_id) REFERENCES sync_sessions(id) ON DELETE SET NULL;

-- Add performance indexes to import_logs
ALTER TABLE import_logs
    ADD INDEX idx_sync_type_status (sync_type, status),
    ADD INDEX idx_initiated_by (initiated_by),
    ADD INDEX idx_started_at (started_at);
