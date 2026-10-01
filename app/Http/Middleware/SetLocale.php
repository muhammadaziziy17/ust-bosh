<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // Default config'dan (APP_LOCALE) olinadi, sessiyada tanlangan til ustun turadi
        $locale = $request->session()->get('locale', config('app.locale'));

        if (in_array($locale, ['uz', 'ru'], true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
