<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Models\AuditChecklist;
use App\Models\SelfEvaluationDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditChecklistController extends Controller
{
    public function store(Request $request, $detailId)
    {
        $request->validate([
            'item' => 'required|string',
            'note' => 'nullable|string',
        ]);

        $detail = SelfEvaluationDetail::findOrFail($detailId);

        $userRole = Auth::user()->userRole ?? null;

        AuditChecklist::create([
            'id'                        => AuditChecklist::generateNextId(),
            'self_evaluation_detail_id' => $detail->id,
            'item'                      => $request->item,
            'note'                      => $request->note,
            'created_by'                => $userRole?->id,
            'updated_by'                => $userRole?->id,
            'active'                    => true,
        ]);

        return back()->with('success', 'Item daftar tilik berhasil ditambahkan.');
    }

    public function destroy($checklistId)
    {
        $cl = AuditChecklist::findOrFail($checklistId);
        $cl->delete();

        return back()->with('success', 'Item daftar tilik dihapus.');
    }
}
