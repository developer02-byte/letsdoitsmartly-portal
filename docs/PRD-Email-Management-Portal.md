# Product Requirements Document (PRD)
# Email Management Portal for Google Workspace

**Version:** 1.0
**Last Updated:** December 30, 2025
**Status:** In Development

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Product Overview](#2-product-overview)
3. [User Personas](#3-user-personas)
4. [Architecture](#4-architecture)
5. [Phase A: Core Google Workspace Integration](#5-phase-a-core-google-workspace-integration)
6. [Phase B: Security Hardening](#6-phase-b-security-hardening)
7. [Phase C: Advanced Features](#7-phase-c-advanced-features)
8. [Phase D: Client Self-Service Enhancements](#8-phase-d-client-self-service-enhancements)
9. [Technical Specifications](#9-technical-specifications)
10. [Database Schema](#10-database-schema)
11. [API Integration](#11-api-integration)
12. [Testing Requirements](#12-testing-requirements)

---

## 1. Executive Summary

### 1.1 Purpose
A multi-tenant email management portal enabling MSPs (Managed Service Providers) to manage Google Workspace email accounts across multiple client domains through a unified interface.

### 1.2 Goals
- Centralized management of Google Workspace email accounts
- Self-service portal for clients to manage their own domains
- License tracking and cost management
- Audit trail for all operations
- Secure operations with OTP verification for destructive actions

### 1.3 Success Metrics
- Reduce email provisioning time from hours to minutes
- 100% sync accuracy between portal and Google Workspace
- Complete audit trail for compliance requirements
- Client self-service adoption rate > 80%

---

## 2. Product Overview

### 2.1 System Components

| Component | Path | Purpose |
|-----------|------|---------|
| Admin Portal | `/admin/` | MSP manages all domains, all emails |
| Client Portal | `/manage/` | Clients manage only their domain's emails |
| Google API Integration | `/google-api/` | Service account API functions |
| Configuration | `/websiteconfig/` | Credentials and settings |

### 2.2 Core Principle

```
User Action → Validate → Call Google API → If success → Update Database → Return Success
                              ↓
                         If fail → Return Error (no DB changes)
```

All operations sync to Google Workspace FIRST, then update the local database only on success.

---

## 3. User Personas

### 3.1 MSP Administrator
- **Role:** Full system access
- **Responsibilities:**
  - Manage all domain owners
  - Create/manage domains for clients
  - Provision email accounts across all domains
  - Monitor license usage and costs
  - View activity logs
  - Handle escalations

### 3.2 Domain Owner (Client)
- **Role:** Scoped to their domain(s) only
- **Responsibilities:**
  - Create/manage email accounts within their domain
  - Manage email aliases
  - Reset user passwords
  - View their license allocation

---

## 4. Architecture

### 4.1 Technology Stack

| Layer | Technology |
|-------|------------|
| Frontend | HTML5, Bootstrap 5.3, JavaScript (Vanilla) |
| Backend | PHP 8.x |
| Database | MySQL/MariaDB |
| API | Google Workspace Admin SDK (Directory API) |
| Authentication | Session-based with bcrypt password hashing |
| Email | PHP mail() for OTP delivery |

### 4.2 Security Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Admin Portal  │────▶│  PHP Backend    │────▶│  Google API     │
│   (HTTPS only)  │     │  (Prepared SQL) │     │  (Service Acct) │
└─────────────────┘     └─────────────────┘     └─────────────────┘
                               │
                               ▼
                        ┌─────────────────┐
                        │    MySQL DB     │
                        │  (Encrypted)    │
                        └─────────────────┘
```

---

## 5. Phase A: Core Google Workspace Integration

**Status:** ✅ COMPLETED

### 5.1 Admin Portal Features

#### 5.1.1 Dashboard (`/admin/dashboard.php`)
| Feature | Status | Description |
|---------|--------|-------------|
| Overview Statistics | ✅ | Total domains, emails, aliases, owners |
| Domain Owner Management | ✅ | Add/edit/view domain owners |
| License Summary | ✅ | Total vs used licenses per owner |
| Quick Navigation | ✅ | Links to all management pages |

#### 5.1.2 Domain Management (`/admin/domains.php`)
| Feature | Status | Description |
|---------|--------|-------------|
| View All Domains | ✅ | List all domains across all owners |
| Domain Statistics | ✅ | Email count, alias count per domain |
| License Usage | ✅ | Used/total licenses per domain owner |
| Filter by Owner | ✅ | View domains for specific owner |
| Link to Emails | ✅ | Quick link to domain's emails |

#### 5.1.3 Email Account Management (`/admin/emails.php`)
| Feature | Status | Description |
|---------|--------|-------------|
| View All Emails | ✅ | List all emails across all domains |
| Filter by Domain | ✅ | View emails for specific domain |
| Filter by Status | ✅ | Active/Suspended filter |
| Search | ✅ | Search by email address |
| Create Email | ✅ | Create new email with Google sync |
| License Info Display | ✅ | Show available licenses when creating |
| Cost Display | ✅ | Show cost per license when creating |
| No License Warning | ✅ | Block creation when no licenses available |
| Suspend Email | ✅ | Suspend in Google + DB |
| Activate Email | ✅ | Activate in Google + DB |
| Delete Email (OTP) | ✅ | OTP verification before delete |
| Reset Password | ✅ | Update password in Google |

#### 5.1.4 Alias Management (`/admin/aliases.php`)
| Feature | Status | Description |
|---------|--------|-------------|
| View All Aliases | ✅ | List all aliases across all domains |
| Filter by Domain | ✅ | View aliases for specific domain |
| Create Alias | ✅ | Create alias with Google sync |
| Delete Alias | ✅ | Delete from Google + DB |

#### 5.1.5 Activity Logs (`/admin/activity_logs.php`)
| Feature | Status | Description |
|---------|--------|-------------|
| View All Activities | ✅ | Paginated activity log |
| Filter by User Type | ✅ | Admin/Client filter |
| Filter by Action | ✅ | Search within action descriptions |
| Filter by Date Range | ✅ | From/to date filters |
| Statistics | ✅ | Total, today, last 7 days counts |

#### 5.1.6 OTP Handler (`/admin/otp_handler.php`)
| Feature | Status | Description |
|---------|--------|-------------|
| Send Delete OTP | ✅ | Generate and email 6-digit OTP |
| Verify Delete OTP | ✅ | Validate OTP and proceed with delete |
| OTP Expiry | ✅ | 5-minute expiration |
| Rate Limiting | ✅ | One active OTP per admin per action |

### 5.2 Client Portal Features

#### 5.2.1 Client Dashboard (`/manage/client_dashboard.php`)
| Feature | Status | Description |
|---------|--------|-------------|
| Domain Overview | ✅ | View assigned domain(s) |
| Email Statistics | ✅ | Total emails in their domain |
| License Usage | ✅ | Their license allocation |

#### 5.2.2 Email Management (`/manage/client_emails.php`)
| Feature | Status | Description |
|---------|--------|-------------|
| View Domain Emails | ✅ | List emails in their domain only |
| Create Email | ✅ | Create within license limits |
| Suspend/Activate | ✅ | Toggle email status |
| Delete Email | ✅ | Remove from Google + DB |
| Reset Password | ✅ | Update password in Google |

#### 5.2.3 Alias Management (`/manage/client_aliases.php`)
| Feature | Status | Description |
|---------|--------|-------------|
| View Domain Aliases | ✅ | List aliases in their domain only |
| Create Alias | ✅ | Create with Google sync |
| Delete Alias | ✅ | Remove from Google + DB |

### 5.3 Google API Functions (`/google-api/google_functions.php`)

| Function | Status | Description |
|----------|--------|-------------|
| `getGoogleClient()` | ✅ | Initialize service account client |
| `fetchGoogleWorkspaceUsers()` | ✅ | Fetch all users from a domain |
| `createGoogleWorkspaceUser()` | ✅ | Create new user in Google |
| `deleteGoogleWorkspaceUser()` | ✅ | Delete user from Google |
| `toggleGoogleUserStatus()` | ✅ | Suspend/activate user |
| `updateGoogleUserPassword()` | ✅ | Reset user password |
| `addGoogleUserAlias()` | ✅ | Add alias to user |
| `removeGoogleUserAlias()` | ✅ | Remove alias from user |
| `fetchGoogleUserAliases()` | ✅ | Get user's aliases |

---

## 6. Phase B: Security Hardening

**Status:** 🔲 PLANNED

### 6.1 Authentication & Authorization

#### 6.1.1 Enhanced Login Security
| Feature | Priority | Description |
|---------|----------|-------------|
| Login Rate Limiting | High | Max 5 attempts per 15 minutes |
| Account Lockout | High | Lock after 10 failed attempts |
| Session Timeout | High | Auto-logout after 30 min inactivity |
| Secure Session Config | High | HttpOnly, Secure, SameSite cookies |
| Password Requirements | Medium | Min 12 chars, complexity rules |
| Two-Factor Auth (2FA) | Medium | TOTP-based 2FA for admin login |

#### 6.1.2 Authorization Improvements
| Feature | Priority | Description |
|---------|----------|-------------|
| Role-Based Access | High | Define granular permissions |
| API Key Auth | Medium | For programmatic access |
| Audit All Access | High | Log all authentication events |

### 6.2 Input Validation & Sanitization

#### 6.2.1 SQL Injection Prevention
| Feature | Priority | Description |
|---------|----------|-------------|
| Prepared Statements | High | All queries use parameterized statements |
| Input Type Validation | High | Validate integers, emails, domains |
| Output Encoding | High | htmlspecialchars() on all output |

#### 6.2.2 XSS Prevention
| Feature | Priority | Description |
|---------|----------|-------------|
| Content Security Policy | High | Strict CSP headers |
| Input Sanitization | High | Strip/escape HTML in inputs |
| JSON Output Escaping | High | Proper JSON encoding |

### 6.3 Code Security Audit

#### 6.3.1 Critical Issues to Address
| Issue | Priority | Location | Resolution |
|-------|----------|----------|------------|
| eval() in index.php | Critical | `/index.php` | Remove or replace with safe alternative |
| Debug Files | High | `/admin/test_db.php` | Remove from production |
| Error Disclosure | High | Various | Disable display_errors in production |
| Credentials in Code | High | Config files | Move to environment variables |

#### 6.3.2 Infrastructure Security
| Feature | Priority | Description |
|---------|----------|-------------|
| HTTPS Only | Critical | Force SSL/TLS on all pages |
| HSTS Header | High | Strict-Transport-Security |
| X-Frame-Options | High | Prevent clickjacking |
| X-Content-Type | Medium | Prevent MIME sniffing |

### 6.4 OTP Security Enhancements

| Feature | Priority | Description |
|---------|----------|-------------|
| Extend OTP to All Deletes | High | Require OTP for alias deletion too |
| OTP Rate Limiting | High | Max 3 OTP requests per hour |
| Failed OTP Lockout | High | Lock after 5 failed OTP attempts |
| OTP for Bulk Operations | Medium | Require OTP for bulk actions |

---

## 7. Phase C: Advanced Features

**Status:** 🔲 PLANNED

### 7.1 Email Forwarding

#### 7.1.1 Internal Forwarding (Aliases)
| Feature | Priority | Description |
|---------|----------|-------------|
| Current Implementation | ✅ Done | Aliases forward to primary email |

#### 7.1.2 External Forwarding (Gmail API)
| Feature | Priority | Description |
|---------|----------|-------------|
| Add Gmail API Scope | High | `gmail.settings.basic` scope |
| Forward to External Email | High | Set up forwarding to any address |
| Verification Workflow | High | Handle address verification |
| Per-User Impersonation | High | API calls as each user |

**Technical Requirements:**
```php
// New scope required in config.php
'https://www.googleapis.com/auth/gmail.settings.basic'

// New function required
function setUserForwarding($userEmail, $forwardTo) {
    $client = getGoogleClientForUser($userEmail); // Impersonate user
    $gmail = new Google_Service_Gmail($client);
    // ... implementation
}
```

#### 7.1.3 Forwarding UI
| Feature | Priority | Description |
|---------|----------|-------------|
| Forward Settings Tab | Medium | Per-email forwarding configuration |
| Forward Destination Input | Medium | Input for external email |
| Verification Status | Medium | Show pending/verified status |
| Enable/Disable Toggle | Medium | Turn forwarding on/off |

### 7.2 Bulk Operations

| Feature | Priority | Description |
|---------|----------|-------------|
| Bulk Create Emails | Medium | CSV import for email creation |
| Bulk Suspend/Activate | Medium | Select multiple emails for action |
| Bulk Delete | Low | Select multiple for deletion (with OTP) |
| Bulk Password Reset | Low | Reset passwords for multiple accounts |

### 7.3 Email Groups/Distribution Lists

| Feature | Priority | Description |
|---------|----------|-------------|
| Create Groups | Medium | Google Workspace groups |
| Manage Members | Medium | Add/remove group members |
| Group Aliases | Low | Aliases for groups |

### 7.4 Storage Management

| Feature | Priority | Description |
|---------|----------|-------------|
| View Storage Usage | Medium | Show used/allocated storage per user |
| Storage Quotas | Medium | Set custom storage limits |
| Storage Alerts | Low | Notify when approaching limits |

### 7.5 Email Signature Management

| Feature | Priority | Description |
|---------|----------|-------------|
| Template Signatures | Low | Create signature templates |
| Apply to Users | Low | Bulk apply signatures |
| Variables | Low | Dynamic variables (name, title, etc.) |

---

## 8. Phase D: Client Self-Service Enhancements

**Status:** 🔲 PLANNED

### 8.1 Client Dashboard Improvements

| Feature | Priority | Description |
|---------|----------|-------------|
| Usage Analytics | Medium | Charts for email usage |
| Storage Overview | Medium | Visual storage consumption |
| Activity Timeline | Medium | Recent actions in their domain |
| License Alerts | Medium | Notify when approaching limits |

### 8.2 Client Activity Logs

| Feature | Priority | Description |
|---------|----------|-------------|
| View Own Logs | Medium | Clients see their own activity |
| Export Logs | Low | Download activity as CSV |
| Log Retention | Low | Configurable retention period |

### 8.3 Client OTP Verification

| Feature | Priority | Description |
|---------|----------|-------------|
| OTP for Client Deletes | High | Require OTP for client deletions |
| Client 2FA | Medium | Optional 2FA for client login |

### 8.4 Self-Service Password Reset

| Feature | Priority | Description |
|---------|----------|-------------|
| Forgot Password | Medium | Email-based password reset |
| Security Questions | Low | Alternative recovery option |

### 8.5 Client Notifications

| Feature | Priority | Description |
|---------|----------|-------------|
| Email Notifications | Medium | Notify on account creation |
| License Warnings | Medium | Alert when near license limit |
| Webhook Integrations | Low | POST to client endpoints |

---

## 9. Technical Specifications

### 9.1 Environment Requirements

| Component | Requirement |
|-----------|-------------|
| PHP | 8.0+ with mysqli, curl, json extensions |
| MySQL | 5.7+ or MariaDB 10.3+ |
| Web Server | Apache 2.4+ or Nginx 1.18+ |
| SSL | Required for production |
| Google API | PHP Client Library v2.x |

### 9.2 Google Workspace Requirements

| Requirement | Details |
|-------------|---------|
| Service Account | JSON credentials file |
| Domain-Wide Delegation | Enabled in Admin Console |
| Admin Email | Super admin for impersonation |
| API Scopes | Directory API, Gmail API (Phase C) |

### 9.3 Required OAuth Scopes

**Current (Phase A):**
```
https://www.googleapis.com/auth/admin.directory.user
https://www.googleapis.com/auth/admin.directory.user.alias
https://www.googleapis.com/auth/admin.directory.orgunit
https://www.googleapis.com/auth/admin.directory.domain
https://www.googleapis.com/auth/admin.reports.usage.readonly
```

**Phase C Addition:**
```
https://www.googleapis.com/auth/gmail.settings.basic
https://www.googleapis.com/auth/gmail.settings.sharing
```

---

## 10. Database Schema

### 10.1 Core Tables

```sql
-- Domain Owners (Clients)
CREATE TABLE domain_owners (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Domains
CREATE TABLE domains (
    id INT PRIMARY KEY AUTO_INCREMENT,
    domain_owner_id INT NOT NULL,
    domain_name VARCHAR(255) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_owner_id) REFERENCES domain_owners(id)
);

-- Email Accounts
CREATE TABLE email_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    domain_id INT NOT NULL,
    email_address VARCHAR(255) NOT NULL UNIQUE,
    storage_limit INT DEFAULT 30720, -- MB
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id)
);

-- Email Aliases
CREATE TABLE email_aliases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email_account_id INT NOT NULL,
    alias_address VARCHAR(255) NOT NULL UNIQUE,
    forward_to VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (email_account_id) REFERENCES email_accounts(id)
);

-- Licenses
CREATE TABLE licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    domain_owner_id INT NOT NULL,
    total_licenses INT DEFAULT 0,
    used_licenses INT DEFAULT 0,
    cost_per_license DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_owner_id) REFERENCES domain_owners(id)
);

-- OTP Codes
CREATE TABLE otp_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    domain_owner_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    action_data JSON,
    expires_at DATETIME NOT NULL,
    verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Activity Logs
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_type ENUM('admin', 'client') NOT NULL,
    user_email VARCHAR(255),
    action VARCHAR(500) NOT NULL,
    email_affected VARCHAR(255),
    domain_affected VARCHAR(255),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin Users
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 10.2 Indexes

```sql
CREATE INDEX idx_emails_domain ON email_accounts(domain_id);
CREATE INDEX idx_emails_status ON email_accounts(status);
CREATE INDEX idx_aliases_email ON email_aliases(email_account_id);
CREATE INDEX idx_logs_created ON activity_logs(created_at);
CREATE INDEX idx_logs_user_type ON activity_logs(user_type);
CREATE INDEX idx_otp_expires ON otp_codes(expires_at);
```

---

## 11. API Integration

### 11.1 Google Workspace Admin SDK

#### Error Handling Pattern
```php
try {
    $result = googleApiCall();
    if ($result['success']) {
        // Update database
        updateDatabase();
        return ['success' => true];
    }
} catch (Google_Service_Exception $e) {
    logError($e->getMessage());
    return ['success' => false, 'error' => $e->getMessage()];
}
```

#### Sync Recovery
```php
// If Google succeeds but DB fails
if ($google_result['success'] && !$db_result) {
    error_log("CRITICAL SYNC ERROR: " . $operation . " - " . $identifier);
    // TODO: Queue for manual review or auto-rollback
}
```

### 11.2 Internal API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/admin/email_handler.php` | POST | Email CRUD operations |
| `/admin/alias_handler.php` | POST | Alias CRUD operations |
| `/admin/otp_handler.php` | POST | OTP send/verify |
| `/manage/email_handler.php` | POST | Client email operations |
| `/manage/alias_handler.php` | POST | Client alias operations |

---

## 12. Testing Requirements

### 12.1 Phase A Test Checklist

#### Admin Portal
- [ ] Create email → appears in Google + DB
- [ ] Suspend email → suspended in Google + DB
- [ ] Activate email → active in Google + DB
- [ ] Delete email → OTP sent, verified, removed from Google + DB
- [ ] Reset password → updated in Google
- [ ] Add alias → created in Google + DB
- [ ] Delete alias → removed from Google + DB
- [ ] View all emails across all domains
- [ ] Filter by domain, status, search
- [ ] License info displays correctly
- [ ] Activity logs record all actions

#### Client Portal
- [ ] Create email → within license limits only
- [ ] Client can only see their domain's emails
- [ ] All operations sync to Google
- [ ] License usage updates correctly

### 12.2 Phase B Security Tests

- [ ] SQL injection attempts blocked
- [ ] XSS attempts sanitized
- [ ] CSRF tokens validated
- [ ] Session hijacking prevented
- [ ] Brute force login blocked
- [ ] OTP rate limiting works
- [ ] All debug files removed
- [ ] Error messages don't disclose internals

### 12.3 Phase C Integration Tests

- [ ] Gmail API scope authorized
- [ ] User impersonation works
- [ ] Forwarding address verification flow
- [ ] Bulk operations maintain consistency
- [ ] Group management syncs correctly

---

## Appendix A: File Structure

```
/well-known/
├── admin/
│   ├── dashboard.php          # Admin dashboard
│   ├── domains.php            # Domain management
│   ├── emails.php             # Email management
│   ├── aliases.php            # Alias management
│   ├── activity_logs.php      # Activity log viewer
│   ├── email_handler.php      # Email operations API
│   ├── alias_handler.php      # Alias operations API
│   ├── otp_handler.php        # OTP send/verify API
│   ├── init.php               # Admin initialization
│   ├── login.php              # Admin login
│   └── logout.php             # Admin logout
├── manage/
│   ├── client_dashboard.php   # Client dashboard
│   ├── client_emails.php      # Client email management
│   ├── client_aliases.php     # Client alias management
│   ├── email_handler.php      # Client email operations
│   ├── alias_handler.php      # Client alias operations
│   ├── init.php               # Client initialization
│   ├── login.php              # Client login
│   └── logout.php             # Client logout
└── index.php                  # Landing page

/google-api/
├── config.php                 # Configuration
├── google_functions.php       # Google API functions
├── credentials.json           # Service account key
└── google-api/                # Google PHP client library
    └── vendor/
```

---

## Appendix B: Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-12-30 | System | Initial PRD creation |

---

## Appendix C: Glossary

| Term | Definition |
|------|------------|
| MSP | Managed Service Provider |
| OTP | One-Time Password |
| Domain Owner | Client organization that owns one or more domains |
| License | Permission to create one email account |
| Alias | Alternative email address forwarding to primary |
| Service Account | Google account for server-to-server API access |
| Domain-Wide Delegation | Permission for service account to impersonate users |
