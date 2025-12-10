<?php

namespace App\Http\Middleware;

use App\Models\AcademicConfig;
use App\Models\SelfEvaluationForm;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireRole
{
    public function handle(Request $request, Closure $next, ...$roleNames)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()
                ->route('login')
                ->withErrors('Silakan login terlebih dahulu.');
        }

        $roles = collect($roleNames)
            ->flatMap(fn($name) => explode('|', $name))
            ->filter()
            ->unique();

        // 1) Cek role normal di user_roles
        $hasRole = $user->roles()
            ->where('active', true)
            ->whereHas('role', fn($q) => $q->whereIn('name', $roles))
            ->exists();

        $routeName      = $request->route()?->getName() ?? '';
        $isAuditeeArea  = str_starts_with($routeName, 'auditee.');
        $isAuditeeMember = false;

        // 2) Kalau ini area auditee.*, izinkan juga anggota FED
        if (!$hasRole && $isAuditeeArea) {
            $activeAcademicId = AcademicConfig::where('active', 1)->value('id');

            if ($activeAcademicId) {
                $isAuditeeMember = SelfEvaluationForm::where('academic_config_id', $activeAcademicId)
                    ->where(function ($q) use ($user) {
                        $q->where('member_auditee_1_user_id', $user->id)
                          ->orWhere('member_auditee_2_user_id', $user->id)
                          ->orWhere('member_auditee_3_user_id', $user->id);
                    })
                    ->exists();
            }
        }

        if (!$hasRole && !$isAuditeeMember) {

            if ($request->expectsJson()) {
                return response()->json([
                    'message'        => 'Anda tidak memiliki akses ke halaman ini.',
                    'required_roles' => $roles->values(),
                ], 403);
            }

            // Penting: LOGOUT supaya gak loop auth → login → auth
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors('Anda tidak memiliki akses ke halaman ini. Diperlukan role: ' . $roles->implode(', '));
        }

        return $next($request);
    }
}
