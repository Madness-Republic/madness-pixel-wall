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
        $areaCm2 = $width * $height; // 1 pixel = 1 cm2 nella tua logica attuale
    } else {
        $areaCm2 = 0;
    }

    // Tariffe (in CENTESIMI per Stripe) - Loaded from Settings
    $landRateCents = $settings['pricing']['land_rate_cents'] ?? 20;
    $inkRateCents = $settings['pricing']['ink_rate_cents'] ?? 30;

    $landCost = $areaCm2 * $landRateCents;
    $inkCost = $pixelCount * $inkRateCents;

    $netAmountCents = $landCost + $inkCost;

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