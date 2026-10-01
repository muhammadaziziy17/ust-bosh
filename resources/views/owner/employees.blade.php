@extends('layouts.app')

@section('title', __('owner.employees_title'))

@section('content')
    <section class="py-10">
        <div class="mx-auto max-w-6xl px-4">
            @include('owner.partials.header', [
                'title' => __('owner.employees_title'),
                'subtitle' => __('owner.employees_subtitle'),
                'shop' => $shop,
            ])

            {{-- Vaqtinchalik parol — faqat bir marta (AJAX bo'lmagan holatda) --}}
            @if (session('employee_password'))
                <div class="mt-6 rounded-field border border-accent/40 bg-accent/10 px-4 py-4 text-sm">
                    <div class="font-semibold text-accent">{{ __('owner.temp_credentials') }}</div>
                    <div class="mt-1 font-mono">{{ session('employee_password') }}</div>
                    <div class="mt-1 text-xs text-ink-soft">{{ __('owner.temp_credentials_hint') }}</div>
                </div>
            @endif

            <div class="mt-6 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] items-start">
                @include('owner.partials.nav')

                <div class="min-w-0">
                    <div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_340px] items-start">

                        <div class="rounded-box border border-line bg-card shadow-elev overflow-x-auto">
                            <div class="px-5 py-4 border-b border-line/60 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <h2 class="font-display text-lg">{{ __('owner.employees_title') }}</h2>
                                    <span class="badge badge-sm border-0 bg-accent/15 text-accent">{{ $employees->count() }}</span>
                                </div>
                                <form method="GET" action="{{ route('owner.employees.index') }}" class="flex gap-2 items-center">
                                    <input type="search" name="q" value="{{ $search }}"
                                           placeholder="{{ __('owner.employees_search_placeholder') }}"
                                           class="input input-sm w-full sm:w-64 bg-base-100" />
                                    <button type="submit" class="btn btn-sm btn-accent">{{ __('admin.search_button') }}</button>
                                </form>
                            </div>
                            <table class="table table-sm">
                                <thead>
                                    <tr class="border-line text-xs uppercase tracking-wider text-ink-soft">
                                        <th>{{ __('owner.emp_name') }}</th>
                                        <th>{{ __('owner.table_role') }}</th>
                                        <th>{{ __('owner.emp_email') }}</th>
                                        <th>{{ __('owner.last_login') }}</th>
                                        <th>{{ __('owner.table_status') }}</th>
                                        <th class="text-right">{{ __('owner.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($employees as $employee)
                                        <tr class="border-line/60">
                                            <td>
                                                <div class="font-semibold">{{ $employee->name }}</div>
                                                <div class="text-xs text-ink-soft">{{ $employee->created_at->format('d.m.Y') }}</div>
                                            </td>
                                            <td>
                                                @if ($employee->role === \App\Models\User::ROLE_CASHIER)
                                                    <span class="badge border-0 bg-accent/15 text-accent">{{ __('owner.role_cashier') }}</span>
                                                @else
                                                    <span class="badge border-0 bg-info/15 text-info">{{ __('owner.role_warehouse') }}</span>
                                                @endif
                                                @if ($employee->must_change_password)
                                                    <span class="badge badge-sm border-0 bg-err/15 text-err mt-1" title="{{ __('owner.must_change_badge') }}">🔑</span>
                                                @endif
                                            </td>
                                            <td class="text-sm">
                                                <div class="max-w-[190px] truncate" title="{{ $employee->email }}">{{ $employee->email }}</div>
                                            </td>
                                            <td class="text-sm text-ink-soft whitespace-nowrap">
                                                {{ $employee->last_login_at?->setTimezone('Asia/Tashkent')->format('d.m.Y H:i') ?? __('owner.no_login_yet') }}
                                            </td>
                                            <td>
                                                @if ($employee->is_active)
                                                    <span class="badge badge-sm border-0 bg-ok/15 text-ok">{{ __('owner.employee_active') }}</span>
                                                @else
                                                    <span class="badge badge-sm border-0 bg-err/15 text-err">{{ __('owner.employee_inactive') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <button type="button" class="btn btn-xs btn-outline btn-secondary"
                                                            data-edit-employee
                                                            data-update-url="{{ route('owner.employees.update', $employee) }}"
                                                            data-name="{{ $employee->name }}"
                                                            data-email="{{ $employee->email }}"
                                                            data-role="{{ $employee->role }}"
                                                            title="{{ __('owner.edit_employee') }}"
                                                            aria-label="{{ __('owner.edit_employee') }}">
                                                        ✏️
                                                    </button>

                                                    <form method="POST" action="{{ route('owner.employees.toggle', $employee) }}"
                                                          data-confirm="{{ $employee->is_active ? __('owner.confirm_deactivate') : __('owner.confirm_activate') }}">
                                                        @csrf
                                                        <button type="submit"
                                                                class="btn btn-xs {{ $employee->is_active ? 'btn-outline btn-secondary' : 'btn-accent' }}"
                                                                title="{{ $employee->is_active ? __('owner.deactivate_btn') : __('owner.activate_btn') }}"
                                                                aria-label="{{ $employee->is_active ? __('owner.deactivate_btn') : __('owner.activate_btn') }}">
                                                            {{ $employee->is_active ? '🚫' : '✅' }}
                                                        </button>
                                                    </form>

                                                    <form method="POST" action="{{ route('owner.employees.reset-password', $employee) }}"
                                                          data-ajax data-credentials data-loader-text="{{ __('owner.regenerate_btn') }}..."
                                                          data-confirm="{{ __('owner.confirm_regenerate') }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-xs btn-outline btn-secondary"
                                                                title="{{ __('owner.regenerate_btn') }}"
                                                                aria-label="{{ __('owner.regenerate_btn') }}">
                                                            🔑
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-10 text-ink-soft">{{ __('owner.employees_empty') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="rounded-box border border-line bg-card p-5 shadow-elev h-fit">
                            <h2 class="font-display text-lg mb-4">{{ __('owner.add_employee') }}</h2>

                            <form method="POST" action="{{ route('owner.employees.store') }}" class="space-y-4"
                                  data-ajax data-reset-on-success data-credentials
                                  data-loader-text="{{ __('owner.emp_add_btn') }}...">
                                @csrf

                                <div>
                                    <label for="emp-name" class="mb-1.5 block text-sm font-semibold">{{ __('owner.emp_name') }}</label>
                                    <input id="emp-name" name="name" type="text" required
                                           class="input w-full bg-base-100" />
                                </div>

                                <div>
                                    <label for="emp-email" class="mb-1.5 block text-sm font-semibold">{{ __('owner.emp_email') }}</label>
                                    <input id="emp-email" name="email" type="email" required
                                           class="input w-full bg-base-100" />
                                </div>

                                <div>
                                    <label for="emp-role" class="mb-1.5 block text-sm font-semibold">{{ __('owner.emp_role') }}</label>
                                    <select id="emp-role" name="role" class="select w-full bg-base-100">
                                        <option value="cashier">{{ __('owner.role_cashier') }}</option>
                                        <option value="warehouse">{{ __('owner.role_warehouse') }}</option>
                                    </select>
                                </div>

                                <div class="rounded-field border border-line bg-base-100/60 px-3 py-2 text-xs text-ink-soft">
                                    {{ __('owner.temp_password_note') }}
                                </div>

                                <button type="submit" class="btn btn-accent sm-grow w-full shadow-elev">
                                    {{ __('owner.emp_add_btn') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= Vaqtinchalik parol modal ================= --}}
    <dialog id="credentials-modal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box rounded-box border border-line bg-card max-w-md">
            <h3 class="font-display text-lg">{{ __('owner.temp_credentials') }}</h3>
            <p class="mt-1 text-sm text-ink-soft">{{ __('owner.temp_credentials_hint') }}</p>

            <div class="mt-4 rounded-field border border-line bg-base-100/60 p-4 space-y-3">
                <div class="flex items-center justify-between gap-2 text-sm">
                    <span class="text-ink-soft shrink-0">{{ __('owner.emp_email') }}:</span>
                    <span id="cred-email" class="font-mono font-semibold truncate"></span>
                </div>
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="text-ink-soft shrink-0">{{ __('owner.temp_password_label') }}:</span>
                    <span class="flex items-center gap-2 min-w-0">
                        <span id="cred-password" class="font-mono font-bold text-accent truncate"></span>
                        <button type="button" id="copy-password-btn"
                                class="btn btn-xs btn-outline btn-secondary shrink-0"
                                title="{{ __('owner.copy_password') }}">
                            📋 {{ __('owner.copy_password') }}
                        </button>
                    </span>
                </div>
            </div>

            <div class="modal-action">
                <button type="button" class="btn btn-ghost"
                        onclick="document.getElementById('credentials-modal').close()">
                    {{ __('admin.cancel') }}
                </button>
                <button type="button" class="btn btn-accent" id="credentials-close-btn">
                    ✓ {{ __('owner.temp_credentials_close') }}
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    {{-- ================= Xodimni tahrirlash modali ================= --}}
    <dialog id="edit-employee-modal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box rounded-box border border-line bg-card max-w-md">
            <h3 class="font-display text-lg">{{ __('owner.edit_employee') }}</h3>

            <form method="POST" id="edit-employee-form" class="mt-4 space-y-4" data-ajax data-loader-text="...">
                @csrf
                <div>
                    <label for="ee-name" class="mb-1.5 block text-sm font-semibold">{{ __('owner.emp_name') }}</label>
                    <input id="ee-name" name="name" type="text" required maxlength="255"
                           class="input w-full bg-base-100" />
                </div>
                <div>
                    <label for="ee-email" class="mb-1.5 block text-sm font-semibold">{{ __('owner.emp_email') }}</label>
                    <input id="ee-email" name="email" type="email" required maxlength="255"
                           class="input w-full bg-base-100" />
                </div>
                <div>
                    <label for="ee-role" class="mb-1.5 block text-sm font-semibold">{{ __('owner.emp_role') }}</label>
                    <select id="ee-role" name="role" class="select w-full bg-base-100">
                        <option value="cashier">{{ __('owner.role_cashier') }}</option>
                        <option value="warehouse">{{ __('owner.role_warehouse') }}</option>
                    </select>
                </div>
                <div class="modal-action">
                    <button type="button" class="btn btn-ghost"
                            onclick="document.getElementById('edit-employee-modal').close()">{{ __('admin.cancel') }}</button>
                    <button type="submit" class="btn btn-accent">{{ __('owner.save_btn') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    @push('scripts')
    <script>
        (function () {
            'use strict';

            var modal = document.getElementById('credentials-modal');
            var copyBtn = document.getElementById('copy-password-btn');
            var closeBtn = document.getElementById('credentials-close-btn');

            // Yangi xodim yoki yangi parol haqidagi ma'lumot kelganda modalni ochamiz
            document.addEventListener('ustbosh:ajax:success', function (event) {
                var detail = event.detail || {};
                var data = detail.data || {};
                var form = detail.form;

                if (!form || !form.matches('[data-credentials]')) return;
                if (!data.temporary_password) return;

                document.getElementById('cred-email').textContent = data.email || data.employee?.email || '';
                document.getElementById('cred-password').textContent = data.temporary_password;
                modal.showModal();
            });

            // Parolni nusxalash
            function copyPassword() {
                var password = document.getElementById('cred-password').textContent;
                if (!password) return;

                var done = function () {
                    window.ustboshToast('{{ __('owner.password_copied') }}', 'success');
                };

                var fallback = function () {
                    var temp = document.createElement('textarea');
                    temp.value = password;
                    temp.setAttribute('readonly', '');
                    temp.style.position = 'absolute';
                    temp.style.left = '-9999px';
                    document.body.appendChild(temp);
                    temp.select();

                    try {
                        document.execCommand('copy');
                        done();
                    } catch (e) {
                        window.ustboshToast('{{ __('owner.copy_failed') }}', 'error');
                    }

                    document.body.removeChild(temp);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(password).then(done).catch(fallback);
                } else {
                    fallback();
                }
            }

            copyBtn.addEventListener('click', copyPassword);

            // Tushunarli tugma ham modalni yopadi
            closeBtn.addEventListener('click', function () {
                modal.close();
            });

            // Modal qanday yopilmasin (tugma, Esc yoki fon) — sahifa yangilanadi,
            // shunda xodimlar jadvali haqiqiy holatni ko'rsatadi.
            modal.addEventListener('close', function () {
                location.reload();
            });

            // ===== Xodimni tahrirlash =====
            document.addEventListener('click', function (event) {
                var btn = event.target.closest('[data-edit-employee]');
                if (!btn) return;

                var form = document.getElementById('edit-employee-form');
                form.action = btn.dataset.updateUrl;
                document.getElementById('ee-name').value = btn.dataset.name;
                document.getElementById('ee-email').value = btn.dataset.email;
                document.getElementById('ee-role').value = btn.dataset.role;
                document.getElementById('edit-employee-modal').showModal();
            });

            document.addEventListener('ustbosh:ajax:success', function (event) {
                var form = event.detail.form;
                if (!form || form.id !== 'edit-employee-form') return;

                document.getElementById('edit-employee-modal').close();
                setTimeout(function () { location.reload(); }, 500);
            });
        })();
    </script>
    @endpush
@endsection
