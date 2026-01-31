/**
 * GDPR Consent Manager
 * Handles cookie consent, Google Consent Mode V2, and UI triggers.
 */
class ConsentManager {
    constructor() {
        this.config = window.MadnessGDPR || {
            ga4_id: '',
            cookie_duration: 180,
            default_lang: 'en',
            privacy_url: 'privacy.php'
        };

        this.cookieName = 'madness_gdpr_consent';
        this.cookieDuration = this.config.cookie_duration; // days
        this.consent = this.getStoredConsent();

        // ONLY initialize Google Consent Mode if consent already exists
        // This satisfies strict "Prior Consent" scanners (Basic Consent Mode)
        if (this.consent) {
            this.initGoogleConsentMode();
            this.activateScripts(this.consent);
        }

        this.currentLang = this.detectLanguage();
    }

    // Generate UUIDv4 for Proof of Consent
    generateUUID() {
        return "10000000-1000-4000-8000-100000000000".replace(/[018]/g, c =>
            (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
        );
    }

    // Initialize Google Consent Mode Defaults
    initGoogleConsentMode() {
        if (window.gtag) return; // Already initialized

        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        window.gtag = gtag;

        if (!this.consent) {
            // Default state: Denied
            gtag('consent', 'default', {
                'ad_storage': 'denied',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied',
                'analytics_storage': 'denied',
                'wait_for_update': 500
            });
        } else {
            // Restore saved state
            this.updateGCM(this.consent);
        }

        gtag('js', new Date());

        // If consent already exists for analytics, load the script immediately
        if (this.consent && this.consent.analytics) {
            this.loadGtagScript();
        }
    }

    // Actual script injection (Only after consent or if already granted)
    loadGtagScript() {
        if (!this.config.ga4_id || document.getElementById('gdpr-gtag-script')) return;

        const script = document.createElement('script');
        script.id = 'gdpr-gtag-script';
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${this.config.ga4_id}`;
        document.head.appendChild(script);

        window.gtag('config', this.config.ga4_id, {
            'anonymize_ip': true,
            'cookie_flags': 'SameSite=Lax;Secure'
        });
    }

    // Sync Checkboxes with current consent state
    syncUI() {
        if (!this.consent) return;

        const chkAnalytics = document.getElementById('chk-analytics');
        const chkMarketing = document.getElementById('chk-marketing');

        if (chkAnalytics) chkAnalytics.checked = this.consent.analytics;
        if (chkMarketing) chkMarketing.checked = this.consent.marketing;
    }

    // Check if user has already acted, or if a specific category is granted
    hasConsent(category = null) {
        if (!this.consent) return false;
        if (!category) return true;
        return !!this.consent[category];
    }

    getStoredConsent() {
        const nameEQ = this.cookieName + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) {
                try {
                    return JSON.parse(decodeURIComponent(c.substring(nameEQ.length, c.length)));
                } catch (e) {
                    return null;
                }
            }
        }
        return null; // No consent found
    }

    // Save consent to cookie and update GCM
    saveConsent(preferences) {
        this.consent = preferences;

        // Ensure Consent ID exists
        if (!this.consent.consent_id) {
            this.consent.consent_id = this.generateUUID();
        }

        const json = encodeURIComponent(JSON.stringify(this.consent));

        let expires = "";
        const date = new Date();
        date.setTime(date.getTime() + (this.cookieDuration * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();

        document.cookie = this.cookieName + "=" + json + expires + "; path=/; SameSite=Lax; Secure";

        // Ensure GCM is initialized before update
        this.initGoogleConsentMode();
        this.updateGCM(preferences);
        this.activateScripts(preferences); // Activate generic scripts
        this.sendConsentLog(this.consent); // Send Proof of Consent

        this.syncUI(); // Update UI to reflect new state
        this.hideBanner();

        // Dispatch event for other scripts
        window.dispatchEvent(new CustomEvent('gdpr-consent-updated', { detail: preferences }));
    }

    // Send Log to Server
    sendConsentLog(prefs) {
        fetch(window.GDPR_ROOT + 'log_consent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                consent_id: prefs.consent_id,
                preferences: prefs
            })
        }).catch(err => console.error("GDPR Log Error:", err));
    }

    // Generic Script Blocker
    // Activates scripts with type="text/plain" and data-category="analytics|marketing"
    activateScripts(prefs) {
        const categories = [];
        if (prefs.analytics) categories.push('analytics');
        if (prefs.marketing) categories.push('marketing');

        categories.forEach(cat => {
            const scripts = document.querySelectorAll(`script[type="text/plain"][data-category="${cat}"]`);
            scripts.forEach(script => {
                // Create a replacement script
                const newScript = document.createElement('script');
                newScript.type = 'text/javascript';

                if (script.src) {
                    newScript.src = script.src;
                    newScript.async = script.async;
                    newScript.defer = script.defer;
                } else {
                    newScript.innerHTML = script.innerHTML;
                }

                // Copy other attributes (e.g. ID, class)
                Array.from(script.attributes).forEach(attr => {
                    if (attr.name !== 'type' && attr.name !== 'data-category') {
                        newScript.setAttribute(attr.name, attr.value);
                    }
                });

                // Replace
                script.parentNode.replaceChild(newScript, script);
            });
        });
    }

    // Map internal categories to GCM types
    updateGCM(prefs) {
        const gcmState = {
            'ad_storage': prefs.marketing ? 'granted' : 'denied',
            'ad_user_data': prefs.marketing ? 'granted' : 'denied',
            'ad_personalization': prefs.marketing ? 'granted' : 'denied',
            'analytics_storage': prefs.analytics ? 'granted' : 'denied'
        };

        window.gtag('consent', 'update', gcmState);

        // If analytics granted, ensure script is loaded
        if (prefs.analytics) {
            this.loadGtagScript();
        }

        console.log("GCM Updated:", gcmState);
    }

    acceptAll() {
        this.saveConsent({
            necessary: true,
            analytics: true,
            marketing: true
        });
    }

    rejectAll() {
        this.saveConsent({
            necessary: true,
            analytics: false,
            marketing: false
        });
    }

    // UI Helpers
    showBanner() {
        const banner = document.getElementById('gdpr-banner');
        if (banner) banner.style.display = 'block';

        // Hide floating button when banner is open
        const floatBtn = document.getElementById('gdpr-floating-btn');
        if (floatBtn) floatBtn.style.display = 'none';
    }

    hideBanner() {
        const banner = document.getElementById('gdpr-banner');
        if (banner) banner.style.display = 'none';

        // Also close modal if open
        const modal = document.getElementById('gdpr-modal-overlay');
        if (modal) modal.style.display = 'none';

        // Show floating button
        const floatBtn = document.getElementById('gdpr-floating-btn');
        if (floatBtn) floatBtn.style.display = 'flex';
    }

    detectLanguage() {
        // 1. Check local override (site-wide key or gdpr specific)
        const stored = localStorage.getItem('mr_lang') || localStorage.getItem('gdpr_lang');
        if (stored) return stored;

        // 2. Prepare available languages
        const available = window.gdprTranslations ? Object.keys(window.gdprTranslations) : [];
        if (available.length === 0) return 'en'; // Extreme fallback

        // 3. Detect browser language
        const browserLang = (navigator.language || navigator.userLanguage || '').toLowerCase().split('-')[0];

        // 4. Match against available (only those enabled and sent to window.gdprTranslations)
        if (available.includes(browserLang)) {
            return browserLang;
        }

        // 5. Global fallback to the one set in Admin
        return (window.MadnessGDPR && window.MadnessGDPR.default_lang) || available[0] || 'en';
    }

    setLanguage(lang) {
        this.currentLang = lang;

        // Re-render Texts
        if (!window.gdprTranslations) return;
        const t = window.gdprTranslations[lang] || window.gdprTranslations['en'];

        // Merge Custom Texts from Config (Override only if not empty)
        const custom = window.MadnessGDPR && window.MadnessGDPR.texts ? window.MadnessGDPR.texts[lang] : null;
        if (custom) {
            if (custom.title && custom.title.trim() !== '') t.banner_title = custom.title;
            if (custom.description && custom.description.trim() !== '') t.banner_text = custom.description;
        }

        // Apply dynamic placeholders (e.g. {{privacy_url}})
        const privacy_url = this.config.privacy_url || 'privacy.php';
        const replacePlaceholders = (str) => {
            if (typeof str !== 'string') return str;
            return str.replace(/{{privacy_url}}/g, privacy_url);
        };

        // Helper to set text
        const setText = (id, text, isHtml = false) => {
            const el = document.getElementById(id);
            if (el) {
                if (isHtml) el.innerHTML = text;
                else el.textContent = text;
            }
        };

        // Banner Texts
        setText('gdpr-title', replacePlaceholders(t.banner_title));
        setText('gdpr-text', replacePlaceholders(t.banner_text), true);
        setText('btn-accept', t.btn_accept);
        setText('btn-reject', t.btn_reject);
        setText('btn-customize', t.btn_customize);

        // Modal Texts
        setText('modal-title', replacePlaceholders(t.modal_title));
        setText('modal-intro', replacePlaceholders(t.modal_intro), true); // HTML for link
        setText('cat-necessary', t.cat_necessary);
        setText('desc-necessary', t.desc_necessary);
        setText('cat-analytics', t.cat_analytics);
        setText('desc-analytics', t.desc_analytics);
        setText('cat-marketing', t.cat_marketing);
        setText('desc-marketing', t.desc_marketing);
        setText('btn-save-prefs', t.btn_save);
    }
}

// Global Instance
window.ConsentManager = new ConsentManager();

// Init Logic when DOM Ready
document.addEventListener('DOMContentLoaded', () => {
    // Inject Text based on Language
    const lang = window.ConsentManager.currentLang; // 'it', 'en', 'es'
    if (!window.gdprTranslations) {
        console.error("GDPR Translations not loaded!");
        return;
    }
    const t = window.gdprTranslations[lang] || window.gdprTranslations['en'];

    // Merge Custom Texts from Config
    const custom = window.MadnessGDPR && window.MadnessGDPR.texts ? window.MadnessGDPR.texts[lang] : null;
    if (custom) {
        if (custom.title) t.banner_title = custom.title;
        if (custom.description) t.banner_text = custom.description;
    }

    // Apply dynamic placeholders
    const privacy_url = window.ConsentManager.config.privacy_url || 'privacy.php';
    const replacePlaceholders = (str) => {
        if (typeof str !== 'string') return str;
        return str.replace(/{{privacy_url}}/g, privacy_url);
    };

    // Helper to set text
    const setText = (id, text, isHtml = false) => {
        const el = document.getElementById(id);
        if (el) {
            if (isHtml) el.innerHTML = text;
            else el.textContent = text;
        }
    };

    // Banner Texts
    setText('gdpr-title', replacePlaceholders(t.banner_title));
    setText('gdpr-text', replacePlaceholders(t.banner_text), true); // Allow HTML for link
    setText('btn-accept', t.btn_accept);
    setText('btn-reject', t.btn_reject);
    setText('btn-customize', t.btn_customize); // Link or button

    // Modal Texts
    setText('modal-title', replacePlaceholders(t.modal_title));
    setText('modal-intro', replacePlaceholders(t.modal_intro), true); // HTML for link
    setText('cat-necessary', t.cat_necessary);
    setText('desc-necessary', t.desc_necessary);
    setText('cat-analytics', t.cat_analytics);
    setText('desc-analytics', t.desc_analytics);
    setText('cat-marketing', t.cat_marketing);
    setText('desc-marketing', t.desc_marketing);
    setText('btn-save-prefs', t.btn_save);

    // Event Listeners
    document.getElementById('btn-accept').addEventListener('click', () => window.ConsentManager.acceptAll());
    document.getElementById('btn-reject').addEventListener('click', () => window.ConsentManager.rejectAll());

    // Customize / Modal
    const modal = document.getElementById('gdpr-modal-overlay');
    const openModalBtn = document.getElementById('btn-customize');
    const floatBtn = document.getElementById('gdpr-floating-btn');
    const closeModalBtn = document.getElementById('close-modal');
    const savePrefsBtn = document.getElementById('btn-save-prefs');

    if (openModalBtn) {
        openModalBtn.addEventListener('click', (e) => {
            e.preventDefault();
            window.ConsentManager.syncUI(); // Sync before showing
            modal.style.display = 'flex';
            if (floatBtn) floatBtn.style.display = 'none';
        });
    }

    if (floatBtn) {
        floatBtn.addEventListener('click', () => {
            window.ConsentManager.syncUI(); // Sync before showing
            modal.style.display = 'flex';
            floatBtn.style.display = 'none';
        });
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            modal.style.display = 'none';
            // If consent exists, show floating button again
            if (window.ConsentManager.hasConsent()) {
                floatBtn.style.display = 'flex';
            } else {
                // If closed without consent (e.g. via X), maybe show banner? 
                // Currently X just closes modal. Let's assume banner is behind it or we return to banner state?
                // For now, simple close. The banner should still be visible if it wasn't hidden by saveConsent.
                // Actually, if we opened modal from Banner, Banner stays open behind?
                // Design choice: Modal overlays everything.
            }
        });
    }

    if (savePrefsBtn) {
        savePrefsBtn.addEventListener('click', () => {
            const analytics = document.getElementById('chk-analytics').checked;
            const marketing = document.getElementById('chk-marketing').checked;

            window.ConsentManager.saveConsent({
                necessary: true,
                analytics: analytics,
                marketing: marketing
            });
        });
    }

    // Show banner if no consent, otherwise show floating button
    if (!window.ConsentManager.hasConsent()) {
        window.ConsentManager.showBanner();
    } else {
        const floatBtn = document.getElementById('gdpr-floating-btn');
        if (floatBtn) floatBtn.style.display = 'flex';
    }
});
