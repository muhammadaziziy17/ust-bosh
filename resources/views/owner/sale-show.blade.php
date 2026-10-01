@extends('layouts.app')

@section('title', __('owner.sale_receipt_title'))

@push('styles')
    <style>
        /* 80 mm termal printer uchun sahifa o'lchami (faqat shu sahifada) */
        @page {
            size: 80mm auto;
            margin: 3mm;
        }
    </style>
@endpush

@section('content')
    <section class="py-8">
        <div class="mx-auto max-w-[340px] px-3">

            {{-- Amallar --}}
            <div class="flex flex-wrap items-center justify-between gap-2 sm-print-hide">
                <a href="{{ route('owner.sales.index') }}" class="btn btn-ghost btn-xs">
                    ← {{ __('owner.sales_title') }}
                </a>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-outline btn-secondary btn-xs" onclick="window.print()">
                        🖨 {{ __('pos.print_btn') }}
                    </button>
                    @if ($canVoid)
                        <button type="button"
                                class="btn btn-xs bg-err text-error-content border-0 hover:opacity-90"
                                onclick="document.getElementById('void-modal').showModal()">
                            ✕ {{ __('pos.void_btn') }}
                        </button>
                    @endif
                </div>
            </div>

            @if (session('success'))
                <div class="sm-alert mt-3 rounded-field border border-ok/30 bg-ok/10 px-3 py-2 text-xs font-medium text-ok sm-print-hide">
                    {{ session('success') }}
                </div>
            @endif

            @include('partials.sale-receipt', ['sale' => $sale, 'shop' => $shop, 'autoprint' => $autoprint])
        </div>
    </section>

    {{-- Bekor qilish modali (faqat ruxsat yoqilgan bo'lsa) --}}
    @if ($canVoid)
        <dialog id="void-modal" class="modal modal-bottom sm:modal-middle sm-print-hide">
            <div class="modal-box rounded-box border border-line bg-card max-w-md">
                <h3 class="font-display text-lg text-err">{{ __('pos.void_modal_title') }}</h3>
                <p class="mt-1 text-sm text-ink-soft">{{ __('pos.void_modal_subtitle') }}</p>

                <form method="POST" action="{{ route('owner.sales.void', $sale) }}" class="mt-5 space-y-4"
                      data-ajax data-loader-text="{{ __('pos.void_confirm_btn') }}...">
                    @csrf
                    <div>
                        <label for="void-reason" class="mb-1.5 block text-sm font-semibold">{{ __('pos.void_reason_label') }}</label>
                        <textarea id="void-reason" name="reason" rows="3" required minlength="3" maxlength="500"
                                  placeholder="{{ __('pos.void_reason_placeholder') }}"
                                  class="textarea w-full bg-base-100"></textarea>
                        <p class="mt-1 text-xs text-ink-soft">{{ __('pos.void_reason_required') }}</p>
                    </div>

                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost"
                                onclick="document.getElementById('void-modal').close()">{{ __('pos.void_cancel_btn') }}</button>
                        <button type="submit" class="btn bg-err text-error-content border-0 hover:opacity-90">
                            {{ __('pos.void_confirm_btn') }}
                        </button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>
    @endif
@endsection
