// --- STATE ---
let currentBrandingLang = 'it';

// --- GLOBAL UTILITIES ---
window.switchTab = (tabId) => {
    const navItems = document.querySelectorAll('.nav-item');
    const tabContents = document.querySelectorAll('.tab-content');
    const targetNav = document.querySelector(`.nav-item[data-tab="${tabId}"]`);
    const targetContent = document.getElementById(tabId);

    if (targetNav && targetContent) {
        // Update Nav
        navItems.forEach(nav => nav.classList.remove('active'));
        targetNav.classList.add('active');

        // Update Content
        tabContents.forEach(content => content.classList.remove('active'));
        targetContent.classList.add('active');

        // Update Hash without jumping
        history.replaceState(null, null, "#" + tabId);

        // Reset scroll position to top
        const contentArea = document.querySelector('.content');
        if (contentArea) contentArea.scrollTop = 0;

        // Trigger loads
        if (tabId === 'overview' && window.loadStats) window.loadStats();
        if (tabId === 'settings' && window.loadSettings) window.loadSettings();
        if (tabId === 'gamification' && window.loadGamification) window.loadGamification();
        if (tabId === 'winners' && window.loadWinners) window.loadWinners();
        if (tabId === 'branding' && window.loadBranding) window.loadBranding();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const navItems = document.querySelectorAll('.nav-item');
    const tabContents = document.querySelectorAll('.tab-content');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    // --- TAB SWITCHING ---
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const tabId = item.getAttribute('data-tab');
            if (tabId) {
                e.preventDefault();
                window.switchTab(tabId);
            }
        });
    });

    // Handle initial hash
    // Handle initial hash
    const handleHash = () => {
        const tabId = window.location.hash.replace('#', '') || 'overview';
        // Small delay to ensure loadStats is registered
        setTimeout(() => window.switchTab(tabId), 50);
    };

    window.addEventListener('hashchange', handleHash);

    // Defer initial load
    setTimeout(handleHash, 100);

    // --- STATS LOADING ---
    window.loadStats = async function () {
        const statsGrid = document.getElementById('dashboard-stats');
        if (!statsGrid) return;

        try {
            const res = await fetch('../api/wall-api.php?type=transactions&stats=1');
            const data = await res.json();

            statsGrid.innerHTML = `
                <div class="stat-card">
                    <span class="stat-label">${t('total_raised_label')}</span>
                    <div class="stat-value">€${data.total_raised.toFixed(2)}</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">${t('gold_found_label')}</span>
                    <div class="stat-value">${data.found_gold}/10</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">${t('silver_found_label')}</span>
                    <div class="stat-value">${data.found_silver}/50</div>
                </div>
                <div class="stat-card">
                    <span class="stat-label">${t('last_contrib_label')}</span>
                    <div class="stat-value" style="font-size: 1rem;">${data.last_contribution ? new Date(data.last_contribution).toLocaleDateString() : 'N/A'}</div>
                </div>
            `;
        } catch (err) {
            statsGrid.innerHTML = `<div class="error">${t('err_stats')}</div>`;
        }
    }

    // --- SETTINGS LOADING ---
    window.loadSettings = async function () {
        const container = document.getElementById('settings-container');
        if (!container) return;

        try {
            // Parallel Fetch
            const [resSettings, resStripe] = await Promise.all([
                fetch('api.php?action=get_settings'),
                fetch('api.php?action=get_stripe_config')
            ]);

            const settings = await resSettings.json();
            const stripeConf = await resStripe.json();

            container.innerHTML = `
                <!-- 1. GENERAL SETTINGS -->
                <form id="settings-form" class="admin-form">
                    <div class="branding-group-title"><i class="fas fa-sliders-h"></i> ${t('settings')}</div>
                    <div class="form-group">
                        <label>${t('grid_width')}</label>
                        <input type="number" name="wall_width" value="${settings.wall.width}">
                    </div>
                    <div class="form-group">
                        <label>${t('grid_height')}</label>
                        <input type="number" name="wall_height" value="${settings.wall.height}">
                    </div>
                    <div class="form-group">
                        <label>${t('land_price')}</label>
                        <input type="number" step="0.01" name="land_rate" value="${settings.pricing.land_rate_cents / 100}">
                    </div>
                    <div class="form-group">
                        <label>${t('ink_price')}</label>
                        <input type="number" step="0.01" name="ink_rate" value="${settings.pricing.ink_rate_cents / 100}">
                    </div>
                    <div class="form-group">
                        <label>${t('maintenance')}</label>
                        <select name="maintenance">
                            <option value="0" ${!settings.maintenance_mode ? 'selected' : ''}>${t('maint_off')}</option>
                            <option value="1" ${settings.maintenance_mode ? 'selected' : ''}>${t('maint_on')}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>${t('preset_load')}</label>
                        <select name="use_preset">
                            <option value="1" ${settings.wall.use_preset ? 'selected' : ''}>${t('preset_on')}</option>
                            <option value="0" ${!settings.wall.use_preset ? 'selected' : ''}>${t('preset_off')}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>${t('preset_pads')}</label>
                        <input type="number" name="preset_padding" value="${settings.wall.preset_padding ?? 4}">
                        <p style="font-size:0.8rem; color:#8b949e; margin-top:5px;">${t('preset_hint')}</p>
                    </div>
                    <button type="submit" class="btn btn-primary">${t('save_settings')}</button>
                </form>
                <!-- 2. AD GRANTS / TRACKING CONFIG -->
                <div style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                    <h3 style="color: var(--text-color); margin-bottom: 15px;">
                        <i class="fas fa-chart-line" style="color: var(--accent);"></i> Google Ad Grants & Analytics
                    </h3>
                    <form id="tracking-form" class="admin-form">
                        <div class="form-group">
                            <label>Google Analytics 4 ID (G-XXXXXX)</label>
                            <input type="text" id="ga4-id" value="${(settings.tracking && settings.tracking.ga4_id) || ''}" placeholder="G-..." style="font-family: monospace;">
                        </div>
                        <div class="form-group">
                            <label>Google Ads Conversion ID (AW-XXXXXX/Label)</label>
                            <input type="text" id="google-ads-id" value="${(settings.tracking && settings.tracking.google_ads_id) || ''}" placeholder="AW-..." style="font-family: monospace;">
                            <small style="color: #666;">Incolla l'ID completo o ID/Label per la conversione.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">SALVA TRACKING</button>
                    </form>
                </div>

                <!-- 3. STRIPE SETTINGS -->
                <form id="stripe-form" class="admin-form" style="margin-top: 30px; border-top: 4px solid var(--accent);">
                    <div class="branding-group-title"><i class="fas fa-credit-card"></i> ${t('stripe_config_title')}</div>
                    
                    <div style="background:rgba(230, 50, 40, 0.1); border:1px solid #ff4d4d; padding:10px; border-radius:4px; margin-bottom:15px; color:#ff4d4d; font-size:0.9rem;">
                        <i class="fas fa-exclamation-triangle"></i> ${t('stripe_warning')}
                    </div>

                    <div class="form-group">
                        <label>${t('stripe_pk_label')}</label>
                        <input type="text" name="publishable_key" value="${stripeConf.publishable_key || ''}" placeholder="pk_live_...">
                    </div>
                    <div class="form-group">
                        <label>${t('stripe_sk_label')}</label>
                        <input type="text" name="secret_key" value="${stripeConf.secret_key || ''}" placeholder="sk_live_... (Hidden)">
                    </div>
                    <div class="form-group">
                        <label>${t('stripe_wh_label')}</label>
                        <input type="text" name="webhook_secret" value="${stripeConf.webhook_secret || ''}" placeholder="whsec_... (Hidden)">
                    </div>

                    <button type="submit" class="btn btn-primary" style="background: #6772e5;">${t('stripe_save')}</button>
                </form>
            `;

            // Handle General Settings
            const settingsForm = document.getElementById('settings-form');
            settingsForm.onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(settingsForm);
                const results = Object.fromEntries(formData.entries());

                const update = {
                    wall: {
                        width: parseInt(results.wall_width),
                        height: parseInt(results.wall_height),
                        use_preset: results.use_preset === "1",
                        preset_padding: parseInt(results.preset_padding) || 0
                    },
                    pricing: {
                        ...settings.pricing,
                        land_rate_cents: Math.round(parseFloat(results.land_rate) * 100),
                        ink_rate_cents: Math.round(parseFloat(results.ink_rate) * 100)
                    },
                    maintenance_mode: results.maintenance === "1"
                };

                const saveRes = await fetch('api.php?action=save_settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify(update)
                });
                const saveData = await saveRes.json();
                if (saveData.success) alert(t('settings_saved'));
                else alert('Error: ' + saveData.error);
            };

            // Handle Stripe Settings
            const stripeForm = document.getElementById('stripe-form');
            stripeForm.onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(stripeForm);
                const results = Object.fromEntries(formData.entries());

                const saveRes = await fetch('api.php?action=save_stripe_config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify(results)
                });
                const saveData = await saveRes.json();
                if (saveData.success) {
                    alert(t('stripe_saved'));
                    // Reload to re-mask keys
                    window.loadSettings();
                } else {
                    alert('Error: ' + saveData.error);
                }
            };

            // Handle Tracking Settings (Added manually)
            const trackingForm = document.getElementById('tracking-form');
            if (trackingForm) {
                trackingForm.onsubmit = async (e) => {
                    e.preventDefault();
                    const ga4 = document.getElementById('ga4-id').value.trim();
                    const ads = document.getElementById('google-ads-id').value.trim();

                    const update = {
                        tracking: {
                            ga4_id: ga4,
                            google_ads_id: ads
                        }
                    };

                    try {
                        const saveRes = await fetch('api.php?action=save_settings', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                            body: JSON.stringify(update)
                        });
                        const saveData = await saveRes.json();
                        if (saveData.success) alert('Tracking ID salvati con successo!');
                        else alert('Errore: ' + saveData.error);
                    } catch (err) {
                        alert('Errore di connessione');
                        console.error(err);
                    }
                };
            }

        } catch (err) {
            container.innerHTML = `<div class="error">${t('error_loading')}</div>`;
            console.error(err);
        }
    };

    // --- TRACKING SAVING ---
    // We attach this globally or inside loadSettings logic because the form is static in index.php but values are dynamic
    // Actually, I added the form to index.php statically. Let's handle the submit event here.
    // MOVED TO loadSettings FUNCTION
    // const trackingForm = document.getElementById('tracking-form');
    // if (trackingForm) {
    //     trackingForm.onsubmit = async (e) => {
    //         e.preventDefault();
    //         const ga4 = document.getElementById('ga4-id').value.trim();
    //         const ads = document.getElementById('google-ads-id').value.trim();

    //         const update = {
    //             tracking: {
    //                 ga4_id: ga4,
    //                 google_ads_id: ads
    //             }
    //         };

    //         // Check CSRF
    //         const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    //         try {
    //             const saveRes = await fetch('api.php?action=save_settings', {
    //                 method: 'POST',
    //                 headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    //                 body: JSON.stringify(update)
    //             });
    //             const saveData = await saveRes.json();
    //             if (saveData.success) alert('Tracking ID salvati con successo!');
    //             else alert('Errore: ' + saveData.error);
    //         } catch (err) {
    //             alert('Errore di connessione');
    //         }
    //     };
    // }

    // --- WINNERS LOADING ---
    window.loadWinners = async function () {
        const container = document.getElementById('winners-list-container');
        if (!container) return;

        try {
            const res = await fetch('../api/wall-api.php?type=winners');
            const data = await res.json();

            let html = `<h3>${t('gold_found_label')}</h3><ul class="admin-list">`;
            data.gold.forEach(w => {
                html += `<li><b>${w.pixel}</b>: ${w.name}</li>`;
            });
            html += `</ul><h3 style="margin-top:20px;">${t('silver_found_label')}</h3><ul class="admin-list">`;
            data.silver.forEach(w => {
                html += `<li><b>${w.pixel}</b>: ${w.name}</li>`;
            });
            html += '</ul>';
            container.innerHTML = html;
        } catch (err) {
            container.innerHTML = `<div class="error">${t('error_loading')}</div>`;
        }
    }

    // --- LANGUAGE SWITCHING ---
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const lang = btn.getAttribute('data-lang');
            if (window.setLanguage) window.setLanguage(lang);
        });
    });

    // --- TOOLS ACTIONS ---
    const btnBackup = document.getElementById('btn-backup-data');
    if (btnBackup) {
        btnBackup.onclick = () => {
            window.location.href = 'api.php?action=system_backup';
        };
    }

    document.getElementById('btn-reset-wall').onclick = async () => {
        if (confirm('SEI SICURO? Questa azione cancellerà TUTTI i dati del wall attuale. Verrà creato un backup automatico.')) {
            const res = await fetch('api.php?action=system_reset', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken }
            });
            const data = await res.json();
            if (data.success) alert('Wall resettato con successo!');
            else alert('Errore: ' + data.error);
        }
    };

    document.getElementById('regenerate-pixels').onclick = async () => {
        const gold = document.getElementById('gold-count').value;
        const silver = document.getElementById('silver-count').value;
        if (confirm(`Rigenerare ${gold} pixel oro e ${silver} pixel argento?`)) {
            const res = await fetch('api.php?action=regenerate_gamification', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ gold_count: gold, silver_count: silver })
            });
            const data = await res.json();
            if (data.success) alert('Locazioni rigenerate con successo!');
            else alert('Errore: ' + data.error);
        }
    };

    // --- GAMIFICATION LOADING ---
    window.loadGamification = async function () {
        // Find container to append/update list
        // We look for a container inside the gamification section
        const section = document.getElementById('gamification');
        if (!section) return;

        // Check if we already have the list container
        let listContainer = document.getElementById('secret-pixels-list');
        if (!listContainer) {
            listContainer = document.createElement('div');
            listContainer.id = 'secret-pixels-list';
            listContainer.style.marginTop = '20px';
            listContainer.style.padding = '15px';
            listContainer.style.background = 'rgba(255,255,255,0.05)';
            listContainer.style.borderRadius = '6px';
            // Insert after the existing form content (admin-form)
            const formDiv = section.querySelector('.admin-form');
            if (formDiv) formDiv.appendChild(listContainer);
        }

        listContainer.innerHTML = '<div class="loading">Caricamento posizioni segrete...</div>';

        try {
            const res = await fetch('api.php?action=get_secret_pixels');
            const data = await res.json();

            if (data.gold || data.silver) {
                let html = '<h3 style="margin-top:0; border-bottom:1px solid #30363d; padding-bottom:10px;">Posizioni Attuali</h3>';

                html += '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">';

                // Gold
                html += '<div>';
                html += `<h4 style="color:#ffd700;">Pixel Oro (${data.gold.length})</h4>`;
                html += '<div style="max-height:200px; overflow-y:auto; font-family:monospace; font-size:0.9rem;">';
                data.gold.forEach(p => {
                    html += `<div>${p}</div>`;
                });
                html += '</div></div>';

                // Silver
                html += '<div>';
                html += `<h4 style="color:#c0c0c0;">Pixel Argento (${data.silver.length})</h4>`;
                html += '<div style="max-height:200px; overflow-y:auto; font-family:monospace; font-size:0.9rem;">';
                data.silver.forEach(p => {
                    html += `<div>${p}</div>`;
                });
                html += '</div></div>';

                html += '</div>';
                listContainer.innerHTML = html;
            } else {
                listContainer.innerHTML = 'Nessuna posizione trovata.';
            }

        } catch (e) {
            listContainer.innerHTML = '<div class="error">Errore caricamento posizioni.</div>';
        }
    };

    const btnCapture = document.getElementById('btn-capture-preset');
    if (btnCapture) {
        btnCapture.onclick = async () => {
            if (confirm('Vuoi salvare lo stato attuale del muro come PRESET? Questo sovrascriverà eventuali preset precedenti.')) {
                const res = await fetch('api.php?action=capture_preset', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken }
                });
                const data = await res.json();
                if (data.success) alert('Preset catturato con successo!');
                else alert('Errore: ' + data.error);
            }
        };
    }

    const btnClearPreset = document.getElementById('btn-clear-preset');
    if (btnClearPreset) {
        btnClearPreset.onclick = async () => {
            if (confirm('Vuoi cancellare il PRESET attuale? Il muro inizierà vuoto (salvo pixel salvati nel DB).')) {
                const res = await fetch('api.php?action=clear_preset', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken }
                });
                const data = await res.json();
                if (data.success) alert('Preset cancellato con successo!');
                else alert('Errore: ' + data.error);
            }
        };
    }

    const btnLoadPreset = document.getElementById('btn-load-preset');
    if (btnLoadPreset) {
        btnLoadPreset.onclick = async () => {
            if (confirm('Vuoi caricare il preset salvato? Questo SOVRASCRIVERÀ lo stato attuale del muro.')) {
                const res = await fetch('api.php?action=load_preset', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken }
                });
                const data = await res.json();
                if (data.success) alert('Preset caricato con successo!');
                else alert('Errore: ' + data.error);
            }
        };
    }

    // --- DONATION LOGIC ---
    const btnLoadDonation = document.getElementById('load-donation-btn');
    if (btnLoadDonation) {
        btnLoadDonation.onclick = () => {
            const container = document.getElementById('donation-container');

            // Visual feedback
            btnLoadDonation.innerHTML = '<span>...</span>';
            btnLoadDonation.style.opacity = '0.7';
            btnLoadDonation.disabled = true;

            // Load Stripe script only on user click
            if (!window._stripeBuyBtnScript) {
                const script = document.createElement('script');
                script.src = 'https://js.stripe.com/v3/buy-button.js';
                script.async = true;
                script.onload = () => {
                    window._stripeBuyBtnScript = true;
                    showStripeComponent();
                };
                document.head.appendChild(script);
            } else {
                showStripeComponent();
            }

            function showStripeComponent() {
                container.innerHTML = `
                    <stripe-buy-button
                        buy-button-id="buy_btn_1SpPILF9KWl6PM6DlsdKpz2m"
                        publishable-key="pk_live_51Ndc8HF9KWl6PM6DrbhJhBjTIbTUF4Q0jgF3Ok9sqepIdeYb4cfz6FX6J1jBYHgAO6vPSHBUBFkMArzlGexHvla100rrpU7GkX"
                    >
                    </stripe-buy-button>
                `;
            }
        };
    }

    // --- BRANDING LOADING ---
    window.setBrandingLang = (lang) => {
        currentBrandingLang = lang;
        window.loadBranding();
    };

    window.loadBranding = async function () {
        const brandingForm = document.getElementById('branding-form');
        if (!brandingForm) return;

        try {
            const res = await fetch('api.php?action=get_branding');
            const allBranding = await res.json();
            const branding = allBranding[currentBrandingLang] || {};
            const styleConfig = allBranding.style_config || {};

            brandingForm.innerHTML = `
                <div class="branding-group" style="border-left: none; padding: 15px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                        <span style="font-weight: bold; color: var(--accent); min-width: 150px;">${t('branding_lang_select')}</span>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn ${currentBrandingLang === 'it' ? 'btn-primary' : 'btn-outline'}" onclick="setBrandingLang('it')" style="width: auto; padding: 8px 15px;">IT</button>
                            <button type="button" class="btn ${currentBrandingLang === 'en' ? 'btn-primary' : 'btn-outline'}" onclick="setBrandingLang('en')" style="width: auto; padding: 8px 15px;">EN</button>
                            <button type="button" class="btn ${currentBrandingLang === 'es' ? 'btn-primary' : 'btn-outline'}" onclick="setBrandingLang('es')" style="width: auto; padding: 8px 15px;">ES</button>
                        </div>
                    </div>
                </div>

                <div class="branding-group">
                    <div class="branding-group-title"><i class="fas fa-globe"></i> ${t('group_general')}</div>
                    <div class="form-group">
                        <label>${t('wall_title_label')}</label>
                        <input type="text" name="wall_title" value="${branding.wall_title || ''}">
                    </div>
                    <div class="form-group">
                        <label>${t('support_email_label')}</label>
                        <input type="email" name="support_email" value="${branding.support_email || ''}">
                    </div>
                </div>

                <div class="branding-group">
                    <div class="branding-group-title"><i class="fas fa-door-open"></i> ${t('group_welcome')}</div>
                    <div class="form-group">
                        <label>${t('welcome_title_label')}</label>
                        <input type="text" name="welcome_title" value="${branding.welcome_title || ''}">
                    </div>
                    <div class="form-group">
                        <label>${t('welcome_text_label')}</label>
                        <textarea name="welcome_text" rows="2">${branding.welcome_text || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label>${t('welcome_details_label')}</label>
                        <textarea name="welcome_details" rows="5">${branding.welcome_details || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label>${t('welcome_upload_label')}</label>
                        <textarea name="welcome_upload" rows="2">${branding.welcome_upload || ''}</textarea>
                    </div>
                </div>

                <div class="branding-group">
                    <div class="branding-group-title"><i class="fas fa-eye"></i> ${t('group_vision')}</div>
                    <div class="form-group">
                        <label>${t('vision_title_label')}</label>
                        <input type="text" name="vision_title" value="${branding.vision_title || ''}">
                    </div>
                    <div class="form-group">
                        <label>${t('vision_text_label')}</label>
                        <input type="text" name="vision_text" value="${branding.vision_text || ''}">
                    </div>
                    <div class="form-group">
                        <label>${t('vision_details_label')}</label>
                        <textarea name="vision_details" rows="5">${branding.vision_details || ''}</textarea>
                    </div>
                    <div class="form-group">
                        <label>${t('vision_image_label')}</label>
                        <input type="text" name="vision_image" value="${branding.vision_image || ''}">
                        <p style="font-size:0.8rem; color:#8b949e; margin-top:5px;">Es: assets/images/real_wall.png</p>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>${t('vision_map_text_label')}</label>
                            <input type="text" name="vision_map_text" value="${branding.vision_map_text || ''}">
                        </div>
                        <div class="form-group">
                            <label>${t('vision_map_url_label')}</label>
                            <input type="text" name="vision_map_url" value="${branding.vision_map_url || ''}">
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>${t('vision_tourism_text_label')}</label>
                            <input type="text" name="vision_tourism_text" value="${branding.vision_tourism_text || ''}">
                        </div>
                        <div class="form-group">
                            <label>${t('vision_tourism_url_label')}</label>
                            <input type="text" name="vision_tourism_url" value="${branding.vision_tourism_url || ''}">
                        </div>
                    </div>
                </div>

                <div class="branding-group">
                    <div class="branding-group-title"><i class="fas fa-trophy"></i> ${t('group_wordcloud')}</div>
                    <div class="form-group">
                        <label>${t('wordcloud_title_label')}</label>
                        <input type="text" name="wordcloud_title" value="${branding.wordcloud_title || ''}">
                    </div>
                    <div class="form-group">
                        <label>${t('wordcloud_text_label')}</label>
                        <textarea name="wordcloud_text" rows="2">${branding.wordcloud_text || ''}</textarea>
                    </div>
                </div>

                <div class="branding-group">
                    <div class="branding-group-title"><i class="fas fa-info-circle"></i> ${t('group_pricing')}</div>
                    <div class="form-group">
                        <label>${t('pricing_email_text_label')}</label>
                        <textarea name="pricing_email_text" rows="2">${branding.pricing_email_text || ''}</textarea>
                    </div>
                </div>

                <div class="branding-group">
                    <div class="branding-group-title"><i class="fas fa-palette"></i> ${t('group_style')}</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label>${t('modal_bg_color')}</label>
                            <input type="color" name="style_modal_bg_color" value="${styleConfig.modal_bg_color || '#111111'}" style="height:50px;">
                        </div>
                        <div class="form-group">
                            <label>${t('modal_text_color')}</label>
                            <input type="color" name="style_modal_text_color" value="${styleConfig.modal_text_color || '#ffffff'}" style="height:50px;">
                        </div>
                        <div class="form-group">
                            <label>${t('modal_border_color')}</label>
                            <input type="color" name="style_modal_border_color" value="${styleConfig.modal_border_color || '#1de9b6'}" style="height:50px;">
                        </div>
                        <div class="form-group">
                            <label>${t('modal_radius')}</label>
                            <input type="number" name="style_modal_radius" value="${styleConfig.modal_radius || 20}" placeholder="20">
                            <p style="font-size:0.8rem; color:#8b949e; margin-top:5px;">px</p>
                        </div>
                        <div class="form-group">
                            <label>${t('modal_opacity')}</label>
                            <input type="number" name="style_modal_opacity" value="${styleConfig.modal_opacity || 0.95}" step="0.05" min="0.1" max="1.0" placeholder="0.95">
                        </div>
                        <div class="form-group">
                            <label>${t('btn_primary_bg')}</label>
                            <input type="color" name="style_btn_primary_bg" value="${styleConfig.btn_primary_bg || '#e63228'}" style="height:50px;">
                        </div>
                        <div class="form-group">
                            <label>${t('btn_primary_text')}</label>
                            <input type="color" name="style_btn_primary_text" value="${styleConfig.btn_primary_text || '#ffffff'}" style="height:50px;">
                        </div>

                        <div class="form-group">
                            <label>${t('box_vision_bg')}</label>
                            <input type="color" name="style_box_vision_bg" value="${styleConfig.box_vision_bg || '#823c8c'}" style="height:50px;">
                        </div>
                        <div class="form-group">
                            <label>${t('box_wall_bg')}</label>
                            <input type="color" name="style_box_wall_bg" value="${styleConfig.box_wall_bg || '#823c8c'}" style="height:50px;">
                        </div>
                    </div>
                    
                    <div class="branding-group-title" style="margin-top:20px; border-top:1px solid #30363d; padding-top:15px;"><i class="fas fa-heading"></i> ${t('style_title_colors')}</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                         <div class="form-group">
                            <label>Col 1 (0%)</label>
                            <input type="color" name="style_title_col_1" value="${styleConfig.title_col_1 || '#ffe600'}" style="height:50px;">
                        </div>
                        <div class="form-group">
                            <label>Col 2 (33%)</label>
                            <input type="color" name="style_title_col_2" value="${styleConfig.title_col_2 || '#f09100'}" style="height:50px;">
                        </div>
                        <div class="form-group">
                            <label>Col 3 (66%)</label>
                            <input type="color" name="style_title_col_3" value="${styleConfig.title_col_3 || '#e63228'}" style="height:50px;">
                        </div>
                        <div class="form-group">
                            <label>Col 4 (100%)</label>
                            <input type="color" name="style_title_col_4" value="${styleConfig.title_col_4 || '#823c8c'}" style="height:50px;">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">${t('save_branding')} [${currentBrandingLang.toUpperCase()}]</button>
            `;

            brandingForm.onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(brandingForm);

                const langPayload = {};
                const stylePayload = {};

                for (let [key, value] of formData.entries()) {
                    if (key.startsWith('style_')) {
                        // Remove prefix
                        const cleanKey = key.replace('style_', '');
                        stylePayload[cleanKey] = value;
                    } else {
                        langPayload[key] = value;
                    }
                }

                // Wrap in current language key for text, separate for styles
                const payload = {};
                payload[currentBrandingLang] = langPayload;
                payload['style_config'] = stylePayload;

                const saveRes = await fetch('api.php?action=save_branding', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify(payload)
                });

                const saveData = await saveRes.json();
                if (saveData.success) {
                    alert(t('branding_saved'));
                    window.loadBranding();
                } else {
                    alert('Errore: ' + saveData.error);
                }
            };
        } catch (err) {
            brandingForm.innerHTML = `<div class="error">${t('error_loading')}</div>`;
        }
    }
});
