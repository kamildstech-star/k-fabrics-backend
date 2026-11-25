# K. Fabrics API and admin

Laravel API for the K. Fabrics Nuxt storefront and Filament workshop/admin panel. Production releases must be coordinated with the corresponding storefront release because the checkout, tracking and browser-session APIs changed.

## Local setup

Requires PHP 8.4, Composer, Node.js 24, and the PHP curl, intl, mbstring, XML, ZIP and PDO driver extensions.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
npm run build
php artisan serve --host=localhost --port=8000
```

Use `php artisan db:seed` only for local/test demo data. Do not restore a database snapshot from Git or put production details in seeders. For staff access, provision the first admin interactively with `php artisan kf:make-admin` in the intended environment.

```sh
php artisan test
composer audit --locked
```

The CI workflow runs feature tests against SQLite and MySQL 8.4. Use the same engine and version as production for concurrency and migration sign-off.

## Release checklist

1. **Database:** Back up and rehearse restore before migration. Test the migration against a representative staging database and verify stock, order totals and historical measurements after migration. Use a separate staging database and test merchant account. Never seed production with fixtures. The removed SQLite snapshot remains in old Git history; assess whether any real data or credentials were in it. If so, restrict access, rotate affected credentials, and coordinate a history cleanup separately. Do not rewrite history without approval.
2. **Storefront sessions:** Use HTTPS for both storefront and API. Configure the actual trusted storefront origin, stateful domains and secure cookies; the storefront and API must share the same registrable domain. If using sibling hosts, scope the CSRF cookie domain accordingly and trust all hosts inside that cookie scope. Keep the admin session on the intended host. Update the storefront and API together. Existing browser sessions may require signing in again. Guest orders from before the tracking upgrade do not have a private tracking code; staff must help these customers privately. Preserve the application key during ordinary releases. If the key is compromised, rotate it and plan for existing private tracking codes and other key-derived data to be invalidated.
3. **COD and messaging:** Confirm the staff verification workflow in staging before enabling COD. If enabling a real SMS provider, use secrets supplied by the provider, verify successful delivery and handle provider outages separately from a bad code. The default `CHECKOUT_COD_MODE=manual` does not send OTPs; staff must verify each order. `CHECKOUT_COD_MODE=otp` requires the Twilio configuration documented in `.env.example`. Enable inbound WhatsApp only after verifying webhook signing and order-specific confirmation in the provider's test environment. Never log OTPs, signing secrets, full payment references, or customer files.
4. **Payments and fulfillment:** Only enable merchant accounts the business has verified; checkout exposes bank transfer only when `CHECKOUT_BANK_TRANSFER_APPROVED=true` and verified `CHECKOUT_BANK_TRANSFER_INSTRUCTIONS` are set. Tailoring otherwise stays paused; eligible ready-to-wear checkout can still use COD. For tailoring, verify the advance before work and the remaining bank transfer before dispatch. Reconcile amount, reference, date and operator for manual payments before progressing a tailoring order to cutting or dispatch. Exercise normal checkout, retry, cancellation, stock release, advance, tracking and refund reconciliation in staging. Nothing in the manual record flow moves money on its own.
5. **Private data:** Upload storage for tailor references must not be exposed by the public storage link. Use authenticated staff access only, confirm old public reference files have been migrated or removed where appropriate, and document cleanup/retention for measurement and reference data. Test backups and restores without exposing that data.
6. **Operations:** Keep debug off in production. Run a monitored queue worker, schedule `php artisan schedule:run` each minute for scheduled cleanup, check `/up`, and alert on checkout errors and failed jobs. Verify real catalog photos, delivery rates, support details and policies before sending traffic to the site.
   The scheduler checks stock holds every 15 minutes. The default hold is 72 hours, and only new ready-to-wear COD orders with a positive total, pending payment, no confirmation, and no recorded receipt are eligible for release. Existing orders and tailoring bookings are excluded. Use the admin **COD Needs Confirmation** tab to review pending orders; save a valid courier name and consignment ID before marking packed orders dispatched. Set `CHECKOUT_COD_CONFIRMATION_HOLD_HOURS=0` to pause automatic expiry and stop assigning new deadlines. Re-enabling resumes eligible previously assigned deadlines.

### Merchandising fit data

In Filament → Shop & Catalog → Products, use **Fit, size guide & care** to enter measured sizes for the specific article. Enter measurements in inches, label the chart as finished garment or recommended body measurements, and use bust/waist/hip circumference rather than a laid-flat width. Add fit notes, model size/height and care directions only when known. Leave unverified measurements blank. Front, back and fabric close-up assets should be the actual article; use each image’s alt text to distinguish the view.

### Private order receipts

Checkout provides a redacted downloadable and printable order receipt. Customers can retrieve it again after submitting their order number and private tracking code. Codes never go in URLs, browser storage, or the status-only tracking response; they are included in the receipt only when the customer explicitly downloads or prints it. Do not enable request-body logging on the tracking and receipt endpoints.

Transactional email is disabled by default. To use it, set `CHECKOUT_RECEIPT_EMAIL_ENABLED=true`, select a trusted transport using `CHECKOUT_RECEIPT_EMAIL_MAILER`, configure a worker on an asynchronous queue, and set `CHECKOUT_RECEIPT_TRACKING_URL` to the configured HTTPS storefront origin plus `/track-order`. It only queues for the matching email address of a signed-in account already marked verified. Guests and unverified accounts should save the on-screen receipt; registration does not currently verify email automatically. Confirm sender identity, authenticated mail transport and delivery in staging before enabling. The mail includes the private code, so do not redirect provider traffic to a shared mailbox or use a logging mailer. The success marker records provider acceptance, not inbox delivery.

### Standalone stitching handover

New bookings use courier handover only and require a measurement method. The customer supplies all required shirt and trouser measurements, or sends a physical sample after the atelier confirms courier instructions. Staff must record fabric receipt, sample receipt where applicable, and verified payment before cutting. Deploy the matching storefront with this API; older storefronts do not send the measurement method. Existing bookings remain on their original workflow.

### Manual restock requests

Restock requests are hidden and rejected by default. Set `RESTOCK_MANUAL_ALERTS_ENABLED=true` only once staff own the queue and have an approved sender. In **Shop & Catalog → Stock Alerts**, use **Ready for manual email**, contact each customer only about the requested size, then use **Record manual notification**. This action does not send anything. Never add these addresses to a marketing list. Delete handled and stale requests according to the store's retention policy.

### Account and guest tracking recovery

Password reset and guest tracking-code recovery use the storefront's first-party session and CSRF checks. Request responses are generic; only the encrypted, short-lived background job tries matching the account or order. Password reset sends a one-time Laravel-broker code to the registered customer email, excludes staff accounts, and revokes the customer's database sessions, persistent login cookie and API tokens on completion. Guest recovery sends the existing tracking code only to the stored checkout email, after checking the order number, email and full normalized phone. Recovery codes are never part of a link or an API response. For orders without an email or a private tracking code, use support's separate ownership verification process; never disclose a code merely because someone knows an order number. Do not log these request bodies or store mail contents in application logs.

Both email flows are disabled by default. Configure `SESSION_DRIVER=database` on the same database as users, Laravel's asynchronous queue (known database, Redis, SQS or Beanstalkd driver), scheduler, a non-logging approved mailer (`RECOVERY_EMAIL_MAILER`) and `RECOVERY_STOREFRONT_URL` matching a configured HTTPS storefront origin. Only then set `RECOVERY_EMAIL_ENABLED=true`. Use a verified sending domain, configure the official storefront support contacts, and confirm recovery in staging before enabling live delivery. Do not redirect recovery mail to a shared mailbox. If disabled, the site shows a support fallback.

### Returns and exchanges

The policy page accepts one support case per delivered order, with the original signed-in account or private tracking code. In **Orders & Fulfillment → Returns & Exchanges**, staff review and record the status and private case notes. This does not send messages or change payment, inventory or order status. Confirm eligibility and handover with the buyer through the published support channel; use verified payment and inventory workflows for any refund or replacement.
