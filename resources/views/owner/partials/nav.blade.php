{{--
    Do'kon egasi paneli navigatsiyasi.
    Katta ekranda — yon panel, mobilda — gorizontal yorliqlar.
--}}
@php
    $ownerNavItems = [
        ['route' => 'owner.dashboard', 'icon' => '📊', 'label' => __('owner.nav_overview')],
        ['route' => 'owner.sales.index', 'icon' => '🧾', 'label' => __('owner.nav_sales')],
        ['route' => 'owner.employees.index', 'icon' => '👥', 'label' => __('owner.nav_employees')],
        ['route' => 'owner.inventory.index', 'icon' => '📦', 'label' => __('owner.nav_inventory')],
        ['route' => 'owner.reports.index', 'icon' => '📈', 'label' => __('owner.nav_reports')],
        ['route' => 'owner.activity.index', 'icon' => '🕐', 'label' => __('owner.nav_activity')],
    ];
@endphp

{{-- Mobil: gorizontal yorliqlar --}}
<nav class="lg:hidden -mx-4 px-4 overflow-x-auto" aria-label="{{ __('owner.dashboard_title') }}">
    <div class="flex min-w-max gap-2 pb-1">
        @foreach ($ownerNavItems as $item)
            <a href="{{ route($item['route']) }}"
               class="btn btn-sm {{ request()->routeIs($item['route']) ? 'btn-accent' : 'btn-ghost' }}">
                {{ $item['icon'] }} {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>

{{-- Katta ekran: yon panel --}}
<nav class="hidden lg:block lg:sticky lg:top-20 rounded-box border border-line bg-card p-2 shadow-elev"
     aria-label="{{ __('owner.dashboard_title') }}">
    <div class="flex flex-col gap-1">
        @foreach ($ownerNavItems as $item)
            <a href="{{ route($item['route']) }}"
               class="flex items-center gap-2.5 rounded-field px-3 py-2.5 text-sm transition-colors
                      {{ request()->routeIs($item['route'])
                            ? 'bg-accent/10 text-accent font-semibold'
                            : 'text-ink-soft font-medium hover:bg-base-200 hover:text-ink' }}">
                <span aria-hidden="true">{{ $item['icon'] }}</span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
