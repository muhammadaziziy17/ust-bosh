@extends('layouts.app')

@section('title', __('auth.login_title'))

@section('content')
    <section class="min-h-[75vh] grid place-items-center py-16">
        <div class="w-full max-w-md px-4">
            <div class="text-center mb-6">
                <span class="inline-grid size-14 place-items-center rounded-2xl bg-accent text-accent-content font-mono font-bold text-2xl shadow-elev">U</span>
                <h1 class="font-display mt-4 text-2xl">{{ __('auth.login_title') }}</h1>
                <p class="mt-1 text-sm text-ink-soft">{{ __('auth.login_subtitle') }}</p>
                @if (session('error'))
                    <div class="mt-3 rounded-field border border-err/30 bg-err/10 px-4 py-2 text-sm font-medium text-err">
                        {{ session('error') }}
                    </div>
                @endif
            </div>

            <div class="rounded-box border border-line bg-card p-6 md:p-8 shadow-elev">
                @if ($errors->any())
                    <div class="sm-alert mb-5 rounded-field border border-err/30 bg-err/10 px-4 py-3 text-sm font-medium text-err">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}" class="space-y-4"
                      data-ajax data-loader-text="{{ __('auth.login_btn') }}...">
                    @csrf

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold">{{ __('auth.email_label') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                               class="input w-full bg-base-100" />
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold">{{ __('auth.password_label') }}</label>
                        <input id="password" name="password" type="password" required
                               class="input w-full bg-base-100" />
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="remember" class="checkbox checkbox-sm checkbox-accent" />
                        {{ __('auth.remember') }}
                    </label>

                    <button type="submit" class="btn btn-accent sm-grow w-full shadow-elev">
                        {{ __('auth.login_btn') }}
                    </button>

                    <p class="text-center text-sm">
                        <a href="{{ route('password.request') }}" class="link link-hover text-ink-soft">
                            {{ __('auth.forgot_password') }}
                        </a>
                    </p>
                </form>
            </div>

            <div class="mt-6 flex items-center justify-center text-sm">
                <a href="{{ route('landing') }}" class="link link-hover text-ink-soft">← {{ __('admin.back_to_site') }}</a>
            </div>
        </div>
    </section>
@endsection
