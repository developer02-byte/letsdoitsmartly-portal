-- Migration: Add owner_email field to domain_owners
-- Purpose: Allow OTP notifications to be sent to a separate email address
--          (different from the login email) for Client Admin operations
-- Date: 2026-01-03

-- Add owner_email column
ALTER TABLE domain_owners
ADD COLUMN owner_email VARCHAR(255) NULL AFTER admin_id;

-- Add comment for documentation
-- This field stores the email where OTP verification codes are sent
-- for sensitive operations performed by Client Admins.
-- If NULL, the login email (from users table) is used instead.
