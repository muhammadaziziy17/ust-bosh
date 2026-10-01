@extends('layouts.app')

@section('title', __('owner.reports_title'))

@section('content')
    <section class="py-10">
        <div class="mx-auto max-w-6xl px-4">
            @include('owner.partials.header', [
                'title' => __('owner.reports_title'),
                'subtitle' => __('owner.reports_subtitle'),
                'shop' => $shop,
            ])

            <div class="mt-6 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] items-start">
                @include('owner.partials.nav')

                <div class="min-w-0 space-y-6">

                    {{-- Davr filtri va eksport --}}
                    <div class="rounded-box border border-line bg-card p-5 shadow-elev space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm text-ink-soft">{{ __('owner.range_label') }}:</span>
                            @foreach (['today', '7d', '30d', 'month'] as $preset)
                                <a href="{{ route('owner.reports.index', ['range' => $preset]) }}"
                                   class="btn btn-sm {{ $range === $preset ? 'btn-accent' : 'btn-ghost' }}">
                                    {{ __('owner.range_'.$preset) }}
                                </a>
                            @endforeach

                            <div class="ml-auto flex items-center gap-2">
                                <a href="{{ route('owner.reports.export', request()->query()) }}"
                                   class="btn btn-sm btn-outline btn-secondary">
                                    📊 {{ __('owner.export_excel') }}
                                </a>
                                <a href="{{ route('owner.reports.print', array_merge(request()->query(), ['print' => 1])) }}"
                                   target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline btn-secondary">
                                    🖨 {{ __('owner.export_print') }}
                                </a>
                            </div>
                        </div>

                        @if ($exportTruncated)
                            <p class="text-xs text-err">
                                {{ __('owner.export_truncated_hint', ['limit' => $exportLimit]) }}
                            </p>
                        @endif

                        <form method="GET" action="{{ route('owner.reports.index') }}"
                              class="flex flex-wrap items-end gap-3 border-t border-line/60 pt-4">
                            <input type="hidden" name="range" value="custom">

                            <div>
                                <label for="report-from" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.range_from') }}</label>
                                <input id="report-from" name="from" type="date" value="{{ $from->format('Y-m-d') }}"
                                       class="input input-sm bg-base-100" />
                            </div>

                            <div>
                                <label for="report-to" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.range_to') }}</label>
                                <input id="report-to" name="to" type="date" value="{{ $to->format('Y-m-d') }}"
                                       class="input input-sm bg-base-100" />
                            </div>

                            <button type="submit" class="btn btn-sm btn-accent">{{ __('owner.range_apply') }}</button>

                            <span class="ml-auto text-sm text-ink-soft">
                                {{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }}
                            </span>
                        </form>
                    </div>

                    @include('owner.partials.report-stats', ['stats' => $stats])

                    <div class="grid gap-6 lg:grid-cols-2 items-start">
                        @include('owner.partials.report-payments', ['paymentSplit' => $paymentSplit])
                        @include('owner.partials.report-low-stock', ['stats' => $stats, 'lowStockVariants' => $lowStockVariants])
                    </div>

                    @include('owner.partials.report-top-products', ['topProducts' => $topProducts])

                    @include('owner.partials.report-daily', ['dailySales' => $dailySales])

                </div>
            </div>
        </div>
    </section>
@endsection
