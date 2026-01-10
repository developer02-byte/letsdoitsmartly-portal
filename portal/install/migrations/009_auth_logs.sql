-- Migration 009: Authentication Logs Table
-- Tracks login/logout events, session duration, device info, failed attempts, and geolocation

CREATE TABLE IF NOT EXISTS auth_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,                              -- NULL for failed logins
    email_attempted VARCHAR(255) NOT NULL,
    auth_type ENUM('login', 'logout', 'failed_login') NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    failure_reason VARCHAR(255) NULL,

    -- Session tracking
    session_id VARCHAR(100) NULL,
    session_started_at DATETIME NULL,
    session_ended_at DATETIME NULL,
    session_duration_seconds INT NULL,

    -- Device and location
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    device_type VARCHAR(50) NULL,                  -- desktop, mobile, tablet
    browser VARCHAR(50) NULL,
    browser_version VARCHAR(20) NULL,
    os VARCHAR(50) NULL,
    os_version VARCHAR(20) NULL,

    -- Geolocation
    country_code CHAR(2) NULL,
    country_name VARCHAR(100) NULL,
    region VARCHAR(100) NULL,
    city VARCHAR(100) NULL,
    timezone VARCHAR(50) NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_email_attempted (email_attempted),
    INDEX idx_auth_type_status (auth_type, status),
    INDEX idx_created_at (created_at),
    INDEX idx_session_id (session_id),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
