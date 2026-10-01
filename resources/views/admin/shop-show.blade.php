@extends('layouts.admin')

@section('title', __('admin.shop_detail_title').' — '.$shop->name)

@section('content')
    <section class="py-8">
        <div class="mx-auto max-w-6xl px-4">

            {{-- Sarlavha --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="font-display text-2xl md:text-3xl">{{ $shop->name }}</h1>
                        @if ($shop->status === \App\Models\Shop::STATUS_ACTIVE)
                            <span class="badge border-0 bg-ok/15 text-ok">{{ __('admin.shop_status_active') }}</span>
                        @elseif ($shop->status === \App\Models\Shop::STATUS_SUSPENDED)
                            <span class="badge border-0 bg-err/15 text-err">{{ __('admin.shop_status_suspended') }}</span>
                        @else
                            <span class="badge border-0 bg-accent/15 text-accent">{{ __('admin.shop_status_pending') }}</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('admin.shop_detail_title') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm">← {{ __('admin.dashboard_title') }}</a>
                    <form method="POST"
                        action="{{ $shop->isActive() ? route('admin.shops.suspend', $shop) : route('admin.shops.activate', $shop) }}"
                        data-ajax
                        data-confirm="{{ $shop->isActive() ? __('admin.confirm_suspend') : __('admin.confirm_activate') }}"
                        data-loader-text="...">
                        @csrf
                        <button type="submit"
                            class="btn btn-sm {{ $shop->isActive() ? 'bg-err text-error-content border-0 hover:opacity-90' : 'bg-ok text-success-content border-0 hover:opacity-90' }}">
                            {{ $shop->isActive() ? __('admin.suspend_btn') : __('admin.activate_btn') }}
                        </button>
                    </form>
                </div>
            </div>

            {{-- Xabarlar --}}
            @if (session('success'))
                <div class="sm-alert mt-6 rounded-field border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="sm-alert mt-6 rounded-field border border-err/30 bg-err/10 px-4 py-3 text-sm font-medium text-err">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="sm-alert mt-6 rounded-field border border-err/30 bg-err/10 px-4 py-3 text-sm font-medium text-err">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Ko'rsatkichlar --}}
            <div class="mt-6 grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.stat_users') }}</div>
                    <div class="font-mono text-3xl font-bold mt-1">{{ $stats['users'] }}</div>
                    <div class="text-xs text-ink-soft mt-1">{{ __('admin.user_active') }}: {{ $stats['active_users'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.stat_products') }}</div>
                    <div class="font-mono text-3xl font-bold mt-1">{{ $stats['products'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.stat_stock') }}</div>
                    <div class="font-mono text-3xl font-bold mt-1 text-ok">{{ $stats['stock'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev col-span-2 lg:col-span-2">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.stat_sales_30d') }}</div>
                    <div class="font-mono text-xl sm:text-2xl font-bold mt-1 text-accent break-words">{{ format_som($stats['sales_30d']) }}</div>
                </div>
            </div>

            {{-- Profil --}}
            <div class="mt-6 rounded-box border border-line bg-card p-5 shadow-elev">
                <h2 class="font-display text-lg mb-4">{{ __('admin.shop_profile_title') }}</h2>
                <form method="POST" action="{{ route('admin.shops.update', $shop) }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label for="shop-name" class="mb-1.5 block text-sm font-semibold">{{ __('admin.shop_name') }}</label>
                        <input id="shop-name" name="name" type="text" required maxlength="255"
                            value="{{ old('name', $shop->name) }}" class="input w-full bg-base-100" />
                    </div>
                    <div>
                        <label for="shop-phone" class="mb-1.5 block text-sm font-semibold">{{ __('admin.phone_label') }}</label>
                        <input id="shop-phone" name="phone" type="text" maxlength="32"
                            value="{{ old('phone', $shop->phone) }}" class="input w-full bg-base-100 font-mono" />
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="btn btn-accent">{{ __('admin.shop_save_btn') }}</button>
                    </div>
                </form>
            </div>

            {{-- Egasi va xodimlar --}}
            <div class="mt-6 rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                <div class="px-5 py-4 border-b border-line/60">
                    <h2 class="font-display text-lg">{{ __('admin.shop_team_title') }}</h2>
                </div>
                <table class="table">
                    <thead>
                        <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                            <th>{{ __('owner.emp_name') }}</th>
                            <th>{{ __('owner.table_role') }}</th>
                            <th>{{ __('admin.table_status') }}</th>
                            <th>{{ __('admin.table_created') }}</th>
                            <th class="text-right">{{ __('admin.table_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $member)
                            <tr class="border-line/60">
                                <td>
                                    <div class="font-semibold text-sm">{{ $member->name }}</div>
                                    <div class="text-xs text-ink-soft">{{ $member->email }}</div>
                                </td>
                                <td>
                                    <span class="badge border-0 bg-base-200 text-ink-soft">{{ __('owner.role_'.$member->role) }}</span>
                                </td>
                                <td>
                                    @if ($member->is_active)
                                        <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('admin.user_active') }}</span>
                                    @else
                                        <span class="badge badge-sm border-0 bg-err/15 text-err">{{ __('admin.user_inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-sm text-ink-soft whitespace-nowrap">{{ $member->created_at->format('d.m.Y') }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.shops.users.toggle', [$shop, $member]) }}"
                                            data-ajax
                                            data-confirm="{{ $member->is_active ? __('admin.confirm_user_deactivate') : __('admin.confirm_user_activate') }}"
                                            data-loader-text="...">
                                            @csrf
                                            <button type="submit"
                                                class="btn btn-xs {{ $member->is_active ? 'bg-err text-error-content border-0 hover:opacity-90' : 'bg-ok text-success-content border-0 hover:opacity-90' }}">
                                                {{ $member->is_active ? __('admin.deactivate_user_btn') : __('admin.activate_user_btn') }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.shops.users.reset-password', [$shop, $member]) }}"
                                            data-ajax
                                            data-confirm="{{ __('admin.confirm_reset_password') }}"
                                            data-loader-text="...">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline btn-secondary">
                                                🔑 {{ __('admin.reset_password_btn') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mahsulotlar --}}
            <div class="mt-6 rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                <div class="px-5 py-4 border-b border-line/60">
                    <h2 class="font-display text-lg">{{ __('admin.shop_products_title') }}</h2>
                </div>
                <table class="table">
                    <thead>
                        <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                            <th>{{ __('warehouse.table_product') }}</th>
                            <th>{{ __('warehouse.table_sku') }}</th>
                            <th class="text-center">{{ __('warehouse.table_variants') }}</th>
                            <th class="text-right">{{ __('warehouse.table_price') }}</th>
                            <th>{{ __('admin.table_status') }}</th>
                            <th class="text-right">{{ __('admin.table_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            <tr class="border-line/60">
                                <td>
                                    <div class="font-semibold text-sm">{{ $product->name }}</div>
                                    @if ($product->category)
                                        <div class="text-xs text-ink-soft">{{ $product->category }}</div>
                                    @endif
                                </td>
                                <td class="font-mono text-sm">{{ $product->sku }}</td>
                                <td class="text-center font-mono text-sm">{{ $product->variants_count }}</td>
                                <td class="text-right font-mono text-sm">{{ format_som($product->sale_price) }}</td>
                                <td>
                                    @if ($product->is_active)
                                        <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('warehouse.status_active') }}</span>
                                    @else
                                        <span class="badge badge-sm border-0 bg-base-200 text-ink-soft">{{ __('warehouse.status_inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('admin.shops.products.edit', [$shop, $product]) }}"
                                        class="btn btn-xs btn-outline btn-secondary">
                                        ✏️ {{ __('admin.edit_product_btn') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-10 text-ink-soft">{{ __('admin.shop_empty') }}</td>
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

            {{-- Kam qolgan qoldiqlar va tuzatish --}}
            <div class="mt-6 rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                <div class="px-5 py-4 border-b border-line/60">
                    <h2 class="font-display text-lg">{{ __('admin.shop_low_stock_title') }}</h2>
                </div>
                <table class="table">
                    <thead>
                        <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                            <th>{{ __('warehouse.table_product') }}</th>
                            <th class="text-right">{{ __('warehouse.table_stock') }}</th>
                            <th class="text-right">{{ __('warehouse.variant_reorder') }}</th>
                            <th class="text-right">{{ __('admin.adjust_btn') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lowStockVariants as $variant)
                            <tr class="border-line/60">
                                <td>
                                    <div class="font-semibold text-sm">{{ $variant->product?->name ?? '—' }}</div>
                                    <div class="font-mono text-xs text-ink-soft">{{ $variant->displayLabel() }}</div>
                                </td>
                                <td class="text-right font-mono text-sm font-bold text-accent">{{ $variant->stock_on_hand }}</td>
                                <td class="text-right font-mono text-sm text-ink-soft">{{ $variant->reorder_level }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.shops.variants.adjust', [$shop, $variant]) }}"
                                        class="flex items-center justify-end gap-1.5">
                                        @csrf
                                        <input name="delta" type="number" required step="1" min="-1000000" max="1000000"
                                            placeholder="+/-" class="input input-xs w-24 bg-base-100 font-mono" />
                                        <input name="reason" type="text" required minlength="5" maxlength="500"
                                            placeholder="{{ __('admin.adjust_reason') }}" class="input input-xs w-44 bg-base-100" />
                                        <button type="submit" class="btn btn-xs btn-outline btn-secondary whitespace-nowrap">
                                            {{ __('admin.adjust_btn') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-10 text-ink-soft">{{ __('admin.shop_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- So'nggi sotuvlar --}}
            <div class="mt-6 rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                <div class="px-5 py-4 border-b border-line/60">
                    <h2 class="font-display text-lg">{{ __('admin.shop_sales_title') }}</h2>
                </div>
                <table class="table">
                    <thead>
                        <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                            <th>{{ __('pos.table_sale_no') }}</th>
                            <th>{{ __('pos.table_cashier') }}</th>
                            <th>{{ __('admin.table_status') }}</th>
                            <th class="text-right">{{ __('pos.table_total') }}</th>
                            <th>{{ __('admin.table_date') }}</th>
                            <th class="text-right">{{ __('admin.table_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentSales as $sale)
                            <tr class="border-line/60">
                                <td class="font-mono text-sm">{{ $sale->sale_no }}</td>
                                <td class="text-sm">{{ $sale->cashier?->name ?? '—' }}</td>
                                <td>
                                    @if ($sale->status === \App\Models\Sale::STATUS_COMPLETED)
                                        <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('pos.status_completed') }}</span>
                                    @else
                                        <span class="badge badge-sm border-0 bg-err/15 text-err">{{ __('pos.status_voided') }}</span>
                                    @endif
                                </td>
                                <td class="text-right font-mono text-sm font-bold">{{ format_som($sale->total) }}</td>
                                <td class="text-sm text-ink-soft whitespace-nowrap">{{ $sale->sold_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    @if ($sale->status === \App\Models\Sale::STATUS_COMPLETED)
                                        <form method="POST" action="{{ route('admin.shops.sales.void', [$shop, $sale]) }}"
                                            class="flex items-center justify-end gap-1.5">
                                            @csrf
                                            <input name="reason" type="text" required minlength="3" maxlength="500"
                                                placeholder="{{ __('pos.void_reason_label') }}" class="input input-xs w-44 bg-base-100" />
                                            <button type="submit" class="btn btn-xs bg-err text-error-content border-0 hover:opacity-90 whitespace-nowrap">
                                                {{ __('pos.void_btn') }}
                                            </button>
                                        </form>
                                    @else
                                        <div class="text-right text-xs text-ink-soft">{{ $sale->void_reason ?? '—' }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-10 text-ink-soft">{{ __('admin.shop_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-3 items-start">
                {{-- So'nggi kirimlar --}}
                <div class="rounded-box border border-line bg-card shadow-elev">
                    <div class="px-5 py-4 border-b border-line/60">
                        <h2 class="font-display text-lg">{{ __('admin.shop_receipts_title') }}</h2>
                    </div>
                    <ul class="divide-y divide-line/60">
                        @forelse ($recentReceipts as $receipt)
                            <li class="px-5 py-3 text-sm">
                                <div class="font-mono font-semibold">{{ $receipt->receipt_no }}</div>
                                <div class="text-xs text-ink-soft">{{ $receipt->creator?->name ?? '—' }} · {{ $receipt->received_at->format('d.m.Y H:i') }}</div>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-sm text-ink-soft">{{ __('admin.shop_empty') }}</li>
                        @endforelse
                    </ul>
                </div>

                {{-- So'nggi kirishlar --}}
                <div class="rounded-box border border-line bg-card shadow-elev">
                    <div class="px-5 py-4 border-b border-line/60">
                        <h2 class="font-display text-lg">{{ __('admin.shop_logins_title') }}</h2>
                    </div>
                    <ul class="divide-y divide-line/60">
                        @forelse ($loginEvents as $event)
                            <li class="px-5 py-3 text-sm">
                                <div class="font-semibold">{{ $event->user?->name ?? '—' }}</div>
                                <div class="text-xs text-ink-soft">{{ $event->logged_in_at->format('d.m.Y H:i') }} · {{ $event->ip_address ?? '—' }}</div>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-sm text-ink-soft">{{ __('admin.shop_empty') }}</li>
                        @endforelse
                    </ul>
                </div>

                {{-- So'nggi audit --}}
                <div class="rounded-box border border-line bg-card shadow-elev">
                    <div class="px-5 py-4 border-b border-line/60">
                        <h2 class="font-display text-lg">{{ __('admin.shop_audit_title') }}</h2>
                    </div>
                    <ul class="divide-y divide-line/60">
                        @forelse ($auditLogs as $log)
                            <li class="px-5 py-3 text-sm">
                                <div class="font-mono text-xs font-semibold">{{ $log->action }}</div>
                                <div class="text-xs text-ink-soft">
                                    {{ $log->actor?->name ?? '—' }} · {{ $log->created_at?->format('d.m.Y H:i') }}
                                </div>
                                @if ($log->reason)
                                    <div class="text-xs text-ink-soft mt-0.5">{{ $log->reason }}</div>
                                @endif
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-sm text-ink-soft">{{ __('admin.shop_empty') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </section>
@endsection
