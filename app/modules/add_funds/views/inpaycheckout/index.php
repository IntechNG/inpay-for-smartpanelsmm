<?php
  $option           = get_value($payment_params, 'option');
  $min_amount       = get_value($payment_params, 'min');
  $max_amount       = get_value($payment_params, 'max');
  $type             = get_value($payment_params, 'type');
  $tnx_fee          = get_value($option, 'tnx_fee');
  $currency_code    = strtoupper(get_option('currency_code', 'USD'));
  $currency_symbol  = get_option('currency_symbol', '$');
  $currency_rate    = (float) get_value($option, 'currency_rate', 1);
  if ($currency_rate <= 0) {
    $currency_rate = 1;
  }
?>

<div class="add-funds-form-content">
  <form class="form inpayCheckoutForm" method="POST">
    <div class="row">
      <div class="col-md-12">
        <div class="for-group text-center">
          <img src="<?=BASE?>/assets/images/payments/inpaycheckout.svg" alt="iNPAY Checkout icon">
          <p class="p-t-10"><small><?=sprintf(lang("you_can_deposit_funds_with_paypal_they_will_be_automaticly_added_into_your_account"), 'iNPAY Checkout')?></small></p>
        </div>

        <div class="form-group">
          <label><?=sprintf(lang("amount_usd"), $currency_code)?></label>
          <input class="form-control square" type="number" step="0.01" min="0" name="amount" placeholder="<?php echo $min_amount; ?>">
        </div>

        <div class="form-group">
          <label><?php echo lang("note"); ?></label>
          <ul>
            <?php if ($tnx_fee > 0) { ?>
            <li><?=lang("transaction_fee")?>: <strong><?php echo $tnx_fee; ?>%</strong></li>
            <?php } ?>
            <li><?=lang("Minimal_payment")?>: <strong><?php echo $currency_symbol.$min_amount; ?></strong></li>
            <?php if ($max_amount > 0) { ?>
            <li><?=lang("Maximal_payment")?>: <strong><?php echo $currency_symbol.$max_amount; ?></strong></li>
            <?php } ?>
            <?php if ($currency_code !== 'NGN') { ?>
            <li>Payments are processed in NGN. Current rate: <strong>1 <?php echo $currency_code; ?> ≈ <?php echo number_format($currency_rate, 4); ?> NGN</strong>.</li>
            <?php } else { ?>
            <li>Payments are processed in NGN. Ensure your currency settings use the ₦ symbol.</li>
            <?php } ?>
            <li><?=lang("clicking_return_to_shop_merchant_after_payment_successfully_completed"); ?></li>
          </ul>
        </div>

        <div class="form-group">
          <label class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" name="agree" value="1">
            <span class="custom-control-label text-uppercase"><strong><?=lang("yes_i_understand_after_the_funds_added_i_will_not_ask_fraudulent_dispute_or_chargeback")?></strong></span>
          </label>
        </div>

        <div class="form-actions left">
          <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
          <input type="hidden" name="payment_method" value="<?php echo $type; ?>">
          <button type="submit" class="btn round btn-primary btn-min-width mr-1 mb-1">
            <?=lang("Pay")?>
          </button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
(function() {
  var form = document.querySelector('.inpayCheckoutForm');
  if (!form) {
    return;
  }

  var sdkPromise;

  function loadSdk() {
    if (window.iNPAY && window.iNPAY.InpayCheckout) {
      return Promise.resolve(window.iNPAY.InpayCheckout);
    }

    if (!sdkPromise) {
      sdkPromise = new Promise(function(resolve, reject) {
        var script = document.createElement('script');
        script.src = 'https://js.inpaycheckout.com/v1/inline.js';
        script.onload = function() {
          if (window.iNPAY && window.iNPAY.InpayCheckout) {
            resolve(window.iNPAY.InpayCheckout);
          } else {
            reject(new Error('iNPAY Checkout initialisation failed.'));
          }
        };
        script.onerror = function() {
          reject(new Error('Unable to load iNPAY Checkout script.'));
        };
        document.head.appendChild(script);
      });
    }

    return sdkPromise;
  }

  function handleError(message) {
    notify(message, 'error');
  }

  function verifyPayment(checkout, reference, transactionId) {
    var payload = {
      reference: reference,
      transaction_id: transactionId,
      token: typeof token !== 'undefined' ? token : ''
    };

    fetch(checkout.verify_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function(response) { return response.json(); })
      .then(function(result) {
        if (result.status === 'success' && result.redirect_url) {
          window.location.href = result.redirect_url;
          return;
        }
        handleError(result.message || 'Unable to confirm payment.');
      })
      .catch(function() {
        handleError('Unable to reach verification endpoint.');
      });
  }

  form.addEventListener('submit', function(event) {
    event.preventDefault();
    pageOverlay.show();

    var data = $(form).serialize();
    data = data + '&' + $.param({token: token});

    $.post(PATH + 'add_funds/process', data, function(response) {
      setTimeout(function() { pageOverlay.hide(); }, 800);

      if (!is_json(response)) {
        setTimeout(function(){ $('.add-funds-form-content').html(response); }, 100);
        return;
      }

      var result = JSON.parse(response);
      if (result.status !== 'success' || !result.checkout) {
        handleError(result.message || 'Unable to start payment.');
        return;
      }

      loadSdk().then(function(Checkout) {
        var checkout = new Checkout();
        var config = result.checkout;
        var metadata = config.metadata ? JSON.stringify(config.metadata) : '';

        checkout.checkout({
          apiKey: config.public_key,
          amount: config.amount_kobo,
          email: config.email,
          firstName: config.first_name,
          lastName: config.last_name,
          metadata: metadata,
          reference: config.reference,
          onSuccess: function(resp) {
            var ref = resp && resp.reference ? resp.reference : config.reference;
            verifyPayment(config, ref, config.transaction_id);
          },
          onFailure: function(error) {
            handleError('Payment failed: ' + (error && error.message ? error.message : 'Unknown error'));
          },
          onExpired: function() {
            handleError('Payment session expired. Please try again.');
          },
          onError: function(error) {
            handleError('Payment error: ' + (error && error.message ? error.message : 'Unknown error'));
          }
        });
      }).catch(function(error) {
        handleError(error.message || 'Unable to start payment.');
      });
    });
  });
})();
</script>
