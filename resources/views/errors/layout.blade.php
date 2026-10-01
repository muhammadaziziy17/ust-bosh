{{--
    UstBosh — xato sahifalari layouti
    Brend: "savdomarkaz" temasi — issiq qog'oz fon, siyoh-yashil, xantal-oltin aksent.
    Dizayn: hero'dagi grid naqsh + suzuvchi kiyim-ikonkalari + kassa "cheki" kartasi.
    Har bir sahifa @extends('errors.layout', ['code' => 404]) kabi kod bilan chaqiriladi.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="savdomarkaz">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('errors.page_title', ['code' => $code]) }} — {{ __('landing.brand') }}</title>

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

        /* ---- POS chek (receipt) detallari ---- */

        /* Shtrix-kod: turli kenglikdagi chiziqlar */
        .receipt-barcode {
            background: repeating-linear-gradient(
                90deg,
                var(--sm-ink) 0 2px, transparent 2px 4px,
                var(--sm-ink) 4px 7px, transparent 7px 9px,
                var(--sm-ink) 9px 10px, transparent 10px 15px
            );
            opacity: .8;
        }

        /* Chekning yirtiq (zigzag) pastki cheti */
        .receipt-zigzag {
            height: 10px;
            background: conic-gradient(from -45deg at 50% 100%, #FDFBF6 0 90deg, transparent 90deg) 50% / 16px 100% repeat-x;
        }

        /* Katta "ghost" kod raqami — matn ortida suzib turadi */
        .error-ghost {
            -webkit-text-stroke: 2px rgba(31, 42, 36, .12);
            color: transparent;
        }
    </style>

    <link href="{{ asset('css/theme.css') }}?v={{ filemtime(public_path('css/theme.css')) }}" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap"
        rel="stylesheet">
</head>

<body class="bg-base-100 text-base-content min-h-screen antialiased">
    @php
        $key = (string) $code;
        $item = __('errors.items.' . $key);

        // Aniq kalit topilmasa (masalan, yangi kod qo'shilsa) — 500 matni bilan ko'rsatamiz
        if (! is_array($item)) {
            $item = __('errors.items.500');
        }

        // Xato turiga qarakandagi asosiy/ikkinchi tugmalar
        $primary = match ((int) $code) {
            401 => 'login',
            419, 429, 503 => 'retry',
            default => 'home',
        };
        $secondary = $primary === 'home' ? 'back' : 'home';
    @endphp

    <div class="relative min-h-screen overflow-hidden flex flex-col">

        {{-- Fon: hero'dagi grid naqsh + suzuvchi kiyim-ikonkalari --}}
        <div class="absolute inset-0 sm-hero-grid" aria-hidden="true"></div>
        <div class="clothes-bg" aria-hidden="true">
            {{-- Futbolka --}}
            <div class="clothes-icon is-accent" style="top: 10%; left: 5%; width: 76px; height: 76px; --icon-opacity: .09; --drift-duration: 18s; --icon-rot: -8deg; animation-delay: -3s;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 2s-1.5 2-4 2-4-2-4-2L2.5 5.5 5 10l2-1v13h10V9l2 1 2.5-4.5L16 2z"/></svg>
            </div>
            {{-- Shimgich (hanger) --}}
            <div class="clothes-icon" style="top: 14%; left: 86%; width: 68px; height: 68px; --icon-opacity: .08; --drift-duration: 22s; --icon-rot: 12deg; animation-delay: -7s;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6a2 2 0 1 1 2-2m-2 2 9.5 6.2a1.5 1.5 0 0 1-.8 2.8H3.3a1.5 1.5 0 0 1-.8-2.8L12 6z"/></svg>
            </div>
            {{-- Shalya (pants) --}}
            <div class="clothes-icon" style="top: 62%; left: 4%; width: 64px; height: 64px; --icon-opacity: .07; --drift-duration: 20s; --icon-rot: 6deg; animation-delay: -11s;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h8l1 5-1 13h-3.5l-.5-9h-.5L11 21H7.5L7 8l1-5z"/></svg>
            </div>
            {{-- Krasovka (sneaker) --}}
            <div class="clothes-icon" style="top: 78%; left: 84%; width: 70px; height: 70px; --icon-opacity: .08; --drift-duration: 19s; --icon-rot: -12deg; animation-delay: -9s;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 17h20v2H2z"/><path d="M2 17c0-2 1-3 3-3l4-4 3 2 2-2c3 3 8 4 8 7"/></svg>
            </div>
            {{-- Paypoq (sock) --}}
            <div class="clothes-icon is-accent" style="top: 86%; left: 30%; width: 54px; height: 54px; --icon-opacity: .09; --drift-duration: 17s; --icon-rot: 10deg; animation-delay: -16s;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3h6v10l3 3a3 3 0 1 1-4 4l-5-5V3z"/></svg>
            </div>
            {{-- Kupchuk (cap) --}}
            <div class="clothes-icon" style="top: 40%; left: 92%; width: 60px; height: 60px; --icon-opacity: .07; --drift-duration: 23s; --icon-rot: 15deg; animation-delay: -14s;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15a8 8 0 0 1 16 0"/><path d="M2 15h20v2H2z"/><path d="M12 7V4"/></svg>
            </div>
        </div>

        {{-- Yuqori panel: brend + til --}}
        <header class="relative z-10 w-full">
            <div class="mx-auto max-w-6xl px-4 py-5 flex items-center justify-between gap-4">
                <a href="{{ route('landing') }}" class="flex items-center gap-2.5 group">
                    <span class="brand-mark grid size-9 place-items-center rounded-xl bg-accent text-accent-content font-mono font-bold text-lg shadow-elev">U</span>
                    <span class="font-extrabold tracking-tight text-lg">{{ __('landing.brand') }}</span>
                </a>
                {{-- Til almashtirganda foydalanuvchi SHU xato sahifasida qoladi (back parametri orqali) --}}
                <a href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'uz' ? 'ru' : 'uz', 'back' => request()->getRequestUri()]) }}"
                    class="btn btn-ghost btn-sm font-mono">{{ __('landing.lang_switch') }}</a>
            </div>
        </header>

        {{-- Asosiy kontent: chapda matn, o'ngda POS chek --}}
        <main class="relative z-10 flex-1 flex items-center justify-center px-4 py-10 md:py-14">
            <div class="w-full max-w-4xl grid lg:grid-cols-[1.15fr_.85fr] gap-10 lg:gap-14 items-center">

                <section class="relative max-w-xl mx-auto lg:mx-0 text-center lg:text-left">
                    {{-- Orqadagi katta "ghost" kod raqami --}}
                    <span class="error-ghost pointer-events-none absolute -top-14 -left-2 font-display text-[7rem] md:text-[9rem] leading-none select-none hidden sm:block"
                        aria-hidden="true">{{ $code }}</span>

                    <div class="relative">
                        <div class="sm-rise sm-rise-1 inline-flex items-center gap-2 rounded-field border border-err/25 bg-err/10 px-3 py-1.5 font-mono text-xs font-semibold text-err">
                            <span class="size-1.5 rounded-full bg-err animate-pulse"></span>
                            {{ $item['kicker'] }}
                        </div>

                        <h1 class="sm-rise sm-rise-2 font-display mt-4 text-3xl sm:text-4xl md:text-5xl leading-[1.08]">
                            {{ $item['title'] }}
                        </h1>

                        <p class="sm-rise sm-rise-3 mt-4 text-base md:text-lg text-ink-soft">
                            {{ $item['text'] }}
                        </p>

                        <p class="sm-rise sm-rise-3 mt-3 text-sm text-ink-soft/80">
                            <span class="font-semibold">{{ __('errors.hint.label') }}</span> {{ $item['note'] }}
                        </p>

                        <div class="sm-rise sm-rise-4 mt-8 flex flex-wrap justify-center lg:justify-start gap-3">
                            @if ($primary === 'login')
                                <a href="{{ route('login') }}" class="btn btn-accent sm-grow shadow-elev px-6">
                                    {{ __('errors.actions.login') }}
                                </a>
                            @elseif ($primary === 'retry')
                                <button type="button" onclick="location.reload()"
                                    class="btn btn-accent sm-grow shadow-elev px-6">
                                    {{ __('errors.actions.retry') }}
                                </button>
                            @else
                                <a href="{{ route('landing') }}" class="btn btn-accent sm-grow shadow-elev px-6">
                                    {{ __('errors.actions.home') }}
                                </a>
                            @endif

                            @if ($secondary === 'back')
                                <a href="{{ url()->previous('/') }}" class="btn btn-outline btn-secondary sm-grow">
                                    ← {{ __('errors.actions.back') }}
                                </a>
                            @else
                                <a href="{{ route('landing') }}" class="btn btn-outline btn-secondary sm-grow">
                                    {{ __('errors.actions.home') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- POS chek: xato "sotilmagan" tovar kabi rasmiylashtirilgan --}}
                <aside class="hidden lg:flex justify-center" aria-hidden="true">
                    <div class="sm-float relative w-full max-w-[300px] rotate-2" style="--float-rot: 2deg;">
                        <div class="rounded-t-box bg-card shadow-elev overflow-hidden">
                            <div class="bg-ink text-paper px-5 py-3 flex items-center justify-between">
                                <span class="font-mono text-[11px] tracking-[0.2em] uppercase">{{ __('errors.receipt.service') }}</span>
                                <span class="font-mono text-[11px] opacity-80">#{{ $code }}</span>
                            </div>

                            <div class="px-5 py-4 font-mono text-sm">
                                <div class="text-center font-bold tracking-wide">{{ __('errors.receipt.title') }}</div>
                                <div class="border-b border-dashed border-line my-3"></div>

                                <div class="space-y-2">
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-ink-soft">{{ __('errors.receipt.date') }}</span>
                                        <span>{{ now()->format('d.m.Y H:i') }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-ink-soft">{{ __('errors.receipt.status') }}</span>
                                        <span class="inline-flex items-center gap-1.5 rounded-field border-0 bg-err/10 text-err px-2 py-0.5 text-xs font-bold">
                                            <span class="size-1.5 rounded-full bg-err"></span>
                                            {{ $code }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-ink-soft">{{ __('errors.receipt.payment') }}</span>
                                        <span>{{ __('errors.receipt.payment_value') }}</span>
                                    </div>
                                </div>

                                <div class="border-b border-dashed border-line my-3"></div>
                                <div class="text-center text-xs text-ink-soft leading-snug">{{ $item['kicker'] }}</div>

                                <div class="receipt-barcode h-10 mt-4"></div>
                                <div class="text-center text-[10px] tracking-[0.35em] mt-1.5 text-ink-soft">USTBOSH-{{ $code }}</div>
                            </div>
                        </div>
                        <div class="receipt-zigzag"></div>
                    </div>
                </aside>

            </div>
        </main>

        {{-- Pastki panel --}}
        <footer class="relative z-10 pb-6 px-4">
            <div class="mx-auto max-w-6xl flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-3 text-xs text-ink-soft/70">
                <span>{{ __('landing.brand') }} © {{ date('Y') }}</span>
                <span class="hidden sm:inline text-line">·</span>
                <a href="{{ route('landing') }}#contact" class="link link-hover text-ink-soft hover:text-accent">
                    {{ __('errors.help.label') }} {{ __('errors.help.link') }}
                </a>
            </div>
        </footer>
    </div>
</body>

</html>
