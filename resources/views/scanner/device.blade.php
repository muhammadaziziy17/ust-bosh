<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="savdomarkaz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('pos.scanner_title') }} · {{ __('landing.brand') }}</title>

    <link href="https://cdn.jsdelivr.net/npm/daisyui@5.7.22/daisyui.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.3"></script>

    <style type="text/tailwindcss">
        @theme {
            --color-card: #FDFBF6;
            --color-line: #DCD2BE;
            --color-ok: #3E7C59;
            --color-err: #B23A3A;
            --color-ink: #1F2A24;
            --color-ink-soft: #5A6660;
            --color-paper: #F6F1E7;
        }
    </style>

    <style>
        body { font-family: 'Archivo', system-ui, sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
        .font-display { font-family: 'Archivo', system-ui, sans-serif; font-weight: 800; letter-spacing: -0.03em; }
        .shadow-elev { box-shadow: 0 1px 2px rgba(31, 42, 36, .05), 0 12px 32px -16px rgba(31, 42, 36, .25); }

        /* html5-qrcode kutubxonasi ichki elementlari */
        #reader video { display: block; width: 100% !important; height: auto !important; }
        #reader__dashboard { display: none; }
    </style>

    <link href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2/html5-qrcode.min.js"></script>
</head>
<body class="flex min-h-screen flex-col antialiased">

    {{-- Sarlavha --}}
    <header class="sticky top-0 z-40 border-b border-line/60 bg-base-100/95 backdrop-blur">
        <div class="mx-auto flex h-14 w-full max-w-md items-center justify-between gap-3 px-4">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-accent font-mono font-bold text-accent-content shadow-elev">U</span>
                <span class="truncate font-extrabold tracking-tight">{{ __('landing.brand') }}</span>
                <span class="hidden text-sm text-ink-soft xs:inline-flex">· {{ __('pos.scanner_title') }}</span>
            </div>
            <button type="button" id="feedback-toggle" class="btn btn-ghost btn-sm btn-circle">
                <span id="feedback-icon" class="text-lg" aria-hidden="true">🔔</span>
                <span id="feedback-label" class="sr-only">{{ __('pos.scanner_feedback_on') }}</span>
            </button>
        </div>
    </header>

    <main class="mx-auto w-full max-w-md flex-1 space-y-4 px-4 py-4 pb-10">

        {{-- Ulanish holati --}}
        <div class="flex items-center gap-3 rounded-box border border-line bg-card px-4 py-3 shadow-elev">
            <span class="relative flex size-2.5 shrink-0">
                <span id="status-ping" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-warning opacity-60"></span>
                <span id="status-dot" class="relative inline-flex size-2.5 rounded-full bg-warning"></span>
            </span>
            <div class="min-w-0">
                <p id="status-text" class="truncate text-sm font-semibold">{{ __('pos.scanner_connecting') }}</p>
                <p id="status-sub" class="truncate text-xs text-ink-soft"></p>
            </div>
        </div>

        {{-- Kamera --}}
        <section class="overflow-hidden rounded-box border border-line bg-card shadow-elev">
            <div class="flex items-center justify-between gap-2 border-b border-line/70 px-4 py-2.5">
                <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">📷 {{ __('pos.scanner_scan_btn') }}</span>
                <span id="camera-state" class="font-mono text-[10px] uppercase tracking-wider text-ink-soft">{{ __('pos.scanner_waiting') }}</span>
            </div>
            <div id="reader" class="min-h-52 bg-base-200"></div>
            <p id="camera-hint" class="px-4 py-2.5 text-center text-xs text-ink-soft">{{ __('pos.scanner_camera_hint') }}</p>
        </section>

        {{-- Oxirgi skan natijasi --}}
        <section id="last-result" class="hidden">
            <div id="result-card" class="flex items-center gap-3 rounded-box border border-line bg-card p-4 shadow-elev">
                <span id="result-icon" class="grid size-10 shrink-0 place-items-center rounded-full bg-success/15 text-lg font-bold text-success">✓</span>
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-soft">{{ __('pos.scanner_last_scan') }}</p>
                    <p id="result-title" class="truncate font-bold"></p>
                    <p id="result-barcode" class="truncate font-mono text-xs text-ink-soft"></p>
                </div>
            </div>
        </section>

        {{-- Qo'lda kiritish --}}
        <section class="rounded-box border border-line bg-card p-4 shadow-elev">
            <label for="manual-input" class="text-xs font-semibold uppercase tracking-wider text-ink-soft">⌨️ {{ __('pos.scanner_manual_tab') }}</label>
            <form id="manual-form" class="mt-2 flex gap-2">
                <input id="manual-input" type="text" placeholder="{{ __('pos.search_placeholder') }}"
                       class="input input-bordered w-full bg-base-100 font-mono" autocomplete="off">
                <button type="submit" class="btn btn-accent shrink-0">{{ __('pos.scanner_send') }}</button>
            </form>
        </section>

        {{-- Qidiruv --}}
        <section class="rounded-box border border-line bg-card p-4 shadow-elev">
            <label for="search-input" class="text-xs font-semibold uppercase tracking-wider text-ink-soft">🔎 {{ __('pos.scanner_search_tab') }}</label>
            <form id="search-form" class="mt-2 flex gap-2">
                <input id="search-input" type="search" placeholder="{{ __('pos.search_placeholder') }}"
                       class="input input-bordered w-full bg-base-100" autocomplete="off">
                <button type="submit" class="btn btn-outline btn-secondary shrink-0">{{ __('pos.scanner_search_btn') }}</button>
            </form>
            <div id="search-results" class="mt-2 space-y-2"></div>
        </section>

        <p class="pt-2 text-center text-xs text-ink-soft">{{ __('landing.brand') }} · {{ __('pos.scanner_title') }}</p>
    </main>

    <script>
        (function () {
            'use strict';

            var TOKEN = @json($token);
            var BASE = '/scanner/' + TOKEN;
            var CSRF = document.querySelector('meta[name="csrf-token"]').content;
            var scanning = false;

            var LANG = {
                connecting: @json(__('pos.scanner_connecting')),
                connected: @json(__('pos.scanner_connected')),
                ready: @json(__('pos.scanner_ready')),
                noCamera: @json(__('pos.scanner_no_camera')),
                networkError: @json(__('pos.network_error')),
                notFound: @json(__('pos.scanner_not_found')),
                inactive: @json(__('pos.scanner_inactive')),
                send: @json(__('pos.scanner_send')),
                resultsEmpty: @json(__('pos.results_empty')),
                soundOn: @json(__('pos.scanner_feedback_on')),
                soundOff: @json(__('pos.scanner_feedback_off'))
            };

            /* ===== Holat ko'rsatkichi ===== */
            var statusDot = document.getElementById('status-dot');
            var statusPing = document.getElementById('status-ping');
            var statusText = document.getElementById('status-text');
            var statusSub = document.getElementById('status-sub');

            function setStatus(state, text, sub) {
                var colors = {
                    connecting: 'bg-warning',
                    connected: 'bg-success',
                    warning: 'bg-warning',
                    error: 'bg-error'
                };
                var color = colors[state] || colors.connecting;

                statusDot.className = 'relative inline-flex size-2.5 rounded-full ' + color;
                statusPing.className = 'absolute inline-flex h-full w-full rounded-full opacity-60 ' + color +
                    (state === 'connecting' ? ' animate-ping' : ' hidden');
                statusText.textContent = text;
                statusSub.textContent = sub || '';
            }

            /* ===== Ovoz va vibratsiya ===== */
            var audioCtx = null;
            var feedbackEnabled = readFeedback();

            function readFeedback() {
                try {
                    return localStorage.getItem('scanner-feedback') !== 'off';
                } catch (e) {
                    return true;
                }
            }

            function saveFeedback() {
                try {
                    localStorage.setItem('scanner-feedback', feedbackEnabled ? 'on' : 'off');
                } catch (e) {
                    // Xotira mavjud bo'lmasa ham sahifa ishlashda davom etadi
                }
            }

            function initAudio() {
                var AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) {
                    return;
                }
                if (!audioCtx) {
                    audioCtx = new AudioCtx();
                }
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
            }

            // Mobil brauzerlar ovozni faqat foydalanuvchi teginishidan keyin ochadi
            document.addEventListener('pointerdown', initAudio);
            document.addEventListener('touchstart', initAudio, { passive: true });

            function playTone(frequencies, duration) {
                if (!feedbackEnabled || !audioCtx) {
                    return;
                }

                var now = audioCtx.currentTime;

                frequencies.forEach(function (frequency, index) {
                    var oscillator = audioCtx.createOscillator();
                    var gain = audioCtx.createGain();
                    var start = now + index * (duration + 0.07);

                    oscillator.type = 'sine';
                    oscillator.frequency.value = frequency;

                    gain.gain.setValueAtTime(0.0001, start);
                    gain.gain.exponentialRampToValueAtTime(0.25, start + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);

                    oscillator.connect(gain);
                    gain.connect(audioCtx.destination);
                    oscillator.start(start);
                    oscillator.stop(start + duration + 0.03);
                });
            }

            function vibrate(pattern) {
                if (!feedbackEnabled || !navigator.vibrate) {
                    return;
                }

                try {
                    navigator.vibrate(pattern);
                } catch (e) {
                    // Qurilma tebranishni qo'llab-quvvatlamaydi
                }
            }

            function scanFeedback(found) {
                if (found) {
                    playTone([880, 1318], 0.1);
                    vibrate(80);
                } else {
                    playTone([220, 220], 0.09);
                    vibrate([60, 60, 60]);
                }
            }

            /* ===== Ovozni yoqish/o'chirish ===== */
            var feedbackToggle = document.getElementById('feedback-toggle');
            var feedbackIcon = document.getElementById('feedback-icon');
            var feedbackLabel = document.getElementById('feedback-label');

            function renderFeedbackToggle() {
                feedbackIcon.textContent = feedbackEnabled ? '🔔' : '🔕';
                feedbackLabel.textContent = feedbackEnabled ? LANG.soundOn : LANG.soundOff;
                feedbackToggle.title = feedbackLabel.textContent;
            }

            feedbackToggle.addEventListener('click', function () {
                feedbackEnabled = !feedbackEnabled;
                saveFeedback();
                initAudio();
                renderFeedbackToggle();

                if (feedbackEnabled) {
                    playTone([1046], 0.08); // Yoqilganini bildiruvchi qisqa signal
                }
            });

            renderFeedbackToggle();

            /* ===== Ulanish ===== */
            setStatus('connecting', LANG.connecting, '');

            fetch(BASE + '/connect', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.connected) {
                    setStatus('connected', LANG.connected, LANG.ready);
                    startCamera();
                } else {
                    setStatus('error', LANG.networkError, '');
                }
            }).catch(function () {
                setStatus('error', LANG.networkError, '');
            });

            /* ===== Kamera ===== */
            function startCamera() {
                try {
                    var scanner = new Html5Qrcode('reader');
                    scanner.start(
                        { facingMode: 'environment' },
                        { fps: 10, qrbox: qrboxSize },
                        function (decodedText) {
                            if (scanning) return;
                            scanning = true;
                            scanner.pause(true);
                            sendScan(decodedText).finally(function () {
                                setTimeout(function () {
                                    scanning = false;
                                    scanner.resume();
                                }, 1500);
                            });
                        }
                    ).catch(function () {
                        cameraFailed();
                    });
                } catch (e) {
                    cameraFailed();
                }
            }

            function qrboxSize(viewfinderWidth, viewfinderHeight) {
                var width = Math.floor(Math.min(viewfinderWidth * 0.9, 320));

                return {
                    width: width,
                    height: Math.max(Math.floor(width * 0.55), Math.floor(viewfinderHeight * 0.35))
                };
            }

            function cameraFailed() {
                setStatus('warning', LANG.noCamera, '');
                document.getElementById('camera-hint').textContent = LANG.noCamera;
            }

            /* ===== Skan yuborish ===== */
            function sendScan(barcode) {
                return fetch(BASE + '/scan', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ barcode: barcode })
                }).then(function (r) { return r.json(); }).then(function (data) {
                    showResult(barcode, data.status, data.name);
                }).catch(function () {
                    showResult(barcode, 'error');
                });
            }

            /* ===== Natija kartasi ===== */
            var resultSection = document.getElementById('last-result');
            var resultCard = document.getElementById('result-card');
            var resultIcon = document.getElementById('result-icon');
            var resultTitle = document.getElementById('result-title');
            var resultBarcode = document.getElementById('result-barcode');

            function showResult(barcode, status, name) {
                var styles = {
                    found: { icon: '✓', classes: 'bg-success/15 text-success', title: name || barcode },
                    not_found: { icon: '✕', classes: 'bg-error/15 text-error', title: LANG.notFound },
                    inactive: { icon: '!', classes: 'bg-warning/15 text-warning', title: LANG.inactive },
                    error: { icon: '!', classes: 'bg-error/15 text-error', title: LANG.networkError }
                };
                var style = styles[status] || styles.error;

                resultSection.classList.remove('hidden');

                // Animatsiyani har skanda qayta ishga tushirish
                resultCard.classList.remove('sm-pop');
                void resultCard.offsetWidth;
                resultCard.classList.add('sm-pop');

                resultIcon.className = 'grid size-10 shrink-0 place-items-center rounded-full text-lg font-bold ' + style.classes;
                resultIcon.textContent = style.icon;
                resultTitle.textContent = style.title;
                resultBarcode.textContent = barcode;

                scanFeedback(status === 'found');
            }

            /* ===== Qo'lda kiritish ===== */
            document.getElementById('manual-form').addEventListener('submit', function (e) {
                e.preventDefault();

                var input = document.getElementById('manual-input');
                var barcode = input.value.trim();

                if (barcode) {
                    sendScan(barcode);
                    input.value = '';
                    input.focus();
                }
            });

            /* ===== Qidiruv ===== */
            var searchResults = document.getElementById('search-results');

            document.getElementById('search-form').addEventListener('submit', function (e) {
                e.preventDefault();

                var q = document.getElementById('search-input').value.trim();
                if (!q) return;

                fetch(BASE + '/search?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' }
                }).then(function (r) { return r.json(); }).then(function (data) {
                    renderSearchResults(data.results || []);
                }).catch(function () {
                    searchResults.innerHTML = '';
                    searchResults.appendChild(emptyText(LANG.networkError));
                });
            });

            function renderSearchResults(items) {
                searchResults.innerHTML = '';

                if (!items.length) {
                    searchResults.appendChild(emptyText(LANG.resultsEmpty));
                    return;
                }

                items.forEach(function (item) {
                    var row = document.createElement('div');
                    row.className = 'flex items-center justify-between gap-2 rounded-field border border-line bg-base-100 p-2.5';

                    var info = document.createElement('div');
                    info.className = 'min-w-0';

                    var name = document.createElement('p');
                    name.className = 'truncate text-sm font-semibold';
                    name.textContent = item.name || '';

                    var meta = document.createElement('p');
                    meta.className = 'truncate font-mono text-xs text-ink-soft';
                    meta.textContent = (item.label || '') + ' · ' + (item.sku || '');

                    info.appendChild(name);
                    info.appendChild(meta);
                    row.appendChild(info);

                    if (item.barcode) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn btn-accent btn-sm shrink-0';
                        btn.textContent = LANG.send;
                        btn.onclick = function () { sendScan(item.barcode); };
                        row.appendChild(btn);
                    }

                    searchResults.appendChild(row);
                });
            }

            function emptyText(text) {
                var el = document.createElement('p');
                el.className = 'py-2 text-center text-sm text-ink-soft';
                el.textContent = text;

                return el;
            }
        })();
    </script>
</body>
</html>
