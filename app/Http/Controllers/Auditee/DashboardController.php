<?php

namespace App\Http\Controllers\Auditee;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\SelfEvaluationForm;
use App\Models\SelfEvaluationDetail;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function currentUserRole(): ?UserRole
    {
        $u = auth()->user();
        if (!$u) {
            return null;
        }

        // Relasi langsung
        if (method_exists($u, 'userRole') && $u->userRole) {
            return $u->userRole;
        }

        // Fallback: kolom user_role_id
        if (!empty($u->user_role_id)) {
            if ($ur = UserRole::find($u->user_role_id)) {
                return $ur;
            }
        }

        // Fallback: berdasarkan cis_user_id
        if (!empty($u->cis_user_id)) {
            $ur = UserRole::where('cis_user_id', $u->cis_user_id)
                ->where('active', 1)
                ->first()
                ?? UserRole::where('cis_user_id', $u->cis_user_id)
                    ->latest('created_at')
                    ->first();

            if ($ur) {
                return $ur;
            }
        }

        // Fallback terakhir
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
        $user     = auth()->user();

        $ur               = $this->currentUserRole();
        $categoryDetailId = $ur?->category_detail_id;
        $currentRoleId    = $ur?->role_id;

        $form              = null;
        $isMemberForm      = false;
        $progress          = ['total' => 0, 'terisi' => 0, 'percent' => 0.0];
        $unfilled          = collect();
        $recent            = collect();
        $statsKetercapaian = [
            'Melampaui'      => 0,
            'Mencapai'       => 0,
            'Tidak Mencapai' => 0,
            'Menyimpang'     => 0,
            'Kosong'         => 0,
        ];
        $canSubmit     = false;
        $lastUpdatedAt = null;

        if ($academic && $user) {
            // 1. coba sebagai KETUA (category_detail_id)
            if ($categoryDetailId) {
                $form = SelfEvaluationForm::with(['status', 'categoryDetail'])
                    ->where('academic_config_id', $academic->id)
                    ->where('category_detail_id', $categoryDetailId)
                    ->first();
            }

            // 2. kalau tidak ada, cek sebagai ANGGOTA
            if (!$form) {
                $form = SelfEvaluationForm::with(['status', 'categoryDetail'])
                    ->where('academic_config_id', $academic->id)
                    ->where(function ($q) use ($user) {
                        $q->where('member_auditee_1_user_id', $user->id)
                            ->orWhere('member_auditee_2_user_id', $user->id)
                            ->orWhere('member_auditee_3_user_id', $user->id);
                    })
                    ->first();

                if ($form) {
                    $isMemberForm     = true;
                    $categoryDetailId = $form->category_detail_id;
                }
            }

            if ($form) {
                // ========== BASE QUERY DETAILS ==========
                $detailsQuery = SelfEvaluationDetail::query()
                    ->with(['indicator.standard', 'standardAchievement', 'updatedBy'])
                    ->leftJoin('users as u', 'u.id', '=', 'self_evaluation_details.updated_by')
                    ->where('self_evaluation_form_id', $form->id)
                    ->whereHas('indicator', function ($q) use ($academic) {
                        $q->where('ami_standard_indicators.active', 1)
                            ->whereExists(function ($qq) use ($academic) {
                                $qq->select(DB::raw(1))
                                    ->from('ami_standards as s')
                                    ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                                    ->where('s.active', 1)
                                    ->where('s.academic_config_id', $academic->id);
                            });
                    });

                // ketua + punya role → filter by PIC
                if (!$isMemberForm && $currentRoleId) {
                    $detailsQuery->whereHas('indicator', function ($q) use ($currentRoleId) {
                        $q->whereExists(function ($qp) use ($currentRoleId) {
                            $qp->select(DB::raw(1))
                                ->from('ami_standard_indicator_pic as p')
                                ->whereColumn('p.standard_indicator_id', 'ami_standard_indicators.id')
                                ->where('p.active', 1)
                                ->where('p.role_id', $currentRoleId);
                        });
                    });
                }

                $details = $detailsQuery
                    ->orderBy('ami_standard_indicator_id')
                    ->get([
                        'self_evaluation_details.*',
                        DB::raw('u.name as updater_name'),
                        DB::raw('u.username as updater_username'),
                    ]);

                // ========== PROGRESS ==========
                $total  = $details->count();
                $terisi = $details->filter(function ($d) {
                    $hasil = isset($d->result) ? trim((string) $d->result) : '';
                    return !is_null($d->standard_achievement_id) || $hasil !== '';
                })->count();

                $progress = [
                    'total'   => $total,
                    'terisi'  => $terisi,
                    'percent' => $total ? round(100 * $terisi / $total, 1) : 0.0,
                ];

                // BUTIR BELUM TERISI (maks 5)
                $unfilled = $details->filter(function ($d) {
                    $hasil = isset($d->result) ? trim((string) $d->result) : '';
                    return is_null($d->standard_achievement_id) && $hasil === '';
                })->values()->take(5);

                // AKTIVITAS TERAKHIR (maks 5)
                $recent = $details->sortByDesc('updated_at')->values()->take(5);

                // STATISTIK KETERCAPAIAN
                $grouped = $details->groupBy(function ($d) {
                    return optional($d->standardAchievement)->name ?: 'Kosong';
                })->map(function ($g) {
                    return $g->count();
                })->toArray();

                foreach (array_keys($statsKetercapaian) as $k) {
                    $statsKetercapaian[$k] = $grouped[$k] ?? 0;
                }

                $statusName    = $form->status->name ?? 'Draft';
                $canSubmit     = ($total > 0 && $terisi === $total && $statusName !== 'Dikirim');
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
            'lastUpdatedAt',
            'isMemberForm'
        ));
    }
}
