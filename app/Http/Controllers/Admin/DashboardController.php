<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AmiStandard;
use App\Models\AmiStandardIndicator;
use App\Models\AmiStandardIndicatorPic;
use App\Models\SelfEvaluationForm;
use App\Models\SelfEvaluationDetail;
use App\Models\StandardAchievement;
use App\Models\Role;
use App\Models\EvaluationStatus;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // ===== TA aktif =====
        $activeAc = AcademicConfig::query()->where('active', 1)->first();

        // ===== Ringkasan =====
        $stdQuery = AmiStandard::query()
            ->with('academicConfig:id,academic_code,active')
            ->whereHas('academicConfig', fn($q) => $q->where('active', 1));

        $indQuery = AmiStandardIndicator::query()
            ->whereHas(
                'standard',
                fn($q) =>
                $q->whereHas('academicConfig', fn($qq) => $qq->where('active', 1))
            );

        $counts = [
            'standards' => (clone $stdQuery)->count(),
            'indicators' => (clone $indQuery)->count(),
            'roles' => Role::where('active', 1)->count(),
            'pics' => AmiStandardIndicatorPic::count(),
        ];

        // ===== Gap data =====
        $standardsNoIndicators = (clone $stdQuery)
            ->withCount('indicators')
            ->having('indicators_count', '=', 0)
            ->take(10)
            ->get(['id', 'name', 'academic_config_id']);

        $indicatorsNoPic = (clone $indQuery)
            ->doesntHave('pics')
            ->orderBy('standard_id')->orderBy('id')
            ->take(10)
            ->get(['id', 'standard_id', 'description']); // tidak ada code/name di indikator

        // ===== FED pada TA aktif =====
        $submittedId = EvaluationStatus::where('name', 'Dikirim')->value('id');

        $formsActiveTA = SelfEvaluationForm::query()
            ->where('academic_config_id', optional($activeAc)->id)
            ->with(['categoryDetail:id,name'])
            ->get(['id', 'category_detail_id', 'status_id', 'updated_at', 'submitted_at']);

        $queueSubmitted = $formsActiveTA->where('status_id', $submittedId)->values();

        // Detail FED untuk progress dan statistik
        $detailsActiveTA = SelfEvaluationDetail::query()
            ->whereIn('self_evaluation_form_id', $formsActiveTA->pluck('id'))
            ->get([
                'id',
                'self_evaluation_form_id',
                'ami_standard_indicator_id',
                'standard_achievement_id',
                'result',
                'updated_at',
                'updated_by',
            ]);

        $filledByForm = $detailsActiveTA
            ->groupBy('self_evaluation_form_id')
            ->map(function ($g) {
                $t = $g->count();
                $f = $g->filter(
                    fn($d) =>
                    !is_null($d->standard_achievement_id)
                    || (isset($d->result) && trim((string) $d->result) !== '')
                )->count();
                return ['total' => $t, 'terisi' => $f, 'percent' => $t ? round(100 * $f / $t, 1) : 0.0];
            });

        // Aktivitas terbaru (join nama updater)
        $recent = SelfEvaluationDetail::query()
            ->leftJoin('user_roles as ur', 'ur.id', '=', 'self_evaluation_details.updated_by')
            ->leftJoin('users as u', 'u.cis_user_id', '=', 'ur.cis_user_id')
            ->whereIn('self_evaluation_details.self_evaluation_form_id', $formsActiveTA->pluck('id'))
            ->orderByDesc('self_evaluation_details.updated_at')
            ->limit(10)
            ->get([
                'self_evaluation_details.*',
                DB::raw('u.name as updater_name'),
                DB::raw('u.username as updater_username'),
            ]);

        // Peta indikator (ambil standard->name + description)
        $indicatorMap = AmiStandardIndicator::query()
            ->with(['standard:id,name'])
            ->whereIn('id', $detailsActiveTA->pluck('ami_standard_indicator_id')->filter()->unique())
            ->get(['id', 'standard_id', 'description'])
            ->keyBy('id');

        // Statistik ketercapaian
        $statsK = [
            'Melampaui' => 0,
            'Mencapai' => 0,
            'Tidak Mencapai' => 0,
            'Menyimpang' => 0,
            'Kosong' => 0,
        ];
        $kMap = StandardAchievement::pluck('name', 'id');
        foreach ($detailsActiveTA as $d) {
            $name = $d->standard_achievement_id ? ($kMap[$d->standard_achievement_id] ?? 'Kosong') : 'Kosong';
            $statsK[$name] = ($statsK[$name] ?? 0) + 1;
        }

        // Coverage PIC per role
        $picCoverage = AmiStandardIndicatorPic::query()
            ->select('role_id', DB::raw('COUNT(*) as c'))
            ->groupBy('role_id')
            ->with('role:id,name')
            ->orderByDesc('c')
            ->take(8)
            ->get();

        return view('admin.dashboard', compact(
            'activeAc',
            'counts',
            'standardsNoIndicators',
            'indicatorsNoPic',
            'queueSubmitted',
            'filledByForm',
            'recent',
            'statsK',
            'picCoverage',
            'indicatorMap'
        ));
    }
}
