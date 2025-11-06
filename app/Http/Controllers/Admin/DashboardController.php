<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AmiStandard;
use App\Models\AmiStandardIndicator;
use App\Models\AmiStandardIndicatorPic;
use App\Models\EvaluasiDiri;
use App\Models\EvaluasiDiriDetail;
use App\Models\KetercapaianStandard;
use App\Models\Role;
use App\Models\StatusEvaluasi;
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
            ->whereHas('standard', fn($q) =>
                $q->whereHas('academicConfig', fn($qq) => $qq->where('active', 1))
            );

        $counts = [
            'standards'  => (clone $stdQuery)->count(),
            'indicators' => (clone $indQuery)->count(),
            'roles'      => Role::where('active', 1)->count(),
            'pics'       => AmiStandardIndicatorPic::count(),
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
        $submittedId = StatusEvaluasi::where('name', 'Dikirim')->value('id');

        $formsActiveTA = EvaluasiDiri::query()
            ->where('academic_config_id', optional($activeAc)->id)
            ->with(['categoryDetail:id,name'])
            ->get(['id','category_detail_id','status_id','updated_at','tanggal_submit']);

        $queueSubmitted = $formsActiveTA->where('status_id', $submittedId)->values();

        // Detail FED untuk progress dan statistik
        $detailsActiveTA = EvaluasiDiriDetail::query()
            ->whereIn('form_evaluasi_diri_id', $formsActiveTA->pluck('id'))
            ->get([
                'id',
                'form_evaluasi_diri_id',
                'ami_standard_indicator_id',
                'ketercapaian_standard_id',
                'hasil',
                'updated_at',
                'updated_by',
            ]);

        $filledByForm = $detailsActiveTA
            ->groupBy('form_evaluasi_diri_id')
            ->map(function ($g) {
                $t = $g->count();
                $f = $g->filter(fn($d) =>
                    !is_null($d->ketercapaian_standard_id)
                    || (isset($d->hasil) && trim((string) $d->hasil) !== '')
                )->count();
                return ['total' => $t, 'terisi' => $f, 'percent' => $t ? round(100 * $f / $t, 1) : 0.0];
            });

        // Aktivitas terbaru (join nama updater)
        $recent = EvaluasiDiriDetail::query()
            ->leftJoin('user_roles as ur', 'ur.id', '=', 'form_evaluasi_diri_detail.updated_by')
            ->leftJoin('users as u', 'u.cis_user_id', '=', 'ur.cis_user_id')
            ->whereIn('form_evaluasi_diri_detail.form_evaluasi_diri_id', $formsActiveTA->pluck('id'))
            ->orderByDesc('form_evaluasi_diri_detail.updated_at')
            ->limit(10)
            ->get([
                'form_evaluasi_diri_detail.*',
                DB::raw('u.name as updater_name'),
                DB::raw('u.username as updater_username'),
            ]);

        // Peta indikator (ambil standard->name + description)
        $indicatorMap = AmiStandardIndicator::query()
            ->with(['standard:id,name'])
            ->whereIn('id', $detailsActiveTA->pluck('ami_standard_indicator_id')->filter()->unique())
            ->get(['id','standard_id','description'])
            ->keyBy('id');

        // Statistik ketercapaian
        $statsK = [
            'Melampaui'      => 0,
            'Mencapai'       => 0,
            'Tidak Mencapai' => 0,
            'Menyimpang'     => 0,
            'Kosong'         => 0,
        ];
        $kMap = KetercapaianStandard::pluck('name', 'id');
        foreach ($detailsActiveTA as $d) {
            $name = $d->ketercapaian_standard_id ? ($kMap[$d->ketercapaian_standard_id] ?? 'Kosong') : 'Kosong';
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
