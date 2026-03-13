<?php
// SECURITY: Hardening Session Cookie
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => $cookieParams['lifetime'],
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => true, // Enforce HTTPS
    'httponly' => true, // Prevent JS access
    'samesite' => 'Strict' // Prevent CSRF
]);
session_start();

// SECURITY: Only allowed if logged in via index.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Non autorizzato']);
    exit;
}

// SECURITY: CSRF Check for state-changing requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = apache_request_headers();
    $token = $headers['X-CSRF-Token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF Token Mismatch']);
        exit;
    }
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$settings_file = __DIR__ . '/../data/settings.json';
$branding_file = __DIR__ . '/../data/custom_branding.json';

// Helper for Atomic updates
function atomicUpdate($filePath, $callback)
{
    if (!file_exists($filePath)) {
        // Attempt to create empty if it doesn't exist
        file_put_contents($filePath, '{}');
    }

    $fp = fopen($filePath, 'c+');
    if (!$fp)
        return false;

    if (flock($fp, LOCK_EX)) {
        $filesize = filesize($filePath);
        $content = $filesize > 0 ? fread($fp, $filesize) : null;
        $data = json_decode($content, true) ?: [];

        $newData = $callback($data);

        if ($newData !== null) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($newData, JSON_PRETTY_PRINT));
        }
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }
    fclose($fp);
    return false;
}

// --- ACTIONS ---

if ($action === 'get_settings') {
    if (file_exists($settings_file)) {
        readfile($settings_file);
    } else {
        echo json_encode(['error' => 'Settings file not found']);
    }
    exit;
}

if ($action === 'save_settings') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['error' => 'Dati non validi']);
        exit;
    }

    $success = atomicUpdate($settings_file, function ($current) use ($input) {
        // Merge with current to preserve fields not modified by UI
        // Note: Tracking IDs (tracking.ga4_id, tracking.google_ads_id) are handled automatically here
        return array_merge_recursive_distinct($current, $input);
    });

    echo json_encode(['success' => $success]);
    exit;
}

if ($action === 'get_branding') {
    if (file_exists($branding_file)) {
        readfile($branding_file);
    } else {
        echo json_encode(['error' => 'Branding file not found']);
    }
    exit;
}

if ($action === 'save_branding') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['error' => 'Dati non validi']);
        exit;
    }

    $success = atomicUpdate($branding_file, function ($current) use ($input) {
        return array_merge_recursive_distinct($current, $input);
    });

    echo json_encode(['success' => $success]);
    exit;
}

if ($action === 'system_backup') {
    $backupDir = __DIR__ . '/../data/backups/';
    if (!is_dir($backupDir))
        mkdir($backupDir, 0777, true);

    $timestamp = date('Ymd_His');
    $backupFile = $backupDir . "manual_backup_$timestamp.zip";

    $zip = new ZipArchive();
    if ($zip->open($backupFile, ZipArchive::CREATE) === TRUE) {
        $dataFiles = ['wall_data.json', 'transactions.json', 'contributors.json', 'winners.json', 'winners_silver.json', 'pixel_owners.json', 'settings.json', 'custom_branding.json'];
        foreach ($dataFiles as $f) {
            $path = __DIR__ . '/../data/' . $f;
            if (file_exists($path))
                $zip->addFile($path, $f);
        }
        $zip->close();

        // Redirect to download the file directly
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=' . basename($backupFile));
        header('Content-Length: ' . filesize($backupFile));
        readfile($backupFile);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create zip']);
        exit;
    }
}

if ($action === 'system_reset') {
    // 1. Create Backup
    $backupDir = __DIR__ . '/../data/backups/';
    if (!is_dir($backupDir))
        mkdir($backupDir, 0777, true);

    $timestamp = date('Ymd_His');
    $backupFile = $backupDir . "backup_$timestamp.zip";

    $zip = new ZipArchive();
    if ($zip->open($backupFile, ZipArchive::CREATE) === TRUE) {
        $dataFiles = ['wall_data.json', 'transactions.json', 'contributors.json', 'winners.json', 'winners_silver.json', 'pixel_owners.json'];
        foreach ($dataFiles as $f) {
            $path = __DIR__ . '/../data/' . $f;
            if (file_exists($path))
                $zip->addFile($path, $f);
        }
        $zip->close();
    }

    // 2. Clear Files
    $toClear = ['wall_data.json', 'transactions.json', 'contributors.json', 'winners.json', 'winners_silver.json', 'pixel_owners.json'];
    foreach ($toClear as $f) {
        $path = __DIR__ . '/../data/' . $f;
        if (file_exists($path)) {
            // Write empty array to keep valid JSON
            file_put_contents($path, ($f === 'wall_data.json' || $f === 'pixel_owners.json') ? '{}' : '[]');
        }
    }

    echo json_encode(['success' => true, 'backup' => basename($backupFile)]);
    exit;
}

if ($action === 'regenerate_gamification') {
    $input = json_decode(file_get_contents('php://input'), true);
    $goldCount = (int) ($input['gold_count'] ?? 10);
    $silverCount = (int) ($input['silver_count'] ?? 50);

    // Get current wall dimensions from settings
    $settings = json_decode(file_get_contents($settings_file), true);
    $w = $settings['wall']['width'] ?? 1000;
    $h = $settings['wall']['height'] ?? 400;

    // Generate Gold
    $gold = [];
    while (count($gold) < $goldCount) {
        $x = rand(0, $w - 1);
        $y = rand(0, $h - 1);
        $key = "$x,$y";
        if (!in_array($key, $gold))
            $gold[] = $key;
    }

    // Generate Silver
    $silver = [];
    while (count($silver) < $silverCount) {
        $x = rand(0, $w - 1);
        $y = rand(0, $h - 1);
        $key = "$x,$y";
        if (!in_array($key, $gold) && !in_array($key, $silver))
            $silver[] = $key;
    }

    // Save to private files
    $goldExport = "<?php\nreturn " . var_export($gold, true) . ";\n";
    $silverExport = "<?php\nreturn " . var_export($silver, true) . ";\n";

    // Use a fixed name or a name that matches current versioning strategy
    // To be safe, we should update the secret file names if we want random ones, 
    // but the app currently expects specific files.
    // Let's identify the current file names.
    // From wall-api.php: secret_gold_pixels_86b5a7.php and secret_silver_pixels_187800.php

    file_put_contents(__DIR__ . '/../private/secret_gold_pixels_86b5a7.php', $goldExport);
    file_put_contents(__DIR__ . '/../private/secret_silver_pixels_187800.php', $silverExport);

    // Save counts to settings
    atomicUpdate($settings_file, function ($current) use ($goldCount, $silverCount) {
        $current['gamification']['gold_count'] = $goldCount;
        $current['gamification']['silver_count'] = $silverCount;
        return $current;
    });

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'get_secret_pixels') {
    // Dynamically find the secret files (since names are randomized)
    $goldFiles = glob(__DIR__ . '/../private/secret_gold_pixels_*.php');
    $silverFiles = glob(__DIR__ . '/../private/secret_silver_pixels_*.php');

    $gold = (!empty($goldFiles) && file_exists($goldFiles[0])) ? include($goldFiles[0]) : [];
    $silver = (!empty($silverFiles) && file_exists($silverFiles[0])) ? include($silverFiles[0]) : [];

    // Ensure we send arrays
    if (!is_array($gold))
        $gold = [];
    if (!is_array($silver))
        $silver = [];

    echo json_encode([
        'gold' => $gold,
        'silver' => $silver,
        'gold_file' => basename($goldFiles[0] ?? 'N/A'),
        'silver_file' => basename($silverFiles[0] ?? 'N/A')
    ]);
    exit;
}

if ($action === 'capture_preset') {
    $wallDataPath = __DIR__ . '/../data/wall_data.json';
    $presetPath = __DIR__ . '/../data/wall_preset.json';

    if (file_exists($wallDataPath)) {
        if (copy($wallDataPath, $presetPath)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to copy wall data']);
        }
    } else {
        // If no wall data, just create empty preset
        file_put_contents($presetPath, '{}');
        echo json_encode(['success' => true, 'notice' => 'No wall data found, creating empty preset']);
    }
    exit;
}

if ($action === 'clear_preset') {
    $presetPath = __DIR__ . '/../data/wall_preset.json';
    if (file_put_contents($presetPath, '{}')) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to clear preset']);
    }
    exit;
}

if ($action === 'load_preset') {
    $wallDataPath = __DIR__ . '/../data/wall_data.json';
    $presetPath = __DIR__ . '/../data/wall_preset.json';

    if (file_exists($presetPath)) {
        if (copy($presetPath, $wallDataPath)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load preset']);
        }
    } else {
        echo json_encode(['error' => 'No preset found']);
    }
    exit;
}

if ($action === 'get_stripe_config') {
    $configFile = __DIR__ . '/../private/stripe_config.php';
    $config = file_exists($configFile) ? include($configFile) : [];

    // Mask secrets for frontend display
    $response = [
        'publishable_key' => $config['publishable_key'] ?? '',
        'secret_key' => !empty($config['secret_key']) ? (substr($config['secret_key'], 0, 8) . '...' . substr($config['secret_key'], -4)) : '',
        'webhook_secret' => !empty($config['webhook_secret']) ? (substr($config['webhook_secret'], 0, 8) . '...' . substr($config['webhook_secret'], -4)) : '',
        'is_configured' => !empty($config['secret_key'])
    ];

    echo json_encode($response);
    exit;
}

if ($action === 'save_stripe_config') {
    $input = json_decode(file_get_contents('php://input'), true);
    $configFile = __DIR__ . '/../private/stripe_config.php';

    // Load existing to preserve if not changed (masked)
    $current = file_exists($configFile) ? include($configFile) : [];
    if (!is_array($current))
        $current = [];

    $newConfig = $current;

    // Helper to check if value is a masked string
    $isMasked = function ($val) {
        return strpos($val, '...') !== false;
    };

    // Update Publishable Key (safe to overwrite usually, but let's be consistent)
    if (isset($input['publishable_key'])) {
        $newConfig['publishable_key'] = trim($input['publishable_key']);
    }

    // Update Secret Key ONLY if it's not the masked version and not empty
    if (!empty($input['secret_key']) && !$isMasked($input['secret_key'])) {
        $newConfig['secret_key'] = trim($input['secret_key']);
    }

    // Update Webhook Secret ONLY if not masked
    if (!empty($input['webhook_secret']) && !$isMasked($input['webhook_secret'])) {
        $newConfig['webhook_secret'] = trim($input['webhook_secret']);
    }

    // Save to PHP file
    $content = "<?php\n";
    $content .= "// SECURE STRIPE CONFIGURATION\n";
    $content .= "if (basename(\$_SERVER['PHP_SELF']) == basename(__FILE__)) { http_response_code(403); die('Forbidden'); }\n\n";
    $content .= "return " . var_export($newConfig, true) . ";\n";

    if (file_put_contents($configFile, $content)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to write config file']);
    }
    exit;
}

// Deep merging for configuration
function array_merge_recursive_distinct(array &$array1, array &$array2)
{
    $merged = $array1;
    foreach ($array2 as $key => &$value) {
        if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
            $merged[$key] = array_merge_recursive_distinct($merged[$key], $value);
        } else {
            $merged[$key] = $value;
        }
    }
    return $merged;
}
