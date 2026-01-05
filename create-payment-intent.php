<?php
require 'vendor/autoload.php';

define('STRIPE_ACCESS', true);
require 'private/Stripe_keys_a7b2c9.php';
// $stripeSecretKey is defined in the included file

\Stripe\Stripe::setApiKey($stripeSecretKey);

header('Content-Type: application/json');

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
            // VALIDATION: Check Bounds (0-999 width, 0-399 height)
            if ($p->x < 0 || $p->x > 999 || $p->y < 0 || $p->y > 399) {
                http_response_code(400);
                throw new Exception("Pixel fuori dai limiti del muro (0-999, 0-399).");
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

    // Tariffe (in CENTESIMI per Stripe)
    // 0.20€/cm2 -> 20 centesimi
    // 0.30€/px -> 30 centesimi
    $landRateCents = 20;
    $inkRateCents = 30;

    $landCost = $areaCm2 * $landRateCents;
    $inkCost = $pixelCount * $inkRateCents;

    $totalAmountCents = $landCost + $inkCost;

    // Minimo di Stripe è solitamente 50 centesimi, assicuriamoci di raggiungerlo
    if ($totalAmountCents < 50) {
        $totalAmountCents = 50;
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