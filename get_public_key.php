<?php
define('STRIPE_ACCESS', true);
require 'private/Stripe_keys_a7b2c9.php';
header('Content-Type: application/json');
echo json_encode(['publicKey' => $stripePublicKey]);
?>