@extends('layouts.app')

@section('title', __('owner.dashboard_title'))

@section('content')
    <section class="py-10">
        <div class="mx-auto max-w-6xl px-4">
            @include('owner.partials.header', [
                'title' => __('owner.dashboard_title'),
                'subtitle' => __('owner.overview_subtitle'),
                'shop' => $shop,
            ])

            <div class="mt-6 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] items-start">
                @include('owner.partials.nav')

                <div class="min-w-0 space-y-6">

                    {{-- Muhim ko'rsatkichlar --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.stat_today_revenue') }}</span>
                            <div class="font-mono text-xl sm:text-2xl font-bold mt-2 text-accent break-words">{{ format_som($stats['today_revenue']) }}</div>
                        </div>
                        <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.stat_today_sales') }}</span>
                            <div class="font-mono text-3xl font-bold mt-2 text-ok">{{ $stats['today_sales'] }}</div>
                        </div>
                        <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.stat_employees') }}</span>
                            <div class="font-mono text-3xl font-bold mt-2">{{ $stats['employees'] }}</div>
                        </div>
                        <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.stat_low_stock') }}</span>
                            <div class="font-mono text-3xl font-bold mt-2 {{ $stats['low_stock'] > 0 ? 'text-err' : 'text-ok' }}">{{ $stats['low_stock'] }}</div>
                        </div>
                    </div>

                    <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] items-start">

                        {{-- Oxirgi sotuvlar --}}
                        <div class="rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                            <div class="px-5 py-4 border-b border-line/60 flex flex-wrap items-center justify-between gap-2">
                                <h2 class="font-display text-lg">{{ __('owner.recent_sales_title') }}</h2>
                                <a href="{{ route('owner.reports.index') }}" class="btn btn-ghost btn-xs">
                                    {{ __('owner.view_reports') }} →
                                </a>
                            </div>

                            <table class="table">
                                <thead>
                                    <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                        <th>{{ __('owner.table_sale') }}</th>
                                        <th>{{ __('owner.table_cashier') }}</th>
                                        <th>{{ __('owner.table_payment') }}</th>
                                        <th>{{ __('owner.table_time') }}</th>
                                        <th class="text-right">{{ __('owner.table_total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentSales as $sale)
                                        <tr class="border-line/60">
                                            <td class="font-mono text-sm">{{ $sale->sale_no }}</td>
                                            <td class="text-sm">{{ $sale->cashier?->name ?? '—' }}</td>
                                            <td>
                                                @if ($sale->payment_method === \App\Models\Sale::PAYMENT_CASH)
                                                    <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('owner.payment_cash') }}</span>
                                                @else
                                                    <span class="badge badge-sm border-0 bg-info/15 text-info">{{ __('owner.payment_card') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-sm text-ink-soft whitespace-nowrap">{{ $sale->sold_at->format('d.m.Y H:i') }}</td>
                                            <td class="text-right font-mono text-sm font-bold whitespace-nowrap">{{ format_som($sale->total) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-10 text-ink-soft">{{ __('owner.recent_sales_empty') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Kam qolgan tovarlar --}}
                        <div class="rounded-box border border-line bg-card shadow-elev">
                            <div class="px-5 py-4 border-b border-line/60">
                                <h2 class="font-display text-lg">{{ __('owner.low_stock_title') }}</h2>
                                <p class="text-xs text-ink-soft mt-0.5">{{ __('owner.low_stock_hint') }}</p>
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
                    </div>

                </div>
            </div>
        </div>
    </section>
@endsection
