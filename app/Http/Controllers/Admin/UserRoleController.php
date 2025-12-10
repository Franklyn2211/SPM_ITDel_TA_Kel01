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

        // filter tambahan
        $filterAcademic = $request->input('filter_academic');
        $filterRole = $request->input('filter_role');
        $filterCategoryDetail = $request->input('filter_category_detail');
        $filterHasAssignment = $request->input('filter_has_assignment'); // '1' = sudah di-assign, '0' = belum, null = semua

        $usersQuery = User::query()
            ->where('username', '!=', 'adminspm')
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($w) use ($q) {
                    $w->where('username', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            });

        // urutan roles per user: terbaru dulu
        $usersQuery->with([
            'roles' => function ($qr) use ($filterAcademic, $filterRole, $filterCategoryDetail) {
                $qr->where('active', true)
                    ->when($filterAcademic, function ($w) use ($filterAcademic) {
                        $w->where('academic_config_id', $filterAcademic);
                    })
                    ->when($filterCategoryDetail, function ($w) use ($filterCategoryDetail) {
                        $w->where('category_detail_id', $filterCategoryDetail);
                    })
                    ->when($filterRole, function ($w) use ($filterRole) {
                        $w->where('role_id', $filterRole);
                    })
                    ->with([
                        'role:id,name,category_id',
                        'role.category:id,name',
                        'academicConfig:id,academic_code',
                        'categoryDetail:id,name',
                    ])
                    ->orderByDesc('created_at');
            }
        ]);

        // filter: user yang punya assignment / tidak punya
        if ($filterHasAssignment === '1' || $filterAcademic || $filterRole || $filterCategoryDetail) {
            // hanya user yang punya roles aktif (plus kombinasi filter lain)
            $usersQuery->whereHas('roles', function ($qr) use ($filterAcademic, $filterRole, $filterCategoryDetail) {
                $qr->where('active', true)
                    ->when($filterAcademic, function ($w) use ($filterAcademic) {
                        $w->where('academic_config_id', $filterAcademic);
                    })
                    ->when($filterCategoryDetail, function ($w) use ($filterCategoryDetail) {
                        $w->where('category_detail_id', $filterCategoryDetail);
                    })
                    ->when($filterRole, function ($w) use ($filterRole) {
                        $w->where('role_id', $filterRole);
                    });
            });
        } elseif ($filterHasAssignment === '0') {
            // hanya user yang BELUM punya roles aktif
            $usersQuery->whereDoesntHave('roles', function ($qr) {
                $qr->where('active', true);
            });
        }

        // urutkan user berdasarkan waktu terakhir di-assign (descending)
        $usersQuery->withMax([
            'roles as last_assigned_at' => function ($qr) use ($filterAcademic, $filterRole, $filterCategoryDetail) {
                $qr->where('active', true)
                    ->when($filterAcademic, function ($w) use ($filterAcademic) {
                        $w->where('academic_config_id', $filterAcademic);
                    })
                    ->when($filterCategoryDetail, function ($w) use ($filterCategoryDetail) {
                        $w->where('category_detail_id', $filterCategoryDetail);
                    })
                    ->when($filterRole, function ($w) use ($filterRole) {
                        $w->where('role_id', $filterRole);
                    });
            }
        ], 'created_at');

        $users = $usersQuery
            ->orderByDesc('last_assigned_at') // paling baru di-assign muncul di atas
            ->orderBy('name')                 // fallback kalau null / sama
            ->paginate(10)
            ->withQueryString();

        $roles = Role::with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        $academics = AcademicConfig::orderBy('academic_code', 'desc')
            ->get(['id', 'academic_code']);

        $categoryDetail = RefCategoryDetail::orderBy('name')
            ->get(['id', 'name']);

        return view('admin.roles.index', compact(
            'users',
            'q',
            'roles',
            'academics',
            'categoryDetail',
            'filterAcademic',
            'filterRole',
            'filterCategoryDetail',
            'filterHasAssignment'
        ));
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
