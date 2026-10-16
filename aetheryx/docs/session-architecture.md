# Independent browser-tab authentication

ÆTHERYX uses a separate opaque authentication token for each browser tab. This is necessary because standard PHP session cookies are shared by tabs in one browser profile.

## Request and storage model

- A successful login returns a cryptographically random 256-bit token. The browser keeps it in that tab's `sessionStorage` and sends it to same-origin APIs in the `X-Aetheryx-Tab-Session` header.
- PHP does not use a shared session cookie as proof of identity. Each protected request hashes the presented token and resolves the user and role from `tab_auth_sessions` and `users`.
- The database stores only the SHA-256 token hash. Tokens expire after 12 hours and are rejected after 30 minutes without activity.
- Logout revokes only the token for the current tab. Logging out does not revoke other tabs' tokens.
- Logging into an account creates a fresh tab context without revoking any token sent with the login request. This prevents logging into Admin in a duplicated tab from signing the original Hero tab out before duplicated-tab isolation runs; the unused prior context expires through the normal idle and absolute time limits.
- Password changes rotate the current administrator tab's token; other tab contexts remain independent.
- A copied tab can inherit its opener's `sessionStorage`. The synchronous frontend bootstrap uses `BroadcastChannel` to compare token fingerprints (never raw tokens) and calls the authenticated tab-session endpoint to create a distinct token before allowing authenticated requests.

The raw token is intentionally not stored in `localStorage`, URLs, or broadcast messages. It is readable by same-origin JavaScript, so this design does not protect against an XSS vulnerability; keep frontend dependencies and output escaping secure.

## Browser and deployment requirements

Independent tab authentication requires `sessionStorage`, `BroadcastChannel`, and Web Crypto in a secure browser context. Use HTTPS in deployment; `http://localhost` is suitable for Laragon development. Browsers that lack the required support cannot log in through the application bootstrap. Same-origin API requests must remain on the ÆTHERYX origin.

Separate browser profiles or private windows already have independent cookie/storage partitions, but the implementation does not depend on those partitions. The browser still controls whether a newly opened page inherits `sessionStorage`; the bootstrap isolates pages that inherit the same token. An explicit logout in one tab affects only that tab, while duplicated tabs receive distinct server contexts.

## Database setup

For an existing database, run `database/tab_auth_sessions_migration.sql` against the database configured in `backend/config/database.php` (or import it through phpMyAdmin after selecting that database). Fresh-install definitions are included in `database/schema.sql` and `database/aiven_schema.sql`.

## Verification

The local Laragon test verified two different active accounts resolve to their own server-side user IDs and roles, the cloned-tab endpoint creates an independent token, signing out one context leaves another authenticated, expired/missing tokens are rejected, and a Hero is denied an admin endpoint. A same-origin browser test with two pages sharing one seeded token verified the bootstrap forked the second page into a distinct token and that signing out the first left the second active.

The local database currently has no active Villain account, so an actual Hero/Villain login pair and Villain-specific data access were not available to test. Login with real credentials, browser lifecycle/idle-time expiry, and production HTTPS behavior should be included in deployment QA.
