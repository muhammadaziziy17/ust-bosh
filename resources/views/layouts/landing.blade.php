<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="savdomarkaz">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('landing.tagline') }}">
    <title>@yield('title', __('landing.brand')) — {{ __('landing.tagline') }}</title>

    <!-- Tailwind + DaisyUI 5 (CDN, versiya pin qilingan) -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5.7.22/daisyui.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.3"></script>

    <!-- Brend ranglarini Tailwind tokenlari sifatida ro'yxatdan o'tkazamiz (bg-ok, text-err, bg-card ishlashi uchun) -->
    <style type="text/tailwindcss">
        body {
            scroll-behavior: smooth;
        }

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
        /* Font utility (Tailwind CDN temani bilmasligi uchun) */
        body {
            font-family: 'Archivo', system-ui, sans-serif;
        }

        .font-mono {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
        }

        .font-display {
            font-family: 'Archivo', system-ui, sans-serif;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .shadow-elev {
            box-shadow: 0 1px 2px rgba(31, 42, 36, .05), 0 12px 32px -16px rgba(31, 42, 36, .25);
        }
    </style>

    <!-- Custom theme + shriftlar -->
    <link href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/loader.css') }}?v={{ filemtime(public_path('css/loader.css')) }}" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap"
        rel="stylesheet">
</head>

<body class="bg-base-100 text-base-content min-h-screen flex flex-col antialiased">
    <header id="site-header" class="site-header sticky top-0 z-40 border-b border-line/60 backdrop-blur bg-base-100/85">
        <div class="header-inner mx-auto max-w-6xl px-4 flex items-center justify-between gap-4">
            <a href="{{ route('landing') }}" class="flex items-center gap-2.5 group">
                <span
                    class="brand-mark grid size-9 place-items-center rounded-xl bg-accent text-accent-content font-mono font-bold text-lg shadow-elev">U</span>
                <span class="font-extrabold tracking-tight text-lg">{{ __('landing.brand') }}</span>
            </a>
            <nav class="hidden md:flex items-center gap-1 text-sm font-medium">
                <a href="#features" class="nav-link px-3 py-2 rounded-field hover:bg-base-200 transition-colors"
                    data-spy="features">{{ __('landing.nav.features') }}</a>
                <a href="#how" class="nav-link px-3 py-2 rounded-field hover:bg-base-200 transition-colors"
                    data-spy="how">{{ __('landing.nav.how') }}</a>
                <a href="#contact" class="nav-link px-3 py-2 rounded-field hover:bg-base-200 transition-colors"
                    data-spy="contact">{{ __('landing.nav.contact') }}</a>
            </nav>
            <div class="flex items-center gap-2">
                <a href="{{ route('locale.switch', app()->getLocale() === 'uz' ? 'ru' : 'uz') }}"
                    class="btn btn-ghost btn-sm font-mono hidden sm:inline-flex">{{ __('landing.lang_switch') }}</a>
                <a href="{{ route('login') }}"
                    class="btn btn-accent btn-sm hidden sm:inline-flex">{{ __('landing.nav.login') }}</a>

                {{-- Mobil hamburger --}}
                <button type="button" class="btn btn-ghost btn-sm md:hidden" aria-label="Menyu"
                    onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Mobil menyu --}}
        <div id="mobile-menu" class="hidden md:hidden border-t border-line/60 bg-base-100">
            <nav class="mx-auto max-w-6xl px-4 py-3 flex flex-col gap-1 text-sm font-medium">
                <a href="#features" class="px-3 py-2.5 rounded-field hover:bg-base-200"
                    onclick="document.getElementById('mobile-menu').classList.add('hidden')">{{ __('landing.nav.features') }}</a>
                <a href="#how" class="px-3 py-2.5 rounded-field hover:bg-base-200"
                    onclick="document.getElementById('mobile-menu').classList.add('hidden')">{{ __('landing.nav.how') }}</a>
                <a href="#contact" class="px-3 py-2.5 rounded-field hover:bg-base-200"
                    onclick="document.getElementById('mobile-menu').classList.add('hidden')">{{ __('landing.nav.contact') }}</a>
                <a href="{{ route('locale.switch', app()->getLocale() === 'uz' ? 'ru' : 'uz') }}"
                    class="px-3 py-2.5 rounded-field hover:bg-base-200 font-mono sm:hidden">{{ __('landing.lang_switch') }}</a>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="border-t border-line/60 py-8">
        <div
            class="mx-auto max-w-6xl px-4 flex flex-col md:flex-row items-center justify-between gap-3 text-sm text-base-content/60">
            <div class="flex items-center gap-2">
                <span
                    class="grid size-6 place-items-center rounded-lg bg-accent text-accent-content font-mono font-bold text-xs">U</span>
                <span class="font-semibold">{{ __('landing.brand') }}</span>
                <span>© {{ date('Y') }} — {{ __('landing.footer.rights') }}</span>
            </div>
            <span>{{ __('landing.footer.made_for') }}</span>
        </div>
    </footer>

    <script>
        (function() {
            var header = document.getElementById('site-header');

            // --- Navbar scroll'da siqilishi ---
            var onScroll = function() {
                header.classList.toggle('is-scrolled', window.scrollY > 8);
            };
            window.addEventListener('scroll', onScroll, {
                passive: true
            });
            onScroll();

            // --- Scroll-reveal: elementlar ko'ringanda yumshoq chiqadi ---
            var revealEls = document.querySelectorAll('.sm-reveal');
            if ('IntersectionObserver' in window) {
                var revealObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            revealObserver.unobserve(entry.target);
                        }
                    });
                }, {
                    threshold: 0.15,
                    rootMargin: '0px 0px -40px 0px'
                });
                revealEls.forEach(function(el) {
                    revealObserver.observe(el);
                });
            } else {
                revealEls.forEach(function(el) {
                    el.classList.add('is-visible');
                });
            }

            // --- Scrollspy: faol bo'lim nav'da belgilanadi ---
            var spyLinks = document.querySelectorAll('.nav-link[data-spy]');
            if (spyLinks.length && 'IntersectionObserver' in window) {
                var sections = {};
                spyLinks.forEach(function(link) {
                    var section = document.getElementById(link.getAttribute('data-spy'));
                    if (section) sections[link.getAttribute('data-spy')] = link;
                });

                var spyObserver = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        var link = sections[entry.target.id];
                        if (!link) return;
                        if (entry.isIntersecting) {
                            spyLinks.forEach(function(l) {
                                l.classList.remove('is-active');
                            });
                            link.classList.add('is-active');
                        }
                    });
                }, {
                    rootMargin: '-40% 0px -55% 0px'
                });

                Object.keys(sections).forEach(function(id) {
                    var section = document.getElementById(id);
                    if (section) spyObserver.observe(section);
                });
            }
        })();
    </script>

    @stack('scripts')

    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>

</html>
