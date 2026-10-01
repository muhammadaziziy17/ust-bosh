@extends('layouts.admin')

@section('title', __('admin.shop_product_edit_title').' — '.$product->name)

@section('content')
    <section class="py-8">
        <div class="mx-auto max-w-3xl px-4">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="font-display text-2xl">{{ __('admin.shop_product_edit_title') }}</h1>
                    <p class="mt-1 text-sm text-ink-soft">{{ $shop->name }} · {{ $product->sku }}</p>
                </div>
                <a href="{{ route('admin.shops.show', $shop) }}" class="btn btn-ghost btn-sm">← {{ __('admin.shop_detail_title') }}</a>
            </div>

            @if ($errors->any())
                <div class="sm-alert mt-6 rounded-field border border-err/30 bg-err/10 px-4 py-3 text-sm font-medium text-err">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="mt-6 rounded-box border border-line bg-card p-5 shadow-elev">
                <form method="POST" action="{{ route('admin.shops.products.update', [$shop, $product]) }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="product-name" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_name') }}</label>
                        <input id="product-name" name="name" type="text" required maxlength="255"
                            value="{{ old('name', $product->name) }}" class="input w-full bg-base-100" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="product-sku" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_sku') }}</label>
                            <input id="product-sku" name="sku" type="text" required maxlength="64"
                                value="{{ old('sku', $product->sku) }}" class="input w-full bg-base-100 font-mono" />
                        </div>
                        <div>
                            <label for="product-category" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_category') }}</label>
                            <input id="product-category" name="category" type="text" maxlength="100"
                                value="{{ old('category', $product->category) }}" class="input w-full bg-base-100" />
                        </div>
                    </div>

                    <div>
                        <label for="product-price" class="mb-1.5 block text-sm font-semibold">{{ __('warehouse.product_price') }}</label>
                        <input id="product-price" name="sale_price" type="number" required min="0" step="0.01"
                            value="{{ old('sale_price', $product->sale_price) }}" class="input w-full bg-base-100 font-mono" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0" />
                        <input type="checkbox" id="product-active" name="is_active" value="1"
                            @checked(old('is_active', $product->is_active))
                            class="checkbox checkbox-sm checkbox-accent" />
                        <label for="product-active" class="text-sm">{{ __('warehouse.product_active') }}</label>
                    </div>

                    <div>
                        <h2 class="text-sm font-bold">{{ __('warehouse.variants_section') }}</h2>
                        <p class="mt-0.5 text-xs text-ink-soft">{{ __('admin.variants_readonly_hint') }}</p>
                        <div class="mt-3 rounded-field border border-line bg-base-100/60 divide-y divide-line/60">
                            @foreach ($product->variants as $variant)
                                <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                                    <span class="font-semibold">{{ $variant->displayLabel() }}</span>
                                    <span class="font-mono text-xs text-ink-soft">
                                        {{ __('warehouse.table_stock') }}: {{ $variant->stock_on_hand }} ·
                                        {{ $variant->is_active ? __('warehouse.status_active') : __('warehouse.status_inactive') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <button type="submit" class="btn btn-accent">{{ __('warehouse.product_save') }}</button>
                        <a href="{{ route('admin.shops.show', $shop) }}" class="btn btn-ghost">{{ __('admin.cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection
