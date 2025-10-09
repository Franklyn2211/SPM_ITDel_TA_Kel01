<?php

namespace App\Http\Controllers\Auditee;

use App\Http\Controllers\Controller;
use App\Models\AmiStandardIndicator;
use App\Models\AmiStandardIndicatorPic;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IndicatorPicController extends Controller
{
    public function index()
    {
        $indicators = AmiStandardIndicator::with(['standard:id,name'])
            ->orderBy('id')
            ->get(['id', 'description', 'standard_id']);

        $roles = Role::where('active', true)->orderBy('name')->get(['id', 'name']);

        $rows = AmiStandardIndicator::with([
                'standard:id,name',
                'pics.role:id,name',
            ])
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

        $indId = $indicator->id; // Explicitly use the ID
        Log::info('Storing PIC for indicator ID: ' . $indId); // Debug log

        if (!$indId) {
            return response()->json([
                'success' => false,
                'message' => 'Indikator ID tidak valid.',
            ], 400);
        }

        $selectedRoles = array_values(array_unique($data['role_ids']));

        try {
            DB::transaction(function () use ($indId, $selectedRoles) {
                // Delete PICs not in the new selection
                AmiStandardIndicatorPic::where('standard_indicator_id', $indId)
                    ->whereNotIn('role_id', $selectedRoles)
                    ->delete();

                // Create or update PICs
                foreach ($selectedRoles as $roleId) {
                    $pic = AmiStandardIndicatorPic::updateOrCreate(
                        ['standard_indicator_id' => $indId, 'role_id' => $roleId],
                        ['id' => AmiStandardIndicatorPic::generateNextId(), 'active' => true]
                    );
                    Log::info('Created/Updated PIC: ', ['id' => $pic->id, 'indicator_id' => $indId, 'role_id' => $roleId]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'PIC indikator berhasil disimpan.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving PIC: ' . $e->getMessage(), [
                'indicator_id' => $indId,
                'role_ids' => $selectedRoles,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, AmiStandardIndicator $indicator)
    {
        $data = $request->validate([
            'role_ids'   => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $indId = $indicator->id; // Explicitly use the ID
        Log::info('Updating PIC for indicator ID: ' . $indId); // Debug log

        if (!$indId) {
            return response()->json([
                'success' => false,
                'message' => 'Indikator ID tidak valid.',
            ], 400);
        }

        $selectedRoles = array_values(array_unique($data['role_ids'] ?? []));

        try {
            DB::transaction(function () use ($indId, $selectedRoles) {
                $q = AmiStandardIndicatorPic::where('standard_indicator_id', $indId);
                if (empty($selectedRoles)) {
                    $q->delete();
                } else {
                    $q->whereNotIn('role_id', $selectedRoles)->delete();
                    foreach ($selectedRoles as $roleId) {
                        $pic = AmiStandardIndicatorPic::updateOrCreate(
                            ['standard_indicator_id' => $indId, 'role_id' => $roleId],
                            ['id' => AmiStandardIndicatorPic::generateNextId(), 'active' => true]
                        );
                        Log::info('Created/Updated PIC: ', ['id' => $pic->id, 'indicator_id' => $indId, 'role_id' => $roleId]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'PIC indikator berhasil diperbarui.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating PIC: ' . $e->getMessage(), [
                'indicator_id' => $indId,
                'role_ids' => $selectedRoles,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(AmiStandardIndicator $indicator)
    {
        try {
            $indId = $indicator->id;
            AmiStandardIndicatorPic::where('standard_indicator_id', $indId)->delete();

        } catch (\Exception $e) {
            Log::error('Error deleting PIC: ' . $e->getMessage(), [
                'indicator_id' => $indicator->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data: ' . $e->getMessage(),
            ], 500);
        }

        return back()->with('success', 'PIC indikator berhasil dihapus.');
    }
}
