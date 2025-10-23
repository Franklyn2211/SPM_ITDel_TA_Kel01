<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicConfigController extends Controller
{
    public function index()
    {
        $academicConfigs = AcademicConfig::all();
        return view('admin.academic_config.index', ['academicConfigs' => $academicConfigs]);
    }

    public function store(Request $request)
{
    $data = $request->validate([
        'academic_code' => 'required|string|unique:academic_configs,academic_code',
        'name' => 'required|string|max:255',
    ]);

    DB::transaction(function () use ($data) {
        // Step 1: matikan yang lain
        AcademicConfig::where('active', true)->update(['active' => false]);

        // Step 2: buat yang baru dan aktifkan dia
        $ac = new AcademicConfig([
            'id'            => AcademicConfig::generateNextId(),
            'academic_code' => $data['academic_code'],
            'name'          => $data['name'],
            'active'        => true, // jangan andalkan default
        ]);

        $ac->save();
    });

    return redirect()
        ->route('admin.academic_config.index')
        ->with('success', 'Academic Config created successfully.');
}

    public function update(Request $request, AcademicConfig $academicConfig)
    {
        $request->validate([
            'academic_code' => 'required|string|unique:academic_configs,academic_code,' . $academicConfig->id . ',id',
            'name' => 'required|string|max:255',
        ]);

        $data = [
            'academic_code' => $request->get('academic_code'),
            'name' => $request->get('name'),
        ];

        $academicConfig->update($data);

        return redirect()->route('admin.academic_config.index')->with('success', 'Academic Config updated successfully.');
    }

    public function destroy($id)
    {
        $academicConfig = AcademicConfig::findOrFail($id);
        $academicConfig->delete();
        return redirect()->route('admin.academic_config.index')->with('success', 'Academic Config deleted successfully.');
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
    return back()->with('success', "Status konfigurasi {$academicConfig->name} berhasil {$statusText}.");
}

}
