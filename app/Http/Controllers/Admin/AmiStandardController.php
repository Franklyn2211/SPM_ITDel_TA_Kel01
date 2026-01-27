<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AmiStandard;
use App\Models\AmiStandardIndicator;
use App\Models\AmiStandardIndicatorPic;
use DB;
use Illuminate\Http\Request;

class AmiStandardController extends Controller
{
    public function index(Request $request)
    {
        $isHistory = (bool) $request->query('history', false);

        $q = trim((string) $request->input('q', ''));
        $rows = AmiStandard::with(['academicConfig', 'createdBy'])
            ->withCount('indicators')
            ->when($isHistory, function ($query) {
                // Hanya standar dari tahun akademik yang tidak aktif
                $query->whereHas('academicConfig', function ($ac) {
                    $ac->where('active', false);
                });
            }, function ($query) {
                // Default: hanya standar dari tahun akademik aktif
                $query->whereHas('academicConfig', function ($ac) {
                    $ac->where('active', true);
                });
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhereHas('academicConfig', function ($ac) use ($q) {
                            $ac->where('academic_code', 'like', "%{$q}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        // Flag apakah ada standar yang aktif untuk mengatur tombol Submit (activate/deactivate)
        // Hanya dihitung pada TA aktif, agar tombol tidak tampil saat melihat riwayat
        $anyActive = AmiStandard::query()
            ->where('active', true)
            ->whereHas('academicConfig', fn($q) => $q->where('active', true))
            ->exists();

        return view('admin.ami.standard', compact('rows', 'anyActive'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $amiStandard = new AmiStandard([
            'id' => AmiStandard::generateNextId(),
            'name' => $request->string('name'),
            // standar baru = draft dulu
            'active' => false,
        ]);

        $amiStandard->save();

        return redirect()
            ->route('admin.ami.standard')
            ->with('success', 'Standar AMI berhasil dibuat sebagai draft.');
    }

    public function update(Request $request, AmiStandard $amiStandard)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $amiStandard->update([
            'name' => $request->string('name'),
        ]);

        return redirect()
            ->route('admin.ami.standard')
            ->with('success', 'Standar AMI berhasil diperbarui.');
    }

    public function submit(Request $request)
    {
        $mode = strtolower($request->string('mode')->toString() ?: 'activate');

        // Kembalikan SEMUA ke draft
        if ($mode === 'deactivate') {
            AmiStandard::query()->update(['active' => false]);

            return redirect()
                ->route('admin.ami.standard')
                ->with('success', 'Semua standar AMI dikembalikan ke draft. Indikator tidak lagi muncul di auditee.');
        }

        // mode = activate: aktifkan semua standar yang punya MINIMAL 1 indikator aktif
        $standardIdsWithIndicators = AmiStandardIndicator::query()
            ->where('active', true)
            ->pluck('standard_id')
            ->unique();

        if ($standardIdsWithIndicators->isEmpty()) {
            return redirect()
                ->route('admin.ami.standard')
                ->with('error', 'Tidak ada standar yang memiliki indikator aktif. Tambahkan indikator terlebih dahulu.');
        }

        // aktifkan hanya standar yang punya indikator aktif
        AmiStandard::whereIn('id', $standardIdsWithIndicators)->update(['active' => true]);

        // kalau mau, standar lain yang tidak punya indikator bisa dipaksa draft:
        AmiStandard::whereNotIn('id', $standardIdsWithIndicators)->update(['active' => false]);

        $totalActivated = $standardIdsWithIndicators->count();

        return redirect()
            ->route('admin.ami.standard')
            ->with('success', "Submit berhasil. {$totalActivated} standar dengan indikator aktif sekarang muncul di sisi auditee.");
    }

    public function destroy($id)
    {
        $amiStandard = AmiStandard::findOrFail($id);
        $amiStandard->delete();

        return redirect()
            ->route('admin.ami.standard')
            ->with('success', 'Standar AMI berhasil dihapus.');
    }

    public function copyFromPrevious(Request $request)
    {
        // Target = Tahun Akademik yang aktif sekarang
        $targetAc = AcademicConfig::query()
            ->where('active', true)
            ->orderByDesc('academic_code')
            ->orderByDesc('created_at')
            ->first();

        if (!$targetAc) {
            return redirect()
                ->route('admin.ami.standard')
                ->with('error', 'Tidak ada Tahun Akademik aktif. Aktifkan TA terlebih dahulu.');
        }

        // Source = TA non-aktif paling baru (atau bisa ditentukan lewat request)
        $sourceAcId = $request->input('source_academic_config_id');

        $sourceAc = $sourceAcId
            ? AcademicConfig::query()->where('id', $sourceAcId)->first()
            : AcademicConfig::query()
                ->where('active', false)
                ->orderByDesc('academic_code')
                ->orderByDesc('created_at')
                ->first();

        if (!$sourceAc) {
            return redirect()
                ->route('admin.ami.standard')
                ->with('error', 'Tidak ada Tahun Akademik sebelumnya (non-aktif) untuk dicopy.');
        }

        if ((string) $sourceAc->id === (string) $targetAc->id) {
            return redirect()
                ->route('admin.ami.standard')
                ->with('error', 'TA sumber sama dengan TA aktif. Pilih TA sumber yang non-aktif.');
        }

        // Cegah duplikasi: kalau TA aktif sudah punya standar, jangan copy lagi kecuali force=1
        $force = $request->boolean('force', false);

        $alreadyHasStandards = AmiStandard::query()
            ->where('academic_config_id', $targetAc->id)
            ->exists();

        if ($alreadyHasStandards && !$force) {
            return redirect()
                ->route('admin.ami.standard')
                ->with('error', 'TA aktif sudah memiliki standar. Hapus dulu atau gunakan mode force untuk copy ulang.');
        }

        // Ambil semua standar dari TA sumber beserta indikator + PIC
        $sourceStandards = AmiStandard::query()
            ->where('academic_config_id', $sourceAc->id)
            ->with(['indicators.pics'])
            ->orderBy('id')
            ->get();

        if ($sourceStandards->isEmpty()) {
            return redirect()
                ->route('admin.ami.standard')
                ->with('error', 'TA sumber tidak punya standar untuk dicopy.');
        }

        $createdStandards = 0;
        $createdIndicators = 0;
        $createdPics = 0;

        DB::transaction(function () use (
            $force,
            $targetAc,
            $sourceStandards,
            &$createdStandards,
            &$createdIndicators,
            &$createdPics
        ) {
            // Kalau force: bersihin dulu data TA aktif biar tidak dobel
            if ($force) {
                $targetStandardIds = AmiStandard::query()
                    ->where('academic_config_id', $targetAc->id)
                    ->pluck('id');

                if ($targetStandardIds->isNotEmpty()) {
                    $targetIndicatorIds = AmiStandardIndicator::query()
                        ->whereIn('standard_id', $targetStandardIds)
                        ->pluck('id');

                    if ($targetIndicatorIds->isNotEmpty()) {
                        AmiStandardIndicatorPic::query()
                            ->whereIn('standard_indicator_id', $targetIndicatorIds)
                            ->delete();

                        AmiStandardIndicator::query()
                            ->whereIn('id', $targetIndicatorIds)
                            ->delete();
                    }

                    AmiStandard::query()
                        ->whereIn('id', $targetStandardIds)
                        ->delete();
                }
            }

            foreach ($sourceStandards as $srcStd) {
                $newStd = new AmiStandard();
                $newStd->id = AmiStandard::generateNextId();
                $newStd->name = $srcStd->name;
                $newStd->academic_config_id = $targetAc->id;

                // Biar aman: hasil copy masuk "Draft" dulu.
                // Admin nanti klik Submit Semua Standar.
                $newStd->active = false;
                $newStd->save();

                $createdStandards++;

                // Clone indikator
                foreach (($srcStd->indicators ?? collect()) as $srcInd) {
                    $newInd = new AmiStandardIndicator();
                    $newInd->id = AmiStandardIndicator::generateNextId();
                    $newInd->standard_id = $newStd->id;
                    $newInd->description = $srcInd->description;

                    // Copy indikator biasanya langsung aktif (standarnya tetap draft sampai submit)
                    $newInd->active = true;
                    $newInd->save();

                    $createdIndicators++;

                    // Clone PIC role
                    foreach (($srcInd->pics ?? collect()) as $srcPic) {
                        $newPic = new AmiStandardIndicatorPic();
                        $newPic->id = AmiStandardIndicatorPic::generateNextId();
                        $newPic->standard_indicator_id = $newInd->id;
                        $newPic->role_id = $srcPic->role_id;
                        $newPic->active = true;
                        $newPic->save();

                        $createdPics++;
                    }
                }
            }
        });

        return redirect()
            ->route('admin.ami.standard')
            ->with(
                'success',
                "Copy selesai. {$createdStandards} standar, {$createdIndicators} indikator, {$createdPics} PIC berhasil dicopy ke TA aktif."
            );
    }
}
