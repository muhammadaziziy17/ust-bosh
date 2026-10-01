@extends('layouts.app')

@section('title', __('owner.sales_title'))

@section('content')
    <section class="py-10">
        <div class="mx-auto max-w-6xl px-4">
            @include('owner.partials.header', [
                'title' => __('owner.sales_title'),
                'subtitle' => __('owner.sales_subtitle'),
                'shop' => $shop,
            ])

            <div class="mt-6 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] items-start">
                @include('owner.partials.nav')

                <div class="min-w-0 space-y-6">

                    {{-- Bekor qilish huquqi sozlamasi (default: o'chiq) --}}
                    <form method="POST" action="{{ route('owner.sales.settings') }}"
                          class="rounded-box border border-line bg-card px-5 py-4 shadow-elev">
                        @csrf
                        <label class="flex cursor-pointer items-center justify-between gap-4">
                            <span>
                                <span class="text-sm font-semibold">{{ __('owner.void_setting_title') }}</span>
                                <span class="mt-0.5 block text-xs text-ink-soft">{{ __('owner.void_setting_hint') }}</span>
                            </span>
                            <span class="shrink-0">
                                <input type="hidden" name="owner_can_void_sales" value="0">
                                <input type="checkbox" name="owner_can_void_sales" value="1"
                                       class="toggle toggle-accent" onchange="this.form.submit()"
                                       @checked($canVoidSales) />
                            </span>
                        </label>
                    </form>

                    {{-- Filtr --}}
                    <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                        <form method="GET" action="{{ route('owner.sales.index') }}" class="flex flex-wrap items-end gap-3">
                            <div>
                                <label for="sale-range" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.range_label') }}</label>
                                <select id="sale-range" name="range" class="select select-sm bg-base-100">
                                    @foreach (['today', '7d', '30d', 'month', 'custom'] as $preset)
                                        <option value="{{ $preset }}" @selected($range === $preset)>{{ __('owner.range_'.$preset) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="sale-from" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.range_from') }}</label>
                                <input id="sale-from" name="from" type="date" value="{{ $from->format('Y-m-d') }}"
                                       class="input input-sm bg-base-100" />
                            </div>

                            <div>
                                <label for="sale-to" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.range_to') }}</label>
                                <input id="sale-to" name="to" type="date" value="{{ $to->format('Y-m-d') }}"
                                       class="input input-sm bg-base-100" />
                            </div>

                            <div>
                                <label for="sale-cashier" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.sales_filter_cashier') }}</label>
                                <select id="sale-cashier" name="cashier" class="select select-sm bg-base-100">
                                    <option value="0">{{ __('owner.sales_filter_all') }}</option>
                                    @foreach ($cashiers as $cashier)
                                        <option value="{{ $cashier->id }}" @selected($cashierId === $cashier->id)>{{ $cashier->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="sale-payment" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.table_payment') }}</label>
                                <select id="sale-payment" name="payment" class="select select-sm bg-base-100">
                                    <option value="all" @selected($payment === 'all')>{{ __('owner.sales_filter_all') }}</option>
                                    <option value="cash" @selected($payment === 'cash')>{{ __('owner.payment_cash') }}</option>
                                    <option value="card" @selected($payment === 'card')>{{ __('owner.payment_card') }}</option>
                                </select>
                            </div>

                            <div>
                                <label for="sale-status" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.table_status') }}</label>
                                <select id="sale-status" name="status" class="select select-sm bg-base-100">
                                    <option value="all" @selected($status === 'all')>{{ __('owner.sales_filter_all') }}</option>
                                    <option value="completed" @selected($status === 'completed')>{{ __('owner.sale_status_completed') }}</option>
                                    <option value="voided" @selected($status === 'voided')>{{ __('owner.sale_status_voided') }}</option>
                                </select>
                            </div>

                            <div class="min-w-40 flex-1">
                                <label for="sale-q" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.sales_search') }}</label>
                                <input id="sale-q" type="search" name="q" value="{{ $search }}"
                                       placeholder="{{ __('owner.sales_search_placeholder') }}"
                                       class="input input-sm w-full bg-base-100" />
                            </div>

                            <button type="submit" class="btn btn-sm btn-accent">{{ __('owner.range_apply') }}</button>
                            <a href="{{ route('owner.sales.index') }}" class="btn btn-sm btn-ghost">{{ __('owner.sales_reset') }}</a>
                        </form>
                    </div>

                    {{-- Sotuvlar --}}
                    <div class="rounded-box border border-line bg-card shadow-elev overflow-hidden">
                        {{-- Katta ekran: jadval --}}
                        <div class="hidden md:block overflow-x-auto">
                            <table class="table">
                                <thead>
                                    <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                        <th>{{ __('owner.table_sale') }}</th>
                                        <th>{{ __('owner.table_time') }}</th>
                                        <th>{{ __('owner.table_cashier') }}</th>
                                        <th class="text-center">{{ __('owner.table_items') }}</th>
                                        <th>{{ __('owner.table_payment') }}</th>
                                        <th class="text-right">{{ __('owner.table_total') }}</th>
                                        <th>{{ __('owner.table_status') }}</th>
                                        <th class="text-right">{{ __('owner.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($sales as $sale)
                                        <tr class="border-line/60">
                                            <td class="font-mono text-sm font-bold whitespace-nowrap">{{ $sale->sale_no }}</td>
                                            <td class="text-sm text-ink-soft whitespace-nowrap">{{ $sale->sold_at->format('d.m.Y H:i') }}</td>
                                            <td class="text-sm">{{ $sale->cashier?->name ?? '—' }}</td>
                                            <td class="text-center font-mono text-sm">{{ $sale->items_count }}</td>
                                            <td>
                                                @if ($sale->payment_method === \App\Models\Sale::PAYMENT_CASH)
                                                    <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('owner.payment_cash') }}</span>
                                                @else
                                                    <span class="badge badge-sm border-0 bg-info/15 text-info">{{ __('owner.payment_card') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right font-mono text-sm font-bold whitespace-nowrap">{{ format_som($sale->total) }}</td>
                                            <td>
                                                @if ($sale->status === \App\Models\Sale::STATUS_COMPLETED)
                                                    <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('owner.sale_status_completed') }}</span>
                                                @else
                                                    <span class="badge badge-sm border-0 bg-err/15 text-err">{{ __('owner.sale_status_voided') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right">
                                                <a href="{{ route('owner.sales.show', $sale) }}" class="btn btn-xs btn-outline btn-secondary gap-1">
                                                    🧾 {{ __('owner.sale_view') }}
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-10 text-ink-soft">{{ __('owner.sales_empty') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Mobil: ixcham kartochkalar --}}
                        <div class="md:hidden divide-y divide-line/60">
                            @forelse ($sales as $sale)
                                <a href="{{ route('owner.sales.show', $sale) }}" class="block px-4 py-3 active:bg-base-200/60">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-mono text-sm font-bold">{{ $sale->sale_no }}</span>
                                        @if ($sale->status === \App\Models\Sale::STATUS_COMPLETED)
                                            <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('owner.sale_status_completed') }}</span>
                                        @else
                                            <span class="badge badge-sm border-0 bg-err/15 text-err">{{ __('owner.sale_status_voided') }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex items-center justify-between gap-2">
                                        <span class="text-xs text-ink-soft">{{ $sale->sold_at->format('d.m.Y H:i') }} · {{ $sale->cashier?->name ?? '—' }}</span>
                                        <span class="font-mono text-sm font-bold">{{ format_som($sale->total) }}</span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-2 text-xs text-ink-soft">
                                        @if ($sale->payment_method === \App\Models\Sale::PAYMENT_CASH)
                                            <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('owner.payment_cash') }}</span>
                                        @else
                                            <span class="badge badge-sm border-0 bg-info/15 text-info">{{ __('owner.payment_card') }}</span>
                                        @endif
                                        <span>{{ $sale->items_count }} {{ __('owner.table_items') }}</span>
                                        <span class="ml-auto text-secondary">🧾 {{ __('owner.sale_view') }}</span>
                                    </div>
                                </a>
                            @empty
                                <div class="px-4 py-10 text-center text-ink-soft">{{ __('owner.sales_empty') }}</div>
                            @endforelse
                        </div>

                        @if ($sales->hasPages())
                            <div class="px-5 py-4 border-t border-line/60">
                                {{ $sales->links() }}
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </section>
@endsection
