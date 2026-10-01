@extends('layouts.app')

@section('title', __('pos.title'))

@section('content')
    <section class="py-8 pb-28 lg:pb-8">
        <div class="mx-auto max-w-6xl px-4">

            {{-- Sarlavha --}}
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-2xl md:text-3xl">{{ __('pos.title') }}</h1>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('pos.subtitle') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="scanner-link-btn" class="btn btn-outline btn-accent btn-sm"
                            onclick="openScannerDialog()">
                        📱 {{ __('pos.scanner_link') }}
                    </button>
                    <a href="{{ route('pos.sales.index') }}" class="btn btn-outline btn-secondary btn-sm">
                        🧾 {{ __('pos.sales_history') }}
                    </a>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_400px] items-start">

                {{-- Chap: qidiruv + natijalar --}}
                <div>
                    <div class="rounded-box border border-line bg-card p-4 shadow-elev">
                        <label for="pos-search" class="mb-1.5 block text-sm font-semibold">{{ __('pos.title') }}</label>
                        <input id="pos-search" type="search" autofocus autocomplete="off"
                               placeholder="{{ __('pos.search_placeholder') }}"
                               class="input w-full bg-base-100 text-lg" />
                    </div>

                    <div id="pos-results" class="mt-4 grid items-start gap-3 sm:grid-cols-2" aria-live="polite">
                        <noscript>
                            <p class="text-sm text-ink-soft">{{ __('pos.results_empty_hint') }}</p>
                        </noscript>
                    </div>
                </div>

                {{-- O'ng: savat (desktop) --}}
                <aside id="cart-column" class="lg:sticky lg:top-20 hidden lg:block">
                    <div class="rounded-box border border-line bg-card shadow-elev" id="cart-content-wrap">
                        @include('pos.partials.cart')
                    </div>
                </aside>
            </div>
        </div>
    </section>

    {{-- Mobil: pastki savat paneli --}}
    <div class="fixed bottom-0 inset-x-0 z-40 lg:hidden border-t border-line bg-base-100/95 backdrop-blur">
        <button type="button" class="w-full px-4 py-3 flex items-center justify-between gap-2"
                onclick="openCartDialog()">
            <span class="font-bold">🛒 {{ __('pos.cart') }}</span>
            <span id="mobile-cart-summary" class="font-mono text-sm text-ink-soft">{{ __('pos.cart_empty') }}</span>
        </button>
    </div>

    {{-- Mobil savat dialogi --}}
    <dialog id="cart-dialog" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box rounded-t-box sm:rounded-box border border-line bg-card max-w-md">
            <div id="cart-dialog-host"></div>
            <div class="modal-action">
                <button type="button" class="btn btn-ghost" onclick="closeCartDialog()">{{ __('admin.cancel') }}</button>
            </div>
        </div>
    </dialog>

    {{-- Sotuv natijasi modal --}}
    <dialog id="sale-modal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box rounded-box border border-line bg-card max-w-sm text-center">
            <span class="inline-grid size-14 place-items-center rounded-full bg-ok/15 text-ok text-2xl">✓</span>
            <h3 class="font-display mt-3 text-xl">{{ __('pos.sale_saved') }}</h3>

            <div class="mt-4 rounded-field border border-line bg-base-100/60 p-4 space-y-2 text-left">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-soft">{{ __('pos.sale_no') }}:</span>
                    <span id="sale-no" class="font-mono font-bold"></span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-ink-soft">{{ __('pos.sale_total') }}:</span>
                    <span id="sale-total" class="font-mono font-bold text-accent"></span>
                </div>
                <div id="sale-received-row" class="hidden items-center justify-between text-sm">
                    <span class="text-ink-soft">{{ __('pos.amount_received_label') }}:</span>
                    <span id="sale-received" class="font-mono"></span>
                </div>
                <div id="sale-change-row" class="hidden items-center justify-between text-sm">
                    <span class="text-ink-soft">{{ __('pos.change_label') }}:</span>
                    <span id="sale-change" class="font-mono font-bold text-ok"></span>
                </div>
            </div>

            <div class="mt-5 space-y-2">
                <a id="sale-print-link" href="#" class="btn btn-accent w-full min-h-12 shadow-elev">
                    🖨 {{ __('pos.print_receipt') }}
                </a>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" id="sale-new-btn" class="btn btn-outline btn-secondary">
                        ＋ {{ __('pos.new_sale') }}
                    </button>
                    <a href="{{ route('pos.sales.index') }}" class="btn btn-ghost">
                        {{ __('pos.sales_history') }}
                    </a>
                </div>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    {{-- Skaner dialog --}}
    <dialog id="scanner-dialog" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box rounded-t-box sm:rounded-box border border-line bg-card max-w-sm text-center relative">
            <button type="button" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2" onclick="hideScannerDialog()">✕</button>
            <h3 class="font-display text-lg">📱 {{ __('pos.scanner_title') }}</h3>
            <p id="scanner-status" class="mt-2 text-sm text-ink-soft">{{ __('pos.scanner_waiting') }}</p>
            <div class="mt-4 flex justify-center">
                <canvas id="scanner-qr-canvas"></canvas>
            </div>
            <div class="modal-action justify-center gap-2">
                <button type="button" class="btn btn-outline" onclick="hideScannerDialog()">
                    Yashirish
                </button>
                <button type="button" class="btn btn-ghost text-error" onclick="closeScannerDialog()">
                    {{ __('pos.scanner_disconnect') }}
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button onclick="hideScannerDialog()">close</button></form>
    </dialog>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/qrcode@1/build/qrcode.min.js"></script>
        <script>
            window.POS_DATA = @json($productsJson);
        </script>
        <script>
            (function () {
                'use strict';

                var LANG = {
                    add: '{{ __('pos.add_btn') }}',
                    inStock: '{{ __('pos.in_stock') }}',
                    variantCount: @json(__('pos.variant_count')),
                    resultsEmpty: '{{ __('pos.results_empty') }}',
                    resultsEmptyHint: '{{ __('pos.results_empty_hint') }}',
                    cart: '{{ __('pos.cart') }}',
                    cartEmpty: '{{ __('pos.cart_empty') }}',
                    cartSubtitle: '{{ __('pos.cart_subtitle') }}',
                    cartQty: '{{ __('pos.cart_qty') }}',
                    cartLineTotal: '{{ __('pos.cart_line_total') }}',
                    cartRemove: '{{ __('pos.cart_remove') }}',
                    cartClear: '{{ __('pos.cart_clear') }}',
                    cartSubtotal: '{{ __('pos.cart_subtotal') }}',
                    cartTotal: '{{ __('pos.cart_total') }}',
                    cartCount: '{{ __('pos.cart_count') }}',
                    completeBtn: '{{ __('pos.complete_btn') }}',
                    processing: '{{ __('pos.complete_btn_processing') }}',
                    saleSaved: '{{ __('pos.sale_saved') }}',
                    saleNo: '{{ __('pos.sale_no') }}',
                    saleTotal: '{{ __('pos.sale_total') }}',
                    toastSuccess: '{{ __('pos.toast_success') }}',
                    toastError: '{{ __('pos.toast_error') }}',
                    networkError: '{{ __('pos.network_error') }}',
                    amountReceived: '{{ __('pos.amount_received_label') }}',
                    amountReceivedPlaceholder: '{{ __('pos.amount_received_placeholder') }}',
                    amountReceivedInsufficient: '{{ __('pos.amount_received_insufficient') }}',
                    changeLabel: '{{ __('pos.change_label') }}'
                };

                var salePrintUrlTemplate = @json(route('pos.sales.show', ['sale' => '__ID__', 'print' => 1]));

                var fmt = function (n) {
                    return Number(n).toLocaleString('ru-RU', { maximumFractionDigits: 0 }) + ' so\'m';
                };

                var cart = {}; // variantId -> {qty, price, name, label, max}
                var idempotencyKey = genIdempotencyKey();
                var amountReceived = '';
                var paymentMethod = 'cash';

                /* ===== Qidiruv ===== */
                var searchInput = document.getElementById('pos-search');
                var resultsEl = document.getElementById('pos-results');
                var searchTimer = null;

                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(function () { runSearch(); }, 250);
                });
                searchInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(searchTimer);
                        runSearch();
                    }
                });

                function runSearch() {
                    var q = searchInput.value.trim();
                    window.ustboshAjax('{{ route('pos.catalog') }}' + '?q=' + encodeURIComponent(q), { method: 'GET' })
                        .then(function (result) {
                            if (result.ok) renderResults(result.data.products || []);
                            else resultsEl.innerHTML = '';
                        })
                        .catch(function () {
                            resultsEl.innerHTML = '';
                        });
                }

                function renderResults(products) {
                    resultsEl.innerHTML = '';

                    if (!products.length) {
                        var empty = document.createElement('div');
                        empty.className = 'sm:col-span-2 rounded-box border border-line bg-card p-10 text-center';
                        var title = document.createElement('div');
                        title.className = 'font-display text-lg';
                        title.textContent = LANG.resultsEmpty;
                        var hint = document.createElement('p');
                        hint.className = 'mt-1 text-sm text-ink-soft';
                        hint.textContent = LANG.resultsEmptyHint;
                        empty.appendChild(title);
                        empty.appendChild(hint);
                        resultsEl.appendChild(empty);
                        return;
                    }

                    products.forEach(function (product) {
                        resultsEl.appendChild(buildProductCard(product));
                    });
                }

                function buildProductCard(product) {
                    var card = document.createElement('div');
                    card.className = 'rounded-box border border-line bg-card p-4 shadow-elev';

                    var head = document.createElement('div');
                    var name = document.createElement('div');
                    name.className = 'font-semibold leading-snug';
                    name.textContent = product.name;
                    head.appendChild(name);
                    var meta = document.createElement('div');
                    meta.className = 'mt-0.5 flex items-center gap-2 text-xs text-ink-soft font-mono';
                    var sku = document.createElement('span');
                    sku.textContent = product.sku;
                    meta.appendChild(sku);
                    if (product.category) {
                        var cat = document.createElement('span');
                        cat.textContent = product.category;
                        meta.appendChild(cat);
                    }
                    head.appendChild(meta);
                    card.appendChild(head);

                    var price = document.createElement('div');
                    price.className = 'mt-2 font-mono font-bold text-accent';
                    price.textContent = fmt(product.price);
                    card.appendChild(price);

                    var variants = document.createElement('div');
                    var manyVariants = product.variants.length > 6;

                    if (manyVariants) {
                        var variantsCount = document.createElement('div');
                        variantsCount.className = 'mt-3 text-[11px] font-semibold uppercase tracking-wider text-ink-soft';
                        variantsCount.textContent = LANG.variantCount.replace(':count', product.variants.length);
                        card.appendChild(variantsCount);
                    }

                    // Variantlar ko'p bo'lsa karta cho'zilib ketmasligi uchun
                    // ro'yxat balandligi cheklanadi va ichida scroll bo'ladi.
                    variants.className = 'flex max-h-52 flex-wrap gap-1.5 overflow-y-auto overscroll-contain pr-1' +
                        ' [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5' +
                        ' [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-line' +
                        (manyVariants ? ' mt-2' : ' mt-3');
                    product.variants.forEach(function (variant) {
                        variants.appendChild(buildVariantButton(product, variant));
                    });
                    card.appendChild(variants);

                    return card;
                }

                function buildVariantButton(product, variant) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-sm max-w-full gap-1 border border-line bg-base-100 min-h-11';
                    btn.style.alignItems = 'center';

                    var label = document.createElement('span');
                    label.className = 'truncate min-w-0 font-semibold';
                    label.textContent = variant.label;
                    btn.appendChild(label);

                    var stock = document.createElement('span');
                    stock.className = 'shrink-0 text-xs font-mono text-ink-soft';
                    stock.textContent = LANG.inStock.replace(':count', variant.stock);
                    btn.appendChild(stock);

                    btn.title = variant.label + ' · ' + stock.textContent;

                    var disabled = variant.stock <= 0;
                    btn.disabled = disabled;
                    if (disabled) {
                        btn.classList.add('opacity-40', 'pointer-events-none');
                    } else {
                        btn.dataset.variantId = variant.id;
                        btn.dataset.stock = variant.stock;
                        btn.dataset.price = product.price;
                        btn.dataset.name = product.name;
                        btn.dataset.label = variant.label;
                    }

                    return btn;
                }

                resultsEl.addEventListener('click', function (e) {
                    var btn = e.target.closest('[data-variant-id]');
                    if (!btn) return;
                    addToCart(
                        btn.dataset.variantId,
                        btn.dataset.name,
                        btn.dataset.label,
                        parseFloat(btn.dataset.price),
                        parseInt(btn.dataset.stock, 10)
                    );
                });

                /* ===== Savat ===== */
                function addToCart(id, name, label, price, max) {
                    if (cart[id]) {
                        if (cart[id].qty < Math.min(max, 999)) cart[id].qty++;
                    } else {
                        cart[id] = { qty: 1, name: name, label: label, price: price, max: max };
                    }
                    renderCart();
                }
                window.addToCart = addToCart;

                function changeQty(id, delta) {
                    var line = cart[id];
                    if (!line) return;
                    line.qty += delta;
                    if (line.qty <= 0) {
                        delete cart[id];
                    } else {
                        line.qty = Math.min(line.qty, Math.min(line.max, 999));
                    }
                    renderCart();
                }

                function clearCart() {
                    cart = {};
                    renderCart();
                }

                function cartCount() {
                    return Object.keys(cart).reduce(function (sum, id) { return sum + cart[id].qty; }, 0);
                }

                function cartTotal() {
                    return Object.keys(cart).reduce(function (sum, id) { return sum + cart[id].qty * cart[id].price; }, 0);
                }

                function renderCart() {
                    var host = document.getElementById('cart-content-wrap');
                    if (document.getElementById('cart-dialog').open) {
                        host = document.getElementById('cart-dialog-host');
                    }
                    host.innerHTML = '';
                    host.appendChild(buildCartContent());

                    var count = cartCount();
                    var summary = count > 0
                        ? LANG.cartCount.replace(':count', count).replace(':total', fmt(cartTotal()))
                        : LANG.cartEmpty;
                    document.getElementById('mobile-cart-summary').textContent = summary;
                }

                function buildCartContent() {
                    var wrap = document.createElement('div');

                    var head = document.createElement('div');
                    head.className = 'px-5 py-4 border-b border-line/60 flex items-center justify-between';
                    var title = document.createElement('h2');
                    title.className = 'font-display text-lg';
                    title.textContent = LANG.cart;
                    head.appendChild(title);
                    if (cartCount() > 0) {
                        var clearBtn = document.createElement('button');
                        clearBtn.type = 'button';
                        clearBtn.className = 'btn btn-xs btn-ghost text-err';
                        clearBtn.textContent = LANG.cartClear;
                        clearBtn.onclick = clearCart;
                        head.appendChild(clearBtn);
                    }
                    wrap.appendChild(head);

                    var body = document.createElement('div');
                    body.className = 'p-4 space-y-3 max-h-[50vh] overflow-y-auto';

                    var ids = Object.keys(cart);
                    if (!ids.length) {
                        var empty = document.createElement('div');
                        empty.className = 'text-center py-8';
                        var emoji = document.createElement('div');
                        emoji.className = 'text-3xl';
                        emoji.textContent = '🛒';
                        var text = document.createElement('p');
                        text.className = 'mt-2 text-sm text-ink-soft';
                        text.textContent = LANG.cartSubtitle;
                        empty.appendChild(emoji);
                        empty.appendChild(text);
                        body.appendChild(empty);
                    } else {
                        ids.forEach(function (id) {
                            body.appendChild(buildCartLine(id, cart[id]));
                        });
                    }
                    wrap.appendChild(body);

                    if (ids.length) {
                        var totals = document.createElement('div');
                        totals.className = 'px-5 py-4 border-t border-line/60 space-y-1';

                        var sub = document.createElement('div');
                        sub.className = 'flex items-center justify-between text-sm text-ink-soft';
                        var subLabel = document.createElement('span');
                        subLabel.textContent = LANG.cartSubtotal;
                        var subValue = document.createElement('span');
                        subValue.className = 'font-mono';
                        subValue.textContent = fmt(cartTotal());
                        sub.appendChild(subLabel);
                        sub.appendChild(subValue);
                        totals.appendChild(sub);

                        var total = document.createElement('div');
                        total.className = 'flex items-center justify-between text-base font-bold';
                        var totalLabel = document.createElement('span');
                        totalLabel.textContent = LANG.cartTotal;
                        var totalValue = document.createElement('span');
                        totalValue.className = 'font-mono text-accent';
                        totalValue.id = 'cart-total';
                        totalValue.textContent = fmt(cartTotal());
                        total.appendChild(totalLabel);
                        total.appendChild(totalValue);
                        totals.appendChild(total);
                        wrap.appendChild(totals);

                        var actions = document.createElement('div');
                        actions.className = 'px-5 py-4 border-t border-line/60 space-y-3';

                        var payLabel = document.createElement('label');
                        payLabel.className = 'block text-sm font-semibold';
                        payLabel.textContent = '{{ __('pos.payment_method') }}';
                        actions.appendChild(payLabel);

                        var payGroup = document.createElement('div');
                        payGroup.className = 'grid grid-cols-2 gap-2';
                        [['cash', '{{ __('pos.payment_cash') }}'], ['card', '{{ __('pos.payment_card') }}']].forEach(function (pair) {
                            var label = document.createElement('label');
                            label.className = 'btn btn-sm border border-line bg-base-100 gap-2';
                            var input = document.createElement('input');
                            input.type = 'radio';
                            input.name = 'payment';
                            input.value = pair[0];
                            input.className = 'radio radio-xs radio-accent';
                            if (pair[0] === paymentMethod) input.checked = true;
                            var span = document.createElement('span');
                            span.textContent = pair[1];
                            label.appendChild(input);
                            label.appendChild(span);
                            payGroup.appendChild(label);
                        });
                        actions.appendChild(payGroup);

                        // Naqd to'lovda olingan summa va qaytim
                        var cashBlock = document.createElement('div');
                        cashBlock.id = 'cash-block';
                        cashBlock.className = 'hidden';

                        var cashLabel = document.createElement('label');
                        cashLabel.className = 'block text-sm font-semibold mt-3';
                        cashLabel.setAttribute('for', 'amount-received');
                        cashLabel.textContent = LANG.amountReceived;

                        var cashInput = document.createElement('input');
                        cashInput.id = 'amount-received';
                        cashInput.type = 'number';
                        cashInput.min = '0';
                        cashInput.step = '1000';
                        cashInput.placeholder = LANG.amountReceivedPlaceholder;
                        cashInput.className = 'input w-full bg-base-100 mt-1.5';
                        cashInput.value = amountReceived;

                        var changeLine = document.createElement('div');
                        changeLine.id = 'change-line';
                        changeLine.className = 'mt-1.5 text-sm';

                        cashBlock.appendChild(cashLabel);
                        cashBlock.appendChild(cashInput);
                        cashBlock.appendChild(changeLine);
                        actions.appendChild(cashBlock);

                        function refreshCashBlock() {
                            var checked = document.querySelector('input[name="payment"]:checked');
                            var isCash = !checked || checked.value === 'cash';
                            cashBlock.classList.toggle('hidden', !isCash);

                            var received = parseFloat(cashInput.value || '');
                            if (isCash && !isNaN(received)) {
                                var diff = received - cartTotal();
                                changeLine.textContent = diff >= 0
                                    ? LANG.changeLabel + ': ' + fmt(diff)
                                    : LANG.amountReceivedInsufficient;
                                changeLine.className = 'mt-1.5 text-sm font-semibold ' + (diff >= 0 ? 'text-ok' : 'text-err');
                            } else {
                                changeLine.textContent = '';
                                changeLine.className = 'mt-1.5 text-sm';
                            }
                        }

                        payGroup.querySelectorAll('input[name="payment"]').forEach(function (input) {
                            input.addEventListener('change', function () {
                                paymentMethod = input.value;
                                refreshCashBlock();
                            });
                        });
                        cashInput.addEventListener('input', function () {
                            amountReceived = cashInput.value;
                            refreshCashBlock();
                        });
                        refreshCashBlock();

                        var checkoutBtn = document.createElement('button');
                        checkoutBtn.type = 'button';
                        checkoutBtn.id = 'checkout-btn';
                        checkoutBtn.className = 'btn btn-accent w-full shadow-elev min-h-12 mt-3';
                        checkoutBtn.textContent = LANG.completeBtn;
                        checkoutBtn.onclick = checkout;
                        actions.appendChild(checkoutBtn);

                        wrap.appendChild(actions);
                    }

                    return wrap;
                }

                function buildCartLine(id, line) {
                    var row = document.createElement('div');
                    row.className = 'rounded-field border border-line bg-base-100/60 p-3';

                    var top = document.createElement('div');
                    top.className = 'flex items-start justify-between gap-2';
                    var name = document.createElement('div');
                    name.className = 'font-semibold text-sm leading-snug';
                    name.textContent = line.name;
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'btn btn-xs btn-ghost text-err shrink-0';
                    remove.textContent = '✕';
                    remove.title = LANG.cartRemove;
                    remove.onclick = function () { delete cart[id]; renderCart(); };
                    top.appendChild(name);
                    top.appendChild(remove);
                    row.appendChild(top);

                    var label = document.createElement('div');
                    label.className = 'text-xs text-ink-soft mt-0.5';
                    label.textContent = line.label;
                    row.appendChild(label);

                    var bottom = document.createElement('div');
                    bottom.className = 'mt-2 flex items-center justify-between gap-2';

                    var stepper = document.createElement('div');
                    stepper.className = 'inline-flex items-center gap-1';
                    var minus = document.createElement('button');
                    minus.type = 'button';
                    minus.className = 'btn btn-xs btn-outline btn-secondary min-w-9 min-h-9';
                    minus.textContent = '−';
                    minus.onclick = function () { changeQty(id, -1); };
                    var qtySpan = document.createElement('span');
                    qtySpan.className = 'font-mono font-bold w-8 text-center';
                    qtySpan.textContent = line.qty;
                    var plus = document.createElement('button');
                    plus.type = 'button';
                    plus.className = 'btn btn-xs btn-outline btn-secondary min-w-9 min-h-9';
                    plus.textContent = '+';
                    plus.onclick = function () { changeQty(id, 1); };
                    stepper.appendChild(minus);
                    stepper.appendChild(qtySpan);
                    stepper.appendChild(plus);
                    bottom.appendChild(stepper);

                    var lineTotal = document.createElement('div');
                    lineTotal.className = 'font-mono text-sm font-bold';
                    lineTotal.textContent = fmt(line.qty * line.price);
                    bottom.appendChild(lineTotal);

                    row.appendChild(bottom);
                    return row;
                }

                /* ===== Checkout ===== */
                function checkout() {
                    var ids = Object.keys(cart);
                    if (!ids.length) return;

                    var checkedInput = document.querySelector('input[name="payment"]:checked');
                    if (checkedInput) {
                        paymentMethod = checkedInput.value;
                    }

                    if (paymentMethod === 'cash' && amountReceived !== '' && parseFloat(amountReceived) < cartTotal()) {
                        window.ustboshToast(LANG.amountReceivedInsufficient, 'error');
                        return;
                    }

                    var btn = document.getElementById('checkout-btn');
                    btn.disabled = true;
                    btn.textContent = LANG.processing;

                    var body = new URLSearchParams();
                    body.set('payment_method', paymentMethod);
                    // Kalit sotuv yakunlanguncha o'zgarmaydi — tarmoq uzilganda
                    // qayta urinish takroriy sotuv yaratmasligi kerak.
                    body.set('idempotency_key', idempotencyKey);
                    if (paymentMethod === 'cash' && amountReceived !== '') {
                        body.set('amount_received', String(amountReceived));
                    }
                    ids.forEach(function (id, i) {
                        body.append('items[' + i + '][variant_id]', id);
                        body.append('items[' + i + '][quantity]', cart[id].qty);
                    });

                    window.ustboshAjax('{{ route('pos.sales.store') }}', {
                        method: 'POST',
                        body: body.toString(),
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }
                    }).then(function (result) {
                        if (result.ok) {
                            // Avval mobil savat oynasini yopamiz — natija oynasi ustma-ust tushmasin
                            var cartDialog = document.getElementById('cart-dialog');
                            if (cartDialog.open) {
                                closeCartDialog();
                            }

                            var sale = result.data.sale || {};
                            showSaleModal(sale);
                            clearCart();
                            idempotencyKey = genIdempotencyKey();
                            amountReceived = '';
                            runSearch();
                        } else {
                            var msg = (result.data && result.data.message) || LANG.toastError;
                            window.ustboshToast(msg, 'error');
                            runSearch();
                        }
                    }).catch(function () {
                        window.ustboshToast(LANG.networkError, 'error');
                    }).finally(function () {
                        btn.disabled = false;
                        btn.textContent = LANG.completeBtn;
                    });
                }

                function genIdempotencyKey() {
                    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
                    return Date.now() + '-' + Math.random().toString(36).slice(2);
                }

                /* ===== Modallar ===== */
                function openCartDialog() {
                    var dialog = document.getElementById('cart-dialog');
                    var host = document.getElementById('cart-dialog-host');
                    var content = document.getElementById('cart-content-wrap');
                    host.innerHTML = '';
                    host.appendChild(content);
                    dialog.showModal();
                    renderCart();
                }

                function closeCartDialog() {
                    var dialog = document.getElementById('cart-dialog');
                    var column = document.getElementById('cart-column');
                    var host = document.getElementById('cart-dialog-host');
                    if (host.firstChild) column.appendChild(host.firstChild);
                    dialog.close();
                    renderCart();
                }

                function showSaleModal(sale) {
                    document.getElementById('sale-no').textContent = sale.sale_no || '—';
                    document.getElementById('sale-total').textContent = fmt(sale.total || 0);

                    var receivedRow = document.getElementById('sale-received-row');
                    if (sale.amount_received !== null && sale.amount_received !== undefined) {
                        document.getElementById('sale-received').textContent = fmt(sale.amount_received);
                        receivedRow.classList.remove('hidden');
                        receivedRow.classList.add('flex');
                    } else {
                        receivedRow.classList.add('hidden');
                        receivedRow.classList.remove('flex');
                    }

                    var changeRow = document.getElementById('sale-change-row');
                    if (sale.change_amount !== null && sale.change_amount !== undefined) {
                        document.getElementById('sale-change').textContent = fmt(sale.change_amount);
                        changeRow.classList.remove('hidden');
                        changeRow.classList.add('flex');
                    } else {
                        changeRow.classList.add('hidden');
                        changeRow.classList.remove('flex');
                    }

                    var printLink = document.getElementById('sale-print-link');
                    if (sale.id) {
                        printLink.href = salePrintUrlTemplate.replace('__ID__', sale.id);
                        printLink.classList.remove('hidden');
                    } else {
                        printLink.classList.add('hidden');
                    }

                    document.getElementById('sale-modal').showModal();
                }

                function closeSaleModal() {
                    document.getElementById('sale-modal').close();
                    searchInput.focus();
                }

                document.getElementById('sale-new-btn').addEventListener('click', closeSaleModal);

                /* ===== Boshlang'ich natijalar ===== */
                renderResults(window.POS_DATA || []);
            })();
        </script>
        <script>
            (function () {
                'use strict';

                var sessionId = null;
                var pollingTimer = null;
                var lastScanId = 0;
                var retryTimeout = null;

                var SCANNER_LANG = {
                    starting: '{{ __("pos.scanner_starting") }}',
                    waiting: '{{ __("pos.scanner_waiting") }}',
                    expired: '{{ __("pos.scanner_expired") }}',
                    networkError: '{{ __("pos.network_error") }}',
                    toastError: '{{ __("pos.toast_error") }}',
                    notFound: '{{ __("pos.scanner_not_found") }}',
                    inactive: '{{ __("pos.scanner_inactive") }}'
                };

                window.openScannerDialog = function () {
                    var dialog = document.getElementById('scanner-dialog');
                    var status = document.getElementById('scanner-status');

                    if (sessionId && pollingTimer) {
                        // Already active, just show modal again
                        dialog.showModal();
                        return;
                    }

                    status.textContent = SCANNER_LANG.starting;
                    dialog.showModal();

                    window.ustboshAjax('{{ route("pos.scanner.store") }}', { method: 'POST' })
                        .then(function (result) {
                            if (!result.ok) {
                                status.textContent = SCANNER_LANG.toastError;
                                return;
                            }
                            sessionId = result.data.session_id;
                            var url = result.data.url;
                            status.textContent = SCANNER_LANG.waiting;

                            var canvas = document.getElementById('scanner-qr-canvas');
                            QRCode.toCanvas(canvas, url, { width: 220, margin: 2 });

                            lastScanId = 0;
                            startPolling();
                        })
                        .catch(function () {
                            status.textContent = SCANNER_LANG.networkError;
                        });
                };

                window.hideScannerDialog = function () {
                    document.getElementById('scanner-dialog').close();
                };

                window.closeScannerDialog = function () {
                    stopPolling();
                    if (sessionId) {
                        window.ustboshAjax(
                            '{{ url("/pos/scanner") }}/' + sessionId + '/close',
                            { method: 'DELETE' }
                        ).catch(function () {});
                        sessionId = null;
                    }
                    document.getElementById('scanner-dialog').close();
                };

                function startPolling() {
                    if (pollingTimer) return;
                    poll();
                }

                function stopPolling() {
                    clearTimeout(pollingTimer);
                    clearTimeout(retryTimeout);
                    pollingTimer = null;
                }

                function poll() {
                    if (!sessionId) return;

                    window.ustboshAjax(
                        '{{ url("/pos/scanner") }}/' + sessionId + '/events?after=' + lastScanId,
                        { method: 'GET' }
                    ).then(function (result) {
                        if (!result.ok) return;

                        if (!result.data.active) {
                            document.getElementById('scanner-status').textContent = SCANNER_LANG.expired;
                            stopPolling();
                            return;
                        }

                        var scans = result.data.scans || [];
                        scans.forEach(function (scan) {
                            if (scan.id > lastScanId) lastScanId = scan.id;
                            if (scan.status === 'found') {
                                window.addToCart(
                                    scan.variant_id,
                                    scan.name,
                                    scan.label,
                                    parseFloat(scan.price),
                                    parseInt(scan.stock, 10)
                                );
                                window.ustboshToast(scan.name + ' ✓', 'success');
                            } else if (scan.status === 'not_found') {
                                window.ustboshToast(scan.barcode + ' — ' + SCANNER_LANG.notFound, 'error');
                            } else if (scan.status === 'inactive') {
                                window.ustboshToast(scan.barcode + ' — ' + SCANNER_LANG.inactive, 'error');
                            }
                        });

                        pollingTimer = setTimeout(poll, 1500);
                    }).catch(function () {
                        retryTimeout = setTimeout(poll, 3000);
                    });
                }
            })();
        </script>
    @endpush
@endsection