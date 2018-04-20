<?php
require_once dirname(__FILE__) . '/lib/Stripe.php';

$stripe = array(
  "secret_key"      => "sk_test_xpUdgw6FKrKc1noVnqVaNXRG",
  "publishable_key" => "pk_test_b1FgKnZfYVxE8g3Zf5D22fzg"
);

Stripe::setApiKey($stripe['secret_key']);