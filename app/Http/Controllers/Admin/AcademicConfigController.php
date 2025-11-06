<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

class AcademicConfigController extends Controller
{
    public function index()
    {
        $academicConfigs = AcademicConfig::orderByDesc('active')
            ->orderBy('academic_code')
            ->get();

        return view('admin.academic_config.index', compact('academicConfigs'));
    }

    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'academic_code' => ['required','string','max:100','unique:academic_configs,academic_code'],
            'name'          => ['required','string','max:255'],
        ], [
            'academic_code.unique' => 'Kode akademik tersebut sudah ada.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('toast_error', 'Gagal menyimpan. Kode akademik sudah terdaftar.');
        }

        try {
            DB::transaction(function () use ($request) {
                // Nonaktifkan yang lain
                AcademicConfig::where('active', true)->update(['active' => false]);

                // Buat yang baru dan aktifkan
                $ac = new AcademicConfig([
                    'id'            => AcademicConfig::generateNextId(),
                    'academic_code' => $request->input('academic_code'),
                    'name'          => $request->input('name'),
                    'active'        => true,
                ]);

                $ac->save();
            });
        } catch (QueryException $e) {
            if ((string)$e->getCode() === '23000') {
                return back()
                    ->withInput()
                    ->with('toast_error', 'Gagal menyimpan. Data dengan kode akademik ini sudah ada.');
            }
            throw $e;
        }

        return redirect()
            ->route('admin.academic_config.index')
            ->with('toast_success', 'Konfigurasi akademik berhasil dibuat & diaktifkan.');
    }

    public function update(Request $request, AcademicConfig $academicConfig)
    {
        $validator = \Validator::make($request->all(), [
            'academic_code' => [
                'required','string','max:100',
                Rule::unique('academic_configs','academic_code')->ignore($academicConfig->id, 'id'),
            ],
            'name' => ['required','string','max:255'],
        ], [
            'academic_code.unique' => 'Kode akademik sudah digunakan oleh konfigurasi lain.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('toast_error', 'Gagal update. Kode akademik bentrok.');
        }

        $academicConfig->update([
            'academic_code' => $request->string('academic_code'),
            'name'          => $request->string('name'),
        ]);

        return redirect()
            ->route('admin.academic_config.index')
            ->with('toast_success', 'Academic Config berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $academicConfig = AcademicConfig::findOrFail($id);
        $academicConfig->delete();

        return redirect()
            ->route('admin.academic_config.index')
            ->with('toast_success', 'Academic Config berhasil dihapus.');
    }

    public function setActive(Request $request, AcademicConfig $academicConfig)
    {
        $request->validate(['active' => 'required|boolean']);
        $newActive = $request->boolean('active');

        if ($newActive) {
            AcademicConfig::where('id', '!=', $academicConfig->id)
                ->where('active', true)
                ->update(['active' => false]);
        }

        $academicConfig->active = $newActive;
        $academicConfig->save();

        $statusText = $newActive ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('toast_success', "Status konfigurasi {$academicConfig->name} berhasil {$statusText}.");
    }
}
