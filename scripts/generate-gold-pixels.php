<?php
// generate-gold-pixels.php
// RUN FROM COMMAND LINE ONLY to initialize the secret winning pixels.

$presetFile = __DIR__ . '/../data/wall_preset.json';

// Generate Random Suffix for filenames (Distinct for Gold and Silver security)
$suffixGold = substr(bin2hex(random_bytes(3)), 0, 6);
$suffixSilver = substr(bin2hex(random_bytes(3)), 0, 6);

$goldFilename = "secret_gold_pixels_{$suffixGold}.php";
$silverFilename = "secret_silver_pixels_{$suffixSilver}.php";

$outputFile = __DIR__ . '/../private/' . $goldFilename;
$outputFileSilver = __DIR__ . '/../private/' . $silverFilename;

$width = 1000;
$height = 400;
$numWinners = 10;

// 1. Load Preset Pixels (Forbidden)
$forbidden = [];
if (file_exists($presetFile)) {
    $json = file_get_contents($presetFile);
    $data = json_decode($json, true);
    if (is_array($data)) {
        foreach (array_keys($data) as $key) {
            $forbidden[$key] = true;
        }
    }
}

echo "Loaded " . count($forbidden) . " preset pixels.\n";

// 2. Generate Random Winners
$winners = [];
$attempts = 0;

while (count($winners) < $numWinners && $attempts < 100000) {
    $attempts++;
    $x = rand(0, $width - 1);
    $y = rand(0, $height - 1);
    $key = "$x,$y";

    // Check if occupied or already selected
    if (!isset($forbidden[$key]) && !in_array($key, $winners)) {
        $winners[] = $key;
    }
}

if (count($winners) < $numWinners) {
    die("Error: Could not generate enough unique pixels after $attempts attempts.\n");
}

echo "Generated 10 Golden Pixels:\n";
print_r($winners);

// 3. Save Golden Pixels
$contentGold = "<?php\n";
$contentGold .= "// SECRET FILE - DO NOT COMMIT OR EXPOSE\n";
$contentGold .= "if (basename(\$_SERVER['PHP_SELF']) == basename(__FILE__)) { http_response_code(403); die('Forbidden'); }\n\n";
$contentGold .= "return " . var_export($winners, true) . ";\n";

if (file_put_contents($outputFile, $contentGold)) {
    echo "Successfully saved 10 Golden Pixels to $goldFilename\n";
} else {
    echo "Error saving Golden Pixels file.\n";
}

// 4. Generate Silver Pixels (50)
$silverWinners = [];
$numSilver = 50;
// Add golden winners to forbidden list to prevent overlap
foreach ($winners as $gw) {
    $forbidden[$gw] = true;
}

$attempts = 0;
while (count($silverWinners) < $numSilver && $attempts < 200000) {
    $attempts++;
    $x = rand(0, $width - 1);
    $y = rand(0, $height - 1);
    $key = "$x,$y";

    if (!isset($forbidden[$key]) && !in_array($key, $silverWinners)) {
        $silverWinners[] = $key;
    }
}

echo "Generated $numSilver Silver Pixels.\n";

// 5. Save Silver Pixels
$contentSilver = "<?php\n";
$contentSilver .= "// SECRET FILE - DO NOT COMMIT OR EXPOSE\n";
$contentSilver .= "if (basename(\$_SERVER['PHP_SELF']) == basename(__FILE__)) { http_response_code(403); die('Forbidden'); }\n\n";
$contentSilver .= "return " . var_export($silverWinners, true) . ";\n";

if (file_put_contents($outputFileSilver, $contentSilver)) {
    echo "Successfully saved 50 Silver Pixels to $silverFilename\n";
} else {
    echo "Error saving Silver Pixels file.\n";
}

// 6. UPDATE wall-api.php REFERENCES
$apiFile = __DIR__ . '/../wall-api.php';
if (file_exists($apiFile)) {
    $apiContent = file_get_contents($apiFile);

    // Regex to replace old filenames with new ones
    // Regex to replace old filenames with new ones (handling optional leading slash)
    $apiContent = preg_replace(
        "/'\/?private\/secret_gold_pixels_.*?\.php'/",
        "'/private/$goldFilename'",
        $apiContent
    );

    $apiContent = preg_replace(
        "/'\/?private\/secret_silver_pixels_.*?\.php'/",
        "'/private/$silverFilename'",
        $apiContent
    );

    if (file_put_contents($apiFile, $apiContent)) {
        echo "✅ Updated wall-api.php with new secret filenames.\n";
    } else {
        echo "❌ Failed to update wall-api.php.\n";
    }
} else {
    echo "❌ wall-api.php not found.\n";
}
