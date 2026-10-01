@extends('layouts.app')

@section('title', __('warehouse.receipt_show_title'))

@section('content')
    <section class="py-10">
        <div class="mx-auto max-w-3xl px-4">

            {{-- Amallar --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('warehouse.dashboard', ['tab' => 'receipts']) }}" class="btn btn-ghost btn-sm">
                    ← {{ __('warehouse.tab_receipts') }}
                </a>
                @if ($receipt->status === \App\Models\StockReceipt::STATUS_POSTED)
                    <button type="button"
                            class="btn btn-sm bg-err text-error-content border-0 hover:opacity-90"
                            onclick="document.getElementById('cancel-receipt-modal').showModal()">
                        ✕ {{ __('warehouse.receipt_cancel_btn') }}
                    </button>
                @endif
            </div>

            @if (session('success'))
                <div class="sm-alert mt-4 rounded-field border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mt-6 rounded-box border border-line bg-card shadow-elev overflow-hidden">
                <div class="px-6 py-5 border-b border-line/60 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-ink-soft">{{ __('warehouse.receipt_no') }}</div>
                        <div class="font-mono text-xl font-bold">{{ $receipt->receipt_no }}</div>
                    </div>
                    @if ($receipt->status === \App\Models\StockReceipt::STATUS_POSTED)
                        <span class="badge border-0 bg-ok/15 text-ok">{{ __('warehouse.receipt_status_posted') }}</span>
                    @else
                        <span class="badge border-0 bg-err/15 text-err">{{ __('warehouse.receipt_status_cancelled') }}</span>
                    @endif
                </div>

                <div class="p-6 space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-ink-soft">{{ __('warehouse.receipt_date') }}</span>
                            <span class="font-mono">{{ $receipt->received_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-ink-soft">{{ __('warehouse.receipt_by') }}</span>
                            <span class="font-semibold">{{ $receipt->creator?->name ?? '—' }}</span>
                        </div>
                    </div>

                    @if ($receipt->notes)
                        <div class="rounded-field border border-line bg-base-100/60 px-4 py-3 text-sm">
                            <span class="text-ink-soft">{{ __('warehouse.receipt_notes') }}:</span>
                            {{ $receipt->notes }}
                        </div>
                    @endif

                    @if ($receipt->status === \App\Models\StockReceipt::STATUS_CANCELLED)
                        <div class="rounded-field border border-err/30 bg-err/10 px-4 py-3 text-sm text-err space-y-1">
                            <div class="font-bold">{{ __('warehouse.receipt_status_cancelled') }}</div>
                            <div>
                                {{ __('warehouse.receipt_cancelled_at') }}:
                                <span class="font-mono">{{ $receipt->cancelled_at?->format('d.m.Y H:i') ?? '—' }}</span>
                            </div>
                            <div>
                                {{ __('warehouse.receipt_cancelled_by') }}:
                                {{ $receipt->canceller?->name ?? '—' }}
                            </div>
                            @if ($receipt->cancel_reason)
                                <div>{{ __('warehouse.receipt_cancel_reason') }}: {{ $receipt->cancel_reason }}</div>
                            @endif
                        </div>
                    @endif

                    <div class="border-t border-dashed border-line"></div>

                    <h2 class="font-display text-lg">{{ __('warehouse.receipt_items_title') }}</h2>

                    <table class="table table-sm">
                        <thead>
                            <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                <th>{{ __('warehouse.movement_product') }}</th>
                                <th class="text-right">{{ __('warehouse.quantity_label') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($receipt->items as $item)
                                <tr class="border-line/60">
                                    <td>
                                        <div class="font-semibold text-sm">{{ $item->variant?->product?->name ?? '—' }}</div>
                                        <div class="font-mono text-xs text-ink-soft">{{ $item->variant?->displayLabel() ?? '—' }}</div>
                                    </td>
                                    <td class="text-right font-mono text-sm font-bold whitespace-nowrap">+{{ $item->quantity }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center py-8 text-ink-soft">{{ __('warehouse.receipts_empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="border-line">
                                <td class="font-semibold">{{ __('warehouse.receipt_total_qty') }}</td>
                                <td class="text-right font-mono font-bold">{{ $receipt->items->sum('quantity') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>

    {{-- Bekor qilish modali --}}
    @if ($receipt->status === \App\Models\StockReceipt::STATUS_POSTED)
        <dialog id="cancel-receipt-modal" class="modal modal-bottom sm:modal-middle">
            <div class="modal-box rounded-box border border-line bg-card max-w-md">
                <h3 class="font-display text-lg text-err">{{ __('warehouse.receipt_cancel_btn') }}</h3>
                <p class="mt-1 text-sm text-ink-soft">{{ __('warehouse.receipt_cancel_hint') }}</p>

                <form method="POST" action="{{ route('warehouse.receipts.cancel', $receipt) }}" class="mt-5 space-y-4"
                      data-ajax data-loader-text="{{ __('warehouse.receipt_cancel_confirm_btn') }}...">
                    @csrf
                    <div>
                        <label for="cancel-reason" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.receipt_cancel_reason') }}</label>
                        <textarea id="cancel-reason" name="reason" rows="3" maxlength="500"
                                  class="textarea w-full bg-base-100"></textarea>
                    </div>

                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost"
                                onclick="document.getElementById('cancel-receipt-modal').close()">{{ __('admin.cancel') }}</button>
                        <button type="submit" class="btn bg-err text-error-content border-0 hover:opacity-90">
                            {{ __('warehouse.receipt_cancel_confirm_btn') }}
                        </button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>
    @endif
@endsection
