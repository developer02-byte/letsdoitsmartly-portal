# cPanel Manual Cleanup Guide

**Server:** bh-in-32.webhostbox.net
**Base Path:** /home4/letsdoitadmin/public_html
**Date Generated:** 2026-01-08

---

## Summary

| Location | Files to Delete |
|----------|-----------------|
| public_html (root) | 5 files |
| portal/ | 24 files |
| portal/install/ | 19 files |
| **Total** | **48 files** |

---

## 1. public_html Root (5 files)

Delete these files from `/home4/letsdoitadmin/public_html/`:

| # | File | Reason |
|---|------|--------|
| 1 | `check_paths.php` | Debug script - says "DELETE AFTER DEBUGGING" |
| 2 | `deploy.php` | Old GitHub webhook deploy - replaced by cPanel API |
| 3 | `deploy_config.php` | Config for old webhook |
| 4 | `server_test.php` | Test script |
| 5 | `simple_test.php` | Test script |

---

## 2. portal/ Folder (24 files)

Delete these files from `/home4/letsdoitadmin/public_html/portal/`:

| # | File | Reason |
|---|------|--------|
| 1 | `_test_upload.php` | Test upload script |
| 2 | `api_check.php` | API test - returns 403 |
| 3 | `api_test_temp.php` | Temporary API test |
| 4 | `check_actions.php` | Debug script |
| 5 | `check_admin.php` | Admin check debug |
| 6 | `check_auth_logs.php` | Auth logs debug |
| 7 | `check_sync_lock.php` | Sync lock debug |
| 8 | `check_sync_status.php` | Sync status debug |
| 9 | `check_sync_times.php` | Sync times debug |
| 10 | `cron_debug.php` | Cron debug script |
| 11 | `debug_email_handler.php` | Email debug - says "DELETE AFTER USE" |
| 12 | `debug_paths.php` | Path debug script |
| 13 | `debug_session.php` | Session debug script |
| 14 | `error_log` | PHP error log file |
| 15 | `run_migration_temp.php` | Temporary migration runner |
| 16 | `server_time.php` | Server time utility |
| 17 | `session_test.php` | Session test script |
| 18 | `test_auth_logging.php` | Auth logging test |
| 19 | `test_direct_insert.php` | Direct DB insert test |
| 20 | `test_logactivity.php` | Activity log test |
| 21 | `test_login_flow.php` | Login flow test |
| 22 | `test_login_minimal.php` | Minimal login test |
| 23 | `test_sync_temp.php` | Temporary sync test |
| 24 | `verify.php` | Verification utility |

---

## 3. portal/install/ Folder (19 files)

Delete these files from `/home4/letsdoitadmin/public_html/portal/install/`:

| # | File | Reason |
|---|------|--------|
| 1 | `admin_setup.php` | One-time admin setup - has hardcoded DB creds |
| 2 | `cleanup_activity_logs.php` | One-time cleanup script |
| 3 | `cleanup_old_logs.php` | One-time cleanup script |
| 4 | `debug_login.php` | Debug script - says "SECURITY: Delete after use!" |
| 5 | `error_log` | PHP error log file |
| 6 | `quick_cleanup.php` | One-time cleanup utility |
| 7 | `run_activity_logs_migrations.php` | One-time migration runner |
| 8 | `run_activity_logs_migrations_embedded.php` | Redundant migration runner |
| 9 | `run_migration.php` | Migration runner - says "can be deleted" |
| 10 | `run_migration_008.php` | One-time migration runner |
| 11 | `run_new_migrations.php` | Migration runner - says "completed successfully" |
| 12 | `run_sync_lock_migration.php` | One-time migration runner |
| 13 | `setup_credentials.php` | Setup script - says "DELETE IMMEDIATELY AFTER RUNNING!" |
| 14 | `setup_phpmailer.php` | One-time PHPMailer setup |
| 15 | `test_email.php` | Email test - says "DELETE THIS FILE AFTER TESTING!" |
| 16 | `test_google.php` | Google API test |
| 17 | `test_otp.php` | OTP test script |
| 18 | `test_otp_debug.php` | OTP debug script |
| 19 | `update_activity_categories.php` | One-time update script |

---

## Files to KEEP

### portal/install/ - DO NOT DELETE:
- `add_last_sync_column.php` - Utility script
- `reset_admin_password.php` - Password reset utility
- `schema.sql` - Database schema reference

### portal/ - Production files (keep all others not listed above)

---

## Quick Delete Commands (cPanel File Manager)

If using cPanel File Manager, you can select multiple files and delete them. Here are the patterns:

### In portal/:
```
Select all files matching: *test*.php, *debug*.php, *check*.php, api_check.php, verify.php, server_time.php, error_log, *_temp.php
```

### In portal/install/:
```
Select all files matching: test_*.php, debug_*.php, setup_*.php, run_*.php, cleanup_*.php, quick_cleanup.php, admin_setup.php, update_activity_categories.php, error_log
```

---

## Verification After Cleanup

After deleting, these should remain in each folder:

### public_html/ should have:
- `cgi-bin/` (directory)
- `docs/` (directory)
- `google-api/` (directory)
- `portal/` (directory)

### portal/ should have:
- `assets/` (directory)
- `includes/` (directory)
- `install/` (directory)
- `sessions/` (directory)
- `templates/` (directory)
- `activity_logs.php`
- `activity_logs_handler.php`
- `activity_logs_unified.php`
- `alias_handler.php`
- `aliases.php`
- `cron_archive_logs.php`
- `cron_sync.php`
- `dashboard.php`
- `domain_handler.php`
- `domains.php`
- `email_handler.php`
- `emails.php`
- `failed_operations.php`
- `failed_ops_handler.php`
- `forgot_password.php`
- `index.php`
- `logout.php`
- `otp_handler.php`
- `password_handler.php`
- `reset_password.php`
- `settings.php`
- `settings_handler.php`
- `sync.php`
- `sync_handler.php`
- `sync_status.php`
- `user_handler.php`
- `users.php`

### portal/install/ should have:
- `add_last_sync_column.php`
- `reset_admin_password.php`
- `schema.sql`
- `migrations/` (directory - if exists)

---

## Notes

1. **Backup first**: Consider downloading these files before deletion if you want to keep copies
2. **Error logs**: The `error_log` files can be safely deleted - they regenerate automatically
3. **Migration files**: The SQL files in `migrations/` folder should be kept for reference
4. **Security**: Many of these files contain sensitive information (DB credentials, setup scripts) - deleting them improves security
