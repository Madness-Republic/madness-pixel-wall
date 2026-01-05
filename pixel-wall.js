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
        this.activeTool = 'pan'; // 'draw' or 'pan'

        this.lastX = 0;
        this.lastY = 0;

        // Pixel Data
        this.confirmedMap = new Map(); // key: "x,y", value: color (PERMANENT WALL)
        this.pixelMap = new Map();     // key: "x,y", value: color (USER WORK-IN-PROGRESS)

        this.history = []; // stack of actions for undo
        this.currentStroke = [];
        this.boundingBox = null;
        this.floatingImage = null;
        this.floatingPos = { x: 0, y: 0 };

        // Optimization: Offscreen Canvas for static wall
        this.cacheCanvas = document.createElement('canvas');
        this.cacheCanvas.width = this.gridWidth;
        this.cacheCanvas.height = this.gridHeight;
        this.cacheCtx = this.cacheCanvas.getContext('2d', { alpha: false }); // No transparency needed for base
        // Fill white initially
        this.cacheCtx.fillStyle = '#ffffff';
        this.cacheCtx.fillRect(0, 0, this.gridWidth, this.gridHeight);

        this.init();
    }

    async init() {
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
    }

    async loadData() {
        this.serverLoadedSuccess = false;
        this.presetMap = new Map();

        // 0. Load PRESET (Default Locked Pixels)
        try {
            const res = await fetch('data/wall_preset.json');
            if (res.ok) {
                const data = await res.json();
                Object.keys(data).forEach(key => this.presetMap.set(key, data[key]));
            }
        } catch (e) {
            console.error("Error loading wall_preset.json", e);
        }

        // OPTIMIZATION: Pre-calculate forbidden border (4 pixel radius)
        this.forbiddenSet = new Set();
        if (this.presetMap.size > 0) {
            const border = 4;
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
        let apiUrl = 'wall-api.php';

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
            localStorage.removeItem('madness_wall_confirmed'); // Nuke old fallback data too
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
                }
            }
        }, { passive: false });

        window.addEventListener('touchend', () => this.handleEnd());

        // Zoom
        this.wrapper.addEventListener('wheel', (e) => this.handleWheel(e), { passive: false });

        // Zoom Buttons
        document.getElementById('zoom-in').onclick = () => this.applyZoom(1.5);
        document.getElementById('zoom-out').onclick = () => this.applyZoom(0.75);
        document.getElementById('zoom-reset').onclick = () => this.resetView();

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
            document.getElementById('tool-draw').style.background = 'var(--accent-orange)';
            document.getElementById('tool-pan').style.background = '#333';
            const upBtn = document.getElementById('tool-upload');
            if (upBtn) upBtn.style.border = 'none';
            // Hide Sticker Controls if active
            const floatCtrl = document.getElementById('floating-controls');
            if (floatCtrl) floatCtrl.style.display = 'none';
            this.render();
        };

        document.getElementById('tool-pan').onclick = (e) => {
            e.stopPropagation();
            this.activeTool = 'pan';
            this.floatingImage = null; // Cancel upload
            document.getElementById('tool-pan').style.background = 'var(--accent-turquoise)';
            document.getElementById('tool-draw').style.background = '#333';
            const upBtn = document.getElementById('tool-upload');
            if (upBtn) upBtn.style.border = 'none';
            // Hide Sticker Controls if active
            const floatCtrl = document.getElementById('floating-controls');
            if (floatCtrl) floatCtrl.style.display = 'none';
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

        const uploadBtn = document.getElementById('tool-upload');
        const fileInput = document.getElementById('file-input');

        if (uploadBtn && fileInput) {
            uploadBtn.onclick = (e) => {
                e.stopPropagation();
                fileInput.click();
            };

            fileInput.onchange = (e) => {
                if (e.target.files && e.target.files[0]) {
                    const file = e.target.files[0];
                    const MAX_SIZE_BYTES = 1024 * 1024; // 1MB

                    if (file.size > MAX_SIZE_BYTES) {
                        const lang = document.documentElement.lang || 'it';
                        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;
                        alert(t('error-image-size'));
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
            this.handleCheckout();
        };

        // Community HUD Interactions
        // Note: Click events are now handled directly in HTML via openDetailsModal()
        // for both Gold and Silver HUD items.


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
                    alert(t('error-image-dimensions'));
                    return;
                }

                const aspectRatio = img.width / img.height;
                const heightInput = prompt(t('prompt-height'), '20');

                if (heightInput !== null) {
                    let heightCm = parseInt(heightInput, 10);
                    if (isNaN(heightCm) || heightCm <= 0) heightCm = 20; // Default

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
                        controls.style.display = 'flex';
                        // Force update text for these elements in case language switched while hidden
                        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;

                        const pEl = controls.querySelector('[data-i18n="pw-sticker-instr"]');
                        if (pEl) pEl.textContent = t('pw-sticker-instr');

                        const btnConf = document.getElementById('floating-confirm');
                        if (btnConf) btnConf.textContent = t('pw-btn-confirm');

                        const btnCanc = document.getElementById('floating-cancel');
                        if (btnCanc) btnCanc.textContent = t('pw-btn-cancel');
                    }

                    // Update UI Buttons
                    document.getElementById('tool-draw').style.background = '#333';
                    document.getElementById('tool-pan').style.background = '#333';
                    document.getElementById('tool-upload').style.border = '2px solid white';

                    this.render();
                }
            };
            img.onerror = () => {
                alert(t('error-image-load'));
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    async handleCheckout() {
        const lang = document.documentElement.lang || 'it';
        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;

        if (this.pixelMap.size === 0) {
            alert(t('pw-alert-draw'));
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
            if (x < minX) minX = x; else if (x > maxX) maxX = x;
            if (y < minY) minY = y; else if (y > maxY) maxY = y;
        });
        const width = (maxX - minX) + 1;
        const height = (maxY - minY + 1);
        const area = width * height;
        const pixels = this.pixelMap.size;
        const amount = (area * 0.20) + (pixels * 0.30);

        totalDisplay.textContent = `€ ${amount.toFixed(2)}`;

        // 2. Initialize Stripe
        // 2. Initialize Stripe
        if (!this.stripe) {
            try {
                // Dynamically load Public Key to ensure consistency with Backend (Test/Live)
                const res = await fetch('get_public_key.php');
                if (!res.ok) throw new Error("Key fetch failed");
                const data = await res.json();
                this.stripe = Stripe(data.publicKey);
            } catch (e) {
                console.error("Stripe Init Failed", e);
                alert("Errore Inizializzazione Pagamento: Impossibile contattare il server. Riprova più tardi.");
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
            const response = await fetch('create-payment-intent.php', {
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
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (!email || !emailRegex.test(email)) {
                    alert('Inserisci un\'email valida per la ricevuta (es. nome@dominio.com).');
                    return;
                }

                submitBtn.disabled = true;
                btnText.classList.add('hidden');
                spinner.classList.remove('hidden');
                messageDiv.classList.add('hidden');

                // SAVE TO LOCAL STORAGE BEFORE REDIRECT (Critical for 3DS / Redirect flows)
                localStorage.setItem('madness_checkout_email', email);
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
                    const saveRes = await this.savePixelsToBackend(email, paymentIntent.id);
                    // 2. Save Transaction Stats (Date & Money) - But NOT Contributor Name yet
                    await this.saveTransactionServer(paymentIntent.id, email, amount);

                    if (saveRes && saveRes.isWinner) {
                        this.showWinnerModal(email, amount, paymentIntent.id);
                    } else if (saveRes && saveRes.isSilverWinner) {
                        this.showSilverWinnerModal(email, amount, paymentIntent.id);
                    } else {
                        // 3. Move to Signature Step (Pass txnId for optional sign)
                        this.showSignatureModal(email, amount, paymentIntent.id);
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

    async savePixelsToBackend(email, txnId) {
        const pixelData = Object.fromEntries(this.pixelMap);

        // Enriched Payload for Ownership Tracking
        const payload = {
            pixels: pixelData,
            meta: {
                email: email,
                txnId: txnId
            }
        };

        try {
            const res = await fetch('wall-api.php', {
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

    async saveTransactionServer(txnId, email, amount) {
        try {
            await fetch('wall-api.php?type=transactions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: txnId,
                    email: email,
                    amount: amount
                })
            });
            // Refresh stats immediately so date appears (from transaction log)
            this.updateGlobalStats();
        } catch (e) {
            console.error("Error saving transaction", e);
        }
    }

    async saveContributorSign(txnId, name, amount) {
        try {
            await fetch('wall-api.php?type=contributors', {
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
                const res = await fetch('get_public_key.php');
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
            const amount = parseFloat(localStorage.getItem('madness_checkout_amount')) || (paymentIntent.amount / 100);

            if (msg) msg.textContent = "Pagamento Confermato! Salvataggio...";

            // 3. Save to Backend
            try {
                // IMPORTANT: this.pixelMap was loaded in constructor/loadData from 'madness_pixels'
                if (this.pixelMap.size > 0) {
                    const saveRes = await this.savePixelsToBackend(email, paymentIntent.id);
                    await this.saveTransactionServer(paymentIntent.id, email, amount);

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
            console.log("Payment not succeeded yet:", paymentIntent ? paymentIntent.status : "unknown");
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

        // Disable close button to prevent closing without decision (optional, but cleaner)
        // document.getElementById('close-checkout-modal').style.display = 'none'; 

        modalTitle.innerText = "🎉 " + t('pw-success-title');

        modalBody.innerHTML = `
            <div style="text-align:center;">
                <div style="font-size: 3rem; color: #4dff88; margin-bottom: 10px;">✓</div>
                <p style="margin-bottom: 20px;">${t('pw-success-msg').replace('{amount}', amount.toFixed(2))}</p>
                
                <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px; border: 1px dashed var(--accent-purple); margin-bottom: 15px;">
                    <label style="display:block; margin-bottom: 10px; color: var(--accent-turquoise); font-size: 1rem; font-weight: bold;">${t('pw-sign-label')}</label>
                    <p style="font-size: 0.8rem; color: #aaa; margin-bottom: 15px;">Scrivi il tuo nome (o nickname) per apparire nella Word Cloud dei sostenitori! (Facoltativo)</p>
                    
                    <input type="text" id="wall-name-input" placeholder="${t('pw-name-placeholder')}" maxlength="25"
                        style="width: 80%; padding: 12px; border-radius: 5px; border: none; font-family: inherit; margin-bottom: 15px; text-align: center; font-size: 1.1rem; color: black; font-weight: bold;">
                    
                    <button id="sign-fame-btn" class="buy-btn" style="background: var(--accent-purple); width: 100%; box-shadow: 0 4px 15px rgba(120, 0, 200, 0.4);">${t('pw-sign-btn')}</button>
                </div>
                
                <button id="close-success" style="background:transparent; border:none; color: #888; font-size: 0.85rem; cursor: pointer; text-decoration: underline; margin-top: 10px;">${t('pw-skip-btn')}</button>
            </div>
        `;

        // Logic for Signature
        document.getElementById('sign-fame-btn').onclick = async () => {
            const name = document.getElementById('wall-name-input').value.trim();
            if (!name) { alert(t('pw-error-name')); return; }

            document.getElementById('sign-fame-btn').textContent = "...";

            try {
                // Save Name to Wall of Fame (Contributors)
                await this.saveContributorSign(txnId, name, amount);

                // Final Clean
                this.pixelMap.clear();
                localStorage.removeItem('madness_pixels');
                window.location.replace('wordcloud.html'); // Redirect to see the name!

            } catch (e) {
                console.error(e);
                alert("Errore nel salvataggio della firma.");
            }
        };

        // Logic for Skip
        document.getElementById('close-success').onclick = () => {
            this.pixelMap.clear();
            localStorage.removeItem('madness_pixels');
            location.reload();
        };

        // Also handle close X button to reload
        document.getElementById('close-checkout-modal').onclick = () => {
            this.pixelMap.clear();
            localStorage.removeItem('madness_pixels');
            location.reload();
        };
    }

    showWinnerModal(email, amount, txnId) {
        const lang = document.documentElement.lang || 'it';
        const t = (k) => (window.translations && window.translations[lang] && window.translations[lang][k]) ? window.translations[lang][k] : k;

        const modalBody = document.querySelector('#checkout-modal .modal-body');
        const modalTitle = document.querySelector('#checkout-modal .modal-title');

        // Setup Golden Theme
        modalTitle.innerText = t('pw-win-gold-title');
        modalTitle.style.color = '#FFD700';
        modalTitle.style.textShadow = '0 0 20px #FFD700';
        modalTitle.style.fontSize = '1.5rem';

        modalBody.innerHTML = `
            <div style="text-align:center;">
                <div style="font-size: 4rem; margin-bottom: 10px; animation: bounce 1s infinite alternate;">🪙</div>
                <h3 style="color: #FFD700; margin-bottom: 15px; font-family: 'Oswald', sans-serif; text-transform: uppercase;">${t('pw-congrats')}</h3>
                <p style="margin-bottom: 20px; font-size: 1.1rem; line-height: 1.4;">${t('pw-win-gold-msg')}</p>
                <p style="margin-bottom: 20px; color: #ccc; font-size: 0.9rem; border-top: 1px solid #333; border-bottom: 1px solid #333; padding: 10px 0;">
                   ${t('pw-win-gold-info')}
                   <br><br><span style="color: #FFD700;">${t('pw-email-confirm')}${email}</span>
                </p>
                
                <div style="background: rgba(255, 215, 0, 0.05); padding: 20px; border-radius: 10px; border: 1px dashed #FFD700; margin-bottom: 15px;">
                    <label style="display:block; margin-bottom: 10px; color: #FFD700; font-size: 1rem; font-weight: bold;">${t('pw-sign-label')}</label>
                    <p style="font-size: 0.8rem; color: #aaa; margin-bottom: 15px;">${t('pw-sign-fame-instr')}</p>
                    
                    <input type="text" id="wall-name-input" placeholder="${t('pw-name-placeholder')}" maxlength="25"
                        style="width: 80%; padding: 12px; border-radius: 5px; border: 1px solid #555; font-family: inherit; margin-bottom: 15px; text-align: center; font-size: 1.1rem; color: white; background: #000; font-weight: bold;">
                    
                    <button id="sign-fame-btn" class="buy-btn" style="background: linear-gradient(135deg, #FFD700, #ff8c00); color: black; width: 100%; box-shadow: 0 0 20px rgba(255, 215, 0, 0.3); font-weight: bold; border:none;">${t('pw-sign-btn')}</button>
                </div>
                
                <button id="close-success" style="background:transparent; border:none; color: #888; font-size: 0.85rem; cursor: pointer; text-decoration: underline; margin-top: 10px;">${t('pw-skip-btn')}</button>
            </div>
            <style>
            @keyframes bounce { from { transform: translateY(0); } to { transform: translateY(-10px); } }
            #wall-name-input:focus { border-color: #FFD700; outline: none; }
            </style>
        `;

        // Logic for Signature
        document.getElementById('sign-fame-btn').onclick = async () => {
            const name = document.getElementById('wall-name-input').value.trim();
            if (!name) { alert(t('pw-error-name')); return; }

            document.getElementById('sign-fame-btn').textContent = "...";
            try {
                await this.saveContributorSign(txnId, name, amount);
                this.pixelMap.clear();
                localStorage.removeItem('madness_pixels');
                window.location.replace('wordcloud.html');
            } catch (e) {
                console.error(e);
                alert(t('pw-error-save-short'));
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

        // Setup Silver Theme
        modalTitle.innerText = t('pw-win-silver-title');
        modalTitle.style.color = '#C0C0C0'; // Silver
        modalTitle.style.textShadow = '0 0 20px #C0C0C0';
        modalTitle.style.fontSize = '1.5rem';

        modalBody.innerHTML = `
            <div style="text-align:center;">
                <div style="font-size: 4rem; margin-bottom: 10px; animation: bounce 1s infinite alternate; filter: grayscale(100%) contrast(1.2);">🪙</div> 
                <h3 style="color: #C0C0C0; margin-bottom: 15px; font-family: 'Oswald', sans-serif; text-transform: uppercase;">${t('pw-fantastic')}</h3>
                <p style="margin-bottom: 20px; font-size: 1.1rem; line-height: 1.4;">${t('pw-win-silver-msg')}</p>
                <p style="margin-bottom: 20px; color: #ccc; font-size: 0.9rem; border-top: 1px solid #333; border-bottom: 1px solid #333; padding: 10px 0;">
                   ${t('pw-win-silver-info')}
                   <br><br><span style="color: #C0C0C0;">${t('pw-email-check')}${email}</span>
                </p>
                
                <div style="background: rgba(192, 192, 192, 0.05); padding: 20px; border-radius: 10px; border: 1px dashed #C0C0C0; margin-bottom: 15px;">
                    <label style="display:block; margin-bottom: 10px; color: #C0C0C0; font-size: 1rem; font-weight: bold;">${t('pw-sign-label')}</label>
                    <p style="font-size: 0.8rem; color: #aaa; margin-bottom: 15px;">${t('pw-sign-fame-instr')}</p>
                    
                    <input type="text" id="wall-name-input" placeholder="${t('pw-name-placeholder')}" maxlength="25"
                        style="width: 80%; padding: 12px; border-radius: 5px; border: 1px solid #555; font-family: inherit; margin-bottom: 15px; text-align: center; font-size: 1.1rem; color: white; background: #000; font-weight: bold;">
                    
                    <button id="sign-fame-btn" class="buy-btn" style="background: linear-gradient(135deg, #C0C0C0, #808080); color: black; width: 100%; box-shadow: 0 0 20px rgba(192, 192, 192, 0.3); font-weight: bold; border:none;">${t('pw-sign-btn')}</button>
                </div>
                
                <button id="close-success" style="background:transparent; border:none; color: #888; font-size: 0.85rem; cursor: pointer; text-decoration: underline; margin-top: 10px;">${t('pw-skip-btn')}</button>
            </div>
            <style>
            @keyframes bounce { from { transform: translateY(0); } to { transform: translateY(-10px); } }
            #wall-name-input:focus { border-color: #C0C0C0; outline: none; }
            </style>
        `;

        // Logic for Signature (same as Gold)
        document.getElementById('sign-fame-btn').onclick = async () => {
            const name = document.getElementById('wall-name-input').value.trim();
            if (!name) { alert(t('pw-error-name')); return; }

            document.getElementById('sign-fame-btn').textContent = "...";
            try {
                await this.saveContributorSign(txnId, name, amount);
                this.pixelMap.clear();
                localStorage.removeItem('madness_pixels');
                window.location.replace('wordcloud.html');
            } catch (e) {
                console.error(e);
                alert(t('pw-error-save-short'));
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
        const isMobile = window.innerWidth < 768;

        if (isMobile) {
            // --- MOBILE SIMPLIFIED LOGIC ---
            // Just fit the board width with some padding, ignoring complex subtractive math
            const marginX = 20; // 10px each side
            const safeW = wrapW - marginX;

            // Calculate scale primarily based on width to ensure readability
            // But check height too so it doesn't overflow vertically if the screen is super short
            const availH = wrapH - 250; // Reserve header + footer space roughly

            let scaleX = safeW / this.gridWidth;
            let scaleY = availH / (this.gridHeight + 50); // +50 for title

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
            const statsWidth = 240;
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
        if (confirm("Sei sicuro di voler cancellare tutto il tuo disegno?")) {
            this.pixelMap.clear();
            this.history = [];
            this.saveSession();
            this.updateStats(); // Update stats (and clear bounding box) FIRST
            this.render(); // Then render the clean slate
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

        if (this.activeTool === 'pan' || (this.activeTool === 'upload' && this.floatingImage)) {
            // Allow panning in upload mode to move world under sticker
            this.isPanning = true;
        } else if (this.activeTool === 'upload') {
            // Should not happen if floatingImage is null, but safety
        } else {
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

        if (this.activeTool === 'draw') {
            const isProtected = this.confirmedMap.has(key) || this.isNearPreset(x, y);
            this.wrapper.style.cursor = isProtected ? 'not-allowed' : 'crosshair';
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
            alert(t('error-collision'));
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

            this.saveSession();
            this.updateStats();

            // Reset State
            this.floatingImage = null;
            this.activeTool = 'pan';
            document.getElementById('tool-pan').click(); // Update UI

            // Hide Controls
            document.getElementById('floating-controls').style.display = 'none';

            this.render();
        }
    }

    cancelFloatingImage() {
        this.floatingImage = null;
        this.activeTool = 'pan';
        document.getElementById('tool-pan').click();
        document.getElementById('floating-controls').style.display = 'none';
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

            // SAFETY BORDER: Prevent drawing adjacent to Preset Pixels
            // Create a 4px buffer of white, inaccessible pixels
            if (this.isNearPreset(x, y)) {
                return;
            }

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
        const area = width * height;
        const pixelParams = this.pixelMap.size;

        // --- HYBRID PRICING MODEL ---
        // Land Tax: 0.20€ per cm² (Area)
        // Ink Fee: 0.30€ per Pixel drawn
        const landCost = area * 0.20;
        const inkCost = pixelParams * 0.30;
        const totalPrice = landCost + inkCost;

        document.querySelectorAll('.val-pixels-count').forEach(el => el.textContent = `${area.toLocaleString()} cm² (${pixelParams} px)`);
        document.querySelectorAll('.val-price-count').forEach(el => el.textContent = `€ ${totalPrice.toFixed(2)}`);
    }

    render() {
        // ... (render method is unchanged, but tool uses context matching so I should not include it unless changing)
        // Oops, I can't just skip render in replacement content if I selected it in Target. 
        // I should have selected only pixelAt and updateStats? 
        // Wait, I see I am replacing pixelAt to updateStats.
        // Let me check my TargetContent.
        // I will replace pixelAt... up to updateGlobalStats start.
    }

    // Correction: I'll do this in 2 chunks or verify TargetContent carefully.
    // Chunk 1: pixelAt + isNearPreset + updateStats (partial)
    // Chunk 2: updateGlobalStats

    // Let's do updateGlobalStats first as a separate request to be cleaner? Or combine?
    // I already included updateStats in the replacement content above but stopped at render.
    // I will use a precise TargetContent for pixelAt -> updateStats end.

    // I need to View File for precise lines? I did view it.
    // pixelAt starts at 1038. updateStats ends at 1098.
    // I will replace 1038 to 1098.

    // Wait, I also need to update handleMove (cursor). It is at 898.
    // So 3 spots:
    // 1. handleMove (898)
    // 2. pixelAt (1038) + isNearPreset (new)
    // 3. updateGlobalStats (1284)

    // I will use MultiReplace.


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
        const area = width * height;
        const pixelParams = this.pixelMap.size;

        // --- HYBRID PRICING MODEL ---
        // Land Tax: 0.20€ per cm² (Area)
        // Ink Fee: 0.30€ per Pixel drawn
        const landCost = area * 0.20;
        const inkCost = pixelParams * 0.30;
        const totalPrice = landCost + inkCost;

        document.querySelectorAll('.val-pixels-count').forEach(el => el.textContent = `${area.toLocaleString()} cm² (${pixelParams} px)`);
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
        this.ctx.drawImage(this.cacheCanvas, 0, 0);

        // --- Draw Session Pixels (The Draft) ---
        this.pixelMap.forEach((color, key) => {
            const [x, y] = key.split(',').map(Number);
            this.ctx.fillStyle = color;
            this.ctx.fillRect(x, y, 1, 1);
        });

        // --- Draw Bounding Box (Pricing Area) ---
        if (this.boundingBox) {
            this.ctx.strokeStyle = '#ff4d4d'; // Accent Orange
            this.ctx.lineWidth = 1 / this.scale;
            this.ctx.setLineDash([2 / this.scale, 2 / this.scale]);
            this.ctx.strokeRect(
                this.boundingBox.x - 0.5,
                this.boundingBox.y - 0.5,
                this.boundingBox.w + 1,
                this.boundingBox.h + 1
            );
            this.ctx.setLineDash([]);
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
            const titleY = this.translateY - (25 * this.scale); // Gap scales with board

            title.style.left = `${titleX}px`;
            title.style.top = `${titleY}px`;

            // Scale title with board, but clamp minimum size for readability
            // We use transform translate center-bottom to anchor it
            title.style.transform = `translate(-50%, -100%) scale(${Math.max(0.5, this.scale)})`;
            title.classList.add('visible');
        }

        // 4. Stats Labels (Start & Last Contribution) - Below Board
        if (this.statStartText || this.statLastText) {
            this.ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
            this.ctx.font = '12px Oswald';
            this.ctx.textBaseline = 'top';

            const yPos = this.translateY + (this.gridHeight * this.scale) + 8; // 8px below board

            // Start Date (Left aligned)
            if (this.statStartText) {
                this.ctx.textAlign = 'left';
                this.ctx.fillText(this.statStartText, this.translateX, yPos);
            }

            // Last Contribution (Right aligned)
            if (this.statLastText) {
                this.ctx.textAlign = 'right';
                // Ensure it stays visibllllard is small, but anchored to right edge
                this.ctx.fillText(this.statLastText, this.translateX + (this.gridWidth * this.scale), yPos);
            }
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
            const res = await fetch('wall-api.php?type=transactions&stats=1');
            if (res.ok) {
                const stats = await res.json();
                const moneyEl = document.getElementById('total-raised-hud');
                if (moneyEl) {
                    moneyEl.textContent = `€ ${stats.total_raised.toLocaleString(undefined, {
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

        this.statStartText = `${t('pw-stat-start-label')} ${t('pw-stat-start-val')}`;

        let lastDateFormatted = "-";

        // Use Global Stats Date if available (more reliable/fast)
        if (this.lastGlobalStats && this.lastGlobalStats.last_contribution) {
            const d = new Date(this.lastGlobalStats.last_contribution);
            const locale = lang === 'en' ? 'en-US' : 'it-IT';
            // Include time in formatting
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
                const locale = lang === 'en' ? 'en-US' : 'it-IT';
                lastDateFormatted = d.toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' }) +
                    " " + d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
            }
        }

        this.statLastText = `${t('pw-stat-last-label')} ${lastDateFormatted.toUpperCase()}`;

        this.render();
    }
}

// Start the engine
// Start the engine
window.madnessWall = new PixelWall();
