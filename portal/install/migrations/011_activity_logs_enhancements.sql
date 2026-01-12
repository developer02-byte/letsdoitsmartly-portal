-- Migration 011: Enhance Activity Logs with Change Tracking
-- Adds categorization, resource tracking, and before/after values

ALTER TABLE activity_logs
    ADD COLUMN action_category ENUM('auth', 'user', 'email', 'alias', 'domain', 'sync', 'settings', 'other') NULL AFTER action,
    ADD COLUMN changes_made JSON NULL AFTER target_domain,
    ADD COLUMN resource_type VARCHAR(50) NULL AFTER target_domain,
    ADD COLUMN resource_id INT NULL AFTER resource_type,
    ADD INDEX idx_action_category (action_category),
    ADD INDEX idx_resource (resource_type, resource_id),
    ADD INDEX idx_user_role (user_role),
    ADD INDEX idx_created_at (created_at);
