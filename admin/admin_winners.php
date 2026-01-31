<?php
session_start();
require_once '../includes/security_headers.php';
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
// admin_winners.php
// Semplice dashboard protetta da password (opzionale) per vedere i vincitori

$winnersFile = __DIR__ . '/../data/winners.json';
$winners = [];
if (file_exists($winnersFile)) {
    $winners = json_decode(file_get_contents($winnersFile), true) ?: [];
}
// Assume silver winners might be added later or not present here, but good practice to update if I missed it? 
// The file only showed $winnersFile content in Step 84 update. 
// Step 75 showed the full file. It does NOT have silver winners logic visible in lines 1-11 shown. 
// Wait, Step 75 showed lines 1-98.
// Lines 5-9:
// $winnersFile = __DIR__ . '/winners.json';
// ...
// There is NO silver winners in admin_winners_z5p0q2.php in the view I saw.
// So I only update winnersFile.

?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Vincitori - Pixel Wall</title>
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

        .gold {
            color: #FFD700;
            font-weight: bold;
        }

        .silver {
            color: #C0C0C0;
            font-weight: bold;
        }

        .coord-badge {
            background: #0d1117;
            padding: 4px 8px;
            border-radius: 4px;
            font-family: monospace;
            color: var(--accent);
            border: 1px solid var(--border);
        }
    </style>
</head>

<body>
    <div class="admin-main-wrapper">
        <header class="content-header">
            <h1 data-i18n="winners">Vincitori</h1>
        </header>

        <h2 style="margin-top: 0; font-size: 1.2rem; color: var(--accent);">🏆 <span data-i18n="win_gold_title"
                data-i18n-html="true">Vincitori Golden Pixel</span></h2>
        <p style="margin-bottom: 20px;"><span data-i18n="total_found">Totale trovati</span>: <strong>
                <?php echo count($winners); ?> / 10
            </strong></p>

        <table class="winners-table">
            <thead>
                <tr>
                    <th data-i18n="date_label">Data</th>
                    <th data-i18n="coord">Coordinata</th>
                    <th data-i18n="email">Email</th>
                    <th data-i18n="trans_id">ID Transazione</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($winners)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; padding: 40px; color: var(--text-dim);"
                            data-i18n="none_found">Nessun vincitore ancora trovato.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach (array_reverse($winners) as $w): ?>
                        <tr>
                            <td style="color: var(--text-dim)"><?php echo date('d/m/Y H:i', strtotime($w['date'])); ?></td>
                            <td><span class="coord-badge gold"><?php echo htmlspecialchars($w['pixel']); ?></span></td>
                            <td><?php echo htmlspecialchars($w['email']); ?></td>
                            <td><small
                                    style="opacity: 0.6; font-family: monospace;"><?php echo htmlspecialchars($w['txnId']); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php
        $silverFile = __DIR__ . '/../data/winners_silver.json';
        $silverWinners = [];
        if (file_exists($silverFile)) {
            $silverWinners = json_decode(file_get_contents($silverFile), true) ?: [];
        }
        ?>

        <h2 style="margin-top: 40px; font-size: 1.2rem; color: var(--accent);">🥈 <span data-i18n="win_silver_title"
                data-i18n-html="true">Vincitori Silver Pixel</span></h2>
        <p style="margin-bottom: 20px;"><span data-i18n="total_found">Totale trovati</span>:
            <strong><?php echo count($silverWinners); ?> / 50</strong>
        </p>

        <table class="winners-table">
            <thead>
                <tr>
                    <th data-i18n="date_label">Data</th>
                    <th data-i18n="coord">Coordinata</th>
                    <th data-i18n="email">Email</th>
                    <th data-i18n="trans_id">ID Transazione</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($silverWinners)): ?>
                    <tr>
                        <td colspan="4" style="text-align:center; padding: 40px; color: var(--text-dim);"
                            data-i18n="none_found">Nessun vincitore ancora trovato.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach (array_reverse($silverWinners) as $w): ?>
                        <tr>
                            <td style="color: var(--text-dim)"><?php echo date('d/m/Y H:i', strtotime($w['date'])); ?></td>
                            <td><span class="coord-badge silver"><?php echo htmlspecialchars($w['pixel']); ?></span></td>
                            <td><?php echo htmlspecialchars($w['email']); ?></td>
                            <td><small
                                    style="opacity: 0.6; font-family: monospace;"><?php echo htmlspecialchars($w['txnId']); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <script src="admin-lang.js?v=2.3.0"></script>
        <script nonce="<?php echo $nonce; ?>">document.addEventListener('DOMContentLoaded', updateInterface);</script>
    </div>
</body>

</html>