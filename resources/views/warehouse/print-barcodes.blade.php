<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shtrix-kodlar — {{ $product->name }}</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 20px;
            background: #fff;
            color: #000;
        }
        .print-btn {
            display: block;
            margin: 0 auto 20px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        .barcode-item {
            text-align: center;
            border: 1px dashed #ccc;
            padding: 10px;
            border-radius: 8px;
            page-break-inside: avoid;
        }
        .barcode-item h4 {
            margin: 0 0 5px;
            font-size: 14px;
        }
        .barcode-item p {
            margin: 0 0 10px;
            font-size: 12px;
            color: #666;
        }
        .barcode-item svg {
            max-width: 100%;
            height: auto;
        }
        @media print {
            .print-btn { display: none; }
            body { padding: 0; }
            .barcode-item { border: none; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>
<body>
    <button type="button" class="print-btn" onclick="window.print()">🖨️ Chop etish</button>

    <div class="grid">
        @foreach($product->variants as $variant)
            @if($variant->barcode)
                <div class="barcode-item">
                    <h4>{{ $product->name }}</h4>
                    <p>{{ $variant->displayLabel() }} | SKU: {{ $product->sku }}</p>
                    <svg class="barcode"
                         jsbarcode-format="CODE128"
                         jsbarcode-value="{{ $variant->barcode }}"
                         jsbarcode-textmargin="0"
                         jsbarcode-fontoptions="bold">
                    </svg>
                </div>
            @endif
        @endforeach
    </div>

    <script>
        JsBarcode(".barcode").init();
    </script>
</body>
</html>
