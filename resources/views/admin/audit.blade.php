@extends('layouts.admin')

@section('title', __('admin.audit_title'))

@section('content')
    <section class="py-8">
        <div class="mx-auto max-w-6xl px-4">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl md:text-3xl">{{ __('admin.audit_title') }}</h1>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('admin.audit_subtitle') }}</p>
                </div>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm">← {{ __('admin.dashboard_title') }}</a>
            </div>

            {{-- Filtr --}}
            <form method="GET" action="{{ route('admin.audit.index') }}"
                class="mt-6 flex flex-wrap items-end gap-3 rounded-box border border-line bg-card p-5 shadow-elev">
                <div>
                    <label for="audit-shop" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.audit_shop') }}</label>
                    <select id="audit-shop" name="shop" class="select select-sm bg-base-100">
                        <option value="0" @selected($shopId === 0)>{{ __('admin.audit_all_shops') }}</option>
                        @foreach ($shops as $shop)
                            <option value="{{ $shop->id }}" @selected($shopId === $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="audit-action" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-ink-soft">{{ __('admin.audit_action') }}</label>
                    <select id="audit-action" name="action" class="select select-sm bg-base-100 font-mono">
                        <option value="all" @selected($action === 'all')>{{ __('admin.audit_all_actions') }}</option>
                        @foreach ($actions as $option)
                            <option value="{{ $option }}" @selected($action === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-sm btn-accent">{{ __('admin.search_button') }}</button>
            </form>

            {{-- Jurnal --}}
            <div class="mt-6 rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                            <th>{{ __('admin.audit_date') }}</th>
                            <th>{{ __('admin.audit_actor') }}</th>
                            <th>{{ __('admin.audit_action') }}</th>
                            <th>{{ __('admin.audit_shop') }}</th>
                            <th>{{ __('admin.audit_subject') }}</th>
                            <th>{{ __('admin.audit_reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-line/60">
                                <td class="text-sm text-ink-soft whitespace-nowrap">{{ $log->created_at?->format('d.m.Y H:i') }}</td>
                                <td class="text-sm">{{ $log->actor?->name ?? '—' }}</td>
                                <td class="font-mono text-xs font-semibold">{{ $log->action }}</td>
                                <td class="text-sm">{{ $log->shop?->name ?? '—' }}</td>
                                <td class="text-xs text-ink-soft">
                                    {{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}
                                </td>
                                <td class="text-xs text-ink-soft">{{ $log->reason ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-10 text-ink-soft">{{ __('admin.audit_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $logs->links() }}
            </div>
        </div>
    </section>
@endsection
