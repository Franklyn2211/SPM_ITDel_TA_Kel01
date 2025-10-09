<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnlyByUsername
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (!$u || $u->username !== 'adminspm') {
            return redirect()->route('admin.dashboard')->withErrors('Akses khusus admin.');
        }
        return $next($request);
    }
}
