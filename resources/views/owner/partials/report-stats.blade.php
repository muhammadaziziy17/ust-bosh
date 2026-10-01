{{-- Davr ko'rsatkichlari (kutilgan: $stats) --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="rounded-box border border-line bg-card p-5 shadow-elev">
        <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.report_revenue') }}</span>
        <div class="font-mono text-xl sm:text-2xl font-bold mt-2 text-accent break-words">{{ format_som($stats['revenue']) }}</div>
    </div>
    <div class="rounded-box border border-line bg-card p-5 shadow-elev">
        <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.report_sales_count') }}</span>
        <div class="font-mono text-3xl font-bold mt-2 text-ok">{{ $stats['sales'] }}</div>
    </div>
    <div class="rounded-box border border-line bg-card p-5 shadow-elev">
        <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.report_avg_check') }}</span>
        <div class="font-mono text-xl sm:text-2xl font-bold mt-2 break-words">{{ format_som($stats['average_check']) }}</div>
    </div>
    <div class="rounded-box border border-line bg-card p-5 shadow-elev">
        <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.report_items_sold') }}</span>
        <div class="font-mono text-3xl font-bold mt-2">{{ $stats['items'] }}</div>
    </div>
</div>
