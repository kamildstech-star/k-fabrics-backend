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
4. **Payments and fulfillment:** Only enable merchant accounts the business has verified; checkout exposes bank transfer only when `CHECKOUT_BANK_TRANSFER_INSTRUCTIONS` is set. Reconcile amount, reference, date and operator for manual payments before progressing a tailoring order to cutting or dispatch. Exercise normal checkout, retry, cancellation, stock release, advance, tracking and refund reconciliation in staging. Nothing in the manual record flow moves money on its own.
5. **Private data:** Upload storage for tailor references must not be exposed by the public storage link. Use authenticated staff access only, confirm old public reference files have been migrated or removed where appropriate, and document cleanup/retention for measurement and reference data. Test backups and restores without exposing that data.
6. **Operations:** Keep debug off in production. Run a monitored queue worker, schedule `php artisan schedule:run` each minute for scheduled cleanup, check `/up`, and alert on checkout errors and failed jobs. Verify real catalog photos, delivery rates, support details and policies before sending traffic to the site.
