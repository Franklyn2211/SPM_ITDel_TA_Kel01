<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NonAdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        // Kalau adminspm mencoba masuk halaman user → lempar ke admin dashboard
        if ($u && $u->username === 'adminspm') {
            return redirect()->route('admin.dashboard')->withErrors('Halaman ini khusus user non-admin.');
        }
        return $next($request);
    }
}
