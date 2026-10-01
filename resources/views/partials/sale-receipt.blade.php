{{--
    Sotuv cheki (80 mm termal).
    Kutilgan o'zgaruvchilar: $sale, $shop, $autoprint.
--}}
<div class="sm-print-area mt-4 rounded-box border border-line bg-card shadow-elev overflow-hidden"
     data-autoprint="{{ $autoprint ? '1' : '0' }}">

    <div class="sm-receipt-head border-b border-dashed border-line px-4 py-4 text-center">
        <div class="font-display text-lg leading-tight">{{ $shop?->name ?? __('landing.brand') }}</div>
        <div class="mt-1 font-mono text-[10px] uppercase tracking-[0.3em] text-ink-soft">{{ __('pos.receipt_title') }}</div>
    </div>

    <div class="sm-receipt-body px-4 py-4 space-y-3 text-[13px]">
        <div class="space-y-1 font-mono text-xs">
            <div class="flex justify-between gap-3">
                <span class="text-ink-soft">{{ __('pos.table_sale_no') }}</span>
                <span class="font-bold">{{ $sale->sale_no }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-ink-soft">{{ __('pos.receipt_sold_at') }}</span>
                <span>{{ $sale->sold_at->format('d.m.Y H:i') }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-ink-soft">{{ __('pos.receipt_cashier') }}</span>
                <span>{{ $sale->cashier?->name ?? '—' }}</span>
            </div>
        </div>

        @if ($sale->status === \App\Models\Sale::STATUS_VOIDED)
            <div class="rounded-field border border-err/40 px-3 py-2 text-xs text-err">
                <div class="font-bold">{{ __('pos.status_voided') }}</div>
                @if ($sale->void_reason)
                    <div class="mt-0.5">{{ __('pos.void_reason_done') }}: {{ $sale->void_reason }}</div>
                @endif
            </div>
        @endif

        <div class="border-t border-dashed border-line"></div>

        <div class="space-y-2">
            @foreach ($sale->items as $item)
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold leading-snug">{{ $item->product_name_snapshot }}</div>
                        <div class="font-mono text-xs text-ink-soft">
                            {{ $item->size_snapshot }}@if ($item->color_snapshot) · {{ $item->color_snapshot }}@endif
                        </div>
                        <div class="font-mono text-xs text-ink-soft">
                            {{ $item->quantity }} × {{ format_som($item->unit_price) }}
                        </div>
                    </div>
                    <div class="font-mono text-sm font-bold whitespace-nowrap">{{ format_som($item->line_total) }}</div>
                </div>
            @endforeach
        </div>

        <div class="border-t border-dashed border-line"></div>

        <div class="space-y-1 font-mono text-xs">
            <div class="flex justify-between gap-3">
                <span class="text-ink-soft">{{ __('pos.cart_subtotal') }}</span>
                <span>{{ format_som($sale->subtotal) }}</span>
            </div>
            <div class="flex justify-between gap-3 text-sm font-bold">
                <span>{{ __('pos.cart_total') }}</span>
                <span class="text-accent">{{ format_som($sale->total) }}</span>
            </div>
            <div class="flex justify-between gap-3">
                <span class="text-ink-soft">{{ __('pos.table_payment') }}</span>
                <span>{{ $sale->payment_method === \App\Models\Sale::PAYMENT_CASH ? __('pos.payment_cash') : __('pos.payment_card') }}</span>
            </div>
            @if ($sale->amount_received !== null)
                <div class="flex justify-between gap-3">
                    <span class="text-ink-soft">{{ __('pos.amount_received_label') }}</span>
                    <span>{{ format_som($sale->amount_received) }}</span>
                </div>
            @endif
            @if ($sale->change_amount !== null)
                <div class="flex justify-between gap-3">
                    <span class="text-ink-soft">{{ __('pos.change_label') }}</span>
                    <span class="font-bold">{{ format_som($sale->change_amount) }}</span>
                </div>
            @endif
        </div>

        <div class="border-t border-dashed border-line"></div>

        <p class="text-center text-[11px] text-ink-soft">{{ __('pos.receipt_thanks') }}</p>
    </div>
</div>

@if ($autoprint)
    @push('scripts')
        <script>
            // "Chekni chop etish" bosilganda print oynasi avtomatik chiqadi
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endpush
@endif