{{--
    Do'kon egasi sahifalarining sarlavhasi:
    nom + do'kon holati + tezkor amallar.
    Kutilgan o'zgaruvchilar: $title, $subtitle (ixtiyoriy), $shop.
--}}
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="font-display text-2xl md:text-3xl">{{ $title }}</h1>
        @if (! empty($subtitle))
            <p class="mt-1 text-sm text-ink-soft">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <div class="rounded-field border border-line bg-card px-4 py-2 shadow-elev">
            <span class="text-xs text-ink-soft">{{ __('owner.my_shop') }}:</span>
            <span class="font-semibold ml-1">{{ $shop?->name ?? '—' }}</span>
            @if ($shop?->status === \App\Models\Shop::STATUS_ACTIVE)
                <span class="badge badge-sm border-0 bg-ok/15 text-ok ml-2">{{ __('owner.shop_active') }}</span>
            @elseif ($shop?->status === \App\Models\Shop::STATUS_SUSPENDED)
                <span class="badge badge-sm border-0 bg-err/15 text-err ml-2">{{ __('owner.shop_suspended') }}</span>
            @else
                <span class="badge badge-sm border-0 bg-accent/15 text-accent ml-2">{{ __('owner.shop_pending') }}</span>
            @endif
        </div>
        <a href="{{ route('password.change') }}" class="btn btn-ghost btn-sm">
            🔑 {{ __('owner.quick_change_password') }}
        </a>
        <a href="{{ route('landing') }}" class="btn btn-ghost btn-sm">
            {{ __('admin.back_to_site') }}
        </a>
    </div>
</div>

@if (session('success'))
    <div class="sm-alert mt-6 rounded-field border border-ok/30 bg-ok/10 px-4 py-3 text-sm font-medium text-ok">
        {{ session('success') }}
    </div>
@endif
