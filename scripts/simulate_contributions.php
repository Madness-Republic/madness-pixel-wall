<?php
// Final script with Collision Control (Preset + Padding + Secret Pixels)
$dataDir = __DIR__ . '/../data/';
$privateDir = __DIR__ . '/../private/';

// 0. Load Restrictions
$settings = json_decode(file_get_contents($dataDir . 'settings.json'), true);
$padding = $settings['wall']['preset_padding'] ?? 4;
$width = $settings['wall']['width'] ?? 1000;
$height = $settings['wall']['height'] ?? 400;

$restricted = []; // Map of "x,y" => true

// A. Preset + Padding
if (file_exists($dataDir . 'wall_preset.json')) {
    $preset = json_decode(file_get_contents($dataDir . 'wall_preset.json'), true) ?: [];
    foreach ($preset as $coord => $color) {
        list($px, $py) = explode(',', $coord);
        // Mark pixel and its padding area
        for ($dx = -$padding; $dx <= $padding; $dx++) {
            for ($dy = -$padding; $dy <= $padding; $dy++) {
                $restricted[($px + $dx) . "," . ($py + $dy)] = true;
            }
        }
    }
}

// B. Secret Pixels (Gamification)
$gold = include($privateDir . 'secret_gold_pixels_86b5a7.php');
$silver = include($privateDir . 'secret_silver_pixels_187800.php');
foreach (array_merge($gold ?: [], $silver ?: []) as $coord) {
    $restricted[$coord] = true;
}

// 1. Define Contributors (Batch of 20)
$names = [
    "Mario Rossi",
    "Luca Bianchi",
    "Giulia Russo",
    "Anna Esposito",
    "Paolo Ferrari",
    "Marco Romano",
    "Elena Martini",
    "Giacomo Ricci",
    "Sofia Marino",
    "Davide Conti",
    "John Smith",
    "Pierre Dubois",
    "Hans Müller",
    "Emma Wilson",
    "Yuki Tanaka",
    "Elena Ivanova",
    "Carlos Garcia",
    "Maria Silva",
    "张伟",
    "अमित"
];

// 2. Generate Randomized Amounts totaling > 2500€
$totalGoal = 2500 + (rand(10, 10000) / 100);
$amounts = [];
$tempRemaining = $totalGoal;
for ($i = 0; $i < 19; $i++) {
    $amount = round(rand(80, 180) + (rand(0, 99) / 100), 2);
    $amounts[] = $amount;
    $tempRemaining -= $amount;
}
$amounts[] = round($tempRemaining, 2);

// Pattern Library
$lib = [
    'EIFFEL' => [[4, 0], [3, 1], [5, 1], [3, 2], [5, 2], [3, 3], [5, 3], [2, 4], [6, 4], [4, 4], [1, 5], [7, 5], [0, 6], [1, 6], [2, 6], [3, 6], [4, 6], [5, 6], [6, 6], [7, 6], [8, 6]],
    'HEART' => [[1, 0], [2, 0], [4, 0], [5, 0], [0, 1], [1, 1], [2, 1], [3, 1], [4, 1], [5, 1], [6, 1], [0, 2], [1, 2], [2, 2], [3, 2], [4, 2], [5, 2], [6, 2], [1, 3], [2, 3], [3, 3], [4, 3], [5, 3], [2, 4], [3, 4], [4, 4], [3, 5]],
    'CIAO' => [[0, 1], [0, 2], [0, 3], [1, 0], [2, 0], [1, 4], [2, 4], [4, 0], [4, 1], [4, 2], [4, 3], [4, 4], [6, 1], [6, 2], [6, 3], [6, 4], [7, 0], [8, 0], [9, 1], [9, 2], [9, 3], [9, 4], [7, 2], [8, 2], [11, 1], [11, 2], [11, 3], [12, 0], [13, 0], [14, 1], [14, 2], [14, 3], [12, 4], [13, 4]],
    'HI' => [[0, 0], [0, 1], [0, 2], [0, 3], [0, 4], [1, 2], [2, 0], [2, 1], [2, 2], [2, 3], [2, 4], [4, 0], [4, 1], [4, 2], [4, 3], [4, 4]],
    'EMMA' => [[0, 0], [0, 1], [0, 2], [0, 3], [0, 4], [1, 0], [2, 0], [1, 2], [2, 2], [1, 4], [2, 4], [4, 0], [4, 1], [4, 2], [4, 3], [4, 4], [5, 1], [6, 2], [7, 1], [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [10, 0], [10, 1], [10, 2], [10, 3], [10, 4], [11, 1], [12, 2], [13, 1], [14, 0], [14, 1], [14, 2], [14, 3], [14, 4], [16, 1], [16, 2], [16, 3], [16, 4], [17, 0], [18, 0], [19, 1], [19, 2], [19, 3], [19, 4], [17, 2], [18, 2]],
    'LOVE' => [[0, 0], [0, 1], [0, 2], [0, 3], [0, 4], [1, 4], [2, 4], [4, 1], [4, 2], [4, 3], [5, 0], [6, 0], [7, 1], [7, 2], [7, 3], [5, 4], [6, 4], [9, 0], [9, 1], [9, 2], [10, 3], [11, 3], [12, 0], [12, 1], [12, 2], [14, 0], [14, 1], [14, 2], [14, 3], [14, 4], [15, 0], [16, 0], [15, 2], [16, 2], [15, 4], [16, 4]],
    'SMILE' => [[1, 0], [2, 0], [3, 0], [0, 1], [4, 1], [0, 2], [4, 2], [0, 3], [4, 3], [1, 4], [2, 4], [3, 4], [1, 1], [3, 1], [1, 3], [2, 3], [3, 3]],
    'STAR' => [[2, 0], [1, 1], [2, 1], [3, 1], [0, 2], [1, 2], [2, 2], [3, 2], [4, 2], [1, 3], [3, 3], [1, 4], [3, 4]],
    'SUN' => [[0, 0], [1, 0], [2, 0], [0, 1], [1, 1], [2, 1], [0, 2], [1, 2], [2, 2], [1, -2], [1, 4], [-2, 1], [4, 1], [-1, -1], [3, 3], [-1, 3], [3, -1]],
    'OK' => [[0, 1], [0, 2], [0, 3], [1, 0], [2, 0], [3, 1], [3, 2], [3, 3], [1, 4], [2, 4], [5, 0], [5, 1], [5, 2], [5, 3], [5, 4], [6, 2], [7, 1], [7, 3], [8, 0], [8, 4]],
    'HOUSE' => [[2, 0], [1, 1], [3, 1], [0, 2], [4, 2], [0, 3], [4, 3], [1, 3], [2, 3], [3, 3], [1, 4], [2, 4], [3, 4]],
    'YES' => [[0, 0], [0, 1], [2, 0], [2, 1], [1, 2], [1, 3], [1, 4], [4, 0], [4, 1], [4, 2], [4, 3], [4, 4], [5, 0], [6, 0], [5, 2], [6, 2], [5, 4], [6, 4], [8, 0], [8, 1], [8, 2], [9, 0], [10, 1], [10, 2], [8, 4], [9, 4], [10, 3]]
];

$batchPatterns = [
    ['type' => 'EMMA', 'color' => '#ff00ff'],
    ['type' => 'CIAO', 'color' => '#ff0000'],
    ['type' => 'HI', 'color' => '#00ff00'],
    ['type' => 'HI', 'color' => '#0000ff'],
    ['type' => 'HEART', 'color' => '#ff1493'],
    ['type' => 'HEART', 'color' => '#ff4500'],
    ['type' => 'EIFFEL', 'color' => '#ffffff'],
    ['type' => 'LOVE', 'color' => '#ff0000'],
    ['type' => 'SMILE', 'color' => '#ffff00'],
    ['type' => 'STAR', 'color' => '#ffa500'],
    ['type' => 'SUN', 'color' => '#ffff00'],
    ['type' => 'OK', 'color' => '#00ff00'],
    ['type' => 'HOUSE', 'color' => '#8b4513'],
    ['type' => 'YES', 'color' => '#00ff7f'],
    ['type' => 'STAR', 'color' => '#ffffff'],
    ['type' => 'HEART', 'color' => '#800080'],
    ['type' => 'SMILE', 'color' => '#00ffff'],
    ['type' => 'SPRAY', 'color' => '#ffffff'],
    ['type' => 'SPRAY', 'color' => '#00ff00'],
    ['type' => 'OK', 'color' => '#ff00ff']
];
shuffle($batchPatterns);

$transactions = [];
$contributors = [];
$pixelOwners = [];
$allPixels = [];

foreach ($names as $i => $name) {
    $txnId = "txn_sim_v7_" . bin2hex(random_bytes(8));
    $amount = $amounts[$i];
    $date = date('c', strtotime("-$i hours"));
    $email = strtolower(preg_replace('/[^a-z0-9]/i', '.', $name)) . "@example.com";

    // Try to find a collision-free position
    $myPixels = [];
    $pConfig = $batchPatterns[$i];
    $color = $pConfig['color'];

    $attempts = 0;
    $placed = false;
    while ($attempts < 50 && !$placed) {
        $startX = rand($padding, $width - $padding - 20);
        $startY = rand($padding, $height - $padding - 20);
        $tempPixels = [];
        $collision = false;

        if ($pConfig['type'] === 'SPRAY') {
            $count = rand(15, 25);
            for ($j = 0; $j < $count; $j++) {
                $coord = ($startX + rand(0, 15)) . "," . ($startY + rand(0, 15));
                if (isset($restricted[$coord])) {
                    $collision = true;
                    break;
                }
                $tempPixels[] = $coord;
            }
        } else {
            $pts = $lib[$pConfig['type']];
            foreach ($pts as $p) {
                $coord = ($startX + $p[0]) . "," . ($startY + $p[1]);
                if (isset($restricted[$coord]) || ($startX + $p[0]) < 0 || ($startX + $p[0]) >= $width || ($startY + $p[1]) < 0 || ($startY + $p[1]) >= $height) {
                    $collision = true;
                    break;
                }
                $tempPixels[] = $coord;
            }
        }

        if (!$collision) {
            $myPixels = $tempPixels;
            $placed = true;
        }
        $attempts++;
    }

    if ($placed) {
        $firstName = explode(' ', $name)[0];
        $transactions[] = ['id' => $txnId, 'amount' => $amount, 'email' => $email, 'referral' => '', 'date' => $date];
        $contributors[] = ['id' => $txnId, 'name' => $firstName, 'amount' => $amount];
        foreach ($myPixels as $coord) {
            $allPixels[$coord] = $color;
        }
        if (!isset($pixelOwners[$email]))
            $pixelOwners[$email] = ['pixels' => [], 'txns' => [], 'last_update' => $date];
        $pixelOwners[$email]['pixels'] = array_values(array_unique(array_merge($pixelOwners[$email]['pixels'], $myPixels)));
        $pixelOwners[$email]['txns'][] = $txnId;
    }
}

// 3. Save Files (Append Mode)
$filesToAppend = [
    'transactions.json' => $transactions,
    'contributors.json' => $contributors
];
foreach ($filesToAppend as $f => $data) {
    $existing = [];
    if (file_exists($dataDir . $f))
        $existing = json_decode(file_get_contents($dataDir . $f), true) ?: [];
    file_put_contents($dataDir . $f, json_encode(array_merge($existing, $data), JSON_PRETTY_PRINT));
}

// Special merge for pixel_owners.json
$existingOwners = [];
if (file_exists($dataDir . 'pixel_owners.json'))
    $existingOwners = json_decode(file_get_contents($dataDir . 'pixel_owners.json'), true) ?: [];
foreach ($pixelOwners as $email => $newData) {
    if (isset($existingOwners[$email])) {
        $existingOwners[$email]['pixels'] = array_values(array_unique(array_merge($existingOwners[$email]['pixels'], $newData['pixels'])));
        $existingOwners[$email]['txns'] = array_values(array_unique(array_merge($existingOwners[$email]['txns'], $newData['txns'])));
        $existingOwners[$email]['last_update'] = $newData['last_update'];
    } else {
        $existingOwners[$email] = $newData;
    }
}
file_put_contents($dataDir . 'pixel_owners.json', json_encode($existingOwners, JSON_PRETTY_PRINT));

// Wall Data
$wallData = [];
if (file_exists($dataDir . 'wall_data.json'))
    $wallData = json_decode(file_get_contents($dataDir . 'wall_data.json'), true) ?: [];
$newWallData = array_merge($wallData, $allPixels);
file_put_contents($dataDir . 'wall_data.json', json_encode($newWallData));

echo "Simulated 20 precise contributions with Collision Control.\n";
echo "Avoided: Preset area (padding 4) and Secret Reward pixels.\n";
echo "Batch total: $totalGoal €\n";
?>