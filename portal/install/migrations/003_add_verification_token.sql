-- Migration: Add verification_token to otp_codes
-- Purpose: Allow verification via clickable link in addition to OTP code
-- Date: 2026-01-03

-- Add verification_token column
ALTER TABLE otp_codes ADD COLUMN verification_token VARCHAR(64) NULL AFTER otp_code;

-- Add index for fast token lookups
CREATE INDEX idx_verification_token ON otp_codes(verification_token);
