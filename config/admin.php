<?php

return [
    /*
    |------------------------------------------------------------------
    | Super Admin (database-backed)
    |------------------------------------------------------------------
    | .env faylida ADMIN_EMAIL va ADMIN_PASSWORD orqali boshqariladi.
    | Parol hech qachon kodga yozilmaydi va DB'da hash'lanadi.
    |
    | Agar users jadvalida super_admin rolidagi foydalanuvchi bo'lmasa,
    | admin login paytida ADMIN_EMAIL/ADMIN_PASSWORD asosida avtomatik
    | yaratiladi (birinchi login).
    */
    'email' => env('ADMIN_EMAIL', ''),
    'password' => env('ADMIN_PASSWORD', ''),
];
