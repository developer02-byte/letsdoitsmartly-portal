# Domain Removal Detection - Design Document

## Overview

Detect when domains are removed from Google Workspace and soft-delete them in the portal. This preserves data for audit purposes while hiding removed domains from active views.

## Problem Statement

When a domain is removed from Google Workspace:
- Portal still shows it as active
- Operations fail with confusing Google API errors
- No visibility that the domain was removed
- Orphan email accounts remain in database

## Solution: Soft Delete with Detection

### Database Changes

**domains table:**
```sql
ALTER TABLE domains
ADD COLUMN google_status ENUM('active', 'removed') DEFAULT 'active',
ADD COLUMN removed_at DATETIME NULL;
```

- `google_status`: Reflects actual Google Workspace state
- `removed_at`: Timestamp when removal was detected
- Keep existing `status` column for portal admin control

**email_accounts table:**
```sql
ALTER TABLE email_accounts
MODIFY status ENUM('active', 'suspended', 'disabled', 'domain_removed') DEFAULT 'active';
```

- Add `domain_removed` status for cascading when domain is removed

### Detection Logic

Location: `syncAllDomainsFromGoogle()` in `google_functions.php`

```
1. Fetch all domains from Google Directory API
2. Get DB domains where google_status = 'active'
3. Compare: DB domains NOT in Google response = removed
4. For each removed domain:
   - Set google_status = 'removed'
   - Set removed_at = NOW()
   - Cascade: UPDATE email_accounts SET status = 'domain_removed' WHERE domain_id = X
5. Log activity for audit trail
```

**Safety against API failures:**
- Only mark as removed on successful Google API response
- If API call fails, do NOT mark anything as removed
- Domain must be present in successful response to be considered active

**Recovery case:**
- If domain reappears in Google (re-added), restore it:
  - Set google_status = 'active'
  - Set removed_at = NULL
  - Trigger re-sync of users for that domain

### UI Changes

**domains.php:**
- Add status filter dropdown: All | Active | Removed
- Default filter to "Active"
- Removed domains show red badge "Removed from Google"
- Removed domains are read-only (no edit/delete buttons)

**dashboard.php:**
- Add alert widget for super admin:
  - "X domain(s) removed from Google Workspace"
  - Link to domains.php?filter=removed

**Operation blocking:**
- `email_handler.php`: Check domain google_status before create/edit
- `alias_handler.php`: Check domain google_status before create/edit
- Return friendly error: "Domain no longer exists in Google Workspace"

### Why Soft Delete?

1. **Audit trail**: Keep record of what existed
2. **Recovery**: Easy restore if domain is re-added
3. **No data loss**: Accidental removals don't destroy data
4. **Visibility**: Admins can see what was removed and when

## Files to Create/Modify

### New Files
| File | Purpose |
|------|---------|
| `portal/install/migrations/008_domain_google_status.sql` | Add columns and status value |

### Modified Files
| File | Changes |
|------|---------|
| `portal/includes/google_functions.php` | Add detection logic in sync |
| `portal/domains.php` | Status filter, removed badge, block operations |
| `portal/dashboard.php` | Removed domains alert widget |
| `portal/email_handler.php` | Block operations on removed domains |
| `portal/alias_handler.php` | Block operations on removed domains |

## Migration SQL

```sql
-- Migration 008: Domain Google Status
-- Tracks when domains are removed from Google Workspace

-- Add google_status column to domains
ALTER TABLE domains
ADD COLUMN google_status ENUM('active', 'removed') DEFAULT 'active' AFTER status,
ADD COLUMN removed_at DATETIME NULL AFTER google_status;

-- Add domain_removed status to email_accounts
ALTER TABLE email_accounts
MODIFY COLUMN status ENUM('active', 'suspended', 'disabled', 'domain_removed') DEFAULT 'active';

-- Index for filtering
CREATE INDEX idx_domains_google_status ON domains(google_status);
```

## Implementation Order

1. Create and run migration 008
2. Add detection logic to `google_functions.php`
3. Update `domains.php` UI
4. Update `dashboard.php` alert
5. Add blocking to `email_handler.php`
6. Add blocking to `alias_handler.php`
7. Deploy and test
