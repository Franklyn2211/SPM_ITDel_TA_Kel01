<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserRoleController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) ($request->input('q', $request->input('search', ''))));

        $users = User::query()
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($w) use ($q) {
                    $w->where('username', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->with(['roles' => function ($qr) {
                $qr->where('active', true)
                   ->with([
                       'role:id,name',
                       'academicConfig:id,academic_code'
                   ])
                   ->orderByDesc('created_at');
            }])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles     = Role::orderBy('name')->get(['id','name']);
        $academics = AcademicConfig::orderBy('academic_code','desc')->get(['id','academic_code']);

        return view('admin.roles.index', compact('users','q','roles','academics'));
    }

    public function assign(Request $request)
{
    \Log::info('[assign] incoming', $request->all());

    $messages = [
        'cis_user_id.required'        => 'User tidak valid (cis_user_id kosong).',
        'cis_user_id.exists'          => 'cis_user_id tidak ditemukan di tabel users.',
        'academic_config_id.required' => 'Tahun akademik wajib dipilih.',
        'academic_config_id.exists'   => 'Tahun akademik tidak valid.',
        'role_ids.required'           => 'Minimal pilih 1 role.',
        'role_ids.array'              => 'Format role_ids harus array.',
        'role_ids.*.exists'           => 'Ada role yang tidak valid.',
    ];

    $data = $request->validate([
        'cis_user_id'        => ['required','string','exists:users,cis_user_id'],
        'academic_config_id' => ['required','string','exists:academic_configs,id'],
        'role_ids'           => ['required','array','min:1'],
        'role_ids.*'         => ['string','exists:roles,id'],
    ], $messages);

    $cis   = $data['cis_user_id'];
    $acId  = $data['academic_config_id'];
    $roles = $data['role_ids'];

    \DB::transaction(function () use ($cis, $acId, $roles) {
        \App\Models\UserRole::where('cis_user_id', $cis)
            ->where('academic_config_id', $acId)
            ->whereNotIn('role_id', $roles)
            ->update(['active' => false]);

        foreach ($roles as $rid) {
            $ur = \App\Models\UserRole::firstOrNew([
                'cis_user_id'        => $cis,
                'academic_config_id' => $acId,
                'role_id'            => $rid,
            ]);

            if (!$ur->exists) {
                $ur->id     = \App\Models\UserRole::generateNextId();
                $ur->active = true;
                $ur->save();
            } elseif (!$ur->active) {
                $ur->active = true;
                $ur->save();
            }
        }
    });

    return back()->with('success', 'Assignment role user disimpan.');
}

}
