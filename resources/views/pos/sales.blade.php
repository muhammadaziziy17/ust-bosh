@extends('layouts.app')

@section('title', __('pos.sales_history'))

@section('content')
    <section class="py-10">
        <div class="mx-auto max-w-6xl px-4">

            {{-- Sarlavha --}}
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-2xl md:text-3xl">{{ __('pos.sales_history') }}</h1>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('pos.sales_history_subtitle') }}</p>
                </div>
                <a href="{{ route('pos.index') }}" class="btn btn-ghost btn-sm">
                    ← {{ __('pos.back_to_pos') }}
                </a>
            </div>

            @if (session('success'))
                <div class="sm-alert mt-6 rounded-field border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 rounded-box border border-line bg-card shadow-elev overflow-hidden">
                {{-- Filtr --}}
                <div class="px-4 sm:px-5 py-4 border-b border-line/60">
                    <form method="GET" action="{{ route('pos.sales.index') }}" class="flex flex-wrap items-end gap-3">
                        <div class="min-w-48 flex-1">
                            <label for="sales-q" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('pos.table_sale_no') }}</label>
                            <input id="sales-q" type="search" name="q" value="{{ $search }}"
                                   placeholder="{{ __('pos.search_sale_placeholder') }}"
                                   class="input input-sm w-full bg-base-100" />
                        </div>
                        <div>
                            <label for="sales-date" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('pos.filter_date') }}</label>
                            <input id="sales-date" type="date" name="date" value="{{ $date }}"
                                   class="input input-sm bg-base-100" />
                        </div>
                        <button type="submit" class="btn btn-sm btn-accent">{{ __('pos.filter_button') }}</button>
                        @if ($search !== '' || $date)
                            <a href="{{ route('pos.sales.index') }}" class="btn btn-sm btn-ghost">{{ __('pos.filter_reset') }}</a>
                        @endif
                    </form>
                </div>

                {{-- Katta ekran: jadval --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                <th>{{ __('pos.table_sale_no') }}</th>
                                <th>{{ __('pos.table_date') }}</th>
                                <th>{{ __('pos.table_cashier') }}</th>
                                <th class="text-center">{{ __('pos.table_items_count') }}</th>
                                <th>{{ __('pos.table_payment') }}</th>
                                <th class="text-right">{{ __('pos.table_total') }}</th>
                                <th>{{ __('pos.table_status') }}</th>
                                <th class="text-right">{{ __('pos.table_actions') }}</th>
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
                                            <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('pos.payment_cash') }}</span>
                                        @else
                                            <span class="badge badge-sm border-0 bg-info/15 text-info">{{ __('pos.payment_card') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right font-mono text-sm font-bold whitespace-nowrap">{{ format_som($sale->total) }}</td>
                                    <td>
                                        @if ($sale->status === \App\Models\Sale::STATUS_COMPLETED)
                                            <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('pos.status_completed') }}</span>
                                        @else
                                            <span class="badge badge-sm border-0 bg-err/15 text-err">{{ __('pos.status_voided') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a href="{{ route('pos.sales.show', $sale) }}" class="btn btn-xs btn-outline btn-secondary gap-1">
                                            🧾 {{ __('pos.view_receipt') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-10 text-ink-soft">{{ __('pos.sales_empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobil: ixcham kartochkalar --}}
                <div class="md:hidden divide-y divide-line/60">
                    @forelse ($sales as $sale)
                        <a href="{{ route('pos.sales.show', $sale) }}" class="block px-4 py-3 active:bg-base-200/60">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-sm font-bold">{{ $sale->sale_no }}</span>
                                @if ($sale->status === \App\Models\Sale::STATUS_COMPLETED)
                                    <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('pos.status_completed') }}</span>
                                @else
                                    <span class="badge badge-sm border-0 bg-err/15 text-err">{{ __('pos.status_voided') }}</span>
                                @endif
                            </div>
                            <div class="mt-1 flex items-center justify-between gap-2">
                                <span class="text-xs text-ink-soft">{{ $sale->sold_at->format('d.m.Y H:i') }} · {{ $sale->cashier?->name ?? '—' }}</span>
                                <span class="font-mono text-sm font-bold">{{ format_som($sale->total) }}</span>
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-xs text-ink-soft">
                                @if ($sale->payment_method === \App\Models\Sale::PAYMENT_CASH)
                                    <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('pos.payment_cash') }}</span>
                                @else
                                    <span class="badge badge-sm border-0 bg-info/15 text-info">{{ __('pos.payment_card') }}</span>
                                @endif
                                <span>{{ $sale->items_count }} {{ __('pos.table_items_count') }}</span>
                                <span class="ml-auto text-secondary">🧾 {{ __('pos.view_receipt') }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="px-4 py-10 text-center text-ink-soft">{{ __('pos.sales_empty') }}</div>
                    @endforelse
                </div>

                @if ($sales->hasPages())
                    <div class="px-5 py-4 border-t border-line/60">
                        {{ $sales->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
