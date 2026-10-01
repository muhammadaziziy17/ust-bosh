<x-mail::message>
# {{ __('emails.reset.greeting', ['name' => $user->name]) }}

{{ __('emails.reset.line1') }}

<x-mail::button :url="$url">
{{ __('emails.reset.cta') }}
</x-mail::button>

{{ __('emails.reset.line2') }}

{{ __('emails.reset.footer') }}
</x-mail::message>