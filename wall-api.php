<?php
// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$type = $_GET['type'] ?? 'wall'; // 'wall', 'contributors', 'transactions'

// Helper function for Atomic Writes
function atomicJsonUpdate($filePath, $callback)
{
    // Open for reading and writing; place the file pointer at the beginning.
    $fp = fopen($filePath, 'c+');
    if (!$fp) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not open file ' . $filePath]);
        exit;
    }

    if (flock($fp, LOCK_EX)) { // Acquire an exclusive lock
        $filesize = filesize($filePath);
        $content = $filesize > 0 ? fread($fp, $filesize) : null;

        $currentData = [];
        if ($content) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $currentData = $decoded;
            }
        }

        // Execute logic (Callback returns modified data or null to abort)
        $newData = $callback($currentData);

        if ($newData !== null) {
            ftruncate($fp, 0);      // Truncate file
            rewind($fp);            // Rewind pointer
            // JSON_PRETTY_PRINT is optional/debug, maybe remove for prod size, 
            // but for now it helps verification. 
            // Actually, for wall_data (huge), we should NOT use pretty print.
            // Let's pass a flag or check filename? 
            // Or just compact. Compact is better for wall_data.
            fwrite($fp, json_encode($newData));
        }

        fflush($fp);            // Flush output
        flock($fp, LOCK_UN);    // Release the lock
    } else {
        http_response_code(503);
        echo json_encode(['error' => 'Could not lock file ' . $filePath]);
        exit;
    }

    fclose($fp);
}

// ----------------------------------------------------
// 1. TRANSACTIONS LOGIC (Stats: Money & Dates)
// ----------------------------------------------------
if ($type === 'transactions') {
    $dataFile = 'data/transactions.json';

    // GET: Read all transactions
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['stats'])) {
            // Internal stats for HUD
            $lastDate = null;
            if (file_exists($dataFile)) {
                $txns = json_decode(file_get_contents($dataFile), true) ?: [];
                foreach ($txns as $t) {
                    $totalAmount += (float) ($t['amount'] ?? 0);
                    // Find latest date (ISO 8601 string sortable)
                    if (isset($t['date'])) {
                        if ($lastDate === null || $t['date'] > $lastDate) {
                            $lastDate = $t['date'];
                        }
                    }
                }
            }

            $foundGold = 0;
            if (file_exists('data/winners.json')) {
                $winners = json_decode(file_get_contents('data/winners.json'), true) ?: [];
                $foundGold = count($winners);
            }

            $foundSilver = 0;
            if (file_exists('data/winners_silver.json')) {
                $sWinners = json_decode(file_get_contents('data/winners_silver.json'), true) ?: [];
                $foundSilver = count($sWinners);
            }

            header('Content-Type: application/json');
            echo json_encode([
                'total_raised' => $totalAmount,
                'found_gold' => $foundGold,
                'found_silver' => $foundSilver,
                'last_contribution' => $lastDate
            ]);
        } else {
            header('Content-Type: application/json');
            if (file_exists($dataFile))
                readfile($dataFile);
            else
                echo '[]';
        }
        exit;
    }

    // POST: Add new transaction (Server-side duplicate check)
    // DISABLED FOR SECURITY: Transactions are now recorded automatically upon valid PaymentIntent verification in the WALL logic block.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(403);
        echo json_encode(['error' => 'Manual transaction submission disabled.']);
        exit;
    }
}

// ----------------------------------------------------
// 2. CONTRIBUTORS LOGIC (Word Cloud: Names & Amounts)
// ----------------------------------------------------
if ($type === 'contributors') {
    $dataFile = 'data/contributors.json';

    // GET: Read contributors
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json');
        if (file_exists($dataFile))
            readfile($dataFile);
        else
            echo '[]';
        exit;
    }

    // POST: Add/Update contributor (Only if user signed)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $newContrib = json_decode($input, true);

        if (!$newContrib || !isset($newContrib['name']) || !isset($newContrib['amount'])) {
            http_response_code(400);
            echo 'Invalid Data';
            exit;
        }

        atomicJsonUpdate($dataFile, function ($currentData) use ($newContrib) {
            // SECURITY CHECK: Verify TxnID exists in verified transactions list
            $txnId = $newContrib['id'] ?? null;
            if (!$txnId)
                return null; // Abort if no ID

            $verified = false;
            $txnsFile = 'data/transactions.json';
            if (file_exists($txnsFile)) {
                $allTxns = json_decode(file_get_contents($txnsFile), true) ?: [];
                foreach ($allTxns as $t) {
                    if (isset($t['id']) && $t['id'] === $txnId) {
                        $verified = true;
                        break;
                    }
                }
            }

            if (!$verified) {
                // Log attempt?
                return null; // Reject signature for unknown/unverified transaction
            }
            $found = false;

            if ($txnId) {
                foreach ($currentData as &$entry) {
                    if (isset($entry['id']) && $entry['id'] === $txnId) {
                        $entry['name'] = $newContrib['name']; // Update name
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                $currentData[] = $newContrib;
            }
            return $currentData;
        });

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

// ----------------------------------------------------
// 3. WALL LOGIC (Pixel Data)
// ----------------------------------------------------
// ----------------------------------------------------
// 3. WALL LOGIC (Pixel Data)
// ----------------------------------------------------
$dataFile = 'data/wall_data.json';
$ownersFile = 'data/pixel_owners.json';

// POST: Save pixels (Merge with existing)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $payload = json_decode($input, true);

    if (!$payload) {
        http_response_code(400);
        echo 'Invalid Data';
        exit;
    }

    // Check if it's the new Enriched format (with metadata)
    $newPixels = [];
    $metaData = null;

    if (isset($payload['pixels']) && is_array($payload['pixels'])) {
        // New Format: { "pixels": {"x,y":"#col"}, "meta": {"email":..., "txnId":...} }
        $newPixels = $payload['pixels'];
        $metaData = $payload['meta'] ?? [];
    } else {
        // Legacy Format not supported for secured writes
        http_response_code(403);
        echo json_encode(['error' => 'Legacy format rejected. Metadata required.']);
        exit;
    }

    // --- SECURITY CHECK START ---
    require_once 'vendor/autoload.php';
    define('STRIPE_ACCESS', true);
    require_once 'private/Stripe_keys_a7b2c9.php';
    \Stripe\Stripe::setApiKey($stripeSecretKey);

    $txnId = $metaData['txnId'] ?? null;

    if (!$txnId) {
        http_response_code(403);
        echo json_encode(['error' => 'Missiong Transaction ID.']);
        exit;
    }

    try {
        $intent = \Stripe\PaymentIntent::retrieve($txnId);
        $amount = $intent->amount; // Capture amount for stats

        // 1. Check Status
        if ($intent->status !== 'succeeded') {
            http_response_code(403);
            echo json_encode(['error' => 'Payment not succeeded. Status: ' . $intent->status]);
            exit;
        }

        // 2. Check Integrity (Pixel Count match)
        // 2. Check Integrity (Pixel Count & Price match)
        // Recalculate expected price server-side to prevent "Land Expansion" attacks
        // (Paying for small bounding box, submitting scattered pixels)

        $minX = 9999;
        $maxX = -9999;
        $minY = 9999;
        $maxY = -9999;

        foreach ($newPixels as $key => $color) {
            $coords = explode(',', $key);
            if (count($coords) !== 2) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid pixel key format']);
                exit;
            }
            $x = intval($coords[0]);
            $y = intval($coords[1]);

            if ($x < $minX)
                $minX = $x;
            if ($x > $maxX)
                $maxX = $x;
            if ($y < $minY)
                $minY = $y;
            if ($y > $maxY)
                $maxY = $y;
        }

        $width = ($maxX - $minX) + 1;
        $height = ($maxY - $minY) + 1;
        $areaCm2 = $width * $height;
        $count = count($newPixels);

        $landRateCents = 20;
        $inkRateCents = 30;

        $expectedCostCents = ($areaCm2 * $landRateCents) + ($count * $inkRateCents);
        if ($expectedCostCents < 50)
            $expectedCostCents = 50; // Minimum Stripe charge

        $paidAmountCents = $intent->amount;

        // Verify Price Integrity (Exact match expected)
        if ($paidAmountCents < $expectedCostCents) {
            http_response_code(403);
            echo json_encode([
                'error' => "Pricing Mismatch. Paid: {$paidAmountCents}, Expected: {$expectedCostCents}. Cheating detected (Area expansion?)."
            ]);
            exit;
        }

        // Also check metadata count just to be sure
        $paidCountMeta = intval($intent->metadata->pixel_count);
        if ($paidCountMeta !== $count) {
            http_response_code(403);
            echo json_encode(['error' => "Pixel Count Mismatch."]);
            exit;
        }

    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Stripe Verification Failed: ' . $e->getMessage()]);
        exit;
    }
    // --- SECURITY CHECK END ---

    // 1. Update Visual Wall (Colors)
    atomicJsonUpdate($dataFile, function ($currentData) use ($newPixels) {
        return array_merge($currentData, $newPixels);
    });

    // 1.1 RECORD TRANSACTION (Securely)
    // Now that Stripe is verified, we record the transaction amount natively here.
    $txnFile = 'data/transactions.json';
    $txnRecord = [
        'id' => $txnId,
        'amount' => $amount / 100, // Stripe amount is in cents
        'email' => $metaData['email'] ?? 'unknown',
        'date' => date('c')
    ];

    atomicJsonUpdate($txnFile, function ($currentData) use ($txnRecord) {
        // Idempotency Check
        foreach ($currentData as $entry) {
            if (isset($entry['id']) && $entry['id'] === $txnRecord['id']) {
                return null;
            }
        }
        $currentData[] = $txnRecord;
        return $currentData;
    });

    // 2. Update Ownership Data (Hidden) - Only if metadata exists
    if ($metaData && !empty($metaData['email'])) {
        atomicJsonUpdate($ownersFile, function ($currentData) use ($newPixels, $metaData) {
            $email = $metaData['email'];
            $timestamp = date('c');

            // Get keys (coordinates) of the new pixels
            $newKeys = array_keys($newPixels);

            // Initialize or retrieve user record
            if (!isset($currentData[$email])) {
                $currentData[$email] = [
                    'pixels' => [],
                    'txns' => [] // Keep track of transactions just in case
                ];
            }

            // Append new pixels (using array_unique to avoid duplicates if re-sent, though keys should be unique per batch)
            // We merge and uniquely store to ensure a clean list
            $currentData[$email]['pixels'] = array_values(array_unique(array_merge($currentData[$email]['pixels'], $newKeys)));

            // Track transaction ID if provided
            if (!empty($metaData['txnId'])) {
                if (!in_array($metaData['txnId'], $currentData[$email]['txns'])) {
                    $currentData[$email]['txns'][] = $metaData['txnId'];
                }
            }

            // Update timestamp
            $currentData[$email]['last_update'] = $timestamp;

            return $currentData;
        });
    }

    // 3. GOLDEN PIXEL REWARD CHECK
    $isWinner = false;
    $secretFile = 'private/secret_gold_pixels.php';
    if (file_exists($secretFile)) {
        // Define variable to capture winner status inside closure
        $winnerFoundInBatch = false;

        $goldPixels = include $secretFile;
        if (is_array($goldPixels)) {
            $purchasedKeys = array_keys($newPixels);
            $foundGold = array_intersect($purchasedKeys, $goldPixels);

            if (!empty($foundGold)) {
                $winnersFile = 'data/winners.json';
                // Use atomic update to record winners safely
                atomicJsonUpdate($winnersFile, function ($currentWinners) use ($foundGold, $metaData, &$winnerFoundInBatch) {
                    if (!is_array($currentWinners))
                        $currentWinners = [];

                    // Get list of already claimed pixels
                    $claimedPixels = array_column($currentWinners, 'pixel');

                    foreach ($foundGold as $gold) {
                        if (!in_array($gold, $claimedPixels)) {
                            // NEW UNCLAIMED WINNER!
                            $currentWinners[] = [
                                'pixel' => $gold,
                                'email' => $metaData['email'] ?? 'unknown',
                                'txnId' => $metaData['txnId'] ?? 'unknown',
                                'date' => date('c')
                            ];
                            $winnerFoundInBatch = true;
                        }
                    }
                    return $currentWinners;
                });
                $isWinner = $winnerFoundInBatch;
            }
        }
    }

    // 4. SILVER PIXEL REWARD CHECK
    $isSilverWinner = false;
    $secretSilverFile = 'private/secret_silver_pixels.php';
    if (file_exists($secretSilverFile)) {
        $silverFoundInBatch = false;
        $silverPixels = include $secretSilverFile;

        if (is_array($silverPixels)) {
            $purchasedKeys = array_keys($newPixels); // Re-use keys
            $foundSilver = array_intersect($purchasedKeys, $silverPixels);

            if (!empty($foundSilver)) {
                $winnersSilverFile = 'data/winners_silver.json';
                atomicJsonUpdate($winnersSilverFile, function ($currentWinners) use ($foundSilver, $metaData, &$silverFoundInBatch) {
                    if (!is_array($currentWinners))
                        $currentWinners = [];
                    $claimedPixels = array_column($currentWinners, 'pixel');

                    foreach ($foundSilver as $silver) {
                        if (!in_array($silver, $claimedPixels)) {
                            // NEW SILVER WINNER
                            $currentWinners[] = [
                                'pixel' => $silver,
                                'email' => $metaData['email'] ?? 'unknown',
                                'txnId' => $metaData['txnId'] ?? 'unknown',
                                'date' => date('c')
                            ];
                            $silverFoundInBatch = true;
                        }
                    }
                    return $currentWinners;
                });
                $isSilverWinner = $silverFoundInBatch;
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'isWinner' => $isWinner, // Gold
        'isSilverWinner' => $isSilverWinner // Silver
    ]);
    exit;
}

// GET: Read data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    if (file_exists($dataFile)) {
        readfile($dataFile);
    } else {
        echo '{}';
    }
    exit;
}
?>