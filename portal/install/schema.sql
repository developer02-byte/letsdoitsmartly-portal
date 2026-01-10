-- Unified Email Management Portal - Database Schema
-- Run this in phpMyAdmin on letsdoit_portal database

-- 1. USERS (unified authentication)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'client_admin') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL
);

-- 2. DOMAIN OWNERS (client profile)
CREATE TABLE domain_owners (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    company_name VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    admin_id VARCHAR(50) UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. DOMAINS
CREATE TABLE domains (
    id INT PRIMARY KEY AUTO_INCREMENT,
    domain_name VARCHAR(255) UNIQUE NOT NULL,
    domain_owner_id INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_google_sync DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_owner_id) REFERENCES domain_owners(id)
);

-- 4. EMAIL ACCOUNTS
CREATE TABLE email_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    domain_id INT NOT NULL,
    email_address VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NULL,
    status ENUM('active', 'suspended') DEFAULT 'active',
    google_user_id VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id)
);

-- 5. EMAIL ALIASES
CREATE TABLE email_aliases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email_account_id INT NOT NULL,
    alias_address VARCHAR(255) UNIQUE NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (email_account_id) REFERENCES email_accounts(id) ON DELETE CASCADE
);

-- 6. ACTIVITY LOGS
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    user_role VARCHAR(20) NULL,
    action VARCHAR(255) NOT NULL,
    target_email VARCHAR(255) NULL,
    target_domain VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 7. IMPORT LOGS
CREATE TABLE import_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    domain_id INT NOT NULL,
    initiated_by INT NULL,
    sync_type ENUM('manual', 'auto', 'scheduled') DEFAULT 'manual',
    status ENUM('in_progress', 'completed', 'failed') DEFAULT 'in_progress',
    total_fetched INT DEFAULT 0,
    total_imported INT DEFAULT 0,
    total_skipped INT DEFAULT 0,
    total_errors INT DEFAULT 0,
    error_message TEXT NULL,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (domain_id) REFERENCES domains(id),
    FOREIGN KEY (initiated_by) REFERENCES users(id)
);

-- 8. OTP CODES
CREATE TABLE otp_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_data JSON NULL,
    expires_at DATETIME NOT NULL,
    verified BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 9. PASSWORD RESETS
CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(100) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Initial Super Admin (password: Admin@123)
INSERT INTO users (email, password_hash, name, role) VALUES
('admin@letsdoitsmartly.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'super_admin');
