# Checkout and fulfillment

## Configure before taking real orders

- Deploy the backend and storefront together. Run Laravel migrations on the production database, never the old bundled SQLite file. Run DB backups and test a restore first.
- Set `CHECKOUT_COD_MODE=manual` for the staff-confirmed pilot. Record whether staff contacted the customer before use of the **Confirm order** action. To require delivery-provider-backed verification use `CHECKOUT_COD_MODE=otp` only after staging tests and credentials are in place. Use `disabled` to remove COD.
- Set `CHECKOUT_BANK_TRANSFER_INSTRUCTIONS` only to verified, customer-facing merchant instructions. Without it, bank transfer is disabled. A COD tailoring order can still be submitted but cutting remains blocked until staff records the verified deposit. Use verified shop contact channels to share deposit instructions.
- Keep `APP_KEY` stable and secret. It protects tracking and checkout retry keys. If rotation is necessary, have support reissue existing tracking codes or verify orders manually; pre-migration orders have no self-service code.
- Existing tailoring orders are assigned the existing 50% advance rule and their linked measurements copied into snapshots. Reconcile existing payments from real records before relying on the ledger or moving existing orders through the new gates. No historical payments are fabricated or automatically marked received.
- Store design references on the private local disk (persistent and shared if using multiple backend instances). Do not serve older public uploads: locate, migrate and delete them from public storage under the shop's retention process. Only new private storage keys can be served in authenticated tailor sheets.

## Operator flow

1. Open the new order in Filament. Verify the COD order with the customer. For bank transfer, match the actual external bank reference and amount before confirmation.
2. Use **Record verified payment / refund** for a receipt; a payment entry records an external action and does not move money. Use unique references; the linked Payments section lists amounts, transaction times and staff.
3. Use **Confirm order**. For tailoring, record when the customer fabric has been received, verify the advance, then move each tailoring request through one stage at a time. Custom-fit variants use atelier fabric, so their inbound step is already recorded.
4. Use **Update fulfillment** to pack, dispatch and mark delivery. All stitching lines must be ready to pack. The verified remaining bank transfer balance is required before dispatch. Record applicable courier details in the order.
5. Use **Cancel order** before tailoring or dispatch begins. This releases reserved standard stock and the coupon once. Staff should review old pending orders and cancel stale requests after confirming shop policy. A return may be damaged; stock is not automatically added back after return. Once a cancelled or returned payment has actually been refunded, record the refund reference in the ledger.

## API contract

- POST `/api/v1/checkout/quote` returns authoritative PKR prices, shipping, coupons, advance and supported payment methods. Invalid coupons return a validation error. Quotes do not reserve stock.
- POST `/api/v1/orders` recalculates inside the reservation transaction. Send a fresh random `Idempotency-Key` per distinct checkout and keep that key on a genuine retry. Exact replay returns 200 with the same order and tracking code; a changed checkout under the same key is rejected. Submit `expected_total_amount` from the latest accepted quote to prevent silent repricing.
- Multiple standalone bookings use `standalone_stitchings`. Attach design references as multipart `design_reference_images[index]`. Only validated JPG, PNG and WebP files are accepted, up to 5 MB per booking. Proxy upload limits should be set slightly above the maximum permitted request.
