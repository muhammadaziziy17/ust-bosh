@extends('layouts.app')

@section('title', __('owner.reports_title'))

@push('styles')
    <style>
        /* A4 chop etish uchun sahifa o'lchami (faqat shu sahifada) */
        @page {
            size: A4;
            margin: 12mm;
        }
    </style>
@endpush

@section('content')
    <section class="py-8">
        <div class="mx-auto max-w-3xl px-4">

            {{-- Amallar --}}
            <div class="flex flex-wrap items-center justify-between gap-2 sm-print-hide">
                <a href="{{ route('owner.reports.index', array_merge(request()->query(), ['print' => null])) }}"
                   class="btn btn-ghost btn-sm">
                    ← {{ __('owner.back_to_reports') }}
                </a>
                <button type="button" class="btn btn-outline btn-secondary btn-sm" onclick="window.print()">
                    🖨 {{ __('owner.export_print') }}
                </button>
            </div>

            <div class="sm-print-area mt-4 rounded-box border border-line bg-card p-6 shadow-elev space-y-6">
                <div class="text-center">
                    <div class="font-display text-xl">{{ $shop?->name ?? __('landing.brand') }}</div>
                    <div class="mt-0.5 text-xs uppercase tracking-[0.2em] text-ink-soft">{{ __('owner.reports_title') }}</div>
                    <div class="mt-1 font-mono text-xs">
                        {{ __('owner.report_period') }}: {{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }}
                    </div>
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
    </section>

    @if ($autoprint)
        @push('scripts')
            <script>
                // "Chop etish / PDF" bosilganda print oynasi avtomatik chiqadi
                window.addEventListener('load', function () {
                    window.print();
                });
            </script>
        @endpush
    @endif
@endsection
