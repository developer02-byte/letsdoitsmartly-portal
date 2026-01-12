-- Migration 008: Domain Google Status
-- Tracks when domains are removed from Google Workspace
-- Run this migration on the server after deployment

-- Add google_status column to domains
ALTER TABLE domains
ADD COLUMN google_status ENUM('active', 'removed') DEFAULT 'active' AFTER status,
ADD COLUMN removed_at DATETIME NULL AFTER google_status;

-- Add domain_removed status to email_accounts
ALTER TABLE email_accounts
MODIFY COLUMN status ENUM('active', 'suspended', 'disabled', 'domain_removed') DEFAULT 'active';

-- Index for filtering by google_status
CREATE INDEX idx_domains_google_status ON domains(google_status);
