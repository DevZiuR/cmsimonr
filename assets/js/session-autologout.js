/**
 * SessionAutoLogout -  User inactivity Auto-Logout with Alert modal & Countdown.
 *
 */

(function (global) {
    'use strict';

    class SessionAutoLogout {
        constructor(options = {}) {
            // Load user profile settings from localStorage if available
            const savedSettings = this.loadSavedSettings();

            this.config = Object.assign({
                enabled: savedSettings.enabled !== undefined ? savedSettings.enabled : true,
                timeoutMinutes: savedSettings.timeoutMinutes || 5, // Default 5 minutes
                warningTimeSeconds: 10, // 10-second countdown alert
                logoutUrl: '/sistema/public/logout.php?reason=inactivity', // Default logout URL
                storageKey: 'session_last_activity'
            }, options);

            this.inactivityTimer = null;
            this.warningInterval = null;
            this.countdownSeconds = this.config.warningTimeSeconds;
            this.isWarningShown = false;

            // Bind handlers
            this.handleUserActivity = this.debounce(this.resetTimer.bind(this), 300);
            this.handleStorageChange = this.handleStorageChange.bind(this);

            if (this.config.enabled) {
                this.init();
            }
        }

        loadSavedSettings() {
            try {
                const data = localStorage.getItem('user_session_settings');
                return data ? JSON.parse(data) : {};
            } catch (e) {
                return {};
            }
        }

        saveSettings(enabled, timeoutMinutes) {
            try {
                const settings = {
                    enabled: Boolean(enabled),
                    timeoutMinutes: parseInt(timeoutMinutes, 10) || 5
                };
                localStorage.setItem('user_session_settings', JSON.stringify(settings));
                this.config.enabled = settings.enabled;
                this.config.timeoutMinutes = settings.timeoutMinutes;

                if (this.config.enabled) {
                    this.init();
                } else {
                    this.destroy();
                }
            } catch (e) {
                console.error('Error saving session auto-logout settings:', e);
            }
        }

        init() {
            if (!this.config.enabled) return;

            // Attach event listeners for user activity
            const activityEvents = ['mousemove', 'mousedown', 'keydown', 'keypress', 'click', 'scroll', 'touchstart'];
            activityEvents.forEach(event => {
                window.addEventListener(event, this.handleUserActivity, { passive: true });
            });

            // Tab synchronization via storage event
            window.addEventListener('storage', this.handleStorageChange);

            // Record initial activity time & start timer
            this.updateLastActivityTimestamp();
            this.startInactivityTimer();
        }

        destroy() {
            const activityEvents = ['mousemove', 'mousedown', 'keydown', 'keypress', 'click', 'scroll', 'touchstart'];
            activityEvents.forEach(event => {
                window.removeEventListener(event, this.handleUserActivity);
            });
            window.removeEventListener('storage', this.handleStorageChange);
            this.clearTimers();
            this.closeWarningModal();
        }

        updateLastActivityTimestamp() {
            try {
                localStorage.setItem(this.config.storageKey, Date.now().toString());
            } catch (e) { }
        }

        handleStorageChange(event) {
            if (event.key === this.config.storageKey && !this.isWarningShown) {
                // Activity occurred in another tab -> reset timer
                this.startInactivityTimer();
            }
        }

        startInactivityTimer() {
            this.clearTimers();
            if (!this.config.enabled) return;

            const totalSeconds = this.config.timeoutMinutes * 60;
            const warningDelaySeconds = totalSeconds - this.config.warningTimeSeconds;

            if (warningDelaySeconds <= 0) {
                this.showWarningAlert();
                return;
            }

            // Set timer until popup warning alert appears
            this.inactivityTimer = setTimeout(() => {
                this.showWarningAlert();
            }, warningDelaySeconds * 1000);
        }

        showWarningAlert() {
            if (this.isWarningShown) return;
            this.isWarningShown = true;
            this.countdownSeconds = this.config.warningTimeSeconds;

            // Check if SweetAlert2 is available
            if (typeof Swal !== 'undefined') {
                this.showSweetAlert();
            } else {
                this.showFallbackHtmlModal();
            }

            // Start 10-second countdown interval
            this.warningInterval = setInterval(() => {
                this.countdownSeconds--;
                this.updateCountdownDisplay();

                if (this.countdownSeconds <= 0) {
                    this.executeLogout();
                }
            }, 1000);
        }

        showSweetAlert() {
            Swal.fire({
                title: '¡Sesión a punto de expirar!',
                html: `
                    <div style="margin-top: 10px; font-size: 14px; color: #334155;">
                        Tu sesión está a punto de caducar por inactividad. ¿Quieres seguir conectado?
                    </div>
                    <div style="margin-top: 15px; font-size: 18px; font-weight: bold; color: #dc2626;">
                        Tiempo restante: <span id="swal-autologout-countdown">${this.countdownSeconds}</span>s
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#25eb28ff',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Continuar',
                cancelButtonText: 'Cerrar sesión',
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: true
            }).then((result) => {
                if (result.isConfirmed) {
                    this.userSelectedContinue();
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    this.executeLogout();
                }
            });
        }

        showFallbackHtmlModal() {
            this.closeWarningModal(); // 

            const modalOverlay = document.createElement('div');
            modalOverlay.id = 'autologout-fallback-modal';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background-color: rgba(15, 23, 42, 0.65);
                backdrop-filter: blur(4px);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 999999;
                font-family: system-ui, -apple-system, sans-serif;
                animation: autologoutFadeIn 0.2s ease-out;
            `;

            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: #ffffff;
                border-radius: 14px;
                padding: 24px 28px;
                max-width: 440px;
                width: 90%;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
                text-align: center;
            `;

            modalContent.innerHTML = `
                <div style="font-size: 42px; margin-bottom: 8px;">⏳</div>
                <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #0f172a; font-weight: 700;">
                    ¿Desea mantener su sesión activa?
                </h3>
                <p style="margin: 0 0 16px 0; font-size: 13.5px; color: #475569; line-height: 1.5;">
                Tu sesión está a punto de caducar por inactividad. ¿Quieres seguir conectado?
                </p>
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                    <span style="font-size: 14px; color: #991b1b; font-weight: 600;">
                        Cierre automático en: <span id="autologout-countdown-num" style="font-size: 20px; font-weight: 800; color: #dc2626;">${this.countdownSeconds}</span>s
                    </span>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button id="autologout-btn-continue" style="
                        background-color: #2563eb;
                        color: #ffffff;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 6px;
                        font-weight: 600;
                        font-size: 13.5px;
                        cursor: pointer;
                        box-shadow: 0 2px 4px rgba(37,99,235,0.2);
                        transition: background-color 0.2s;
                    ">Continue</button>
                    <button id="autologout-btn-logout" style="
                        background-color: #e2e8f0;
                        color: #334155;
                        border: none;
                        padding: 10px 16px;
                        border-radius: 6px;
                        font-weight: 600;
                        font-size: 13.5px;
                        cursor: pointer;
                        transition: background-color 0.2s;
                    ">Cerrar sesión</button>
                </div>
            `;

            //  animation CSS keyframes if NOT exists
            if (!document.getElementById('autologout-styles')) {
                const styleEl = document.createElement('style');
                styleEl.id = 'autologout-styles';
                styleEl.textContent = `
                    @keyframes autologoutFadeIn {
                        from { opacity: 0; transform: scale(0.95); }
                        to { opacity: 1; transform: scale(1); }
                    }
                `;
                document.head.appendChild(styleEl);
            }

            modalOverlay.appendChild(modalContent);
            document.body.appendChild(modalOverlay);

            document.getElementById('autologout-btn-continue').addEventListener('click', () => {
                this.userSelectedContinue();
            });

            document.getElementById('autologout-btn-logout').addEventListener('click', () => {
                this.executeLogout();
            });
        }

        updateCountdownDisplay() {
            // Update SweetAlert element if present
            const swalCountdownEl = document.getElementById('swal-autologout-countdown');
            if (swalCountdownEl) {
                swalCountdownEl.textContent = this.countdownSeconds;
            }

            // Update HTML modal element if present
            const htmlCountdownEl = document.getElementById('autologout-countdown-num');
            if (htmlCountdownEl) {
                htmlCountdownEl.textContent = this.countdownSeconds;
            }
        }

        userSelectedContinue() {
            this.closeWarningModal();
            this.updateLastActivityTimestamp();
            this.startInactivityTimer();
        }

        closeWarningModal() {
            this.isWarningShown = false;
            if (this.warningInterval) {
                clearInterval(this.warningInterval);
                this.warningInterval = null;
            }

            // Close SweetAlert if open
            if (typeof Swal !== 'undefined' && Swal.isVisible()) {
                Swal.close();
            }

            // Close HTML modal if open
            const modalOverlay = document.getElementById('autologout-fallback-modal');
            if (modalOverlay) {
                modalOverlay.remove();
            }
        }

        resetTimer() {
            // Do not reset automatically if warning popup is visible; user must explicitly click "Continue" a menos que el tiempo se agote
            if (this.isWarningShown) return;

            this.updateLastActivityTimestamp();
            this.startInactivityTimer();
        }

        executeLogout() {
            this.clearTimers();
            window.location.href = this.config.logoutUrl;
        }

        clearTimers() {
            if (this.inactivityTimer) {
                clearTimeout(this.inactivityTimer);
                this.inactivityTimer = null;
            }
            if (this.warningInterval) {
                clearInterval(this.warningInterval);
                this.warningInterval = null;
            }
        }

        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    }

    // Auto-initialize default instance on load
    document.addEventListener('DOMContentLoaded', () => {
        global.sessionAutoLogoutInstance = new SessionAutoLogout();
    });

    // Expose class globally
    global.SessionAutoLogout = SessionAutoLogout;

})(window);
