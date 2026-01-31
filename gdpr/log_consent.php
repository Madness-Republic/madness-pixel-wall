<?php
/**
 * Madness GDPR - Consent Logger (Proof of Consent)
 * 
 * Receives consent data from consent_manager.js and logs it to a CSV file.
 * IP addresses are anonymized.
 */

header('Content-Type: application/json');

// 1. Basic Security Checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 2. Get Data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// 3. Validation
$consent_id = $input['consent_id'] ?? 'unknown';
$preferences = $input['preferences'] ?? [];
$timestamp = date('Y-m-d H:i:s');
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// 4. IP Anonymization (GDPR Requirement)
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    // Mask last octet for IPv4 (e.g., 192.168.1.123 -> 192.168.1.0)
    $ip_parts = explode('.', $ip);
    array_pop($ip_parts);
    $ip_parts[] = '0';
    $anonymized_ip = implode('.', $ip_parts);
} elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    // Keep first 3 blocks for IPv6
    $ip_parts = explode(':', $ip);
    $anonymized_ip = implode(':', array_slice($ip_parts, 0, 3)) . '::';
} else {
    $anonymized_ip = '0.0.0.0';
}

// 5. Format Log Entry
// Columns: Date, ConsentID, IP (Anon), Categories (JSON), UserAgent
$log_entry = [
    $timestamp,
    $consent_id,
    $anonymized_ip,
    json_encode($preferences),
    // Simple sanitization for UA to avoid CSV breaking
    str_replace([",", "\n", "\r"], "", substr($user_agent, 0, 100))
];

// 6. Write to File
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
    // Add .htaccess to protect logs
    file_put_contents($log_dir . '/.htaccess', "Order Deny,Allow\nDeny from all");
}

$log_file = $log_dir . '/consent_' . date('Y-m-d') . '.csv';
$is_new = !file_exists($log_file);

$fp = fopen($log_file, 'a');
if ($fp) {
    if ($is_new) {
        fputcsv($fp, ['Timestamp', 'ConsentID', 'IP_Anon', 'Preferences', 'UserAgent']);
    }
    fputcsv($fp, $log_entry);
    fclose($fp);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Could not write log']);
}
?>