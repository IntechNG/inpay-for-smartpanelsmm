<?php
  $webhook_url = cn('add_funds/inpaycheckout/webhook');
  $base_currency = get_option('currency_code', 'USD');
  $currency_rate_value = isset($payment_option->currency_rate) ? $payment_option->currency_rate : 1;
  $payment_elements = [
    [
      'label'      => form_label('Public key'),
      'element'    => form_input(['name' => 'payment_params[option][public_key]', 'value' => @$payment_option->public_key, 'type' => 'text', 'class' => $class_element]),
      'class_main' => 'col-md-12 col-sm-12 col-xs-12',
    ],
    [
      'label'      => form_label('Secret key'),
      'element'    => form_input(['name' => 'payment_params[option][secret_key]', 'value' => @$payment_option->secret_key, 'type' => 'text', 'class' => $class_element]),
      'class_main' => 'col-md-12 col-sm-12 col-xs-12',
    ],
    [
      'label'      => form_label('Currency Rate'),
      'element'    => '<div class="input-group"><div class="input-group-prepend"><span class="input-group-text">1 ' . strtoupper($base_currency) . ' =</span></div>' .
                       form_input(['name' => 'payment_params[option][currency_rate]', 'value' => $currency_rate_value, 'type' => 'number', 'step' => '0.0001', 'min' => '0', 'class' => $class_element]) .
                       '<div class="input-group-append"><span class="input-group-text">NGN</span></div></div>' .
                       '<small class="text-muted d-block mt-1">Leave as 1 if your platform currency is already NGN.</small>',
      'class_main' => 'col-md-12 col-sm-12 col-xs-12',
    ],
    [
      'label'      => form_label('Environment'),
      'element'    => '<input type="text" class="' . $class_element . '" value="Live only" readonly>',
      'class_main' => 'col-md-12 col-sm-12 col-xs-12',
    ],
    [
      'label'      => form_label('Webhook URL'),
      'element'    => '<input type="text" class="' . $class_element . '" value="' . $webhook_url . '" readonly>',
      'class_main' => 'col-md-12 col-sm-12 col-xs-12',
    ],
  ];
  echo render_elements_form($payment_elements);
?>
