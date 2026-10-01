Vazifa: POS uchun telefondan shtrix-kod skanerlash (Pusher’siz, InfinityFree mos).

Kontekst: Laravel 13, Blade, MySQL, kassir POS sahifasi PosController@index. Shtrix-kod product_variants.barcodeda. WebSocket/queue/cron yo‘q (InfinityFree). Polling ishlatiladi. CSP SetSecurityHeaders middleware’da.

1. Migratsiyalar: create_scanner_sessions_table va create_scanner_scans_table (yuqoridagi ustunlar; token_hash unique, (scanner_session_id, id) indeks, FK’lar cascadeOnDelete). Mavjud shops/users/product_variants bilan bog‘lash.

2. Modellar: ScannerSession (relations: shop, cashier, scans; isExpired(), isActive()), ScannerScan (relations: session, variant). ProductVariantga teskari relation shart emas.

3. Controllerlar:
- PosScannerController (auth, kassir middleware ichida): store (sessiya + token + url qaytaradi; eski yozuvlarni opportunistik tozalaydi), events (faqat o‘z sessiyasi; after id’dan katta skanlar; variant bilan eager load; barcode/variant nomi/label/narx/qoldiqni JSON qaytaradi), close.
- ScannerDeviceController (public, token): show (Blade sahifa; yaroqsiz token → 404), connect, scan (barcode → variant; found|not_found|inactive; navbatga yozish; JSON feedback), search (nom/SKU/barcode bo‘yicha, sessiya do‘koniga cheklangan, max 10 natija).

4. Routes: yuqoridagi 7 ta route; auth guruhida throttle:pos-scanner, public guruhda throttle:scanner-public. Route param {token} uchun regex cheklov.

5. Rate limitlar (AppServiceProvider): pos-scanner (kassir bo‘yicha 120/min), scanner-session (10/min), scanner-public (token+IP bo‘yicha 120/min).

6. Frontend (POS): pos/index.blade.phpga «📱 Qurilmani ulash» tugmasi va dialog: QR canvas (jsdelivr qrcode), holat matni, «Uzish». Sessiya boshlanganda har 1500ms da events?after= polling; found bo‘lsa mavjud addToCart(variantId, name, label, price, stock) chaqiriladi (savat limitlari saqlanadi), not_found/inactive bo‘lsa toast xato; after eng katta id’ga yangilanadi. Modal yopilsa close chaqiriladi. Polling faqat modal ochiq paytda; xato bo‘lsa 3 soniyada qayta urinadi.

7. Frontend (telefon): resources/views/scanner/device.blade.php — minimal, mobilga mos: holat, <video> (html5-qrcode), oxirgi skan natijasi, «Qo‘lda kiritish» maydoni va «Qidiruv» tab; @csrf bilan fetch POST. Xatolar toast orqali. Kamera ruxsati berilmasa qo‘lda kiritish ko‘rsatiladi.

8. CSP: worker-src 'self' blob: https://cdn.jsdelivr.net qo‘shish; script-srcda jsdelivr allaqachon bor. media-src kerak emas.

9. Testlar (PHPUnit feature):
- kassir sessiya yaratadi → token_hash bazada, raw token javobda;
- begona kassir eventsni ololmaydi (404);
- barcode skan found navbatga yozadi; events uni bir marta qaytaradi (after bilan takrorlanmaydi);
- not_found / inactive holatlari;
- muddati o‘tgan/yopilgan sessiya skanerlarni rad etadi;
- search faqat o‘z do‘konini ko‘radi;
- owner/warehouse scanner endpointlariga kira olmaydi;
- rate limit 429.

10. Qabul mezonlari: Pusher/websocket/cron/queue ishlatilmaydi; faqat PHP+MySQL+polling. Telefon HTTPS’da kamera bilan skanerlaydi; natija 2 soniyadan kechikmasdan savatga tushadi; token 15 daqiqada o‘ladi; barcha yangi kod pint va to‘liq test to‘plamidan o‘tadi.

Eslatma: mavjud manual qidiruv o‘zgarmaydi; yangi funksiya unga qo‘shimcha.

