<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xavfsizlik headerlari: clickjacking, MIME sniffing va keraksiz
 * tashqi resurslarga qarshi asosiy himoya.
 */
class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        return $response;
    }

    /**
     * Inline script/uslublar hozircha ruxsat etilgan (Blade ichida onclick va
     * Tailwind CDN ishlatiladi). Kelajakda nonce/hash bilan qattiqlashtirish mumkin.
     */
    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data:",
            // jsDelivr — DevTools sourcemap so'rovlari uchun (kutubxonalar ishlashiga ta'sir qilmaydi)
            "connect-src 'self' https://cdn.jsdelivr.net",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
