<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="savdomarkaz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('admin.dashboard_title')) — UstBosh Admin</title>

    <!-- Tailwind + DaisyUI 5 (CDN, versiya pin qilingan) -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5.7.22/daisyui.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.3"></script>

    <!-- Brend rang tokenlari -->
    <style type="text/tailwindcss">
        @theme {
            --color-card: #FDFBF6;
            --color-line: #DCD2BE;
            --color-ok: #3E7C59;
            --color-err: #B23A3A;
            --color-ink: #1F2A24;
            --color-ink-soft: #5A6660;
            --color-paper: #F6F1E7;
        }
    </style>

    <style>
        body { font-family: 'Archivo', system-ui, sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
        .font-display { font-family: 'Archivo', system-ui, sans-serif; font-weight: 800; letter-spacing: -0.03em; }
        .shadow-elev { box-shadow: 0 1px 2px rgba(31,42,36,.05), 0 12px 32px -16px rgba(31,42,36,.25); }
    </style>

    <link href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/loader.css') }}?v={{ filemtime(public_path('css/loader.css')) }}" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
</head>
<body class="bg-base-100 text-base-content min-h-screen flex flex-col antialiased">

    {{-- Admin sarlavhasi: faqat logotip, panel nomi va chiqish --}}
    @php
        $adminLocale = app()->getLocale() === 'uz' ? 'ru' : 'uz';
    @endphp
    <header class="sticky top-0 z-40 border-b border-line/60 bg-base-100/95 backdrop-blur">
        <div class="mx-auto max-w-6xl px-4 h-14 flex items-center justify-between gap-4">
            <div class="flex items-center gap-2.5">
                <span class="grid size-8 place-items-center rounded-lg bg-accent text-accent-content font-mono font-bold shadow-elev">U</span>
                <span class="font-extrabold tracking-tight">UstBosh</span>
                <span class="badge badge-sm border-0 bg-accent/15 text-accent font-semibold">Admin</span>
            </div>

            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-sm">{{ __('admin.nav_dashboard') }}</a>
                    <a href="{{ route('admin.audit.index') }}" class="btn btn-ghost btn-sm">{{ __('admin.nav_audit') }}</a>
                @endauth
                <a href="{{ route('locale.switch', ['locale' => $adminLocale, 'back' => request()->getRequestUri()]) }}"
                   class="btn btn-ghost btn-sm font-mono">
                    {{ __('landing.lang_switch') }}
                </a>
                <a href="{{ route('landing') }}" class="btn btn-ghost btn-sm">{{ __('admin.back_to_site') }}</a>
                @auth
                    <form method="POST" action="{{ route('admin.logout') }}" data-confirm="{{ __('admin.confirm_logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-secondary btn-sm">{{ __('admin.logout') }}</button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="border-t border-line/60 py-4">
        <div class="mx-auto max-w-6xl px-4 text-center text-xs text-ink-soft">
            UstBosh Admin © {{ date('Y') }}
        </div>
    </footer>

    @stack('scripts')

    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
