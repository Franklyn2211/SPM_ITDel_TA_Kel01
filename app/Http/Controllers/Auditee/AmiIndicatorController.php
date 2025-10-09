<?php

namespace App\Http\Controllers\Auditee;

use App\Http\Controllers\Controller;
use App\Models\AmiStandard;
use App\Models\AmiStandardIndicator;
use Illuminate\Http\Request;

class AmiIndicatorController extends Controller
{
    public function index()
    {
        $rows = AmiStandardIndicator::with([
            'standard:id,name,academic_config_id',
            'standard.academicConfig:id,academic_code,active',
        ])
        ->orderBy('id')
        ->paginate(20);

        $standards = AmiStandard::with('academicConfig:id,academic_code,active')
            ->whereHas('academicConfig', fn ($q) => $q->where('active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'academic_config_id']);

        return view('auditee.ami.indicator', compact('rows', 'standards'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string',
            'standard_id' => 'required|string|exists:ami_standards,id',
        ]);

        $std = AmiStandard::with('academicConfig:id,active')->findOrFail($request->get('standard_id'));
        if (!$std->academicConfig || !$std->academicConfig->active) {
            return redirect()->route('auditee.ami.indicator')->with('error', 'The selected standard is not associated with an active Academic Config.');
        }

        $amiIndicator = new AmiStandardIndicator([
            'id' => AmiStandardIndicator::generateNextId(),
            'description' => $request->get('description'),
            'standard_id' => $request->get('standard_id'),
        ]);

        $amiIndicator->save();
        return redirect()->route('auditee.ami.indicator')->with('success', 'AMI Indicator created successfully.');
    }

    public function update(Request $request, AmiStandardIndicator $amiIndicator)
    {
        $request->validate([
            'description' => 'required|string',
            'standard_id' => 'required|string|exists:ami_standards,id',
        ]);

        $std = AmiStandard::with('academicConfig:id,active')->findOrFail($request->get('standard_id'));
        if (!$std->academicConfig || !$std->academicConfig->active) {
            return redirect()->route('auditee.ami.indicator')->with('error', 'The selected standard is not associated with an active Academic Config.');
        }

        $data = [
            'description' => $request->get('description'),
            'standard_id' => $request->get('standard_id'),
        ];

        $amiIndicator->update($data);

        return redirect()->route('auditee.ami.indicator')->with('success', 'AMI Indicator updated successfully.');
    }

    public function destroy($id)
    {
        $amiIndicator = AmiStandardIndicator::findOrFail($id);
        $amiIndicator->delete();
        return redirect()->route('auditee.ami.indicator')->with('success', 'AMI Indicator deleted successfully.');
    }
}
