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

// SECURITY: CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CONFIGURATION
$envPath = __DIR__ . '/../.env';
$ADMIN_PASSWORD = 'madness_republic_secret'; // Fallback default

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0)
            continue;
        list($name, $value) = explode('=', $line, 2);
        if (trim($name) === 'ADMIN_PASSWORD') {
            $ADMIN_PASSWORD = trim($value);
            break;
        }
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Handle Login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: /admin/index.php");
        exit;
    } else {
        $error = "Password Errata.";
    }
}

// Require Login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="it">

    <head>
        <meta charset="UTF-8">
        <title>Login - Pixel Wall Admin</title>
        <style>
            body {
                background: #0b0e14;
                color: #1de9b6;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }

            form {
                background: #161b22;
                padding: 40px;
                border-radius: 12px;
                border: 1px solid #30363d;
                text-align: center;
                width: 300px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            }

            h2 {
                margin-top: 0;
                font-size: 1.2rem;
                text-transform: uppercase;
                letter-spacing: 2px;
            }

            input {
                padding: 12px;
                border: 1px solid #30363d;
                background: #0d1117;
                color: white;
                margin-bottom: 20px;
                display: block;
                width: 100%;
                box-sizing: border-box;
                border-radius: 6px;
            }

            button {
                padding: 12px;
                background: #1de9b6;
                border: none;
                font-weight: bold;
                cursor: pointer;
                width: 100%;
                border-radius: 6px;
                color: #0b0e14;
                transition: opacity 0.2s;
            }

            button:hover {
                opacity: 0.9;
            }

            .error {
                color: #ff4d4d;
                margin-bottom: 10px;
                font-size: 0.9rem;
            }
        </style>
    </head>

    <body>
        <form method="POST">
            <h2>PIXEL WALL ADMIN</h2>
            <?php if ($error): ?>
                <p class="error">
                    <?php echo $error; ?>
                </p>
            <?php endif; ?>
            <input type="password" name="password" placeholder="Inserisci Password..." required autofocus>
            <button type="submit">ACCEDI</button>
        </form>
    </body>

    </html>
    <?php
    exit;
}

// --- AUTHENTICATED AREA ---
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pixel Wall Admin</title>
    <base href="/admin/">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <link rel="stylesheet" href="admin-style.css?v=2.3.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="logo-text">Pixel Wall Admin</span>
            </div>
            <nav class="sidebar-nav">
                <a href="#overview" class="nav-item active" data-tab="overview">
                    <i class="fas fa-home"></i> <span data-i18n="overview">Panoramica</span>
                </a>
                <a href="#settings" class="nav-item" data-tab="settings">
                    <i class="fas fa-cogs"></i> <span data-i18n="settings">Configurazione</span>
                </a>
                <a href="#gamification" class="nav-item" data-tab="gamification">
                    <i class="fas fa-trophy"></i> <span data-i18n="gamification">Gamification</span>
                </a>
                <a href="#news" class="nav-item" data-tab="news">
                    <i class="fas fa-newspaper"></i> <span data-i18n="news">Notizie</span>
                </a>
                <a href="#contributions" class="nav-item" data-tab="contributions">
                    <i class="fas fa-file-invoice-dollar"></i> <span data-i18n="contributions">Contributi</span>
                </a>
                <a href="#winners" class="nav-item" data-tab="winners">
                    <i class="fas fa-star"></i> <span data-i18n="winners">Vincitori</span>
                </a>
                <a href="#branding" class="nav-item" data-tab="branding">
                    <i class="fas fa-tag"></i> <span data-i18n="branding">Personalizzazione</span>
                </a>
                <a href="#tools" class="nav-item" data-tab="tools">
                    <i class="fas fa-wrench"></i> <span data-i18n="tools">Strumenti</span>
                </a>
            </nav>
            <div class="sidebar-footer" style="display:flex; flex-direction:column; align-items:center; gap:10px;">
                <a href="../index.php" target="_blank" class="nav-item"
                    style="color: var(--accent); width:100%; text-align:center; justification:center;">
                    <i class="fas fa-external-link-alt"></i> <span data-i18n="view_wall">Vedi Wall</span>
                </a>
                <a href="?logout=1" class="logout-link" style="width:100%; text-align:center;">
                    <i class="fas fa-sign-out-alt"></i> <span data-i18n="logout">Logout</span>
                </a>
                <div class="lang-switch"
                    style="margin-top:5px; display:flex; justify-content:center; gap:15px; width:100%;">
                    <button class="lang-btn" data-lang="it"
                        style="background:none; border:none; cursor:pointer; font-size:1.5rem; transition:transform 0.2s;">🇮🇹</button>
                    <button class="lang-btn" data-lang="en"
                        style="background:none; border:none; cursor:pointer; font-size:1.5rem; transition:transform 0.2s;">🇬🇧</button>
                    <style>
                        .lang-btn:hover {
                            transform: scale(1.2);
                        }

                        .lang-btn.active {
                            opacity: 1 !important;
                            transform: scale(1.2);
                            filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.3));
                        }
                    </style>
                </div>
                <div
                    style="margin-top: 15px; font-size: 0.7rem; color: #8b949e; border-top: 1px solid #30363d; padding-top: 10px; width:100%; text-align:center;">
                    v<?php echo trim(file_get_contents(__DIR__ . '/../VERSION')); ?><br>
                    © <a href="https://madnessrepublic.com/" target="_blank"
                        style="color: #8b949e; text-decoration: none;">Madness Republic</a>
                </div>
            </div>
        </aside>

        <main class="content">
            <section id="overview" class="tab-content active">
                <header class="content-header">
                    <h1 data-i18n="overview_title">Panoramica</h1>
                </header>
                <div class="stats-grid" id="dashboard-stats">
                    <!-- Stats loaded via JS -->
                    <div class="loading">Caricamento statistiche...</div>
                </div>
            </section>

            <section id="settings" class="tab-content">
                <header class="content-header">
                    <h1 data-i18n="settings_title">Impostazioni Wall</h1>
                </header>
                <div id="settings-container">
                    <form id="settings-form" class="admin-form">
                        <div class="loading">Caricamento impostazioni...</div>
                    </form>
                </div>
            </section>

            <section id="gamification" class="tab-content">
                <header class="content-header">
                    <h1 data-i18n="gamification_title">Gamification</h1>
                </header>
                <div class="admin-form">
                    <div class="form-group">
                        <label data-i18n="gold_count">Numero Pixel Oro</label>
                        <input type="number" id="gold-count" value="10">
                    </div>
                    <div class="form-group">
                        <label data-i18n="silver_count">Numero Pixel Argento</label>
                        <input type="number" id="silver-count" value="50">
                    </div>
                    <button type="button" class="btn btn-primary" id="regenerate-pixels" data-i18n="regenerate">Rigenera
                        Locazioni
                        Segrete</button>
                    <p class="help-text" data-i18n="help_regen">Attenzione: rigenerando le locazioni, i vecchi pixel non
                        ancora trovati
                        verranno spostati.</p>
                    <div
                        style="margin-top: 15px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 6px; font-size: 0.85rem; color: #8b949e;">
                        <strong data-i18n="security_logic">Logica di Sicurezza:</strong><br>
                        <span data-i18n="security_desc">Le coordinate segrete vengono salvate in file PHP protetti
                            all'interno della cartella
                            <code>private/</code> con nomi offuscati (es.
                            <code>secret_gold_pixels_86b5a7.php</code>).<br>
                            Questo impedisce che vengano scaricati o indovinati dagli utenti tramite il browser.</span>
                    </div>
                </div>
            </section>

            <!-- Other placeholders for tabs -->
            <section id="news" class="tab-content">
                <iframe src="admin_log.php" class="admin-iframe"></iframe>
            </section>

            <section id="contributions" class="tab-content">
                <iframe src="admin_contributions.php" class="admin-iframe"></iframe>
            </section>

            <section id="winners" class="tab-content">
                <iframe src="admin_winners.php" class="admin-iframe"></iframe>
            </section>

            <section id="branding" class="tab-content">
                <header class="content-header">
                    <h1 data-i18n="branding">Personalizzazione</h1>
                </header>
                <form class="admin-form" id="branding-form">
                    <!-- Loaded via JS -->
                    <div class="loading" data-i18n="loading">Caricamento...</div>
                </form>
            </section>

            <section id="tools" class="tab-content">
                <header class="content-header">
                    <h1 data-i18n="tool_title">Strumenti di Sistema</h1>
                </header>
                <div class="tools-grid">
                    <div class="tool-card">
                        <h3 data-i18n="tool_reset">Reset Wall</h3>
                        <p data-i18n="tool_reset_desc">Cancella tutti i pixel, transazioni e contributi. Viene creato un
                            backup automatico.</p>
                        <button class="btn btn-danger" id="btn-reset-wall" data-i18n="btn_reset">ESEGUI RESET</button>
                    </div>
                    <div class="tool-card">
                        <h3 data-i18n="tool_backup">Backup Manuale</h3>
                        <p data-i18n="tool_backup_desc">Scarica un archivio ZIP con tutti i dati attuali.</p>
                        <button class="btn btn-outline" id="btn-backup-data" data-i18n="btn_backup">SCARICA
                            BACKUP</button>
                    </div>
                    <div class="tool-card">
                        <h3 data-i18n="tool_preset">Gestione Preset</h3>
                        <p data-i18n="tool_preset_desc">Il preset definisce lo stato iniziale del muro (pixel bloccati).
                        </p>
                        <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                            <button class="btn btn-primary" id="btn-capture-preset" style="flex:1;"
                                data-i18n="btn_capture">CATTURA</button>
                            <button class="btn btn-info" id="btn-load-preset" style="flex:1;"
                                data-i18n="btn_load">CARICA</button>
                            <button class="btn btn-danger" id="btn-clear-preset" style="flex:1;"
                                data-i18n="btn_clear">PULISCI</button>
                        </div>
                    </div>
                    <div class="tool-card">
                        <h3 data-i18n="tool_donation">Sostieni il Progetto</h3>
                        <p data-i18n="tool_donation_desc">Inviaci una donazione per supportare lo sviluppo e la
                            manutenzione.</p>
                        <div id="donation-container"
                            style="display: flex; justify-content: center; margin-top: 15px; min-height: 45px;">
                            <button id="load-donation-btn" class="btn btn-primary" style="width: 100%;">
                                <span data-i18n="btn_donation">ESEGUI DONAZIONE</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="admin-lang.js?v=2.3.0"></script>
    <script src="admin-logic.js?v=2.3.0"></script>
</body>

</html>