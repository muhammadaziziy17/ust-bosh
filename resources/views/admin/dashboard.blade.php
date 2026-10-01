@extends('layouts.admin')

@section('title', __('admin.dashboard_title'))

@section('content')
    <section class="py-10">
        <div class="mx-auto max-w-6xl px-4">

            {{-- Header --}}
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-2xl md:text-3xl">{{ __('admin.dashboard_title') }}</h1>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('admin.nav_applications') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'uz' ? 'ru' : 'uz', 'from' => 'admin.dashboard']) }}"
                       class="btn btn-outline btn-secondary btn-sm">
                        🌐 {{ __('landing.lang_switch') }}
                    </a>
                </div>
            </div>

            {{-- Platforma ko'rsatkichlari --}}
            <div class="mt-8 grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.stat_shops_total') }}</div>
                    <div class="font-mono text-3xl font-bold mt-1">{{ $platformStats['shops_total'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.stat_shops_active') }}</div>
                    <div class="font-mono text-3xl font-bold mt-1 text-ok">{{ $platformStats['shops_active'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.stat_shops_suspended') }}</div>
                    <div class="font-mono text-3xl font-bold mt-1 text-err">{{ $platformStats['shops_suspended'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.stat_users_total') }}</div>
                    <div class="font-mono text-3xl font-bold mt-1">{{ $platformStats['users_total'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.stat_sales_30d') }}</div>
                    <div class="font-mono text-xl sm:text-2xl font-bold mt-1 text-accent break-words">{{ format_som($platformStats['sales_30d']) }}</div>
                </div>
            </div>

            {{-- Statistika kartalari --}}
            <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.stat_total') }}
                    </div>
                    <div class="font-mono text-3xl font-bold mt-1">{{ $stats['total'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.stat_pending') }}
                    </div>
                    <div class="font-mono text-3xl font-bold mt-1 text-accent">{{ $stats['pending'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">
                        {{ __('admin.stat_approved') }}</div>
                    <div class="font-mono text-3xl font-bold mt-1 text-ok">{{ $stats['approved'] }}</div>
                </div>
                <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="text-xs font-semibold uppercase tracking-wider text-ink-soft">
                        {{ __('admin.stat_rejected') }}</div>
                    <div class="font-mono text-3xl font-bold mt-1 text-err">{{ $stats['rejected'] }}</div>
                </div>
            </div>

            {{-- Qidiruv + Filtr --}}
            <div class="mt-8 flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3">
                <form method="GET" action="{{ route('admin.dashboard') }}" class="flex gap-2 w-full sm:w-auto"
                    data-ajax-search>
                    <input type="search" name="q" value="{{ $search }}"
                        placeholder="{{ __('admin.search_placeholder') }}"
                        class="input input-sm w-full sm:w-72 bg-base-100" />
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button type="submit" class="btn btn-sm btn-accent">{{ __('admin.search_button') }}</button>
                </form>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold mr-1">{{ __('admin.filter_label') }}:</span>
                    @foreach (['all', 'pending', 'approved', 'rejected'] as $filter)
                        <a href="{{ route('admin.dashboard', ['status' => $filter, 'q' => $search]) }}"
                            class="btn btn-sm {{ $status === $filter ? 'btn-accent' : 'btn-ghost' }}">
                            {{ __("admin.filter_{$filter}") }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Flash xabar --}}
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

            {{-- Arizalar jadvali --}}
            <div class="mt-6 rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                            <th>{{ __('admin.table_shop') }}</th>
                            <th>{{ __('admin.table_contact') }}</th>
                            <th>{{ __('admin.table_phone') }}</th>
                            <th>{{ __('admin.table_status') }}</th>
                            <th>{{ __('admin.table_date') }}</th>
                            <th class="text-right">{{ __('admin.table_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($applications as $app)
                            <tr class="border-line/60 hover:bg-base-200/50" data-application-row="{{ $app->id }}">
                                <td>
                                    <div class="font-semibold">{{ $app->shop_name }}</div>
                                    @if ($app->message)
                                        <div class="text-xs text-ink-soft mt-0.5 max-w-xs truncate"
                                            title="{{ $app->message }}">{{ $app->message }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $app->contact_name }}</div>
                                    <div class="text-xs text-ink-soft">{{ $app->email }}</div>
                                </td>
                                <td class="font-mono text-sm">{{ $app->phone }}</td>
                                <td>
                                    @if ($app->status === \App\Models\Application::STATUS_PENDING)
                                        <span
                                            class="badge border-0 bg-accent/15 text-accent">{{ __('admin.status_pending') }}</span>
                                    @elseif ($app->status === \App\Models\Application::STATUS_APPROVED)
                                        <span
                                            class="badge border-0 bg-ok/15 text-ok">{{ __('admin.status_approved') }}</span>
                                    @else
                                        <span
                                            class="badge border-0 bg-err/15 text-err">{{ __('admin.status_rejected') }}</span>
                                    @endif
                                </td>
                                <td class="text-sm text-ink-soft">{{ $app->created_at->format('d.m.Y H:i') }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($app->status === \App\Models\Application::STATUS_PENDING)
                                            <form method="POST" action="{{ route('admin.applications.approve', $app) }}"
                                                data-ajax data-confirm="{{ __('admin.confirm_approve') }}"
                                                data-loader-text="{{ __('admin.approve_btn') }}...">
                                                @csrf
                                                <button type="submit"
                                                    class="btn btn-xs bg-ok text-success-content border-0 hover:opacity-90">
                                                    ✓ {{ __('admin.approve_btn') }}
                                                </button>
                                            </form>
                                            <button type="button"
                                                class="btn btn-xs bg-err text-error-content border-0 hover:opacity-90"
                                                data-reject-url="{{ route('admin.applications.reject', $app) }}"
                                                onclick="openRejectModal({{ $app->id }}, {{ json_encode($app->shop_name, JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) }}, this.dataset.rejectUrl)">
                                                ✕ {{ __('admin.reject_btn') }}
                                            </button>
                                        @elseif ($app->status === \App\Models\Application::STATUS_REJECTED)
                                            <span class="text-xs text-ink-soft max-w-[180px] truncate block"
                                                title="{{ $app->rejection_reason }}">
                                                {{ $app->rejection_reason }}
                                            </span>
                                            <form method="POST" action="{{ route('admin.applications.resend', $app) }}"
                                                data-ajax data-confirm="{{ __('admin.confirm_resend') }}"
                                                data-loader-text="...">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline btn-secondary"
                                                    title="{{ __('admin.resend_btn') }}">
                                                    ✉️ {{ __('admin.resend_btn') }}
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-xs text-ok font-medium">✓</span>
                                            <form method="POST" action="{{ route('admin.applications.resend', $app) }}"
                                                data-ajax data-confirm="{{ __('admin.confirm_resend') }}"
                                                data-loader-text="...">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline btn-secondary"
                                                    title="{{ __('admin.resend_btn') }}">
                                                    ✉️ {{ __('admin.resend_btn') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-10 text-ink-soft">{{ __('admin.table_empty') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginatsiya --}}
            <div class="mt-6">
                {{ $applications->links() }}
            </div>

            {{-- Do'konlar --}}
            <div class="mt-10">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-display text-xl">{{ __('admin.shops_title') }}</h2>
                    <form method="GET" action="{{ route('admin.dashboard') }}" class="flex gap-2 items-center">
                        <input type="hidden" name="q" value="{{ $search }}">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <input type="hidden" name="shop_status" value="{{ $shopStatus }}">
                        <input type="search" name="shop_q" value="{{ $shopSearch }}"
                            placeholder="{{ __('admin.shop_search_placeholder') }}"
                            class="input input-sm w-full sm:w-64 bg-base-100" />
                        <button type="submit" class="btn btn-sm btn-accent">{{ __('admin.search_button') }}</button>
                    </form>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold mr-1">{{ __('admin.shops_status_filter') }}:</span>
                    @foreach (['all' => __('admin.filter_all'), 'active' => __('admin.shop_status_active'), 'suspended' => __('admin.shop_status_suspended'), 'pending' => __('admin.shop_status_pending')] as $value => $label)
                        <a href="{{ route('admin.dashboard', ['shop_status' => $value, 'shop_q' => $shopSearch, 'q' => $search, 'status' => $status]) }}"
                            class="btn btn-sm {{ $shopStatus === $value ? 'btn-accent' : 'btn-ghost' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <div class="mt-4 rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                <th>{{ __('admin.table_shop') }}</th>
                                <th>{{ __('admin.table_owner') }}</th>
                                <th>{{ __('admin.table_phone') }}</th>
                                <th>{{ __('admin.table_status') }}</th>
                                <th class="text-center">{{ __('admin.table_users') }}</th>
                                <th>{{ __('admin.table_date') }}</th>
                                <th class="text-right">{{ __('admin.table_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($shops as $shop)
                                <tr class="border-line/60" data-shop-row="{{ $shop->id }}">
                                    <td class="font-semibold">{{ $shop->name }}</td>
                                    <td class="text-sm">
                                        <div>{{ $shop->owner?->name ?? '—' }}</div>
                                        <div class="text-xs text-ink-soft">{{ $shop->owner?->email }}</div>
                                    </td>
                                    <td class="font-mono text-sm">{{ $shop->phone ?? '—' }}</td>
                                    <td>
                                        @if ($shop->status === \App\Models\Shop::STATUS_ACTIVE)
                                            <span class="badge border-0 bg-ok/15 text-ok">{{ __('admin.shop_status_active') }}</span>
                                        @elseif ($shop->status === \App\Models\Shop::STATUS_SUSPENDED)
                                            <span class="badge border-0 bg-err/15 text-err">{{ __('admin.shop_status_suspended') }}</span>
                                        @else
                                            <span class="badge border-0 bg-accent/15 text-accent">{{ __('admin.shop_status_pending') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center font-mono text-sm">{{ $shop->users_count }}</td>
                                    <td class="text-sm text-ink-soft">{{ $shop->created_at->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.shops.show', $shop) }}"
                                                class="btn btn-xs btn-outline btn-secondary">
                                                👁 {{ __('admin.view_shop') }}
                                            </a>
                                            <form method="POST"
                                                action="{{ $shop->isActive() ? route('admin.shops.suspend', $shop) : route('admin.shops.activate', $shop) }}"
                                                data-ajax
                                                data-confirm="{{ $shop->isActive() ? __('admin.confirm_suspend') : __('admin.confirm_activate') }}"
                                                data-loader-text="...">
                                                @csrf
                                                <button type="submit"
                                                    class="btn btn-xs {{ $shop->isActive() ? 'bg-err text-error-content border-0 hover:opacity-90' : 'bg-ok text-success-content border-0 hover:opacity-90' }}">
                                                    {{ $shop->isActive() ? __('admin.suspend_btn') : __('admin.activate_btn') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-10 text-ink-soft">{{ __('admin.shops_empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($shops->hasPages())
                    <div class="mt-4">
                        {{ $shops->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ================= RAD ETISH MODALI ================= --}}
    <dialog id="reject-modal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box rounded-box border border-line bg-card max-w-lg">
            <h3 class="font-display text-lg">{{ __('admin.reject_modal_title') }}</h3>
            <p class="mt-1 text-sm text-ink-soft">
                <span id="reject-shop-name" class="font-semibold text-base-content"></span> —
                {{ __('admin.reject_modal_subtitle') }}
            </p>
            <form method="POST" id="reject-form" class="mt-5 space-y-4" data-ajax
                data-loader-text="{{ __('admin.reject_btn') }}...">
                @csrf
                <div>
                    <label for="reason"
                        class="mb-1.5 block text-sm font-semibold">{{ __('admin.reject_modal_title') }}</label>
                    <textarea id="reason" name="reason" rows="4" required
                        placeholder="{{ __('admin.reject_reason_placeholder') }}" class="textarea w-full bg-base-100"></textarea>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost"
                        onclick="closeRejectModal()">{{ __('admin.cancel') }}</button>
                    <button type="submit" class="btn bg-err text-error-content border-0 hover:opacity-90">
                        ✕ {{ __('admin.reject_submit') }}
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    @push('scripts')
        <script>
            function openRejectModal(id, shopName, rejectUrl) {
                document.getElementById('reject-form').action = rejectUrl;
                document.getElementById('reject-shop-name').textContent = shopName;
                document.getElementById('reason').value = '';
                document.getElementById('reject-modal').showModal();
            }

            function closeRejectModal() {
                document.getElementById('reject-modal').close();
            }

            // AJAX muvaffaqiyatidan keyin qatorni yangilash
            document.addEventListener('ustbosh:ajax:success', function(event) {
                var detail = event.detail || {};
                var data = detail.data || {};

                // 1) Rad etish modali yuborilgan bo'lsa — modalni yopamiz
                if (detail.form && detail.form.id === 'reject-form') {
                    closeRejectModal();
                }

                // 2) Ariza holati o'zgargan bo'lsa — ro'yxatni yangilaymiz
                if (data.application_id) {
                    var row = document.querySelector('[data-application-row="' + data.application_id + '"]');
                    if (row) {
                        // Qatorni xira qilib, sahifani yangilaymiz (server to'liq holatni qaytaradi)
                        row.style.transition = 'opacity .4s ease';
                        row.style.opacity = '0.45';
                    }
                    setTimeout(function() {
                        location.reload();
                    }, 700);
                }

                // 3) Do'kon holati o'zgargan bo'lsa — jadval holatini yangilaymiz
                if (data.shop_id) {
                    var shopRow = document.querySelector('[data-shop-row="' + data.shop_id + '"]');
                    if (shopRow) {
                        shopRow.style.transition = 'opacity .4s ease';
                        shopRow.style.opacity = '0.45';
                    }
                    setTimeout(function() {
                        location.reload();
                    }, 700);
                }
            });
        </script>
    @endpush
@endsection
