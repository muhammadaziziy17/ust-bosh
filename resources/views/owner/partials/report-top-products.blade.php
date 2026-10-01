{{-- Eng ko'p sotilgan mahsulotlar (kutilgan: $topProducts) --}}
<div class="rounded-box border border-line bg-card shadow-elev overflow-x-auto">
    <div class="px-5 py-4 border-b border-line/60">
        <h2 class="font-display text-lg">{{ __('owner.report_top_products_title') }}</h2>
    </div>

    <table class="table">
        <thead>
            <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                <th>{{ __('owner.table_product') }}</th>
                <th class="text-right">{{ __('owner.table_qty') }}</th>
                <th class="text-right">{{ __('owner.table_revenue') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($topProducts as $product)
                <tr class="border-line/60">
                    <td class="font-semibold">{{ $product->product_name_snapshot }}</td>
                    <td class="text-right font-mono text-sm whitespace-nowrap">{{ (int) $product->quantity_sum }}</td>
                    <td class="text-right font-mono text-sm font-bold whitespace-nowrap">{{ format_som($product->revenue_sum) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center py-10 text-ink-soft">{{ __('owner.report_top_products_empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
