# Quasar File Replacer 1.1.0 — hardened update

## Package contents
- `quasar-file-replacer.php`
- `admin-ui.php`
- `CHANGELOG.md`

## Installation
1. Back up the current plugin directory and database.
2. Keep the existing `js/` and `css/` directories.
3. Replace the two PHP files with the versions in this package.
4. Open **Quasar File Replacer** in WordPress administration.
5. Test with a non-critical PDF first and verify that its existing URL still serves the new PDF.

## Behavior change
Version 1.1.0 intentionally supports PDF attachments only. Existing non-PDF targets remain in the saved option but cannot be replaced through this version. Select PDF attachments as targets.

## Backups and web-server protection
Original files are backed up under `wp-content/qfr-private-backups`. The plugin creates an `index.php` and Apache `.htaccess` denial rules in that directory. This protects direct HTTP access on Apache when `.htaccess` overrides are enabled.

If the site uses Nginx, add an equivalent server-level deny rule for `/wp-content/qfr-private-backups/` before using the plugin. Filesystem permissions alone are not a substitute for web-server access controls.

## Validation performed
Both PHP files passed `php -l` syntax checks. This is a static syntax check, not a substitute for testing on a staging copy of the target WordPress site.
