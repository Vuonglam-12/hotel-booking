# SePay Test Mode webhook

The public endpoint is `POST /api/webhooks/sepay`. It returns SePay's required
`{"success": true}` response only after an authenticated event has been durably
recorded. Repeated transaction IDs are idempotent. Reconciliation outcomes
(including amount/reference/account mismatches and late transfers) are retained
without confirming a Booking.

The integration is disabled by default. It accepts **SePay Test mode only** and
also requires all three existing Booking V2 gates: `integration_complete`,
`final_integration`, and `delivery_safety`. It additionally requires
`APP_ENV=testing` with no production manifest and the repository's owned,
separately initialized MySQL test server. The public app currently has these
gates disabled; do not enable them or Checkout as a shortcut for webhook tests.
The test suite uses synthetic rows and cannot connect to the `hotel_booking`
database.

## Local configuration

Set these values only in a protected environment file, never in source control:

```dotenv
SEPAY_WEBHOOK_ENABLED=true
SEPAY_WEBHOOK_MODE=test
SEPAY_WEBHOOK_AUTH_METHOD=api_key
SEPAY_WEBHOOK_API_KEY=<the API Key created for the SePay Test mode webhook>
SEPAY_WEBHOOK_ACCOUNT_NUMBER=<the receiving account number created in Test mode>
```

For HMAC-SHA256 instead, set `SEPAY_WEBHOOK_AUTH_METHOD=hmac_sha256` and
`SEPAY_WEBHOOK_HMAC_SECRET=<the HMAC Secret Key>`; do not set up a no-auth or
OAuth 2.0 webhook for this endpoint. API Key requests use
`Authorization: Apikey <key>`. HMAC requests use the original request body and
SePay's `X-SePay-Timestamp` / `X-SePay-Signature` headers, with the timestamp
bound to the body and a five-minute acceptance window.

Changing these environment values alone does not bypass the Booking V2
database/deployment guards. Use the existing isolated test/deployment process;
do not point the V2 connection at `hotel_booking`.

## SePay Dashboard

1. Switch the SePay Dashboard to **Test mode**, create a Test mode bank account,
   and create a webhook in that Test mode workspace.
2. Set the URL to `https://<your-test-tunnel-host>/api/webhooks/sepay`; select
   **incoming transfers** and JSON payload delivery.
3. Select **API Key** or **HMAC-SHA256** authentication and configure the
   corresponding secret locally. Never select no authentication.
4. Configure the local account number to exactly match the Test mode
   destination account. Test transfers must include the complete
   `payment.transaction_id` as a distinct token in the original transfer
   content. SePay's optional `code` field is also accepted when it equals that
   full Payment reference; a prefix or unrelated code is not enough by itself.
5. Use SePay's Test mode simulator only. No Live-mode webhook or bank transfer
   is needed for these tests.

The receiver uses the documented SePay transaction `id` as the external
idempotency key, `transferType` to reject outgoing transactions, `accountNumber`
to bind the receiving account, `transferAmount` for exact VND matching, and
`code`/`content` for the Payment reference.

## Reused Booking V2 behavior

The webhook calls the existing `RoomTypeV2Writer` lock boundary, applies the
same no-late-restoration rule as Admin verification and verified gateway
callbacks, writes `payment_gateway_receipt` and `booking_state_audit`, and
creates the existing `booking_effect` only for an on-time successful payment.
The existing delivery worker remains responsible for invoices, notifications,
and confirmation email. Admin manual verification remains available for
reconciliation.

Official SePay references:

- [Webhook integration and payload](https://developer.sepay.vn/vi/sepay-webhooks/tich-hop-webhook)
- [Webhook authentication](https://developer.sepay.vn/vi/sepay-webhooks/xac-thuc)
- [SePay Test mode quick start](https://developer.sepay.vn/vi/sepay-webhooks/test-mode/bat-dau-nhanh)
