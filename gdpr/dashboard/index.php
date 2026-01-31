<?php
session_start();

/**
 * MADNESS GDPR SYSTEM ADMIN
 * Manage configuration visually.
 */

// Simple Auth
$ADMIN_PASSWORD = 'password'; // Default password - Change this!
$CONFIG_FILE = __DIR__ . '/../config.php';
$VERSION = trim(file_get_contents(__DIR__ . '/../VERSION') ?: '1.2.1');

// Discover Languages
$available_langs = [];
foreach (glob(__DIR__ . '/../languages/*.json') as $file) {
    $code = basename($file, '.json');
    $lang_data = json_decode(file_get_contents($file), true);
    $l_name = $lang_data['language_name'] ?? strtoupper($code);
    $available_langs[$code] = $l_name;
}

// Determine Interface Language
$ui_lang = isset($_GET['lang']) && array_key_exists($_GET['lang'], $available_langs) ? $_GET['lang'] : (isset($_SESSION['ui_lang']) ? $_SESSION['ui_lang'] : 'it');
if (!array_key_exists($ui_lang, $available_langs))
    $ui_lang = 'it'; // Fallback
$_SESSION['ui_lang'] = $ui_lang;

// Load Admin Translations
$lang_json = json_decode(file_get_contents(__DIR__ . "/../languages/$ui_lang.json"), true);
$t = $lang_json['admin'];

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF'] . "?lang=$ui_lang");
    exit;
}

// Handle Login
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $ADMIN_PASSWORD) {
        $_SESSION['gdpr_admin_logged_in'] = true;
        header("Location: " . $_SERVER['PHP_SELF'] . "?lang=$ui_lang");
        exit;
    } else {
        $login_error = $t['wrong_pass'];
    }
}

// Check Auth
if (!isset($_SESSION['gdpr_admin_logged_in']) || $_SESSION['gdpr_admin_logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $ui_lang; ?>">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $t['title']; ?> - Login</title>
        <style>
            body {
                font-family: sans-serif;
                background: #0f172a;
                color: #f1f5f9;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }

            .login-card {
                background: #1e293b;
                padding: 2rem;
                border-radius: 1rem;
                width: 100%;
                max-width: 350px;
                text-align: center;
                border: 1px solid #334155;
            }

            h1 {
                color: #f59e0b;
                margin-top: 0;
                font-size: 1.5rem;
            }

            input {
                width: 100%;
                padding: 10px;
                margin: 15px 0;
                border-radius: 6px;
                border: 1px solid #475569;
                background: #0f172a;
                color: white;
                box-sizing: border-box;
            }

            button {
                width: 100%;
                padding: 10px;
                border-radius: 6px;
                border: none;
                background: #f59e0b;
                color: #000;
                font-weight: bold;
                cursor: pointer;
            }

            button:hover {
                background: #d97706;
            }

            .error {
                color: #ef4444;
                font-size: 0.9rem;
                margin-bottom: 10px;
            }

            .lang-switch {
                margin-top: 20px;
                font-size: 0.9rem;
            }

            .lang-switch a {
                color: #94a3b8;
                text-decoration: none;
                margin: 0 5px;
            }

            .lang-switch a.active {
                color: #f59e0b;
                font-weight: bold;
            }
        </style>
    </head>

    <body>
        <div class="login-card">
            <h1><?php echo $t['title']; ?></h1>
            <?php if ($login_error): ?>
                <div class="error"><?php echo $login_error; ?></div><?php endif; ?>
            <form method="POST">
                <input type="password" name="login_password" placeholder="<?php echo $t['password']; ?>" required autofocus>
                <button type="submit"><?php echo $t['login']; ?></button>
            </form>
        </div>
        <div class="lang-switch">
            <select onchange="window.location.href='?lang='+this.value" style="background:#0f172a; color:#94a3b8; border:1px solid #334155; padding:5px 10px; border-radius:6px; cursor:pointer;">
                <?php foreach($available_langs as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo $ui_lang === $code ? 'selected' : ''; ?>>
                        <?php echo $name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </body>

    </html>
    <?php
    exit;
}

// Load current config
$gdpr_company_name = '';
$gdpr_company_address = '';
$gdpr_company_vat = '';
$gdpr_company_email = '';
$gdpr_ga4_id = '';
$gdpr_cookie_duration = 180;
$gdpr_default_lang = 'it';
$gdpr_privacy_url = 'privacy.php';
// Default Colors
$gdpr_col_primary = '#f09100';
$gdpr_col_accept_1 = '#f09100';
$gdpr_col_accept_2 = '#ff4d4d';
$gdpr_col_secondary = '#cccccc';
$gdpr_col_bg = '#1e1e1e';
$gdpr_col_text = '#ffffff';
$gdpr_bg_opacity = 95; // Default 95%
// Default Texts (we will load them dynamically later, but we need defaults if not set)
// No static defaults needed here as we will loop available langs
if (file_exists($CONFIG_FILE)) {
    include $CONFIG_FILE;
    if (!isset($gdpr_bg_opacity))
        $gdpr_bg_opacity = 95;
    if (!isset($gdpr_enabled_languages))
        $gdpr_enabled_languages = ['it', 'en']; // New default
} else {
    $gdpr_enabled_languages = ['it', 'en'];
}

// Handle Save
$success_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {

    // Sanitize Inputs
    $c_name = trim($_POST['company_name']);
    $c_addr = trim($_POST['company_address']);
    $c_vat = trim($_POST['company_vat']);
    $c_email = trim($_POST['company_email']);
    $g_id = trim($_POST['ga4_id']);
    $c_dur = (int) $_POST['cookie_duration'];
    $d_lang = trim($_POST['default_lang']);
    $p_url = trim($_POST['privacy_url'] ?? 'privacy.php');

    // Enabled Languages
    $enabled_langs = isset($_POST['enabled_languages']) ? $_POST['enabled_languages'] : [$d_lang];
    // Ensure default lang is always enabled
    if (!in_array($d_lang, $enabled_langs)) {
        $enabled_langs[] = $d_lang;
    }

    // Colors
    $col_p = trim($_POST['col_primary']);
    $col_a1 = trim($_POST['col_accept_1']);
    $col_a2 = trim($_POST['col_accept_2']);
    $col_s = trim($_POST['col_secondary']);
    $col_b = trim($_POST['col_bg']);
    $col_t = trim($_POST['col_text']);
    $bg_op = (int) $_POST['bg_opacity'];

    // Dynamic Texts & Policies
    $dynamic_texts = [];
    foreach ($enabled_langs as $lang) {
        $dynamic_texts[$lang]['title'] = trim($_POST['text_title_' . $lang] ?? '');
        $dynamic_texts[$lang]['desc'] = trim($_POST['text_desc_' . $lang] ?? '');

        // Save Policies (if posted)
        if (isset($_POST["policy_$lang"])) {
            file_put_contents(__DIR__ . "/../content/policy_$lang.html", $_POST["policy_$lang"]);
        }
    }

    // Fixed Branding
    $brand_enable = 'true';
    $brand_text = "Madness GDPR Consent System v" . $VERSION;

    // Generate Content for Config
    $content = "<?php\n";
    $content .= "// Madness GDPR Consent System - Configuration File\n\n";
    $content .= "// 1. Company Information (For Policy Pages)\n";
    $content .= "\$gdpr_company_name = " . var_export($c_name, true) . ";\n";
    $content .= "\$gdpr_company_address = " . var_export($c_addr, true) . ";\n";
    $content .= "\$gdpr_company_vat = " . var_export($c_vat, true) . ";\n";
    $content .= "\$gdpr_company_email = " . var_export($c_email, true) . ";\n\n";
    $content .= "// 2. Technical Settings\n";
    $content .= "\$gdpr_ga4_id = " . var_export($g_id, true) . "; // Google Analytics 4 Measurement ID\n";
    $content .= "\$gdpr_cookie_duration = $c_dur; // Days\n";
    $content .= "\$gdpr_default_lang = " . var_export($d_lang, true) . ";\n";
    $content .= "\$gdpr_privacy_url = " . var_export($p_url, true) . ";\n";
    $content .= "\$gdpr_version = " . var_export($VERSION, true) . ";\n";
    $content .= "\$gdpr_enabled_languages = " . var_export($enabled_langs, true) . ";\n\n";
    $content .= "// 3. Style Settings\n";
    $content .= "\$gdpr_col_primary = " . var_export($col_p, true) . ";\n";
    $content .= "\$gdpr_col_accept_1 = " . var_export($col_a1, true) . ";\n";
    $content .= "\$gdpr_col_accept_2 = " . var_export($col_a2, true) . ";\n";
    $content .= "\$gdpr_col_secondary = " . var_export($col_s, true) . ";\n";
    $content .= "\$gdpr_col_bg = " . var_export($col_b, true) . ";\n";
    $content .= "\$gdpr_col_text = " . var_export($col_t, true) . ";\n";
    $content .= "\$gdpr_bg_opacity = $bg_op;\n\n";
    $content .= "// 4. Text Settings\n";

    foreach ($dynamic_texts as $lang => $txt) {
        $content .= "\$gdpr_text_title_$lang = " . var_export($txt['title'], true) . ";\n";
        $content .= "\$gdpr_text_desc_$lang = " . var_export($txt['desc'], true) . ";\n";
    }

    $content .= "\n// 5. Branding (MANDATORY per License)\n";
    $content .= "// Modifying these lines to hide branding is a violation of the license.\n";
    $content .= "\$gdpr_enable_branding = " . $brand_enable . ";\n";
    $content .= "\$gdpr_brand_name = " . var_export($brand_text, true) . ";\n";
    $content .= "?>";

    // Write Config File
    if (file_put_contents($CONFIG_FILE, $content)) {
        $success_msg = $t['save_success'];

        // Force update variables in memory
        $gdpr_company_name = $c_name;
        $gdpr_company_address = $c_addr;
        $gdpr_company_vat = $c_vat;
        $gdpr_company_email = $c_email;
        $gdpr_ga4_id = $g_id;
        $gdpr_cookie_duration = $c_dur;
        $gdpr_default_lang = $d_lang;
        $gdpr_privacy_url = $p_url;
        $gdpr_enabled_languages = $enabled_langs;

        $gdpr_col_primary = $col_p;
        $gdpr_col_accept_1 = $col_a1;
        $gdpr_col_accept_2 = $col_a2;
        $gdpr_col_secondary = $col_s;
        $gdpr_col_bg = $col_b;
        $gdpr_col_text = $col_t;
        $gdpr_bg_opacity = $bg_op;

        foreach ($dynamic_texts as $lang => $txt) {
            ${"gdpr_text_title_$lang"} = $txt['title'];
            ${"gdpr_text_desc_$lang"} = $txt['desc'];
        }

        // Reload checks
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($CONFIG_FILE, true);
        }
    } else {
        $success_msg = $t['save_error'];
    }
}

// Load Policy Content to display in form
$policy_contents = [];
$enabled_langs_to_load = array_keys($available_langs);
foreach ($enabled_langs_to_load as $lang) {
    // If variable not set (e.g. first run of new language), default to empty
    // But we also need to have the TITLE and DESC variables available for the form below
    // We already loaded them from config if present via variable variable names
    // e.g. ${"gdpr_text_title_$lang"}

    // Policy Content
    $p_path = __DIR__ . "/../content/policy_$lang.html";
    if (file_exists($p_path)) {
        $policy_contents[$lang] = file_get_contents($p_path);
    } else {
        $policy_contents[$lang] = ""; // Content to be created
    }
}

?>
<!DOCTYPE html>
<html lang="<?php echo $ui_lang; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['title']; ?></title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            margin: 0;
            padding: 20px;

        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #334155;
            padding-bottom: 20px;
        }

        h1 {
            color: #f59e0b;
            margin: 0;
        }

        .nav-links a {
            color: #94a3b8;
            text-decoration: none;
            margin-left: 15px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #f59e0b;
        }

        .nav-links .logout {
            color: #ef4444;
        }

        .card {
            background: #1e293b;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #334155;
            margin-bottom: 20px;
        }

        h2 {
            margin-top: 0;
            border-bottom: 1px solid #334155;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #cbd5e1;
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #94a3b8;
            font-weight: 500;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="number"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            background: #0f172a;
            border: 1px solid #475569;
            color: white;
            border-radius: 6px;
            box-sizing: border-box;
            font-family: inherit;
        }

        textarea {
            min-height: 400px;
            line-height: 1.5;
            font-family: monospace;
            font-size: 0.9rem;
        }

        input:focus,
        textarea:focus {
            border-color: #f59e0b;
            outline: none;
        }

        .help-text {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 5px;
        }

        .btn-save {
            background: #f59e0b;
            color: #000;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            display: block;
            width: 100%;
            margin-top: 40px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            transition: background 0.2s, transform 0.2s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            background: #d97706;
        }

        .success {
            background: #064e3b;
            color: #34d399;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #059669;
        }

        .branding-notice {
            background: rgba(245, 158, 11, 0.1);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #f59e0b;
            font-size: 0.9rem;
            color: #fbbf24;
        }

        /* Range Slider */
        input[type=range] {
            -webkit-appearance: none;
            width: 100%;
            background: transparent;
        }

        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 20px;
            width: 20px;
            border-radius: 50%;
            background: #f59e0b;
            cursor: pointer;
            margin-top: -8px;
        }

        input[type=range]::-webkit-slider-runnable-track {
            width: 100%;
            height: 4px;
            cursor: pointer;
            background: #475569;
            border-radius: 2px;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .tab-btn {
            background: #334155;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .tab-btn.active {
            background: #f59e0b;
            color: black;
            font-weight: bold;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Responsive Improvements */
        .responsive-flex {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
            }

            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .nav-links {
                display: flex;
                gap: 10px;
                justify-content: center;
                flex-wrap: wrap;
            }

            .nav-links a {
                margin: 0;
            }

            .responsive-flex {
                flex-direction: column;
                gap: 30px;
            }

            .card {
                padding: 15px;
            }

            #preview-container {
                min-height: 250px !important;
                height: auto !important;
                padding: 10px !important;
            }

            .gdpr-banner {
                transform: scale(0.85);
                transform-origin: bottom right;
                width: 120% !important;
            }
        }

        @media screen and (max-width: 480px) {
            h1 {
                font-size: 1.5rem;
            }

            .gdpr-banner {
                transform: scale(0.7);
                width: 140% !important;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/cookie_style.css">
    <!-- Note: using relative path logic for admin usually serving from gdpr/ -->
    <script>
        function openTab(lang) {
            // Scope to Policy Edition
            document.querySelectorAll('.tab-content:not(.banner-tab-content)').forEach(d => d.style.display = 'none');
            document.querySelectorAll('.tab-btn:not([data-lang-banner-btn])').forEach(b => b.classList.remove('active'));

            document.getElementById('tab-' + lang).style.display = 'block';
            document.getElementById('btn-' + lang).classList.add('active');
        }

        function openBannerTab(lang) {
            // Scope to Banner Texts
            document.querySelectorAll('.banner-tab-content').forEach(d => d.style.display = 'none');
            document.querySelectorAll('[data-lang-banner-btn]').forEach(b => b.classList.remove('active'));

            document.getElementById('banner-tab-' + lang).style.display = 'block';
            document.getElementById('banner-btn-' + lang).classList.add('active');
        }

        function syncLanguageVisibility() {
            const checkboxes = document.querySelectorAll('input[name="enabled_languages[]"]');
            checkboxes.forEach(cb => {
                const lang = cb.value;
                const isEnabled = cb.checked;
                
                // Toggle banner text tabs
                const bannerBtn = document.querySelector(`[data-lang-banner-btn="${lang}"]`);
                if (bannerBtn) bannerBtn.style.display = isEnabled ? 'inline-block' : 'none';
                
                const bannerTab = document.querySelector(`[data-lang-banner-content="${lang}"]`);
                // if we disable the active banner tab, switch to another
                if (!isEnabled && document.getElementById('banner-btn-' + lang).classList.contains('active')) {
                    const firstEnabled = document.querySelector('input[name="enabled_languages[]"]:checked');
                    if (firstEnabled) openBannerTab(firstEnabled.value);
                }

                // Toggle policy tabs
                const tabBtn = document.querySelector(`[data-lang-tab-btn="${lang}"]`);
                if (tabBtn) tabBtn.style.display = isEnabled ? 'inline-block' : 'none';
                
                // If we disable the active policy tab, switch to another enabled one
                if (!isEnabled && document.getElementById('btn-' + lang).classList.contains('active')) {
                    const firstEnabled = document.querySelector('input[name="enabled_languages[]"]:checked');
                    if (firstEnabled) openTab(firstEnabled.value);
                }
            });
            // Update preview to reflect changes in available targets
            if (typeof updatePreview === 'function') updatePreview();
        }

        // Helper to convert hex to rgba
        function hexToRgba(hex, alpha) {
            let c;
            if (/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
                c = hex.substring(1).split('');
                if (c.length == 3) {
                    c = [c[0], c[0], c[1], c[1], c[2], c[2]];
                }
                c = '0x' + c.join('');
                return 'rgba(' + [(c >> 16) & 255, (c >> 8) & 255, c & 255].join(',') + ',' + alpha + ')';
            }
            return hex; // fallback
        }
    </script>
</head>

<body>

    <div class="container">
        <header>
            <div style="flex:1;">
                <h1 style="margin-bottom: 5px;"><?php echo $t['title']; ?> ⚙️</h1>
                <p style="margin: 0; font-size: 0.8rem; color: #94a3b8; font-weight: 500;">
                    Madness GDPR v<?php echo $VERSION; ?> | Last Update: <?php echo date("d M Y", filemtime(__DIR__ . '/../VERSION')); ?>
                </p>
            </div>
            <nav class="nav-links" style="display:flex; align-items:center; gap:10px;">
                <a href="../docs/install_guide.php?lang=<?php echo $ui_lang; ?>" style="color: #94a3b8; font-weight: 600; text-decoration: none; font-size: 0.9rem;">📖 <?php echo $t['install_guide']; ?></a>
                <a href="../docs/technical_compliance.php?lang=<?php echo $ui_lang; ?>" style="color: #94a3b8; font-weight: 600; text-decoration: none; font-size: 0.9rem; margin-left: 10px;">🛠️ <?php echo $t['tech_doc']; ?></a>
                <select onchange="window.location.href='?lang='+this.value" style="background:#0f172a; color:#f59e0b; border:1px solid #f59e0b; padding:4px 8px; border-radius:6px; cursor:pointer; font-weight:600; outline:none; margin-left: 10px; width: auto !important;">
                    <?php foreach($available_langs as $code => $name): ?>
                        <option value="<?php echo $code; ?>" <?php echo $ui_lang === $code ? 'selected' : ''; ?>>
                            <?php echo strtoupper($code); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="?logout=1" class="logout" style="margin-left:5px;"><?php echo $t['logout']; ?></a>
            </nav>
        </header>

        <?php if ($success_msg): ?>
            <div class="success"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="save_config" value="1">

            <div class="card">
                <h2>🏢 <?php echo $t['company_data']; ?></h2>
                <p style="font-size: 0.9rem; color: #94a3b8; margin-bottom: 20px;"><?php echo $t['company_desc']; ?></p>

                <div class="form-group">
                    <label><?php echo $t['company_name']; ?> <small>(Placeholder: {{company_name}})</small></label>
                    <input type="text" name="company_name" value="<?php echo htmlspecialchars($gdpr_company_name); ?>"
                        required>
                </div>
                <div class="form-group">
                    <label><?php echo $t['company_addr']; ?> <small>(Placeholder: {{company_address}})</small></label>
                    <input type="text" name="company_address"
                        value="<?php echo htmlspecialchars($gdpr_company_address); ?>" required>
                </div>
                <div class="form-group">
                    <label><?php echo $t['company_vat']; ?> <small>(Placeholder: {{company_vat}})</small></label>
                    <input type="text" name="company_vat" value="<?php echo htmlspecialchars($gdpr_company_vat); ?>"
                        required>
                </div>
                <div class="form-group">
                    <label><?php echo $t['company_email']; ?> <small>(Placeholder: {{company_email}})</small></label>
                    <input type="email" name="company_email"
                        value="<?php echo htmlspecialchars($gdpr_company_email); ?>" required>
                </div>
            </div>

            <div class="card">
                <h2>⚙️ <?php echo $t['tech_settings']; ?></h2>

                <div class="form-group">
                    <label><?php echo $t['ga4_id']; ?></label>
                    <input type="text" name="ga4_id" value="<?php echo htmlspecialchars($gdpr_ga4_id); ?>"
                        placeholder="G-...">
                    <div class="help-text"><?php echo $t['ga4_help']; ?></div>
                </div>

                <div class="form-group">
                    <label><?php echo $t['cookie_dur']; ?></label>
                    <input type="number" name="cookie_duration"
                        value="<?php echo htmlspecialchars($gdpr_cookie_duration); ?>" min="1" max="365">
                </div>

                <div class="form-group">
                    <label><?php echo $t['default_lang']; ?></label>
                    <select name="default_lang">
                        <?php foreach ($available_langs as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo $gdpr_default_lang === $code ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help-text"><?php echo $t['lang_help']; ?></div>
                </div>

                <div class="form-group">
                    <label><?php echo $t['privacy_url_label'] ?? 'Privacy Policy URL'; ?></label>
                    <input type="text" name="privacy_url" value="<?php echo htmlspecialchars($gdpr_privacy_url ?? 'privacy.php'); ?>">
                    <div class="help-text"><?php echo $t['privacy_url_help'] ?? 'Specify the path from the site root.'; ?></div>
                </div>

                <div class="form-group">
                    <label><?php echo $t['enabled_langs']; ?></label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <?php foreach ($available_langs as $code => $name): ?>
                            <label style="display: flex; align-items: center; gap: 5px; font-weight: normal; color: #fff; cursor: pointer;">
                                <input type="checkbox" name="enabled_languages[]" value="<?php echo $code; ?>"
                                    <?php echo in_array($code, $gdpr_enabled_languages) ? 'checked' : ''; ?>
                                    onchange="syncLanguageVisibility()">
                                <?php echo $name; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="help-text"><?php echo $t['add_lang_help']; ?></div>
                </div>
            </div>

            <div class="card">
                <h2>📝 <?php echo $t['banner_text_title']; ?></h2>
                <p style="font-size: 0.9rem; color: #94a3b8;"><?php echo $t['banner_text_intro']; ?></p>

                <div class="tabs">
                    <?php 
                    $first = true;
                    foreach ($available_langs as $lang => $name): 
                        $isEnabled = in_array($lang, $gdpr_enabled_languages);
                    ?>
                    <button type="button" class="tab-btn <?php echo ($isEnabled && $first) ? 'active' : ''; ?>" 
                            id="banner-btn-<?php echo $lang; ?>" 
                            style="<?php echo $isEnabled ? '' : 'display:none;'; ?>"
                            data-lang-banner-btn="<?php echo $lang; ?>"
                            onclick="openBannerTab('<?php echo $lang; ?>')">
                        <?php echo $name; ?>
                    </button>
                    <?php if ($isEnabled) $first = false; endforeach; ?>
                </div>

                <?php 
                $first = true;
                foreach ($available_langs as $lang => $name): 
                    $isEnabled = in_array($lang, $gdpr_enabled_languages);
                    
                    // Load defaults from JSON if config variable doesn't exist
                    $lang_data = json_decode(file_get_contents(__DIR__ . "/languages/$lang.json"), true);
                    $default_title = $lang_data['banner_title'] ?? '';
                    $default_desc = $lang_data['banner_text'] ?? '';
                    
                    $t_title = ${"gdpr_text_title_$lang"} ?? $default_title;
                    $t_desc = ${"gdpr_text_desc_$lang"} ?? $default_desc;
                ?>
                <div id="banner-tab-<?php echo $lang; ?>" class="tab-content banner-tab-content" 
                     style="<?php echo ($isEnabled && $first) ? 'display:block;' : 'display:none;'; ?>"
                     data-lang-banner-content="<?php echo $lang; ?>">
                    
                    <div class="form-group">
                        <label><?php echo $t['text_title']; ?></label>
                        <input type="text" name="text_title_<?php echo $lang; ?>"
                            value="<?php echo htmlspecialchars($t_title); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo $t['text_desc']; ?></label>
                        <textarea name="text_desc_<?php echo $lang; ?>"
                            style="min-height: 150px;"><?php echo htmlspecialchars($t_desc); ?></textarea>
                        <div class="help-text">HTML allowed.</div>
                    </div>
                </div>
                <?php if($isEnabled) $first = false; endforeach; ?>
            </div>

            <div class="card">
                <h2>🎨 <?php echo $t['style_title']; ?></h2>
                <p style="font-size: 0.9rem; color: #94a3b8;"><?php echo $t['style_desc']; ?></p>

                <div class="responsive-flex" style="gap: 15px; margin-bottom: 20px;">
                    <div style="flex:1; min-width: 140px;">
                        <label style="font-size: 0.85rem;"><?php echo $t['col_primary']; ?></label>
                        <div style="display:flex; gap:5px;">
                            <input type="color" name="col_primary" id="col_primary"
                                value="<?php echo htmlspecialchars($gdpr_col_primary); ?>"
                                style="width: 35px; padding: 0; border: none; height: 35px; cursor: pointer; border-radius: 4px;">
                            <input type="text" name="col_primary_text"
                                value="<?php echo htmlspecialchars($gdpr_col_primary); ?>"
                                style="padding: 4px 8px; font-size: 0.85rem;"
                                onchange="document.getElementById('col_primary').value = this.value; updatePreview();">
                        </div>
                    </div>
                    <div style="flex:1; min-width: 140px;">
                        <label style="font-size: 0.85rem;"><?php echo $t['col_accept_1']; ?></label>
                        <div style="display:flex; gap:5px;">
                            <input type="color" name="col_accept_1" id="col_accept_1"
                                value="<?php echo htmlspecialchars($gdpr_col_accept_1 ?? $gdpr_col_primary); ?>"
                                style="width: 35px; padding: 0; border: none; height: 35px; cursor: pointer; border-radius: 4px;">
                            <input type="text" name="col_accept_1_text"
                                value="<?php echo htmlspecialchars($gdpr_col_accept_1 ?? $gdpr_col_primary); ?>"
                                style="padding: 4px 8px; font-size: 0.85rem;"
                                onchange="document.getElementById('col_accept_1').value = this.value; updatePreview();">
                        </div>
                    </div>
                    <div style="flex:1; min-width: 140px;">
                        <label style="font-size: 0.85rem;"><?php echo $t['col_accept_2']; ?></label>
                        <div style="display:flex; gap:5px;">
                            <input type="color" name="col_accept_2" id="col_accept_2"
                                value="<?php echo htmlspecialchars($gdpr_col_accept_2 ?? '#ff4d4d'); ?>"
                                style="width: 35px; padding: 0; border: none; height: 35px; cursor: pointer; border-radius: 4px;">
                            <input type="text" name="col_accept_2_text"
                                value="<?php echo htmlspecialchars($gdpr_col_accept_2 ?? '#ff4d4d'); ?>"
                                style="padding: 4px 8px; font-size: 0.85rem;"
                                onchange="document.getElementById('col_accept_2').value = this.value; updatePreview();">
                        </div>
                    </div>
                </div>

                <div class="responsive-flex" style="gap: 15px;">
                    <div style="flex:1; min-width: 140px;">
                        <label style="font-size: 0.85rem;"><?php echo $t['col_secondary']; ?></label>
                        <div style="display:flex; gap:5px;">
                            <input type="color" name="col_secondary" id="col_secondary"
                                value="<?php echo htmlspecialchars($gdpr_col_secondary); ?>"
                                style="width: 35px; padding: 0; border: none; height: 35px; cursor: pointer; border-radius: 4px;">
                            <input type="text" name="col_secondary_text"
                                value="<?php echo htmlspecialchars($gdpr_col_secondary); ?>"
                                style="padding: 4px 8px; font-size: 0.85rem;"
                                onchange="document.getElementById('col_secondary').value = this.value; updatePreview();">
                        </div>
                    </div>
                    <div style="flex:1; min-width: 140px;">
                        <label style="font-size: 0.85rem;"><?php echo $t['col_bg']; ?></label>
                        <div style="display:flex; gap:5px;">
                            <input type="color" name="col_bg" id="col_bg"
                                value="<?php echo htmlspecialchars($gdpr_col_bg); ?>"
                                style="width: 35px; padding: 0; border: none; height: 35px; cursor: pointer; border-radius: 4px;">
                            <input type="text" name="col_bg_text" 
                                value="<?php echo htmlspecialchars($gdpr_col_bg); ?>"
                                style="padding: 4px 8px; font-size: 0.85rem;"
                                onchange="document.getElementById('col_bg').value = this.value; updatePreview();">
                        </div>
                    </div>
                    <div style="flex:1; min-width: 140px;">
                        <label style="font-size: 0.85rem;"><?php echo $t['col_text']; ?></label>
                        <div style="display:flex; gap:5px;">
                            <input type="color" name="col_text" id="col_text"
                                value="<?php echo htmlspecialchars($gdpr_col_text); ?>"
                                style="width: 35px; padding: 0; border: none; height: 35px; cursor: pointer; border-radius: 4px;">
                            <input type="text" name="col_text_text"
                                value="<?php echo htmlspecialchars($gdpr_col_text); ?>"
                                style="padding: 4px 8px; font-size: 0.85rem;"
                                onchange="document.getElementById('col_text').value = this.value; updatePreview();">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 25px;">
                    <label><?php echo $t['bg_opacity']; ?>: <span
                            id="op_val"><?php echo $gdpr_bg_opacity; ?></span>%</label>
                    <input type="range" name="bg_opacity" id="bg_opacity" min="20" max="100"
                        value="<?php echo $gdpr_bg_opacity; ?>"
                        oninput="document.getElementById('op_val').innerText = this.value; updatePreview();">
                </div>

                <hr style="border: 0; border-top: 1px solid #334155; margin: 20px 0;">

                <h3><?php echo $t['preview_title']; ?></h3>
                <div id="preview-container"
                    style="position: relative; min-height: 300px; height: auto; background: #333; border-radius: 8px; border: 1px dashed #666; display: flex; align-items: flex-end; justify-content: flex-end; padding: 20px; overflow: hidden; min-width: 320px;">
                    <!-- Mock Background -->
                    <div
                        style="position: absolute; top:0; left:0; width:100%; height:100%; opacity: 0.3; background-image: repeating-linear-gradient(45deg, #444 0, #444 1px, transparent 0, transparent 50%); background-size: 20px 20px;">
                    </div>

                    <!-- Mock Banner -->
                    <div class="gdpr-banner"
                        style="display: block; position: relative; right: auto; bottom: auto; animation: none;">
                        <div class="gdpr-content">
                            <div class="gdpr-text">
                                <h3><?php echo $t['preview_policy']; ?></h3>
                                <p><?php echo $t['preview_text']; ?></p>
                            </div>
                            <div class="gdpr-actions">
                                <button type="button"
                                    class="gdpr-btn gdpr-btn-accept"><?php echo $t['preview_accept']; ?></button>
                                <button type="button"
                                    class="gdpr-btn gdpr-btn-reject"><?php echo $t['preview_reject']; ?></button>
                                <a href="#" class="gdpr-btn-customize"><?php echo $t['preview_customize']; ?></a>
                            </div>
                            <div class="gdpr-system-info">
                                <?php echo $gdpr_brand_name; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <script>

                    const enabledLangs = <?php echo json_encode(array_keys($available_langs)); ?>;
                    function getEnabledLangs() {
                        return Array.from(document.querySelectorAll('input[name="enabled_languages[]"]:checked')).map(cb => cb.value);
                    }

                    function updatePreview() {
                        const colP = document.getElementById('col_primary').value;
                        const colA1 = document.getElementById('col_accept_1').value;
                        const colA2 = document.getElementById('col_accept_2').value;
                        const colS = document.getElementById('col_secondary').value;
                        const colB = document.getElementById('col_bg').value;
                        const colT = document.getElementById('col_text').value;
                        const op = document.getElementById('bg_opacity').value;

                        // Sync text inputs
                        document.getElementsByName('col_primary_text')[0].value = colP;
                        document.getElementsByName('col_accept_1_text')[0].value = colA1;
                        document.getElementsByName('col_accept_2_text')[0].value = colA2;
                        document.getElementsByName('col_secondary_text')[0].value = colS;
                        document.getElementsByName('col_bg_text')[0].value = colB;
                        document.getElementsByName('col_text_text')[0].value = colT;

                        const container = document.getElementById('preview-container');
                        container.style.setProperty('--gdpr-primary', colP);
                        container.style.setProperty('--gdpr-btn-accept-1', colA1);
                        container.style.setProperty('--gdpr-btn-accept-2', colA2);
                        container.style.setProperty('--gdpr-secondary', colS);
                        // With transparency
                        const rgbaBg = hexToRgba(colB, op / 100);
                        container.style.setProperty('--gdpr-bg', rgbaBg);

                        container.style.setProperty('--gdpr-text', colT);
                        // Standard colors don't change often but accent usually matches primary or specific
                        container.style.setProperty('--gdpr-accent', '#1de9b6');

                        // Update Text Preview from Inputs
                        const adminUiLang = '<?php echo $ui_lang; ?>';
                        const currentLangs = getEnabledLangs();
                        // Fallback to first enabled if current not active
                        let targetLang = currentLangs.includes(adminUiLang) ? adminUiLang : currentLangs[0];
                        
                        if (targetLang) {
                            const titleEl = document.getElementsByName('text_title_' + targetLang)[0];
                            const descEl = document.getElementsByName('text_desc_' + targetLang)[0];
                            
                            if (titleEl) container.querySelector('.gdpr-text h3').textContent = titleEl.value;
                            if (descEl) {
                                let descVal = descEl.value;
                                // Clean up display for preview (remove extra slashes if any)
                                descVal = descVal.replace(/\\'/g, "'").replace(/\\"/g, '"');
                                container.querySelector('.gdpr-text p').innerHTML = descVal;
                            }
                        }
                    }

                    // Listeners
                    document.getElementById('col_primary').addEventListener('input', updatePreview);
                    document.getElementById('col_accept_1').addEventListener('input', updatePreview);
                    document.getElementById('col_accept_2').addEventListener('input', updatePreview);
                    document.getElementById('col_secondary').addEventListener('input', updatePreview);
                    document.getElementById('col_bg').addEventListener('input', updatePreview);
                    document.getElementById('col_text').addEventListener('input', updatePreview);

                    enabledLangs.forEach(lang => {
                         const t = document.getElementsByName('text_title_' + lang)[0];
                         const d = document.getElementsByName('text_desc_' + lang)[0];
                         if (t) t.addEventListener('input', updatePreview);
                         if (d) d.addEventListener('input', updatePreview);
                    });

                    // Init
                    updatePreview();
                </script>
            </div>

            <div class="card">
                <div
                    style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #334155; padding-bottom: 10px; margin-bottom: 20px;">
                    <h2 style="border:none; padding:0; margin:0;">📄 <?php echo $t['edit_policy']; ?></h2>
                    <a href="preview_standalone.php" target="_blank"
                        style="background: #334155; color: #f59e0b; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; border: 1px solid #f59e0b;">👁️
                        Preview Policy</a>
                </div>
                <p style="font-size: 0.9rem; color: #94a3b8;"><?php echo $t['policy_desc']; ?></p>
                <div class="help-text" style="color: #f59e0b; font-weight: 600; margin-bottom: 20px;">
                    <?php echo $t['preview_help']; ?>
                </div>

                <div class="tabs">
                    <?php 
                    $first = true;
                    foreach ($available_langs as $lang => $name): 
                        $isEnabled = in_array($lang, $gdpr_enabled_languages);
                    ?>
                    <button type="button" class="tab-btn <?php echo ($isEnabled && $first) ? 'active' : ''; ?>" 
                            id="btn-<?php echo $lang; ?>" 
                            style="<?php echo $isEnabled ? '' : 'display:none;'; ?>"
                            data-lang-tab-btn="<?php echo $lang; ?>"
                            onclick="openTab('<?php echo $lang; ?>')">
                        <?php echo $name; ?>
                    </button>
                    <?php if ($isEnabled) $first = false; endforeach; ?>
                </div>

                <?php 
                $first = true;
                foreach ($available_langs as $lang => $name): 
                    $isEnabled = in_array($lang, $gdpr_enabled_languages);
                ?>
                <div id="tab-<?php echo $lang; ?>" class="tab-content" 
                     style="<?php echo ($isEnabled && $first) ? 'display:block;' : 'display:none;'; ?>"
                     data-lang-tab-content="<?php echo $lang; ?>">
                    <textarea name="policy_<?php echo $lang; ?>"
                        spellcheck="false"><?php echo htmlspecialchars($policy_contents[$lang] ?? ''); ?></textarea>
                </div>
                <?php if($isEnabled) $first = false; endforeach; ?>

            </div>

            <div class="card">
                <h2>🏷️ <?php echo $t['license']; ?></h2>
                <div class="branding-notice">
                    <?php echo $t['branding_notice']; ?>
                    <br><br>
                    <a href="LICENSE" target="_blank" style="color: #f59e0b; font-weight: 600; text-decoration: underline;">📄 <?php echo $t['view_license']; ?></a>
                    <p style="margin-top: 20px; font-size: 0.85rem; color: #94a3b8; border-top: 1px solid #334155; padding-top: 10px;">
                        <?php echo $gdpr_brand_name; ?>
                    </p>
                </div>
            </div>

            <button type="submit" class="btn-save"><?php echo $t['btn_save']; ?></button>
        </form>
    </div>

</body>

</html>