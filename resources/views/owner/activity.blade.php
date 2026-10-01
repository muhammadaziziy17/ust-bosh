@extends('layouts.app')

@section('title', __('owner.login_history_title'))

@section('content')
    <section class="py-10">
        <div class="mx-auto max-w-6xl px-4">
            @include('owner.partials.header', [
                'title' => __('owner.login_history_title'),
                'subtitle' => __('owner.login_history_subtitle'),
                'shop' => $shop,
            ])

            <div class="mt-6 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] items-start">
                @include('owner.partials.nav')

                <div class="min-w-0 space-y-6">

                    {{-- Kirish statistikasi --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.stat_logins_today') }}</span>
                            <div class="font-mono text-3xl font-bold mt-2 text-ok">{{ $stats['logins_today'] }}</div>
                        </div>
                        <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.stat_logins_total') }}</span>
                            <div class="font-mono text-3xl font-bold mt-2">{{ $stats['logins_total'] }}</div>
                        </div>
                        <div class="rounded-box border border-line bg-card p-5 shadow-elev">
                            <span class="text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('owner.stat_must_change') }}</span>
                            <div class="font-mono text-3xl font-bold mt-2 text-err">{{ $stats['must_change'] }}</div>
                        </div>
                    </div>

                    <div class="rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                        <div class="px-5 py-3 border-b border-line/60 flex flex-wrap items-center gap-3">
                            <form method="GET" action="{{ route('owner.activity.index') }}" class="flex gap-2 items-center">
                                <input type="search" name="q" value="{{ $loginSearch }}"
                                       placeholder="{{ __('owner.login_search_placeholder') }}"
                                       class="input input-sm w-full sm:w-64 bg-base-100" />
                                <input type="hidden" name="sort" value="{{ $loginSort }}">
                                <input type="hidden" name="role" value="{{ $loginRole }}">
                                <button type="submit" class="btn btn-sm btn-accent" aria-label="{{ __('admin.search_button') }}">🔍</button>
                            </form>

                            <div class="flex items-center gap-2">
                                <span class="text-sm text-ink-soft">{{ __('owner.login_filter_role') }}:</span>
                                <a href="{{ route('owner.activity.index', ['q' => $loginSearch, 'sort' => $loginSort, 'role' => 'all']) }}"
                                   class="btn btn-sm {{ $loginRole === 'all' ? 'btn-accent' : 'btn-ghost' }}">
                                    {{ __('owner.login_role_all') }}
                                </a>
                                <a href="{{ route('owner.activity.index', ['q' => $loginSearch, 'sort' => $loginSort, 'role' => \App\Models\User::ROLE_OWNER]) }}"
                                   class="btn btn-sm {{ $loginRole === \App\Models\User::ROLE_OWNER ? 'btn-accent' : 'btn-ghost' }}">
                                    {{ __('owner.role_owner') }}
                                </a>
                                <a href="{{ route('owner.activity.index', ['q' => $loginSearch, 'sort' => $loginSort, 'role' => \App\Models\User::ROLE_CASHIER]) }}"
                                   class="btn btn-sm {{ $loginRole === \App\Models\User::ROLE_CASHIER ? 'btn-accent' : 'btn-ghost' }}">
                                    {{ __('owner.role_cashier') }}
                                </a>
                                <a href="{{ route('owner.activity.index', ['q' => $loginSearch, 'sort' => $loginSort, 'role' => \App\Models\User::ROLE_WAREHOUSE]) }}"
                                   class="btn btn-sm {{ $loginRole === \App\Models\User::ROLE_WAREHOUSE ? 'btn-accent' : 'btn-ghost' }}">
                                    {{ __('owner.role_warehouse') }}
                                </a>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="text-sm text-ink-soft">{{ __('owner.login_sort') }}:</span>
                                <a href="{{ route('owner.activity.index', ['q' => $loginSearch, 'sort' => 'desc', 'role' => $loginRole]) }}"
                                   class="btn btn-sm {{ $loginSort === 'desc' ? 'btn-accent' : 'btn-ghost' }}">
                                    {{ __('owner.login_sort_desc') }}
                                </a>
                                <a href="{{ route('owner.activity.index', ['q' => $loginSearch, 'sort' => 'asc', 'role' => $loginRole]) }}"
                                   class="btn btn-sm {{ $loginSort === 'asc' ? 'btn-accent' : 'btn-ghost' }}">
                                    {{ __('owner.login_sort_asc') }}
                                </a>
                            </div>
                        </div>

                        <table class="table">
                            <thead>
                                <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                    <th>{{ __('owner.emp_name') }}</th>
                                    <th>{{ __('owner.table_role') }}</th>
                                    <th>{{ __('owner.login_time') }}</th>
                                    <th>{{ __('owner.login_ip') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($loginEvents as $event)
                                    <tr class="border-line/60">
                                        <td class="font-semibold">{{ $event->user?->name ?? '—' }}</td>
                                        <td>
                                            @if ($event->user?->role === \App\Models\User::ROLE_OWNER)
                                                <span class="badge border-0 bg-ok/15 text-ok">{{ __('owner.role_owner') }}</span>
                                            @elseif ($event->user?->role === \App\Models\User::ROLE_CASHIER)
                                                <span class="badge border-0 bg-accent/15 text-accent">{{ __('owner.role_cashier') }}</span>
                                            @else
                                                <span class="badge border-0 bg-info/15 text-info">{{ __('owner.role_warehouse') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-sm whitespace-nowrap">{{ $event->logged_in_at->setTimezone('Asia/Tashkent')->format('d.m.Y H:i:s') }}</td>
                                        <td class="font-mono text-sm text-ink-soft whitespace-nowrap">{{ $event->ip_address ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-10 text-ink-soft">{{ __('owner.no_login_history') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <div class="px-5 py-4 border-t border-line/60">
                            {{ $loginEvents->links() }}
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
@endsection
