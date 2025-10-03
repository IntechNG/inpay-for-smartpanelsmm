# iNPAY Checkout Payment Gateway for SmartPanel (v4.2)

Standalone module for adding the iNPAY Checkout payment option to SmartPanel. Developed against **SmartPanel v4.2**; other versions may work but are unverified.

## Repository Layout

```
app/
  modules/
    add_funds/
      controllers/inpaycheckout.php
      libraries/inpaycheckoutapi.php
      views/inpaycheckout/index.php
    admin/
      views/payments/integrations/inpaycheckout.php
assets/
  images/payments/inpaycheckout.svg
database/
  inpaycheckout_payment.sql
```

Copy these files into the matching paths within your SmartPanel installation.

## Prerequisites

- SmartPanel v4.2 with Add Funds module enabled.
- iNPAY Checkout merchant account (public + secret API keys).
- SmartPanel currency configured as **NGN**, or a currency-rate value prepared to convert your base currency into NGN (iNPAY Checkout processes Nigerian naira only).
- HTTPS-enabled site reachable externally for webhooks.

### Preparing the NGN currency option

Out of the box, SmartPanel’s currency selector doesn’t list the Nigerian naira. Add it manually before enabling this gateway:

1. Open `app/helpers/currency_helper.php` in your SmartPanel installation.
2. Locate the `currency_codes()` function and append the following entry to the `$data` array (keep the trailing comma):
   ```php
   "NGN" => "Nigerian naira",
   ```
3. Save the file.
4. In SmartPanel admin → **Settings → Currency**, pick `NGN` as the currency code (or keep your preferred base currency if you plan to use the currency-rate conversion) and set the currency symbol to `₦`.
5. If you keep a non-NGN base currency, note the exchange rate you’ll use (see step 4 of the installation instructions below).

## Installation Steps

1. **Backup first.** Snapshot your SmartPanel files and database before changes.

2. **Copy files.** Place each file from this repository into the corresponding directory in your SmartPanel root:
   - `app/modules/add_funds/controllers/inpaycheckout.php`
   - `app/modules/add_funds/libraries/inpaycheckoutapi.php`
   - `app/modules/add_funds/views/inpaycheckout/index.php`
   - `app/modules/admin/views/payments/integrations/inpaycheckout.php`
   - `assets/images/payments/inpaycheckout.svg`

3. **Add the webhook route.** Edit `app/config/routes.php` and append:
   ```php
   $route['inpaycheckout/webhook'] = 'add_funds/inpaycheckout/webhook';
   ```
   This publishes a public webhook endpoint at `https://your-domain.com/inpaycheckout/webhook`.

4. **Allow the webhook to bypass CSRF protection.** SmartPanel blocks POST requests without its CSRF token. In `app/config/config.php`, just below the existing `if (stripos(...))` rules, add:
   ```php
   if (stripos($_SERVER["REQUEST_URI"], 'inpaycheckout/webhook') !== false) {
       $config['csrf_protection'] = false;
   }
   ```
   This keeps protection enabled elsewhere while letting iNPAY's webhook through.

5. **Insert the payment method record.** If SmartPanel doesn’t already have an `inpaycheckout` method, insert one (see optional helper below). Update the placeholder currency rate (`"currency_rate":"1"`) if your base currency isn’t NGN:
   ```sql
   INSERT INTO `payments` (`type`, `name`, `sort`, `min`, `max`, `new_users`, `status`, `params`)
   VALUES (
     'inpaycheckout',
     'iNPAY Checkout',
     3,
     1.00,
     0.00,
     1,
     0,
    '{"type":"inpaycheckout","name":"iNPAY Checkout","min":1,"max":0,"new_users":1,"status":0,"option":{"public_key":"","secret_key":"","tnx_fee":"0","currency_rate":"1"}}'
   );
   ```
   Adjust limits and defaults to suit your environment.
   - **Non-technical option:** import `database/inpaycheckout_payment.sql` (included in this repo) through phpMyAdmin → *Import*. After importing, edit the payment in SmartPanel admin to enter the correct currency rate and API keys.
   
6. **Clear caches** (if OPCache/Cloudflare/etc.) so new files load.

## Configuration Inside SmartPanel

1. In Admin → Payments, edit **iNPAY Checkout**.
2. **Public key / Secret key:** paste from iNPAY dashboard (only live keys are supported—iNPAY does not offer sandbox keys).
3. **Currency Rate:** enter the conversion from your SmartPanel base currency to NGN (example: if SmartPanel is in USD and ₦1500 = $1, enter `1500`). This field is required whenever your base currency is not NGN—leaving it at `1` will prevent the gateway from processing payments.
4. Optional **Transaction Fee (%)** to deduct from credited balance.
5. Copy the **Webhook URL** displayed and set it in the iNPAY dashboard under Settings → Webhooks.
6. Enable the method, adjust min/max amounts, and allow/disallow new users as needed. Remember to update the currency rate whenever your exchange rate changes.

## User Flow Verification

1. Navigate to Add Funds → iNPAY Checkout tab.
2. Enter amount, accept terms, submit—the inline iNPAY modal loads.
3. Upon successful payment, SmartPanel posts a pending transaction, verifies with iNPAY, credits balance (minus optional fee), applies bonuses, and redirects to the success page.
   The charge sent to iNPAY is always in NGN based on the configured currency rate.
4. Confirm webhook logs show HTTP 200 acknowledgments.

## Webhook Behaviour

- Validates `X-Webhook-Signature` (HMAC SHA256), `X-Webhook-Timestamp`, and allowed event names.
- Re-verifies the transaction via iNPAY API before marking complete.
- Idempotent: ignores already-completed logs but still returns 200.

## Uninstall

1. Delete the copied files.
2. Remove the route from `app/config/routes.php`.
3. Disable/remove the iNPAY Checkout record in the Payments table.

## Support & Links

- iNPAY Docs: https://dev.inpaycheckout.com
- iNPAY Dashboard: https://dashboard.inpaycheckout.com
- SmartPanel vendor support: contact your script provider.

Issues and improvements welcome via pull request.

### Release Notes
- 2025-10-03: Verified live payment flow and webhook delivery against production iNPAY (ref `inpay_1759477843_3f923f41`). Update controller cast fixes array/object issues and confirm CSRF/webhook adjustments.
