<?php
require '../includes/env_loader.php';
header('Content-Type: application/json');
echo json_encode(['publicKey' => env('STRIPE_PUBLIC_KEY')]);
?>