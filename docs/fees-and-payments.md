# Fees and payments

The fee workspace at `/fees` is the canonical finance interface. Its permission-aware tabs cover the fee catalogue, student invoices, and collections; legacy `/fees/fee-invoices` and create URLs resolve to the same Livewire workspace for backward compatibility.

## Fee catalogue and approval

Staff with fee permissions can create a category inline while creating a fee. A fee definition contains:

- fee name, category, and explanation;
- default amount;
- optional fixed or percentage waiver;
- optional fine; and
- draft/approved status and approval history.

The displayed total is `amount + fine - waiver`. Percentage waivers are stored as basis points and calculated from the amount. A fee cannot be approved without a positive amount and a clear explanation of at least ten characters. Editing an approved fee returns it to draft so that changed terms must be approved again.

Approval is school-scoped, recorded in the audit trail, and queues one deduplicated message to each parent in the school who has a valid non-placeholder email. This is a fee-catalogue announcement; actual liability is created by an invoice.

## Class and individual invoices

Invoice creation can bill every active student in a selected class/section or one selected student. Only approved fee definitions are selectable. Each student receives a separate invoice with its own fee-item snapshots, issue date, due date, class/section context, and note.

The invoice item stores the amount, waiver, fine, and derived paid projection at the time of billing. Later catalogue changes do not silently rewrite an existing invoice. Staff can filter invoices and collections by class and payment status.

Parents see invoices for linked children and outstanding balances on their dashboard. Authorized staff and class teachers receive school/class-scoped outstanding summaries; they do not receive access to unrelated schools or classes.

## Collections, manual payments, and receipts

The Collections tab lists paid, partial, and unpaid fee items and supports search and class filters. Authorized staff can record cash, bank-transfer, POS, cheque, or online payments. The payment cannot exceed the outstanding balance.

Every payment creates an append-only `fee_payment_transactions` row containing a unique receipt number, amount/currency, method, cashier or gateway snapshot, payer snapshot, references, timestamp, IP/user-agent context, and audit event. The historical `fee_invoice_records.paid` column is only a synchronized compatibility projection of the ledger.

Corrections never edit or delete an existing payment. Staff create a linked reversal with a required reason. Reconciliation entries record matched, unmatched, or disputed status without changing the original transaction. Fee items with payment history cannot be deleted.

A successful payment queues:

- a receipt email to the payer/parent when a deliverable address exists; and
- one deduplicated school-payment alert to the first deliverable super-administrator account in that school.

The receipt can also be opened from the portal. Queue failures must be monitored because a recorded payment remains valid even if a notification fails.

## Online gateways

Paystack and Flutterwave are optional and independently enabled. Both use server-created hosted checkout, server-to-server verification, exact amount/currency/reference checks, tenant-scoped invoice access, idempotent fulfillment, immutable ledger recording, and provider-signed webhook validation.

Provider credentials belong only in `.env` or a production secret manager. See:

- [Paystack setup and acceptance](paystack-fee-payments.md)
- [Flutterwave setup and acceptance](flutterwave-fee-payments.md)

Do not mark either gateway production-ready until callback, webhook, replay, mismatch, failure, receipt, notification, settlement, and reconciliation tests pass against that deployment's public HTTPS URL.

## Required operations

Queued receipts, fee announcements, and administrator alerts require a supervised queue worker. Gateway webhooks require the public HTTPS routes documented in the provider guides. After environment changes:

```bash
php artisan config:clear
php artisan payments:paystack-status
php artisan payments:flutterwave-status
php artisan queue:failed
```

## Production verification

Before release, verify catalogue approval, class billing, dashboard visibility, manual ledger entries, reversals, tenant boundaries, provider configuration, verified fulfillment, and replay protection in a controlled pre-production environment. These checks do not prove that a live provider has accepted the deployment or settled funds.
