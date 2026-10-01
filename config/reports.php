<?php

return [
    /*
    |------------------------------------------------------------------
    | Excel hisoboti chegarasi
    |------------------------------------------------------------------
    | Sotuvlar varag'iga tushadigan maksimal yozuvlar soni. Davrda undan
    | ko'p sotuv bo'lsa, faqat eng oxirgi yozuvlar eksport qilinadi va
    | varaqda ogohlantirish ko'rsatiladi (xotira sarfini cheklash uchun).
    */
    'max_export_sales' => (int) env('REPORT_MAX_EXPORT_SALES', 5000),

    /*
    |------------------------------------------------------------------
    | Boshqa varaqlar chegaralari
    |------------------------------------------------------------------
    | Mahsulotlar varaqidagi eng ko'p tushum keltirgan yozuvlar va ombor
    | varaqidagi kam qoldiqli variantlar soni ham cheklanadi.
    */
    'max_export_products' => (int) env('REPORT_MAX_EXPORT_PRODUCTS', 500),
    'max_export_low_stock' => (int) env('REPORT_MAX_EXPORT_LOW_STOCK', 500),
];
