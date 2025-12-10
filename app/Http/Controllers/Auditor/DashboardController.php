<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Models\SelfEvaluationForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $userRole = $user->userRole ?? null; // mengikuti pola di model kamu

        // Query FED yang relevan untuk auditor ini
        $formsQuery = SelfEvaluationForm::query()
            ->with(['categoryDetail', 'academicConfig', 'status'])
            ->where('active', 1);

        // Kalau userRole menyimpan mapping ke academic_config & category_detail
        if ($userRole) {
            if (!empty($userRole->academic_config_id)) {
                $formsQuery->where('academic_config_id', $userRole->academic_config_id);
            }

            if (!empty($userRole->category_detail_id)) {
                $formsQuery->where('category_detail_id', $userRole->category_detail_id);
            }
        }

        $forms = $formsQuery->orderBy('updated_at', 'desc')->get();

        // Ringkasan status
        $summary = [
            'total' => $forms->count(),
            'draft' => $forms->filter(fn($f) => ($f->status->name ?? '') === 'Draft')->count(),
            'submitted' => $forms->filter(fn($f) => ($f->status->name ?? '') === 'Dikirim')->count(),
            'approved' => $forms->filter(fn($f) => ($f->status->name ?? '') === 'Disetujui')->count(),
            'rejected' => $forms->filter(fn($f) => ($f->status->name ?? '') === 'Ditolak')->count(),
        ];

        // Ambil tahun akademik aktif (kalau ada data)
        $activeAcademic = $forms->first()->academicConfig ?? null;

        // FED sample (sebagian rekap FED untuk dashboard auditor, mirip admin)
        $formIds = $forms->pluck('id');
        $details = \App\Models\SelfEvaluationDetail::query()
            ->whereIn('self_evaluation_form_id', $formIds)
            ->with('indicator:id,standard_id')
            ->get();
        $detailsByForm = $details->groupBy('self_evaluation_form_id');

        // Ambil sample rekap FED seperti admin: gabungkan semua kategori, urut nama, ambil 5 teratas
        $categoryDetails = \App\Models\RefCategoryDetail::query()
            ->with('category')
            ->where('active', 1)
            ->get();

        $formsActiveTA = \App\Models\SelfEvaluationForm::query()
            ->where('active', 1)
            ->with(['categoryDetail.category'])
            ->get()
            ->keyBy('category_detail_id');

        $detailsActiveTA = \App\Models\SelfEvaluationDetail::query()
            ->whereIn('self_evaluation_form_id', $formsActiveTA->pluck('id'))
            ->with('indicator:id,standard_id')
            ->get();
        $detailsByForm = $detailsActiveTA->groupBy('self_evaluation_form_id');

        $fedSampleRows = [];
        foreach ($categoryDetails as $cd) {
            $unitName = $cd->name ?? ('Unit #' . $cd->id);
            $form = $formsActiveTA->get($cd->id);
            $formId = $form->id ?? null;
            $formDetails = $formId ? $detailsByForm->get($formId, collect()) : collect();
            $filled = $formDetails->filter(function ($d) {
                return !is_null($d->standard_achievement_id)
                    || (isset($d->result) && trim((string) $d->result) !== '');
            })->count();
            $total = $formDetails->count();
            $percent = $total ? round(100 * $filled / $total, 1) : 0.0;
            $submittedAt = $form && $form->submitted_at ? \Illuminate\Support\Carbon::parse($form->submitted_at)->translatedFormat('d M Y') : '—';
            $fedSampleRows[] = [
                'name' => $unitName,
                'filled' => $filled,
                'total' => $total,
                'percent' => $percent,
                'submitted_at' => $submittedAt,
            ];
        }
        $fedSample = collect($fedSampleRows)->sortBy('name')->take(5)->values()->all();

        return view('auditor.dashboard', [
            'forms' => $forms,
            'summary' => $summary,
            'activeAcademic' => $activeAcademic,
            'fedSample' => $fedSample,
        ]);
    }
}
