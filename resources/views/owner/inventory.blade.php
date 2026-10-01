@extends('layouts.app')

@section('title', __('owner.inventory_title'))

@section('content')
    <section class="py-10">
        <div class="mx-auto max-w-6xl px-4">
            @include('owner.partials.header', [
                'title' => __('owner.inventory_title'),
                'subtitle' => __('owner.inventory_subtitle'),
                'shop' => $shop,
            ])

            <div class="mt-6 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] items-start">
                @include('owner.partials.nav')

                <div class="min-w-0 space-y-6">

                    {{-- Faqat ko'rish uchun ekanligi haqida eslatma --}}
                    <div class="rounded-field border border-line bg-card px-4 py-3 text-xs text-ink-soft shadow-elev">
                        {{ __('owner.inventory_readonly_hint') }}
                    </div>

                    {{-- Statistika --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.inventory_stat_products') }}</span>
                            <div class="font-mono text-3xl font-bold mt-2">{{ $stats['products'] }}</div>
                        </div>
                        <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.inventory_stat_total_stock') }}</span>
                            <div class="font-mono text-3xl font-bold mt-2 text-ok">{{ $stats['total_stock'] }}</div>
                        </div>
                        <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.inventory_stat_low_stock') }}</span>
                            <div class="font-mono text-3xl font-bold mt-2 text-accent">{{ $stats['low_stock'] }}</div>
                        </div>
                        <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.inventory_stat_out_of_stock') }}</span>
                            <div class="font-mono text-3xl font-bold mt-2 text-err">{{ $stats['out_of_stock'] }}</div>
                        </div>
                    </div>

                    {{-- Mahsulotlar ro'yxati --}}
                    <div class="rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                        <div class="px-5 py-4 border-b border-line/60 flex flex-wrap items-center justify-between gap-3">
                            <h2 class="font-display text-lg">{{ __('owner.inventory_products_title') }}</h2>
                            <form method="GET" action="{{ route('owner.inventory.index') }}" class="flex gap-2 items-center">
                                <input type="search" name="q" value="{{ $search }}"
                                       placeholder="{{ __('owner.inventory_search_placeholder') }}"
                                       class="input input-sm w-full sm:w-64 bg-base-100" />
                                <button type="submit" class="btn btn-sm btn-accent">{{ __('admin.search_button') }}</button>
                            </form>
                        </div>

                        <table class="table">
                            <thead>
                                <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                    <th>{{ __('warehouse.table_product') }}</th>
                                    <th>{{ __('warehouse.table_sku') }}</th>
                                    <th class="text-center">{{ __('warehouse.table_variants') }}</th>
                                    <th class="text-right">{{ __('warehouse.table_stock') }}</th>
                                    <th>{{ __('warehouse.table_status') }}</th>
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
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-10 text-ink-soft">{{ __('warehouse.inventory_empty') }}</td>
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

                    {{-- Kam qolgan variantlar --}}
                    <div class="rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                        <div class="px-5 py-4 border-b border-line/60 flex items-center justify-between gap-2">
                            <h2 class="font-display text-lg">{{ __('owner.report_low_stock_title') }}</h2>
                            <span class="badge badge-sm border-0 bg-accent/15 text-accent">{{ $stats['low_stock'] }}</span>
                        </div>

                        <table class="table">
                            <thead>
                                <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                    <th>{{ __('warehouse.table_product') }}</th>
                                    <th class="text-right">{{ __('warehouse.table_stock') }}</th>
                                    <th class="text-right">{{ __('warehouse.variant_reorder') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lowStockVariants as $variant)
                                    <tr class="border-line/60">
                                        <td>
                                            <div class="font-semibold text-sm">{{ $variant->product?->name ?? '—' }}</div>
                                            <div class="font-mono text-xs text-ink-soft">{{ $variant->displayLabel() }}</div>
                                        </td>
                                        <td class="text-right font-mono text-sm font-bold {{ $variant->stock_on_hand <= 0 ? 'text-err' : 'text-accent' }}">
                                            {{ $variant->stock_on_hand }}
                                        </td>
                                        <td class="text-right font-mono text-sm text-ink-soft">{{ $variant->reorder_level }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-10 text-ink-soft">{{ __('owner.low_stock_empty') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Tugagan variantlar --}}
                    <div class="rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                        <div class="px-5 py-4 border-b border-line/60 flex items-center justify-between gap-2">
                            <h2 class="font-display text-lg">{{ __('owner.report_out_of_stock_title') }}</h2>
                            <span class="badge badge-sm border-0 bg-err/15 text-err">{{ $stats['out_of_stock'] }}</span>
                        </div>

                        <table class="table">
                            <thead>
                                <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                    <th>{{ __('warehouse.table_product') }}</th>
                                    <th class="text-right">{{ __('warehouse.table_stock') }}</th>
                                    <th class="text-right">{{ __('warehouse.variant_reorder') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($outOfStockVariants as $variant)
                                    <tr class="border-line/60">
                                        <td>
                                            <div class="font-semibold text-sm">{{ $variant->product?->name ?? '—' }}</div>
                                            <div class="font-mono text-xs text-ink-soft">{{ $variant->displayLabel() }}</div>
                                        </td>
                                        <td class="text-right font-mono text-sm font-bold text-err">
                                            {{ $variant->stock_on_hand }}
                                        </td>
                                        <td class="text-right font-mono text-sm text-ink-soft">{{ $variant->reorder_level }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-10 text-ink-soft">{{ __('owner.out_of_stock_empty') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </section>
@endsection
