<section class="py-20" id="contact">
    <div class="mx-auto max-w-6xl px-4">
        <div class="grid gap-10 md:grid-cols-2 items-start">

            {{-- Chap: matn --}}
            <div class="sm-reveal-left sm-reveal">
                <p class="text-sm font-bold uppercase tracking-[.2em] text-accent">
                    {{ __('landing.form.kicker') }}
                </p>
                <h2 class="font-display mt-2 text-3xl md:text-4xl">
                    {{ __('landing.form.title') }}
                </h2>
                <p class="mt-3 text-ink-soft">
                    {{ __('landing.form.subtitle') }}
                </p>

                <div class="mt-8 rounded-box border border-line bg-card p-5 shadow-elev">
                    <div class="flex items-center gap-3 text-sm">
                        <span class="grid size-10 shrink-0 place-items-center rounded-field bg-ok/10 text-ok">✓</span>
                        <p class="text-ink-soft">{{ __('landing.form.privacy') }}</p>
                    </div>
                </div>
            </div>

            {{-- O'ng: forma --}}
            <div class="sm-reveal sm-reveal-right rounded-box border border-line bg-card p-6 md:p-8 shadow-elev">
                @if (session('success'))
                    <div
                        class="sm-alert mb-5 rounded-field border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('duplicate'))
                    <div
                        class="sm-alert mb-5 rounded-field border border-accent/30 bg-accent/10 px-4 py-3 text-sm font-medium text-accent">
                        {{ session('duplicate') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div
                        class="sm-alert mb-5 rounded-field border border-err/30 bg-err/10 px-4 py-3 text-sm font-medium text-err">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('applications.store') }}" class="space-y-4"
                      data-ajax data-reset-on-success data-result-modal
                      data-loader-text="{{ __('landing.form.sending') }}"
                      data-modal-title-success="{{ __('admin.modal.title_success') }}"
                      data-modal-title-duplicate="{{ __('admin.modal.duplicate_title') }}"
                      data-modal-title-error="{{ __('admin.modal.title_error') }}">
                    @csrf

                    <div>
                        <label for="shop_name" class="mb-1.5 block text-sm font-semibold">
                            {{ __('landing.form.shop_name') }}
                        </label>
                        <input id="shop_name" name="shop_name" type="text" value="{{ old('shop_name') }}"
                            placeholder="{{ __('landing.form.shop_name_placeholder') }}" required
                            class="input w-full bg-base-100" />
                    </div>

                    <div>
                        <label for="contact_name" class="mb-1.5 block text-sm font-semibold">
                            {{ __('landing.form.contact_name') }}
                        </label>
                        <input id="contact_name" name="contact_name" type="text" value="{{ old('contact_name') }}"
                            placeholder="{{ __('landing.form.contact_name_placeholder') }}" required
                            class="input w-full bg-base-100" />
                    </div>

                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold">
                            {{ __('landing.form.email') }}
                        </label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}"
                            placeholder="{{ __('landing.form.email_placeholder') }}" required
                            class="input w-full bg-base-100" />
                    </div>

                    <div>
                        <label for="phone" class="mb-1.5 block text-sm font-semibold">
                            {{ __('landing.form.phone') }}
                        </label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}"
                            placeholder="{{ __('landing.form.phone_placeholder') }}" required
                            class="input w-full bg-base-100" />
                    </div>

                    <div>
                        <label for="message" class="mb-1.5 block text-sm font-semibold">
                            {{ __('landing.form.message') }}
                        </label>
                        <textarea id="message" name="message" rows="3"
                            placeholder="{{ __('landing.form.message_placeholder') }}" class="textarea w-full resize-y bg-base-100">{{ old('message') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-accent btn-lg sm-grow w-full shadow-elev">
                        {{ __('landing.form.submit') }}
                    </button>
                </form>
            </div>

        </div>
    </div>
</section>
