<?php
// Simple .env Loader
// Created to secure Stripe Keys

function loadEnv($path)
{
    if (!file_exists($path)) {
        // If no .env, we assume environment variables are set by the server
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load from .env in the parent directory
loadEnv(dirname(__DIR__) . '/.env');

// OVERRIDE: Load from Admin-saved Stripe Config if exists
$stripeConfigPath = dirname(__DIR__) . '/private/stripe_config.php';
if (file_exists($stripeConfigPath)) {
    $stripeConf = include($stripeConfigPath);
    if (is_array($stripeConf)) {
        if (!empty($stripeConf['secret_key'])) {
            $_ENV['STRIPE_SECRET_KEY'] = $stripeConf['secret_key'];
            $_SERVER['STRIPE_SECRET_KEY'] = $stripeConf['secret_key'];
            putenv("STRIPE_SECRET_KEY=" . $stripeConf['secret_key']);
        }
        if (!empty($stripeConf['publishable_key'])) {
            $_ENV['STRIPE_PUBLIC_KEY'] = $stripeConf['publishable_key'];
            $_SERVER['STRIPE_PUBLIC_KEY'] = $stripeConf['publishable_key'];
            putenv("STRIPE_PUBLIC_KEY=" . $stripeConf['publishable_key']);
        }
        if (!empty($stripeConf['webhook_secret'])) {
            $_ENV['STRIPE_WEBHOOK_SECRET'] = $stripeConf['webhook_secret'];
            $_SERVER['STRIPE_WEBHOOK_SECRET'] = $stripeConf['webhook_secret'];
            putenv("STRIPE_WEBHOOK_SECRET=" . $stripeConf['webhook_secret']);
        }
    }
}

// Helper to get env with fallback
if (!function_exists('env')) {
    function env($key, $default = null)
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }
}