# Activity Logs Page Specification

## Overview

A unified page with 3 tabs showing different types of activity in the portal.

---

## Tab 1: Login/Logout

**Purpose**: Track authentication events for security and auditing.

**Who can see**:
- Super Admin: All users' login/logout events
- Client Admin: Only their own login/logout events

### Data Source
Table: `auth_logs`

### Columns to Display

| Column | Source Field | Format | Description |
|--------|--------------|--------|-------------|
| Time | `created_at` | "Jan 8, 2026 2:30 PM" | When the event occurred |
| User | `user_name` (from users table) | Plain text | Name of the user |
| Role | `user_role` (from users table) | Badge (Super Admin / Client Admin) | User's role |
| Event Type | `auth_type` | Badge: Login (blue), Logout (gray), Failed (red) | Type of auth event |
| Status | `status` | Badge: Success (green), Failed (red) | Whether it succeeded |
| IP Address | `ip_address` | Plain text | Client IP |
| Device/Browser | `device_type`, `browser` | "Desktop / Chrome" | Device info |
| Session Duration | `session_duration_seconds` | "2h 15m" or "-" | How long the session lasted (for logouts) |

### Filters
- User dropdown (super admin only)
- Event Type: All / Login / Logout / Failed Login
- Date range

### Sample Output
```
| Time              | User          | Role         | Event   | Status  | IP           | Device          | Duration |
|-------------------|---------------|--------------|---------|---------|--------------|-----------------|----------|
| Jan 8, 2:30 PM    | John Admin    | Super Admin  | Login   | Success | 192.168.1.1  | Desktop/Chrome  | -        |
| Jan 8, 1:15 PM    | Jane Client   | Client Admin | Logout  | Success | 10.0.0.5     | Mobile/Safari   | 45m      |
| Jan 8, 12:00 PM   | Unknown       | -            | Failed  | Failed  | 203.0.113.50 | Desktop/Firefox | -        |
```

---

## Tab 2: Sync Status

**Purpose**: Track Google Workspace synchronization operations.

**Who can see**: Super Admin only (tab hidden for Client Admin)

### Data Source
Table: `sync_sessions`

### Columns to Display

| Column | Source Field | Format | Description |
|--------|--------------|--------|-------------|
| Started | `started_at` | "Jan 8, 2026 2:30 PM" | When sync began |
| Type | `sync_type` | Badge: Manual / Auto / Cron | How sync was triggered |
| Initiated By | `initiated_by_name` | Plain text or "System" | Who started it |
| Status | `status` | Badge: Completed (green), Failed (red), In Progress (yellow) | Current status |
| Duration | `duration_seconds` | "1m 23s" | How long it took |
| Domains | `total_domains_processed` | Number | Domains processed |
| Users | `total_users_imported`, `total_users_updated` | "5 new, 12 updated" | User changes |
| Actions | - | Button | View details (opens modal) |

### Filters
- Type: All / Manual / Auto / Cron
- Status: All / Completed / Failed / In Progress
- Date range

### Details Modal (when clicking "View")
Shows per-domain breakdown from `import_logs` table:
- Domain name
- Users fetched/imported/skipped/errors
- Any error messages

### Sample Output
```
| Started           | Type   | Initiated By   | Status    | Duration | Domains | Users           | Actions |
|-------------------|--------|----------------|-----------|----------|---------|-----------------|---------|
| Jan 8, 2:30 PM    | Manual | John Admin     | Completed | 1m 23s   | 136     | 5 new, 12 updated | [View] |
| Jan 8, 2:00 PM    | Cron   | System         | Completed | 45s      | 136     | 0 new, 3 updated  | [View] |
| Jan 7, 6:00 PM    | Manual | John Admin     | Failed    | 5s       | 0       | -                 | [View] |
```

---

## Tab 3: Admin Actions

**Purpose**: Track administrative changes made in the portal (NOT login/logout).

**Who can see**:
- Super Admin: All admin actions
- Client Admin: Only actions on their assigned domains

### Data Source
Table: `activity_logs` (excluding login/logout entries)

### What Actions to Show

| Category | Actions Included | Example Log Message |
|----------|------------------|---------------------|
| **Users** | Create, Update, Delete, Password Reset | "Created user: John (john@example.com)" |
| **Domains** | Add, Update, Delete, **Assign to User** | "Assigned domain example.com to John Client" |
| **Emails** | Create, Update, Delete, Suspend, Activate, Password Reset | "Created email: user@example.com" |
| **Aliases** | Create, Delete | "Added alias: sales@example.com -> john@example.com" |
| **Sync** | Full Sync, Domain Sync, Import from Google | "Full sync completed: 136 domains, 5 new users" |

### What Actions to EXCLUDE
- Login/logout (shown in Tab 1)
- Failed login attempts (shown in Tab 1)

### Columns to Display

| Column | Source Field | Format | Description |
|--------|--------------|--------|-------------|
| Time | `created_at` | "Jan 8, 2026 2:30 PM" | When action occurred |
| Admin | `user_name` (from users table) | Plain text | Who performed the action |
| Action | `action` | Badge with category color | What was done |
| Target | `target_email` or `target_domain` | Plain text | What was affected |
| Details | `changes_made` | Button (if changes exist) | View before/after |

### Category Colors for Action Badge
- User actions: Green
- Domain actions: Blue
- Email actions: Primary/Blue
- Alias actions: Orange/Warning
- Sync actions: Dark/Gray

### Details View (when clicking "View Details")
Shows JSON `changes_made` in readable format:

**For Updates:**
```
Before:
  - status: active
  - owner: Unassigned

After:
  - status: active
  - owner: John Client
```

**For Creates:**
```
Created:
  - Name: John Smith
  - Email: john@example.com
  - Role: Client Admin
  - Company: Acme Corp
```

### Filters
- Admin user dropdown
- Category: All / Users / Domains / Emails / Aliases / Sync
- Date range
- Search (searches action text, target email, target domain)

### Sample Output
```
| Time              | Admin        | Action                                      | Target           | Details |
|-------------------|--------------|---------------------------------------------|------------------|---------|
| Jan 8, 2:30 PM    | John Admin   | Assigned domain example.com to Jane Client  | example.com      | [View]  |
| Jan 8, 2:15 PM    | John Admin   | Created user: Jane (jane@corp.com)         | -                | [View]  |
| Jan 8, 2:00 PM    | John Admin   | Created email: sales@example.com           | sales@example.com| [View]  |
| Jan 8, 1:45 PM    | John Admin   | Full sync completed: 136 domains           | -                | [View]  |
| Jan 8, 1:30 PM    | Jane Client  | Reset password for: user@example.com       | user@example.com | -       |
```

---

## Technical Implementation Notes

### Database Tables Used

1. **auth_logs** - Login/Logout tab
   - Joined with `users` table for user name/role

2. **sync_sessions** - Sync Status tab
   - Linked to `import_logs` for per-domain details

3. **activity_logs** - Admin Actions tab
   - Joined with `users` table for admin name
   - Filter: `action NOT LIKE '%logged in%' AND action NOT LIKE '%logged out%'`
   - Or: `action_category != 'auth'`

### Access Control Rules

| Tab | Super Admin | Client Admin |
|-----|-------------|--------------|
| Login/Logout | See all users | See only own logs |
| Sync Status | Full access | Tab hidden |
| Admin Actions | See all actions | See only actions on own domains |

### Auto-Refresh
- Sync Status tab should auto-refresh every 30 seconds if any sync is "in_progress"
- Stop auto-refresh when no active syncs

---

## Questions to Confirm

1. Should Client Admin see the "Admin Actions" tab at all, or only Login/Logout?
2. For domain assignment changes, what's the preferred format:
   - "Assigned domain example.com to John Client"
   - "Updated domain: example.com"
3. Should we show IP address in Admin Actions tab as well?
4. What date range should be the default filter? (Last 7 days? Last 30 days? All?)
