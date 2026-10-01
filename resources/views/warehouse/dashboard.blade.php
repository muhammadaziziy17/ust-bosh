@extends('layouts.app')

@section('title', __('warehouse.title'))

@section('content')
    <section class="py-8">
        <div class="mx-auto max-w-6xl px-4">

            {{-- Sarlavha --}}
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-2xl md:text-3xl">{{ __('warehouse.title') }}</h1>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('warehouse.subtitle') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('warehouse.receipts.create') }}" class="btn btn-accent shadow-elev">
                        🚚 {{ __('warehouse.add_receipt') }}
                    </a>
                    <button type="button" class="btn btn-outline btn-secondary"
                            onclick="document.getElementById('product-modal').showModal()">
                        + {{ __('warehouse.new_product') }}
                    </button>
                </div>
            </div>

            @if (session('success'))
                <div class="sm-alert mt-6 rounded-field border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Statistika --}}
            <div class="mt-8 grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('warehouse.stat_products') }}</span>
                    <div class="font-mono text-3xl font-bold mt-1">{{ $stats['products'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('warehouse.stat_total_stock') }}</span>
                    <div class="font-mono text-3xl font-bold mt-1 text-ok">{{ $stats['total_stock'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('warehouse.stat_low_stock') }}</span>
                    <div class="font-mono text-3xl font-bold mt-1 text-accent">{{ $stats['low_stock'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('warehouse.stat_out_of_stock') }}</span>
                    <div class="font-mono text-3xl font-bold mt-1 text-err">{{ $stats['out_of_stock'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('warehouse.stat_receipts_today') }}</span>
                    <div class="font-mono text-3xl font-bold mt-1 text-info">{{ $stats['receipts_today'] }}</div>
                </div>
            </div>

            {{-- Tablar --}}
            <div class="mt-8 tabs tabs-boxed w-fit">
                <button type="button" data-wh-tab-btn="inventory"
                        class="tab {{ $tab === 'inventory' ? 'tab-active' : '' }}"
                        onclick="switchWhTab('inventory')">
                    📦 {{ __('warehouse.tab_inventory') }}
                </button>
                <button type="button" data-wh-tab-btn="receipts"
                        class="tab {{ $tab === 'receipts' ? 'tab-active' : '' }}"
                        onclick="switchWhTab('receipts')">
                    🚚 {{ __('warehouse.tab_receipts') }}
                </button>
                <button type="button" data-wh-tab-btn="movements"
                        class="tab {{ $tab === 'movements' ? 'tab-active' : '' }}"
                        onclick="switchWhTab('movements')">
                    🔁 {{ __('warehouse.tab_movements') }}
                </button>
            </div>

            {{-- Qoldiq bo'limi --}}
            <div data-wh-tab="inventory" class="{{ $tab === 'inventory' ? 'mt-6' : 'mt-6 hidden' }}">
                <div class="rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                        <div class="px-5 py-4 border-b border-line/60 flex flex-wrap items-center justify-between gap-3">
                            <h2 class="font-display text-lg">{{ __('warehouse.tab_inventory') }}</h2>
                            <form method="GET" action="{{ route('warehouse.dashboard') }}" class="flex gap-2 items-center">
                                <input type="hidden" name="tab" value="inventory">
                                <input type="search" name="q" value="{{ $search }}"
                                       placeholder="{{ __('warehouse.search_placeholder') }}"
                                       class="input input-sm w-full sm:w-64 bg-base-100" />
                                <button type="submit" class="btn btn-sm btn-accent">{{ __('warehouse.search_button') }}</button>
                            </form>
                        </div>

                        <table class="table">
                            <thead>
                                <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                    <th>{{ __('warehouse.table_product') }}</th>
                                    <th>{{ __('warehouse.table_sku') }}</th>
                                    <th class="text-right">{{ __('warehouse.table_price') }}</th>
                                    <th class="text-center">{{ __('warehouse.table_variants') }}</th>
                                    <th class="text-right">{{ __('warehouse.table_stock') }}</th>
                                    <th>{{ __('warehouse.table_status') }}</th>
                                    <th class="text-right">{{ __('warehouse.table_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($products as $product)
                                    <tr class="border-line/60">
                                        <td>
                                            <div class="font-semibold">{{ $product->name }}</div>
                                            @if ($product->category)
                                                <div class="text-xs text-ink-soft">{{ $product->category }}</div>
                                            @endif
                                        </td>
                                        <td class="font-mono text-sm">{{ $product->sku }}</td>
                                        <td class="text-right font-mono text-sm">{{ format_som($product->sale_price) }}</td>
                                        <td class="text-center font-mono text-sm">{{ $product->variants_count }}</td>
                                        <td class="text-right font-mono text-sm font-bold">{{ $product->total_stock ?? 0 }}</td>
                                        <td>
                                            @if (! $product->is_active)
                                                <span class="badge border-0 bg-base-200 text-ink-soft">{{ __('warehouse.status_inactive') }}</span>
                                            @elseif (($product->total_stock ?? 0) === 0)
                                                <span class="badge border-0 bg-err/15 text-err">{{ __('warehouse.status_out') }}</span>
                                            @else
                                                <span class="badge border-0 bg-ok/15 text-ok">{{ __('warehouse.status_active') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('warehouse.products.print', $product) }}" target="_blank"
                                                   class="btn btn-xs btn-outline btn-info gap-1"
                                                   title="{{ __('warehouse.print_barcodes') ?? 'Shtrix-kodlarni chop etish' }}">
                                                    🖨️
                                                </a>
                                                <button type="button" class="btn btn-xs btn-outline btn-secondary gap-1"
                                                        data-edit-product="{{ $product->id }}"
                                                        data-edit-url="{{ route('warehouse.products.show', $product) }}">
                                                    ✏️ {{ __('warehouse.edit_product') }}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-10 text-ink-soft">{{ __('warehouse.inventory_empty') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        @if ($products->hasPages())
                            <div class="px-5 py-4 border-t border-line/60">
                                {{ $products->links() }}
                            </div>
                        @endif
                </div>
            </div>

            {{-- Kirimlar bo'limi --}}
            <div data-wh-tab="receipts" class="{{ $tab === 'receipts' ? 'mt-6' : 'mt-6 hidden' }}">
                <div class="rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                    <div class="px-5 py-4 border-b border-line/60">
                        <h2 class="font-display text-lg">{{ __('warehouse.tab_receipts') }}</h2>
                    </div>
                    <table class="table">
                        <thead>
                            <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                <th>{{ __('warehouse.receipt_no') }}</th>
                                <th>{{ __('warehouse.receipt_date') }}</th>
                                <th>{{ __('warehouse.receipt_by') }}</th>
                                <th class="text-center">{{ __('warehouse.receipt_items') }}</th>
                                <th>{{ __('warehouse.receipt_status') }}</th>
                                <th>{{ __('warehouse.receipt_notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($receipts as $receipt)
                                <tr class="border-line/60">
                                    <td class="font-mono text-sm font-bold whitespace-nowrap">
                                        <a href="{{ route('warehouse.receipts.show', $receipt) }}" class="link link-hover">{{ $receipt->receipt_no }}</a>
                                    </td>
                                    <td class="text-sm whitespace-nowrap">{{ $receipt->received_at->format('d.m.Y H:i') }}</td>
                                    <td class="text-sm">{{ $receipt->creator?->name ?? '—' }}</td>
                                    <td class="text-center font-mono text-sm">{{ $receipt->items_count }}</td>
                                    <td>
                                        @if ($receipt->status === \App\Models\StockReceipt::STATUS_POSTED)
                                            <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('warehouse.receipt_status_posted') }}</span>
                                        @else
                                            <span class="badge badge-sm border-0 bg-err/15 text-err">{{ __('warehouse.receipt_status_cancelled') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-sm text-ink-soft">{{ $receipt->notes ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-10 text-ink-soft">{{ __('warehouse.receipts_empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    @if ($receipts->hasPages())
                        <div class="px-5 py-4 border-t border-line/60">
                            {{ $receipts->appends(['tab' => 'receipts'])->links() }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Harakatlar bo'limi --}}
            <div data-wh-tab="movements" class="{{ $tab === 'movements' ? 'mt-6' : 'mt-6 hidden' }}">
                <div class="rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                    <div class="px-5 py-4 border-b border-line/60 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="font-display text-lg">{{ __('warehouse.tab_movements') }}</h2>
                            <p class="text-xs text-ink-soft mt-0.5">{{ __('warehouse.movements_hint') }}</p>
                        </div>
                        <form method="GET" action="{{ route('warehouse.dashboard') }}" class="flex items-end gap-2">
                            <input type="hidden" name="tab" value="movements">
                            <div>
                                <label for="movement-type" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('warehouse.movement_type_label') }}</label>
                                <select id="movement-type" name="movement_type" class="select select-sm bg-base-100">
                                    @foreach (['all', 'receipt', 'receipt_cancel', 'sale', 'sale_void', 'adjustment'] as $type)
                                        <option value="{{ $type }}" @selected($movementType === $type)>
                                            {{ $type === 'all' ? __('warehouse.movement_filter_all') : __('warehouse.movement_'.$type) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-accent">{{ __('warehouse.search_button') }}</button>
                        </form>
                    </div>

                    <table class="table">
                        <thead>
                            <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                <th>{{ __('warehouse.movement_date') }}</th>
                                <th>{{ __('warehouse.movement_product') }}</th>
                                <th>{{ __('warehouse.movement_type_label') }}</th>
                                <th class="text-right">{{ __('warehouse.movement_delta') }}</th>
                                <th class="text-right">{{ __('warehouse.movement_stock_after') }}</th>
                                <th>{{ __('warehouse.movement_actor') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($movements as $movement)
                                <tr class="border-line/60">
                                    <td class="text-sm text-ink-soft whitespace-nowrap">{{ $movement->created_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td>
                                        <div class="font-semibold text-sm">{{ $movement->variant?->product?->name ?? '—' }}</div>
                                        <div class="font-mono text-xs text-ink-soft">{{ $movement->variant?->displayLabel() ?? '—' }}</div>
                                    </td>
                                    <td>
                                        @php
                                            $movementBadge = match ($movement->movement_type) {
                                                \App\Models\InventoryMovement::TYPE_RECEIPT => 'bg-ok/15 text-ok',
                                                \App\Models\InventoryMovement::TYPE_RECEIPT_CANCEL => 'bg-err/15 text-err',
                                                \App\Models\InventoryMovement::TYPE_SALE => 'bg-accent/15 text-accent',
                                                default => 'bg-info/15 text-info',
                                            };
                                        @endphp
                                        <span class="badge badge-sm border-0 {{ $movementBadge }}">
                                            {{ __('warehouse.movement_'.$movement->movement_type) }}
                                        </span>
                                    </td>
                                    <td class="text-right font-mono text-sm font-bold whitespace-nowrap {{ $movement->quantity_delta >= 0 ? 'text-ok' : 'text-err' }}">
                                        {{ $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }}
                                    </td>
                                    <td class="text-right font-mono text-sm">{{ $movement->stock_after }}</td>
                                    <td class="text-sm text-ink-soft">{{ $movement->actor?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-10 text-ink-soft">{{ __('warehouse.movements_empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    @if ($movements->hasPages())
                        <div class="px-5 py-4 border-t border-line/60">
                            {{ $movements->appends(['tab' => 'movements', 'movement_type' => $movementType])->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ================= YANGI MAHSULOT MODAL ================= --}}
    <dialog id="product-modal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box rounded-box border border-line bg-card max-w-2xl">
            <h3 class="font-display text-lg">{{ __('warehouse.new_product') }}</h3>

            <form method="POST" action="{{ route('warehouse.products.store') }}" id="product-form" class="mt-4 space-y-4"
                  data-ajax data-loader-text="...">
                @csrf

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="p-name" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_name') }}</label>
                        <input id="p-name" name="name" type="text" required maxlength="255"
                               class="input w-full bg-base-100" />
                    </div>
                    <div>
                        <label for="p-sku" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_sku') }}</label>
                        <input id="p-sku" name="sku" type="text" required maxlength="64"
                               class="input w-full bg-base-100 font-mono" />
                    </div>
                    <div>
                        <label for="p-category" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_category') }}</label>
                        <input id="p-category" name="category" type="text" maxlength="100"
                               class="input w-full bg-base-100" />
                    </div>
                    <div>
                        <label for="p-price" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_price') }}</label>
                        <input id="p-price" type="text" inputmode="numeric" autocomplete="off" required value="0"
                               data-price-input class="input w-full bg-base-100 font-mono" />
                        <input id="p-price-value" name="sale_price" type="hidden" value="0" />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0" />
                    <input type="checkbox" id="p-active" name="is_active" value="1" checked
                           class="checkbox checkbox-sm checkbox-accent" />
                    <label for="p-active" class="text-sm">{{ __('warehouse.product_active') }}</label>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-bold">{{ __('warehouse.variants_section') }}</h4>
                        <button type="button" class="btn btn-xs btn-outline btn-secondary"
                                onclick="addVariantRow()">+ {{ __('warehouse.variant_add_row') }}</button>
                    </div>
                    <p class="text-xs text-ink-soft mt-0.5">{{ __('warehouse.variant_required_hint') }}</p>
                    <div id="variant-rows" class="mt-3 space-y-2"></div>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost"
                            onclick="document.getElementById('product-modal').close()">{{ __('admin.cancel') }}</button>
                    <button type="submit" class="btn btn-accent">{{ __('warehouse.product_save') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    {{-- ================= MAHSULOTNI TAHRIRLASH MODAL ================= --}}
    <dialog id="edit-product-modal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box rounded-box border border-line bg-card max-w-2xl">
            <h3 class="font-display text-lg">{{ __('warehouse.edit_product_title') }}</h3>

            <form method="POST" id="edit-product-form" class="mt-4 space-y-4" data-ajax data-loader-text="...">
                @csrf

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="e-name" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_name') }}</label>
                        <input id="e-name" name="name" type="text" required maxlength="255"
                               class="input w-full bg-base-100" />
                    </div>
                    <div>
                        <label for="e-sku" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_sku') }}</label>
                        <input id="e-sku" name="sku" type="text" required maxlength="64"
                               class="input w-full bg-base-100 font-mono" />
                    </div>
                    <div>
                        <label for="e-category" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_category') }}</label>
                        <input id="e-category" name="category" type="text" maxlength="100"
                               class="input w-full bg-base-100" />
                    </div>
                    <div>
                        <label for="e-price" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_price') }}</label>
                        <input id="e-price" type="text" inputmode="numeric" autocomplete="off" required value="0"
                               data-price-input class="input w-full bg-base-100 font-mono" />
                        <input id="e-price-value" name="sale_price" type="hidden" value="0" />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0" />
                    <input type="checkbox" id="e-active" name="is_active" value="1"
                           class="checkbox checkbox-sm checkbox-accent" />
                    <label for="e-active" class="text-sm">{{ __('warehouse.product_active') }}</label>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-bold">{{ __('warehouse.variants_section') }}</h4>
                        <button type="button" class="btn btn-xs btn-outline btn-secondary"
                                onclick="addEditVariantRow()">+ {{ __('warehouse.variant_add_row') }}</button>
                    </div>
                    <p class="text-xs text-ink-soft mt-0.5">{{ __('warehouse.variant_required_hint') }}</p>
                    <div id="edit-variant-rows" class="mt-3 space-y-2"></div>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost"
                            onclick="document.getElementById('edit-product-modal').close()">{{ __('admin.cancel') }}</button>
                    <button type="submit" class="btn btn-accent">{{ __('warehouse.product_save') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    @push('scripts')
        <script>
            var SIZE_OPTIONS = @json($sizes);

            /* ===== Narxni formatlash (90 000 -> 90.000), bazaga esa 90000 saqlanadi ===== */
            function formatPrice(value) {
                var digits = String(value == null ? '' : value).replace(/\D+/g, '');

                if (digits === '') return '';

                digits = digits.replace(/^0+(?=\d)/, '');

                return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            function bindPriceInput(inputId, hiddenId) {
                var input = document.getElementById(inputId);
                var hidden = document.getElementById(hiddenId);

                if (!input || !hidden) return;

                var sync = function () {
                    var formatted = formatPrice(input.value);
                    input.value = formatted;
                    hidden.value = formatted.replace(/\D+/g, '');
                };

                input.addEventListener('input', sync);
                sync();
            }

            bindPriceInput('p-price', 'p-price-value');
            bindPriceInput('e-price', 'e-price-value');

            function switchWhTab(name) {
                document.querySelectorAll('[data-wh-tab]').forEach(function (el) {
                    el.classList.toggle('hidden', el.dataset.whTab !== name);
                });
                document.querySelectorAll('[data-wh-tab-btn]').forEach(function (btn) {
                    btn.classList.toggle('tab-active', btn.dataset.whTabBtn === name);
                });
            }

            /* ===== Mahsulot variant qatorlari ===== */
            var variantRowCount = 0;

            function addVariantRow() {
                var container = document.getElementById('variant-rows');
                var idx = variantRowCount++;

                var row = document.createElement('div');
                row.className = 'variant-row grid grid-cols-2 sm:grid-cols-5 gap-2 items-end rounded-field border border-line bg-base-100/60 p-2';

                var sizeWrap = document.createElement('div');
                var sizeLabel = document.createElement('label');
                sizeLabel.className = 'block text-xs font-semibold mb-1';
                sizeLabel.textContent = '{{ __('warehouse.variant_size') }}';
                var sizeSelect = document.createElement('select');
                sizeSelect.name = 'variants[' + idx + '][size]';
                sizeSelect.required = true;
                sizeSelect.className = 'select select-sm w-full bg-base-100';
                SIZE_OPTIONS.forEach(function (s) {
                    var opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s;
                    sizeSelect.appendChild(opt);
                });
                sizeWrap.appendChild(sizeLabel);
                sizeWrap.appendChild(sizeSelect);
                row.appendChild(sizeWrap);

                var colorWrap = document.createElement('div');
                var colorLabel = document.createElement('label');
                colorLabel.className = 'block text-xs font-semibold mb-1';
                colorLabel.textContent = '{{ __('warehouse.variant_color') }}';
                var colorInput = document.createElement('input');
                colorInput.name = 'variants[' + idx + '][color]';
                colorInput.type = 'text';
                colorInput.maxLength = 50;
                colorInput.placeholder = '—';
                colorInput.className = 'input input-sm w-full bg-base-100';
                colorWrap.appendChild(colorLabel);
                colorWrap.appendChild(colorInput);
                row.appendChild(colorWrap);

                var barcodeWrap = document.createElement('div');
                var barcodeLabel = document.createElement('label');
                barcodeLabel.className = 'block text-xs font-semibold mb-1';
                barcodeLabel.textContent = '{{ __('warehouse.variant_barcode') }}';
                var barcodeInput = document.createElement('input');
                barcodeInput.name = 'variants[' + idx + '][barcode]';
                barcodeInput.type = 'text';
                barcodeInput.maxLength = 64;
                barcodeInput.placeholder = '—';
                barcodeInput.className = 'input input-sm w-full bg-base-100 font-mono';
                barcodeWrap.appendChild(barcodeLabel);
                barcodeWrap.appendChild(barcodeInput);
                row.appendChild(barcodeWrap);

                var reorderWrap = document.createElement('div');
                var reorderLabel = document.createElement('label');
                reorderLabel.className = 'block text-xs font-semibold mb-1';
                reorderLabel.textContent = '{{ __('warehouse.variant_reorder') }}';
                var reorderInput = document.createElement('input');
                reorderInput.name = 'variants[' + idx + '][reorder_level]';
                reorderInput.type = 'number';
                reorderInput.min = 0;
                reorderInput.value = 0;
                reorderInput.className = 'input input-sm w-full bg-base-100';
                reorderWrap.appendChild(reorderLabel);
                reorderWrap.appendChild(reorderInput);
                row.appendChild(reorderWrap);

                // Variant qatorini olib tashlash (kamida bittasi qolishi kerak)
                var removeWrap = document.createElement('div');
                removeWrap.className = 'pb-1';
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-xs btn-ghost text-err';
                removeBtn.textContent = '✕';
                removeBtn.title = '{{ __('warehouse.variant_remove_row') }}';
                removeBtn.onclick = function () {
                    if (container.querySelectorAll('.variant-row').length <= 1) {
                        window.ustboshToast('{{ __('warehouse.variant_min_one') }}', 'error');
                        return;
                    }

                    row.remove();
                };
                removeWrap.appendChild(removeBtn);
                row.appendChild(removeWrap);

                container.appendChild(row);
            }

            /* Sahifa yuklanganda bitta variant qatori */
            addVariantRow();

            /* ===== Mahsulotni tahrirlash ===== */
            var editVariantCount = 0;
            var productUpdateTemplate = @json(route('warehouse.products.update', ['product' => '__ID__']));

            function addEditVariantRow(data) {
                data = data || {};

                var container = document.getElementById('edit-variant-rows');
                var idx = editVariantCount++;
                var isExisting = !!data.id;

                var row = document.createElement('div');
                row.className = 'grid grid-cols-2 sm:grid-cols-6 gap-2 items-end rounded-field border border-line bg-base-100/60 p-2';

                if (isExisting) {
                    var hiddenId = document.createElement('input');
                    hiddenId.type = 'hidden';
                    hiddenId.name = 'variants[' + idx + '][id]';
                    hiddenId.value = data.id;
                    row.appendChild(hiddenId);
                }

                var sizeWrap = document.createElement('div');
                var sizeLabel = document.createElement('label');
                sizeLabel.className = 'block text-xs font-semibold mb-1';
                sizeLabel.textContent = '{{ __('warehouse.variant_size') }}';
                var sizeSelect = document.createElement('select');
                sizeSelect.name = 'variants[' + idx + '][size]';
                sizeSelect.required = true;
                sizeSelect.className = 'select select-sm w-full bg-base-100';
                SIZE_OPTIONS.forEach(function (s) {
                    var opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s;
                    if (data.size === s) opt.selected = true;
                    sizeSelect.appendChild(opt);
                });
                sizeWrap.appendChild(sizeLabel);
                sizeWrap.appendChild(sizeSelect);
                row.appendChild(sizeWrap);

                var colorWrap = document.createElement('div');
                var colorLabel = document.createElement('label');
                colorLabel.className = 'block text-xs font-semibold mb-1';
                colorLabel.textContent = '{{ __('warehouse.variant_color') }}';
                var colorInput = document.createElement('input');
                colorInput.name = 'variants[' + idx + '][color]';
                colorInput.type = 'text';
                colorInput.maxLength = 50;
                colorInput.placeholder = '—';
                colorInput.value = data.color || '';
                colorInput.className = 'input input-sm w-full bg-base-100';
                colorWrap.appendChild(colorLabel);
                colorWrap.appendChild(colorInput);
                row.appendChild(colorWrap);

                var barcodeWrap = document.createElement('div');
                var barcodeLabel = document.createElement('label');
                barcodeLabel.className = 'block text-xs font-semibold mb-1';
                barcodeLabel.textContent = '{{ __('warehouse.variant_barcode') }}';
                var barcodeInput = document.createElement('input');
                barcodeInput.name = 'variants[' + idx + '][barcode]';
                barcodeInput.type = 'text';
                barcodeInput.maxLength = 64;
                barcodeInput.placeholder = '—';
                barcodeInput.value = data.barcode || '';
                barcodeInput.className = 'input input-sm w-full bg-base-100 font-mono';
                barcodeWrap.appendChild(barcodeLabel);
                barcodeWrap.appendChild(barcodeInput);
                row.appendChild(barcodeWrap);

                var reorderWrap = document.createElement('div');
                var reorderLabel = document.createElement('label');
                reorderLabel.className = 'block text-xs font-semibold mb-1';
                reorderLabel.textContent = '{{ __('warehouse.variant_reorder') }}';
                var reorderInput = document.createElement('input');
                reorderInput.name = 'variants[' + idx + '][reorder_level]';
                reorderInput.type = 'number';
                reorderInput.min = 0;
                reorderInput.value = data.reorder_level != null ? data.reorder_level : 0;
                reorderInput.className = 'input input-sm w-full bg-base-100';
                reorderWrap.appendChild(reorderLabel);
                reorderWrap.appendChild(reorderInput);
                row.appendChild(reorderWrap);

                var activeWrap = document.createElement('div');
                activeWrap.className = 'flex items-center gap-2 pb-2';
                var hiddenActive = document.createElement('input');
                hiddenActive.type = 'hidden';
                hiddenActive.name = 'variants[' + idx + '][is_active]';
                hiddenActive.value = '0';
                var activeCheck = document.createElement('input');
                activeCheck.type = 'checkbox';
                activeCheck.name = 'variants[' + idx + '][is_active]';
                activeCheck.value = '1';
                activeCheck.className = 'checkbox checkbox-sm checkbox-accent';
                activeCheck.checked = data.is_active !== false;
                var activeLabel = document.createElement('span');
                activeLabel.className = 'text-xs font-semibold';
                activeLabel.textContent = '{{ __('warehouse.variant_active') }}';
                activeWrap.appendChild(hiddenActive);
                activeWrap.appendChild(activeCheck);
                activeWrap.appendChild(activeLabel);
                if (isExisting) {
                    var stockInfo = document.createElement('span');
                    stockInfo.className = 'text-xs text-ink-soft font-mono';
                    stockInfo.textContent = '· ' + (data.stock_on_hand || 0);
                    activeWrap.appendChild(stockInfo);
                }
                row.appendChild(activeWrap);

                if (!isExisting) {
                    var removeWrap = document.createElement('div');
                    removeWrap.className = 'pb-1';
                    var removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-xs btn-ghost text-err';
                    removeBtn.textContent = '✕';
                    removeBtn.title = '{{ __('warehouse.variant_remove_row') }}';
                    removeBtn.onclick = function () { row.remove(); };
                    removeWrap.appendChild(removeBtn);
                    row.appendChild(removeWrap);
                }

                container.appendChild(row);
            }

            function openEditProduct(url) {
                fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('bad response');
                        return response.json();
                    })
                    .then(function (data) {
                        var product = data.product || {};

                        document.getElementById('edit-product-form').action = productUpdateTemplate.replace('__ID__', product.id);
                        document.getElementById('e-name').value = product.name || '';
                        document.getElementById('e-sku').value = product.sku || '';
                        document.getElementById('e-category').value = product.category || '';
                        var priceValue = product.sale_price != null ? product.sale_price : 0;
                        var formattedPrice = formatPrice(priceValue);
                        document.getElementById('e-price').value = formattedPrice;
                        document.getElementById('e-price-value').value = formattedPrice.replace(/\D+/g, '');
                        document.getElementById('e-active').checked = product.is_active !== false;

                        var container = document.getElementById('edit-variant-rows');
                        container.innerHTML = '';
                        editVariantCount = 0;
                        (product.variants || []).forEach(addEditVariantRow);

                        document.getElementById('edit-product-modal').showModal();
                    })
                    .catch(function () {
                        window.ustboshToast('{{ __('warehouse.network_error') }}', 'error');
                    });
            }

            document.addEventListener('click', function (event) {
                var btn = event.target.closest('[data-edit-product]');
                if (!btn) return;
                openEditProduct(btn.dataset.editUrl);
            });

            /* Saqlangach modal yopiladi va sahifa yangilanadi */
            document.addEventListener('ustbosh:ajax:success', function (event) {
                var form = event.detail.form;
                if (form && (form.id === 'product-form' || form.id === 'edit-product-form')) {
                    setTimeout(function () {
                        document.getElementById('product-modal').close();
                        document.getElementById('edit-product-modal').close();
                        location.reload();
                    }, 600);
                }
            });
        </script>
    @endpush
@endsection