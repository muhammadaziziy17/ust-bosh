{{-- Kam qolgan tovarlar (kutilgan: $stats, $lowStockVariants) --}}
<div class="rounded-box border border-line bg-card shadow-elev">
    <div class="px-5 py-4 border-b border-line/60 flex items-center justify-between gap-2">
        <h2 class="font-display text-lg">{{ __('owner.report_low_stock_title') }}</h2>
        <span class="badge badge-sm border-0 bg-accent/15 text-accent">{{ $stats['low_stock'] }}</span>
    </div>

    <ul class="divide-y divide-line/60">
        @forelse ($lowStockVariants as $variant)
            <li class="px-5 py-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-semibold text-sm truncate">{{ $variant->product?->name ?? '—' }}</div>
                    <div class="font-mono text-xs text-ink-soft">{{ $variant->displayLabel() }}</div>
                </div>
                <span class="badge border-0 shrink-0 {{ $variant->stock_on_hand <= 0 ? 'bg-err/15 text-err' : 'bg-accent/15 text-accent' }}">
                    {{ __('owner.stock_left', ['count' => $variant->stock_on_hand]) }}
                </span>
            </li>
        @empty
            <li class="px-5 py-10 text-center text-ink-soft text-sm">{{ __('owner.low_stock_empty') }}</li>
        @endforelse
    </ul>
</div>
