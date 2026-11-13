# Paystack fee payments

WaterSpring initializes Paystack transactions on the server and records money only after a second server-to-server verification. The Paystack callback and webhook share the same idempotent fulfillment path, so retries cannot create a second receipt or increase the paid balance twice.

## Configuration

Set these values in the deployment environment, never in source control:

```dotenv
PAYSTACK_ENABLED=true
PAYSTACK_PUBLIC_KEY=pk_live_or_test_value
PAYSTACK_SECRET_KEY=sk_live_or_test_value
PAYSTACK_WEBHOOK_SECRET=dedicated_webhook_signing_secret
PAYSTACK_BASE_URL=https://api.paystack.co
PAYSTACK_CALLBACK_URL=https://school.example/payments/paystack/callback
```

Configure this webhook URL in the Paystack dashboard:

```text
https://school.example/api/payments/paystack/webhook
```

The application accepts Paystack's timestamped `Paystack-Signature` format when a dedicated webhook secret is configured. It also supports the legacy `x-paystack-signature` HMAC that uses the secret API key.

## Required services

Receipt email is queued. Keep both of these processes supervised in production:

```bash
php artisan queue:work --queue=default --tries=3 --backoff=60 --timeout=120
php artisan schedule:work
```

## Go-live checklist

1. Use Paystack test keys first and keep `PAYSTACK_ENABLED=false` until the callback and webhook URLs are publicly reachable over HTTPS.
2. Clear cached configuration after setting keys: `php artisan config:clear` (or rebuild the production config cache).
3. Make a small successful test payment and confirm one payment attempt, one immutable ledger transaction, one receipt, and one queued notification.
4. Replay the webhook and confirm the paid balance and transaction count do not change.
5. Test a declined payment and an amount mismatch; neither may alter the fee balance.
6. Switch to live keys, make another small payment, and reconcile it against the Paystack dashboard/settlement report.
7. Restrict access to secret keys, rotate any exposed value immediately, and monitor failed jobs and `review_required` payment attempts.

## Operational statuses

- `initialized`: the user can open Paystack checkout; no money has been recorded.
- `paid`: Paystack verified the exact reference, amount, currency, and success state, and the immutable ledger contains the payment.
- `not_successful`: Paystack has not confirmed success; the fee balance is unchanged.
- `review_required`: verified provider data conflicts with the local attempt or the current outstanding balance; staff must investigate before any manual correction.
- `initialization_failed`: checkout was not created and no fee balance changed.
