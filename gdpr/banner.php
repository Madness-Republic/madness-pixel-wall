<!-- GDPR Cookie Banner & Modal -->
<?php
include_once __DIR__ . '/config.php';
// Default Colors
if (!isset($gdpr_col_primary))
    $gdpr_col_primary = '#f09100';
if (!isset($gdpr_col_secondary))
    $gdpr_col_secondary = '#cccccc';
if (!isset($gdpr_col_bg))
    $gdpr_col_bg = '#1e1e1e';
if (!isset($gdpr_col_text))
    $gdpr_col_text = '#ffffff';
if (!isset($gdpr_bg_opacity))
    $gdpr_bg_opacity = 95;
if (!isset($gdpr_version))
    $gdpr_version = trim(@file_get_contents(__DIR__ . '/VERSION') ?: '1.2.0');

// Text Defaults
if (!isset($gdpr_text_title_it))
    $gdpr_text_title_it = "Cookie Policy";
if (!isset($gdpr_text_desc_it))
    $gdpr_text_desc_it = "Utilizziamo i cookie per migliorare la tua esperienza.";

function gdpr_hex2rgb($hex)
{
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "$r, $g, $b";
}
$bg_rgb = gdpr_hex2rgb($gdpr_col_bg);
$bg_alpha = $gdpr_bg_opacity / 100;

// Determine the web path to the gdpr directory
$current_dir = str_replace('\\', '/', __DIR__);
$document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$web_root = str_replace($document_root, '', $current_dir);
$web_root = '/' . ltrim(str_replace('\\', '/', $web_root), '/') . '/';
// Fix double slashes
$web_root = str_replace('//', '/', $web_root);

// Define privacy URL (can be overridden in config.php)
$gdpr_final_privacy_url = isset($gdpr_privacy_url) ? $gdpr_privacy_url : str_replace('gdpr/', '', $web_root) . 'privacy.php';
?>
<link rel="stylesheet" href="<?php echo $web_root; ?>assets/css/cookie_style.css?v=<?php echo $gdpr_version; ?>">
<style>
    :root {
        --gdpr-primary:
            <?php echo $gdpr_col_primary; ?>
        ;
        --gdpr-btn-accept-1:
            <?php echo $gdpr_col_accept_1 ?? $gdpr_col_primary; ?>
        ;
        --gdpr-btn-accept-2:
            <?php echo $gdpr_col_accept_2 ?? '#ff4d4d'; ?>
        ;
        --gdpr-secondary:
            <?php echo $gdpr_col_secondary; ?>
        ;
        --gdpr-bg: rgba(<?php echo $bg_rgb; ?>,
                <?php echo $bg_alpha; ?>
            );
        --gdpr-text:
            <?php echo $gdpr_col_text; ?>
        ;
    }
</style>

<?php
$enabled_langs = isset($gdpr_enabled_languages) ? $gdpr_enabled_languages : ['it', 'en'];
$translations_bundle = [];
foreach ($enabled_langs as $lang) {
    $f = __DIR__ . "/languages/$lang.json";
    if (file_exists($f)) {
        $translations_bundle[$lang] = json_decode(file_get_contents($f), true);
        if (isset($translations_bundle[$lang]['admin']))
            unset($translations_bundle[$lang]['admin']);
    }
}
?>
<script<?php echo isset($nonce) ? ' nonce="' . $nonce . '"' : ''; ?>>
    window.GDPR_ROOT = '<?php echo $web_root; ?>';
    window.gdprTranslations = <?php echo json_encode($translations_bundle); ?>;
    window.MadnessGDPR = {
        ga4_id: '<?php echo $gdpr_ga4_id; ?>',
        cookie_duration: <?php echo $gdpr_cookie_duration; ?>,
        default_lang: '<?php echo $gdpr_default_lang; ?>',
        privacy_url: '<?php echo $gdpr_final_privacy_url; ?>',
        texts: {
            <?php
            $first = true;
            foreach ($enabled_langs as $lang) {
                if (!$first)
                    echo ",\n";
                $conf_title = ${"gdpr_text_title_$lang"} ?? '';
                $conf_desc = ${"gdpr_text_desc_$lang"} ?? '';
                echo "$lang: {";
                echo "title: " . json_encode($conf_title) . ",";
                echo "description: " . json_encode($conf_desc);
                echo "}";
                $first = false;
            }
            ?>
        }
    };
</script>
    <script<?php echo isset($nonce) ? ' nonce="' . $nonce . '"' : ''; ?>
        src="<?php echo $web_root; ?>assets/js/consent_manager.js?v=<?php echo $gdpr_version; ?>">
        </script>

        <div id="gdpr-banner" class="gdpr-banner">
            <div class="gdpr-content">
                <div class="gdpr-text">
                    <h3 id="gdpr-title">Privacy</h3>
                    <p id="gdpr-text">This website uses cookies. <a href="<?php echo $gdpr_final_privacy_url; ?>"
                            style="color: var(--gdpr-accent); text-decoration: underline;">Privacy Policy</a></p>
                </div>
                <div class="gdpr-actions">
                    <button id="btn-accept" class="gdpr-btn gdpr-btn-accept">Accept</button>
                    <button id="btn-reject" class="gdpr-btn gdpr-btn-reject">Reject</button>
                    <button id="btn-customize" class="gdpr-btn gdpr-btn-customize">Customize</button>
                </div>
                <?php if ($gdpr_enable_branding): ?>
                    <div class="gdpr-system-info"><?php echo $gdpr_brand_name; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <button id="gdpr-floating-btn" class="gdpr-floating-btn" title="Cookie Preferences">🍪</button>

        <div id="gdpr-modal-overlay" class="gdpr-modal-overlay">
            <div class="gdpr-modal">
                <span id="close-modal" class="gdpr-modal-close">&times;</span>
                <div class="gdpr-modal-header">
                    <h2 id="modal-title">Cookie Preferences</h2>
                    <p id="modal-intro">Manage your settings.</p>
                </div>
                <div class="gdpr-modal-body">
                    <div class="gdpr-category">
                        <div class="gdpr-cat-info">
                            <h4 id="cat-necessary">Strictly Necessary</h4>
                            <p id="desc-necessary">Essential for the website to function.</p>
                        </div>
                        <label class="gdpr-switch">
                            <input type="checkbox" checked disabled>
                            <span class="gdpr-slider"></span>
                        </label>
                    </div>
                    <div class="gdpr-category">
                        <div class="gdpr-cat-info">
                            <h4 id="cat-analytics">Analytics</h4>
                            <p id="desc-analytics">Anonymous usage statistics.</p>
                        </div>
                        <label class="gdpr-switch">
                            <input type="checkbox" id="chk-analytics">
                            <span class="gdpr-slider"></span>
                        </label>
                    </div>
                    <div class="gdpr-category">
                        <div class="gdpr-cat-info">
                            <h4 id="cat-marketing">Marketing</h4>
                            <p id="desc-marketing">Targeted advertising.</p>
                        </div>
                        <label class="gdpr-switch">
                            <input type="checkbox" id="chk-marketing">
                            <span class="gdpr-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="gdpr-modal-footer">
                    <div style="font-size: 0.8rem;">
                        <a href="<?php echo $gdpr_final_privacy_url; ?>"
                            style="color: var(--gdpr-secondary); text-decoration: underline;">Privacy Policy</a>
                    </div>
                    <?php if ($gdpr_enable_branding): ?>
                        <div class="gdpr-system-info"><?php echo $gdpr_brand_name; ?></div>
                    <?php endif; ?>
                    <button id="btn-save-prefs" class="gdpr-btn gdpr-btn-accept">Save Preferences</button>
                </div>
            </div>
        </div>