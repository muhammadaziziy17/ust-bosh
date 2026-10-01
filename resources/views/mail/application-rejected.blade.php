<x-mail::message>
# {{ __('emails.rejected.greeting', ['name' => $application->contact_name]) }}

{{ __('emails.rejected.line1', ['shop' => $application->shop_name]) }}

{{ __('emails.rejected.reason_title') }}

<x-mail::panel>
{{ $reason }}
</x-mail::panel>

{{ __('emails.rejected.line2') }}

{{ __('emails.rejected.line3') }}

{{ __('emails.rejected.footer') }}
</x-mail::message>
