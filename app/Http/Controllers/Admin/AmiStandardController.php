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
}
