/** Pixel Wall v2.3.0 */
class PixelWall {
    constructor() {
        this.canvas = document.getElementById('pixel-canvas');
        // Removed alpha: false to prevent black screen issues on some mobile browsers
        this.ctx = this.canvas.getContext('2d');
        this.wrapper = document.getElementById('canvas-wrapper');

        // Configuration
        this.gridWidth = 1000;
        this.gridHeight = 400;

        // State
        this.scale = 1.0;
        this.translateX = 0;
        this.translateY = 0;
        this.isPanning = false;
        this.isDrawing = false;
        this.activeColor = '#ff4d4d';
        this.activeTool = 'pan'; // 'draw', 'pan', 'erase'
        this.isDimmed = false;

        this.lastX = 0;
        this.lastY = 0;
        this.lastPinchDistance = 0;
        this.isPinching = false;

        // Pixel Data
        this.confirmedMap = new Map(); // key: "x,y", value: color (PERMANENT WALL)
        this.pixelMap = new Map();     // key: "x,y", value: color (USER WORK-IN-PROGRESS)

        this.history = []; // stack of actions for undo
        this.currentStroke = [];
        this.boundingBox = null;
        this.floatingImage = null;
        this.floatingPos = { x: 0, y: 0 };
        this.winners = { gold: [], silver: [] }; // Special pixels found by users
        this.hasLoggedDrawing = false; // Tracking flag for session stats

        this.init();
    }

    logEvent(eventName) {
        // Send a background signal to the tracker with the event name
        // Using a more robust path relative to current location
        const trackerPath = '../analytics/tracker.php';

        fetch(trackerPath, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                page: 'Pixel Wall',
                event: eventName,
                is_ad: window.location.search.includes('gclid'),
                lang: document.documentElement.lang || 'it'
            })
        })
            .then(res => res.json())
            .then(data => console.log(`PixelWall Tracking: ${eventName}`, data))
            .catch(e => console.warn('PixelWall Tracking failed:', e));
    }

    async init() {
        await this.loadBranding();
        await this.loadSettings();
        this.resize();
        await this.loadData();

        this.resetView(); // Center and fit on load

        // Mobile Fix: Trigger another resize/reset shortly after to catch layout shifts (URL bar)
        setTimeout(() => {
            this.resize();
            this.resetView();
        }, 500);

        this.attachEvents();
        this.updateStats();

        // Start animation loop for pulsing highlights
        this.startAnimation();

        // Handle window resize
        window.addEventListener('resize', () => {
            this.resize();
            this.resetView(); // Keep centered on resize
        });
    }

    resize() {
        // Fallback to window dimensions if wrapper is not ready (prevents 0x0 canvas)
        const w = this.wrapper.clientWidth || window.innerWidth;
        const h = this.wrapper.clientHeight || window.innerHeight;

        // Set canvas internal dimensions to match display size (High DPI)
        this.canvas.width = w * window.devicePixelRatio;
        this.canvas.height = h * window.devicePixelRatio;

        // CRITICAL: Force CSS size to match logical size to prevent browser scaling
        this.canvas.style.width = `${w}px`;
        this.canvas.style.height = `${h}px`;

        this.ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

        // Disable image smoothing for pixel-perfect drawing
        this.ctx.imageSmoothingEnabled = false;
        this.ctx.mozImageSmoothingEnabled = false;
        this.ctx.webkitImageSmoothingEnabled = false;

        this.isMobile = w < 768;
    }

    async loadSettings() {
        try {
            const res = await fetch('api/wall-api.php?type=settings');
            if (res.ok) {
                const settings = await res.json();
                this.settings = settings;
                this.gridWidth = settings.wall.width || 1000;
                this.gridHeight = settings.wall.height || 400;
                this.isMaintenance = settings.maintenance_mode || false;

                if (this.isMaintenance) {
                    console.warn("PIXEL WALL: Maintenance Mode Active");
                    // Optionally disable drawing
                    this.activeTool = 'pan';
                }

                // Re-apply branding placeholders with real dimensions
                this.applyBranding();

                // Update UI with new settings
                if (window.setLanguage) {
                    const currentLang = document.documentElement.lang || 'it';
                    window.setLanguage(currentLang);
                }

                // FIX: Ensure global ADS_ID is available for tracking
                if (this.settings.tracking && this.settings.tracking.google_ads_id) {
                    window.ADS_ID = this.settings.tracking.google_ads_id;
                }
            }
        } catch (e) {
            console.error("Error loading settings", e);
        }

        // Initialize Cache Canvas after settings are loaded
        this.cacheCanvas = document.createElement('canvas');
        this.cacheCanvas.width = this.gridWidth;
        this.cacheCanvas.height = this.gridHeight;
        this.cacheCtx = this.cacheCanvas.getContext('2d', { alpha: false });
        this.cacheCtx.fillStyle = '#ffffff';
        this.cacheCtx.fillRect(0, 0, this.gridWidth, this.gridHeight);
    }

    async loadBranding() {
        try {
            const res = await fetch('data/custom_branding.json');
            if (res.ok) {
                this.allBranding = await res.json();
                this.applyBranding();
            }
        } catch (err) {
            console.error("Error loading branding:", err);
        }
    }

    applyBranding() {
        const lang = document.documentElement.lang || 'it';
        const branding = (this.allBranding && this.allBranding[lang]) ? this.allBranding[lang] : (this.allBranding ? this.allBranding['it'] : null);

        if (!branding) return;

        // Helper to replace placeholders
        const applyPlaceholders = (text) => {
            if (!text) return text;
            const replacements = {
                wall_width_m: (this.gridWidth / 100).toFixed(0),
                wall_height_m: (this.gridHeight / 100).toFixed(0),
                support_email: branding.support_email || 'info@madnessrepublic.com'
            };
            Object.keys(replacements).forEach(key => {
                text = text.replace(new RegExp(`{${key}}`, 'g'), replacements[key]);
            });
            return text;
        };

        // Apply branding to DOM
        if (branding.wall_title) {
            document.title = branding.wall_title;
            const canvasTitle = document.querySelector('[data-i18n="canvas-title"]');
            if (canvasTitle) canvasTitle.innerText = branding.wall_title;
        }

        if (branding.welcome_title) {
            const welcomeTitle = document.querySelector('[data-i18n="pw-welcome-title"]');
            if (welcomeTitle) welcomeTitle.innerText = branding.welcome_title;
        }

        if (branding.welcome_text) {
            const welcomeIntro = document.querySelector('[data-i18n="pw-welcome-intro"]');
            if (welcomeIntro) welcomeIntro.innerText = branding.welcome_text;
        }

        if (branding.welcome_details) {
            const welcomeDetails = document.getElementById('welcome-details-container');
            if (welcomeDetails) welcomeDetails.innerHTML = applyPlaceholders(branding.welcome_details);
        }

        if (branding.welcome_upload) {
            const uploadInfo = document.querySelector('[data-i18n="pw-welcome-upload-info"]');
            if (uploadInfo) uploadInfo.innerHTML = applyPlaceholders(branding.welcome_upload);
        }

        if (branding.vision_title) {
            const vTitle = document.querySelector('[data-i18n="pw-modal-vision-title"]');
            if (vTitle) vTitle.innerText = branding.vision_title;
            const vLabel = document.querySelector('[data-i18n="pw-mission-label"]');
            if (vLabel) vLabel.innerText = branding.vision_title;
        }

        if (branding.vision_text) {
            const vP1 = document.querySelector('[data-i18n="pw-modal-vision-p1"]');
            if (vP1) vP1.innerText = applyPlaceholders(branding.vision_text);
            const vHome = document.querySelector('[data-i18n="pw-mission-text"]');
            if (vHome) vHome.innerText = branding.vision_text;
        }

        if (branding.vision_details) {
            const visionDetails = document.getElementById('vision-details-container');
            if (visionDetails) visionDetails.innerHTML = applyPlaceholders(branding.vision_details);
        }

        if (branding.vision_image) {
            const vImg = document.querySelector('.mission-img');
            if (vImg) vImg.src = branding.vision_image;
        }

        if (branding.vision_map_url) {
            const vMap = document.querySelector('.mission-map-link');
            if (vMap) {
                vMap.href = branding.vision_map_url;
                if (branding.vision_map_text) vMap.innerText = branding.vision_map_text;
            }
        }

        if (branding.vision_tourism_url) {
            const vTour = document.querySelector('.mission-tourism-link');
            if (vTour) {
                vTour.href = branding.vision_tourism_url;
                if (branding.vision_tourism_text) vTour.innerText = branding.vision_tourism_text;
            }
        }

        if (branding.support_email) {
            const supportLinks = document.querySelectorAll('.support-link-wrapper');
            supportLinks.forEach(el => {
                el.innerHTML = `<a href="mailto:${branding.support_email}" style="color:#1de9b6;">${branding.support_email}</a>`;
            });
        }

        if (branding.wordcloud_title) {
            const wcp = document.querySelector('[data-i18n="pw-wall-text"]');
            if (wcp) wcp.innerText = branding.wordcloud_title;
        }

        if (branding.wordcloud_text) {
            const wci = document.querySelector('[data-i18n="pw-wall-intro"]');
            if (wci) wci.innerText = applyPlaceholders(branding.wordcloud_text);
        }
    }

    async loadData() {
        this.serverLoadedSuccess = false;
        this.presetMap = new Map();

        // 0. Load PRESET (Default Locked Pixels) if enabled
        if (this.settings && this.settings.wall && this.settings.wall.use_preset) {
            try {
                const res = await fetch('data/wall_preset.json');
                if (res.ok) {
                    const data = await res.json();
                    Object.keys(data).forEach(key => this.presetMap.set(key, data[key]));
                }
            } catch (e) {
                console.error("Error loading wall_preset.json", e);
            }
        }

        // OPTIMIZATION: Pre-calculate forbidden border (dynamic from settings, default 4)
        this.forbiddenSet = new Set();
        if (this.presetMap.size > 0) {
            const border = (this.settings && this.settings.wall && this.settings.wall.preset_padding !== undefined)
                ? parseInt(this.settings.wall.preset_padding)
                : 4;
            this.presetMap.forEach((_, key) => {
                const [px, py] = key.split(',').map(Number);
                for (let dx = -border; dx <= border; dx++) {
                    for (let dy = -border; dy <= border; dy++) {
                        this.forbiddenSet.add(`${px + dx},${py + dy}`);
                    }
                }
            });
        }

        // 1. Load Permanent Wall from SERVER API (PHP for Aruba Hosting)
        let apiUrl = 'api/wall-api.php';

        try {
            const response = await fetch(apiUrl);
            if (response.ok) {
                this.serverLoadedSuccess = true;
                const data = await response.json();
                Object.keys(data).forEach(key => this.confirmedMap.set(key, data[key]));
            }
        } catch (e) {
            console.error("Error loading wall data (PHP API expected). Fallback to local storage if available.");
            const wallSaved = localStorage.getItem('madness_wall_confirmed');
            if (wallSaved) {
                const data = JSON.parse(wallSaved);
                Object.keys(data).forEach(key => this.confirmedMap.set(key, data[key]));
            }
        }

        // 2. Load current drafting session from LocalStorage
        if (!localStorage.getItem('graphic_v6_seen')) {
            localStorage.removeItem('madness_pixels');
            localStorage.removeItem('madness_wall_confirmed');
            localStorage.setItem('graphic_v6_seen', 'true');
            this.pixelMap.clear();
            // If we nuked the confirmed cache, we should also clear the confirmedMap in memory if it was loaded from it
            // ensuring a fresh start.
            if (!this.serverLoadedSuccess) {
                this.confirmedMap.clear();
            }
        } else {
            const sessSaved = localStorage.getItem('madness_pixels');
            if (sessSaved) {
                const data = JSON.parse(sessSaved);
                Object.keys(data).forEach(key => this.pixelMap.set(key, data[key]));
            }
        }

        this.updateGlobalStats(); // Fetch global stats for HUD

        // 3. Fetch found special pixels (Winners coordinates for animation)
        try {
            const winRes = await fetch('api/wall-api.php?type=winners');
            if (winRes.ok) {
                this.winners = await winRes.json();
                // Build lookup map for clicks
                this.winnerMap = new Map();
                if (this.winners.gold) this.winners.gold.forEach(w => this.winnerMap.set(w.pixel, { name: w.name, type: 'gold' }));
                if (this.winners.silver) this.winners.silver.forEach(w => this.winnerMap.set(w.pixel, { name: w.name, type: 'silver' }));
            }
        } catch (e) {
            console.error("Error loading winners coordinates", e);
        }

        this.updateCache(); // Build the cache ONCE
        this.render();
        this.updateStats();

        // 4. Handle Stripe Redirect Success
        await this.checkStripeRedirect();

    }

    updateCache() {
        // Redraw only the permanent confirmed pixels to the offscreen canvas
        this.cacheCtx.fillStyle = '#ffffff';
        this.cacheCtx.fillRect(0, 0, this.gridWidth, this.gridHeight);

        this.confirmedMap.forEach((color, key) => {
            const [x, y] = key.split(',').map(Number);
            this.cacheCtx.fillStyle = color;
            this.cacheCtx.fillRect(x, y, 1, 1);
        });
    }



    saveSession() {
        const obj = Object.fromEntries(this.pixelMap);
        localStorage.setItem('madness_pixels', JSON.stringify(obj));
    }

    saveConfirmed() {
        const obj = Object.fromEntries(this.confirmedMap);
        localStorage.setItem('madness_wall_confirmed', JSON.stringify(obj));
    }

    startAnimation() {
        if (this.isAnimating) return;
        this.isAnimating = true;

        const animate = () => {
            // Only re-render if we have winners coordinates to pulse
            if (this.winners && (this.winners.gold?.length > 0 || this.winners.silver?.length > 0)) {
                this.render();
            }
            requestAnimationFrame(animate);
        };
        requestAnimationFrame(animate);
    }

    attachEvents() {
        // Mouse/Touch for Drawing & Panning
        // Mouse Events
        this.wrapper.addEventListener('mousedown', (e) => this.handleStart(e));
        window.addEventListener('mousemove', (e) => this.handleMove(e));
        window.addEventListener('mouseup', () => this.handleEnd());

        // Touch Events
        this.wrapper.addEventListener('touchstart', (e) => {
            // Only capture touch if it's on the CANVAS itself, letting UI buttons work
            if (e.target === this.canvas) {
                if (e.touches.length === 1) {
                    e.preventDefault(); // Prevent scrolling/zooming while drawing
                    const touch = e.touches[0];
                    this.handleStart({ clientX: touch.clientX, clientY: touch.clientY, preventDefault: () => { } });
                } else if (e.touches.length === 2 && this.activeTool === 'pan') {
                    // PINCH ZOOM IN PAN MODE
                    e.preventDefault();
                    this.isPinching = true;
                    this.lastPinchDistance = this.getDistance(e.touches[0], e.touches[1]);
                }
            }
        }, { passive: false });

        window.addEventListener('touchmove', (e) => {
            // We only care about drag events initiated on the canvas
            if (e.target === this.canvas) {
                if (e.touches.length === 1) {
                    e.preventDefault(); // Prevent scrolling
                    const touch = e.touches[0];
                    this.handleMove({ clientX: touch.clientX, clientY: touch.clientY, preventDefault: () => { } });
                } else if (e.touches.length === 2 && this.isPinching) {
                    // PINCH ZOOM LOGIC
                    e.preventDefault();
                    const dist = this.getDistance(e.touches[0], e.touches[1]);
                    const mid = this.getMidpoint(e.touches[0], e.touches[1]);

                    if (this.lastPinchDistance > 0) {
                        const factor = dist / this.lastPinchDistance;
                        // Limit factor to avoid extreme jumps
                        const safeFactor = Math.max(0.8, Math.min(factor, 1.2));
                        this.applyZoom(safeFactor, mid.x, mid.y);
                    }
                    this.lastPinchDistance = dist;
                }
            }
        }, { passive: false });

        window.addEventListener('touchend', () => {
            this.isPinching = false;
            this.lastPinchDistance = 0;
            this.handleEnd();
        });

        // Zoom
        this.wrapper.addEventListener('wheel', (e) => this.handleWheel(e), { passive: false });

        // Zoom Buttons
        document.getElementById('zoom-in').onclick = () => this.applyZoom(1.5);
        document.getElementById('zoom-out').onclick = () => this.applyZoom(0.75);
        document.getElementById('zoom-reset').onclick = () => this.resetView();

        const focusBtn = document.getElementById('focus-mode');
        if (focusBtn) {
            focusBtn.onclick = (e) => {
                e.stopPropagation();
                this.isDimmed = !this.isDimmed;
                focusBtn.classList.toggle('active', this.isDimmed);
                focusBtn.blur(); // Remove mobile focus/hover stickiness
                this.render();
            };
        }

        // Key shortcuts
        window.addEventListener('keydown', (e) => {
            if (e.key.toLowerCase() === 'z' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                this.undo();
            }
        });

        // Tools
        const toolsContainer = document.querySelector('.pixel-tools');
        toolsContainer.addEventListener('mousedown', (e) => e.stopPropagation());
        toolsContainer.addEventListener('touchstart', (e) => e.stopPropagation());

        const statsPanel = document.querySelector('.stats-panel');
        statsPanel.addEventListener('mousedown', (e) => e.stopPropagation());

        const zoomControls = document.querySelector('.zoom-controls');
        zoomControls.addEventListener('mousedown', (e) => e.stopPropagation());

        const colorCirlces = document.querySelectorAll('.color-circle');
        const colorPicker = document.getElementById('color-picker');
        const customColorBtn = document.getElementById('custom-color-btn');

        colorCirlces.forEach(c => {
            if (c.id === 'custom-color-btn') return; // Handled by input

            c.onclick = (e) => {
                e.stopPropagation();
                colorCirlces.forEach(o => o.classList.remove('active'));
                c.classList.add('active');
                this.activeColor = c.dataset.color;
            };
        });

        if (colorPicker) {
            colorPicker.oninput = (e) => {
                colorCirlces.forEach(o => o.classList.remove('active'));
                customColorBtn.classList.add('active');
                this.activeColor = e.target.value;
                // Update the button border or some indicator if desired
                customColorBtn.style.borderColor = '#fff';
            };
            colorPicker.onclick = (e) => e.stopPropagation();
            colorPicker.onmousedown = (e) => e.stopPropagation();
        }

        document.getElementById('tool-draw').onclick = (e) => {
            e.stopPropagation();
            this.activeTool = 'draw';
            this.floatingImage = null; // Cancel upload
            this.setActiveToolUI('tool-draw');

            // Hide Sticker Controls if active
            const floatCtrl = document.getElementById('floating-controls');
            if (floatCtrl) floatCtrl.classList.add('hidden');
            this.render();
        };

        document.getElementById('tool-erase').onclick = (e) => {
            e.stopPropagation();
            this.activeTool = 'erase';
            this.floatingImage = null; // Cancel upload
            this.setActiveToolUI('tool-erase');

            const floatCtrl = document.getElementById('floating-controls');
            if (floatCtrl) floatCtrl.classList.add('hidden');
            this.render();
        };

        document.getElementById('tool-pan').onclick = (e) => {
            e.stopPropagation();
            this.activeTool = 'pan';
            this.floatingImage = null; // Cancel upload
            this.setActiveToolUI('tool-pan');

            // Hide Sticker Controls if active
            const floatCtrl = document.getElementById('floating-controls');
            if (floatCtrl) floatCtrl.classList.add('hidden');
            this.render();
        };

        document.getElementById('tool-undo').onclick = (e) => {
            e.stopPropagation();
            this.undo();
        };
        document.getElementById('tool-clear').onclick = (e) => {
            e.stopPropagation();
            this.clearAll();
        };

        // Clear Modal Listeners
        const clearModal = document.getElementById('clear-modal');
        if (clearModal) {
            document.getElementById('clear-confirm-btn').onclick = () => {
                this.logEvent('Canvas Cleared');
                this.pixelMap.clear();
                this.history = [];
                this.saveSession();
                this.updateStats();
                this.render();
                clearModal.classList.remove('active');
                setTimeout(() => clearModal.style.display = 'none', 300);
            };
            document.getElementById('clear-cancel-btn').onclick = () => {
                clearModal.classList.remove('active');
                setTimeout(() => clearModal.style.display = 'none', 300);
            };
            // Close on outside click
            clearModal.onclick = (e) => {
                if (e.target === clearModal) {
                    clearModal.classList.remove('active');
                    setTimeout(() => clearModal.style.display = 'none', 300);
                }
            };
        }

        const uploadBtn = document.getElementById('tool-upload');
        const fileInput = document.getElementById('file-input');

        if (uploadBtn && fileInput) {
            uploadBtn.onclick = (e) => {
                e.stopPropagation();
                this.logEvent('Upload Started');
                fileInput.click();
            };

            fileInput.onchange = (e) => {
                if (e.target.files && e.target.files[0]) {
                    const file = e.target.files[0];
                    const MAX_SIZE_BYTES = 1024 * 1024; // 1MB

                    if (file.size > MAX_SIZE_BYTES) {
                        const lang = document.documentElement.lang || 'it';
                        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;
                        showError(t('error-image-size'));
                        e.target.value = '';
                        return;
                    }

                    this.processUploadedImage(file);
                    e.target.value = '';
                }
            };
        }

        // Floating Image Controls
        const floatConfirm = document.getElementById('floating-confirm');
        const floatCancel = document.getElementById('floating-cancel');
        if (floatConfirm) {
            floatConfirm.onclick = (e) => {
                e.stopPropagation();
                this.confirmFloatingImage();
            };
        }
        if (floatCancel) {
            floatCancel.onclick = (e) => {
                e.stopPropagation();
                this.cancelFloatingImage();
            };
        }

        document.getElementById('checkout-btn').onclick = (e) => {
            e.stopPropagation();
            this.logEvent('Checkout Opened');
            this.handleCheckout();
        };

        // Community HUD Interactions
        // Note: Click events are handled in the DOMContentLoaded block below (CSP compliance)


        const toggleBtn = document.getElementById('toggle-controls');
        const rightPanel = document.querySelector('.right-side-controls');

        if (toggleBtn && rightPanel) {
            toggleBtn.onclick = (e) => {
                e.stopPropagation();
                rightPanel.classList.toggle('minimized');
                // Change icon based on state
                if (rightPanel.classList.contains('minimized')) {
                    toggleBtn.textContent = '+'; // Symbol to expand
                    toggleBtn.style.background = 'rgba(77, 255, 136, 0.4)'; // Green tint to hint activity
                } else {
                    toggleBtn.textContent = '_'; // Symbol to minimize
                    toggleBtn.style.background = 'rgba(255, 255, 255, 0.1)';
                }
            };

            // Default to minimized on mobile load? 
            if (window.innerWidth <= 768) {
                // Not forcing it here, let user verify first as requested to "restore previous version". 
                // Previous version was open.
                toggleBtn.textContent = '_';
            }
        }

        // Initialize Default Tool UI
        this.setActiveToolUI('tool-pan');

    }

    setActiveToolUI(activeId) {
        const tools = ['tool-pan', 'tool-draw', 'tool-erase', 'tool-upload'];

        tools.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;

            // Clean inline styles to allow CSS override
            el.style.background = '';
            el.style.border = '';

            if (id === activeId) {
                el.classList.add('active-tool');
            } else {
                el.classList.remove('active-tool');
            }
        });
    }

    processUploadedImage(file) {
        const lang = document.documentElement.lang || 'it';
        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;

        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const MAX_WIDTH_PX = 1920;
                if (img.width > MAX_WIDTH_PX) {
                    showError(t('error-image-dimensions'));
                    return;
                }

                // Open Size Modal instead of Prompt
                const sizeModal = document.getElementById('size-modal');
                const confirmBtn = document.getElementById('size-confirm-btn');
                const cancelBtn = document.getElementById('size-cancel-btn');
                const input = document.getElementById('image-height-input');

                // Reset input
                input.value = 20;

                // Define handlers
                const onConfirm = () => {
                    let heightCm = parseInt(input.value, 10);
                    // Validate height: min 1, max 100
                    if (isNaN(heightCm) || heightCm <= 0) heightCm = 20;
                    if (heightCm > 100) {
                        heightCm = 100;
                        showError(t('error-image-dimensions') + " (Max 100cm)");
                    }

                    if (typeof window.hideModal === 'function') {
                        window.hideModal(sizeModal);
                    } else {
                        sizeModal.classList.remove('active');
                        sizeModal.style.display = 'none';
                    }
                    this.finalizeImageProcessing(img, heightCm);
                    cleanup();
                };

                const onCancel = () => {
                    if (typeof window.hideModal === 'function') {
                        window.hideModal(sizeModal);
                    } else {
                        sizeModal.classList.remove('active');
                        sizeModal.style.display = 'none';
                    }
                    cleanup();
                };

                const cleanup = () => {
                    confirmBtn.onclick = null;
                    cancelBtn.onclick = null;
                };

                confirmBtn.onclick = onConfirm;
                cancelBtn.onclick = onCancel;

                if (typeof window.showModal === 'function') {
                    window.showModal(sizeModal);
                } else {
                    sizeModal.style.display = 'flex';
                    setTimeout(() => sizeModal.classList.add('active'), 10);
                }
            };
            img.onerror = () => {
                showError(t('error-image-load'));
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    finalizeImageProcessing(img, heightCm) {
        this.logEvent('Image Processed');
        const lang = document.documentElement.lang || 'it';
        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;

        const aspectRatio = img.width / img.height;
        // 1 cm = 1 px (as per user design decision)
        const targetHeight = heightCm;
        const targetWidth = Math.round(targetHeight * aspectRatio);

        // Create offscreen canvas to resize
        const oc = document.createElement('canvas');
        oc.width = targetWidth;
        oc.height = targetHeight;
        const octx = oc.getContext('2d');
        octx.drawImage(img, 0, 0, targetWidth, targetHeight);

        // Get Pixel Data
        const imageData = octx.getImageData(0, 0, targetWidth, targetHeight);
        const pixels = [];

        for (let y = 0; y < targetHeight; y++) {
            for (let x = 0; x < targetWidth; x++) {
                const i = (y * targetWidth + x) * 4;
                const r = imageData.data[i];
                const g = imageData.data[i + 1];
                const b = imageData.data[i + 2];
                const a = imageData.data[i + 3];

                if (a > 128) { // Only solid-ish pixels
                    pixels.push({
                        x: x,
                        y: y,
                        color: `rgb(${r},${g},${b})`
                    });
                }
            }
        }

        this.floatingImage = {
            width: targetWidth,
            height: targetHeight,
            pixels: pixels
        };

        this.activeTool = 'upload'; // Custom tool state
        this.uploadFirstMove = true;
        this.setActiveToolUI('tool-upload');

        // STICKER MODE: Center on screen initially
        const screenCX = this.wrapper.clientWidth / 2;
        const screenCY = this.wrapper.clientHeight / 2;
        const worldX = (screenCX - this.translateX) / this.scale;
        const worldY = (screenCY - this.translateY) / this.scale;

        this.floatingPos = {
            x: worldX - (targetWidth / 2),
            y: worldY - (targetHeight / 2)
        };

        // Show Sticky Controls
        const controls = document.getElementById('floating-controls');
        if (controls) {
            this.resetFloatingControlsUI();
            controls.classList.remove('hidden');
            // Force update text for these elements in case language switched while hidden
            const pEl = controls.querySelector('[data-i18n="pw-sticker-instr"]');
            if (pEl) pEl.textContent = t('pw-sticker-instr');

            const btnConf = document.getElementById('floating-confirm');
            if (btnConf) btnConf.textContent = t('pw-btn-confirm');

            const btnCanc = document.getElementById('floating-cancel');
            if (btnCanc) btnCanc.textContent = t('pw-btn-cancel');
        }

        // Update UI Buttons handled by setActiveToolUI now

        this.render();
    }

    async handleCheckout() {
        this.logEvent('Checkout Opened');
        const lang = document.documentElement.lang || 'it';
        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;

        if (this.pixelMap.size === 0) {
            showError(t('pw-alert-draw'));
            return;
        }

        // 1. Show Checkout Modal
        const modal = document.getElementById('checkout-modal');
        const closeModal = document.getElementById('close-checkout-modal');
        const totalDisplay = document.getElementById('checkout-total');
        const submitBtn = document.getElementById('submit-payment');
        const spinner = document.getElementById('spinner');
        const btnText = document.getElementById('button-text');
        const messageDiv = document.getElementById('payment-message');

        modal.classList.add('active');
        modal.style.display = 'flex';

        // Recalculate Total
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        this.pixelMap.forEach((_, key) => {
            const [x, y] = key.split(',').map(Number);
            if (x < minX) minX = x;
            if (x > maxX) maxX = x;
            if (y < minY) minY = y;
            if (y > maxY) maxY = y;
        });
        const width = (maxX - minX) + 1;
        const height = (maxY - minY + 1);
        const area = width * height;
        const pixels = this.pixelMap.size;

        // --- FEE PASSING CALCULATION ---
        const landRate = this.settings?.pricing?.land_rate_cents ? this.settings.pricing.land_rate_cents / 100 : 0.20;
        const inkRate = this.settings?.pricing?.ink_rate_cents ? this.settings.pricing.ink_rate_cents / 100 : 0.30;
        const netAmount = (area * landRate) + (pixels * inkRate);
        const fixedFee = this.settings?.pricing?.stripe_fixed_cents !== undefined ? this.settings.pricing.stripe_fixed_cents / 100 : 0.25;
        const percentFee = this.settings?.pricing?.stripe_percent !== undefined ? this.settings.pricing.stripe_percent : 0.015;
        const minCharge = this.settings?.pricing?.min_charge_cents !== undefined ? this.settings.pricing.min_charge_cents / 100 : 0.50;
        
        let amount = 0;

        if (netAmount > 0) {
            amount = (netAmount + fixedFee) / (1 - percentFee);
            if (amount < minCharge) amount = minCharge;
        }

        const fees = amount - netAmount;

        // Display Breakdown
        const summaryEl = document.getElementById('checkout-summary');
        if (summaryEl) {
            // Hardcoded "Imponibile" / "Commissioni" as per request for simplicity, 
            // or use translations if available. For now user wanted it implemented fast.
            // Let's try to be generic or use Italian/English based on document.lang if possible, 
            // but hardcoding "Net + Fees" logic cleanly.
            const labelNet = lang === 'it' ? 'Imponibile' : 'Subtotal';
            const labelFees = lang === 'it' ? 'Commissioni' : 'Fees';

            summaryEl.innerHTML = `
                ${labelNet}: € ${netAmount.toFixed(2)}<br>
                ${labelFees}: € ${fees.toFixed(2)}
            `;
        }

        totalDisplay.textContent = `€ ${amount.toFixed(2)}`;

        // 2. Initialize Stripe
        if (!this.stripe) {
            try {
                // Ensure Stripe script is loaded if not already present
                if (typeof Stripe === 'undefined') {
                    // Loading Stripe script dynamically...
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://js.stripe.com/v3/";
                        script.onload = resolve;
                        script.onerror = () => reject(new Error("Failed to load Stripe script"));
                        document.head.appendChild(script);
                    });
                }

                // Dynamically load Public Key to ensure consistency with Backend (Test/Live)
                const res = await fetch('api/get_public_key.php');
                if (!res.ok) throw new Error("Key fetch failed");
                const data = await res.json();
                this.stripe = Stripe(data.publicKey);
            } catch (e) {
                console.error("Stripe Init Failed", e);
                showError("Errore Inizializzazione Pagamento: " + e.message);
                return;
            }
        }

        // 3. Create Payment Intent
        const pixelsArray = [];
        this.pixelMap.forEach((color, key) => {
            const [x, y] = key.split(',').map(Number);
            pixelsArray.push({ x, y, color });
        });

        submitBtn.disabled = true;
        btnText.textContent = t('pw-btn-loading');

        try {
            const response = await fetch('api/create-payment-intent.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pixels: pixelsArray }),
            });

            const { clientSecret, error } = await response.json();

            if (error) {
                throw new Error(error);
            }

            // 4. Mount Payment Element
            this.elements = this.stripe.elements({ clientSecret });
            const paymentElement = this.elements.create('payment');
            paymentElement.mount('#payment-element');

            submitBtn.disabled = false;
            btnText.textContent = "Paga Ora";

            // Handle Payment Submission (Only Email required)
            submitBtn.onclick = async () => {
                const email = document.getElementById('signer-email').value.trim();
                const referral = document.getElementById('referral-email').value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (!email || !emailRegex.test(email)) {
                    showError('Inserisci un\'email valida per la ricevuta (es. nome@dominio.com).');
                    return;
                }

                if (referral && !emailRegex.test(referral)) {
                    showError('Inserisci un\'email valida per il referral (o lascia vuoto).');
                    return;
                }

                submitBtn.disabled = true;
                btnText.classList.add('hidden');
                spinner.classList.remove('hidden');
                messageDiv.classList.add('hidden');

                // SAVE TO LOCAL STORAGE BEFORE REDIRECT (Critical for 3DS / Redirect flows)
                localStorage.setItem('madness_checkout_email', email);
                localStorage.setItem('madness_checkout_referral', referral);
                localStorage.setItem('madness_checkout_amount', amount);

                const { error, paymentIntent } = await this.stripe.confirmPayment({
                    elements: this.elements,
                    confirmParams: {
                        return_url: window.location.href.split('?')[0] + '?payment_success=true',
                        receipt_email: email,
                        payment_method_data: {
                            billing_details: {
                                email: email
                            }
                        }
                    },
                    redirect: 'if_required'
                });

                if (error) {
                    messageDiv.textContent = error.message;
                    messageDiv.classList.remove('hidden');
                    submitBtn.disabled = false;
                    btnText.classList.remove('hidden');
                    spinner.classList.add('hidden');
                } else if (paymentIntent && paymentIntent.status === 'succeeded') {
                    // Payment Success! 
                    // 1. Save Pixels with Ownership Metadata
                    // (The transaction recording is now handled server-side in wall-api.php)
                    const saveRes = await this.savePixelsToBackend(email, paymentIntent.id, referral);

                    if (saveRes && saveRes.success) {
                        // --- TRACKING: Conversion Event ---
                        this.trackConversion(amount, paymentIntent.id, email);

                        // 2. Refresh stats immediately so date/money appears in HUD
                        this.updateGlobalStats();

                        if (saveRes.isWinner) {
                            this.showWinnerModal(email, amount, paymentIntent.id);
                        } else if (saveRes.isSilverWinner) {
                            this.showSilverWinnerModal(email, amount, paymentIntent.id);
                        } else {
                            // 3. Move to Signature Step (Pass txnId for optional sign)
                            this.showSignatureModal(email, amount, paymentIntent.id);
                        }
                    } else {
                        // PAYMENT OK, BUT SAVE FAILED
                        console.error("Save failed but payment succeeded", saveRes);
                        const serverError = saveRes && saveRes.error ? saveRes.error : "Unknown Error";
                        showError(`Errore salvataggio: ${serverError} (ID: ${paymentIntent.id})`);
                        // Still try to refresh stats in case color update failed but txn recorded
                        this.updateGlobalStats();
                    }
                }
            };

        } catch (e) {
            console.error(e);
            messageDiv.textContent = t('pw-error-init') + e.message;
            messageDiv.classList.remove('hidden');
            submitBtn.disabled = false;
            btnText.textContent = t('pw-btn-retry');
        }

        // Close logic
        closeModal.onclick = () => {
            modal.classList.remove('active');
            modal.style.display = 'none';
        };
    }

    trackConversion(amount, txnId, email = null) {
        console.log('PixelWall: Attempting to track conversion...', { amount, txnId, email: email ? '***' : null, adsId: window.ADS_ID });

        if (typeof window.gtag === 'function') {
            // 1. GA4 generic purchase event
            window.gtag('event', 'purchase', {
                transaction_id: txnId,
                value: amount,
                currency: 'EUR',
                items: [{
                    item_id: 'pixel_wall_donation',
                    item_name: 'Donazione Pixel Wall',
                    price: amount,
                    quantity: 1
                }]
            });

            // 2. Google Ads specific conversion event
            // Using 'purchase' name is also recommended by modern Google Ads docs for GA4-linked accounts
            if (window.ADS_ID) {
                // Enhanced Conversions: Set user data before the conversion event
                if (email) {
                    window.gtag('set', 'user_data', {
                        "email": email
                    });
                }

                window.gtag('event', 'purchase', {
                    'send_to': window.ADS_ID + '/lSfKCOXXnvAbELuFvL5C',
                    'value': amount,
                    'currency': 'EUR',
                    'transaction_id': txnId
                });

                // Fallback / Legacy compatibility
                window.gtag('event', 'conversion', {
                    'send_to': window.ADS_ID + '/lSfKCOXXnvAbELuFvL5C',
                    'value': amount,
                    'currency': 'EUR',
                    'transaction_id': txnId
                });
            }

            console.log('PixelWall: Conversion Events Fired.');
        } else {
            console.warn('PixelWall: gtag not found, conversion not tracked.');
        }
    }

    async savePixelsToBackend(email, txnId, referral = '') {
        const pixelData = Object.fromEntries(this.pixelMap);

        // Enriched Payload for Ownership Tracking
        const payload = {
            pixels: pixelData,
            meta: {
                email: email,
                txnId: txnId,
                referral: referral
            }
        };

        try {
            const res = await fetch('api/wall-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            return await res.json();
        } catch (e) {
            console.error("Error saving pixels", e);
            return null;
        }
    }



    async saveContributorSign(txnId, name, amount) {
        try {
            await fetch('api/wall-api.php?type=contributors', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: txnId, // ID used for upsert check if needed, though usually just append
                    name: name,
                    amount: amount
                })
            });
            // No need to refresh global stats here as money/date comes from transactions
        } catch (e) {
            console.error("Error saving signature", e);
        }
    }

    async checkStripeRedirect() {
        const urlParams = new URLSearchParams(window.location.search);
        const paymentIntentId = urlParams.get('payment_intent');
        const clientSecret = urlParams.get('payment_intent_client_secret');

        if (!paymentIntentId || !clientSecret) return;

        // Show a loader/processing state using the existing modal
        const modal = document.getElementById('checkout-modal');
        const spinner = document.getElementById('spinner');
        const msg = document.getElementById('payment-message');
        const btn = document.getElementById('submit-payment');
        const btnText = document.getElementById('button-text');

        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
            if (spinner) spinner.classList.remove('hidden');
            if (btn) btn.style.display = 'none';
            if (msg) {
                msg.textContent = "Verifica Pagamento in corso...";
                msg.classList.remove('hidden');
                msg.style.color = 'var(--accent-turquoise)';
            }
        }

        // 1. Initialize Stripe
        if (!this.stripe) {
            try {
                if (typeof Stripe === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = "https://js.stripe.com/v3/";
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }
                const res = await fetch('api/get_public_key.php');
                const data = await res.json();
                this.stripe = Stripe(data.publicKey);
            } catch (e) {
                console.error("Failed to init Stripe", e);
                return;
            }
        }

        // 2. Retrieve Intent
        const { paymentIntent } = await this.stripe.retrievePaymentIntent(clientSecret);

        if (paymentIntent && paymentIntent.status === 'succeeded') {
            const email = localStorage.getItem('madness_checkout_email') || paymentIntent.receipt_email || "anonymous@madness.com";
            const referral = localStorage.getItem('madness_checkout_referral') || "";
            const amount = parseFloat(localStorage.getItem('madness_checkout_amount')) || (paymentIntent.amount / 100);

            if (msg) msg.textContent = "Pagamento Confermato! Salvataggio...";

            // 3. Save to Backend
            try {
                // IMPORTANT: this.pixelMap was loaded in constructor/loadData from 'madness_pixels'
                if (this.pixelMap.size > 0) {
                    const saveRes = await this.savePixelsToBackend(email, paymentIntent.id, referral);

                    // --- TRACKING: Conversion Event ---
                    this.trackConversion(amount, paymentIntent.id, email);

                    // Show Success / Signature UI
                    if (saveRes && saveRes.isWinner) {
                        this.showWinnerModal(email, amount, paymentIntent.id);
                    } else if (saveRes && saveRes.isSilverWinner) {
                        this.showSilverWinnerModal(email, amount, paymentIntent.id);
                    } else {
                        this.showSignatureModal(email, amount, paymentIntent.id);
                    }

                    // Note: We don't clear localStorage 'madness_pixels' yet, 
                    // the Success UI will do it (or we do it here if we want to be safe)
                } else {
                    // Pixels already gone? Just redirect or show success
                    window.location.replace('wordcloud.html');
                }
            } catch (e) {
                console.error("Error finalizing purchase", e);
                if (msg) {
                    msg.textContent = "Errore nel salvataggio dei pixel. Contattaci!";
                    msg.style.color = '#ff4d4d';
                }
            }
        } else {
            // console.log("Payment not succeeded yet:", paymentIntent ? paymentIntent.status : "unknown");
            if (modal) {
                modal.classList.remove('active');
                modal.style.display = 'none';
            }
        }
    }

    showSignatureModal(email, amount, txnId) {
        const lang = document.documentElement.lang || 'it';
        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;

        // Reuse checkout modal for success state
        const modalBody = document.querySelector('#checkout-modal .modal-body');
        const modalTitle = document.querySelector('#checkout-modal .modal-title');
        const modalContent = document.querySelector('#checkout-modal .modal-content');

        if (modalContent) modalContent.scrollTop = 0; // Reset scroll to top

        modalTitle.innerText = "🎉 " + t('pw-success-title');

        modalBody.innerHTML = `
            <div class="text-center">
                <div class="checkout-success-icon">✓</div>
                <p class="checkout-success-msg">${t('pw-success-msg').replace('{amount}', amount.toFixed(2))}</p>
                
                <div class="checkout-sign-box">
                    <label class="checkout-sign-label">${t('pw-sign-label')}</label>
                    <p class="checkout-sign-instr">Scrivi il tuo nome (o nickname) per apparire nella Word Cloud dei sostenitori! (Facoltativo)</p>
                    
                    <input type="text" id="wall-name-input" placeholder="${t('pw-name-placeholder')}" maxlength="25"
                        class="winner-input">
                    
                    <button id="sign-fame-btn" class="buy-btn checkout-sign-btn">${t('pw-sign-btn')}</button>
                </div>
                
                <button id="close-success" class="skip-btn">${t('pw-skip-btn')}</button>
            </div>
        `;

        // Logic for Signature
        document.getElementById('sign-fame-btn').onclick = async () => {
            const name = document.getElementById('wall-name-input').value.trim();
            if (!name) { showError(t('pw-error-name')); return; }

            document.getElementById('sign-fame-btn').textContent = "...";
            try {
                await this.saveContributorSign(txnId, name, amount);
                this.pixelMap.clear();
                localStorage.removeItem('madness_pixels');
                window.location.replace('wordcloud.html');
            } catch (e) {
                console.error(e);
                showError(t('pw-error-save-short'));
            }
        };

        // Logic for Skip
        document.getElementById('close-success').onclick = () => {
            this.pixelMap.clear();
            localStorage.removeItem('madness_pixels');
            location.reload();
        };

        document.getElementById('close-checkout-modal').style.display = 'none'; // Force decision
    }

    showWinnerModal(email, amount, txnId) {
        const lang = document.documentElement.lang || 'it';
        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;

        const modalBody = document.querySelector('#checkout-modal .modal-body');
        const modalTitle = document.querySelector('#checkout-modal .modal-title');
        const modalContent = document.querySelector('#checkout-modal .modal-content');

        if (modalContent) modalContent.scrollTop = 0; // Reset scroll to top

        // Setup Golden Theme
        modalTitle.innerText = t('pw-win-gold-title');
        modalTitle.style.color = '#FFD700';
        modalTitle.style.textShadow = '0 0 20px #FFD700';
        modalTitle.style.fontSize = '1.5rem';

        modalBody.innerHTML = `
            <div class="text-center">
                <div class="gold-emoji">🪙</div>
                <h3 class="gold-title">${t('pw-congrats')}</h3>
                <p class="winner-msg">${t('pw-win-gold-msg')}</p>
                <p class="winner-info">
                   ${t('pw-win-gold-info')}
                   <br><br><span class="gold-text">${t('pw-email-confirm')}${email}</span>
                </p>
                
                <div class="gold-box-container">
                    <label class="gold-label">${t('pw-sign-label')}</label>
                    <p class="sign-instr">${t('pw-sign-fame-instr')}</p>
                    
                    <input type="text" id="wall-name-input" placeholder="${t('pw-name-placeholder')}" maxlength="25"
                        class="winner-input winner-input-gold">
                    
                    <button id="sign-fame-btn" class="buy-btn btn-gold">${t('pw-sign-btn')}</button>
                </div>
                
                <button id="close-success" class="skip-btn">${t('pw-skip-btn')}</button>
            </div>
        `;

        // Logic for Signature
        document.getElementById('sign-fame-btn').onclick = async () => {
            const name = document.getElementById('wall-name-input').value.trim();
            if (!name) { showError(t('pw-error-name')); return; }

            document.getElementById('sign-fame-btn').textContent = "...";
            try {
                await this.saveContributorSign(txnId, name, amount);
                this.pixelMap.clear();
                localStorage.removeItem('madness_pixels');
                window.location.replace('wordcloud.html');
            } catch (e) {
                console.error(e);
                showError(t('pw-error-save-short'));
            }
        };

        // Logic for Skip
        document.getElementById('close-success').onclick = () => {
            this.pixelMap.clear();
            localStorage.removeItem('madness_pixels');
            location.reload();
        };

        document.getElementById('close-checkout-modal').style.display = 'none'; // Force decision
    }

    showSilverWinnerModal(email, amount, txnId) {
        const lang = document.documentElement.lang || 'it';
        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;

        const modalBody = document.querySelector('#checkout-modal .modal-body');
        const modalTitle = document.querySelector('#checkout-modal .modal-title');
        const modalContent = document.querySelector('#checkout-modal .modal-content');

        if (modalContent) modalContent.scrollTop = 0; // Reset scroll to top

        // Setup Silver Theme
        modalTitle.innerText = t('pw-win-silver-title');
        modalTitle.style.color = '#C0C0C0'; // Silver
        modalTitle.style.textShadow = '0 0 20px #C0C0C0';
        modalTitle.style.fontSize = '1.5rem';

        modalBody.innerHTML = `
            <div class="text-center">
                <div class="silver-emoji">🪙</div> 
                <h3 class="silver-title">${t('pw-fantastic')}</h3>
                <p class="winner-msg">${t('pw-win-silver-msg')}</p>
                <p class="winner-info">
                   ${t('pw-win-silver-info')}
                   <br><br><span class="silver-text">${t('pw-email-check')}${email}</span>
                </p>
                
                <div class="silver-box-container">
                    <label class="silver-label">${t('pw-sign-label')}</label>
                    <p class="sign-instr">${t('pw-sign-fame-instr')}</p>
                    
                    <input type="text" id="wall-name-input" placeholder="${t('pw-name-placeholder')}" maxlength="25"
                        class="winner-input winner-input-silver">
                    
                    <button id="sign-fame-btn" class="buy-btn btn-silver">${t('pw-sign-btn')}</button>
                </div>
                
                <button id="close-success" class="skip-btn">${t('pw-skip-btn')}</button>
            </div>
        `;

        // Logic for Signature (same as Gold)
        document.getElementById('sign-fame-btn').onclick = async () => {
            const name = document.getElementById('wall-name-input').value.trim();
            if (!name) { showError(t('pw-error-name')); return; }

            document.getElementById('sign-fame-btn').textContent = "...";
            try {
                await this.saveContributorSign(txnId, name, amount);
                this.pixelMap.clear();
                localStorage.removeItem('madness_pixels');
                window.location.replace('wordcloud.html');
            } catch (e) {
                console.error(e);
                showError(t('pw-error-save-short'));
            }
        };

        // Logic for Skip
        document.getElementById('close-success').onclick = () => {
            this.pixelMap.clear();
            localStorage.removeItem('madness_pixels');
            location.reload();
        };

        document.getElementById('close-checkout-modal').style.display = 'none';
    }

    resetView() {
        // Ensure physical canvas size is correct before calculating view
        this.resize();

        const wrapW = this.wrapper.clientWidth || window.innerWidth;
        const wrapH = this.wrapper.clientHeight || window.innerHeight;

        // Responsive Logic
        const isMobile = this.isMobile;

        if (isMobile) {
            // --- MOBILE SIMPLIFIED LOGIC ---
            // Just fit the board width with some padding, ignoring complex subtractive math
            const marginX = 20; // 10px each side
            const safeW = wrapW - marginX;

            // Calculate scale primarily based on width to ensure readability
            // But check height too so it doesn't overflow vertically if the screen is super short
            // In landscape (wrapW > wrapH) we reserve less vertical space
            const reserveH = (wrapW > wrapH) ? 120 : 250;
            const availH = wrapH - reserveH;

            let scaleX = safeW / this.gridWidth;
            let scaleY = availH / (this.gridHeight + 30); // Use 30 for title space on mobile

            this.scale = Math.min(scaleX, scaleY);

            // Safety: Ensure meaningful visibility
            if (this.scale < 0.15) this.scale = 0.15;
            if (this.scale > 1.2) this.scale = 1.2;

            const boardW = this.gridWidth * this.scale;
            const boardH = this.gridHeight * this.scale;

            // Center Horizontally
            this.translateX = (wrapW - boardW) / 2;

            // Center Vertically - biased slightly upwards to clear the bottom toolbar
            // We aim for it to be centered in the visual viewport between standard header and bottom
            this.translateY = (wrapH - boardH) / 2 - 20;

        } else {
            // --- DESKTOP STANDARD LOGIC ---
            const headerHeight = 70;
            const toolbarHeight = 120;
            const statsWidth = 320; // Increased from 240 to shift board left (optical centering)
            const zoomWidth = 80;

            const safeX1 = zoomWidth;
            const safeX2 = wrapW - statsWidth;
            const safeY1 = headerHeight;
            const safeY2 = wrapH - toolbarHeight;

            const safeWidth = safeX2 - safeX1;
            const safeHeight = safeY2 - safeY1;

            const virtualHeight = this.gridHeight + 60;
            const scaleX = safeWidth / this.gridWidth;
            const scaleY = safeHeight / virtualHeight;

            this.scale = Math.min(scaleX, scaleY);
            if (this.scale > 4.0) this.scale = 4.0;
            // Safety check
            if (this.scale <= 0) this.scale = 0.5;

            const boardWidth = this.gridWidth * this.scale;
            const blockHeight = virtualHeight * this.scale;

            this.translateX = safeX1 + (safeWidth - boardWidth) / 2;
            const centeredBlockTop = safeY1 + (safeHeight - blockHeight) / 2;
            this.translateY = centeredBlockTop + (60 * this.scale);
        }

        this.render();

        // One-time toolbar positioning
        const toolbar = document.getElementById('pixel-tools-bar');
        if (toolbar) {
            const boardW = this.gridWidth * this.scale;
            const boardCenterX = this.translateX + boardW / 2;
            // On mobile, keep toolbar centered on screen, not linked to board center if it pans
            if (!isMobile) {
                toolbar.style.left = `${boardCenterX}px`;
            } else {
                toolbar.style.left = '50%';
            }
        }
    }

    undo() {
        if (this.history.length === 0) return;

        const lastStroke = this.history.pop();

        // Reverse the entire stroke
        lastStroke.forEach(pixel => {
            if (pixel.oldColor) {
                this.pixelMap.set(pixel.key, pixel.oldColor);
            } else {
                this.pixelMap.delete(pixel.key);
            }
        });

        this.saveSession();
        this.updateStats(); // Recalculate stats (and bounding box) FIRST
        this.render();      // Then render
    }

    clearAll() {
        const modal = document.getElementById('clear-modal');
        if (modal) {
            if (typeof window.showModal === 'function') {
                window.showModal(modal);
            } else {
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('active'), 10);
            }
        } else {
            // Fallback if modal is missing
            if (confirm("Sei sicuro di voler cancellare tutto il tuo disegno?")) {
                this.pixelMap.clear();
                this.history = [];
                this.saveSession();
                this.updateStats(); // Update stats (and clear bounding box) FIRST
                this.render(); // Then render the clean slate
            }
        }
    }

    handleWheel(e) {
        e.preventDefault();
        const delta = e.deltaY > 0 ? 0.9 : 1.1;
        this.applyZoom(delta, e.clientX, e.clientY);
    }

    applyZoom(factor, centerX, centerY) {
        const oldScale = this.scale;
        this.scale *= factor;

        // Limit zoom
        this.scale = Math.max(0.2, Math.min(this.scale, 50));

        if (!centerX) {
            centerX = this.wrapper.clientWidth / 2;
            centerY = this.wrapper.clientHeight / 2;
        }

        // Adjust translation to zoom into mouse point
        const mouseX = (centerX - this.translateX) / oldScale;
        const mouseY = (centerY - this.translateY) / oldScale;

        this.translateX = centerX - mouseX * this.scale;
        this.translateY = centerY - mouseY * this.scale;

        // STICKER MODE: Re-center on Zoom
        if (this.activeTool === 'upload' && this.floatingImage) {
            const screenCX = this.wrapper.clientWidth / 2;
            const screenCY = this.wrapper.clientHeight / 2;
            const worldX = (screenCX - this.translateX) / this.scale;
            const worldY = (screenCY - this.translateY) / this.scale;

            this.floatingPos = {
                x: worldX - (this.floatingImage.width / 2),
                y: worldY - (this.floatingImage.height / 2)
            };
        }

        this.render();
    }

    handleStart(e) {
        this.lastX = e.clientX;
        this.lastY = e.clientY;

        // --- DISABLE FOCUS MODE ON CLICK ---
        if (this.isDimmed) {
            this.isDimmed = false;
            const focusBtn = document.getElementById('focus-mode');
            if (focusBtn) {
                focusBtn.classList.remove('active');
                focusBtn.blur();
            }
            this.render();
        }

        // --- WINNER CLICK DETECTION ---
        const rect = this.wrapper.getBoundingClientRect();
        const gx = Math.floor((e.clientX - rect.left - this.translateX) / this.scale);
        const gy = Math.floor((e.clientY - rect.top - this.translateY) / this.scale);

        // Check 3x3 area around click for better mobile tolerance
        let foundWinner = null;
        for (let dy = -1; dy <= 1; dy++) {
            for (let dx = -1; dx <= 1; dx++) {
                const key = `${gx + dx},${gy + dy}`;
                if (this.winnerMap && this.winnerMap.has(key)) {
                    foundWinner = this.winnerMap.get(key);
                    break;
                }
            }
            if (foundWinner) break;
        }

        if (foundWinner) {
            this.showWinnerLabel(e.clientX, e.clientY, foundWinner.name, foundWinner.type);
        }

        if (this.activeTool === 'pan' || (this.activeTool === 'upload' && this.floatingImage)) {
            // Allow panning in upload mode to move world under sticker
            this.isPanning = true;
        } else if (this.activeTool === 'upload') {
            // Should not happen if floatingImage is null, but safety
        } else {
            if (!this.hasLoggedDrawing) {
                const eventName = (this.activeTool === 'erase') ? 'Eraser Used' : 'Drawing Started';
                this.logEvent(eventName);
                this.hasLoggedDrawing = true;
            }
            this.isDrawing = true;
            this.currentStroke = []; // Start new stroke
            this.pixelAt(e.clientX, e.clientY);
        }
    }

    handleMove(e) {
        // Update cursor feedback
        const rect = this.wrapper.getBoundingClientRect();
        const x = Math.floor((e.clientX - rect.left - this.translateX) / this.scale);
        const y = Math.floor((e.clientY - rect.top - this.translateY) / this.scale);
        const key = `${x},${y}`;

        if (this.activeTool === 'draw' || this.activeTool === 'erase') {
            const isProtected = this.confirmedMap.has(key) || this.isNearPreset(x, y);
            this.wrapper.style.cursor = isProtected ? 'not-allowed' : (this.activeTool === 'erase' ? 'cell' : 'crosshair');
        } else {
            this.wrapper.style.cursor = 'grab';
        }

        if (this.isPanning) {
            this.wrapper.style.cursor = 'grabbing';
            const dx = e.clientX - this.lastX;
            const dy = e.clientY - this.lastY;
            this.translateX += dx;
            this.translateY += dy;
            this.lastX = e.clientX;
            this.lastY = e.clientY;

            // STICKER MODE UPDATE: 
            // If dragging canvas while image is floating, we RE-CENTER the floating image relative to the screen
            // effectively making it "stick" to the center of the viewport while the world moves under it.
            if (this.activeTool === 'upload' && this.floatingImage) {
                const screenCX = this.wrapper.clientWidth / 2;
                const screenCY = this.wrapper.clientHeight / 2;
                const worldX = (screenCX - this.translateX) / this.scale;
                const worldY = (screenCY - this.translateY) / this.scale;

                this.floatingPos = {
                    x: worldX - (this.floatingImage.width / 2),
                    y: worldY - (this.floatingImage.height / 2)
                };
            }

            this.render();
        } else if (this.activeTool === 'upload' && this.floatingImage) {
            // STICKER MODE: 
            // Do NOT follow mouse/cursor. Image stays fixed relative to screen (centered).
            // User must Pan/Zoom to position world under sticker.
        } else if (this.isDrawing) {
            this.pixelAt(e.clientX, e.clientY);
        }
    }

    handleEnd() {
        if (this.isDrawing) {
            if (this.currentStroke.length > 0) {
                this.history.push(this.currentStroke);
                // Limit history to 50 strokes
                if (this.history.length > 50) this.history.shift();
            }
            this.saveSession();
        }

        if (this.activeTool === 'upload' && this.floatingImage) {
            // DO NOTHING! Wait for confirm button!
            // Just drop the "dragging" state
        }

        this.isPanning = false;
        this.isDrawing = false;
        this.currentStroke = [];
    }

    // New Method to confirm placement
    confirmFloatingImage() {
        const lang = document.documentElement.lang || 'it';
        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;

        if (!this.floatingImage) return;

        // 1. Check Collision
        let collision = false;
        const baseX = Math.round(this.floatingPos.x);
        const baseY = Math.round(this.floatingPos.y);

        // Bounds check
        if (baseX < 0 || baseY < 0 ||
            (baseX + this.floatingImage.width) > this.gridWidth ||
            (baseY + this.floatingImage.height) > this.gridHeight) {
            collision = true;
        } else {
            for (let p of this.floatingImage.pixels) {
                const absX = baseX + p.x;
                const absY = baseY + p.y;
                const key = `${absX},${absY}`;
                if (this.confirmedMap.has(key) || this.isNearPreset(absX, absY)) {
                    collision = true;
                    break;
                }
            }
        }

        if (collision) {
            showError(t('error-collision'));
        } else {
            // STAMP IT!
            const stroke = [];
            for (let p of this.floatingImage.pixels) {
                const absX = baseX + p.x;
                const absY = baseY + p.y;
                const key = `${absX},${absY}`;
                const oldColor = this.pixelMap.get(key);

                if (oldColor !== p.color) {
                    stroke.push({ key, oldColor, newColor: p.color });
                    this.pixelMap.set(key, p.color);
                }
            }

            if (stroke.length > 0) {
                this.history.push(stroke);
            }

            this.logEvent('Image Placed');
            this.saveSession();
            this.updateStats();

            // Reset State
            this.floatingImage = null;
            this.activeTool = 'pan';
            document.getElementById('tool-pan').click(); // Update UI

            // Hide Controls
            const controls = document.getElementById('floating-controls');
            if (controls) {
                controls.classList.add('hidden');
                this.resetFloatingControlsUI();
            }

            this.render();
        }
    }

    cancelFloatingImage() {
        this.floatingImage = null;
        this.activeTool = 'pan';
        document.getElementById('tool-pan').click();

        const controls = document.getElementById('floating-controls');
        if (controls) {
            controls.classList.add('hidden');
            this.resetFloatingControlsUI();
        }

        this.render();
    }

    pixelAt(screenX, screenY) {
        // Convert screen coordinates to canvas/grid coordinates
        const rect = this.wrapper.getBoundingClientRect();
        const x = Math.floor((screenX - rect.left - this.translateX) / this.scale);
        const y = Math.floor((screenY - rect.top - this.translateY) / this.scale);

        if (x >= 0 && x < this.gridWidth && y >= 0 && y < this.gridHeight) {
            const key = `${x},${y}`;

            // PROTECTION: Prevent drawing on pixels already confirmed (purchased)
            if (this.confirmedMap.has(key)) {
                return;
            }

            // SAFETY BORDER: Prevent drawing/erasing adjacent to Preset Pixels
            if (this.isNearPreset(x, y)) {
                return;
            }

            if (this.activeTool === 'erase') {
                // ERASER LOGIC
                if (this.pixelMap.has(key)) {
                    const oldColor = this.pixelMap.get(key);
                    // Add erase action to history (newColor null means delete)
                    this.currentStroke.push({ key, oldColor, newColor: null });
                    this.pixelMap.delete(key);
                    this.updateStats();
                    this.render();
                }
            } else {
                // DRAW LOGIC
                const oldColor = this.pixelMap.get(key);

                // Only act if color is different and not already in current stroke to avoid duplicates
                if (oldColor !== this.activeColor) {
                    this.currentStroke.push({ key, oldColor, newColor: this.activeColor });

                    this.pixelMap.set(key, this.activeColor);
                    this.updateStats(); // Recalculate bounding box FIRST
                    this.render(); // Then draw it
                }
            }
        }
    }

    isNearPreset(x, y) {
        return this.forbiddenSet && this.forbiddenSet.has(`${x},${y}`);
    }

    updateStats() {
        if (this.pixelMap.size === 0) {
            this.boundingBox = null;
            document.querySelectorAll('.val-pixels-count').forEach(el => el.textContent = `0 cm² (0 px)`);
            document.querySelectorAll('.val-price-count').forEach(el => el.textContent = `€ 0.00`);
            return;
        }

        // Calculate Bounding Box
        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        this.pixelMap.forEach((_, key) => {
            const [x, y] = key.split(',').map(Number);
            if (x < minX) minX = x;
            if (x > maxX) maxX = x;
            if (y < minY) minY = y;
            if (y > maxY) maxY = y;
        });

        const width = maxX - minX + 1;
        const height = maxY - minY + 1;
        this.boundingBox = { x: minX, y: minY, w: width, h: height };

        // --- GROSS AREA ---
        const grossArea = width * height;

        // --- NET AREA OPTIMIZATION (Fair Pricing) ---
        // Subtract pixels in the bounding box that are ALREADY occupied by others (confirmedMap)
        // or by presets (presetMap).
        // The user pays land tax only for FREE space they occupy/enclose.

        let occupiedCount = 0;

        // We iterate the bounding box. Since max grid is small (1000x400), this is performant enough (~10ms max)
        for (let y = minY; y <= maxY; y++) {
            for (let x = minX; x <= maxX; x++) {
                const key = `${x},${y}`;
                // If it's NOT our pixel
                if (!this.pixelMap.has(key)) {
                    // Check if it's occupied by others (confirmed) OR is a forbidden area (preset + border)
                    if ((this.confirmedMap && this.confirmedMap.has(key)) ||
                        (this.forbiddenSet && this.forbiddenSet.has(key))) {
                        occupiedCount++;
                    }
                }
            }
        }

        const netArea = Math.max(0, grossArea - occupiedCount);
        const pixelParams = this.pixelMap.size;

        // --- HYBRID PRICING MODEL ---
        const landRate = this.settings?.pricing?.land_rate_cents ? this.settings.pricing.land_rate_cents / 100 : 0.20;
        const inkRate = this.settings?.pricing?.ink_rate_cents ? this.settings.pricing.ink_rate_cents / 100 : 0.30;
        const landCost = netArea * landRate;
        const inkCost = pixelParams * inkRate;
        const totalPrice = landCost + inkCost;

        document.querySelectorAll('.val-pixels-count').forEach(el => el.textContent = `${netArea.toLocaleString()} cm² (${pixelParams} px)`);
        document.querySelectorAll('.val-price-count').forEach(el => el.textContent = `€ ${totalPrice.toFixed(2)}`);
    }

    render() {
        const wrapW = this.wrapper.clientWidth;
        const wrapH = this.wrapper.clientHeight;

        // 1. Clear the entire full-screen canvas
        this.ctx.clearRect(0, 0, wrapW, wrapH);

        // 2. Draw the board background (White Rectangle)
        // We apply the transform manually for the board
        this.ctx.save();
        this.ctx.translate(this.translateX, this.translateY);
        this.ctx.scale(this.scale, this.scale);

        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.gridWidth, this.gridHeight);

        // --- Draw Grid (Internal) ---

        // Minor Grid: 10cm (every 10px)
        if (this.scale > 3) {
            this.ctx.strokeStyle = 'rgba(0, 0, 0, 0.05)';
            this.ctx.lineWidth = 0.2 / this.scale;
            this.ctx.beginPath();
            for (let i = 0; i <= this.gridWidth; i += 10) {
                this.ctx.moveTo(i, 0); this.ctx.lineTo(i, this.gridHeight);
            }
            for (let j = 0; j <= this.gridHeight; j += 10) {
                this.ctx.moveTo(0, j); this.ctx.lineTo(this.gridWidth, j);
            }
            this.ctx.stroke();
        }

        // Major Grid: 1 meter (every 100px)
        this.ctx.strokeStyle = 'rgba(0, 0, 0, 0.1)';
        this.ctx.lineWidth = 0.5 / this.scale;
        this.ctx.beginPath();
        for (let i = 0; i <= this.gridWidth; i += 100) {
            this.ctx.moveTo(i, 0); this.ctx.lineTo(i, this.gridHeight);
        }
        for (let j = 0; j <= this.gridHeight; j += 100) {
            this.ctx.moveTo(0, j); this.ctx.lineTo(this.gridWidth, j);
        }
        this.ctx.stroke();

        // --- Draw Confirmed Pixels (OPTIMIZED: From Cache) ---
        // Instead of 10,000 fillRect calls, we do 1 drawImage
        if (this.cacheCanvas) {
            this.ctx.drawImage(this.cacheCanvas, 0, 0);
        }

        // --- Draw Session Pixels (The Draft) ---
        this.pixelMap.forEach((color, key) => {
            const [x, y] = key.split(',').map(Number);
            this.ctx.fillStyle = color;
            this.ctx.fillRect(x, y, 1, 1);
        });

        // --- Dim Overlay (Focus Mode) ---
        if (this.isDimmed) {
            this.ctx.fillStyle = 'rgba(0, 0, 0, 0.85)';
            this.ctx.fillRect(0, 0, this.gridWidth, this.gridHeight);
        }

        // --- Draw Winner Highlights (Pulsing Star Reflections) ---
        if (this.winners) {
            const time = Date.now();
            const pulse = 0.5 + 0.5 * Math.sin(time / 300);

            // Gold Winners
            if (this.winners.gold && this.winners.gold.length > 0) {
                this.winners.gold.forEach(w => this.drawStar(w.pixel, '#FFD700', pulse));
            }

            // Silver Winners
            if (this.winners.silver && this.winners.silver.length > 0) {
                this.winners.silver.forEach(w => this.drawStar(w.pixel, '#E0E0E0', pulse)); // Brighter Silver for glow
            }
        }

        // --- Draw Bounding Box (Pricing Area) ---
        if (this.boundingBox) {
            this.ctx.strokeStyle = '#ff4d4d'; // Accent Orange
            this.ctx.lineWidth = 1 / this.scale;
            this.ctx.setLineDash([4 / this.scale, 4 / this.scale]);
            this.ctx.strokeRect(
                this.boundingBox.x - 0.5,
                this.boundingBox.y - 0.5,
                this.boundingBox.w + 1,
                this.boundingBox.h + 1
            );
            this.ctx.setLineDash([]);

            // Draw Dimensions Labels
            this.ctx.save();
            this.ctx.fillStyle = '#ff4d4d';
            // Constant screen size ~14px approx
            const fontSize = 14 / this.scale;
            this.ctx.font = `bold ${fontSize}px 'Oswald', sans-serif`;
            this.ctx.textBaseline = 'bottom';
            this.ctx.textAlign = 'center';

            // Width Label (Top Center)
            const labelW = `${this.boundingBox.w} cm`;
            // Add slight padding above the line
            this.ctx.fillText(labelW, this.boundingBox.x + this.boundingBox.w / 2, this.boundingBox.y - (2 / this.scale));

            // Height Label (Left Center - Rotated)
            // Save state again for rotation
            this.ctx.save();
            this.ctx.translate(this.boundingBox.x - (2 / this.scale), this.boundingBox.y + this.boundingBox.h / 2);
            this.ctx.rotate(-Math.PI / 2);
            const labelH = `${this.boundingBox.h} cm`;
            this.ctx.fillText(labelH, 0, 0);
            this.ctx.restore();

            this.ctx.restore();
        }

        // --- Draw Floating Image (Upload Preview) ---
        if (this.floatingImage && this.floatingPos) {
            const bx = Math.round(this.floatingPos.x);
            const by = Math.round(this.floatingPos.y);

            // 1. Draw the pixels
            this.floatingImage.pixels.forEach(p => {
                this.ctx.fillStyle = p.color;
                this.ctx.fillRect(bx + p.x, by + p.y, 1, 1);
            });

            // 2. Draw border
            // Check collision for visual feedback
            let collision = false;
            // Bounds check
            if (bx < 0 || by < 0 ||
                (bx + this.floatingImage.width) > this.gridWidth ||
                (by + this.floatingImage.height) > this.gridHeight) {
                collision = true;
            } else {
                // Sample check for collision (checking all might be heavy but necessary for accuracy)
                // We can optimize by checking corners first? No, existing pixels could be anywhere.
                // Let's just do it, standard image isn't too big.
                for (let p of this.floatingImage.pixels) {
                    const absX = bx + p.x;
                    const absY = by + p.y;
                    const key = `${absX},${absY}`;
                    if (this.confirmedMap.has(key) || this.isNearPreset(absX, absY)) {
                        collision = true;
                        break;
                    }
                }
            }

            this.ctx.strokeStyle = collision ? 'red' : '#4dff88';
            this.ctx.lineWidth = 2 / this.scale;
            this.ctx.strokeRect(bx, by, this.floatingImage.width, this.floatingImage.height);

            // Update HTML controls position to be under the image preview
            this.updateFloatingControlsUI();
        }

        this.ctx.restore();

        // 3. Draw OUTSIDE Labels (Sharp Text, 1:1 resolution)
        this.ctx.fillStyle = 'rgba(255, 255, 255, 0.6)';
        this.ctx.font = 'bold 12px Oswald';
        this.ctx.textAlign = 'center';
        this.ctx.textBaseline = 'bottom';

        // X Axis Labels (Top)
        for (let i = 0; i <= this.gridWidth; i += 100) {
            const screenX = this.translateX + i * this.scale;
            const screenY = this.translateY - 8;
            this.ctx.fillText(`${i / 100} m`, screenX, screenY);

            // Helpful dash line outside
            this.ctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
            this.ctx.lineWidth = 1;
            this.ctx.beginPath();
            this.ctx.moveTo(screenX, screenY + 4);
            this.ctx.lineTo(screenX, this.translateY);
            this.ctx.stroke();
        }

        // Y Axis Labels (Left)
        this.ctx.textAlign = 'right';
        this.ctx.textBaseline = 'middle';
        for (let j = 0; j <= this.gridHeight; j += 100) {
            const screenX = this.translateX - 12;
            const screenY = this.translateY + j * this.scale;
            this.ctx.fillText(`${j / 100} m`, screenX, screenY);

            this.ctx.beginPath();
            this.ctx.moveTo(screenX + 4, screenY);
            this.ctx.lineTo(this.translateX, screenY);
            this.ctx.stroke();
        }



        // Update Title Position (Anchored to Board Top)
        const title = document.querySelector('.canvas-title');
        if (title) {
            const titleX = this.translateX + (this.gridWidth * this.scale) / 2;
            const titleY = this.translateY - Math.max(20, 30 * this.scale);

            title.style.left = `${titleX}px`;
            title.style.top = `${titleY}px`;

            // Scale title with board, but clamp minimum size for readability
            // On mobile we use a larger minimum scale (0.8 instead of 0.5)
            const minTitleScale = this.isMobile ? 0.9 : 0.5;
            title.style.transform = `translate(-50%, -100%) scale(${Math.max(minTitleScale, this.scale)})`;
            title.classList.add('visible');
        }

        // 4. Stats Labels HUD Update (DOM based)
        const hud = document.getElementById('wall-stats-hud');
        if (hud) {
            const x = this.translateX;
            const y = this.translateY + (this.gridHeight * this.scale) + 8; // 8px below board
            const w = this.gridWidth * this.scale;

            hud.style.left = `${x}px`;
            hud.style.top = `${y}px`;
            hud.style.width = `${w}px`;
            hud.style.display = (this.scale > 0.05) ? 'flex' : 'none';
        }
    }
    async updateGlobalStats() {
        // Update Total Pixels (Local confirmed count is accurate for visual)
        // Subtract Preset Pixels from total to show "Sold" count correctly
        const presetCount = this.presetMap ? this.presetMap.size : 0;
        const totalPixels = Math.max(0, this.confirmedMap.size - presetCount);

        const pixelsEl = document.getElementById('total-pixels-hud');
        if (pixelsEl) pixelsEl.textContent = totalPixels.toLocaleString() + " px";

        // Update Total Raised & Gold Found (From Stats API)
        try {
            const res = await fetch('api/wall-api.php?type=transactions&stats=1');
            if (res.ok) {
                const stats = await res.json();
                const moneyEl = document.getElementById('total-raised-hud');
                if (moneyEl) {
                    moneyEl.textContent = `€ ${(stats.total_raised || 0).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })}`;
                }

                const goldEl = document.getElementById('gold-found-hud');
                if (goldEl) {
                    goldEl.textContent = `${stats.found_gold}/10`;
                }

                const silverEl = document.getElementById('silver-found-hud');
                if (silverEl) {
                    silverEl.textContent = `${stats.found_silver}/50`;
                }

                // Store data for language switch refreshes
                this.lastGlobalStats = stats;
                this.updateStatusText();
            }
        } catch (e) {
            console.error("Error fetching global stats", e);
        }
    }

    updateStatusText() {
        // Prepare localized Start Text
        const lang = document.documentElement.lang || 'it';
        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;

        const startText = `${t('pw-stat-start-label')} ${t('pw-stat-start-val')}`;

        let lastDateFormatted = "-";

        // Use Global Stats Date if available (more reliable/fast)
        if (this.lastGlobalStats && this.lastGlobalStats.last_contribution) {
            const d = new Date(this.lastGlobalStats.last_contribution);
            const locale = (lang === 'en') ? 'en-US' : ((lang === 'es') ? 'es-ES' : 'it-IT');
            lastDateFormatted = d.toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' }) +
                " " + d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        } else {
            // Fallback: Find latest date from loaded pixels
            let lastTimestamp = 0;
            if (this.confirmedMap) {
                for (const p of this.confirmedMap.values()) {
                    if (p.date) {
                        const ts = new Date(p.date).getTime();
                        if (ts > lastTimestamp) lastTimestamp = ts;
                    }
                }
            }
            if (lastTimestamp > 0) {
                const d = new Date(lastTimestamp);
                const locale = (lang === 'en') ? 'en-US' : ((lang === 'es') ? 'es-ES' : 'it-IT');
                lastDateFormatted = d.toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' }) +
                    " " + d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
            }
        }

        const lastText = `${t('pw-stat-last-label')} ${lastDateFormatted.toUpperCase()}`;
        const versionText = window.APP_VERSION ? `V${window.APP_VERSION}` : '';

        // Update DOM elements
        const statStart = document.getElementById('stat-start');
        const statVersion = document.getElementById('stat-version');
        const statLast = document.getElementById('stat-last');

        if (statStart) statStart.textContent = startText;
        if (statVersion) statVersion.textContent = versionText;
        if (statLast) statLast.textContent = lastText;

        this.render();
    }

    showWinnerLabel(screenX, screenY, name, type) {
        // Remove existing label if any
        const oldLabel = document.getElementById('winner-label');
        if (oldLabel) oldLabel.remove();

        const label = document.createElement('div');
        label.id = 'winner-label';
        label.className = 'winner-popup';

        const icon = type === 'gold' ? '🪙' : '<span class="hud-icon-silver">🪙</span>';
        label.innerHTML = `${icon} <strong>${name}</strong>`;

        // Calculate position (centered above click)
        label.style.left = `${screenX}px`;
        label.style.top = `${screenY - 20}px`;

        document.body.appendChild(label);

        // Auto remove after 4 seconds
        setTimeout(() => {
            if (label && label.parentNode) {
                label.style.opacity = '0';
                label.style.transform = 'translate(-50%, -10px)';
                setTimeout(() => label.remove(), 500);
            }
        }, 3000);
    }

    getDistance(t1, t2) {
        return Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
    }

    getMidpoint(t1, t2) {
        return {
            x: (t1.clientX + t2.clientX) / 2,
            y: (t1.clientY + t2.clientY) / 2
        };
    }

    /**
     * Dynamically positions the floating controls below the image preview in screen space.
     * Prevents overlapping with the image regardless of the zoom level.
     */
    updateFloatingControlsUI() {
        if (!this.floatingImage || !this.floatingPos) return;

        const controls = document.getElementById('floating-controls');
        if (!controls || controls.classList.contains('hidden')) return;

        // Calculate screen coordinates (relative to canvas-wrapper)
        // bx, by are the world coordinates of the image's top-left corner
        const bx = this.floatingPos.x;
        const by = this.floatingPos.y;
        const bw = this.floatingImage.width;
        const bh = this.floatingImage.height;

        // The center-x and bottom-y in screen coordinates
        const screenCenterX = this.translateX + (bx + bw / 2) * this.scale;
        const screenBottomY = this.translateY + (by + bh) * this.scale;

        const margin = 20; // 20px gap below the image
        const wrapH = this.wrapper.clientHeight;
        const wrapW = this.wrapper.clientWidth;

        // Target top position
        let targetTop = screenBottomY + margin;

        // Measurements of the controls
        const controlsH = controls.offsetHeight || 100;
        const controlsW = controls.offsetWidth || 300;
        const halfW = controlsW / 2;

        // Vertical Clamping: Ensure it stays within viewport
        const minGap = 20;
        if (targetTop + controlsH > wrapH - minGap) {
            targetTop = wrapH - controlsH - minGap;
        }
        // Also don't let it go too high (above top of screen)
        if (targetTop < minGap) targetTop = minGap;

        // Horizontal Clamping
        let targetLeft = screenCenterX;
        if (targetLeft - halfW < minGap) {
            targetLeft = halfW + minGap;
        } else if (targetLeft + halfW > wrapW - minGap) {
            targetLeft = wrapW - halfW - minGap;
        }

        // Apply styles
        controls.style.left = `${targetLeft}px`;
        controls.style.top = `${targetTop}px`;
        controls.style.bottom = 'auto'; // Disable original bottom positioning
        controls.style.transform = 'translateX(-50%)'; // Keep center alignment
    }

    /**
     * Resets the floating controls to their default CSS state.
     */
    resetFloatingControlsUI() {
        const controls = document.getElementById('floating-controls');
        if (controls) {
            controls.style.left = '';
            controls.style.top = '';
            controls.style.bottom = '';
            controls.style.transform = '';
        }
    }

    drawStar(key, color, pulse) {
        const [x, y] = key.split(',').map(Number);
        this.ctx.save();
        this.ctx.translate(x + 0.5, y + 0.5); // Center on the pixel

        const flareLen = 1.5 + 2 * pulse;
        const flareWidth = 0.3; // Tapering width (grid units)

        // 1. Atmospheric Glow (Radial Gradient)
        const grad = this.ctx.createRadialGradient(0, 0, 0, 0, 0, flareLen * 0.8);
        grad.addColorStop(0, color);
        grad.addColorStop(1, 'rgba(0,0,0,0)');

        this.ctx.save();
        this.ctx.globalAlpha = 0.2 * pulse;
        this.ctx.fillStyle = grad;
        this.ctx.beginPath();
        this.ctx.arc(0, 0, flareLen * 0.8, 0, Math.PI * 2);
        this.ctx.fill();
        this.ctx.restore();

        // 2. Tapered Flares (Diamond shapes for realism)
        this.ctx.fillStyle = color;
        this.ctx.globalAlpha = 0.4 + 0.6 * pulse;

        // Vertical Flare
        this.ctx.beginPath();
        this.ctx.moveTo(0, -flareLen);
        this.ctx.lineTo(flareWidth, 0);
        this.ctx.lineTo(0, flareLen);
        this.ctx.lineTo(-flareWidth, 0);
        this.ctx.fill();

        // Horizontal Flare
        this.ctx.beginPath();
        this.ctx.moveTo(-flareLen, 0);
        this.ctx.lineTo(0, -flareWidth);
        this.ctx.lineTo(flareLen, 0);
        this.ctx.lineTo(0, flareWidth);
        this.ctx.fill();

        // 3. Central Core (Brightest part)
        this.ctx.globalAlpha = 1.0;
        this.ctx.beginPath();
        this.ctx.arc(0, 0, 0.4, 0, Math.PI * 2);
        this.ctx.fill();

        this.ctx.restore();
    }
}

// Start the engine
window.madnessWall = new PixelWall();

// ==========================================
// UI & MODAL LOGIC (Extracted from index.html)
// ==========================================

// Functions to handle modals (Global scope)
window.showModal = function (m) {
    if (!m) return;
    m.style.display = 'flex';
    setTimeout(() => m.classList.add('active'), 10);
}
window.hideModal = function (m) {
    if (!m) return;
    m.classList.remove('active');
    setTimeout(() => m.style.display = 'none', 300);
}

document.addEventListener('DOMContentLoaded', () => {
    // Modal Logic
    const pModal = document.getElementById('pricing-modal');
    const mModal = document.getElementById('mission-modal');
    const wModal = document.getElementById('welcome-modal');
    const wallModal = document.getElementById('wall-modal');

    const openPricing = document.getElementById('open-pricing-modal');
    const openMission = document.getElementById('open-mission-modal');
    const openWall = document.getElementById('open-wall-modal');

    const closePricing = document.getElementById('close-pricing-modal');
    const closeMission = document.getElementById('close-mission-modal');
    const closeWelcome = document.getElementById('close-welcome-modal');
    const closeWall = document.getElementById('close-wall-modal');
    const ackWelcome = document.getElementById('ack-welcome');

    if (openPricing) openPricing.onclick = (e) => { e.stopPropagation(); showModal(pModal); };
    if (openMission) openMission.onclick = (e) => { e.stopPropagation(); showModal(mModal); };
    if (openWall) openWall.onclick = (e) => {
        e.stopPropagation();
        const iframe = wallModal.querySelector('iframe');
        const introText = wallModal.querySelector('p[data-i18n="pw-wall-intro"]');
        if (iframe) iframe.src = "pages/wordcloud.html";
        if (introText) introText.style.display = 'block';
        showModal(wallModal);
    };

    // Expose function for HUD clicks
    window.openDetailsModal = (type) => {
        const iframe = wallModal.querySelector('iframe');
        const introText = wallModal.querySelector('p[data-i18n="pw-wall-intro"]');

        if (type === 'gold') {
            iframe.src = "pages/winners.php";
            if (introText) introText.style.display = 'none';
        } else if (type === 'silver') {
            iframe.src = "pages/winners.php#silver";
            if (introText) introText.style.display = 'none';
        } else {
            // Default Wall
            iframe.src = "pages/wordcloud.html";
            if (introText) introText.style.display = 'block';
        }
        showModal(wallModal);
    };

    const hudGold = document.getElementById('hud-gold');
    const hudSilver = document.getElementById('hud-silver');
    if (hudGold) hudGold.onclick = (e) => { e.stopPropagation(); window.openDetailsModal('gold'); };
    if (hudSilver) hudSilver.onclick = (e) => { e.stopPropagation(); window.openDetailsModal('silver'); };

    if (closePricing) closePricing.onclick = () => hideModal(pModal);
    if (closeMission) closeMission.onclick = () => hideModal(mModal);
    if (closeWall) closeWall.onclick = () => hideModal(wallModal);

    // --- LIGHTBOX LOGIC (Top Level inside DOMContentLoaded) ---
    const lbOverlay = document.getElementById('lightbox-overlay');
    const lbImg = document.getElementById('lightbox-img');
    const lbClose = document.querySelector('.lightbox-close');

    window.openLightbox = (src) => {
        const lb = lbOverlay || document.getElementById('lightbox-overlay');
        const lbI = lbImg || document.getElementById('lightbox-img');
        if (!lb || !lbI) {
            console.error("Lightbox elements not found");
            return;
        }
        lbI.src = src;
        lb.style.display = 'flex';
        lb.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.hideLightbox = () => {
        const lb = lbOverlay || document.getElementById('lightbox-overlay');
        if (!lb) return;
        lb.classList.remove('active');
        lb.style.display = 'none';
        document.body.style.overflow = '';
    };

    if (lbClose) lbClose.onclick = window.hideLightbox;
    if (lbOverlay) {
        lbOverlay.onclick = (e) => {
            if (e.target !== lbImg) window.hideLightbox();
        };
    }

    window.onclick = (event) => {
        if (event.target == pModal) hideModal(pModal);
        if (event.target == mModal) hideModal(mModal);
        if (event.target == wModal) hideModal(wModal);
        if (event.target == wallModal) hideModal(wallModal);
        const dModal = document.getElementById('diary-modal'); // Define locally or ensure visibility
        if (dModal && event.target == dModal) hideModal(dModal);
    };

    if (closeWelcome) closeWelcome.onclick = () => hideModal(wModal);
    if (ackWelcome) ackWelcome.onclick = () => hideModal(wModal);

    // Show Welcome Modal on Load (immediately)
    if (wModal) {
        showModal(wModal);
        // Trigger animation manually to ensure it runs
        const card = wModal.querySelector('.welcome-card');
        if (card) {
            // Reset animation just in case
            card.classList.remove('animate-border');
            void card.offsetWidth; // Trigger reflow
            card.classList.add('animate-border');
        }
    }

    // Clean session on first load of the new version if needed
    // v4 is legacy, v6 is current focus

    // Language Switcher Logic
    window.setLanguage = function (lang) {
        const toggleBtn = document.getElementById('lang-toggle');

        // Prepare replacement data
        const settings = window.madnessWall ? {
            land_price_eur: (window.madnessWall.settings?.pricing?.land_rate_cents / 100 || 0.20).toFixed(2),
            ink_price_eur: (window.madnessWall.settings?.pricing?.ink_rate_cents / 100 || 0.30).toFixed(2),
            wall_width_m: (window.madnessWall.gridWidth / 100 || 10).toFixed(0),
            wall_height_m: (window.madnessWall.gridHeight / 100 || 4).toFixed(0)
        } : {
            land_price_eur: "0.20",
            ink_price_eur: "0.30",
            wall_width_m: "10",
            wall_height_m: "4"
        };

        document.querySelectorAll('[data-i18n]').forEach(el => {
            let key = el.getAttribute('data-i18n');
            let attr = null;

            if (key.startsWith('[')) {
                const endBracket = key.indexOf(']');
                if (endBracket !== -1) {
                    attr = key.substring(1, endBracket);
                    key = key.substring(endBracket + 1);
                }
            }

            if (window.translations && window.translations[lang] && window.translations[lang][key]) {
                let text = window.translations[lang][key];

                // Replace placeholders like {wall_width_m}
                Object.keys(settings).forEach(sKey => {
                    text = text.replace(new RegExp(`{${sKey}}`, 'g'), settings[sKey]);
                });

                if (attr) {
                    el.setAttribute(attr, text);
                } else {
                    el.innerHTML = text;
                }
            }
        });
        // Update Canvas Title specifically
        const titleEl = document.querySelector('.canvas-title');
        if (titleEl && window.translations && window.translations[lang] && window.translations[lang]["canvas-title"]) {
            titleEl.textContent = window.translations[lang]["canvas-title"];
        }

        document.documentElement.lang = lang;

        // Update branding for the new language
        if (window.madnessWall && typeof window.madnessWall.applyBranding === 'function') {
            window.madnessWall.applyBranding();
        }

        // Update Pixel Wall dynamic stats if present
        if (window.madnessWall && typeof window.madnessWall.updateStatusText === 'function') {
            window.madnessWall.updateStatusText();
        }

        localStorage.setItem('mr_lang', lang);

        // Sync with GDPR Banner if present
        if (window.ConsentManager && typeof window.ConsentManager.setLanguage === 'function') {
            window.ConsentManager.setLanguage(lang);
        }
    }

    const langBtn = document.getElementById('lang-toggle');
    if (langBtn) {
        langBtn.addEventListener('click', () => {
            const current = document.documentElement.lang || 'it';
            // Cycle: it -> en -> es -> it
            let nextLang = 'it';
            if (current === 'it') nextLang = 'en';
            else if (current === 'en') nextLang = 'es';
            else if (current === 'es') nextLang = 'it';

            setLanguage(nextLang);
        });
    }

    // Initialize Lang
    let savedLang = localStorage.getItem('mr_lang');
    if (!savedLang) {
        const browserLang = navigator.language.split('-')[0];
        // Support it, en, es (default to en for others)
        if (['it', 'en', 'es'].includes(browserLang)) {
            savedLang = browserLang;
        } else {
            savedLang = 'en'; // Global default
        }
    }
    // Listen for close commands from iFrames (e.g. Wall of Fame)
    window.addEventListener('message', (event) => {
        if (event.data === 'closeWallModal') {
            const modal = document.getElementById('wall-modal');
            if (modal) modal.style.display = 'none';
        }
    });

    // --- DIARY LOGIC ---
    const dModal = document.getElementById('diary-modal');
    const openDiary = document.getElementById('open-diary-btn');
    const closeDiary = document.getElementById('close-diary-modal');
    const diaryContent = document.getElementById('diary-content');

    if (openDiary) {
        openDiary.onclick = (e) => {
            e.stopPropagation();
            if (dModal) {
                showModal(dModal);
                loadDiary();
            }
        };
    }

    if (closeDiary) {
        closeDiary.onclick = () => {
            if (dModal) hideModal(dModal);
        }
    }

    async function loadDiary() {
        if (!diaryContent) return;
        diaryContent.innerHTML = '<p class="diary-empty" style="animation: pulse 1s infinite;">System Accessing...</p>';

        try {
            // Append timestamp to prevent caching
            const res = await fetch(`data/updates.json?t=${new Date().getTime()}`);
            if (!res.ok) throw new Error("Log not found");
            let posts = await res.json();

            // Safety check: ensure posts is always an array
            // (PHP json_encode returns an object if array keys are non-sequential/preserved)
            if (posts && !Array.isArray(posts)) {
                posts = Object.values(posts);
            }

            if (!posts || posts.length === 0) {
                diaryContent.innerHTML = '<p class="diary-empty">No entries found.</p>';
                return;
            }

            let html = '';
            // Sort by date desc (optional, assuming JSON is chronological or manual)
            // posts.reverse(); 

            // SECURITY: Basic escape for admin-provided fields that shouldn't contain HTML
            const escape = (s) => (s || '').replace(/</g, "&lt;").replace(/>/g, "&gt;");

            // Track user reactions in localStorage to prevent spam and show active state
            const userReactions = JSON.parse(localStorage.getItem('pixel_wall_reactions') || '{}');

            posts.forEach(post => {
                const postId = post.id || '';
                const linkLabel = post.link_label || 'Scopri di più';
                const linkHtml = post.link ? `<a href="${post.link}" target="_blank" class="diary-link">🔗 ${linkLabel}</a>` : '';
                const imgHtml = post.image ? `
                    <div class="diary-image-link" title="Visualizza a dimensioni reali">
                        <img src="${post.image}" class="diary-img" alt="${escape(post.title)}">
                    </div>` : '';

                const hasLiked = userReactions[postId] === 'likes';
                const hasHearted = userReactions[postId] === 'hearts';

                html += `
                    <div class="diary-entry" data-id="${postId}">
                        <span class="diary-date">&gt; ${escape(post.date)}</span>
                        <div class="diary-title">${escape(post.title)}</div>
                        <div class="diary-text">${post.content || ''}</div>
                        ${imgHtml}
                        ${linkHtml}
                        
                        <div class="diary-reactions">
                            <button class="reaction-btn like-btn ${hasLiked ? 'active' : ''}" data-reaction="likes">
                                👍 <span class="reaction-count">${post.likes || 0}</span>
                            </button>
                            <button class="reaction-btn heart-btn ${hasHearted ? 'active' : ''}" data-reaction="hearts">
                                ❤️ <span class="reaction-count">${post.hearts || 0}</span>
                            </button>
                        </div>
                    </div>
                 `;
            });
            // Process content to make iframes responsive and skip Iubenda blocking if desired
            let processedContent = html;
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = processedContent;

            tempDiv.querySelectorAll('iframe').forEach(iframe => {
                // Add Iubenda skip class to avoid "Content Blocked" message before consent
                iframe.classList.add("_iub_cs_skip");

                // Wrap in responsive container
                const wrapper = document.createElement("div");
                wrapper.className = "iframe-container";

                // Get original width/height to set as max-width if they exist
                const origWidth = iframe.getAttribute('width');
                if (origWidth && !origWidth.includes('%')) {
                    wrapper.style.maxWidth = origWidth + 'px';

                    // Also adjust padding-bottom if height is specified to maintain correct aspect ratio
                    const origHeight = iframe.getAttribute('height');
                    if (origHeight) {
                        const ratio = (parseInt(origHeight) / parseInt(origWidth)) * 100;
                        if (!isNaN(ratio)) {
                            wrapper.style.paddingBottom = ratio + '%';
                        }
                    }
                }

                iframe.parentNode.insertBefore(wrapper, iframe);
                wrapper.appendChild(iframe);
            });

            diaryContent.innerHTML = tempDiv.innerHTML;
        } catch (e) {
            console.error("Diary load error", e);
            diaryContent.innerHTML = '<p class="diary-error">SYSTEM ERROR: Connection Lost.</p>';
        }
    }

    // --- DIARY EVENT DELEGATION (Attached once) ---
    if (diaryContent) {
        diaryContent.addEventListener('click', async (e) => {
            // 1. Lightbox / Image Click
            const imgLink = e.target.closest('.diary-image-link');
            if (imgLink) {
                e.preventDefault();
                e.stopPropagation();
                const img = imgLink.querySelector('img');
                if (img && typeof window.openLightbox === 'function') {
                    window.openLightbox(img.src);
                }
                return;
            }

            // 2. Reaction Click
            const btn = e.target.closest('.reaction-btn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                const reaction = btn.getAttribute('data-reaction');
                const entry = btn.closest('.diary-entry');
                const postId = entry.getAttribute('data-id');

                const localReactions = JSON.parse(localStorage.getItem('pixel_wall_reactions') || '{}');
                const previousReaction = localReactions[postId];

                if (previousReaction === reaction) return;

                try {
                    const res = await fetch('api/wall-api.php?type=reactions', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: postId,
                            add: reaction,
                            remove: previousReaction || null
                        })
                    });

                    if (res.ok) {
                        const countEl = btn.querySelector('.reaction-count');
                        countEl.textContent = parseInt(countEl.textContent || '0') + 1;
                        btn.classList.add('active');

                        if (previousReaction) {
                            const otherBtn = entry.querySelector(`.reaction-btn[data-reaction="${previousReaction}"]`);
                            if (otherBtn) {
                                const otherCountEl = otherBtn.querySelector('.reaction-count');
                                otherCountEl.textContent = Math.max(0, parseInt(otherCountEl.textContent || '0') - 1);
                                otherBtn.classList.remove('active');
                            }
                        }

                        localReactions[postId] = reaction;
                        localStorage.setItem('pixel_wall_reactions', JSON.stringify(localReactions));
                    }
                } catch (err) {
                    console.error("Reaction error", err);
                }
            }
        });
    }

    setLanguage(savedLang);
});
