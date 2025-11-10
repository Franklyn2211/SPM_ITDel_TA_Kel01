<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmiStandard;
use App\Models\AmiStandardIndicator;
use Illuminate\Http\Request;

class AmiStandardController extends Controller
{
    public function index(Request $request)
    {
        $isHistory = (bool) $request->query('history', false);

        $rows = AmiStandard::query()
            ->with([
                'academicConfig:id,name,academic_code',
                'createdBy:id,name',
                'updatedBy:id,name',
            ])
            // hitung indikator aktif per standar
            ->withCount([
                'indicators as indicators_count' => function ($q) {
                    $q->where('active', true);
                },
            ])
            // Tampilkan hanya standar pada Tahun Akademik sesuai mode:
            // - default (history=0): hanya TA aktif
            // - history=1: hanya TA tidak aktif (riwayat)
            ->whereHas('academicConfig', function ($q) use ($isHistory) {
                $q->where('active', !$isHistory);
            })
            // jangan filter active = 1, biar admin bisa lihat draft juga (dalam TA terpilih)
            ->orderByDesc('active')       // published duluan
            ->orderByDesc('created_at')
            ->paginate(10);

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
}
