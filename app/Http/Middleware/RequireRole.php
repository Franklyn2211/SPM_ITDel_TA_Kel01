<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireRole
{
    public function handle(Request $request, Closure $next, ...$roleNames)
    {
        $user = $request->user();

        // Check if user is logged in
        if (!$user) {
            return redirect()
                ->route('login')
                ->withErrors('Silakan login terlebih dahulu.');
        }

        // Handle multiple roles (separated by | in route definition)
        $roles = collect($roleNames)
            ->flatMap(fn($name) => explode('|', $name))
            ->unique();

        // Check if user has any of the required roles
        $hasRole = $user->roles()
            ->where('active', true)
            ->whereHas('role', fn($q) => $q->whereIn('name', $roles))
            ->exists();

        if (!$hasRole) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Anda tidak memiliki akses ke halaman ini.',
                    'required_roles' => $roles->toArray()
                ], 403);
            }

            // For web requests, redirect with error message
            return redirect()
                ->route('auditee.dashboard') // or another appropriate fallback route
                ->withErrors('Anda tidak memiliki akses ke halaman ini. Diperlukan role: ' . $roles->implode(', '));
        }

        return $next($request);
    }
}
