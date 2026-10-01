@extends('layouts.app')

@section('title', __('auth.forgot_password_title'))

@section('content')
    <section class="min-h-[75vh] grid place-items-center py-16">
        <div class="w-full max-w-md px-4">
            <div class="text-center mb-6">
                <span class="inline-grid size-14 place-items-center rounded-2xl bg-accent text-accent-content font-mono font-bold text-2xl shadow-elev">U</span>
                <h1 class="font-display mt-4 text-2xl">{{ __('auth.forgot_password_title') }}</h1>
                <p class="mt-1 text-sm text-ink-soft">{{ __('auth.forgot_password_subtitle') }}</p>
                @if (session('success'))
                    <div class="mt-3 rounded-field border border-ok/30 bg-ok/10 px-4 py-2 text-sm font-medium text-ok">
                        {{ session('success') }}
                    </div>
                @endif
            </div>

            <div class="rounded-box border border-line bg-card p-6 md:p-8 shadow-elev">
                @if ($errors->any())
                    <div class="sm-alert mb-5 rounded-field border border-err/30 bg-err/10 px-4 py-3 text-sm font-medium text-err">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="space-y-4"
                      data-ajax data-reset-on-success
                      data-loader-text="{{ __('auth.sending') }}...">
                    @csrf

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold">{{ __('auth.email_label') }}</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                               class="input w-full bg-base-100" />
                    </div>

                    <button type="submit" class="btn btn-accent sm-grow w-full shadow-elev">
                        {{ __('auth.send_reset_link') }}
                    </button>
                </form>
            </div>

            <div class="mt-6 flex items-center justify-center text-sm">
                <a href="{{ route('login') }}" class="link link-hover text-ink-soft">← {{ __('auth.back_to_login') }}</a>
            </div>
        </div>
    </section>
@endsection