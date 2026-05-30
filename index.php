<?php
require_once 'includes/security_headers.php';
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pixel Wall Crowdfunding | Madness Republic Iglesias</title>
    <meta name="description"
        content="Fai una donazione per la Pixel Wall di Madness Republic a Iglesias. Contribuisci al crowdfunding disegnando il tuo pixel per supportare la riqualificazione del centro sportivo in Piazza Bruno Buozzi.">

    <?php
    // LOAD SETTINGS FOR TRACKING
    $settingsPath = __DIR__ . '/data/settings.json';
    $settings = file_exists($settingsPath) ? json_decode(file_get_contents($settingsPath), true) : [];
    $ga4_id = $settings['tracking']['ga4_id'] ?? '';
    // We also expose the Google Ads ID for conversion events if needed
    $ads_id = $settings['tracking']['google_ads_id'] ?? '';
    ?>

    <script nonce="<?php echo $nonce; ?>">
        window.APP_VERSION = "<?php echo file_exists('VERSION') ? trim(file_get_contents('VERSION')) : '1.3.1'; ?>";
        window.GA4_ID = "<?php echo htmlspecialchars($ga4_id); ?>";
        window.ADS_ID = "<?php echo htmlspecialchars($ads_id); ?>";
    </script>

    <!-- Google tag (gtag.js) - Static for verification -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($ads_id ?: $ga4_id); ?>"
        nonce="<?php echo $nonce; ?>"></script>
    <script nonce="<?php echo $nonce; ?>">
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        window.gtag = gtag; // Explicitly make it global

        // 1. Consent Mode Default (Advanced)
        // Set defaults BEFORE any config calls
        gtag('consent', 'default', {
            'ad_storage': 'denied',
            'ad_user_data': 'denied',
            'ad_personalization': 'denied',
            'analytics_storage': 'denied',
            'wait_for_update': 500
        });

        gtag('js', new Date());

        // 2. Initial Config
        if (window.ADS_ID) {
            gtag('config', window.ADS_ID);
        }
        if (window.GA4_ID) {
            gtag('config', window.GA4_ID, { 'anonymize_ip': true });
        }
    </script>

    <script nonce="<?php echo $nonce; ?>">
        // Tracking state is handled and updated by ConsentManager (gdpr/assets/js/consent_manager.js)
    </script>


    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.madnessrepublic.com/pixelwall/">
    <meta property="og:title" content="Pixel Wall - Madness Republic">
    <meta property="og:description"
        content="Disegna il tuo pixel e supporta la costruzione del nuovo centro sportivo! La tua firma rimarrà nell'opera.">
    <meta property="og:image" content="https://www.madnessrepublic.com/pixelwall/assets/images/render_gen.jpg">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://www.madnessrepublic.com/pixelwall/">
    <meta property="twitter:title" content="Pixel Wall - Madness Republic">
    <meta property="twitter:description"
        content="Disegna il tuo pixel e supporta la costruzione del nuovo centro sportivo!">
    <meta property="twitter:image" content="https://www.madnessrepublic.com/pixelwall/assets/images/render_gen.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Oswald:wght@400;500;700&family=Source+Sans+Pro:wght@400;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pixel-wall.css?v=2.3.2">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">

    <?php
    $brandingFile = __DIR__ . '/data/custom_branding.json';
    $styleConfig = [];
    if (file_exists($brandingFile)) {
        $brandingData = json_decode(file_get_contents($brandingFile), true);
        $styleConfig = $brandingData['style_config'] ?? [];
    }

    if (!empty($styleConfig)) {
        $s_modal_bg_hex = $styleConfig['modal_bg_color'] ?? '#111111';
        $s_modal_opacity = $styleConfig['modal_opacity'] ?? '0.95';

        // Convert HEX to RGBA
        list($r, $g, $b) = sscanf($s_modal_bg_hex, "#%02x%02x%02x");
        $s_modal_bg_rgba = "rgba($r, $g, $b, $s_modal_opacity)";

        $s_modal_text = $styleConfig['modal_text_color'] ?? '#ffffff';
        $s_modal_border = $styleConfig['modal_border_color'] ?? '#1de9b6';
        $s_modal_radius = ($styleConfig['modal_radius'] ?? '20') . 'px';
        ?>
        <style>
            :root {
                --bs-modal-bg:
                    <?php echo $s_modal_bg_rgba; ?>
                ;
                --bs-modal-text:
                    <?php echo $s_modal_text; ?>
                ;
                --bs-modal-border:
                    <?php echo $s_modal_border; ?>
                ;
                --bs-modal-radius:
                    <?php echo $s_modal_radius; ?>
                ;
            }

            /* Modal Overrides */
            .modal-content,
            .welcome-card {
                background: var(--bs-modal-bg) !important;
                color: var(--bs-modal-text) !important;
                border: 1px solid var(--bs-modal-border) !important;
                border-radius: var(--bs-modal-radius) !important;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.5) !important;
            }

            #welcome-modal .welcome-card {
                background: var(--bs-modal-bg) !important;
                border: 1px solid var(--bs-modal-border) !important;
                border-radius: var(--bs-modal-radius) !important;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.5) !important;
            }

            .modal-title,
            .modal-close {
                color: var(--bs-modal-border) !important;
            }

            <?php if (!empty($styleConfig['btn_primary_bg'])): ?>
                .buy-btn,
                .btn-primary {
                    background:
                        <?php echo $styleConfig['btn_primary_bg']; ?>
                        !important;
                    color:
                        <?php echo $styleConfig['btn_primary_text'] ?? '#fff'; ?>
                        !important;
                    border: 1px solid rgba(255, 255, 255, 0.1) !important;
                }

                .pixel-tools .buy-btn {
                    background: #333 !important;
                    color: #fff !important;
                }

                .pixel-tools .buy-btn.active-tool {
                    background:
                        <?php echo $styleConfig['btn_primary_bg']; ?>
                        !important;
                    color:
                        <?php echo $styleConfig['btn_primary_text'] ?? '#fff'; ?>
                        !important;
                    border-color: rgba(255, 255, 255, 0.5) !important;
                }

            <?php endif; ?>

            <?php if (!empty($styleConfig['box_vision_bg'])): ?>
                #open-mission-modal.mission-box {
                    background:
                        <?php echo $styleConfig['box_vision_bg']; ?>
                        !important;
                    animation: none !important;
                }

            <?php endif; ?>

            <?php if (!empty($styleConfig['box_wall_bg'])): ?>
                #open-wall-modal.mission-wall-box,
                #open-wall-modal.mission-box {
                    background:
                        <?php echo $styleConfig['box_wall_bg']; ?>
                        !important;
                    animation: none !important;
                }

            <?php endif; ?>

            <?php if (!empty($styleConfig['title_col_1'])):
                $c1 = $styleConfig['title_col_1'];
                $c2 = $styleConfig['title_col_2'] ?? $c1;
                $c3 = $styleConfig['title_col_3'] ?? $c1;
                $c4 = $styleConfig['title_col_4'] ?? $c1;
                ?>
                @keyframes color-cycle {
                    0% {
                        color:
                            <?php echo $c1; ?>
                        ;
                        text-shadow: 0 0 10px
                            <?php echo $c1; ?>
                            66;
                    }

                    33% {
                        color:
                            <?php echo $c2; ?>
                        ;
                        text-shadow: 0 0 10px
                            <?php echo $c2; ?>
                            66;
                    }

                    66% {
                        color:
                            <?php echo $c3; ?>
                        ;
                        text-shadow: 0 0 10px
                            <?php echo $c3; ?>
                            66;
                    }

                    100% {
                        color:
                            <?php echo $c4; ?>
                        ;
                        text-shadow: 0 0 10px
                            <?php echo $c4; ?>
                            66;
                    }
                }

            <?php endif; ?>
        </style>
    <?php } ?>

</head>

<body class="pixel-page">
    <div class="site-bg"></div>

    <!-- SEO & Accessibility Text -->
    <div style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;">
        <h1>Donazione Pixel Wall - Madness Republic Iglesias</h1>
        <p>La Pixel Wall è l'iniziativa di raccolta fondi e donazione per la riqualificazione urbana del centro sportivo Madness Republic, situato in Piazza Bruno Buozzi a Iglesias. Attraverso questa campagna di crowdfunding, puoi lasciare il tuo segno disegnando un pixel e supportare la riqualificazione della piazza e far parte della community. Un pixel, un mattone per il nostro progetto.</p>
    </div>

    <header class="pixel-header">
        <a href="../index.php" class="logo">
            <img src="assets/images/logo.png" alt="Logo" class="logo-img">
        </a>

        <div class="header-links header-links-wrapper">
            <div id="open-diary-btn" class="diary-btn" title="Dev Log">
                <div class="diary-dot"></div>
                <span data-i18n="pw-news" class="diary-label">PIXEL NEWS</span>
            </div>
            <button id="lang-toggle" data-i18n="lang-switch" class="lang-toggle-btn">EN</button>
        </div>
    </header>

    <main class="pixel-container" id="canvas-wrapper">
        <div class="community-hud">
            <div class="hud-item">
                <span class="hud-icon">🏆</span>
                <span class="hud-value" id="total-raised-hud">€ 0.00</span>
            </div>
            <div class="hud-divider"></div>
            <div class="hud-item">
                <span class="hud-icon">🎨</span>
                <span class="hud-value" id="total-pixels-hud">0</span>
            </div>
            <div class="hud-divider"></div>
            <div class="hud-item hud-item-clickable" id="hud-gold">
                <span class="hud-icon">🪙</span>
                <span class="hud-value" id="gold-found-hud">0/10</span>
            </div>
            <div class="hud-divider"></div>
            <div class="hud-item hud-item-clickable" id="hud-silver">
                <span class="hud-icon hud-icon-silver">🪙</span>
                <span class="hud-value" id="silver-found-hud">0/50</span>
            </div>
        </div>

        <div class="zoom-controls">
            <button class="zoom-btn" id="zoom-in">+</button>
            <button class="zoom-btn" id="zoom-out">-</button>
            <button class="zoom-btn" id="zoom-reset">⟲</button>
            <button class="zoom-btn" id="focus-mode" title="Focus Highlight">🌟</button>
        </div>

        <div id="floating-controls" class="floating-controls hidden">
            <p class="controls-text" data-i18n="pw-sticker-instr">Posiziona l'immagine e conferma</p>
            <div class="controls-buttons">
                <button id="floating-cancel" class="buy-btn btn-cancel" data-i18n="pw-btn-cancel">❌ Annulla</button>
                <button id="floating-confirm" class="buy-btn btn-confirm" data-i18n="pw-btn-confirm">✅ Conferma</button>
            </div>
        </div>

        <div class="right-side-controls" id="right-panel">
            <button id="toggle-controls" class="toggle-panel-btn">▼</button>
            <div class="stats-panel">
                <div class="stat-item">
                    <span class="stat-label" data-i18n="pw-area"></span>
                    <span class="stat-value val-pixels-count" id="pixels-count">0 cm²</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label" data-i18n="pw-contribution"></span>
                    <span class="stat-value val-price-count" id="price-count">€ 0.00</span>
                </div>

                <div class="pricing-info-box" id="open-pricing-modal">
                    <span class="stat-label pricing-info-title" data-i18n="pw-pricing-title"></span>
                    <p class="pricing-info-text" data-i18n="pw-land-tax"></p>
                    <p class="pricing-info-subtext" data-i18n="pw-ink-fee"></p>
                </div>

                <button class="buy-btn checkout-btn" id="checkout-btn" data-i18n="pw-confirm"></button>
            </div>

            <div class="mission-box" id="open-mission-modal">
                <span class="stat-label" data-i18n="pw-mission-label"></span>
                <p data-i18n="pw-mission-text"></p>
            </div>

            <div class="mission-box mission-wall-box" id="open-wall-modal">
                <span class="stat-label mission-wall-label" data-i18n="pw-wall-label"></span>
                <p data-i18n="pw-wall-text" class="mission-wall-text"></p>
            </div>
        </div>

        <div class="modal-overlay" id="wall-modal">
            <div class="modal-content wall-modal-content">
                <span class="modal-close wall-modal-close" id="close-wall-modal">&times;</span>
                <p data-i18n="pw-wall-intro" class="wall-modal-intro"></p>
                <iframe data-src="pages/wordcloud.html" class="wall-modal-iframe" id="wall-modal-iframe"></iframe>
            </div>
        </div>

        <canvas id="pixel-canvas"></canvas>

        <div id="wall-stats-hud" class="wall-stats-hud">
            <div id="stat-start" class="stat-cell stat-left"></div>
            <div id="stat-version" class="stat-cell stat-center"></div>
            <div id="stat-last" class="stat-cell stat-right"></div>
        </div>

        <div class="canvas-title" data-i18n="canvas-title"></div>

        <div class="pixel-tools" id="pixel-tools-bar">
            <div class="tool-group">
                <div class="color-circle active color-circle-red" data-color="#ff4d4d"></div>
                <div class="color-circle color-circle-green" data-color="#4dff88"></div>
                <div class="color-circle color-circle-blue" data-color="#4da6ff"></div>
                <div class="color-circle color-circle-yellow" data-color="#ffcc4d"></div>
                <div class="color-circle color-circle-black" data-color="#000000"></div>
                <div class="color-picker-wrapper">
                    <div class="color-circle custom-color-btn" id="custom-color-btn"
                        title="Scegli colore personalizzato"></div>
                    <input type="color" id="color-picker" class="color-input-hidden">
                </div>
            </div>
            <div class="tool-divider"></div>
            <div class="tool-group tool-draw-group">
                <button class="buy-btn tool-btn-pan" id="tool-pan" data-i18n="tool-pan"></button>
                <button class="buy-btn tool-btn-draw" id="tool-draw" data-i18n="tool-draw"></button>
                <button class="buy-btn tool-btn-erase" id="tool-erase" data-i18n="tool-erase" title="Gomma">🧽</button>
            </div>
            <div class="tool-divider"></div>
            <div class="tool-group">
                <button class="buy-btn tool-btn-undo" id="tool-undo" title="Annulla ultimo pixel"
                    data-i18n="tool-undo"></button>
                <button class="buy-btn tool-btn-clear" id="tool-clear" title="Cancella tutto"
                    data-i18n="tool-clear"></button>
            </div>
            <div class="tool-group">
                <button class="buy-btn tool-btn-upload" id="tool-upload" title="Carica Immagine"
                    data-i18n="tool-upload">🖼️</button>
                <input type="file" id="file-input" accept="image/*" class="file-input-hidden">
            </div>
        </div>
    </main>

    <!-- Modals -->
    <div class="modal-overlay" id="mission-modal">
        <div class="modal-content">
            <span class="modal-close" id="close-mission-modal">&times;</span>
            <div class="modal-title" data-i18n="pw-modal-vision-title"></div>
            <div class="modal-body">
                <img src="assets/images/real_wall.png" alt="Real Wall Render" class="mission-img">
                <div id="vision-details-container">
                    <p data-i18n="pw-modal-vision-p1"></p>
                    <p data-i18n="pw-modal-vision-p2"></p>
                    <p data-i18n="pw-modal-vision-p3"></p>
                    <p data-i18n="pw-modal-vision-tourism" class="mission-tourism-text"></p>
                </div>
                <div class="mission-modal-btns">
                    <a href="https://maps.app.goo.gl/x3qHV5TA2FzTdCSr6" target="_blank"
                        class="btn btn-outline mission-map-link" data-i18n="pw-vision-map-link"></a>
                    <a href="https://www.iglesiasturismo.it/" target="_blank"
                        class="btn btn-outline mission-tourism-link" data-i18n="pw-vision-tourism-link"></a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="size-modal">
        <div class="modal-content size-modal-content">
            <div class="modal-title" data-i18n="prompt-height">Altezza desiderata in cm</div>
            <div class="modal-body">
                <input type="number" id="image-height-input" value="20" min="1" max="100" class="size-input">
                <div class="size-buttons-wrapper">
                    <button class="buy-btn btn-auto-width btn-bg-444" id="size-cancel-btn"
                        data-i18n="pw-btn-cancel">Annulla</button>
                    <button class="buy-btn btn-auto-width" id="size-confirm-btn"
                        data-i18n="pw-btn-confirm">Conferma</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="clear-modal">
        <div class="modal-content clear-modal-content">
            <div class="modal-title" data-i18n="tool-clear">🗑️ Pulisci</div>
            <div class="modal-body">
                <p class="clear-confirm-text" data-i18n="pw-clear-confirm">Sei sicuro di voler cancellare tutto il tuo
                    disegno?</p>
                <div class="size-buttons-wrapper">
                    <button class="buy-btn btn-auto-width btn-bg-444" id="clear-cancel-btn"
                        data-i18n="pw-btn-cancel">Annulla</button>
                    <button class="buy-btn btn-auto-width btn-bg-red" id="clear-confirm-btn"
                        data-i18n="pw-btn-confirm">Conferma</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="pricing-modal">
        <div class="modal-content">
            <span class="modal-close" id="close-pricing-modal">&times;</span>
            <div class="modal-title" data-i18n="pw-modal-pricing-title"></div>
            <div class="modal-body">
                <p data-i18n="pw-modal-pricing-intro"></p>
                <p data-i18n="pw-modal-pricing-p1"></p>
                <p data-i18n="pw-modal-pricing-p2"></p>
                <p data-i18n="pw-modal-pricing-p3"></p>
                <p data-i18n="pw-modal-pricing-end"></p>
                <p data-i18n="pw-modal-support" class="support-link-wrapper"></p>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="welcome-modal">
        <div class="welcome-card modal-content">
            <span class="modal-close" id="close-welcome-modal">&times;</span>
            <div class="welcome-header">
                <div class="welcome-icon welcome-icon-style">🚀</div>
                <div class="modal-title welcome-title-style" data-i18n="pw-welcome-title"></div>
            </div>
            <div class="modal-body">
                <p data-i18n="pw-welcome-intro"></p>
                <div class="gold-box gold-box-style">
                    <div class="gold-box-header">
                        <span style="font-size:1.2rem;">🪙</span>
                        <strong class="gold-title-style" data-i18n="pw-gold-title"></strong>
                        <span class="hud-icon-silver" style="font-size:1.2rem;">🪙</span>
                    </div>
                    <p data-i18n="pw-gold-text" class="gold-text-style"></p>
                </div>
                <div id="welcome-details-container">
                    <p data-i18n="pw-welcome-usage"></p>
                    <p data-i18n="pw-welcome-wall"></p>
                    <p data-i18n="pw-welcome-email-note" style="margin-top: 8px;"></p>
                </div>
                <p data-i18n="pw-welcome-upload-info" style="margin-top: 15px;"></p>
                <p data-i18n="pw-welcome-conversion" class="welcome-conversion-text"></p>
            </div>
            <button class="buy-btn welcome-close-btn" id="ack-welcome" data-i18n="pw-close"></button>
        </div>
    </div>

    <div class="modal-overlay" id="diary-modal">
        <div class="modal-content diary-modal-content">
            <span class="modal-close" id="close-diary-modal">&times;</span>
            <div class="modal-title diary-title-header">Madness Log</div>
            <div class="modal-body diary-body-content" id="diary-content"></div>
        </div>
    </div>

    <div class="modal-overlay" id="checkout-modal">
        <div class="modal-content checkout-modal-content">
            <span class="modal-close" id="close-checkout-modal">&times;</span>
            <div class="modal-title checkout-modal-title" data-i18n="pw-confirm"></div>
            <div class="modal-body">
                <div class="checkout-summary-container">
                    <div id="checkout-summary" class="checkout-breakdown"></div>
                    <p id="checkout-total" class="checkout-total-price">€ 0.00</p>
                    <p data-i18n="pw-sign-label" class="sign-label-margin"></p>
                </div>
                <div class="email-input-group">
                    <div class="email-input-wrapper">
                        <input type="email" id="signer-email" placeholder="La tua email (per ricevuta)"
                            data-i18n="[placeholder]pw-email-placeholder" class="email-input">
                        <p class="privacy-text">La tua email sarà usata solo per inviarti la ricevuta e per verificare
                            la proprietà dei pixel. <a href="pages/privacy.php" target="_blank"
                                data-i18n="footer-privacy">Privacy & Cookie Policy</a></p>
                    </div>
                    <div class="email-input-wrapper" style="margin-top: 15px;">
                        <input type="email" id="referral-email" placeholder="Email di chi ti ha suggerito (Referral)"
                            data-i18n="[placeholder]pw-referral-placeholder" class="email-input">
                        <p class="privacy-text" data-i18n="pw-referral-instr">Facoltativo: inserisci l'email di chi ti
                            ha suggerito di donare.</p>
                    </div>
                    <div class="stripe-logo-wrapper">
                        <img src="assets/images/Stripe_logo.svg" alt="Powered by Stripe" class="stripe-logo">
                    </div>
                    <div id="payment-element"></div>
                    <div id="payment-message" class="hidden payment-error-msg"></div>
                    <button class="buy-btn pay-btn-wrapper" id="submit-payment">
                        <span id="button-text">Paga Ora</span>
                        <div class="spinner hidden" id="spinner"></div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="lightbox-overlay" class="lightbox-overlay">
        <span class="lightbox-close">&times;</span>
        <img id="lightbox-img" src="" alt="Full view">
    </div>

    <script src="assets/js/translations.js?v=2.3.2" nonce="<?php echo $nonce; ?>"></script>
    <script src="assets/js/error_modal.js?v=2.3.2" nonce="<?php echo $nonce; ?>"></script>
    <script src="assets/js/pixel-wall.js?v=2.3.2" nonce="<?php echo $nonce; ?>"></script>

    <?php
    if (file_exists('gdpr/banner.php')) {
        include_once 'gdpr/banner.php';
    } elseif (file_exists('../gdpr/banner.php')) {
        include_once '../gdpr/banner.php';
    }
    ?>
    <div class="footer-legal">
        <?php
        require 'gdpr/config.php';
        echo htmlspecialchars($gdpr_company_name) . " - P.IVA " . htmlspecialchars($gdpr_company_vat) . "<br>";
        echo htmlspecialchars($gdpr_company_address) . " | <a href='pages/privacy.php' target='_blank'>Privacy & Cookie Policy</a>";
        ?>
    </div>
</body>

</html>