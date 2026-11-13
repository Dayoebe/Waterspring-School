# Paystack fee payments

WaterSpring initializes Paystack transactions on the server and records money only after a second server-to-server verification. The Paystack callback and webhook share the same idempotent fulfillment path, so retries cannot create a second receipt or increase the paid balance twice.

## Configuration

Set these values in the deployment environment, never in source control:

```dotenv
APP_URL=https://school.example
PAYSTACK_ENABLED=true
PAYSTACK_SECRET_KEY=sk_live_or_test_value
# Optional. Leave blank to use APP_URL/payments/paystack/callback.
PAYSTACK_CALLBACK_URL=https://school.example/payments/paystack/callback
```

WaterSpring uses Paystack's hosted checkout and initializes transactions from the server, so the browser never needs a Paystack public key. Start with an `sk_test_...` secret key. Store it only in `.env` or the production secret manager; never paste it into a Blade view, JavaScript, a support ticket, or Git.

Configure this webhook URL in the Paystack dashboard:

```text
https://school.example/api/payments/paystack/webhook
```

Paystack signs webhook bodies in the `x-paystack-signature` header using HMAC SHA-512 and the account's secret API key. WaterSpring validates that signature before reading the event, then independently verifies the transaction with Paystack before recording money.

After changing `.env`, clear cached configuration and run the secret-safe readiness check:

```bash
php artisan config:clear
php artisan payments:paystack-status
```

The check reports whether the key and public HTTPS URLs are ready, but never displays the secret key.

For local testing, expose the application through a trusted HTTPS development tunnel and use that temporary HTTPS origin for `APP_URL` and the Paystack dashboard URLs. Paystack cannot deliver a server-to-server webhook to `localhost`. Keep `PAYSTACK_ENABLED=false` whenever the tunnel is not running.

## Required services

Receipt email is queued. Keep both of these processes supervised in production:

```bash
php artisan queue:work --queue=default --tries=3 --backoff=60 --timeout=120
php artisan schedule:work
```

## Go-live checklist

1. Put an `sk_test_...` key in `.env`, keep `PAYSTACK_ENABLED=false`, and configure `APP_URL` with the real public HTTPS domain.
2. Set the callback and webhook URLs above in the Paystack dashboard, then run `php artisan config:clear` and `php artisan payments:paystack-status`.
3. Set `PAYSTACK_ENABLED=true` only after the readiness check passes.
4. Make a small successful test payment and confirm one payment attempt, one immutable ledger transaction, one receipt, and one queued notification.
5. Replay the webhook and confirm the paid balance and transaction count do not change.
6. Test a declined payment and an amount mismatch; neither may alter the fee balance.
7. Switch to an `sk_live_...` key, repeat a small payment, and reconcile it against the Paystack dashboard and settlement report.
8. Restrict access to secret keys, rotate any exposed value immediately, and monitor failed jobs and `review_required` payment attempts.

## Operational statuses

- `initialized`: the user can open Paystack checkout; no money has been recorded.
- `paid`: Paystack verified the exact reference, amount, currency, and success state, and the immutable ledger contains the payment.
- `not_successful`: Paystack has not confirmed success; the fee balance is unchanged.
- `review_required`: verified provider data conflicts with the local attempt or the current outstanding balance; staff must investigate before any manual correction.
- `initialization_failed`: checkout was not created and no fee balance changed.
