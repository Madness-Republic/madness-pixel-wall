<?php
// Include Security Headers (HSTS, NoSniff, etc.)
require_once __DIR__ . '/../includes/security_headers.php';

// Handle CORS (Explicitly override if needed, though security_headers handles general security)
// Handle CORS (Restricted to specific origins)
$allowedOrigins = [
    'https://www.madnessrepublic.com',
    'http://localhost:8080',
    'http://127.0.0.1:8080'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Disable error printing (logs only) to avoid breaking JSON output
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$type = $_GET['type'] ?? 'wall'; // 'wall', 'contributors', 'transactions', 'winners', 'settings'

// Helper function for Atomic Writes
function atomicJsonUpdate($filePath, $callback)
{
    // Use a separate lock file to ensure that the read-modify-write cycle is exclusive,
    // even across file renames (which would change the inode of the main file).
    $lockFile = $filePath . '.lock';
    
    // Open lock file with w+ (creates if not exists)
    $fpLock = fopen($lockFile, 'w+'); 
    if (!$fpLock) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not open lock file']);
        exit;
    }

    if (flock($fpLock, LOCK_EX)) {
        // 1. Read current data
        $currentData = [];
        if (file_exists($filePath)) {
            $content = @file_get_contents($filePath);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $currentData = $decoded;
                }
            }
        }

        // 2. Execute Logic
        $newData = $callback($currentData);

        if ($newData !== null) {
            // 3. Atomic Write (Write tmp -> Rename)
            $tempPath = $filePath . '.tmp';
            
            // Encode data
            $jsonOutput = json_encode($newData);
            if ($jsonOutput === false) {
                 // Encoding failed
                 error_log("JSON Encoding failed in atomicJsonUpdate for $filePath");
            } else {
                 $writeResult = file_put_contents($tempPath, $jsonOutput);
                 
                 if ($writeResult !== false) {
                    // Ensure permissions
                    @chmod($tempPath, 0666);
                    
                    // Atomic Rename
                    rename($tempPath, $filePath);
                    
                    // Ensure permissions on final file
                    @chmod($filePath, 0666);
                 } else {
                     error_log("Failed to write temp file: $tempPath");
                 }
            }
        }

        flock($fpLock, LOCK_UN);
    } else {
        http_response_code(503);
        echo json_encode(['error' => 'Could not acquire lock']);
        exit;
    }

    fclose($fpLock);
    return true;
}

// ----------------------------------------------------
// 0. WALL DATA (Main Canvas)
// ----------------------------------------------------
if ($type === 'wall') {
    $dataFile = __DIR__ . '/../data/wall_data.json';

    // GET: Read wall data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (file_exists($dataFile)) {
            readfile($dataFile);
        } else {
            echo '{}';
        }
        exit;
    }
}

// ----------------------------------------------------
// 1. TRANSACTIONS LOGIC (Stats: Money & Dates)
// ----------------------------------------------------
if ($type === 'transactions') {
    $dataFile = __DIR__ . '/../data/transactions.json';

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
            if (file_exists(__DIR__ . '/../data/winners.json')) {
                $winners = json_decode(file_get_contents(__DIR__ . '/../data/winners.json'), true) ?: [];
                $foundGold = count($winners);
            }

            $foundSilver = 0;
            if (file_exists(__DIR__ . '/../data/winners_silver.json')) {
                $sWinners = json_decode(file_get_contents(__DIR__ . '/../data/winners_silver.json'), true) ?: [];
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
            // SECURITY: Do not expose raw transaction log to public (contains PII)
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. Use stats=1 for aggregate data.']);
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
// 1.1 WINNERS LOGIC (Public Coordinates for Animation)
// ----------------------------------------------------
if ($type === 'winners') {
    $goldFile = __DIR__ . '/../data/winners.json';
    $silverFile = __DIR__ . '/../data/winners_silver.json';
    $contribFile = __DIR__ . '/../data/contributors.json';

    // Build txnId -> name lookup
    $nameMap = [];
    if (file_exists($contribFile)) {
        $contribs = json_decode(file_get_contents($contribFile), true) ?: [];
        foreach ($contribs as $c) {
            if (isset($c['id']) && isset($c['name'])) {
                $nameMap[$c['id']] = $c['name'];
            }
        }
    }

    $goldData = [];
    if (file_exists($goldFile)) {
        $winners = json_decode(file_get_contents($goldFile), true) ?: [];
        foreach ($winners as $w) {
            if (isset($w['pixel'])) {
                $goldData[] = [
                    'pixel' => $w['pixel'],
                    'name' => isset($w['txnId']) ? ($nameMap[$w['txnId']] ?? 'Anonymous') : 'Anonymous'
                ];
            }
        }
    }

    $silverData = [];
    if (file_exists($silverFile)) {
        $winners = json_decode(file_get_contents($silverFile), true) ?: [];
        foreach ($winners as $w) {
            if (isset($w['pixel'])) {
                $silverData[] = [
                    'pixel' => $w['pixel'],
                    'name' => isset($w['txnId']) ? ($nameMap[$w['txnId']] ?? 'Anonymous') : 'Anonymous'
                ];
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'gold' => $goldData,
        'silver' => $silverData
    ]);
    exit;
}

// ----------------------------------------------------
// 1.2 SETTINGS LOGIC (Public Configuration)
// ----------------------------------------------------
if ($type === 'settings') {
    $settingsFile = __DIR__ . '/../data/settings.json';
    if (file_exists($settingsFile)) {
        header('Content-Type: application/json');
        readfile($settingsFile);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Settings not found']);
    }
    exit;
}

// ----------------------------------------------------
// 2. CONTRIBUTORS LOGIC (Word Cloud: Names & Amounts)
// ----------------------------------------------------
if ($type === 'contributors') {
    $dataFile = __DIR__ . '/../data/contributors.json';

    // GET: Read contributors
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json');
        if (file_exists($dataFile)) {
            $content = @file_get_contents($dataFile);
            // If empty or invalid JSON, return empty array
            if (empty(trim($content)) || json_decode($content) === null) {
                echo '[]';
            } else {
                echo $content;
            }
        } else {
            echo '[]';
        }
        exit;
    }

    // POST: Add/Update contributor (Only if user signed)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $newContrib = json_decode($input, true);

        if (!$newContrib || !isset($newContrib['name']) || !isset($newContrib['amount'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Data']);
            exit;
        }

        // SECURITY: Sanitize Name
        $safeName = strip_tags($newContrib['name']);
        $safeName = htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8');
        $safeName = mb_substr($safeName, 0, 50); // Max length limit
        $newContrib['name'] = $safeName;

        $txnId = $newContrib['id'] ?? null;
        if (!$txnId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing Transaction ID']);
            exit;
        }

        // 1. Verify Transaction exists
        $verified = false;
        $txnsFile = __DIR__ . '/../data/transactions.json';
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
            http_response_code(403);
            echo json_encode(['error' => 'Transaction ID not found or unverified. Signature rejected.']);
            exit;
        }

        // 2. Perform Atomic Update
        atomicJsonUpdate($dataFile, function ($currentData) use ($newContrib, $txnId) {
            $found = false;
            foreach ($currentData as &$entry) {
                if (isset($entry['id']) && $entry['id'] === $txnId) {
                    $entry['name'] = $newContrib['name']; // Update name
                    $found = true;
                    break;
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
// 2.1 REACTIONS LOGIC (News/Log Reactions)
// ----------------------------------------------------
if ($type === 'reactions') {
    $updatesFile = __DIR__ . '/../data/updates.json';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $payload = json_decode($input, true);
        $postId = $payload['id'] ?? '';
        $add = $payload['add'] ?? '';    // 'likes' or 'hearts'
        $remove = $payload['remove'] ?? ''; // 'likes' or 'hearts'

        if (!$postId || (!$add && !$remove)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
            exit;
        }

        atomicJsonUpdate($updatesFile, function ($data) use ($postId, $add, $remove) {
            $found = false;
            foreach ($data as &$post) {
                if (isset($post['id']) && $post['id'] === $postId) {
                    if ($remove && in_array($remove, ['likes', 'hearts'])) {
                        $post[$remove] = max(0, (int) ($post[$remove] ?? 0) - 1);
                    }
                    if ($add && in_array($add, ['likes', 'hearts'])) {
                        $post[$add] = (int) ($post[$add] ?? 0) + 1;
                    }
                    $found = true;
                    break;
                }
            }
            return $found ? $data : null;
        });

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    exit;
}


// ----------------------------------------------------
// 3. WALL LOGIC (Pixel Data)
// ----------------------------------------------------
$dataFile = __DIR__ . '/../data/wall_data.json';
$ownersFile = __DIR__ . '/../data/pixel_owners.json';

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

    // --- SECURITY: INPUT VALIDATION ---
// Validate email if present
    if (isset($metaData['email'])) {
        $metaData['email'] = filter_var($metaData['email'], FILTER_SANITIZE_EMAIL);
    }
    if (isset($metaData['referral'])) {
        $metaData['referral'] = filter_var($metaData['referral'], FILTER_SANITIZE_EMAIL);
    }

    // Validate Pixel Colors (Hex only)
// Validate Pixel Colors (Hex or RGB)
    foreach ($newPixels as $key => $color) {
        $isHex = preg_match('/^#[0-9a-fA-F]{6}$/', $color);
        $isRgb = preg_match('/^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$/', $color);

        if (!$isHex && !$isRgb) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Invalid color format found. Expected Hex or RGB. Got: ' . substr(
                    htmlspecialchars($color),
                    0,
                    20
                )
            ]);
            exit;
        }
    }

    // --- SECURITY CHECK START ---
    require_once __DIR__ . '/../vendor/autoload.php';
    define('STRIPE_ACCESS', true);
    require_once __DIR__ . '/../includes/env_loader.php';

    // Priority: Secure Config File > Env Variables
    $stripeConfigPath = __DIR__ . '/../private/stripe_config.php';
    $stripeSecret = null;

    if (file_exists($stripeConfigPath)) {
        $stripeConf = include $stripeConfigPath;
        if (!empty($stripeConf['secret_key'])) {
            $stripeSecret = $stripeConf['secret_key'];
        }
    }

    if (!$stripeSecret) {
        $stripeSecret = env('STRIPE_SECRET_KEY');
    }

    \Stripe\Stripe::setApiKey($stripeSecret);

    $txnId = $metaData['txnId'] ?? null;

    if (!$txnId) {
        http_response_code(403);
        echo json_encode(['error' => 'Missing Transaction ID.']);
        exit;
    }

    try {
        $intent = \Stripe\PaymentIntent::retrieve($txnId);
        $amount = $intent->amount; // Capture amount for stats

        // 2. Strict Payment Verification (Pay-Less-Paint-More Protection)
        $paidCount = (int) ($intent->metadata['pixel_count'] ?? 0);
        $paidArea = (int) ($intent->metadata['area_cm2'] ?? 0);

        // Calculate Submitted Stats
        $submittedCount = count($newPixels);
        $minX = PHP_INT_MAX;
        $maxX = PHP_INT_MIN;
        $minY = PHP_INT_MAX;
        $maxY = PHP_INT_MIN;

        foreach ($newPixels as $key => $color) {
            [$x, $y] = explode(',', $key);
            $x = (int) $x;
            $y = (int) $y;
            if ($x < $minX)
                $minX = $x;
            if ($x > $maxX)
                $maxX = $x;
            if ($y < $minY)
                $minY = $y;
            if ($y > $maxY)
                $maxY = $y;
        }
        $width = ($submittedCount > 0) ? ($maxX - $minX) + 1 : 0;
        $height = ($submittedCount > 0) ? ($maxY - $minY) + 1 : 0;
        $submittedArea = $width * $height;

        // Tolerance: Allow small margin? No, exact matches from intent creation.
        // Actually, allow <= because user might delete pixels last second? 
        // No, the intent was created for a specific set.
        // But let's check strict LE (Less or Equal).
        if ($submittedCount > $paidCount || $submittedArea > $paidArea) {
            http_response_code(403);
            echo json_encode([
                'error' => "Validation Failed. Paid for $paidCount px / $paidArea cm2. Received $submittedCount px / $submittedArea cm2."
            ]);
            exit;
        }

    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Stripe Verification Failed: ' . $e->getMessage()]);
        exit;
    }
    // --- SECURITY CHECK END ---

    // 1. RECORD TRANSACTION FIRST (Prevents Replay Attacks)
    $txnFile = __DIR__ . '/../data/transactions.json';
    $txnRecord = [
        'id' => $txnId,
        'amount' => $amount / 100, // Stripe amount is in cents
        'email' => $metaData['email'] ?? 'unknown',
        'referral' => $metaData['referral'] ?? '',
        'date' => date('c')
    ];

    $transactionRecorded = false;
    atomicJsonUpdate($txnFile, function ($currentData) use ($txnRecord, &$transactionRecorded) {
        // Idempotency Check
        foreach ($currentData as $entry) {
            if (isset($entry['id']) && $entry['id'] === $txnRecord['id']) {
                return null; // Duplicate found, return null to abort write
            }
        }
        $currentData[] = $txnRecord;
        $transactionRecorded = true;
        return $currentData;
    });

    if (!$transactionRecorded) {
        http_response_code(403);
        echo json_encode(['error' => 'Transaction ID already used or invalid. Replay attack detected.']);
        exit;
    }

    // 2. Update Visual Wall (Colors) - Executed ONLY if transaction was new
    atomicJsonUpdate($dataFile, function ($currentData) use ($newPixels) {
        return array_merge($currentData, $newPixels);
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
    $secretFile = __DIR__ . '/../private/secret_gold_pixels_86b5a7.php';
    if (file_exists($secretFile)) {
        // Define variable to capture winner status inside closure
        $winnerFoundInBatch = false;

        $goldPixels = include $secretFile;
        if (is_array($goldPixels)) {
            $purchasedKeys = array_keys($newPixels);
            $foundGold = array_intersect($purchasedKeys, $goldPixels);

            if (!empty($foundGold)) {
                $winnersFile = __DIR__ . '/../data/winners.json';
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
    $secretSilverFile = __DIR__ . '/../private/secret_silver_pixels_187800.php';
    if (file_exists($secretSilverFile)) {
        $silverFoundInBatch = false;
        $silverPixels = include $secretSilverFile;

        if (is_array($silverPixels)) {
            $purchasedKeys = array_keys($newPixels); // Re-use keys
            $foundSilver = array_intersect($purchasedKeys, $silverPixels);

            if (!empty($foundSilver)) {
                $winnersSilverFile = __DIR__ . '/../data/winners_silver.json';
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