<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmiStandard;
use Illuminate\Http\Request;

class AmiStandardController extends Controller
{
    public function index()
    {
        $rows = AmiStandard::with(['academicConfig:id,academic_code,active'])
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
            'id' => AmiStandard::generateNextId(),
            'name' => $request->get('name'),
        ]);

        $amiStandard->save();
        return redirect()->route('admin.ami.standard')->with('success', 'AMI Standard created successfully.');
    }

    public function update(Request $request, AmiStandard $amiStandard)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $data = [
            'name' => $request->get('name')
        ];

        $amiStandard->update($data);

        return redirect()->route('admin.ami.standard')->with('success', 'AMI Standard updated successfully.');
    }

    public function destroy($id)
    {
        $amiStandard = AmiStandard::findOrFail($id);
        $amiStandard->delete();
        return redirect()->route('admin.ami.standard')->with('success', 'AMI Standard deleted successfully.');
    }
}
