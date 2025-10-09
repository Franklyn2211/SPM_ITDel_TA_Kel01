<?php

namespace App\Http\Controllers\Auditee;

use App\Http\Controllers\Controller;
use App\Models\AmiStandardIndicator;
use App\Models\AmiStandardIndicatorPic;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndicatorPicController extends Controller
{
    public function index()
    {
        $indicators = AmiStandardIndicator::with([
                'standard:id,name,academic_config_id',
                'standard.academicConfig:id,academic_code,active',
            ])
            ->whereHas('standard.academicConfig', fn($q) => $q->where('active', true))
            ->orderBy('id')
            ->get(['id', 'description', 'standard_id']);

        $roles = Role::where('active', true)->orderBy('name')->get(['id','name']);

        $rows = AmiStandardIndicator::with([
                'standard:id,name,academic_config_id',
                'standard.academicConfig:id,academic_code,active',
                'pics.role:id,name',
            ])
            ->whereHas('standard.academicConfig', fn($q) => $q->where('active', true))
            ->orderBy('id')
            ->paginate(20);

        return view('auditee.ami.pic', compact('indicators', 'roles', 'rows'));
    }

    public function store(Request $request, AmiStandardIndicator $indicator)
    {
        $data = $request->validate([
            'role_ids'   => ['required', 'array', 'min:1'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $indicator->load('standard.academicConfig:id,active');

        if (!$indicator->standard || !$indicator->standard->academicConfig?->active) {
            return response()->json([
                'success' => false,
                'message' => 'Indikator tidak berada pada standar dengan Tahun Akademik aktif.'
            ], 422);
        }

        $indId = $indicator->id;
        $selectedRoles = array_values(array_unique($data['role_ids']));

        DB::transaction(function () use ($indId, $selectedRoles) {
            AmiStandardIndicatorPic::where('standard_indicator_id', $indId)
                ->whereNotIn('role_id', $selectedRoles)
                ->delete();

            foreach ($selectedRoles as $roleId) {
                AmiStandardIndicatorPic::firstOrCreate(
                    ['standard_indicator_id' => $indId, 'role_id' => $roleId],
                    ['id' => AmiStandardIndicatorPic::generateNextId(), 'active' => true]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'PIC indikator berhasil disimpan.'
        ]);
    }

    public function update(Request $request, AmiStandardIndicator $indicator)
    {
        $data = $request->validate([
            'role_ids'   => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $indicator->load('standard.academicConfig:id,active');

        if (!$indicator->standard || !$indicator->standard->academicConfig?->active) {
            return response()->json([
                'success' => false,
                'message' => 'Indikator tidak berada pada standar dengan Tahun Akademik aktif.'
            ], 422);
        }

        $indId = $indicator->id;
        $selectedRoles = array_values(array_unique($data['role_ids'] ?? []));

        DB::transaction(function () use ($indId, $selectedRoles) {
            $q = AmiStandardIndicatorPic::where('standard_indicator_id', $indId);
            if (empty($selectedRoles)) {
                $q->delete();
            } else {
                $q->whereNotIn('role_id', $selectedRoles)->delete();
                foreach ($selectedRoles as $roleId) {
                    AmiStandardIndicatorPic::firstOrCreate(
                        ['standard_indicator_id' => $indId, 'role_id' => $roleId],
                        ['id' => AmiStandardIndicatorPic::generateNextId(), 'active' => true]
                    );
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'PIC indikator berhasil diperbarui.'
        ]);
    }

    public function destroy(AmiStandardIndicator $indicator)
    {
        $indicator->load('standard.academicConfig:id,active');

        if (!$indicator->standard || !$indicator->standard->academicConfig?->active) {
            return response()->json([
                'success' => false,
                'message' => 'Indikator tidak berada pada standar dengan Tahun Akademik aktif.'
            ], 422);
        }

        AmiStandardIndicatorPic::where('standard_indicator_id', $indicator->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Semua PIC untuk indikator ini telah dihapus.'
        ]);
    }
}
