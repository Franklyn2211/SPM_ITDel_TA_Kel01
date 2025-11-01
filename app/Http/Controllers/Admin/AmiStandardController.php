<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmiStandard;
use Illuminate\Http\Request;

class AmiStandardController extends Controller
{
    public function index()
    {
        $rows = AmiStandard::query()
            ->with(['academicConfig:id,academic_code,active'])
            ->where('ami_standards.active', 1)
            ->whereHas('academicConfig', fn($q) => $q->where('active', 1))
            ->withCount(['indicators as indicators_count' => function ($q) {
                $q->where('active', 1);
            }])
            ->orderBy('id')
            ->paginate(20);

        return view('admin.ami.standard', compact('rows'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $amiStandard = new AmiStandard([
            'id'   => AmiStandard::generateNextId(),
            'name' => $request->get('name'),
            'active' => true,
        ]);

        $amiStandard->save();
        return redirect()->route('admin.ami.standard')->with('success', 'Standar AMI berhasil dibuat.');
    }

    public function update(Request $request, AmiStandard $amiStandard)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $amiStandard->update([
            'name' => $request->get('name'),
        ]);

        return redirect()->route('admin.ami.standard')->with('success', 'Standar AMI berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $amiStandard = AmiStandard::findOrFail($id);
        $amiStandard->delete();
        return redirect()->route('admin.ami.standard')->with('success', 'Standar AMI berhasil dihapus.');
    }
}
