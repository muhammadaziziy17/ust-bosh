<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ishonchli proksi manzillari
    |--------------------------------------------------------------------------
    |
    | Ngrok/Herd kabi lokal tunnel proksilari X-Forwarded-Host va
    | X-Forwarded-Proto headerlarini yuboradi. Ular ishonchli bo'lmasa,
    | asset()/route() manzillari https://ust-bosh.test deb chiqadi va
    | ngrok orqali ochilganda CSS/JS yuklanmaydi.
    |
    | Manzillar vergul bilan ajratiladi (masalan: "127.0.0.1,::1").
    | Faqat shu manzillardan kelgan so'rovlardagi X-Forwarded-* headerlarga
    | ishoniladi, shuning uchun tashqi mijoz ularni soxtalashtira olmaydi.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
