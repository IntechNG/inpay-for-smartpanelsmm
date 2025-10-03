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
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `sort` = VALUES(`sort`),
  `min` = VALUES(`min`),
  `max` = VALUES(`max`),
  `new_users` = VALUES(`new_users`),
  `status` = VALUES(`status`),
  `params` = VALUES(`params`);
