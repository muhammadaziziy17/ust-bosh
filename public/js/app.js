/*
 * UstBosh — umumiy frontend yordamchilari
 * 1) Scroll pozitsiyasini eslab qolish (navbar havolalari orqali render bo'lsa ham)
 * 2) AJAX helper: formalarni fetch bilan yuborish + loader + toast xabarlar
 */
(function () {
    'use strict';

    /* ============================================================
     * 1) SCROLL POZITSIYASINI ESLAB QOLISH
     * ============================================================
     * Sahifa qayta yuklanganda brauzer scroll'ni tepaga qaytaradi.
     * sessionStorage'da saqlab, yuklanganda tiklaymiz.
     */
    var SCROLL_KEY = 'ustbosh_scroll_' + location.pathname;

    function saveScroll() {
        try {
            sessionStorage.setItem(SCROLL_KEY, String(window.scrollY));
        } catch (e) { /* sessionStorage mavjud emas */ }
    }

    function restoreScroll() {
        try {
            var saved = sessionStorage.getItem(SCROLL_KEY);
            if (saved !== null && !location.hash) {
                // Sekundning 10% ichida — layout to'liq qurilishini kutib
                var y = parseInt(saved, 10);
                requestAnimationFrame(function () {
                    window.scrollTo(0, y);
                    // Rasm/shrift yuklanishi bilan balandlik o'zgarsa — yana bir marta
                    setTimeout(function () { window.scrollTo(0, y); }, 60);
                });
            }
        } catch (e) { /* ignore */ }
    }

    window.addEventListener('scroll', throttle(saveScroll, 150), { passive: true });
    window.addEventListener('pageshow', restoreScroll);

    // Ajax navigatsiyadan keyin ham tiklash
    document.addEventListener('ustbosh:restored', restoreScroll);

    function throttle(fn, ms) {
        var last = 0, timer = null;
        return function () {
            var now = Date.now();
            if (now - last >= ms) { last = now; fn(); }
            else {
                clearTimeout(timer);
                timer = setTimeout(function () { last = Date.now(); fn(); }, ms - (now - last));
            }
        };
    }

    /* ============================================================
     * 2) TOAST (xabar oynachalari)
     * ============================================================ */
    var toastContainer = null;

    function ensureToasts() {
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        }
        return toastContainer;
    }

    window.ustboshToast = function (message, type) {
        var container = ensureToasts();
        var toast = document.createElement('div');
        toast.className = 'toast-item toast-' + (type || 'info');
        toast.setAttribute('role', 'status');

        var icon = type === 'success' ? '✓' : (type === 'error' ? '✕' : 'ℹ');
        var dot = document.createElement('span');
        dot.className = 'toast-icon';
        dot.textContent = icon;

        var text = document.createElement('span');
        text.textContent = message;

        var close = document.createElement('button');
        close.className = 'toast-close';
        close.type = 'button';
        close.setAttribute('aria-label', 'close');
        close.innerHTML = '&times;';
        close.addEventListener('click', function () { dismiss(); });

        function dismiss() {
            toast.classList.add('is-leaving');
            setTimeout(function () { toast.remove(); }, 250);
        }

        toast.appendChild(dot);
        toast.appendChild(text);
        toast.appendChild(close);
        container.appendChild(toast);

        setTimeout(dismiss, 4500);
    };

    /* ============================================================
     * 3) LOADER
     * ============================================================ */
    var loaderOverlay = null;

    function ensureLoader() {
        if (!loaderOverlay) {
            loaderOverlay = document.createElement('div');
            loaderOverlay.className = 'loader-overlay';
            loaderOverlay.innerHTML =
                '<span class="loader-spinner" aria-hidden="true"></span>' +
                '<span class="sr-only">Yuklanmoqda...</span>';
            document.body.appendChild(loaderOverlay);
        }
        return loaderOverlay;
    }

    window.ustboshLoader = {
        show: function () { ensureLoader().classList.add('is-active'); },
        hide: function () { if (loaderOverlay) loaderOverlay.classList.remove('is-active'); }
    };

    // Tugmalarni loading holatiga o'tkazish
    function setButtonLoading(button, loading) {
        if (!button) return;
        if (loading) {
            button.dataset.originalHtml = button.innerHTML;
            button.classList.add('is-loading');
            button.disabled = true;
            var labelText = button.dataset.loadingText || button.textContent.trim() || '...';
            button.innerHTML = '';
            var spinner = document.createElement('span');
            spinner.className = 'btn-spinner';
            button.appendChild(spinner);
            var label = document.createElement('span');
            label.className = 'btn-loading-label';
            label.textContent = labelText;
            button.appendChild(label);
        } else {
            button.classList.remove('is-loading');
            button.disabled = false;
            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
                delete button.dataset.originalHtml;
            }
        }
    }

    /* ============================================================
     * 3.5) KATTA MODAL (landing forma natijasi uchun)
     * ============================================================ */
    var resultModal = null;

    function ensureResultModal() {
        if (!resultModal) {
            resultModal = document.createElement('div');
            resultModal.className = 'result-modal-overlay';
            resultModal.innerHTML =
                '<div class="result-modal" role="dialog" aria-modal="true">' +
                '  <div class="result-modal-icon"></div>' +
                '  <h3 class="result-modal-title"></h3>' +
                '  <p class="result-modal-text"></p>' +
                '  <button type="button" class="result-modal-close btn btn-accent sm-grow">OK</button>' +
                '</div>';

            resultModal.querySelector('.result-modal-close').addEventListener('click', closeResultModal);
            resultModal.addEventListener('click', function (e) {
                if (e.target === resultModal) closeResultModal();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && resultModal.classList.contains('is-open')) closeResultModal();
            });

            document.body.appendChild(resultModal);
        }
        return resultModal;
    }

    function closeResultModal() {
        if (!resultModal) return;
        resultModal.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    window.ustboshModal = function (title, text, type) {
        var modal = ensureResultModal();
        var iconEl = modal.querySelector('.result-modal-icon');
        modal.querySelector('.result-modal-title').textContent = title;
        modal.querySelector('.result-modal-text').textContent = text;

        modal.classList.remove('modal-success', 'modal-error', 'modal-info');
        modal.classList.add('modal-' + (type || 'info'));
        iconEl.textContent = type === 'success' ? '\u2713' : (type === 'error' ? '\u2715' : 'i');

        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        modal.querySelector('.result-modal-close').focus();
    };

    /* ============================================================
     * 4) AJAX HELPERS
     * ============================================================ */
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');
        var input = document.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    window.ustboshAjax = function (url, options) {
        options = options || {};
        var headers = options.headers || {};
        headers['X-Requested-With'] = 'XMLHttpRequest';
        headers['Accept'] = 'application/json';
        if (options.method !== 'GET') {
            headers['X-CSRF-TOKEN'] = getCsrfToken();
        }

        return fetch(url, {
            method: options.method || 'GET',
            headers: headers,
            body: options.body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().catch(function () {
                return { success: false, message: 'Server javobi noto\u2018g\u2018ri' };
            }).then(function (data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        });
    };

    /* ============================================================
     * 5) FORMHandler: data-ajax formalarni ushlab, AJAX yuborish
     * ============================================================ */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-ajax]')) return;

        // data-confirm: yuborishdan OLDIN so'rash
        var confirmMsg = form.getAttribute('data-confirm');
        if (confirmMsg && !window.confirm(confirmMsg)) {
            event.preventDefault();
            return;
        }

        event.preventDefault();

        var method = (form.getAttribute('method') || 'POST').toUpperCase();
        var submitBtn = form.querySelector('[type="submit"]');
        var loaderText = form.getAttribute('data-loader-text') || null;

        if (loaderText && submitBtn) {
            submitBtn.dataset.loadingText = loaderText;
        }

        setButtonLoading(submitBtn, true);
        window.ustboshLoader.show();

        var body;
        var headers = {};
        if (form.getAttribute('enctype') === 'multipart/form-data') {
            body = new FormData(form);
        } else {
            body = new URLSearchParams(new FormData(form)).toString();
            headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
        }

        window.ustboshAjax(form.action, { method: method, body: body, headers: headers })
            .then(function (result) {
                var data = result.data || {};
                var titles = {
                    success: form.getAttribute('data-modal-title-success') || 'Muvaffaqiyatli!',
                    error: form.getAttribute('data-modal-title-error') || 'Xatolik',
                    duplicate: form.getAttribute('data-modal-title-duplicate') || 'Diqqat',
                    warning: form.getAttribute('data-modal-title-warning') || 'Diqqat'
                };

                if (result.ok) {
                    var isModalForm = form.hasAttribute('data-result-modal');
                    var isDuplicate = data.duplicate === true;
                    var isWarning = data.warning === true;

                    if (isModalForm) {
                        // Landing formasi: katta modal chiqamiz
                        var title = isDuplicate ? titles.duplicate : (isWarning ? titles.warning : titles.success);
                        window.ustboshModal(title, data.message || '', (isDuplicate || isWarning) ? 'info' : 'success');
                    } else {
                        window.ustboshToast(data.message || 'Muvaffaqiyatli!', isWarning ? 'info' : 'success');
                    }

                    document.dispatchEvent(new CustomEvent('ustbosh:ajax:success', {
                        detail: { form: form, data: data, response: result }
                    }));
                    if (form.hasAttribute('data-reset-on-success')) form.reset();

                    // Login endpointlari JSON ichida dashboard URL qaytaradi.
                    // Redirect bo'lmasa, oddiy AJAX forma odatdagidek davom etadi.
                    if (typeof data.redirect === 'string' && data.redirect !== '') {
                        window.location.assign(data.redirect);
                    }
                } else {
                    var msg = data.message || (data.errors && firstError(data.errors)) || 'Xatolik yuz berdi';

                    if (form.hasAttribute('data-result-modal')) {
                        window.ustboshModal(titles.error, msg, 'error');
                    } else {
                        window.ustboshToast(msg, 'error');
                    }

                    document.dispatchEvent(new CustomEvent('ustbosh:ajax:error', {
                        detail: { form: form, data: data, response: result }
                    }));
                }
            })
            .catch(function () {
                var netMsg = form.getAttribute('data-net-error') || 'Tarmoq xatosi. Qaytadan urinib ko\u2018ring.';
                if (form.hasAttribute('data-result-modal')) {
                    window.ustboshModal(form.getAttribute('data-modal-title-error') || 'Xatolik', netMsg, 'error');
                } else {
                    window.ustboshToast(netMsg, 'error');
                }
            })
            .finally(function () {
                setButtonLoading(submitBtn, false);
                window.ustboshLoader.hide();
            });
    });

    function firstError(errors) {
        for (var key in errors) {
            if (Object.prototype.hasOwnProperty.call(errors, key) && errors[key].length) {
                return errors[key][0];
            }
        }
        return null;
    }

    /* ============================================================
     * 6) data-confirm — data-ajax BO'LMAGAN formalarga ham xizmat qiladi
     * ============================================================ */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || form.matches('[data-ajax]')) return;
        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    /* ============================================================
     * 7) PAROL TALABLARI — real vaqtda indikator
     * ============================================================
     * [data-password-form] ichidagi kiritish maydonlari bo'yicha
     * [data-password-rule] elementlariga "is-ok" klassi qo'yiladi.
     * Faqat parol o'zgartiriladigan sahifalarda ishlatiladi (loginda emas).
     */
    document.querySelectorAll('[data-password-form]').forEach(function (form) {
        var input = form.querySelector('[data-password-input]');

        if (!input) return;

        var confirmation = form.querySelector('[data-password-confirmation]');
        var rules = form.querySelectorAll('[data-password-rule]');

        function refreshRules() {
            var value = input.value;
            var passed = {
                length: value.length >= 8,
                case: /[a-z]/.test(value) && /[A-Z]/.test(value),
                number: /[0-9]/.test(value),
                match: value !== '' && confirmation !== null && confirmation.value === value
            };

            rules.forEach(function (rule) {
                rule.classList.toggle('is-ok', passed[rule.dataset.passwordRule] === true);
            });
        }

        input.addEventListener('input', refreshRules);

        if (confirmation) {
            confirmation.addEventListener('input', refreshRules);
        }

        refreshRules();
    });
})();
