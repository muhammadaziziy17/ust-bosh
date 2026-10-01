<section class="py-20" id="features">
    <div class="mx-auto max-w-6xl px-4">
        <div class="max-w-2xl">
            <p class="text-sm font-bold uppercase tracking-[.2em] text-accent">
                {{ __('landing.features.kicker') }}
            </p>
            <h2 class="font-display mt-2 text-3xl md:text-4xl">
                {{ __('landing.features.title') }}
            </h2>
            <p class="mt-3 text-ink-soft">
                {{ __('landing.features.subtitle') }}
            </p>
        </div>

        <div class="mt-10 grid gap-5 sm:grid-cols-2">
            @foreach ([0, 1, 2, 3] as $i)
                <div class="sm-reveal rounded-box border border-line bg-card p-6 shadow-elev transition-all duration-300 hover:-translate-y-1 hover:shadow-lg" style="--reveal-delay: {{ $i * 0.12 }}s">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-field bg-accent/10 font-mono text-sm font-bold text-accent">
                            {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <h3 class="font-display text-lg">
                            {{ __('landing.features.items.' . $i . '.title') }}
                        </h3>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-ink-soft">
                        {{ __('landing.features.items.' . $i . '.text') }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</section>
