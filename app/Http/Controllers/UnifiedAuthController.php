<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CisClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UnifiedAuthController extends Controller
{
    public function show()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $cred = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // ===============================
        // A) ADMIN lokal (username 'adminspm')
        // ===============================
        if ($cred['username'] === 'adminspm') {
            $admin = User::where('username', 'adminspm')->first();
            if (!$admin || !Hash::check($cred['password'], $admin->password)) {
                throw ValidationException::withMessages(['username' => 'Kredensial admin salah.']);
            }

            session()->forget(['cis_token', 'cis_profile']);
            Auth::login($admin, true);

            return redirect()->route('admin.dashboard')->with('status', 'Login admin berhasil.');
        }

        // ===============================
        // B) User CIS via do-auth
        // ===============================
        $resp = Http::baseUrl(config('services.ext_api.base_url'))
            ->acceptJson()
            ->asForm()
            ->timeout(config('services.ext_api.timeout', 15))
            ->post(env('CIS_AUTH_PATH', '/jwt-api/do-auth'), [
                'username' => $cred['username'],
                'password' => $cred['password'],
            ]);

        $body = $resp->json();
        $ok   = $resp->ok() && (data_get($body, 'result') === true || data_get($body, 'result') === 1);
        if (!$ok) {
            $msg = data_get($body, 'error') ?? data_get($body, 'message') ?? 'Login CIS gagal.';
            throw ValidationException::withMessages(['username' => $msg]);
        }

        $token   = data_get($body, 'token')
                ?? data_get($body, 'access_token')
                ?? data_get($body, 'data.token')
                ?? data_get($body, 'data.access_token');

        $profile = data_get($body, 'user') ?? data_get($body, 'data.user') ?? [];

        $cisId   = (string) data_get($profile, 'user_id'); // ID stabil dari CIS
        $emailL  = (string) data_get($profile, 'email');
        $unameL  = (string) $cred['username'];

        if (!$token || !$cisId) {
            throw ValidationException::withMessages(['username' => 'Login CIS gagal: token atau user_id tidak ditemukan.']);
        }

        // (Opsional) ambil nama dari direktori (dosen/pegawai) — kalau tidak perlu, skip seluruh blok ini
        $finalName = data_get($profile, 'name') ?? data_get($profile, 'nama') ?? $unameL;
        try {
            $client = new CisClient();
            $lect = $client->getWithToken($token, env('CIS_LECTURERS_PATH', '/library-api/dosen'), [
                'user_id' => $cisId,
            ]);
            $dosenRow = data_get($lect, 'data.dosen.0');
            if (is_array($dosenRow)) {
                $finalName = data_get($dosenRow, 'nama') ?? data_get($dosenRow, 'name') ?? $finalName;
            } else {
                $emp = $client->getWithToken($token, env('CIS_EMPLOYEES_PATH', '/library-api/pegawai'), [
                    'user_id' => $cisId,
                ]);
                $pgwRow = data_get($emp, 'data.pegawai.0');
                if (is_array($pgwRow)) {
                    $finalName = data_get($pgwRow, 'nama') ?? data_get($pgwRow, 'name') ?? $finalName;
                }
            }
        } catch (\Throwable $e) {
            // abaikan error direktori; pakai nama dari profil login
        }

        // C) Rekonsiliasi user lokal berdasarkan cis_user_id
        $user = User::where('cis_user_id', $cisId)->first();
        if (!$user) {
            $user = User::create([
                'cis_user_id' => $cisId,
                'username'    => $unameL ?: ('cis_' . $cisId),
                'name'        => $finalName,
                'email'       => $emailL ?: ('u_' . ($unameL ?: 'cis_' . $cisId) . '@cis.local'),
                'password'    => Hash::make(Str::random(32)), // dummy
                'active'      => true,
            ]);
        } else {
            $user->update([
                'username' => $unameL ?: $user->username,
                'email'    => $emailL ?: $user->email,
                'name'     => $user->name ?: $finalName,
            ]);
        }

        // D) Simpan sesi & login, lalu redirect BERDASARKAN ROLE AKTIF
        session(['cis_token' => $token, 'cis_profile' => $profile]);
        Auth::login($user, true);

        return $this->redirectBasedOnRole($user);
    }

    public function logout()
    {
        Auth::logout();
        session()->forget(['cis_token', 'cis_profile']);
        return redirect()->route('login')->with('status', 'Logout berhasil.');
    }

    /**
     * Tentukan halaman landing setelah login:
     * - adminspm => admin.dashboard
     * - jika punya role aktif bernama "Auditee" => auditee.dashboard
     * - jika belum punya role aktif => kembali ke login dengan pesan
     */
    protected function redirectBasedOnRole(User $user)
    {
        if ($user->username === 'adminspm') {
            return redirect()->route('admin.dashboard');
        }

        // cek role aktif berdasarkan NAMA role (bukan ID)
        $hasAuditee = $user->roles()
            ->where('active', true)
            ->whereHas('role', fn($q) => $q->where('name', 'Auditee'))
            ->exists();

        if ($hasAuditee) {
            return redirect()->route('auditee.dashboard');
        }

        // kalau belum di-assign role aktif, jangan dibiarkan keluyuran
        Auth::logout();
        session()->forget(['cis_token','cis_profile']);
        return redirect()
            ->route('login')
            ->withErrors('Akun belum memiliki role aktif. Hubungi admin.');
    }
}
