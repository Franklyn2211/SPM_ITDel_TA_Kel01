<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmiStandard;
use App\Models\AmiStandardIndicator;
use App\Models\AmiStandardIndicatorPic;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AmiIndicatorController extends Controller
{
    public function index(Request $request)
    {
        $standardId = $request->query('standard_id');
        $roleId = $request->query('role_id');
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        // Listing indikator:
        // - Kalau tidak filter by standard_id: hanya indikator milik standar yang TA aktif + standar aktif (untuk yang "live").
        // - Kalau filter by standard_id: tampilkan semua indikator standar itu (meskipun standar masih draft), supaya admin bisa edit.
        $rows = AmiStandardIndicator::query()
            ->with([
                'standard:id,name,academic_config_id,active',
                'standard.academicConfig:id,academic_code,active',
                'pics.role:id,name',
            ])
            ->when($standardId, function ($q) use ($standardId) {
                $q->where('standard_id', $standardId);
            }, function ($q) {
                // Tanpa filter standard_id: tampilkan semua indikator dari standar di TA aktif
                // (boleh draft maupun aktif). Jadi tidak lagi membatasi standar.active = true.
                $q->whereHas('standard', function ($qs) {
                    $qs->whereHas('academicConfig', fn($qa) => $qa->where('active', true));
                });
            })
            ->when(
                $roleId,
                fn($q) =>
                $q->whereHas('pics', fn($qq) => $qq->where('role_id', $roleId))
            )
            ->orderBy('standard_id')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        // Dropdown standar:
        // sekarang: SEMUA standar di TA aktif (baik draft maupun aktif),
        // supaya admin bisa isi indikator dulu baru submit standar.
        $standards = AmiStandard::query()
            ->with('academicConfig:id,academic_code,active')
            ->whereHas('academicConfig', fn($q) => $q->where('active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'academic_config_id', 'active']);

        // Info standar yang difilter (kalau ada)
        $selectedStandard = $standardId
            ? AmiStandard::query()
                ->with('academicConfig:id,academic_code,active')
                ->select('id', 'name', 'academic_config_id', 'active')
                ->find($standardId)
            : null;

        // List role buat form create/edit
        $roles = Role::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.ami.indicator', compact('rows', 'standards', 'selectedStandard', 'roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'description' => ['required', 'string'],
            'standard_id' => ['required', 'exists:ami_standards,id'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['exists:roles,id'],
            'positive_result_template' => ['nullable', 'string'],
            'negative_result_template' => ['nullable', 'string'],
        ]);

        // Standar wajib TA aktif, tapi boleh draft maupun aktif
        $std = AmiStandard::with('academicConfig:id,active')->findOrFail($data['standard_id']);
        if (!$std->academicConfig || !$std->academicConfig->active) {
            return redirect()->route('admin.ami.indicator')
                ->with('error', 'Standar yang dipilih tidak berada pada Tahun Akademik aktif.');
        }

        DB::transaction(function () use ($data) {
            $indicator = new AmiStandardIndicator([
                'id' => AmiStandardIndicator::generateNextId(),
                'description' => $data['description'],
                'standard_id' => $data['standard_id'],
                'positive_result_template' => $data['positive_result_template'] ?? null,
                'negative_result_template' => $data['negative_result_template'] ?? null,
                'active' => true,
            ]);
            $indicator->save();

            $roleIds = collect($data['role_ids'])
                ->map(fn($id) => (string) $id)
                ->filter()
                ->unique()
                ->values();

            foreach ($roleIds as $rid) {
                AmiStandardIndicatorPic::create([
                    'id' => AmiStandardIndicatorPic::generateNextId(),
                    'standard_indicator_id' => $indicator->id,
                    'role_id' => $rid,
                    'active' => true,
                ]);
            }
        });

        // Redirect kembali ke standar yang sama agar konteks tidak hilang
        return redirect()->route('admin.ami.indicator', ['standard_id' => $data['standard_id']])
            ->with('success', 'Indikator berhasil dibuat.');
    }

    public function update(Request $request, AmiStandardIndicator $amiIndicator)
    {
        $data = $request->validate([
            'description' => ['required', 'string'],
            'standard_id' => ['required', 'exists:ami_standards,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
            'positive_result_template' => ['nullable', 'string'],
            'negative_result_template' => ['nullable', 'string'],
        ]);

        // Standar wajib TA aktif, tapi boleh draft maupun aktif
        $std = AmiStandard::with('academicConfig:id,active')->findOrFail($data['standard_id']);
        if (!$std->academicConfig || !$std->academicConfig->active) {
            return redirect()->route('admin.ami.indicator')
                ->with('error', 'Standar yang dipilih tidak berada pada Tahun Akademik aktif.');
        }

        DB::transaction(function () use ($amiIndicator, $data) {
            $amiIndicator->update([
                'description' => $data['description'],
                'standard_id' => $data['standard_id'],
                'positive_result_template' => $data['positive_result_template'] ?? null,
                'negative_result_template' => $data['negative_result_template'] ?? null,
                'active' => true,
            ]);

            $roleIds = collect($data['role_ids'] ?? [])
                ->map(fn($id) => (string) $id)
                ->filter()
                ->unique()
                ->values();

            // Sinkronisasi PIC
            if ($roleIds->isEmpty()) {
                AmiStandardIndicatorPic::where('standard_indicator_id', $amiIndicator->id)->delete();
                return;
            }

            AmiStandardIndicatorPic::where('standard_indicator_id', $amiIndicator->id)
                ->whereNotIn('role_id', $roleIds)->delete();

            foreach ($roleIds as $rid) {
                $existing = AmiStandardIndicatorPic::where([
                    'standard_indicator_id' => $amiIndicator->id,
                    'role_id' => $rid,
                ])->first();

                if ($existing) {
                    $existing->update(['active' => true]);
                } else {
                    AmiStandardIndicatorPic::create([
                        'id' => AmiStandardIndicatorPic::generateNextId(),
                        'standard_indicator_id' => $amiIndicator->id,
                        'role_id' => $rid,
                        'active' => true,
                    ]);
                }
            }
        });

        // Tetap di standar yang sedang diedit (bisa berubah jika standard_id di-update)
        return redirect()->route('admin.ami.indicator', ['standard_id' => $data['standard_id']])
            ->with('success', 'Indikator berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $amiIndicator = AmiStandardIndicator::findOrFail($id);

        DB::transaction(function () use ($amiIndicator) {
            AmiStandardIndicatorPic::where('standard_indicator_id', $amiIndicator->id)->delete();
            $amiIndicator->delete();
        });

        // Ambil standard_id sebelum dihapus agar tetap di halaman filter yang sama
        $standardId = $amiIndicator->standard_id;
        return redirect()->route('admin.ami.indicator', ['standard_id' => $standardId])
            ->with('success', 'Indikator berhasil dihapus.');
    }
}
