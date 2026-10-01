<section class="relative overflow-hidden">
    <div class="absolute inset-0 sm-hero-grid sm-breathe" aria-hidden="true"></div>
    @include('landing.partials.clothes-bg')

    <div class="relative mx-auto max-w-6xl px-4 pt-10 pb-16 md:pt-24 md:pb-28">
        <div class="grid md:grid-cols-[1.15fr_.85fr] gap-10 md:gap-12 items-center">

            {{-- Chap tomon: matn --}}
            <div>

                <h1 class="sm-rise sm-rise-2 font-display sm-hero-title mt-4 sm:mt-5 text-3xl sm:text-4xl md:text-6xl leading-[1.05]">
                    {{ __('landing.hero.title') }}
                    <span class="text-accent">{{ __('landing.hero.title_accent') }}</span>
                    {{ __('landing.hero.title_suffix') }}
                </h1>

                <p class="sm-rise sm-rise-3 mt-5 max-w-xl text-base md:text-lg text-ink-soft">
                    {{ __('landing.hero.description') }}
                </p>

                <div class="sm-rise sm-rise-4 mt-6 sm:mt-8 flex flex-wrap gap-3">
                    <a href="#contact" class="btn btn-accent shadow-elev px-6 sm:px-8 btn-lg sm-hero-cta">
                        {{ __('landing.hero.cta') }}
                    </a>
                    <a href="#how" class="btn btn-outline btn-secondary btn-lg sm-grow">
                        {{ __('landing.hero.cta_secondary') }}
                    </a>
                </div>

                <div class="sm-rise sm-rise-4 sm-stats mt-8 sm:mt-10 flex flex-wrap gap-x-8 gap-y-3 text-sm">
                    <div class="sm-pop sm-pop-1 flex items-center gap-2">
                        <span class="font-mono text-2xl font-bold">4</span>
                        <span class="text-ink-soft">{{ __('landing.hero.stat_shops') }}</span>
                    </div>
                    <div class="sm-pop sm-pop-2 flex items-center gap-2">
                        <span class="font-mono text-2xl font-bold">4</span>
                        <span class="text-ink-soft">{{ __('landing.hero.stat_roles') }}</span>
                    </div>
                    <div class="sm-pop sm-pop-3 flex items-center gap-2">
                        <span class="font-mono text-2xl font-bold">10</span>
                        <span class="text-ink-soft">{{ __('landing.hero.stat_minutes') }}</span>
                    </div>
                </div>
            </div>

            {{-- O'ng tomson: mini POS kartochkasi --}} <div class="sm-rise sm-rise-3 relative hidden md:block">
                <div class="sm-float rounded-box border border-line bg-card p-5 shadow-elev rotate-2"
                    style="--float-rot: 2deg;">
                    <div class="flex items-center justify-between border-b border-line/70 pb-3">
                        <span class="text-sm font-semibold">POS · Kassa</span>
                        <span class="badge badge-sm border-0 bg-ok/15 text-ok">online</span>
                    </div>

                    <div class="mt-4 space-y-2.5 text-sm">
                        <div
                            class="flex items-center justify-between rounded-field border border-line bg-base-100 px-3 py-2.5">
                            <span class="font-medium">Futbolka oversize</span>
                            <span class="font-mono text-ink-soft">L · 12</span>
                        </div>
                        <div
                            class="flex items-center justify-between rounded-field border border-line bg-base-100 px-3 py-2.5">
                            <span class="font-medium">Jeans slim</span>
                            <span class="font-mono text-ink-soft">M · 4</span>
                        </div>
                        <div
                            class="flex items-center justify-between rounded-field border border-line bg-base-100 px-3 py-2.5">
                            <span class="font-medium">Kurtka demisezon</span>
                            <span class="font-mono text-ink-soft">XL · 2</span>
                        </div>
                        <div
                            class="flex items-center justify-between rounded-field border border-line bg-base-100 px-3 py-2.5">
                            <span class="font-medium">Krasovka urban</span>
                            <span class="font-mono text-ink-soft">42 · 6</span>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t border-line/70 pt-3">
                        <span class="text-sm text-ink-soft">Jami</span>
                        <span class="font-mono text-lg font-bold text-accent">1 240 000</span>
                    </div>
                </div>

                <div class="sm-float sm-float-slow absolute -bottom-5 -left-5 rounded-field border border-line bg-card px-4 py-3 shadow-elev -rotate-3"
                    style="--float-rot: -3deg;">
                    <div class="text-[11px] text-ink-soft">Bugungi sotuv</div>
                    <div class="font-mono text-lg font-bold text-ok">+8 450 000</div>
                </div>

                <div class="sm-float absolute -top-4 -right-3 rounded-field bg-ink px-3 py-2 shadow-elev rotate-3"
                    style="--float-rot: 3deg; animation-delay: 1.2s;">
                    <span class="font-mono text-xs text-paper">S · M · L · XL</span>
                </div>
            </div>

        </div>
    </div>
</section>
