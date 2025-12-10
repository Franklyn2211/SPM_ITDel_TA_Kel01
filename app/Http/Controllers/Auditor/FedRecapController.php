<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AmiStandardIndicator;
use App\Models\AmiStandardIndicatorPic;
use App\Models\SelfEvaluationForm;
use App\Models\SelfEvaluationDetail;
use App\Models\StandardAchievement;
use App\Models\RefCategoryDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class FedRecapController extends Controller
{
    public function index(Request $request)
    {
        $activeAc = AcademicConfig::query()
            ->where('active', 1)
            ->first();

        // Kalau TA aktif belum diset, balikin paginator kosong
        if (!$activeAc) {
            $emptyPaginator = new LengthAwarePaginator([], 0, 10, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

            return view('auditor.fed_rekap.index', [
                'title' => 'Rekap Form Evaluasi Diri',
                'category' => 'prodi',
                'sort' => 'name',
                'dir' => 'asc',
                'recapItems' => $emptyPaginator,
            ]);
        }

        // ======================= DATA DASAR TA AKTIF =======================

        // master unit/fakultas/prodi
        $categoryDetails = RefCategoryDetail::query()
            ->with('category')
            ->where('active', 1)
            ->get();

        // form FED per unit untuk TA aktif
        $formsActiveTA = SelfEvaluationForm::query()
            ->where('academic_config_id', $activeAc->id)
            ->with(['categoryDetail.category'])
            ->get([
                'id',
                'category_detail_id',
                'status_id',
                'updated_at',
                'submitted_at',
            ])
            ->keyBy('category_detail_id');

        // detail FED
        $detailsActiveTA = SelfEvaluationDetail::query()
            ->whereIn('self_evaluation_form_id', $formsActiveTA->pluck('id'))
            ->with('indicator:id,standard_id')
            ->get([
                'id',
                'self_evaluation_form_id',
                'ami_standard_indicator_id',
                'standard_achievement_id',
                'result',
                'updated_at',
                'updated_by',
            ]);

        $detailsByForm = $detailsActiveTA->groupBy('self_evaluation_form_id');

        // progress per form
        $filledByForm = $detailsByForm->map(function ($g) {
            $total = $g->count();
            $filled = $g->filter(function ($d) {
                return !is_null($d->standard_achievement_id)
                    || (isset($d->result) && trim((string) $d->result) !== '');
            })->count();

            return [
                'total' => $total,
                'terisi' => $filled,
                'percent' => $total ? round(100 * $filled / $total, 1) : 0.0,
            ];
        });

        // peta indikator / standar / PIC
        $allIndicatorIds = $detailsActiveTA
            ->pluck('indicator.id')
            ->filter()
            ->unique();

        $indicatorMap = AmiStandardIndicator::query()
            ->whereIn('id', $allIndicatorIds)
            ->get(['id', 'standard_id'])
            ->keyBy('id');

        $picsByIndicator = AmiStandardIndicatorPic::query()
            ->whereIn('standard_indicator_id', $allIndicatorIds)
            ->get(['standard_indicator_id', 'role_id'])
            ->groupBy('standard_indicator_id');

        // pimpinan per unit
        $leadersRaw = DB::table('user_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->leftJoin('users as u', 'u.cis_user_id', '=', 'ur.cis_user_id')
            ->where('ur.academic_config_id', $activeAc->id)
            ->where('ur.active', 1)
            ->select(
                'ur.category_detail_id',
                'r.name as role_name',
                'u.name as user_name'
            )
            ->get()
            ->groupBy('category_detail_id');

        // mapping ketercapaian
        $kMap = StandardAchievement::pluck('name', 'id');

        $emptyAch = [
            'Melampaui' => 0,
            'Mencapai' => 0,
            'Tidak Mencapai' => 0,
            'Menyimpang' => 0,
        ];

        $achievementsByForm = $detailsByForm->map(function ($g) use ($kMap, $emptyAch) {
            $acc = $emptyAch;
            foreach ($g as $d) {
                if (!$d->standard_achievement_id) {
                    continue;
                }
                $name = $kMap[$d->standard_achievement_id] ?? null;
                if (!$name || !array_key_exists($name, $acc)) {
                    continue;
                }
                $acc[$name]++;
            }
            return $acc;
        });

        // ======================= BUILD REKAP PER KATEGORI =======================

        $facultyRecap = [];
        $prodiRecap = [];
        $unitRecap = [];

        foreach ($categoryDetails as $cd) {
            $category = optional($cd->category)->name;
            $catLower = Str::lower((string) $category);
            $unitName = $cd->name ?? ('Unit #' . $cd->id);

            $form = $formsActiveTA->get($cd->id);
            $formId = $form->id ?? null;

            $formDetails = $formId
                ? $detailsByForm->get($formId, collect())
                : collect();

            $indicatorIds = $formDetails
                ->pluck('indicator.id')
                ->filter()
                ->unique();

            $standardIds = $indicatorIds
                ->map(function ($id) use ($indicatorMap) {
                    $ind = $indicatorMap->get($id);
                    return $ind ? $ind->standard_id : null;
                })
                ->filter()
                ->unique();

            $picsForUnit = $indicatorIds->flatMap(function ($id) use ($picsByIndicator) {
                return $picsByIndicator->get($id, collect());
            });

            // progress
            if ($formId && isset($filledByForm[$formId])) {
                $progress = $filledByForm[$formId];
            } else {
                $progress = [
                    'total' => $formDetails->count(),
                    'terisi' => 0,
                    'percent' => 0,
                ];
            }

            // ketercapaian
            $achUnit = $formId
                ? ($achievementsByForm[$formId] ?? $emptyAch)
                : $emptyAch;

            // pimpinan utama
            $leadersForUnit = $leadersRaw->get($cd->id, collect());
            $primary = null;

            if ($leadersForUnit->isNotEmpty()) {
                if ($catLower === 'fakultas') {
                    $primary = $leadersForUnit->first(function ($row) {
                        return Str::contains(Str::lower($row->role_name), 'dekan');
                    }) ?? $leadersForUnit->first();
                } elseif ($catLower === 'prodi' || $catLower === 'program studi') {
                    $primary = $leadersForUnit->first(function ($row) {
                        $rn = Str::lower($row->role_name);
                        return Str::contains($rn, 'kaprodi')
                            || Str::contains($rn, 'ketua program studi')
                            || Str::contains($rn, 'kepala program studi');
                    }) ?? $leadersForUnit->first();
                } else {
                    $primary = $leadersForUnit->first();
                }
            }

            // skip kalau primary role = auditor
            if ($primary && Str::lower(trim($primary->role_name)) === 'auditor') {
                continue;
            }

            $row = [
                'name' => $unitName,
                'standards' => $standardIds->count(),
                'indicators' => $indicatorIds->count(),
                'pics' => $picsForUnit->count(),
                'roles' => $picsForUnit->pluck('role_id')->filter()->unique()->count(),
                'total' => $progress['total'],
                'filled' => $progress['terisi'],
                'percent' => $progress['percent'],
                'submitted_at' => $form && $form->submitted_at
                    ? Carbon::parse($form->submitted_at)->translatedFormat('d M Y')
                    : null,
                'primary_role' => $primary->role_name ?? null,
                'primary_name' => $primary->user_name ?? null,
                'achievements' => $achUnit,
            ];

            if ($catLower === 'fakultas') {
                $facultyRecap[] = $row;
            } elseif ($catLower === 'prodi' || $catLower === 'program studi') {
                $prodiRecap[] = $row;
            } else {
                $unitRecap[] = $row;
            }
        }

        // sort default by name
        $facultyRecap = collect($facultyRecap)->sortBy('name')->values();
        $prodiRecap = collect($prodiRecap)->sortBy('name')->values();
        $unitRecap = collect($unitRecap)->sortBy('name')->values();

        // ======================= FILTER / SORT / PAGINATION =======================

        $category = $request->input('category', 'prodi');
        $sort = $request->input('sort', 'name');
        $dir = $request->input('dir', 'asc');

        // Handle 'Semua' category: gabungkan semua recap
        if ($category === 'semua') {
            $items = collect()
                ->concat($facultyRecap)
                ->concat($prodiRecap)
                ->concat($unitRecap);
            $title = 'Rekap Form Evaluasi Diri - Semua Kategori';
        } elseif ($category === 'fakultas') {
            $items = $facultyRecap;
            $title = 'Rekap Form Evaluasi Diri - Fakultas';
        } elseif ($category === 'unit') {
            $items = $unitRecap;
            $title = 'Rekap Form Evaluasi Diri - Unit';
        } else {
            $items = $prodiRecap;
            $title = 'Rekap Form Evaluasi Diri - Program Studi';
            $category = 'prodi';
        }

        $dirDesc = $dir === 'desc';

        if (in_array($sort, ['name', 'percent'])) {
            $items = $items->sortBy($sort, SORT_REGULAR, $dirDesc)->values();
        }

        $perPage = 10;
        $page = (int) $request->input('page', 1);

        $paged = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('auditor.fed_rekap.index', [
            'title' => $title,
            'category' => $category,
            'sort' => $sort,
            'dir' => $dir,
            'recapItems' => $paged,
        ]);
    }
}
