<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmiStandardIndicator;
use App\Models\AmiStandardIndicatorPic;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IndicatorPicController extends Controller
{
    public function index(Request $request)
    {
        $indicatorId = $request->query('indicator_id');
        $perPage     = in_array((int) $request->query('per_page', 20), [10,20,25,50,100], true)
            ? (int) $request->query('per_page', 20) : 20;

        $indicators = AmiStandardIndicator::with(['standard:id,name'])
            ->orderBy('id')
            ->get(['id', 'description', 'standard_id']);

        $roles = Role::where('active', true)->orderBy('name')->get(['id', 'name']);

        $rowsQ = AmiStandardIndicator::with([
            'standard:id,name',
            'pics.role:id,name',
        ])->orderBy('id');

        if ($indicatorId) {
            $rowsQ->where('id', $indicatorId);
        }

        $rows = $rowsQ->paginate($perPage)->withQueryString();

        return view('admin.ami.pic', compact('indicators', 'roles', 'rows'));
    }

    public function store(Request $request, AmiStandardIndicator $indicator)
    {
        $data = $request->validate([
            'role_ids'   => ['required', 'array', 'min:1'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $indId = $indicator->id;
        $selectedRoles = array_values(array_unique($data['role_ids']));
        $redirectTo = route('admin.ami.indicator', ['standard_id' => $indicator->standard_id]);

        try {
            DB::transaction(function () use ($indId, $selectedRoles) {
                // hapus PIC yang tidak terpilih
                AmiStandardIndicatorPic::where('standard_indicator_id', $indId)
                    ->whereNotIn('role_id', $selectedRoles)
                    ->delete();

                // create jika belum ada, update aktif kalau sudah ada
                foreach ($selectedRoles as $roleId) {
                    $existing = AmiStandardIndicatorPic::where([
                        'standard_indicator_id' => $indId,
                        'role_id'               => $roleId,
                    ])->first();

                    if ($existing) {
                        $existing->update(['active' => true]);
                    } else {
                        AmiStandardIndicatorPic::create([
                            'id'                     => AmiStandardIndicatorPic::generateNextId(),
                            'standard_indicator_id'  => $indId,
                            'role_id'                => $roleId,
                            'active'                 => true,
                        ]);
                    }
                }
            });

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'PIC indikator berhasil disimpan.']);
            }
            return redirect($redirectTo)->with('success', 'PIC indikator berhasil disimpan.');
        } catch (\Exception $e) {
            Log::error('Error saving PIC: '.$e->getMessage(), [
                'indicator_id' => $indId,
                'role_ids'     => $selectedRoles,
                'trace'        => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat menyimpan data: '.$e->getMessage(),
                ], 500);
            }
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data.');
        }
    }

    public function update(Request $request, AmiStandardIndicator $indicator)
    {
        $data = $request->validate([
            'role_ids'   => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $indId = $indicator->id;
        $selectedRoles = array_values(array_unique($data['role_ids'] ?? []));
        $redirectTo = route('admin.ami.indicator', ['standard_id' => $indicator->standard_id]);

        try {
            DB::transaction(function () use ($indId, $selectedRoles) {
                $q = AmiStandardIndicatorPic::where('standard_indicator_id', $indId);

                if (empty($selectedRoles)) {
                    // kosongkan semua PIC
                    $q->delete();
                    return;
                }

                // buang yang tidak dipilih
                $q->whereNotIn('role_id', $selectedRoles)->delete();

                // pastikan yang dipilih ada
                foreach ($selectedRoles as $roleId) {
                    $existing = AmiStandardIndicatorPic::where([
                        'standard_indicator_id' => $indId,
                        'role_id'               => $roleId,
                    ])->first();

                    if ($existing) {
                        $existing->update(['active' => true]);
                    } else {
                        AmiStandardIndicatorPic::create([
                            'id'                     => AmiStandardIndicatorPic::generateNextId(),
                            'standard_indicator_id'  => $indId,
                            'role_id'                => $roleId,
                            'active'                 => true,
                        ]);
                    }
                }
            });

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'PIC indikator berhasil diperbarui.']);
            }
            return redirect($redirectTo)->with('success', 'PIC indikator berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating PIC: '.$e->getMessage(), [
                'indicator_id' => $indId,
                'role_ids'     => $selectedRoles,
                'trace'        => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat memperbarui data: '.$e->getMessage(),
                ], 500);
            }
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data.');
        }
    }

    public function destroy(Request $request, AmiStandardIndicator $indicator)
    {
        try {
            AmiStandardIndicatorPic::where('standard_indicator_id', $indicator->id)->delete();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'PIC indikator berhasil dihapus.']);
            }
            return back()->with('success', 'PIC indikator berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting PIC: '.$e->getMessage(), [
                'indicator_id' => $indicator->id,
                'trace'        => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan saat menghapus data: '.$e->getMessage(),
                ], 500);
            }
            return back()->with('error', 'Terjadi kesalahan saat menghapus data.');
        }
    }
}
