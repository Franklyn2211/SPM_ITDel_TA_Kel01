<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\SelfEvaluationForm;
use Illuminate\Http\Request;

class AuditChecklistRecapController extends Controller
{
    private function activeAcademicId(): ?string
    {
        return AcademicConfig::where('active', true)->value('id');
    }

    public function index(Request $request)
    {
        $academicId = $this->activeAcademicId();
        abort_unless($academicId, 403, 'Tahun akademik aktif belum diset.');

        $q = trim((string) $request->query('q', ''));

        // List FED + jumlah checklist aktif (akumulasi dari semua detail)
        $feds = SelfEvaluationForm::query()
            ->with(['categoryDetail', 'academicConfig'])
            ->where('academic_config_id', $academicId)
            ->where('active', 1)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('categoryDetail', fn($x) => $x->where('name', 'like', "%{$q}%"));
            })
            ->withCount([
                // hitung checklist aktif lewat relasi details -> auditChecklists
                'details as checklist_count' => function ($qq) {
                    $qq->where('active', 1)
                       ->whereHas('auditChecklists', fn($x) => $x->where('active', 1));
                },
            ])
            ->orderBy('category_detail_id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.audit_checklists.index', compact('feds', 'q'));
    }

    public function show(SelfEvaluationForm $form)
    {
        $academicId = $this->activeAcademicId();
        abort_unless($academicId && $form->academic_config_id === $academicId, 403, 'FED bukan tahun aktif.');

        // Ambil detail indikator + checklist aktif
        $details = $form->details()
            ->with([
                'status',
                'standardAchievement',
                'indicator.standard',
                'auditChecklists' => fn($q) => $q->where('active', 1)->orderBy('id'),
            ])
            ->where('active', 1)
            ->orderBy('ami_standard_indicator_id')
            ->get();

        $totalChecklist = $details->sum(fn($d) => $d->auditChecklists->count());

        return view('admin.audit_checklists.show', compact('form', 'details', 'totalChecklist'));
    }
}
