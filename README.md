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
- SmartPanel currency configured as **NGN** (iNPAY Checkout processes Nigerian naira only).
- HTTPS-enabled site reachable externally for webhooks.

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

4. **Insert the payment method record.** If SmartPanel doesn’t already have an `inpaycheckout` method, insert one (see optional helper below):
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
     '{"type":"inpaycheckout","name":"iNPAY Checkout","min":1,"max":0,"new_users":1,"status":0,"option":{"public_key":"","secret_key":"","tnx_fee":"0"}}'
   );
   ```
   Adjust limits and defaults to suit your environment.
   - **Non-technical option:** import `database/inpaycheckout_payment.sql` (included in this repo) through phpMyAdmin → *Import*. It creates the same record automatically.
   
5. **Clear caches** (if OPCache/Cloudflare/etc.) so new files load.

## Configuration Inside SmartPanel

1. In Admin → Payments, edit **iNPAY Checkout**.
2. **Public key / Secret key:** paste from iNPAY dashboard (only live keys are supported—iNPAY does not offer sandbox keys).
3. Optional **Transaction Fee (%)** to deduct from credited balance.
4. Copy the **Webhook URL** displayed and set it in the iNPAY dashboard under Settings → Webhooks.
5. Enable the method, adjust min/max amounts, and allow/disallow new users as needed.

## User Flow Verification

1. Navigate to Add Funds → iNPAY Checkout tab.
2. Enter amount, accept terms, submit—the inline iNPAY modal loads.
3. Upon successful payment, SmartPanel posts a pending transaction, verifies with iNPAY, credits balance (minus optional fee), applies bonuses, and redirects to the success page.
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
