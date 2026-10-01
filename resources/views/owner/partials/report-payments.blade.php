{{-- To'lov turlari (kutilgan: $paymentSplit) --}}
<div class="rounded-box border border-line bg-card shadow-elev">
    <div class="px-5 py-4 border-b border-line/60">
        <h2 class="font-display text-lg">{{ __('owner.report_payments_title') }}</h2>
    </div>

    <div class="p-5 space-y-4">
        <div class="flex items-center justify-between gap-3">
            <span class="badge border-0 bg-ok/15 text-ok">{{ __('owner.payment_cash') }}</span>
            <span class="font-mono font-bold">{{ format_som($paymentSplit['cash']) }}</span>
        </div>
        <div class="h-2 rounded-full bg-base-200 overflow-hidden">
            <div class="h-full bg-ok" style="width: {{ $paymentSplit['cash_percent'] }}%"></div>
        </div>

        <div class="flex items-center justify-between gap-3 pt-2">
            <span class="badge border-0 bg-info/15 text-info">{{ __('owner.payment_card') }}</span>
            <span class="font-mono font-bold">{{ format_som($paymentSplit['card']) }}</span>
        </div>
        <div class="h-2 rounded-full bg-base-200 overflow-hidden">
            <div class="h-full bg-info" style="width: {{ $paymentSplit['card_percent'] }}%"></div>
        </div>
    </div>
</div>
