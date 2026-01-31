<?php
session_start();
require_once '../includes/security_headers.php';

// CONFIGURATION
require_once __DIR__ . '/../includes/env_loader.php';
$ADMIN_PASSWORD = env('ADMIN_PASSWORD', 'madness_republic_secret');

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Require Login - Now simplified to share the main dashboard session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('<div style="color: #ff4d4d; font-family: sans-serif; padding: 20px; background: #111; height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center;">
            <div>
                <h2 data-i18n="password_err">Sessione Scaduta o Non Autorizzato</h2>
                <a href="index.php" target="_parent" style="color: #1de9b6; text-decoration: none; border: 1px solid #1de9b6; padding: 5px 15px; border-radius: 4px; display: inline-block; margin-top: 10px;">Login</a>
            </div>
         </div>
          <script src="admin-lang.js?v=2.3.0"></script>
         <script>document.addEventListener("DOMContentLoaded", updateInterface);</script>');
}

// --- AUTHENTICATED AREA ---

$contributors_file = __DIR__ . '/../data/contributors.json';
$transactions_file = __DIR__ . '/../data/transactions.json';
$owners_file = __DIR__ . '/../data/pixel_owners.json';

$contributors = file_exists($contributors_file) ? json_decode(file_get_contents($contributors_file), true) : [];
$transactions = file_exists($transactions_file) ? json_decode(file_get_contents($transactions_file), true) : [];
$owners = file_exists($owners_file) ? json_decode(file_get_contents($owners_file), true) : [];

// Map contributors by txnId for easier lookup if needed, though they are usually in order
$contributor_map = [];
if (is_array($contributors)) {
    foreach ($contributors as $c) {
        if (isset($c['id'])) {
            $contributor_map[$c['id']] = $c;
        }
    }
}

// Map transactions by ID
$txn_map = [];
if (is_array($transactions)) {
    foreach ($transactions as $t) {
        if (isset($t['id'])) {
            $txn_map[$t['id']] = $t;
        }
    }
}

// Build merged list
$merged_data = [];
if (is_array($owners)) {
    foreach ($owners as $email => $data) {
        if (isset($data['txns'])) {
            foreach ($data['txns'] as $txnId) {
                $merged_data[] = [
                    'email' => $email,
                    'txnId' => $txnId,
                    'name' => isset($contributor_map[$txnId]) ? $contributor_map[$txnId]['name'] : 'N/A',
                    'amount' => isset($txn_map[$txnId]) ? $txn_map[$txnId]['amount'] : (isset($contributor_map[$txnId]) ? $contributor_map[$txnId]['amount'] : 0),
                    'date' => isset($txn_map[$txnId]) ? $txn_map[$txnId]['date'] : 'N/A',
                    'pixels' => count($data['pixels'] ?? [])
                ];
            }
        }
    }
}

// Sort by date descending
usort($merged_data, function ($a, $b) {
    return strcmp($b['date'], $a['date']);
});

$total_amount = 0;
foreach ($merged_data as $item) {
    $total_amount += $item['amount'];
}

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pixel_wall_contributions_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');

    // Header
    fputcsv($output, ['Data', 'Nome', 'Email', 'Importo (EUR)', 'Pixel', 'Transaction ID']);

    // Data
    foreach ($merged_data as $row) {
        fputcsv($output, [
            date('d/m/Y H:i', strtotime($row['date'])),
            $row['name'],
            $row['email'],
            number_format($row['amount'], 2, '.', ''),
            $row['pixels'],
            $row['txnId']
        ]);
    }

    fclose($output);
    exit;
}

?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contributions Admin - Pixel Wall</title>
    <link rel="stylesheet" href="admin-style.css?v=2.3.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            padding: 0 !important;
            background: #0b0e14;
            overflow-y: auto !important;
            height: auto !important;
        }

        .admin-main-wrapper {
            max-width: 100%;
        }

        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .stat-val {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-dim);
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .winners-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }

        .winners-table th,
        .winners-table td {
            padding: 15px;
            border: 1px solid var(--border);
            text-align: left;
        }

        .winners-table th {
            background: var(--bg-hover);
            color: var(--accent);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }

        .winners-table tr:hover {
            background: rgba(29, 233, 182, 0.05);
        }

        .txn-id {
            font-family: monospace;
            font-size: 0.8rem;
            opacity: 0.6;
        }
    </style>
</head>

<body>
    <div class="admin-main-wrapper">
        <header class="content-header" style="position: relative;">
            <h1 data-i18n="contributions">Contributi & Vendite</h1>
            <a href="?export=csv" class="btn btn-outline"
                style="font-size: 0.8rem; position: absolute; right: 0; top: 0;">
                <i class="fa-solid fa-file-csv"></i> Esporta CSV
            </a>
        </header>

        <div class="stats-grid"
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="stat-card">
                <div class="stat-label">Totale Incassato</div>
                <div class="stat-val">€<?php echo number_format($total_amount, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Transazioni Totali</div>
                <div class="stat-val"><?php echo count($merged_data); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pixel Venduti</div>
                <div class="stat-val"><?php
                $total_p = 0;
                foreach ($merged_data as $m)
                    $total_p += $m['pixels'];
                echo $total_p;
                ?></div>
            </div>
        </div>

        <table class="winners-table">
            <thead>
                <tr>
                    <th data-i18n="date_label">Data</th>
                    <th data-i18n="supporter">Nome</th>
                    <th data-i18n="email">Email</th>
                    <th data-i18n="amount_eur">Importo</th>
                    <th data-i18n="pixels">Pixel</th>
                    <th data-i18n="trans_id">ID Transazione</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($merged_data)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 40px; color: var(--text-dim);"
                            data-i18n="no_contrib">Nessun contributo trovato.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($merged_data as $row): ?>
                        <tr>
                            <td style="color: var(--text-dim)"><?php echo date('d/m/Y H:i', strtotime($row['date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td style="color: #ffcc4d; font-weight: bold;">€<?php echo number_format($row['amount'], 2); ?></td>
                            <td><?php echo $row['pixels']; ?> px</td>
                            <td class="txn-id"><?php echo htmlspecialchars($row['txnId']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <script src="admin-lang.js?v=2.3.0"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof updateInterface === 'function') updateInterface();
            });
        </script>
    </div>
</body>

</html>