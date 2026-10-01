<?php

/*
|--------------------------------------------------------------------------
| Xato sahifalari (uz)
|--------------------------------------------------------------------------
| Har bir xato sodda va tushunarli tilda tushuntiriladi: nima bo'ldi,
| nima uchun bo'ldi va foydalanuvchi keyin nima qilishi mumkin.
*/

return [
    'page_title' => 'Xatolik — :code',

    'meta' => [
        '404' => 'Sahifa topilmadi',
        '403' => 'Ruxsat yo‘q',
        '401' => 'Avval tizimga kirish kerak',
        '419' => 'Sahifa muddati tugadi',
        '429' => 'Juda ko‘p so‘rov',
        '500' => 'Server xatosi',
        '503' => 'Texnik tanaffus',
    ],

    'items' => [
        '404' => [
            'kicker' => '404 — sahifa topilmadi',
            'title' => 'Bu manzilda sahifa yo‘q',
            'text' => 'Ehtimol, sahifa boshqa joyga ko‘chgan yoki manzil xato yozilgan. Quyidagi tugmalar orqali davom eting.',
            'note' => 'Manzilni qayta tekshirib ko‘ring.',
        ],
        '403' => [
            'kicker' => '403 — ruxsat yo‘q',
            'title' => 'Bu bo‘limga ruxsatingiz yo‘q',
            'text' => 'Bu sahifa faqat boshqa rol uchun ochilgan. Agar sizga haqiqatan kerak bo‘lsa, do‘kon egasi yoki administratorga murojaat qiling.',
            'note' => 'Har bir xodim o‘z bo‘limida ishlaydi.',
        ],
        '401' => [
            'kicker' => '401 — tizimga kirish kerak',
            'title' => 'Avval tizimga kiring',
            'text' => 'Bu bo‘lim faqat shaxsiy kabinetga kirgan foydalanuvchilar uchun. Login va parolingiz bilan kirsangiz, ishni shu yerdan davom ettiramiz.',
            'note' => 'Login va parolni do‘kon egasi beradi.',
        ],
        '419' => [
            'kicker' => '419 — sessiya muddati tugadi',
            'title' => 'Sahifa juda uzoq ochiq turdi',
            'text' => 'Xavfsizlik uchun sahifadagi sessiya muddati tugagan. Sahifani yangilab, amalni qaytadan bajaring.',
            'note' => 'Formadagi ma’lumotlarni qayta kiritish kerak bo‘lishi mumkin.',
        ],
        '429' => [
            'kicker' => '429 — juda ko‘p urinish',
            'title' => 'Biroz kutib, qayta urinib ko‘ring',
            'text' => 'Qisqa vaqt ichida juda ko‘p so‘rov yuborildi. Xavfsizlik uchun bir necha soniya kutib, keyin yana urinib ko‘ring.',
            'note' => 'Odatda bir daqiqadan kam kutish yetarli.',
        ],
        '500' => [
            'kicker' => '500 — server xatosi',
            'title' => 'Kutilmagan xatolik yuz berdi',
            'text' => 'Bizning tomonda vaqtincha muammo bo‘ldi. Birozdan so‘ng qayta urinib ko‘ring — jamoa xatoni tekshirmoqda.',
            'note' => 'Muammo takrorlansa, biz bilan bog‘laning.',
        ],
        '503' => [
            'kicker' => '503 — texnik tanaffus',
            'title' => 'Tizim qisqa tanaffusda',
            'text' => 'Rejalashtirilgan texnik ishlar olib borilmoqda. Odatda bir necha daqiqada qaytamiz.',
            'note' => 'Sahifani yangilab ko‘ring yoki birozdan so‘ng qayta kiring.',
        ],
    ],

    'actions' => [
        'home' => 'Bosh sahifa',
        'login' => 'Tizimga kirish',
        'back' => 'Orqaga',
        'retry' => 'Qaytadan urinish',
    ],

    'receipt' => [
        'title' => 'Xato cheki',
        'service' => 'UstBosh · kassa',
        'date' => 'Sana',
        'status' => 'Holat',
        'payment' => 'To‘lov',
        'payment_value' => '0 so‘m',
    ],

    'help' => [
        'label' => 'Muammo bo‘lsa:',
        'link' => 'biz bilan bog‘laning',
        'suffix' => ' — biz doim yordamga tayyormiz.',
    ],

    'hint' => [
        'label' => 'Eslatma:',
    ],
];
