/** Error Modal v2.3.0 */
// ==========================================
// ERROR MODAL (Dynamic Injection)
// ==========================================
window.showError = function (msg) {
    let errModal = document.getElementById('error-modal');

    // Helper to close modal safely
    const closeErrModal = () => {
        if (typeof window.hideModal === 'function') {
            window.hideModal(errModal);
        } else {
            errModal.classList.remove('active');
            errModal.style.display = 'none';
        }
    };

    if (!errModal) {
        errModal = document.createElement('div');
        errModal.id = 'error-modal';
        errModal.className = 'modal-overlay';
        // Reuse existing modal structure using IDs for event attachment instead of onclick
        errModal.innerHTML = `
            <div class="modal-content" style="max-width: 400px; text-align: center; border-color: var(--accent-red);">
                <span class="modal-close" id="err-close-x">&times;</span>
                <div class="modal-title" style="color: var(--accent-red);">Avviso</div>
                <div class="modal-body"><p id="error-msg-text" style="color: white; font-size: 1rem;"></p></div>
                <button class="buy-btn" id="err-close-btn" style="margin-top:20px; background: var(--accent-red);">OK</button>
            </div>
        `;
        document.body.appendChild(errModal);

        // Attach Listeners (CSP Safe)
        document.getElementById('err-close-x').addEventListener('click', closeErrModal);
        document.getElementById('err-close-btn').addEventListener('click', closeErrModal);

        // Close on click outside
        errModal.addEventListener('click', (e) => {
            if (e.target === errModal) closeErrModal();
        });
    }

    document.getElementById('error-msg-text').textContent = msg;

    if (typeof window.showModal === 'function') {
        window.showModal(errModal);
    } else {
        errModal.classList.add('active');
        errModal.style.display = 'flex';
    }
}
