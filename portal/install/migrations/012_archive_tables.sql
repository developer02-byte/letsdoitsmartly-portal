-- Migration 012: Archive Tables for 3-Year Retention with Hot/Cold Storage
-- Creates archive tables for long-term storage of activity logs

-- Archive table for auth_logs
CREATE TABLE IF NOT EXISTS auth_logs_archive LIKE auth_logs;

-- Archive table for sync_sessions
CREATE TABLE IF NOT EXISTS sync_sessions_archive LIKE sync_sessions;

-- Archive table for activity_logs
CREATE TABLE IF NOT EXISTS activity_logs_archive LIKE activity_logs;

-- Add archive indicator to main tables (for tracking archival status)
ALTER TABLE auth_logs ADD COLUMN IF NOT EXISTS archived BOOLEAN DEFAULT FALSE;
ALTER TABLE sync_sessions ADD COLUMN IF NOT EXISTS archived BOOLEAN DEFAULT FALSE;
ALTER TABLE activity_logs ADD COLUMN IF NOT EXISTS archived BOOLEAN DEFAULT FALSE;

-- Add indexes for archived column for faster archival queries
ALTER TABLE auth_logs ADD INDEX IF NOT EXISTS idx_archived (archived);
ALTER TABLE sync_sessions ADD INDEX IF NOT EXISTS idx_archived (archived);
ALTER TABLE activity_logs ADD INDEX IF NOT EXISTS idx_archived (archived);
