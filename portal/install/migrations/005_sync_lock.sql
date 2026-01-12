-- Migration 005: Sync Lock Table
-- Prevents concurrent sync operations

CREATE TABLE IF NOT EXISTS sync_locks (
    id INT PRIMARY KEY DEFAULT 1,
    locked_at DATETIME NULL,
    locked_by VARCHAR(100) NULL,
    CONSTRAINT single_row CHECK (id = 1)
);

-- Ensure single row exists
INSERT IGNORE INTO sync_locks (id) VALUES (1);
