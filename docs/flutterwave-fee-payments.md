# Flutterwave fee payments

WaterSpring uses Flutterwave Checkout to create a hosted payment page on the server. A fee balance changes only after WaterSpring re-queries Flutterwave and confirms the transaction ID, WaterSpring reference, exact amount, currency, provider reference, and successful status.

Flutterwave is one optional collection method within the [fee catalogue, invoicing, collections, and immutable ledger workflow](fees-and-payments.md). Enabling it does not create fees or invoices automatically.

## Configuration

Set these values in the deployment environment and never commit real secret values:

```dotenv
APP_URL=https://school.example
FLUTTERWAVE_ENABLED=false
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-your-test-key-X
FLUTTERWAVE_SECRET_HASH=use-a-long-random-value-here
# Optional. Leave blank to use APP_URL/payments/flutterwave/callback.
FLUTTERWAVE_CALLBACK_URL=https://school.example/payments/flutterwave/callback
```

Configure this webhook URL in **Flutterwave Dashboard > Settings > Webhooks**:

```text
https://school.example/api/payments/flutterwave/webhook
```

Set the dashboard webhook secret hash to the exact same random value used for `FLUTTERWAVE_SECRET_HASH`. Flutterwave sends that value in the `verif-hash` header. WaterSpring rejects a missing or different value and still independently verifies successful transactions through Flutterwave's API before recording money.

After changing `.env`, clear cached configuration and run the secret-safe readiness check:

```bash
php artisan config:clear
php artisan payments:flutterwave-status
```

The command never prints the secret key or webhook secret hash. For local testing, expose WaterSpring through a trusted HTTPS development tunnel; Flutterwave cannot send server-to-server webhooks to `localhost`.

## Required services

Receipt email and the primary school super-administrator payment alert are queued. Keep the queue worker and scheduler supervised in production:

```bash
php artisan queue:work --queue=default --tries=3 --backoff=60 --timeout=120
php artisan schedule:work
```

## Go-live checklist

1. Start with a `FLWSECK_TEST-...` key and keep `FLUTTERWAVE_ENABLED=false` while configuring public HTTPS URLs.
2. Add the webhook URL and matching secret hash in the Flutterwave dashboard.
3. Set `FLUTTERWAVE_ENABLED=true`, run `php artisan config:clear`, then run `php artisan payments:flutterwave-status`.
4. If any readiness check fails, set `FLUTTERWAVE_ENABLED=false` again until the configuration is corrected.
5. Make a small successful payment and confirm one attempt, one immutable ledger transaction, one receipt, and one notification.
6. Replay the callback and webhook; the transaction count and paid balance must not increase again.
7. Test cancellation, a failed payment, an amount mismatch, and an invalid webhook secret; none may alter the fee balance.
8. Switch to a live key only after test acceptance, repeat a small payment, and reconcile it with the Flutterwave dashboard and settlement report.

## References

- [Flutterwave hosted Checkout](https://developer.flutterwave.com/docs/collections-inflow/flutterwave-standard)
- [Flutterwave webhooks](https://developer.flutterwave.com/docs/webhooks)
- [Flutterwave transaction verification](https://developer.flutterwave.com/docs/transaction-verification)
