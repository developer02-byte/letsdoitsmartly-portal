# Unified Email Management Portal - Master Plan

## Overview
Single portal with role-based access (Super Admin + Client Admin) instead of two separate portals.

---

## Architecture Summary

```
┌─────────────────────────────────────────────────────────┐
│                    UNIFIED PORTAL                        │
├─────────────────────────────────────────────────────────┤
│  Login → Check Role → Load Permissions → Show Content   │
├──────────────────────┬──────────────────────────────────┤
│    SUPER ADMIN       │         CLIENT ADMIN             │
│  - All domains       │  - Their domain(s) only          │
│  - All users         │  - Their emails/aliases          │
│  - System settings   │  - Their profile                 │
│  - Full activity log │  - Their activity log            │
└──────────────────────┴──────────────────────────────────┘
```

---

## Phase 1: Core Portal (Current Focus)

### Database Schema

```sql
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
```

### File Structure

```
/portal/
├── index.php                 # Login page
├── logout.php                # Logout handler
├── dashboard.php             # Role-based dashboard
│
├── emails.php                # Email accounts list
├── email_handler.php         # Email CRUD + Google sync
│
├── aliases.php               # Email aliases list
├── alias_handler.php         # Alias CRUD operations
│
├── domains.php               # Domains management
├── domain_handler.php        # Domain operations
│
├── users.php                 # User management (admin only)
├── user_handler.php          # User CRUD operations
│
├── settings.php              # Profile & settings
├── settings_handler.php      # Settings operations
│
├── activity_logs.php         # Activity log viewer
├── sync_handler.php          # Google sync AJAX
├── otp_handler.php           # OTP operations
│
├── forgot_password.php       # Password reset request
├── reset_password.php        # Password reset form
├── password_handler.php      # Password operations
│
├── includes/
│   ├── config.php            # App configuration
│   ├── db.php                # Database connection
│   ├── auth.php              # Authentication & session
│   ├── permissions.php       # Role-based access control
│   ├── google_functions.php  # Google API functions
│   ├── helpers.php           # Utility functions
│   └── navigation.php        # Dynamic sidebar menu
│
├── assets/
│   ├── css/
│   │   └── style.css         # Main stylesheet
│   └── js/
│       └── app.js            # Main JavaScript
│
└── templates/
    ├── header.php            # Common header
    ├── footer.php            # Common footer
    └── sidebar.php           # Sidebar component
```

### Features - Phase 1
- [ ] Unified login (single entry point)
- [ ] Role-based dashboard
- [ ] Email management (CRUD + Google sync)
- [ ] Alias management
- [ ] Domain management
- [ ] User management (super_admin only)
- [ ] Smart Cache sync from Google
- [ ] Activity logging
- [ ] OTP verification for deletes
- [ ] Password reset flow

---

## Phase 2: Licensing & Billing (DEFERRED)

### Database Tables - DEFERRED
```sql
pricing_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_name VARCHAR(50) NOT NULL,
    price_per_license DECIMAL(10,2) NOT NULL,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)

licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    domain_owner_id INT NOT NULL,
    plan_id INT NULL,
    total_licenses INT DEFAULT 0,
    used_licenses INT DEFAULT 0,
    cost_per_license DECIMAL(10,2) DEFAULT 0,
    last_renewal_date DATE NULL,
    next_renewal_date DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_owner_id) REFERENCES domain_owners(id),
    FOREIGN KEY (plan_id) REFERENCES pricing_plans(id)
)
```

### Features - Phase 2
- [ ] Pricing plans management
- [ ] License allocation per client
- [ ] License usage tracking
- [ ] License limit enforcement on email creation
- [ ] Renewal date tracking
- [ ] Billing reports

---

## Deployment Strategy

### Git-based Workflow
```
Local Dev → GitHub (Private Repo) → cPanel Git Pull → Live
     │              │                      │
   Claude      Central Hub            Production
   + You       (version control)      (live site)
```

### Branch Strategy
- `main` - Production (auto-deploys to live)
- `develop` - Staging/testing
- `feature/*` - Individual features

### Repository Setup
1. Create GitHub account (if needed)
2. Create private repo: `letsdoitsmartly-email-portal`
3. Initialize local git, push to GitHub
4. Connect cPanel Git Version Control to repo
5. Set up deploy path: `/home4/letsdoitadmin/public_html/portal`

---

## Implementation Steps

### Step 1: Local Project Setup
```
d:\Projects\Letdoitsmartly\portal\    ← New unified portal folder
```

### Step 2: Create Core Files (in order)
1. `includes/config.php` - Configuration
2. `includes/db.php` - Database connection
3. `includes/auth.php` - Authentication
4. `includes/permissions.php` - Access control
5. `includes/helpers.php` - Utility functions
6. `includes/navigation.php` - Dynamic menu
7. `templates/header.php`, `footer.php`, `sidebar.php`
8. `assets/css/style.css` - Styling
9. `index.php` - Login page
10. `logout.php` - Logout
11. `dashboard.php` - Main dashboard

### Step 3: Feature Pages
12. `emails.php` + `email_handler.php`
13. `aliases.php` + `alias_handler.php`
14. `domains.php` + `domain_handler.php`
15. `users.php` + `user_handler.php`
16. `settings.php` + `settings_handler.php`
17. `activity_logs.php`
18. `sync_handler.php`
19. `otp_handler.php`

### Step 4: Password Reset
20. `forgot_password.php`
21. `reset_password.php`
22. `password_handler.php`

### Step 5: Google Integration
23. `includes/google_functions.php` - Adapt from existing

---

## Technical Stack
- PHP 8.x
- MySQL 8.x
- Bootstrap 5.3
- Bootstrap Icons
- Google Workspace Admin SDK
- Service Account Authentication

---

## Access Control Matrix

| Page | Super Admin | Client Admin |
|------|-------------|--------------|
| dashboard.php | All stats | Their domain stats |
| emails.php | All emails | Their domain emails |
| aliases.php | All aliases | Their domain aliases |
| domains.php | CRUD all | View their domain |
| users.php | CRUD all | HIDDEN |
| activity_logs.php | All logs | Their logs |
| settings.php | System + Profile | Profile only |
| sync_handler.php | Sync all | Sync their domain |

---

## Notes
- Two roles only: super_admin, client_admin
- Single codebase, role-based permissions
- Google API first, then DB sync pattern
- Licensing deferred to Phase 2
