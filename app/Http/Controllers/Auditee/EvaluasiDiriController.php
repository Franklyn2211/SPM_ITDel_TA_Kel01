<?php

namespace App\Http\Controllers\Auditee;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AmiStandardIndicator;
use App\Models\AmiStandardIndicatorPic;
use App\Models\SelfEvaluationForm;
use App\Models\SelfEvaluationDetail;
use App\Models\StandardAchievement;
use App\Models\EvaluationStatus;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\TextRun;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html as WordHtml;
use ConvertApi\ConvertApi;

class EvaluasiDiriController extends Controller
{
    private const TEMPLATE_PATH = 'templates/F-219_Formulir_Evaluasi_Diri_Auditee.docx';

    /* ================= Helpers ================= */

    private function currentUserRole(): ?UserRole
    {
        $u = auth()->user();
        if (!$u) {
            return null;
        }

        if (method_exists($u, 'userRole') && $u->userRole) {
            return $u->userRole;
        }

        if (!empty($u->user_role_id)) {
            if ($ur = UserRole::find($u->user_role_id)) {
                return $ur;
            }
        }

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

        return UserRole::where('id', $u->id)->where('active', 1)->first()
            ?? UserRole::where('id', $u->id)->latest('created_at')->first();
    }

    private function resolveCategoryDetailId(): ?string
    {
        return optional($this->currentUserRole())->category_detail_id;
    }

    private function resolveRoleId(): ?string
    {
        return optional($this->currentUserRole())->role_id;
    }

    private function activeAcademic(): ?AcademicConfig
    {
        return AcademicConfig::where('active', true)->first();
    }

    private function activeAcademicId(): ?string
    {
        return optional($this->activeAcademic())->id;
    }

    private function ensureFormOwnedByUser(SelfEvaluationForm $form): void
    {
        $userCat = $this->resolveCategoryDetailId();

        $isSameUnit = $userCat && $form->category_detail_id === $userCat;
        $isMember   = $this->currentUserIsFormMember($form);

        abort_unless($isSameUnit || $isMember, 403, 'Tidak berhak mengakses form ini.');
    }

    private function ensureFormOnActiveYear(SelfEvaluationForm $form): void
    {
        $activeId = $this->activeAcademicId();
        abort_unless($activeId && $form->academic_config_id === $activeId, 403, 'Form ini bukan untuk tahun akademik aktif.');
    }

    private function currentUserIsFormMember(SelfEvaluationForm $form): bool
    {
        $userId = auth()->id();
        if (!$userId) {
            return false;
        }

        return in_array($userId, [
            $form->member_auditee_1_user_id,
            $form->member_auditee_2_user_id,
            $form->member_auditee_3_user_id,
        ], true);
    }

    /**
     * Sinkron butir berdasarkan PIC role yang login.
     */
    private function syncDetailsWithPIC(SelfEvaluationForm $form, string $currentRoleId): void
    {
        $academicId = $form->academic_config_id;

        $picIndicatorIds = AmiStandardIndicatorPic::where('role_id', $currentRoleId)
            ->where('active', 1)
            ->pluck('standard_indicator_id')
            ->unique();

        if ($picIndicatorIds->isEmpty()) {
            return;
        }

        $eligibleIndicatorIds = AmiStandardIndicator::query()
            ->whereIn('ami_standard_indicators.id', $picIndicatorIds)
            ->where('ami_standard_indicators.active', 1)
            ->whereExists(function ($q) use ($academicId) {
                $q->select(DB::raw(1))
                    ->from('ami_standards as s')
                    ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                    ->where('s.active', 1)
                    ->where('s.academic_config_id', $academicId);
            })
            ->orderBy('ami_standard_indicators.id')
            ->pluck('id');

        if ($eligibleIndicatorIds->isEmpty()) {
            return;
        }

        $existing = SelfEvaluationDetail::where('self_evaluation_form_id', $form->id)
            ->pluck('ami_standard_indicator_id');

        $missing = $eligibleIndicatorIds->diff($existing);
        if ($missing->isEmpty()) {
            return;
        }

        $statusDraftId = EvaluationStatus::where('name', 'Draft')->value('id');

        foreach ($missing as $indId) {
            SelfEvaluationDetail::create([
                'id'                     => SelfEvaluationDetail::generateNextId(),
                'self_evaluation_form_id'=> $form->id,
                'ami_standard_indicator_id' => $indId,
                'status_id'              => $statusDraftId,
                'active'                 => true,
            ]);
        }
    }

    /* ================= Screen ================= */

    public function index(Request $request)
    {
        $academic = $this->activeAcademic();
        $user     = auth()->user();
        $ur       = $this->currentUserRole();

        $categoryDetailId   = $ur?->category_detail_id;
        $currentRoleId      = $ur?->role_id;
        $categoryDetailName = null;
        $form               = null;
        $details            = collect();
        $progress           = ['total' => 0, 'terisi' => 0, 'percent' => 0.0];
        $isMemberForm       = false;

        // param pencarian & filter
        $q                  = trim((string) $request->input('q', ''));
        $selectedStandardId = $request->input('standard_id');
        $standards          = collect();

        // default untuk modal create
        $defaultHeadName     = $user?->name ?? '';
        $defaultHeadPosition = optional($ur?->role)->name ?? '';

        if ($academic) {
            // 1) coba sebagai KETUA
            if ($categoryDetailId) {
                $form = SelfEvaluationForm::with(['status', 'categoryDetail'])
                    ->where('academic_config_id', $academic->id)
                    ->where('category_detail_id', $categoryDetailId)
                    ->first();
            }

            // 2) kalau tidak ada, cek sebagai ANGGOTA
            if (!$form && $user) {
                $form = SelfEvaluationForm::with(['status', 'categoryDetail'])
                    ->where('academic_config_id', $academic->id)
                    ->where(function ($q2) use ($user) {
                        $q2->where('member_auditee_1_user_id', $user->id)
                            ->orWhere('member_auditee_2_user_id', $user->id)
                            ->orWhere('member_auditee_3_user_id', $user->id);
                    })
                    ->first();

                if ($form) {
                    $isMemberForm        = true;
                    $categoryDetailId    = $form->category_detail_id;
                    $defaultHeadName     = $form->head_auditee_name ?: $defaultHeadName;
                    $defaultHeadPosition = $form->head_auditee_position ?: $defaultHeadPosition;
                }
            }

            // nama unit/prodi
            if ($form && $form->categoryDetail) {
                $categoryDetailName = $form->categoryDetail->name;
            } elseif ($categoryDetailId) {
                $categoryDetailName = DB::table('ref_category_details')
                    ->where('id', $categoryDetailId)
                    ->value('name');
            }

            if ($form) {
                // AMBIL DETAIL
                if (!$isMemberForm && $currentRoleId) {
                    DB::transaction(fn () => $this->syncDetailsWithPIC($form, $currentRoleId));

                    $allDetails = SelfEvaluationDetail::with(['indicator.standard', 'standardAchievement', 'updatedBy'])
                        ->where('self_evaluation_form_id', $form->id)
                        ->whereHas('indicator', function ($q2) use ($academic, $currentRoleId) {
                            $q2->where('ami_standard_indicators.active', 1)
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
                        ->get();
                } else {
                    $allDetails = SelfEvaluationDetail::with(['indicator.standard', 'standardAchievement', 'updatedBy'])
                        ->where('self_evaluation_form_id', $form->id)
                        ->whereHas('indicator', function ($q2) use ($academic) {
                            $q2->where('ami_standard_indicators.active', 1)
                                ->whereExists(function ($qq) use ($academic) {
                                    $qq->select(DB::raw(1))
                                        ->from('ami_standards as s')
                                        ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                                        ->where('s.active', 1)
                                        ->where('s.academic_config_id', $academic->id);
                                });
                        })
                        ->orderBy('ami_standard_indicator_id')
                        ->get();
                }

                $allDetailsFull = $allDetails;

                // DAFTAR STANDAR UNTUK FILTER
                $standards = $allDetailsFull
                    ->map(function ($d) {
                        return $d->indicator?->standard;
                    })
                    ->filter()
                    ->unique('id')
                    ->sortBy('name')
                    ->values();

                // PROGRESS GLOBAL
                $total  = $allDetailsFull->count();
                $terisi = $allDetailsFull->filter(function ($d) {
                    $hasil = isset($d->result) ? trim((string) $d->result) : '';
                    return !is_null($d->standard_achievement_id) || $hasil !== '';
                })->count();

                $progress = [
                    'total'   => $total,
                    'terisi'  => $terisi,
                    'percent' => $total ? round(100 * $terisi / $total, 1) : 0.0,
                ];

                // FILTER LIST
                $filtered = $allDetailsFull;

                if (!empty($selectedStandardId)) {
                    $filtered = $filtered->filter(function ($d) use ($selectedStandardId) {
                        return (string) optional($d->indicator?->standard)->id === (string) $selectedStandardId;
                    })->values();
                }

                if ($q !== '') {
                    $needle = Str::lower($q);
                    $filtered = $filtered->filter(function ($d) use ($needle) {
                        $desc = Str::lower(strip_tags($d->indicator->description ?? ''));
                        $std  = Str::lower(optional($d->indicator->standard)->name ?? '');
                        return Str::contains($desc, $needle) || Str::contains($std, $needle);
                    })->values();
                }

                // PAGINATION
                $totalFiltered = $filtered->count();
                $perPage       = 10;
                $currentPage   = LengthAwarePaginator::resolveCurrentPage();

                $currentItems = $filtered
                    ->slice(($currentPage - 1) * $perPage, $perPage)
                    ->values();

                $details = new LengthAwarePaginator(
                    $currentItems,
                    $totalFiltered,
                    $perPage,
                    $currentPage,
                    [
                        'path'  => $request->url(),
                        'query' => $request->query(),
                    ]
                );
            } else {
                if (!$categoryDetailId) {
                    session()->flash('warning', 'Akun Anda belum terhubung ke Unit/Prodi. Hubungi admin.');
                }
            }
        }

        $opsiKetercapaian = StandardAchievement::where('active', 1)->get();

        $roles = Role::where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('auditee.fed.index', compact(
            'academic',
            'categoryDetailId',
            'categoryDetailName',
            'form',
            'details',
            'progress',
            'opsiKetercapaian',
            'roles',
            'defaultHeadName',
            'defaultHeadPosition',
            'isMemberForm',
            'q',
            'standards',
            'selectedStandardId'
        ));
    }

    /* ================= Actions ================= */

    public function store(Request $request)
    {
        $academicId      = $this->activeAcademicId();
        $ur              = $this->currentUserRole();
        $categoryDetailId= $ur?->category_detail_id;
        $currentRoleId   = $ur?->role_id;

        if (!$academicId || !$categoryDetailId) {
            return redirect()->route('auditee.fed.index')->with(
                'warning',
                !$academicId
                    ? 'Tahun akademik aktif belum diset oleh admin.'
                    : 'Akun belum terhubung ke Unit/Prodi.'
            );
        }

        if (SelfEvaluationForm::where('academic_config_id', $academicId)
            ->where('category_detail_id', $categoryDetailId)
            ->exists()
        ) {
            return redirect()->route('auditee.fed.index')->with('info', 'Form sudah ada. Lanjutkan pengisian.');
        }

        $data = $request->validate([
            'ketua_auditee_nama'            => ['nullable', 'string', 'max:255'],
            'ketua_auditee_jabatan'         => ['nullable', 'string', 'max:255'],

            'member_auditee_1_user_id'      => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_satu'  => ['nullable', 'string', 'max:255'],

            'member_auditee_2_user_id'      => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_dua'   => ['nullable', 'string', 'max:255'],

            'member_auditee_3_user_id'      => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_tiga'  => ['nullable', 'string', 'max:255'],
        ]);

        DB::beginTransaction();
        try {
            $statusDraftId = EvaluationStatus::where('name', 'Draft')->value('id');

            $currentUser = auth()->user();
            $headName    = $data['ketua_auditee_nama'] ?: ($currentUser->name ?? null);
            $headPos     = $data['ketua_auditee_jabatan'] ?: (optional($ur?->role)->name ?? null);

            $member1Name = null;
            if (!empty($data['member_auditee_1_user_id'])) {
                $member1Name = optional(User::find($data['member_auditee_1_user_id']))->name;
            }

            $member2Name = null;
            if (!empty($data['member_auditee_2_user_id'])) {
                $member2Name = optional(User::find($data['member_auditee_2_user_id']))->name;
            }

            $member3Name = null;
            if (!empty($data['member_auditee_3_user_id'])) {
                $member3Name = optional(User::find($data['member_auditee_3_user_id']))->name;
            }

            $form                       = new SelfEvaluationForm();
            $form->id                   = SelfEvaluationForm::generateNextId();
            $form->academic_config_id   = $academicId;
            $form->category_detail_id   = $categoryDetailId;
            $form->status_id            = $statusDraftId;
            $form->active               = true;

            $form->head_auditee_name    = $headName;
            $form->head_auditee_position= $headPos;

            $form->member_auditee_1_name     = $member1Name;
            $form->member_auditee_1_position = $data['anggota_auditee_jabatan_satu'] ?? null;

            $form->member_auditee_2_name     = $member2Name;
            $form->member_auditee_2_position = $data['anggota_auditee_jabatan_dua'] ?? null;

            $form->member_auditee_3_name     = $member3Name;
            $form->member_auditee_3_position = $data['anggota_auditee_jabatan_tiga'] ?? null;

            $form->member_auditee_1_user_id  = $data['member_auditee_1_user_id'] ?? null;
            $form->member_auditee_2_user_id  = $data['member_auditee_2_user_id'] ?? null;
            $form->member_auditee_3_user_id  = $data['member_auditee_3_user_id'] ?? null;

            $form->save();

            if ($currentRoleId) {
                $picIds = AmiStandardIndicator::query()
                    ->whereIn('ami_standard_indicators.id', function ($sub) use ($currentRoleId) {
                        $sub->select('standard_indicator_id')
                            ->from('ami_standard_indicator_pic')
                            ->where('active', 1)
                            ->where('role_id', $currentRoleId);
                    })
                    ->where('ami_standard_indicators.active', 1)
                    ->whereExists(function ($q) use ($academicId) {
                        $q->select(DB::raw(1))
                            ->from('ami_standards as s')
                            ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                            ->where('s.active', 1)
                            ->where('s.academic_config_id', $academicId);
                    })
                    ->orderBy('ami_standard_indicators.id')
                    ->pluck('id');

                foreach ($picIds as $indId) {
                    SelfEvaluationDetail::create([
                        'id'                        => SelfEvaluationDetail::generateNextId(),
                        'self_evaluation_form_id'   => $form->id,
                        'ami_standard_indicator_id' => $indId,
                        'status_id'                 => $statusDraftId,
                        'active'                    => true,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('auditee.fed.index')->with('success', 'Form Evaluasi Diri berhasil dibuat.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('auditee.fed.index')->with('error', 'Gagal membuat form: ' . $e->getMessage());
        }
    }

    public function updateHeader(Request $request, SelfEvaluationForm $form)
    {
        $this->ensureFormOwnedByUser($form);
        $this->ensureFormOnActiveYear($form);

        $submittedId = EvaluationStatus::where('name', 'Dikirim')->value('id');
        if ($form->status_id === $submittedId) {
            return back()->with('warning', 'Form sudah dikirim dan tidak dapat diubah.');
        }

        $data = $request->validate([
            'ketua_auditee_nama'            => ['nullable', 'string', 'max:255'],
            'ketua_auditee_jabatan'         => ['nullable', 'string', 'max:255'],

            'member_auditee_1_user_id'      => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_satu'  => ['nullable', 'string', 'max:255'],

            'member_auditee_2_user_id'      => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_dua'   => ['nullable', 'string', 'max:255'],

            'member_auditee_3_user_id'      => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_tiga'  => ['nullable', 'string', 'max:255'],
        ]);

        $headName = $data['ketua_auditee_nama'] ?: $form->head_auditee_name;
        $headPos  = $data['ketua_auditee_jabatan'] ?: $form->head_auditee_position;

        $member1Name = $form->member_auditee_1_name;
        $member1Id   = $form->member_auditee_1_user_id;

        if (!empty($data['member_auditee_1_user_id'])) {
            $u1          = User::find($data['member_auditee_1_user_id']);
            $member1Name = optional($u1)->name;
            $member1Id   = optional($u1)->id;
        }
        $member1Pos = $data['anggota_auditee_jabatan_satu'] ?? $form->member_auditee_1_position;

        $member2Name = $form->member_auditee_2_name;
        $member2Id   = $form->member_auditee_2_user_id;
        if (!empty($data['member_auditee_2_user_id'])) {
            $u2          = User::find($data['member_auditee_2_user_id']);
            $member2Name = optional($u2)->name;
            $member2Id   = optional($u2)->id;
        }
        $member2Pos = $data['anggota_auditee_jabatan_dua'] ?? $form->member_auditee_2_position;

        $member3Name = $form->member_auditee_3_name;
        $member3Id   = $form->member_auditee_3_user_id;
        if (!empty($data['member_auditee_3_user_id'])) {
            $u3          = User::find($data['member_auditee_3_user_id']);
            $member3Name = optional($u3)->name;
            $member3Id   = optional($u3)->id;
        }
        $member3Pos = $data['anggota_auditee_jabatan_tiga'] ?? $form->member_auditee_3_position;

        $form->update([
            'head_auditee_name'          => $headName,
            'head_auditee_position'      => $headPos,

            'member_auditee_1_name'      => $member1Name,
            'member_auditee_1_position'  => $member1Pos,
            'member_auditee_1_user_id'   => $member1Id,

            'member_auditee_2_name'      => $member2Name,
            'member_auditee_2_position'  => $member2Pos,
            'member_auditee_2_user_id'   => $member2Id,

            'member_auditee_3_name'      => $member3Name,
            'member_auditee_3_position'  => $member3Pos,
            'member_auditee_3_user_id'   => $member3Id,
        ]);

        return redirect()->route('auditee.fed.index')->with('success', 'Data auditee diperbarui.');
    }

    public function updateDetail(Request $request, SelfEvaluationForm $form, SelfEvaluationDetail $detail)
    {
        $this->ensureFormOwnedByUser($form);
        $this->ensureFormOnActiveYear($form);
        if ($detail->self_evaluation_form_id !== $form->id) {
            abort(404);
        }

        $ur            = $this->currentUserRole();
        $currentRoleId = $ur?->role_id;

        // cek hak edit
        $isPic = false;
        if ($currentRoleId) {
            $isPic = AmiStandardIndicatorPic::where('standard_indicator_id', $detail->ami_standard_indicator_id)
                ->where('role_id', $currentRoleId)
                ->where('active', 1)
                ->exists();
        }

        $isMember = $this->currentUserIsFormMember($form);

        abort_unless($isPic || $isMember, 403, 'Anda bukan PIC/anggota auditee form ini.');

        $submittedId = EvaluationStatus::where('name', 'Dikirim')->value('id');
        if ($form->status_id === $submittedId) {
            return back()->with('warning', 'Form sudah dikirim dan tidak dapat diubah.');
        }

        $data = $request->validate([
            'ketercapaian_standard_id'     => ['nullable', 'string', 'max:255'],
            'hasil'                        => ['nullable', 'string'],
            'bukti_pendukung'             => ['nullable', 'string'],
            'faktor_penghambat_pendukung' => ['nullable', 'string'],
        ]);

        $draftId = EvaluationStatus::where('name', 'Draft')->value('id');

        $detail->update([
            'standard_achievement_id' => $data['ketercapaian_standard_id'] ?? null,
            'result'                  => $data['hasil'] ?? null,
            'supporting_evidence'     => $data['bukti_pendukung'] ?? null,
            'contributing_factors'    => $data['faktor_penghambat_pendukung'] ?? null,
            'status_id'               => $draftId,
            // updated_by di-handle di model (booted)
        ]);

        return redirect()
            ->route('auditee.fed.index')
            ->with('success', 'Butir tersimpan.');
    }

    public function submit(Request $request, SelfEvaluationForm $form)
    {
        $this->ensureFormOwnedByUser($form);
        $this->ensureFormOnActiveYear($form);

        $submittedId = EvaluationStatus::where('name', 'Dikirim')->value('id');
        if ($form->status_id === $submittedId) {
            return back()->with('info', 'Form sudah dikirim.');
        }

        $ur            = $this->currentUserRole();
        $currentRoleId = $ur?->role_id;

        $details = SelfEvaluationDetail::where('self_evaluation_form_id', $form->id)
            ->whereHas('indicator', function ($q) use ($form, $currentRoleId) {
                $q->where('ami_standard_indicators.active', 1)
                    ->whereExists(function ($qq) use ($form) {
                        $qq->select(DB::raw(1))
                            ->from('ami_standards as s')
                            ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                            ->where('s.active', 1)
                            ->where('s.academic_config_id', $form->academic_config_id);
                    })
                    ->whereExists(function ($qp) use ($currentRoleId) {
                        $qp->select(DB::raw(1))
                            ->from('ami_standard_indicator_pic as p')
                            ->whereColumn('p.standard_indicator_id', 'ami_standard_indicators.id')
                            ->where('p.active', 1)
                            ->where('p.role_id', $currentRoleId);
                    });
            })
            ->get(['standard_achievement_id', 'result']);

        $incomplete = $details->filter(
            fn ($d) =>
            is_null($d->standard_achievement_id)
            && (!isset($d->result) || trim($d->result) === '')
        )->count();

        if ($incomplete > 0) {
            throw ValidationException::withMessages([
                'form' => "Masih ada {$incomplete} indikator yang belum diisi untuk role Anda.",
            ]);
        }

        $form->update([
            'status_id'    => $submittedId,
            'submitted_at' => now()->toDateString(),
        ]);

        return redirect()->route('auditee.fed.index')->with('success', 'Form berhasil dikirim. Terima kasih!');
    }

    public function searchUsers(Request $request)
    {
        $q = trim($request->query('q', ''));

        $users = User::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%");
                });
            })
            ->where('active', 1)
            ->orderBy('name')
            ->limit(20)
            ->get();

        $results = $users->map(function ($u) {
            if (!empty($u->cis_user_id)) {
                $ur = UserRole::where('cis_user_id', $u->cis_user_id)->where('active', 1)->latest('created_at')->first()
                    ?? UserRole::where('cis_user_id', $u->cis_user_id)->latest('created_at')->first();
            } else {
                $ur = UserRole::where('id', $u->id)->where('active', 1)->latest('created_at')->first()
                    ?? UserRole::where('id', $u->id)->latest('created_at')->first();
            }

            $roleName = optional($ur?->role)->name ?? 'Anggota';

            return [
                'id'        => $u->id,
                'name'      => $u->name,
                'role_name' => $roleName,
            ];
        });

        return response()->json($results);
    }

public function exportDoc(Request $request, SelfEvaluationForm $form): BinaryFileResponse
{
    $this->ensureFormOwnedByUser($form);
    $this->ensureFormOnActiveYear($form);

    $submittedId = EvaluationStatus::where('name', 'Dikirim')->value('id');
    abort_unless($form->status_id === $submittedId, 403, 'Dokumen hanya tersedia setelah form dikirim.');

    if (!class_exists(\ZipArchive::class)) {
        abort(500, 'PHP Zip extension belum aktif.');
    }

    $ur            = $this->currentUserRole();
    $currentRoleId = $ur?->role_id;

    // DETAIL SESUAI ROLE & TAHUN
    $details = SelfEvaluationDetail::with(['indicator.standard', 'standardAchievement'])
        ->where('self_evaluation_form_id', $form->id)
        ->whereHas('indicator', function ($q) use ($form, $currentRoleId) {
            $q->where('ami_standard_indicators.active', 1)
                ->whereExists(function ($qq) use ($form) {
                    $qq->select(DB::raw(1))
                        ->from('ami_standards as s')
                        ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                        ->where('s.active', 1)
                        ->where('s.academic_config_id', $form->academic_config_id);
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
        ->get();

    // ==== LOAD TEMPLATE ====
    $templateAbsPath = storage_path('app/' . self::TEMPLATE_PATH);
    if (!is_file($templateAbsPath)) {
        abort(500, 'Template DOCX tidak ditemukan: ' . self::TEMPLATE_PATH);
    }

    $tp = new TemplateProcessor($templateAbsPath);

    // ==== HEADER (AUDITEE, TA, DLL) ====
    $taName   = optional($form->academicConfig)->name ?? '';
    $unitName = optional($form->categoryDetail)->name ?? '';

    $ketua      = trim(($form->head_auditee_position ?? '') . ' / ' . ($form->head_auditee_name ?? ''), ' /');
    $namaketua  = trim($form->head_auditee_name ?? '');
    $angg1      = trim(($form->member_auditee_1_position ?? '') . ' / ' . ($form->member_auditee_1_name ?? ''), ' /');
    $angg2      = trim(($form->member_auditee_2_position ?? '') . ' / ' . ($form->member_auditee_2_name ?? ''), ' /');
    $angg3      = trim(($form->member_auditee_3_position ?? '') . ' / ' . ($form->member_auditee_3_name ?? ''), ' /');

    $tp->setValue('categoryDetail', $unitName);
    $tp->setValue('ta', $taName);
    $tp->setValue('ketua', $ketua ?: '');
    $tp->setValue('namaketua', $namaketua ?: '');
    $tp->setValue('anggota1', $angg1 ?: '');
    $tp->setValue('anggota2', $angg2 ?: '');
    $tp->setValue('anggota3', $angg3 ?: '');
    $tp->setValue('tanggal', now()->format('d/m/Y'));

    // ==== HELPER TEXT PLAIN ====
    $cleanText = function (?string $value, string $fallback = ''): string {
        $text = $value ?? '';

        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[^\P{C}\n]+/u', '', $text);
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        $text = trim($text);

        return $text === '' ? $fallback : $text;
    };

    $parseHtmlToTextRun = function (\PhpOffice\PhpWord\Element\TextRun $run, ?string $html) {
        $html = $html ?? '';
        if (trim($html) === '') {
            return;
        }

        $dom = new \DOMDocument();
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML("<div>{$html}</div>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $container = $dom->getElementsByTagName('div')->item(0);
        if (!$container) {
            $run->addText(strip_tags($html));
            return;
        }

        // Closure rekursif untuk traverse
        $traverse = function ($node, $style = [], $listContext = []) use (&$traverse, $run) {
            if ($node->nodeType === XML_TEXT_NODE) {
                // Text biasa
                $text = $node->textContent;
                if ($text !== '') {
                    $run->addText($text, $style);
                }
                return;
            }

            if ($node->nodeType !== XML_ELEMENT_NODE) {
                return;
            }

            $tag = strtolower($node->nodeName);

            // Handle styling inline
            if ($tag === 'b' || $tag === 'strong') {
                $style['bold'] = true;
            } elseif ($tag === 'i' || $tag === 'em') {
                $style['italic'] = true;
            } elseif ($tag === 'u') {
                $style['underline'] = 'single';
            }

            // Handle Block & Splits
            // P / DIV: jika bukan node pertama, beri break
            if ($tag === 'p' || $tag === 'div') {
                // Cek apakah node ini punya previous sibling text/element, kalau ya beri break
               if ($node->previousSibling) {
                   $run->addTextBreak();
               }
            }
            if ($tag === 'br') {
                $run->addTextBreak();
                return;
            }

            // Handle LIST
            if ($tag === 'ul' || $tag === 'ol') {
                $idx = 1;
                $depth = ($listContext['depth'] ?? 0) + 1;
                $type  = $tag === 'ol'
                    ? ($node->getAttribute('type') ?: '1')
                    : 'ul';

                foreach ($node->childNodes as $child) {
                    if (strtolower($child->nodeName) === 'li') {
                        $traverse($child, $style, ['depth' => $depth, 'type' => $type, 'index' => $idx++]);
                    }
                }
                return; // children processed manually
            }

            // Handle LIST ITEM
            if ($tag === 'li') {
                $depth = $listContext['depth'] ?? 1;
                $idx   = $listContext['index'] ?? 1;
                $lType = $listContext['type']  ?? 'ul';

                // Marker
                $marker = '• ';
                if ($lType !== 'ul') {
                    if ($lType === 'a') {
                        $alpha = chr(96 + (($idx - 1) % 26 + 1));
                        $marker = "{$alpha}. ";
                    } elseif ($lType === 'A') {
                        $alpha = chr(64 + (($idx - 1) % 26 + 1));
                        $marker = "{$alpha}. ";
                    } else {
                        $marker = "{$idx}. ";
                    }
                }

                $run->addTextBreak(); // Item list selalu baris baru

                // Indentasi manual dengan non-breaking space (utf-8 0xA0)
                // 3 spasi per kedalaman
                $nbsp = "\xC2\xA0";
                $indentStr = str_repeat($nbsp . $nbsp . $nbsp, $depth);

                $run->addText($indentStr . $marker, $style);

                // Isi item
                foreach ($node->childNodes as $child) {
                    $traverse($child, $style, $listContext);
                }
                return;
            }

            // Handle LINK
            if ($tag === 'a') {
                $href = $node->getAttribute('href');
                $inner = $node->textContent;
                if ($href) {
                     // Gunakan addLink biasa.
                     // Note: TemplateProcessor kadang tidak otomatis bind rels untuk link baru di dalam block.
                     // Tetapi error 'Invalid type HYPERLINK' terjadi karena addField memvalidasi tipe filed yg didukung.
                     // Jika addLink tidak clickable di output akhir karena limitasi TemplateProcessor,
                     // setidaknya tidak error 500.
                     $linkStyle = array_merge($style, ['color' => '0000FF', 'underline' => 'single']);
                     $run->addLink($href, $inner, $linkStyle);
                     return;
                }
            }

            // Recurse generic
            foreach ($node->childNodes as $child) {
                $traverse($child, $style, $listContext);
            }
        };

        $traverse($container);
    };

    // ==== SUSUN DATA UNTUK cloneRow ====
    $rows        = [];
    // Kita simpan raw values untuk plain text di cloneRow (opsi jika layout tabel simple)
    // Tapi karena pakai setComplexBlock, array ini lebih ke metadata row count & flag

    // Kita butuh TextRun object untuk setiap sel yg kompleks
    $standarBlocks = [];
    $hasilBlocks   = [];
    $faktorBlocks  = [];

    foreach ($details as $i => $d) {
        $index = $i + 1;

        // 1. Standar Block
        $stdRun = new TextRun();
        $stdName = trim(strip_tags($d->indicator->standard->name ?? ''));
        if ($stdName) {
            $stdRun->addText($stdName, ['bold' => true]);
            $stdRun->addTextBreak();
        }
        // Deskripsi Indikator
        $parseHtmlToTextRun($stdRun, $d->indicator->description ?? '');
        $standarBlocks[$index] = $stdRun;

        // 2. Hasil Block
        $resRun = new TextRun();
        $parseHtmlToTextRun($resRun, $d->result ?? '');
        $hasilBlocks[$index] = $resRun;

        // 3. Faktor Block
        $fakRun = new TextRun();
        $parseHtmlToTextRun($fakRun, $d->contributing_factors ?? '');
        $faktorBlocks[$index] = $fakRun;

        // Data simplerow
        $flag = strtolower(optional($d->standardAchievement)->name ?? '');
        $picRoleNames = DB::table('ami_standard_indicator_pic as p')
            ->join('roles as r', 'r.id', '=', 'p.role_id')
            ->where('p.standard_indicator_id', $d->ami_standard_indicator_id)
            ->where('p.active', 1)
            ->pluck('r.name')
            ->unique()
            ->implode(', ');

        $rows[] = [
            'no'             => (string) $index,
            'sumber'         => $cleanText($picRoleNames, ''),
            'melampaui'      => $flag === 'melampaui' ? '✓' : '',
            'mencapai'       => $flag === 'mencapai' ? '✓' : '',
            'tidak_mencapai' => $flag === 'tidak mencapai' ? '✓' : '',
            'menyimpang'     => $flag === 'menyimpang' ? '✓' : '',
        ];
    }

    // Clone baris berdasarkan placeholder ${no}
    $tp->cloneRowAndSetValues('no', $rows);

    // ==== ISI KOLUMN STANDAR, HASIL, FAKTOR PAKAI COMPLEX BLOCK ====
    foreach ($rows as $idx => $_) {
        $i = $idx + 1;

        if (isset($standarBlocks[$i])) {
            $tp->setComplexBlock("standar#{$i}", $standarBlocks[$i]);
        }
        if (isset($hasilBlocks[$i])) {
            $tp->setComplexBlock("hasil#{$i}", $hasilBlocks[$i]);
        }
        if (isset($faktorBlocks[$i])) {
            $tp->setComplexBlock("faktor#{$i}", $faktorBlocks[$i]);
        }
    }

    // ==== SIMPAN & DOWNLOAD ====
    $safe = function (string $name): string {
        $name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]+/', '', $name);
        $name = trim(preg_replace('/\\s+/', ' ', $name));
        return Str::limit($name, 120, '');
    };

    $safeUnit = $safe($unitName ?: 'Unit');
    $filename = "F-219_Formulir_Evaluasi_Diri_{$safeUnit}.docx";

    $tmpDir = storage_path('app/tmp');
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0775, true);
    }

    $target = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (file_exists($target)) {
        @unlink($target);
    }

    try {
    $tp->saveAs($target);
} catch (\Throwable $e) {
    logger()->error('EXPORT DOCX FAILED', [
        'message' => $e->getMessage(),
        'trace'   => $e->getTraceAsString(),
    ]);
    throw $e;
}


    return response()->download($target, $filename)->deleteFileAfterSend(true);
}

/**
 * Export Form Evaluasi Diri ke PDF menggunakan ConvertAPI
 * Generates DOCX first then converts to PDF for 100% identical output
 */
public function exportPdf(Request $request, SelfEvaluationForm $form): BinaryFileResponse
{
    $this->ensureFormOwnedByUser($form);
    $this->ensureFormOnActiveYear($form);

    $submittedId = EvaluationStatus::where('name', 'Dikirim')->value('id');
    abort_unless($form->status_id === $submittedId, 403, 'Dokumen hanya tersedia setelah form dikirim.');

    // Check ConvertAPI secret is configured
    $convertApiSecret = env('CONVERTAPI_SECRET');
    if (empty($convertApiSecret)) {
        abort(500, 'CONVERTAPI_SECRET belum dikonfigurasi. Silakan set di file .env');
    }

    if (!class_exists(\ZipArchive::class)) {
        abort(500, 'PHP Zip extension belum aktif.');
    }

    $ur            = $this->currentUserRole();
    $currentRoleId = $ur?->role_id;

    // DETAIL SESUAI ROLE & TAHUN (same logic as exportDoc)
    $details = SelfEvaluationDetail::with(['indicator.standard', 'standardAchievement'])
        ->where('self_evaluation_form_id', $form->id)
        ->whereHas('indicator', function ($q) use ($form, $currentRoleId) {
            $q->where('ami_standard_indicators.active', 1)
                ->whereExists(function ($qq) use ($form) {
                    $qq->select(DB::raw(1))
                        ->from('ami_standards as s')
                        ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                        ->where('s.active', 1)
                        ->where('s.academic_config_id', $form->academic_config_id);
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
        ->get();

    // ==== LOAD TEMPLATE (same as exportDoc) ====
    $templateAbsPath = storage_path('app/' . self::TEMPLATE_PATH);
    if (!is_file($templateAbsPath)) {
        abort(500, 'Template DOCX tidak ditemukan: ' . self::TEMPLATE_PATH);
    }

    $tp = new TemplateProcessor($templateAbsPath);

    // ==== HEADER (AUDITEE, TA, DLL) ====
    $taName   = optional($form->academicConfig)->name ?? '';
    $unitName = optional($form->categoryDetail)->name ?? '';

    $ketua      = trim(($form->head_auditee_position ?? '') . ' / ' . ($form->head_auditee_name ?? ''), ' /');
    $namaketua  = trim($form->head_auditee_name ?? '');
    $angg1      = trim(($form->member_auditee_1_position ?? '') . ' / ' . ($form->member_auditee_1_name ?? ''), ' /');
    $angg2      = trim(($form->member_auditee_2_position ?? '') . ' / ' . ($form->member_auditee_2_name ?? ''), ' /');
    $angg3      = trim(($form->member_auditee_3_position ?? '') . ' / ' . ($form->member_auditee_3_name ?? ''), ' /');

    $tp->setValue('categoryDetail', $unitName);
    $tp->setValue('ta', $taName);
    $tp->setValue('ketua', $ketua ?: '');
    $tp->setValue('namaketua', $namaketua ?: '');
    $tp->setValue('anggota1', $angg1 ?: '');
    $tp->setValue('anggota2', $angg2 ?: '');
    $tp->setValue('anggota3', $angg3 ?: '');
    $tp->setValue('tanggal', now()->format('d/m/Y'));

    // ==== HELPER TEXT PLAIN ====
    $cleanText = function (?string $value, string $fallback = ''): string {
        $text = $value ?? '';
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[^\P{C}\n]+/u', '', $text);
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        return $text === '' ? $fallback : $text;
    };

    $parseHtmlToTextRun = function (\PhpOffice\PhpWord\Element\TextRun $run, ?string $html) {
        $html = $html ?? '';
        if (trim($html) === '') {
            return;
        }

        $dom = new \DOMDocument();
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML("<div>{$html}</div>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $container = $dom->getElementsByTagName('div')->item(0);
        if (!$container) {
            $run->addText(strip_tags($html));
            return;
        }

        $traverse = function ($node, $style = [], $listContext = []) use (&$traverse, $run) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = $node->textContent;
                if ($text !== '') {
                    $run->addText($text, $style);
                }
                return;
            }

            if ($node->nodeType !== XML_ELEMENT_NODE) {
                return;
            }

            $tag = strtolower($node->nodeName);

            if ($tag === 'b' || $tag === 'strong') {
                $style['bold'] = true;
            } elseif ($tag === 'i' || $tag === 'em') {
                $style['italic'] = true;
            } elseif ($tag === 'u') {
                $style['underline'] = 'single';
            }

            if ($tag === 'p' || $tag === 'div') {
                if ($node->previousSibling) {
                    $run->addTextBreak();
                }
            }
            if ($tag === 'br') {
                $run->addTextBreak();
                return;
            }

            if ($tag === 'ul' || $tag === 'ol') {
                $idx = 1;
                $depth = ($listContext['depth'] ?? 0) + 1;
                $type  = $tag === 'ol'
                    ? ($node->getAttribute('type') ?: '1')
                    : 'ul';

                foreach ($node->childNodes as $child) {
                    if (strtolower($child->nodeName) === 'li') {
                        $traverse($child, $style, ['depth' => $depth, 'type' => $type, 'index' => $idx++]);
                    }
                }
                return;
            }

            if ($tag === 'li') {
                $depth = $listContext['depth'] ?? 1;
                $idx   = $listContext['index'] ?? 1;
                $lType = $listContext['type']  ?? 'ul';

                $marker = '• ';
                if ($lType !== 'ul') {
                    if ($lType === 'a') {
                        $alpha = chr(96 + (($idx - 1) % 26 + 1));
                        $marker = "{$alpha}. ";
                    } elseif ($lType === 'A') {
                        $alpha = chr(64 + (($idx - 1) % 26 + 1));
                        $marker = "{$alpha}. ";
                    } else {
                        $marker = "{$idx}. ";
                    }
                }

                $run->addTextBreak();
                $nbsp = "\xC2\xA0";
                $indentStr = str_repeat($nbsp . $nbsp . $nbsp, $depth);
                $run->addText($indentStr . $marker, $style);

                foreach ($node->childNodes as $child) {
                    $traverse($child, $style, $listContext);
                }
                return;
            }

            if ($tag === 'a') {
                $href = $node->getAttribute('href');
                $inner = $node->textContent;
                if ($href) {
                    $linkStyle = array_merge($style, ['color' => '0000FF', 'underline' => 'single']);
                    $run->addLink($href, $inner, $linkStyle);
                    return;
                }
            }

            foreach ($node->childNodes as $child) {
                $traverse($child, $style, $listContext);
            }
        };

        $traverse($container);
    };

    // ==== SUSUN DATA UNTUK cloneRow ====
    $rows        = [];
    $standarBlocks = [];
    $hasilBlocks   = [];
    $faktorBlocks  = [];

    foreach ($details as $i => $d) {
        $index = $i + 1;

        $stdRun = new TextRun();
        $stdName = trim(strip_tags($d->indicator->standard->name ?? ''));
        if ($stdName) {
            $stdRun->addText($stdName, ['bold' => true]);
            $stdRun->addTextBreak();
        }
        $parseHtmlToTextRun($stdRun, $d->indicator->description ?? '');
        $standarBlocks[$index] = $stdRun;

        $resRun = new TextRun();
        $parseHtmlToTextRun($resRun, $d->result ?? '');
        $hasilBlocks[$index] = $resRun;

        $fakRun = new TextRun();
        $parseHtmlToTextRun($fakRun, $d->contributing_factors ?? '');
        $faktorBlocks[$index] = $fakRun;

        $flag = strtolower(optional($d->standardAchievement)->name ?? '');
        $picRoleNames = DB::table('ami_standard_indicator_pic as p')
            ->join('roles as r', 'r.id', '=', 'p.role_id')
            ->where('p.standard_indicator_id', $d->ami_standard_indicator_id)
            ->where('p.active', 1)
            ->pluck('r.name')
            ->unique()
            ->implode(', ');

        $rows[] = [
            'no'             => (string) $index,
            'sumber'         => $cleanText($picRoleNames, ''),
            'melampaui'      => $flag === 'melampaui' ? '✓' : '',
            'mencapai'       => $flag === 'mencapai' ? '✓' : '',
            'tidak_mencapai' => $flag === 'tidak mencapai' ? '✓' : '',
            'menyimpang'     => $flag === 'menyimpang' ? '✓' : '',
        ];
    }

    $tp->cloneRowAndSetValues('no', $rows);

    foreach ($rows as $idx => $_) {
        $i = $idx + 1;
        if (isset($standarBlocks[$i])) {
            $tp->setComplexBlock("standar#{$i}", $standarBlocks[$i]);
        }
        if (isset($hasilBlocks[$i])) {
            $tp->setComplexBlock("hasil#{$i}", $hasilBlocks[$i]);
        }
        if (isset($faktorBlocks[$i])) {
            $tp->setComplexBlock("faktor#{$i}", $faktorBlocks[$i]);
        }
    }

    // ==== SIMPAN DOCX SEMENTARA ====
    $safe = function (string $name): string {
        $name = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        return Str::limit($name, 120, '');
    };

    $safeUnit = $safe($unitName ?: 'Unit');
    $docxFilename = "F-219_Formulir_Evaluasi_Diri_{$safeUnit}.docx";
    $pdfFilename = "F-219_Formulir_Evaluasi_Diri_{$safeUnit}.pdf";

    $tmpDir = storage_path('app/tmp');
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0775, true);
    }

    $docxPath = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $docxFilename;
    $pdfPath = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pdfFilename;

    if (file_exists($docxPath)) {
        @unlink($docxPath);
    }
    if (file_exists($pdfPath)) {
        @unlink($pdfPath);
    }

    $tp->saveAs($docxPath);

    // ==== CONVERT DOCX TO PDF USING CONVERTAPI ====
    try {
        ConvertApi::setApiCredentials($convertApiSecret);

        $result = ConvertApi::convert('pdf', [
            'File' => $docxPath,
        ], 'docx');

        // Save the converted PDF
        $result->saveFiles($tmpDir);

        // ConvertAPI saves with original name but .pdf extension
        // Check if file exists, if not try to find it
        if (!file_exists($pdfPath)) {
            // Look for any PDF file just created
            $files = glob($tmpDir . '/*.pdf');
            if (!empty($files)) {
                // Get the most recently created PDF
                usort($files, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                $convertedPdf = $files[0];
                if ($convertedPdf !== $pdfPath) {
                    rename($convertedPdf, $pdfPath);
                }
            }
        }

        // Clean up DOCX temp file
        if (file_exists($docxPath)) {
            @unlink($docxPath);
        }

        if (!file_exists($pdfPath)) {
            abort(500, 'Gagal mengkonversi dokumen ke PDF.');
        }

        return response()->download($pdfPath, $pdfFilename)->deleteFileAfterSend(true);

    } catch (\Exception $e) {
        // Clean up temp files on error
        if (file_exists($docxPath)) {
            @unlink($docxPath);
        }
        if (file_exists($pdfPath)) {
            @unlink($pdfPath);
        }

        abort(500, 'Gagal mengkonversi ke PDF: ' . $e->getMessage());
    }
}


}
