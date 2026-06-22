<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Gate a route by role. `role:admin` requires an admin; `role:it_staff`
     * allows admin or IT staff (mirrors User::isItStaff()). Runs after
     * auth:sanctum, so an unauthenticated request is already a 401.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        $allowed = match ($role) {
            'admin'    => (bool) $user?->isAdmin(),
            'it_staff' => (bool) $user?->isItStaff(),
            default    => false,
        };

        abort_unless($allowed, 403);

        return $next($request);
    }
}
