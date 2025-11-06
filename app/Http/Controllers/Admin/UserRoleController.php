<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\RefCategoryDetail;
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
            ->with([
                'roles' => function ($qr) {
                    $qr->where('active', true)
                        ->with([
                            'role:id,name,category_id',
                            'role.category:id,name',
                            'academicConfig:id,academic_code',
                            'categoryDetail:id,name',
                        ])
                        ->orderByDesc('created_at');
                }
            ])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles = Role::with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);
        $academics = AcademicConfig::orderBy('academic_code', 'desc')->get(['id', 'academic_code']);
        $categoryDetail = RefCategoryDetail::orderBy('name')->get(['id', 'name']);

        return view('admin.roles.index', compact('users', 'q', 'roles', 'academics', 'categoryDetail'));
    }

    public function assign(Request $request)
    {
        \Log::info('[assign] incoming', $request->all());

        $messages = [
            'cis_user_id.required' => 'User tidak valid (cis_user_id kosong).',
            'cis_user_id.exists' => 'cis_user_id tidak ditemukan di tabel users.',
            'academic_config_id.required' => 'Tahun akademik wajib dipilih.',
            'academic_config_id.exists' => 'Tahun akademik tidak valid.',
            'category_detail_ids.required' => 'Minimal pilih 1 detail kategori.',
            'category_detail_ids.array' => 'Format detail kategori harus array.',
            'category_detail_ids.*.exists' => 'Ada detail kategori yang tidak valid.',
            'role_ids.required' => 'Minimal pilih 1 role.',
            'role_ids.array' => 'Format role_ids harus array.',
            'role_ids.*.exists' => 'Ada role yang tidak valid.',
        ];

        $data = $request->validate([
            'cis_user_id' => ['required', 'string', 'exists:users,cis_user_id'],
            'academic_config_id' => ['required', 'string', 'exists:academic_configs,id'],
            'category_detail_ids' => ['required', 'array', 'min:1'],
            'category_detail_ids.*' => ['string', 'exists:ref_category_details,id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['string', 'exists:roles,id'],
        ], $messages);

        $cis = $data['cis_user_id'];
        $acId = $data['academic_config_id'];
        $cDetailIds = $data['category_detail_ids'];
        $roles = $data['role_ids'];

        \DB::transaction(function () use ($cis, $acId, $cDetailIds, $roles) {
            UserRole::where('cis_user_id', $cis)
                ->where('academic_config_id', $acId)
                ->where(function ($q) use ($cDetailIds, $roles) {
                    $q->whereNotIn('category_detail_id', $cDetailIds)
                        ->orWhereNotIn('role_id', $roles);
                })
                ->update(['active' => false]);

            foreach ($cDetailIds as $cdId) {
                foreach ($roles as $rid) {
                    // Pastikan isi kolom id saat create
                    $ur = UserRole::firstOrNew([
                        'cis_user_id' => $cis,
                        'academic_config_id' => $acId,
                        'category_detail_id' => $cdId,
                        'role_id' => $rid,
                    ]);

                    if (!$ur->exists) {
                        $ur->id = UserRole::generateNextId();
                        $ur->active = true;
                        $ur->save();
                    } elseif (!$ur->active) {
                        $ur->active = true;
                        $ur->save();
                    }
                }
            }
        });

        return back()->with('success', 'Assignment role user disimpan.');
    }
}
