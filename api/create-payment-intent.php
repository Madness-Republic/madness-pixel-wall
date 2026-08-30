<?php
require '../vendor/autoload.php';

define('STRIPE_ACCESS', true);
require '../includes/env_loader.php';

\Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

header('Content-Type: application/json');

// Load dynamic settings
$settingsFile = __DIR__ . '/../data/settings.json';
$settings = json_decode(file_get_contents($settingsFile), true);
if (!$settings) {
    throw new Exception("Configurazione non trovata.");
}

$wallW = $settings['wall']['width'] ?? 1000;
$wallH = $settings['wall']['height'] ?? 400;

try {
    // Leggi il corpo della richiesta JSON
    $jsonStr = file_get_contents('php://input');
    $jsonObj = json_decode($jsonStr);

    if (!isset($jsonObj->pixels) || !is_array($jsonObj->pixels)) {
        throw new Exception("Dati pixel non validi o mancanti.");
    }

    $pixels = $jsonObj->pixels;
    $pixelCount = count($pixels);

    // Calcolo del prezzo lato server (per sicurezza)
    // Logica:Bounding Box (Terra) + Pixel Reali (Inchiostro)
    // NOTA: Questa logica deve corrispondere a quella dichiarata nel frontend (translations.js)

    // Trova i limiti del bounding box
    if ($pixelCount > 0) {
        $minX = $pixels[0]->x;
        $maxX = $pixels[0]->x;
        $minY = $pixels[0]->y;
        $maxY = $pixels[0]->y;

        foreach ($pixels as $p) {
            // VALIDATION: Check Bounds
            if ($p->x < 0 || $p->x >= $wallW || $p->y < 0 || $p->y >= $wallH) {
                http_response_code(400);
                throw new Exception("Pixel fuori dai limiti del muro (0-" . ($wallW - 1) . ", 0-" . ($wallH - 1) . ").");
            }

            if ($p->x < $minX)
                $minX = $p->x;
            if ($p->x > $maxX)
                $maxX = $p->x;
            if ($p->y < $minY)
                $minY = $p->y;
            if ($p->y > $maxY)
                $maxY = $p->y;
        }

        $width = ($maxX - $minX) + 1;
        $height = ($maxY - $minY) + 1;

        // --- ZONE AND TIER PRICING CALCULATION ---
        $wallDataFile = __DIR__ . '/../data/wall_data.json';
        $wallData = [];
        if (file_exists($wallDataFile)) {
            $wallData = json_decode(file_get_contents($wallDataFile), true) ?: [];
        }

        $presetFile = __DIR__ . '/../data/wall_preset.json';
        $presetData = [];
        if (($settings['wall']['use_preset'] ?? false) && file_exists($presetFile)) {
            $presetData = json_decode(file_get_contents($presetFile), true) ?: [];
        }

        // Build forbidden set
        $forbiddenSet = [];
        $border = (int)($settings['wall']['preset_padding'] ?? 4);
        if (!empty($presetData)) {
            foreach ($presetData as $key => $val) {
                $parts = explode(',', $key);
                if (count($parts) === 2) {
                    $px = (int)$parts[0];
                    $py = (int)$parts[1];
                    for ($dx = -$border; $dx <= $border; $dx++) {
                        for ($dy = -$border; $dy <= $border; $dy++) {
                            $forbiddenSet[($px + $dx) . ',' . ($py + $dy)] = true;
                        }
                    }
                }
            }
        }

        $userPixelMap = [];
        foreach ($pixels as $p) {
            $userPixelMap[$p->x . ',' . $p->y] = true;
        }

        $netAreaTop = 0;
        $netAreaVip = 0;
        $netAreaStandard = 0;

        for ($y = $minY; $y <= $maxY; $y++) {
            $zone = 'standard';
            if ($y < 150) $zone = 'top';
            else if ($y < 280) $zone = 'vip';

            for ($x = $minX; $x <= $maxX; $x++) {
                $key = $x . ',' . $y;
                $isOccupied = (!isset($userPixelMap[$key]) && 
                               (isset($wallData[$key]) || isset($forbiddenSet[$key])));
                if (!$isOccupied) {
                    if ($zone === 'top') $netAreaTop++;
                    else if ($zone === 'vip') $netAreaVip++;
                    else $netAreaStandard++;
                }
            }
        }

        $inkTop = 0;
        $inkVip = 0;
        $inkStandard = 0;

        foreach ($pixels as $p) {
            if ($p->y < 150) $inkTop++;
            else if ($p->y < 280) $inkVip++;
            else $inkStandard++;
        }

        $zones = $settings['pricing']['zones'] ?? [];
        
        $landRateTop = $zones['top']['land_rate_cents'] ?? 15;
        $inkRateTop = $zones['top']['ink_rate_cents'] ?? 10;

        $landRateVip = $zones['vip']['land_rate_cents'] ?? 20;
        $inkRateVip = $zones['vip']['ink_rate_cents'] ?? 12;

        $landRateStandard = $zones['standard']['land_rate_cents'] ?? 10;
        $inkRateStandard = $zones['standard']['ink_rate_cents'] ?? 8;

        $totalSold = count($wallData);
        $landMultiplier = 1.0;
        if ($totalSold > 250000) {
            $landMultiplier = 1.5;
        } else if ($totalSold > 100000) {
            $landMultiplier = 1.25;
        }

        $landCostCents = ($netAreaTop * $landRateTop + $netAreaVip * $landRateVip + $netAreaStandard * $landRateStandard) * $landMultiplier;
        $inkCostCents = $inkTop * $inkRateTop + $inkVip * $inkRateVip + $inkStandard * $inkRateStandard;

        $netAmountCents = ceil($landCostCents + $inkCostCents);
        $areaCm2 = $netAreaTop + $netAreaVip + $netAreaStandard;
    } else {
        $areaCm2 = 0;
        $netAmountCents = 0;
    }

    // --- FEE PASSING: Customer pays Stripe Fees ---
    $stripeFixedCents = $settings['pricing']['stripe_fixed_cents'] ?? 25;
    $stripePercent = $settings['pricing']['stripe_percent'] ?? 0.015;
    $minChargeCents = $settings['pricing']['min_charge_cents'] ?? 50;

    // Formula: Charge = (Net + Fixed) / (1 - Percent)
    if ($netAmountCents > 0) {
        $totalAmountCents = ceil(($netAmountCents + $stripeFixedCents) / (1 - $stripePercent));
    } else {
        $totalAmountCents = 0;
    }

    // Stripe Minimum Charge
    if ($totalAmountCents < $minChargeCents) {
        $totalAmountCents = $minChargeCents;
    }

    // Leggi gclid dal body JSON o dal Cookie di prima parte
    $gclid = isset($jsonObj->gclid) ? trim($jsonObj->gclid) : '';
    if (empty($gclid) && isset($_COOKIE['madness_gclid'])) {
        $gclid = trim($_COOKIE['madness_gclid']);
    }

    // Crea il PaymentIntent
    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => $totalAmountCents,
        'currency' => 'eur',
        'description' => "Contributo Pixel Wall: $pixelCount pixel (Area: $areaCm2 cm²)", // Shows on Receipt
        'automatic_payment_methods' => [
            'enabled' => true,
        ],
        'metadata' => [
            'pixel_count' => $pixelCount,
            'area_cm2' => $areaCm2,
            'gclid' => $gclid,
            // Potresti salvare qui i pixel compressi o un riferimento a un ID temporaneo nel DB
        ],
    ]);

    $output = [
        'clientSecret' => $paymentIntent->client_secret,
        'amountFormatted' => number_format($totalAmountCents / 100, 2) . ' €', // Per conferma visiva
    ];

    echo json_encode($output);

} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>