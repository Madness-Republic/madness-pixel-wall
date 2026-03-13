<?php
/**
 * admin_visitors.php - Super Diagnostic Version
 */

if (session_status() === PHP_SESSION_NONE)
    session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)
    die("Accesso Negato.");

$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$paths = [$root . '/analytics/', $root . '/website/analytics/', __DIR__ . '/../../analytics/', __DIR__ . '/../../website/analytics/'];
$logDir = '';
foreach ($paths as $p) {
    if (file_exists($p . 'access_logs.json')) {
        $logDir = $p;
        break;
    }
}
$logFile = $logDir ? $logDir . 'access_logs.json' : '';
$cacheFile = $logDir ? $logDir . 'ip_cache.json' : '';

function getIPDetails($ip)
{
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0)
        return ['isp' => 'Localhost', 'org' => 'Local'];
    $res = @json_decode(file_get_contents("http://ip-api.com/json/$ip?fields=status,isp,org,country,city"), true);
    return ($res && $res['status'] === 'success') ? $res : ['isp' => 'N/A', 'org' => 'API Limit', 'country' => 'Unknown', 'city' => ''];
}

$ipCache = (file_exists($cacheFile)) ? json_decode(file_get_contents($cacheFile), true) : [];
$uniqueVisitors = [];
if ($logFile && file_exists($logFile)) {
    $logs = json_decode(file_get_contents($logFile), true);
    if (is_array($logs)) {
        foreach ($logs as $entry) {
            if (($entry['page'] ?? '') !== 'Pixel Wall')
                continue;
            $ip = $entry['ip'] ?? 'Unknown';
            if (!isset($uniqueVisitors[$ip])) {
                $uniqueVisitors[$ip] = ['ip' => $ip, 'os' => $entry['os'] ?? '', 'browser' => $entry['browser'] ?? '', 'last_visit' => $entry['timestamp'], 'referrer' => $entry['referrer'] ?? '', 'events' => [], 'country' => $entry['country'] ?? 'Unknown', 'city' => $entry['city'] ?? ''];
            }
            $e = $entry['event'] ?? '';
            if ($e && !in_array($e, $uniqueVisitors[$ip]['events']))
                $uniqueVisitors[$ip]['events'][] = $e;
            if (strtotime($entry['timestamp']) > strtotime($uniqueVisitors[$ip]['last_visit']))
                $uniqueVisitors[$ip]['last_visit'] = $entry['timestamp'];
        }
    }
}
// Sort & Geolocation
uasort($uniqueVisitors, function ($a, $b) {
    return strtotime($b['last_visit']) - strtotime($a['last_visit']);
});
$apiCount = 0;
foreach ($uniqueVisitors as $ip => &$v) {
    if (!isset($ipCache[$ip]) && $apiCount < 20) {
        $details = getIPDetails($ip);
        $v['isp'] = $details['isp'];
        $v['org'] = $details['org'];
        $v['country'] = $details['country'];
        $v['city'] = $details['city'];
        $ipCache[$ip] = $v;
        $apiCount++;
    } else {
        $v['isp'] = $ipCache[$ip]['isp'] ?? 'Pending...';
        $v['org'] = $ipCache[$ip]['org'] ?? '';
        $v['country'] = $ipCache[$ip]['country'] ?? $v['country'];
        $v['city'] = $ipCache[$ip]['city'] ?? $v['city'];
    }
}
@file_put_contents($cacheFile, json_encode($ipCache));
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: sans-serif;
            background: #0b0e14;
            color: #fff;
            padding: 20px;
        }

        .card {
            background: #161b22;
            border-radius: 8px;
            border: 1px solid #30363d;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #30363d;
            text-align: left;
        }

        th {
            background: rgba(255, 255, 255, 0.05);
            color: #8b949e;
        }

        .ip {
            color: #1de9b6;
            font-family: monospace;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            margin: 2px;
            background: #30363d;
        }

        .badge-event {
            background: rgba(233, 30, 99, 0.2);
            color: #ff80ab;
            border: 1px solid #ff1e6344;
        }

        .dim {
            color: #8b949e;
            font-size: 0.75rem;
        }

        .debug-info {
            margin-top: 10px;
            font-size: 0.7rem;
            color: #58a6ff;
        }
    </style>
</head>

<body>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>IP / Località</th>
                    <th>Azioni Pixel Wall</th>
                    <th>Provider</th>
                    <th>Ora</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($uniqueVisitors as $v): ?>
                    <tr>
                        <td>
                            <div class="ip"><?= $v['ip'] ?></div>
                            <div class="dim"><?= $v['country'] ?> (<?= $v['city'] ?>)</div>
                        </td>
                        <td>
                            <?php
                            foreach ($v['events'] as $e)
                                echo '<span class="badge badge-event">⚡ ' . $e . '</span>';
                            if (empty($v['events']))
                                echo '<span class="badge">Visita (Pagina Caricata)</span>';
                            ?>
                        </td>
                        <td class="dim"><?= $v['isp'] ?></td>
                        <td class="dim"><?= date('d/m H:i:s', strtotime($v['last_visit'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="debug-info">Log File: <?= $logFile ?></div>
</body>

</html>