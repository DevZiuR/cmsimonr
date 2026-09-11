/**
 * SessionAutoLogout - User inactivity Auto-Logout with Alert modal & Countdown.
 * ES5-compatible (IE11 safe): no class, no arrow functions, no template literals,
 * no const/let, no rest params, no spread.
 */

(function (global) {
    'use strict';

    /* ── Constructor ─────────────────────────────────────────────── */
    function SessionAutoLogout(options) {
        options = options || {};
        var savedSettings = this.loadSavedSettings();

        this.config = {
            enabled:             (savedSettings.enabled !== undefined) ? savedSettings.enabled : true,
            timeoutMinutes:      savedSettings.timeoutMinutes || 5,
            warningTimeSeconds:  15,
            logoutUrl:           '/sistema/public/logout.php?reason=inactivity',
            storageKey:          'session_last_activity'
        };

        /* merge caller options */
        for (var k in options) {
            if (Object.prototype.hasOwnProperty.call(options, k)) {
                this.config[k] = options[k];
            }
        }

        this.inactivityTimer  = null;
        this.warningInterval  = null;
        this.countdownSeconds = this.config.warningTimeSeconds;
        this.isWarningShown   = false;

        /* Bind handlers */
        var self = this;
        this.handleUserActivity  = this._debounce(function () { self.resetTimer(); }, 300);
        this.handleStorageChange = function (e) { self._onStorageChange(e); };

        if (this.config.enabled) {
            this.init();
        }
    }

    /* ── Prototype methods ───────────────────────────────────────── */
    SessionAutoLogout.prototype.loadSavedSettings = function () {
        try {
            var data = localStorage.getItem('user_session_settings');
            return data ? JSON.parse(data) : {};
        } catch (e) {
            return {};
        }
    };

    SessionAutoLogout.prototype.saveSettings = function (enabled, timeoutMinutes) {
        try {
            var settings = {
                enabled:        Boolean(enabled),
                timeoutMinutes: parseInt(timeoutMinutes, 10) || 5
            };
            localStorage.setItem('user_session_settings', JSON.stringify(settings));
            this.config.enabled        = settings.enabled;
            this.config.timeoutMinutes = settings.timeoutMinutes;

            if (this.config.enabled) {
                this.init();
            } else {
                this.destroy();
            }
        } catch (e) {
            /* ignore */
        }
    };

    SessionAutoLogout.prototype.init = function () {
        if (!this.config.enabled) return;

        var activityEvents = ['mousemove', 'mousedown', 'keydown', 'keypress', 'click', 'scroll', 'touchstart'];
        for (var i = 0; i < activityEvents.length; i++) {
            /* IE11 only accepts boolean as 3rd arg — do NOT pass { passive: true } */
            window.addEventListener(activityEvents[i], this.handleUserActivity, false);
        }

        window.addEventListener('storage', this.handleStorageChange);

        this.updateLastActivityTimestamp();
        this.startInactivityTimer();
    };

    SessionAutoLogout.prototype.destroy = function () {
        var activityEvents = ['mousemove', 'mousedown', 'keydown', 'keypress', 'click', 'scroll', 'touchstart'];
        for (var i = 0; i < activityEvents.length; i++) {
            window.removeEventListener(activityEvents[i], this.handleUserActivity);
        }
        window.removeEventListener('storage', this.handleStorageChange);
        this.clearTimers();
        this.closeWarningModal();
    };

    SessionAutoLogout.prototype.updateLastActivityTimestamp = function () {
        try {
            localStorage.setItem(this.config.storageKey, Date.now().toString());
        } catch (e) { }
    };

    SessionAutoLogout.prototype._onStorageChange = function (event) {
        if (event.key === this.config.storageKey && !this.isWarningShown) {
            this.startInactivityTimer();
        }
    };

    SessionAutoLogout.prototype.startInactivityTimer = function () {
        this.clearTimers();
        if (!this.config.enabled) return;

        var self = this;
        var totalSeconds        = this.config.timeoutMinutes * 60;
        var warningDelaySeconds = totalSeconds - this.config.warningTimeSeconds;

        if (warningDelaySeconds <= 0) {
            this.showWarningAlert();
            return;
        }

        this.inactivityTimer = setTimeout(function () {
            self.showWarningAlert();
        }, warningDelaySeconds * 1000);
    };

    SessionAutoLogout.prototype.showWarningAlert = function () {
        if (this.isWarningShown) return;
        this.isWarningShown   = true;
        this.countdownSeconds = this.config.warningTimeSeconds;

        if (typeof Swal !== 'undefined') {
            this.showSweetAlert();
        } else {
            this.showFallbackHtmlModal();
        }

        var self = this;
        this.warningInterval = setInterval(function () {
            self.countdownSeconds--;
            self.updateCountdownDisplay();
            if (self.countdownSeconds <= 0) {
                self.executeLogout();
            }
        }, 1000);
    };

    SessionAutoLogout.prototype.showSweetAlert = function () {
        var self = this;
        var html =
            '<div style="margin-top:10px;font-size:14px;color:#334155;">' +
                'Tu sesi\u00f3n est\u00e1 a punto de caducar por inactividad. \u00bfQuieres seguir conectado?' +
            '</div>' +
            '<div style="margin-top:15px;font-size:18px;font-weight:bold;color:#dc2626;">' +
                'Tiempo restante: <span id="swal-autologout-countdown">' + this.countdownSeconds + '</span>s' +
            '</div>';

        Swal.fire({
            title:             '\u00a1Sesi\u00f3n a punto de expirar!',
            html:              html,
            icon:              'warning',
            showCancelButton:  true,
            confirmButtonColor:'#25eb28',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Continuar',
            cancelButtonText:  'Cerrar sesi\u00f3n',
            focusConfirm:      true,
            allowOutsideClick: false,
            allowEscapeKey:    false,
            allowEnterKey:     true
        }).then(function (result) {
            if (result.isConfirmed) {
                self.userSelectedContinue();
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                self.executeLogout();
            }
        });
    };

    SessionAutoLogout.prototype.showFallbackHtmlModal = function () {
        this.closeWarningModal();

        var self = this;

        var modalOverlay     = document.createElement('div');
        modalOverlay.id      = 'autologout-fallback-modal';
        modalOverlay.style.cssText =
            'position:fixed;top:0;left:0;width:100%;height:100%;' +
            'background-color:rgba(15,23,42,0.65);' +
            'display:-ms-flexbox;display:flex;' +
            '-ms-flex-align:center;align-items:center;' +
            '-ms-flex-pack:center;justify-content:center;' +
            'z-index:999999;font-family:system-ui,-apple-system,sans-serif;';

        /* Darker overlay if backdrop-filter is unsupported */
        try {
            var bs = document.body.style;
            if (!('backdropFilter' in bs) && !('webkitBackdropFilter' in bs)) {
                modalOverlay.style.backgroundColor = 'rgba(15,23,42,0.82)';
            }
        } catch (e) { }

        var modalContent        = document.createElement('div');
        modalContent.style.cssText =
            'background:#ffffff;border-radius:14px;padding:24px 28px;' +
            'max-width:440px;width:90%;text-align:center;' +
            'box-shadow:0 20px 25px -5px rgba(0,0,0,.2),0 10px 10px -5px rgba(0,0,0,.08);';

        modalContent.innerHTML =
            '<div style="font-size:42px;margin-bottom:8px;">\u23f3</div>' +
            '<h3 style="margin:0 0 10px 0;font-size:18px;color:#0f172a;font-weight:700;">' +
                '\u00bfDesea mantener su sesi\u00f3n activa?' +
            '</h3>' +
            '<p style="margin:0 0 16px 0;font-size:13.5px;color:#475569;line-height:1.5;">' +
                'Tu sesi\u00f3n est\u00e1 a punto de caducar por inactividad. \u00bfQuieres seguir conectado?' +
            '</p>' +
            '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:20px;">' +
                '<span style="font-size:14px;color:#991b1b;font-weight:600;">' +
                    'Cierre autom\u00e1tico en: ' +
                    '<span id="autologout-countdown-num" style="font-size:20px;font-weight:800;color:#dc2626;">' +
                        this.countdownSeconds +
                    '</span>s' +
                '</span>' +
            '</div>' +
            '<div style="display:-ms-flexbox;display:flex;gap:10px;-ms-flex-pack:center;justify-content:center;">' +
                '<button id="autologout-btn-continue" style="background-color:#2563eb;color:#fff;border:none;padding:10px 20px;border-radius:6px;font-weight:600;font-size:13.5px;cursor:pointer;">Continuar</button>' +
                '<button id="autologout-btn-logout"   style="background-color:#e2e8f0;color:#334155;border:none;padding:10px 16px;border-radius:6px;font-weight:600;font-size:13.5px;cursor:pointer;">Cerrar sesi\u00f3n</button>' +
            '</div>';

        /* Inject animation keyframes once */
        if (!document.getElementById('autologout-styles')) {
            var styleEl      = document.createElement('style');
            styleEl.id       = 'autologout-styles';
            styleEl.type     = 'text/css';
            var css =
                '@-webkit-keyframes autologoutFadeIn{from{opacity:0;-webkit-transform:scale(.95)}to{opacity:1;-webkit-transform:scale(1)}}' +
                '@keyframes autologoutFadeIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}';
            try {
                styleEl.appendChild(document.createTextNode(css));
            } catch (ex) {
                styleEl.styleSheet.cssText = css; /* IE8 fallback, harmless */
            }
            document.head.appendChild(styleEl);
            modalOverlay.style.animation = 'autologoutFadeIn .2s ease-out';
        }

        modalOverlay.appendChild(modalContent);
        document.body.appendChild(modalOverlay);

        document.getElementById('autologout-btn-continue').addEventListener('click', function () {
            self.userSelectedContinue();
        });
        document.getElementById('autologout-btn-logout').addEventListener('click', function () {
            self.executeLogout();
        });
    };

    SessionAutoLogout.prototype.updateCountdownDisplay = function () {
        var swalEl = document.getElementById('swal-autologout-countdown');
        if (swalEl) { swalEl.textContent = this.countdownSeconds; }

        var htmlEl = document.getElementById('autologout-countdown-num');
        if (htmlEl) { htmlEl.textContent = this.countdownSeconds; }
    };

    SessionAutoLogout.prototype.userSelectedContinue = function () {
        this.closeWarningModal();
        this.updateLastActivityTimestamp();
        this.startInactivityTimer();
    };

    SessionAutoLogout.prototype.closeWarningModal = function () {
        this.isWarningShown = false;
        if (this.warningInterval) {
            clearInterval(this.warningInterval);
            this.warningInterval = null;
        }

        if (typeof Swal !== 'undefined' && Swal.isVisible()) {
            Swal.close();
        }

        var overlay = document.getElementById('autologout-fallback-modal');
        if (overlay && overlay.parentNode) { overlay.parentNode.removeChild(overlay); }
    };

    SessionAutoLogout.prototype.resetTimer = function () {
        if (this.isWarningShown) return;
        this.updateLastActivityTimestamp();
        this.startInactivityTimer();
    };

    SessionAutoLogout.prototype.executeLogout = function () {
        this.clearTimers();
        window.location.href = this.config.logoutUrl;
    };

    SessionAutoLogout.prototype.clearTimers = function () {
        if (this.inactivityTimer) {
            clearTimeout(this.inactivityTimer);
            this.inactivityTimer = null;
        }
        if (this.warningInterval) {
            clearInterval(this.warningInterval);
            this.warningInterval = null;
        }
    };

    /* Simple debounce — ES5, no rest/spread */
    SessionAutoLogout.prototype._debounce = function (func, wait) {
        var timeout;
        return function () {
            var args    = arguments;
            var context = this;
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                func.apply(context, args);
            }, wait);
        };
    };

    /* ── Auto-initialize on DOM ready ───────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        global.sessionAutoLogoutInstance = new SessionAutoLogout();
    });

    /* Expose class globally */
    global.SessionAutoLogout = SessionAutoLogout;

})(window);
