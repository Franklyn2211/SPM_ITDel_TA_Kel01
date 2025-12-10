<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Models\SelfEvaluationForm;
use App\Models\SelfEvaluationDetail;
use App\Models\StandardAchievement;
use App\Models\EvaluationStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FedReviewController extends Controller
{
    public function index()
    {
        $forms = SelfEvaluationForm::with(['categoryDetail', 'academicConfig', 'status'])
            ->where('active', 1)
            ->whereHas('status', fn ($q) => $q->where('name', 'Dikirim'))
            ->orderBy('category_detail_id')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('auditor.fed.index', compact('forms'));
    }

    public function show($formId)
{
    $form = SelfEvaluationForm::with([
        'categoryDetail',
        'academicConfig',
        'status',
        'details.indicator',
        'details.status',
        'details.auditChecklists',
    ])->findOrFail($formId);

    $opsiKetercapaian = StandardAchievement::where('active', 1)
        ->get();

    return view('auditor.fed.show', [
        'form'              => $form,
        'opsiKetercapaian'  => $opsiKetercapaian,
    ]);
}

    public function approveDetail($formId, $detailId)
    {
        $detail = SelfEvaluationDetail::where('self_evaluation_form_id', $formId)
            ->with('status')
            ->findOrFail($detailId);

        // cuma boleh approve kalau sekarang "Dikirim"
        if (($detail->status->name ?? null) !== 'Dikirim') {
            return back()->with('error', 'Indikator ini sudah diproses dan tidak bisa diubah lagi.');
        }

        $approvedStatusId = EvaluationStatus::where('name', 'Disetujui')->value('id');

        $detail->status_id  = $approvedStatusId;
        $detail->updated_by = Auth::user()->userRole->id ?? null;
        $detail->save();

        return back()->with('success', 'Indikator berhasil disetujui.');
    }

    public function rejectDetail($formId, $detailId)
    {
        $detail = SelfEvaluationDetail::where('self_evaluation_form_id', $formId)
            ->with('status')
            ->findOrFail($detailId);

        // cuma boleh reject kalau sekarang "Dikirim"
        if (($detail->status->name ?? null) !== 'Dikirim') {
            return back()->with('error', 'Indikator ini sudah diproses dan tidak bisa diubah lagi.');
        }

        $rejectedStatusId = EvaluationStatus::where('name', 'Ditolak')->value('id');

        $detail->status_id  = $rejectedStatusId;
        $detail->updated_by = Auth::user()->userRole->id ?? null;
        $detail->save();

        return back()->with('success', 'Indikator ditolak. Silakan isi daftar tilik dan lakukan perbaikan pada isi FED.');
    }

    // dipanggil dari popup; hanya boleh kalau status sekarang "Ditolak"
    // setelah disimpan -> auto Disetujui
    public function updateDetail(Request $request, $formId, $detailId)
{
    $request->validate([
        'ketercapaian_standard_id'      => 'nullable|string',
        'hasil'                         => 'required|string',
        'bukti_pendukung'               => 'nullable|string',
        'faktor_penghambat_pendukung'   => 'nullable|string',
    ]);

    $detail = SelfEvaluationDetail::where('self_evaluation_form_id', $formId)
        ->with('status')
        ->findOrFail($detailId);

    if (($detail->status->name ?? null) !== 'Ditolak') {
        return back()->with('error', 'Isi FED hanya bisa diubah saat status indikator Ditolak.');
    }

    $approvedStatusId = EvaluationStatus::where('name', 'Disetujui')->value('id');

    $detail->standard_achievement_id = $request->ketercapaian_standard_id;
    $detail->result                  = $request->hasil;
    $detail->supporting_evidence     = $request->bukti_pendukung;
    $detail->contributing_factors    = $request->faktor_penghambat_pendukung;
    $detail->status_id               = $approvedStatusId;
    $detail->updated_by              = Auth::user()->userRole->id ?? null;
    $detail->save();

    return back()->with('success', 'Isi FED telah diperbarui dan indikator dinyatakan disetujui.');
}

}
