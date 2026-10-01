<x-mail::message>
# {{ __('emails.approved.greeting', ['name' => $application->contact_name]) }}

{{ __('emails.approved.line1', ['shop' => $application->shop_name]) }}

<x-mail::panel>
**{{ __('emails.approved.credentials_title') }}**

**{{ __('emails.approved.login') }}:** {{ $loginEmail }}
</x-mail::panel>

{{ __('emails.approved.line3') }}:

<x-mail::button :url="$resetUrl">
{{ __('emails.approved.cta') }}
</x-mail::button>

{{ __('emails.approved.security_note') }}

{{ __('emails.approved.footer') }}
</x-mail::message>