<section class="py-20 border-y border-line/60 bg-card/60" id="how">
    <div class="mx-auto max-w-6xl px-4">
        <div class="max-w-2xl">
            <p class="text-sm font-bold uppercase tracking-[.2em] text-accent">
                {{ __('landing.steps.kicker') }}
            </p>
            <h2 class="font-display mt-2 text-3xl md:text-4xl">
                {{ __('landing.steps.title') }}
            </h2>
            <p class="mt-3 text-ink-soft">
                {{ __('landing.steps.subtitle') }}
            </p>
        </div>

        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach ([0, 1, 2] as $i)
                <div class="sm-reveal relative rounded-box border border-line bg-card p-6 shadow-elev" style="--reveal-delay: {{ $i * 0.15 }}s">
                    <span class="font-mono text-4xl font-bold text-accent/25">
                        {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                    </span>
                    <h3 class="font-display mt-2 text-lg">
                        {{ __('landing.steps.items.' . $i . '.title') }}
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                        {{ __('landing.steps.items.' . $i . '.text') }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</section>
