# Sanzida's Closet

Phase 1 establishes the custom PHP + MySQL application foundation for MilesWeb shared hosting.

## Local setup

1. Copy `.env.example` to `.env`.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, a canonical `APP_URL`, and MySQL credentials in `.env`.
3. Create the database with `database/schema.sql`.
4. Point the web server document root at the project root for this repository layout. A separate `public/` document root requires restructuring or an equivalent host-level route mapping because application routes currently live beside `public/`.

The root `index.php` delegates to `public/index.php` so the current shared-hosting layout remains usable. No product, customer, payment, or admin behavior is implemented in this phase.

## Foundation endpoints

- `/` renders the public foundation homepage.
- `/api/health.php` reports application and database availability without exposing credentials.

Run PHP syntax checks with `php -l <file>` after PHP 8.x is installed and available on `PATH`.

## Database verification

With MySQL 8.x credentials configured, import and verify the schema with:

```text
mysql --default-character-set=utf8mb4 -u YOUR_USER -p < database/schema.sql
mysql -u YOUR_USER -p -e "USE sanzidas_closet; SHOW TABLES;"
```

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