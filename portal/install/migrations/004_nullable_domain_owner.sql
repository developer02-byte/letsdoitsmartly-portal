-- Migration 004: Allow domains without assigned owner
-- This enables auto-sync to create domains that aren't yet assigned to any client

-- Make domain_owner_id nullable
ALTER TABLE domains MODIFY domain_owner_id INT NULL;

-- Add index for efficient filtering of unassigned domains
ALTER TABLE domains ADD INDEX idx_domain_owner (domain_owner_id);

-- Update foreign key to allow NULL (drop and recreate)
ALTER TABLE domains DROP FOREIGN KEY domains_ibfk_1;
ALTER TABLE domains ADD CONSTRAINT domains_ibfk_1
    FOREIGN KEY (domain_owner_id) REFERENCES domain_owners(id) ON DELETE SET NULL;
