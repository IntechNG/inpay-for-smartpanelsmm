<?php
  $webhook_url = cn('add_funds/inpaycheckout/webhook');
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
