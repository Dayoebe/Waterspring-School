# WaterSpring documentation

This directory documents the application that is currently implemented in this repository. Start with the project [README](../README.md) for installation and a product overview, then use the guides below for the relevant workflow.

## Product and access

- [Authentication and access](authentication-and-access.md): username/email login, administrator-created accounts, public signup approval, roles, account restrictions, and tenant boundaries.
- [Fees and payments](fees-and-payments.md): fee catalogue approval, class billing, collections, immutable ledger entries, receipts, reversals, parent/admin notifications, Paystack, and Flutterwave.
- [CBT integrity and operations](cbt-integrity.md): exam publication, attempt ownership, autosave/concurrency protections, result privacy, integrity checks, and capacity preparation.
- [School workflows](school-workflows.md): admissions, audit/accountability, parent-teacher appointments, transport, and authorized pickup.
- [ClassPilot AI](class-pilot.md): optional teacher, student, and family AI workspaces.
- [Open Learning](open-learning.md): public curriculum-grounded learning and optional account-bound saved progress.
- [Dashboard design](dashboard-design.md): shared portal layout and interface conventions.

## Deployment and integrations

- [Operations runbook](operations.md): queue, scheduler, health checks, HTTPS, encrypted backups, restore tests, and deployment cautions.
- [Paystack setup](paystack-fee-payments.md): Paystack keys, callback, webhook, verification, and go-live acceptance.
- [Flutterwave setup](flutterwave-fee-payments.md): Flutterwave keys, callback, webhook secret hash, verification, and go-live acceptance.

## Public website

- [Public-site implementation notes](watersprings-public-site.md): verified content sources, media, forms, prospectus, and historical validation.
- [SEO and AI discovery](seo-ai-discoverability-report.md): canonical metadata, sitemaps, crawler rules, and AI-readable endpoints.

## Documentation rules

- The code, migrations, route middleware, permission matrix, and configuration files are authoritative when a document drifts.
- Historical browser or release checks are labelled with their date and must not be read as proof of the current deployment.
- Never put real API keys, passwords, encryption keys, personal data, or production database credentials in these files.
- Public school claims must come from the verified sources listed in the public-site notes; do not invent results, facilities, partnerships, testimonials, or statistics.
- Operational readiness requires environment-specific acceptance. Passing automated tests does not prove mail delivery, provider webhook delivery, payment settlement, backup off-site retention, or production capacity.
