@extends('layouts.admin')

@section('title', __('admin.login_title'))

@section('content')
    <section class="relative min-h-[70vh] grid place-items-center py-16">
        <div class="absolute inset-0 sm-hero-grid sm-breathe" aria-hidden="true"></div>

        <div class="relative w-full max-w-md px-4">
            <div class="text-center mb-6">
                <span class="brand-mark inline-grid size-14 place-items-center rounded-2xl bg-accent text-accent-content font-mono font-bold text-2xl shadow-elev">U</span>
                <h1 class="font-display mt-4 text-2xl">{{ __('admin.login_title') }}</h1>
                <p class="mt-1 text-sm text-ink-soft">{{ __('admin.login_subtitle') }}</p>
            </div>

            <div class="rounded-box border border-line bg-card p-6 md:p-8 shadow-elev">
                @if (session('success'))
                    <div class="sm-alert mb-5 rounded-field border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="sm-alert mb-5 rounded-field border border-err/30 bg-err/10 px-4 py-3 text-sm font-medium text-err">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4"
                      data-ajax data-loader-text="{{ __('admin.login_btn') }}...">
                    @csrf

                    <div>
                        <label for="login" class="mb-1.5 block text-sm font-semibold">{{ __('admin.login') }}</label>
                        <input id="login" name="login" type="email" value="{{ old('login') }}" required autofocus
                               class="input w-full bg-base-100" />
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold">{{ __('admin.password') }}</label>
                        <input id="password" name="password" type="password" required
                               class="input w-full bg-base-100" />
                    </div>

                    <button type="submit" class="btn btn-accent sm-grow w-full shadow-elev">
                        {{ __('admin.login_btn') }}
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center">
                <a href="{{ route('landing') }}" class="link link-hover text-sm text-ink-soft">
                    ← {{ __('admin.back_to_site') }}
                </a>
            </p>
        </div>
    </section>
@endsection
