# Sanzida's Closet

The application is being migrated from PHP to Node.js for Vercel. PHP files remain in the repository as the migration source, but `.vercelignore` excludes them from deployment. The Node routes listed below are migrated; other store workflows are not yet available on Vercel.

## Local Node setup

Requirements: Node.js 20 or newer and a MySQL database with `database/schema.sql` and migrations `001` through `014` applied.

1. Run `npm install`.
2. Copy `.env.example` to `.env` and configure the MySQL connection, canonical `APP_URL`, and a unique `SESSION_SECRET` with at least 32 characters.
3. Run `npm run dev` and open `http://localhost:3000`.

`npm run build` performs Node syntax checks. The local server serves assets from `assets/` and uploads from `uploads/`. On Windows PowerShell systems that block `npm.ps1`, use `npm.cmd run build` and `npm.cmd run dev`.

## Vercel

Import the repository as a Node.js project and redeploy after every change to `vercel.json`, `api/`, `lib/`, or `assets/`. The project pins Vercel to Node 20 and uses `npm run build` as its deployment check. Configure `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `SESSION_SECRET`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `PAYMENT_PROVIDER`, `PAYMENT_MODE`, `PAYMENT_CURRENCY`, `RAZORPAY_KEY_ID`, `RAZORPAY_SECRET`, and `RAZORPAY_WEBHOOK_SECRET` in Vercel's server-side environment settings. Do not expose secrets with a `NEXT_PUBLIC_` prefix. Use a remotely reachable MySQL provider; `127.0.0.1` is only a local default. Set `PAYMENT_MODE=test` until sandbox checkout and webhook verification pass; only then switch to `live`.

Vercel routes requests to `api/index.js`; the handler also serves `/assets/` and `/uploads/` with safe path validation and explicit MIME types. Customer and guest sessions use signed cookies plus the existing `user_sessions` table, so `SESSION_SECRET` must be stable across deployments and at least 32 characters long. Verify `/api/health` after deployment; `status: "ok"` confirms database connectivity, while `status: "degraded"` means the app is serving but the database is unavailable or misconfigured.

## Migrated routes

- Storefront home, product catalogue, category catalogue, new arrivals, sale, and product detail pages.
- Customer login, registration, logout, basic account/order-history view, cart and wishlist APIs, cart page, search suggestions, and health endpoint.
- Checkout, inventory reservation, order confirmation/detail, cash-on-delivery, Razorpay order initiation/verification, and Razorpay payment webhooks.
- Customer profile, password and address forms; return policy and customer return requests; admin sign-in/dashboard and permission-gated read-only module lists.

Admin create/update actions, CMS editing, product/category writes, password-reset email delivery, and remaining PHP routes still require migration. Admin module lists are read-only for now. Guest order links use `guest_order_access` from migration `014_guest_order_access.sql`; apply it before guest checkout. Do not switch production traffic until all workflows are ported and tested against staging MySQL and payment-provider credentials.

## Database verification

With MySQL 8.x credentials configured, import and verify the schema with:

```text
mysql --default-character-set=utf8mb4 -u YOUR_USER -p < database/schema.sql
mysql -u YOUR_USER -p -e "USE sanzidas_closet; SHOW TABLES;"
```

Create the first admin account from the server terminal after importing the schema:

```text
ADMIN_EMAIL=admin@example.com ADMIN_PASSWORD='use-a-strong-12-character-password' php database/create-admin.php
```

The script creates or updates the owner account and never exposes the password in the database or application views. Do not commit the command, password, or `.env` file to GitHub.

The table and relationship notes are documented in `database/RELATIONSHIPS.md`. The schema does not contain credentials, payment secrets, raw card data, or passwords.

## Payment integration

Phase 11 prepares a Razorpay adapter without embedding credentials in PHP views or JavaScript source. Copy the following values into the server-only `.env` file when the merchant account is ready:

```text
PAYMENT_PROVIDER=razorpay
PAYMENT_MODE=test
PAYMENT_CURRENCY=INR
RAZORPAY_KEY_ID=your_test_key_id
RAZORPAY_SECRET=your_test_secret
RAZORPAY_WEBHOOK_SECRET=your_webhook_secret
```

Configure the Razorpay webhook URL as `/api/payment-webhook.php` and subscribe to `payment.captured` and `payment.failed`. The webhook must send `X-Razorpay-Signature` and `X-Razorpay-Event-Id`; duplicate events are ignored by the unique provider/event ID constraint.

Payment initiation and verification use `POST /api/payment.php`; the customer retry surface is `/checkout/payment.php?order=ORDER_NUMBER`. The backend creates the Razorpay order, verifies the HMAC signature, fetches the payment from Razorpay, confirms the order ID, amount, and captured status, and only then marks the local payment as paid. Unconfigured, failed, or pending payments remain pending and can be retried through the same initiation action. Never set `PAYMENT_MODE=live` until sandbox reconciliation and webhook delivery have been tested.