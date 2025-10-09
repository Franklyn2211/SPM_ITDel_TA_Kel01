<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    public function handle(Request $request, Closure $next, string $roleName)
    {
        $u = $request->user();
        $ok = $u
            && $u->roles()
                ->where('active', true)
                ->whereHas('role', fn($q) => $q->where('name', $roleName))
                ->exists();

        if (!$ok) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
