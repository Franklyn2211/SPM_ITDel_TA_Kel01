<?php

namespace App\Http\Controllers\Auditee;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\EvaluasiDiri;
use App\Models\EvaluasiDiriDetail;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function currentUserRole(): ?UserRole
    {
        $u = auth()->user();
        if (!$u) return null;

        if (method_exists($u, 'userRole') && $u->userRole) return $u->userRole;

        if (!empty($u->user_role_id)) {
            if ($ur = UserRole::find($u->user_role_id)) return $ur;
        }

        if (!empty($u->cis_user_id)) {
            $ur = UserRole::where('cis_user_id', $u->cis_user_id)->where('active', 1)->first()
               ?? UserRole::where('cis_user_id', $u->cis_user_id)->latest('created_at')->first();
            if ($ur) return $ur;
        }

        return UserRole::where('id', $u->id)->where('active', 1)->first()
            ?? UserRole::where('id', $u->id)->latest('created_at')->first();
    }

    private function activeAcademic(): ?AcademicConfig
    {
        return AcademicConfig::where('active', 1)->first();
    }

    public function index(Request $request)
    {
        $academic = $this->activeAcademic();

        $ur               = $this->currentUserRole();
        $categoryDetailId = $ur?->category_detail_id;
        $currentRoleId    = $ur?->role_id;

        $form           = null;
        $progress       = ['total' => 0, 'terisi' => 0, 'percent' => 0.0];
        $unfilled       = collect();
        $recent         = collect();
        $statsKetercapaian = [
            'Melampaui' => 0,
            'Mencapai' => 0,
            'Tidak Mencapai' => 0,
            'Menyimpang' => 0,
            'Kosong' => 0,
        ];
        $canSubmit      = false;
        $lastUpdatedAt  = null;

        if ($academic && $categoryDetailId) {
            $form = EvaluasiDiri::with('status')
                ->where('academic_config_id', $academic->id)
                ->where('category_detail_id', $categoryDetailId)
                ->first();

            if ($form && $currentRoleId) {
                // Detail hanya indikator yg jadi PIC role login
                $details = EvaluasiDiriDetail::with(['AmiStandardIndicator','KetercapaianStandard'])
                    ->leftJoin('user_roles as ur', 'ur.id', '=', 'form_evaluasi_diri_detail.updated_by')
                    ->leftJoin('users as u', 'u.cis_user_id', '=', 'ur.cis_user_id')
                    ->where('form_evaluasi_diri_id', $form->id)
                    ->whereHas('AmiStandardIndicator', function ($q) use ($academic, $currentRoleId) {
                        $q->where('ami_standard_indicators.active', 1)
                          ->whereExists(function ($qq) use ($academic) {
                              $qq->select(DB::raw(1))
                                 ->from('ami_standards as s')
                                 ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                                 ->where('s.active', 1)
                                 ->where('s.academic_config_id', $academic->id);
                          })
                          ->whereExists(function ($qp) use ($currentRoleId) {
                              $qp->select(DB::raw(1))
                                 ->from('ami_standard_indicator_pic as p')
                                 ->whereColumn('p.standard_indicator_id', 'ami_standard_indicators.id')
                                 ->where('p.active', 1)
                                 ->where('p.role_id', $currentRoleId);
                          });
                    })
                    ->orderBy('ami_standard_indicator_id')
                    ->get([
                        'form_evaluasi_diri_detail.*',
                        DB::raw('u.name as updater_name'),
                        DB::raw('u.username as updater_username'),
                        DB::raw('ur.id as updater_role_id'),
                    ]);

                $total  = $details->count();
                $terisi = $details->filter(function ($d) {
                    $hasil = isset($d->hasil) ? trim((string)$d->hasil) : '';
                    return !is_null($d->ketercapaian_standard_id) || $hasil !== '';
                })->count();

                $progress = [
                    'total'   => $total,
                    'terisi'  => $terisi,
                    'percent' => $total ? round(100 * $terisi / $total, 1) : 0.0,
                ];

                $unfilled = $details
                    ->filter(function ($d) {
                        $hasil = isset($d->hasil) ? trim((string)$d->hasil) : '';
                        return is_null($d->ketercapaian_standard_id) && $hasil === '';
                    })
                    ->values()
                    ->take(10);

                $recent = $details->sortByDesc('updated_at')->values()->take(10);

                $grouped = $details->groupBy(function ($d) {
                    return optional($d->KetercapaianStandard)->name ?: 'Kosong';
                })->map->count()->toArray();

                foreach (array_keys($statsKetercapaian) as $k) {
                    $statsKetercapaian[$k] = $grouped[$k] ?? 0;
                }

                // Submit boleh kalau semua indikator milik ROLE LOGIN sudah terisi dan form belum "Dikirim".
                $statusName = $form->status->name ?? 'Draft';
                $canSubmit  = ($total > 0 && $terisi === $total && $statusName !== 'Dikirim');

                $lastUpdatedAt = $recent->first()->updated_at ?? $form->updated_at;
            }
        }

        return view('auditee.dashboard', compact(
            'academic',
            'form',
            'progress',
            'unfilled',
            'recent',
            'statsKetercapaian',
            'canSubmit',
            'lastUpdatedAt'
        ));
    }
}
