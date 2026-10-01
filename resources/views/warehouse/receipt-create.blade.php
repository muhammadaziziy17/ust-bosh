@extends('layouts.app')

@section('title', __('warehouse.receipt_create_title'))

@section('content')
    <section class="py-8">
        <div class="mx-auto max-w-5xl px-4">

            {{-- Sarlavha --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl md:text-3xl">{{ __('warehouse.receipt_create_title') }}</h1>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('warehouse.receipt_create_subtitle') }}</p>
                </div>
                <a href="{{ route('warehouse.dashboard', ['tab' => 'receipts']) }}" class="btn btn-ghost btn-sm">
                    ← {{ __('warehouse.back_to_warehouse') }}
                </a>
            </div>

            @if (! $hasVariants)
                <div class="mt-6 rounded-box border border-line bg-card p-6 shadow-elev text-sm text-ink-soft">
                    {{ __('warehouse.no_variants_yet') }}
                </div>
            @else
                <form method="POST" action="{{ route('warehouse.receipts.store') }}" id="receipt-form" class="mt-6"
                      data-ajax data-loader-text="{{ __('warehouse.add_receipt') }}...">
                    @csrf
                    {{-- Tarmoq uzilib qayta yuborilsa, kirim ikki marta saqlanmasligi uchun --}}
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}" />

                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px] items-start">

                        {{-- Chap: qidiruv va natijalar --}}
                        <div>
                            <div class="rounded-box border border-line bg-card p-4 shadow-elev">
                                <label for="receipt-search" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.select_variant') }}</label>
                                <input id="receipt-search" type="search" autofocus autocomplete="off"
                                       placeholder="{{ __('warehouse.receipt_search_placeholder') }}"
                                       class="input w-full bg-base-100 text-lg" />
                            </div>

                            <div id="receipt-results" class="mt-4 grid gap-3 sm:grid-cols-2" aria-live="polite"></div>
                        </div>

                        {{-- O'ng: tanlangan qatorlar --}}
                        <aside class="lg:sticky lg:top-20">
                            <div class="rounded-box border border-line bg-card shadow-elev">
                                <div class="px-5 py-4 border-b border-line/60 flex items-center justify-between gap-2">
                                    <h2 class="font-display text-lg">{{ __('warehouse.receipt_selected_title') }}</h2>
                                    <span id="receipt-lines-count" class="badge badge-sm border-0 bg-accent/15 text-accent">0</span>
                                </div>

                                <div id="receipt-lines" class="p-4 space-y-3 max-h-[45vh] overflow-y-auto"></div>

                                <div class="px-5 py-4 border-t border-line/60 space-y-4">
                                    <div>
                                        <label for="receipt-notes" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.receipt_notes') }}</label>
                                        <input id="receipt-notes" name="notes" type="text" maxlength="500"
                                               class="input w-full bg-base-100" />
                                    </div>

                                    <button type="submit" id="receipt-submit-btn" class="btn btn-accent w-full shadow-elev">
                                        🚚 {{ __('warehouse.add_receipt') }}
                                    </button>
                                </div>
                            </div>
                        </aside>
                    </div>

                    <div id="receipt-hidden"></div>
                </form>
            @endif
        </div>
    </section>

    @if ($hasVariants)
        @push('scripts')
            <script>
                (function () {
                    'use strict';

                    var LANG = {
                        empty: '{{ __('warehouse.receipt_search_empty') }}',
                        add: '{{ __('warehouse.add_line_btn') }}',
                        stockNow: '{{ __('warehouse.stock_now') }}',
                        selectedEmpty: '{{ __('warehouse.receipt_selected_empty') }}',
                        remove: '{{ __('warehouse.variant_remove_row') }}',
                        networkError: '{{ __('warehouse.network_error') }}'
                    };

                    var searchUrl = '{{ route('warehouse.variants.search') }}';

                    /* ===== Idempotency: bitta kirim uchun bitta kalit =====
                       Sahifa qayta yuklansa ham o'sha kalit ishlatiladi;
                       kirim muvaffaqiyatli saqlangach kalit tozalanadi. */
                    var KEY_STORAGE = 'ustbosh_receipt_idempotency_key';
                    var idempotencyInput = document.querySelector('#receipt-form input[name="idempotency_key"]');

                    if (idempotencyInput) {
                        var storedKey = null;
                        try { storedKey = sessionStorage.getItem(KEY_STORAGE); } catch (e) { /* ignore */ }

                        if (!storedKey) {
                            storedKey = (window.crypto && crypto.randomUUID)
                                ? crypto.randomUUID()
                                : Date.now() + '-' + Math.random().toString(36).slice(2);

                            try { sessionStorage.setItem(KEY_STORAGE, storedKey); } catch (e) { /* ignore */ }
                        }

                        idempotencyInput.value = storedKey;
                    }

                    document.addEventListener('ustbosh:ajax:success', function (event) {
                        var form = event.detail && event.detail.form;
                        if (!form || form.id !== 'receipt-form') return;
                        try { sessionStorage.removeItem(KEY_STORAGE); } catch (e) { /* ignore */ }
                    });

                    var searchInput = document.getElementById('receipt-search');
                    var resultsEl = document.getElementById('receipt-results');
                    var linesEl = document.getElementById('receipt-lines');
                    var hiddenEl = document.getElementById('receipt-hidden');
                    var submitBtn = document.getElementById('receipt-submit-btn');
                    var countEl = document.getElementById('receipt-lines-count');

                    var lines = {}; // variantId -> {qty, product_name, label, sku, stock}
                    var searchTimer = null;

                    /* ===== Qidiruv ===== */
                    function search(q) {
                        fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        })
                            .then(function (response) { return response.json(); })
                            .then(function (data) { renderResults(data.variants || []); })
                            .catch(function () { window.ustboshToast(LANG.networkError, 'error'); });
                    }

                    searchInput.addEventListener('input', function () {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(function () { search(searchInput.value.trim()); }, 250);
                    });

                    searchInput.addEventListener('keydown', function (event) {
                        if (event.key !== 'Enter') return;
                        event.preventDefault();
                        clearTimeout(searchTimer);
                        search(searchInput.value.trim());
                    });

                    function renderResults(variants) {
                        resultsEl.innerHTML = '';

                        if (!variants.length) {
                            var empty = document.createElement('div');
                            empty.className = 'sm:col-span-2 rounded-box border border-line bg-card p-8 text-center text-sm text-ink-soft';
                            empty.textContent = LANG.empty;
                            resultsEl.appendChild(empty);
                            return;
                        }

                        variants.forEach(function (variant) {
                            resultsEl.appendChild(buildResultCard(variant));
                        });
                    }

                    function buildResultCard(variant) {
                        var card = document.createElement('div');
                        card.className = 'rounded-box border border-line bg-card p-4 shadow-elev';

                        var name = document.createElement('div');
                        name.className = 'font-semibold leading-snug';
                        name.textContent = variant.product_name;
                        card.appendChild(name);

                        var meta = document.createElement('div');
                        meta.className = 'mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 font-mono text-xs text-ink-soft';
                        var labelSpan = document.createElement('span');
                        labelSpan.textContent = variant.label;
                        var skuSpan = document.createElement('span');
                        skuSpan.textContent = variant.sku;
                        meta.appendChild(labelSpan);
                        meta.appendChild(skuSpan);
                        card.appendChild(meta);

                        var bottom = document.createElement('div');
                        bottom.className = 'mt-3 flex items-center justify-between gap-2';

                        var stock = document.createElement('span');
                        stock.className = 'badge badge-sm border-0 ' + (variant.stock > 0 ? 'bg-ok/15 text-ok' : 'bg-err/15 text-err');
                        stock.textContent = LANG.stockNow.replace(':count', variant.stock);
                        bottom.appendChild(stock);

                        var addBtn = document.createElement('button');
                        addBtn.type = 'button';
                        addBtn.className = 'btn btn-xs btn-outline btn-secondary';
                        addBtn.textContent = '+ ' + LANG.add;
                        addBtn.onclick = function () { addLine(variant); };
                        bottom.appendChild(addBtn);

                        card.appendChild(bottom);

                        return card;
                    }

                    /* ===== Tanlangan qatorlar ===== */
                    function addLine(variant) {
                        if (lines[variant.id]) {
                            lines[variant.id].qty += 1;
                        } else {
                            lines[variant.id] = {
                                qty: 1,
                                product_name: variant.product_name,
                                label: variant.label,
                                sku: variant.sku,
                                stock: variant.stock
                            };
                        }

                        renderLines();
                    }

                    function changeQty(id, delta) {
                        var line = lines[id];
                        if (!line) return;

                        line.qty += delta;

                        if (line.qty <= 0) {
                            delete lines[id];
                        }

                        renderLines();
                    }

                    function removeLine(id) {
                        delete lines[id];
                        renderLines();
                    }

                    function renderLines() {
                        var ids = Object.keys(lines);

                        linesEl.innerHTML = '';
                        submitBtn.disabled = ids.length === 0;
                        countEl.textContent = ids.length;

                        if (!ids.length) {
                            var empty = document.createElement('p');
                            empty.className = 'py-6 text-center text-sm text-ink-soft';
                            empty.textContent = LANG.selectedEmpty;
                            linesEl.appendChild(empty);
                        }

                        ids.forEach(function (id) {
                            linesEl.appendChild(buildLine(id, lines[id]));
                        });

                        syncHiddenInputs();
                    }

                    /* Serverga yuboriladigan yashirin maydonlarni yangilash
                       (miqdor maydonida yozilganda ham chaqiriladi) */
                    function syncHiddenInputs() {
                        var ids = Object.keys(lines);

                        hiddenEl.innerHTML = '';

                        ids.forEach(function (id, index) {
                            var hiddenId = document.createElement('input');
                            hiddenId.type = 'hidden';
                            hiddenId.name = 'items[' + index + '][variant_id]';
                            hiddenId.value = id;

                            var hiddenQty = document.createElement('input');
                            hiddenQty.type = 'hidden';
                            hiddenQty.name = 'items[' + index + '][quantity]';
                            hiddenQty.value = lines[id].qty;

                            hiddenEl.appendChild(hiddenId);
                            hiddenEl.appendChild(hiddenQty);
                        });
                    }

                    function buildLine(id, line) {
                        var row = document.createElement('div');
                        row.className = 'rounded-field border border-line bg-base-100/60 p-3';

                        var top = document.createElement('div');
                        top.className = 'flex items-start justify-between gap-2';

                        var nameWrap = document.createElement('div');
                        nameWrap.className = 'min-w-0';
                        var name = document.createElement('div');
                        name.className = 'text-sm font-semibold leading-snug';
                        name.textContent = line.product_name;
                        var meta = document.createElement('div');
                        meta.className = 'font-mono text-xs text-ink-soft';
                        meta.textContent = line.label + ' · ' + line.sku;
                        nameWrap.appendChild(name);
                        nameWrap.appendChild(meta);
                        top.appendChild(nameWrap);

                        var remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'btn btn-xs btn-ghost text-err shrink-0';
                        remove.textContent = '✕';
                        remove.title = LANG.remove;
                        remove.onclick = function () { removeLine(id); };
                        top.appendChild(remove);

                        row.appendChild(top);

                        var bottom = document.createElement('div');
                        bottom.className = 'mt-2 flex items-center justify-between gap-2';

                        var stepper = document.createElement('div');
                        stepper.className = 'inline-flex items-center gap-1';

                        var minus = document.createElement('button');
                        minus.type = 'button';
                        minus.className = 'btn btn-xs btn-outline btn-secondary min-w-9 min-h-9';
                        minus.textContent = '−';
                        minus.onclick = function () { changeQty(id, -1); };

                        // Miqdorni qo'lda yozish ham mumkin (masalan, 1000 dona)
                        var qtyInput = document.createElement('input');
                        qtyInput.type = 'number';
                        qtyInput.min = '1';
                        qtyInput.max = '100000';
                        qtyInput.inputMode = 'numeric';
                        qtyInput.value = line.qty;
                        qtyInput.className = 'input input-sm w-20 bg-base-100 text-center font-mono';
                        qtyInput.addEventListener('input', function () {
                            var value = parseInt(qtyInput.value, 10);

                            if (isNaN(value) || value < 1) return;

                            lines[id].qty = Math.min(value, 100000);
                            syncHiddenInputs();
                        });
                        qtyInput.addEventListener('blur', function () {
                            var value = parseInt(qtyInput.value, 10);

                            if (isNaN(value) || value < 1) value = 1;

                            value = Math.min(value, 100000);
                            qtyInput.value = value;
                            lines[id].qty = value;
                            syncHiddenInputs();
                        });

                        var plus = document.createElement('button');
                        plus.type = 'button';
                        plus.className = 'btn btn-xs btn-outline btn-secondary min-w-9 min-h-9';
                        plus.textContent = '+';
                        plus.onclick = function () { changeQty(id, 1); };

                        stepper.appendChild(minus);
                        stepper.appendChild(qtyInput);
                        stepper.appendChild(plus);
                        bottom.appendChild(stepper);

                        var stock = document.createElement('span');
                        stock.className = 'font-mono text-xs text-ink-soft';
                        stock.textContent = LANG.stockNow.replace(':count', line.stock);
                        bottom.appendChild(stock);

                        row.appendChild(bottom);

                        return row;
                    }

                    /* Boshlang'ich holat: bo'sh ro'yxat va dastlabki natijalar */
                    renderLines();
                    search('');
                })();
            </script>
        @endpush
    @endif
@endsection
