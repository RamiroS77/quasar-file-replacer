# Changelog — Quasar File Replacer

## 1.1.0 — Security hardening

### Security
- Restricted replacement targets to existing Media Library attachments whose WordPress MIME type is `application/pdf`.
- Restricted replacement uploads to `.pdf` files with a detected `application/pdf` MIME type and a PDF signature (`%PDF-`) in the first 1,024 bytes.
- Added a default 25 MiB upload limit, configurable through the `qfr_max_upload_bytes` filter.
- Removed the use of `wp_handle_upload()` for replacement files; uploaded files are validated and staged directly without allowing WordPress's general upload handler to choose a destination.
- The server resolves the destination from the attachment ID; no destination path is accepted from the browser.
- Added canonical path checks to ensure the destination is within the WordPress uploads directory.
- Rejects symbolic links, non-regular files, missing files, and targets outside the uploads directory.
- Requires the current user to have `manage_options` and `edit_post` permission for the selected attachment.
- Added a bounded audit log (`qfr_audit_log`) containing timestamp, user ID, attachment ID, result, and outcome detail.
- Added a protected backup directory under `wp-content/qfr-private-backups`, with Apache `.htaccess` denial rules and restrictive filesystem permissions.
- Added a same-directory staging file and `rename()` commit to reduce the risk of partially written target files.
- Preserves the target file's existing permission bits where possible.

### Administration
- Added success and failure notices after saving.
- Added a maximum of 50 submitted target rows.
- Added an explicit PDF file picker hint (`accept=".pdf,application/pdf"`).
- Updated the interface copy to explain PDF-only behavior and backup location.

### Compatibility
- Retains the existing `qfr_file_data` option and row field names.
- Retains the existing `qfr_add_row` AJAX action and nonce name.
- Retains the existing admin-post action (`qfr_save`).
- Retains the original attachment URL and filename by replacing the file contents at the existing path.
- Existing `js/admin.js` and `css/admin.css` are not modified.

### Deployment notes
- Replace the two PHP files in the existing plugin directory; keep the existing `js/` and `css/` directories.
- Backups are written to `wp-content/qfr-private-backups`.
- Apache access is denied by the generated `.htaccess`. On Nginx, add an equivalent deny rule for `/wp-content/qfr-private-backups/` before using the backup feature.
- The plugin intentionally no longer replaces images or other non-PDF files.
