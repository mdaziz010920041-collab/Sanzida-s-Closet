# Security Audit

Date: 2026-09-21
Scope: PHP application, admin panel, authentication, checkout/payment flows, CMS uploads, and Apache deployment rules.

## Summary

The application uses PDO with emulated prepares disabled, password hashing, CSRF tokens, token-backed database sessions, output escaping in rendered views, and role-based admin authorization. The audit found no confirmed SQL injection or reflected/stored XSS path in the reviewed PHP surfaces.

## Findings and Remediation

### High

- **Project-root file exposure risk**: a root document root could expose `.env`, VCS metadata, schema, configuration, or include paths. Added Apache deny rules in `.htaccess` and retained the public document-root protections.
- **Audit-log authorization gap**: the audit module previously accepted any authenticated admin. Added the dedicated `audit.view` permission in `database/schema.sql` and migration `009_audit_permission.sql`.

### Medium

- **Admin login brute-force gap**: admin login did not use the existing login-attempt limiter. Admin authentication now records and enforces email/IP throttling using the existing `auth_login_attempts` table.
- **Open redirect risk**: login continuation values used a permissive leading-slash check. Added strict same-origin path validation and rejected protocol-relative, backslash, NUL, and scheme-prefixed values.
- **CMS URL injection risk**: CMS CTA, social, and banner links were persisted without URL-scheme validation. They now allow local paths/fragments or HTTP(S) URLs and reject `javascript:`, `data:`, protocol-relative, and malformed values.
- **Session hardening**: disabled URL session IDs, enabled strict mode and cookie-only sessions, set HttpOnly/SameSite/path cookie attributes, rotated IDs on authentication, and clear admin session state on logout.

### Low / Informational

- Added `Permissions-Policy` and conditional HSTS headers while retaining nosniff, frame, and referrer protections.
- Password reset responses remain generic to avoid account enumeration. Passwords use `password_hash()` and `password_verify()`.
- Product/order/customer access paths use owner-scoped queries or admin permission middleware.
- CMS uploads validate MIME type and image structure, enforce a 5 MB limit, use random names, and deny executable extensions in the upload directory.

## Control Review

- SQL injection: reviewed dynamic SQL; values are bound and dynamic identifiers are allowlisted.
- XSS: rendered user/database values use `escape_html()`; JSON is encoded with JSON encoding flags.
- CSRF: state-changing account, checkout, cart, payment, return, admin, and CMS requests require the session CSRF token.
- Authentication bypass: customer and admin routes use `auth_current_user()` / `admin_require()`; normal customers do not satisfy admin sessions.
- Authorization/IDOR: account and order queries scope records to the authenticated user; admin modules map to permissions.
- Session fixation/hijacking: session IDs rotate after login/reset/logout; server-side session tokens are random, hashed, revocable, and expiry checked.
- File uploads/path traversal: CMS images are MIME-validated and saved under generated names; source paths are not accepted from upload input.
- Error disclosure: production exception output is generic and details are logged server-side.
- Rate limiting: customer and admin login attempts are limited by email and IP over a rolling window.
- Payment security: webhook HMAC, provider/payment/order/amount checks, and duplicate event handling are present.

## Tests Performed

- PHP lint sweep over the project, with explicit checks for security-critical files.
- Editor diagnostics for touched PHP files.
- Static search for SQL construction, POST handlers, redirects, uploads, and output sites.
- Direct checks of redirect validation with local, protocol-relative, external, and backslash-containing inputs.

## Deployment Notes

- Apply `database/migrations/009_audit_permission.sql` to existing databases.
- Serve the application over HTTPS in production so secure cookies and HSTS are active.
- Keep `.env` outside the web root where possible; Apache deny rules are defense in depth, not a substitute for correct hosting configuration.
- Configure the server to execute PHP only where intended and prevent directory listing.
- Runtime database, browser, and external payment-provider tests were not available in this environment.
