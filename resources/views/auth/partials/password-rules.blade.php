{{--
    Parol talablari — foydalanuvchi yozayotganda real vaqtda yangilanadi.
    Holatni app.js dagi [data-password-form] bloki boshqaradi.
--}}
<ul class="pw-rules mt-2 space-y-1 text-xs" data-password-rules aria-live="polite">
    <li class="pw-rule" data-password-rule="length">{{ __('auth.password_rule_length') }}</li>
    <li class="pw-rule" data-password-rule="case">{{ __('auth.password_rule_case') }}</li>
    <li class="pw-rule" data-password-rule="number">{{ __('auth.password_rule_number') }}</li>
</ul>
