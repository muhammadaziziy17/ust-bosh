<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * @param  string  ...$roles  Ruxsat etilgan rollar (masalan: role:owner)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        // Super admin boshqa panellarga kira olmaydi — u faqat admin panelga ega
        abort_if($user->role === User::ROLE_SUPER_ADMIN, 403);

        abort_unless(in_array($user->role, $roles, true), 403);

        return $next($request);
    }
}
