<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmiStandard;
use App\Models\AmiStandardIndicator;
use App\Models\Role;
use Illuminate\Http\Request;

class AmiIndicatorController extends Controller
{
    public function index(Request $request)
    {
$standardId = $request->query('standard_id');
    $perPage    = (int) $request->query('per_page', 10);
    if (!in_array($perPage, [10, 25, 50, 100], true)) {
        $perPage = 10;
    }

    // data tabel indikator
    $rows = AmiStandardIndicator::query()
        ->with([
            'standard:id,name,academic_config_id',
            'standard.academicConfig:id,academic_code,active',
            'pics.role:id,name',
        ])
        ->when($standardId, fn ($q) => $q->where('standard_id', $standardId))
        ->orderBy('standard_id')
        ->orderBy('id')
        ->paginate($perPage)
        ->withQueryString(); // bawa terus ?standard_id & ?per_page

    // dropdown standar (hanya TA aktif)
    $standards = AmiStandard::query()
        ->with('academicConfig:id,academic_code,active')
        ->whereHas('academicConfig', fn ($q) => $q->where('active', true))
        ->orderBy('name')
        ->get(['id', 'name', 'academic_config_id']);

    // info standar yang difilter (untuk alert biru di atas tabel)
    $selectedStandard = $standardId
        ? AmiStandard::query()
            ->with('academicConfig:id,academic_code,active')
            ->select('id', 'name', 'academic_config_id')
            ->find($standardId)
        : null;

    // list role buat modal PIC
    $roles = Role::query()
        ->where('active', true)
        ->orderBy('name')
        ->get(['id', 'name']);

    return view('admin.ami.indicator', compact('rows', 'standards', 'selectedStandard', 'roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string',
            'standard_id' => 'required|string|exists:ami_standards,id',
        ]);

        $std = AmiStandard::with('academicConfig:id,active')->findOrFail($request->get('standard_id'));
        if (!$std->academicConfig || !$std->academicConfig->active) {
            return redirect()->route('admin.ami.indicator')->with('error', 'The selected standard is not associated with an active Academic Config.');
        }

        $amiIndicator = new AmiStandardIndicator([
            'id' => AmiStandardIndicator::generateNextId(),
            'description' => $request->get('description'),
            'standard_id' => $request->get('standard_id'),
        ]);

        $amiIndicator->save();
        return redirect()->route('admin.ami.indicator')->with('success', 'AMI Indicator created successfully.');
    }

    public function update(Request $request, AmiStandardIndicator $amiIndicator)
    {
        $request->validate([
            'description' => 'required|string',
            'standard_id' => 'required|string|exists:ami_standards,id',
        ]);

        $std = AmiStandard::with('academicConfig:id,active')->findOrFail($request->get('standard_id'));
        if (!$std->academicConfig || !$std->academicConfig->active) {
            return redirect()->route('admin.ami.indicator')->with('error', 'The selected standard is not associated with an active Academic Config.');
        }

        $data = [
            'description' => $request->get('description'),
            'standard_id' => $request->get('standard_id'),
        ];

        $amiIndicator->update($data);

        return redirect()->route('admin.ami.indicator')->with('success', 'AMI Indicator updated successfully.');
    }

    public function destroy($id)
    {
        $amiIndicator = AmiStandardIndicator::findOrFail($id);
        $amiIndicator->delete();
        return redirect()->route('admin.ami.indicator')->with('success', 'AMI Indicator deleted successfully.');
    }
}
