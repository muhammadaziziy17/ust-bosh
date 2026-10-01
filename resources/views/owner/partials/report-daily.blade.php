{{-- Kunlar kesimida savdo (kutilgan: $dailySales) --}}
<div class="rounded-box border border-line bg-card shadow-elev overflow-x-auto">
    <div class="px-5 py-4 border-b border-line/60">
        <h2 class="font-display text-lg">{{ __('owner.report_daily_title') }}</h2>
    </div>

    <table class="table">
        <thead>
            <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                <th>{{ __('owner.table_day') }}</th>
                <th class="text-right">{{ __('owner.report_sales_count') }}</th>
                <th class="text-right">{{ __('owner.table_revenue') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($dailySales as $day)
                <tr class="border-line/60">
                    <td class="font-mono text-sm whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($day->day)->format('d.m.Y') }}</td>
                    <td class="text-right font-mono text-sm">{{ (int) $day->sales_count }}</td>
                    <td class="text-right font-mono text-sm font-bold whitespace-nowrap">{{ format_som($day->revenue_sum) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center py-10 text-ink-soft">{{ __('owner.report_daily_empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
