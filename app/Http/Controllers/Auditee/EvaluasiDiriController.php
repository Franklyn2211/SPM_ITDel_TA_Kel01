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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use PhpOffice\PhpWord\TemplateProcessor;

class EvaluasiDiriController extends Controller
{
    private const TEMPLATE_PATH = 'templates/F-219_Formulir_Evaluasi_Diri_Auditee.docx';

    /* ================= Helpers ================= */

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
        abort_unless($userCat && $form->category_detail_id === $userCat, 403, 'Tidak berhak mengakses form ini.');
    }

    private function ensureFormOnActiveYear(SelfEvaluationForm $form): void
    {
        $activeId = $this->activeAcademicId();
        abort_unless($activeId && $form->academic_config_id === $activeId, 403, 'Form ini bukan untuk tahun akademik aktif.');
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

        if ($picIndicatorIds->isEmpty()) return;

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

        if ($eligibleIndicatorIds->isEmpty()) return;

        $existing = SelfEvaluationDetail::where('self_evaluation_form_id', $form->id)
            ->pluck('ami_standard_indicator_id');

        $missing = $eligibleIndicatorIds->diff($existing);
        if ($missing->isEmpty()) return;

        $statusDraftId = EvaluationStatus::where('name', 'Draft')->value('id');

        foreach ($missing as $indId) {
            SelfEvaluationDetail::create([
                'id'                        => SelfEvaluationDetail::generateNextId(),
                'self_evaluation_form_id'   => $form->id,
                'ami_standard_indicator_id' => $indId,
                'status_id'                 => $statusDraftId,
                'active'                    => true,
            ]);
        }
    }

    /* ================= Screen ================= */

    public function index(Request $request)
    {
        $academic         = $this->activeAcademic();
        $ur               = $this->currentUserRole();
        $categoryDetailId = $ur?->category_detail_id;
        $currentRoleId    = $ur?->role_id;

        $categoryDetailName = null;
        if ($categoryDetailId) {
            $categoryDetailName = DB::table('ref_category_details')
                ->where('id', $categoryDetailId)
                ->value('name');
        }

        // DEFAULT KETUA: user login + jabatan dari role
        $currentUser         = auth()->user();
        $defaultHeadName     = $currentUser->name ?? null;
        $defaultHeadPosition = optional($ur?->role)->name ?? null;

        $form     = null;
        $details  = collect();
        $progress = ['total' => 0, 'terisi' => 0, 'percent' => 0.0];

        if (!$academic)         session()->flash('warning', 'Tahun akademik aktif belum diset oleh admin.');
        if (!$categoryDetailId) session()->flash('warning', 'Akun Anda belum terhubung ke Unit/Prodi. Hubungi admin.');

        if ($academic && $categoryDetailId) {
            $form = SelfEvaluationForm::with(['status', 'categoryDetail'])
                ->where('academic_config_id', $academic->id)
                ->where('category_detail_id', $categoryDetailId)
                ->first();

            if ($form && $currentRoleId) {
                DB::transaction(fn() => $this->syncDetailsWithPIC($form, $currentRoleId));

                $allDetails = SelfEvaluationDetail::with(['indicator', 'standardAchievement', 'updatedBy'])
                    ->where('self_evaluation_form_id', $form->id)
                    ->whereHas('indicator', function ($q) use ($academic, $currentRoleId) {
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
                    ->get();

                $total  = $allDetails->count();
                $terisi = $allDetails->filter(fn($d) =>
                    !is_null($d->standard_achievement_id) || (isset($d->result) && trim($d->result) !== '')
                )->count();

                $progress = [
                    'total'   => $total,
                    'terisi'  => $terisi,
                    'percent' => $total ? round(100 * $terisi / $total, 1) : 0.0,
                ];

                $perPage      = 10;
                $currentPage  = LengthAwarePaginator::resolveCurrentPage();
                $currentItems = $allDetails->slice(($currentPage - 1) * $perPage, $perPage)->values();

                $details = new LengthAwarePaginator(
                    $currentItems,
                    $total,
                    $perPage,
                    $currentPage,
                    ['path' => LengthAwarePaginator::resolveCurrentPath()]
                );
            }
        }

        $opsiKetercapaian = StandardAchievement::where('active', 1)->get();

        $roles = Role::where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // dropdown anggota pakai AJAX ke searchUsers(), jadi tidak perlu preload $users di sini

        return view('auditee.fed.index', compact(
            'academic',
            'categoryDetailId',
            'categoryDetailName',
            'form',
            'details',
            'progress',
            'opsiKetercapaian',
            'defaultHeadName',
            'defaultHeadPosition',
            'roles'
        ));
    }

    /* ================= Actions ================= */

    public function store(Request $request)
    {
        $academicId       = $this->activeAcademicId();
        $ur               = $this->currentUserRole();
        $categoryDetailId = $ur?->category_detail_id;
        $currentRoleId    = $ur?->role_id;

        if (!$academicId || !$categoryDetailId) {
            return redirect()->route('auditee.fed.index')->with(
                'warning',
                !$academicId ? 'Tahun akademik aktif belum diset oleh admin.' : 'Akun belum terhubung ke Unit/Prodi.'
            );
        }

        if (SelfEvaluationForm::where('academic_config_id', $academicId)->where('category_detail_id', $categoryDetailId)->exists()) {
            return redirect()->route('auditee.fed.index')->with('info', 'Form sudah ada. Lanjutkan pengisian.');
        }

        $data = $request->validate([
            'ketua_auditee_nama'           => ['nullable', 'string', 'max:255'],
            'ketua_auditee_jabatan'        => ['nullable', 'string', 'max:255'],

            'member_auditee_1_user_id'     => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_satu' => ['nullable', 'string', 'max:255'],

            'member_auditee_2_user_id'     => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_dua'  => ['nullable', 'string', 'max:255'],

            'member_auditee_3_user_id'     => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_tiga' => ['nullable', 'string', 'max:255'],
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

            $form = new SelfEvaluationForm();
            $form->id                 = SelfEvaluationForm::generateNextId();
            $form->academic_config_id = $academicId;
            $form->category_detail_id = $categoryDetailId;
            $form->status_id          = $statusDraftId;
            $form->active             = true;

            $form->head_auditee_name         = $headName;
            $form->head_auditee_position     = $headPos;
            $form->member_auditee_1_name     = $member1Name;
            $form->member_auditee_1_position = $data['anggota_auditee_jabatan_satu'] ?? null;
            $form->member_auditee_2_name     = $member2Name;
            $form->member_auditee_2_position = $data['anggota_auditee_jabatan_dua'] ?? null;
            $form->member_auditee_3_name     = $member3Name;
            $form->member_auditee_3_position = $data['anggota_auditee_jabatan_tiga'] ?? null;

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
            'ketua_auditee_nama'           => ['nullable', 'string', 'max:255'],
            'ketua_auditee_jabatan'        => ['nullable', 'string', 'max:255'],

            'member_auditee_1_user_id'     => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_satu' => ['nullable', 'string', 'max:255'],

            'member_auditee_2_user_id'     => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_dua'  => ['nullable', 'string', 'max:255'],

            'member_auditee_3_user_id'     => ['nullable', 'exists:users,id'],
            'anggota_auditee_jabatan_tiga' => ['nullable', 'string', 'max:255'],
        ]);

        $headName = $data['ketua_auditee_nama'] ?: $form->head_auditee_name;
        $headPos  = $data['ketua_auditee_jabatan'] ?: $form->head_auditee_position;

        $member1Name = $form->member_auditee_1_name;
        if (!empty($data['member_auditee_1_user_id'])) {
            $member1Name = optional(User::find($data['member_auditee_1_user_id']))->name;
        }
        $member1Pos = $data['anggota_auditee_jabatan_satu'] ?? $form->member_auditee_1_position;

        $member2Name = $form->member_auditee_2_name;
        if (!empty($data['member_auditee_2_user_id'])) {
            $member2Name = optional(User::find($data['member_auditee_2_user_id']))->name;
        }
        $member2Pos = $data['anggota_auditee_jabatan_dua'] ?? $form->member_auditee_2_position;

        $member3Name = $form->member_auditee_3_name;
        if (!empty($data['member_auditee_3_user_id'])) {
            $member3Name = optional(User::find($data['member_auditee_3_user_id']))->name;
        }
        $member3Pos = $data['anggota_auditee_jabatan_tiga'] ?? $form->member_auditee_3_position;

        $form->update([
            'head_auditee_name'         => $headName,
            'head_auditee_position'     => $headPos,
            'member_auditee_1_name'     => $member1Name,
            'member_auditee_1_position' => $member1Pos,
            'member_auditee_2_name'     => $member2Name,
            'member_auditee_2_position' => $member2Pos,
            'member_auditee_3_name'     => $member3Name,
            'member_auditee_3_position' => $member3Pos,
        ]);

        return redirect()->route('auditee.fed.index')->with('success', 'Data auditee diperbarui.');
    }

    public function updateDetail(Request $request, SelfEvaluationForm $form, SelfEvaluationDetail $detail)
    {
        $this->ensureFormOwnedByUser($form);
        $this->ensureFormOnActiveYear($form);
        if ($detail->self_evaluation_form_id !== $form->id) abort(404);

        $ur = $this->currentUserRole();
        $currentRoleId = $ur?->role_id;

        $isPic = AmiStandardIndicatorPic::where('standard_indicator_id', $detail->ami_standard_indicator_id)
            ->where('role_id', $currentRoleId)
            ->where('active', 1)
            ->exists();
        abort_unless($isPic, 403, 'Anda bukan PIC indikator ini.');

        $submittedId = EvaluationStatus::where('name', 'Dikirim')->value('id');
        if ($form->status_id === $submittedId) {
            return back()->with('warning', 'Form sudah dikirim dan tidak dapat diedit.');
        }

        $data = $request->validate([
            'ketercapaian_standard_id'    => ['nullable', 'string', 'max:255'],
            'hasil'                       => ['nullable', 'string'],
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
            'updated_by'              => optional($ur)->id,
        ]);

        return redirect()->route('auditee.fed.index')->with('success', 'Butir tersimpan.');
    }

    public function submit(Request $request, SelfEvaluationForm $form)
    {
        $this->ensureFormOwnedByUser($form);
        $this->ensureFormOnActiveYear($form);

        $submittedId = EvaluationStatus::where('name', 'Dikirim')->value('id');
        if ($form->status_id === $submittedId) {
            return back()->with('info', 'Form sudah dikirim.');
        }

        $ur = $this->currentUserRole();
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

        $incomplete = $details->filter(fn($d) =>
            is_null($d->standard_achievement_id) && (!isset($d->result) || trim($d->result) === '')
        )->count();

        if ($incomplete > 0) {
            throw ValidationException::withMessages([
                'form' => "Masih ada {$incomplete} indikator yang belum diisi untuk role Anda.",
            ]);
        }

        $form->update([
            'status_id'     => $submittedId,
            'submitted_at'  => now()->toDateString(),
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

        $submittedId = EvaluationStatus::where('name','Dikirim')->value('id');
        abort_unless($form->status_id === $submittedId, 403, 'Dokumen hanya tersedia setelah form dikirim.');

        if (!class_exists(\ZipArchive::class)) {
            abort(500, 'PHP Zip extension belum aktif.');
        }

        $ur            = $this->currentUserRole();
        $currentRoleId = $ur?->role_id;

        $details = SelfEvaluationDetail::with(['indicator.standard','standardAchievement'])
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

        $templateAbsPath = storage_path('app/' . self::TEMPLATE_PATH);
        if (!is_file($templateAbsPath)) {
            abort(500, 'Template DOCX tidak ditemukan: ' . self::TEMPLATE_PATH);
        }

        $tp = new TemplateProcessor($templateAbsPath);

        $taName   = optional($form->academicConfig)->name ?? '-';
        $unitName = optional($form->categoryDetail)->name ?? '-';

        $ketua      = trim(($form->head_auditee_position ?? '') . ' / ' . ($form->head_auditee_name ?? ''), ' /');
        $namaketua  = trim(($form->head_auditee_name ?? ''));
        $angg1      = trim(($form->member_auditee_1_position ?? '') . ' / ' . ($form->member_auditee_1_name ?? ''), ' /');
        $angg2      = trim(($form->member_auditee_2_position ?? '') . ' / ' . ($form->member_auditee_2_name ?? ''), ' /');
        $angg3      = trim(($form->member_auditee_3_position ?? '') . ' / ' . ($form->member_auditee_3_name ?? ''), ' /');

        $tp->setValue('categoryDetail', $unitName);
        $tp->setValue('ta', $taName);
        $tp->setValue('ketua', $ketua ?: '-');
        $tp->setValue('namaketua', $namaketua ?: '-');
        $tp->setValue('anggota1', $angg1 ?: '-');
        $tp->setValue('anggota2', $angg2 ?: '-');
        $tp->setValue('anggota3', $angg3 ?: '-');
        $tp->setValue('tanggal', now()->format('d/m/Y'));

        $cleanText = function (?string $value, string $fallback = '—'): string {
        $text = $value ?? '';
        // buang HTML
        $text = strip_tags($text);
        // buang control chars yang bikin XML rusak
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text);
        $text = trim($text);
        return $text === '' ? $fallback : $text;
    };

    $rows = [];
    foreach ($details as $i => $d) {
        $stdName   = optional($d->indicator?->standard)->name ?? '-';
        $descPlain = trim(strip_tags($d->indicator->description ?? ''));
        $judul     = $cleanText($stdName . ($descPlain ? ': ' . $descPlain : ''), '-');

        $flag = strtolower(optional($d->standardAchievement)->name ?? '');

        $rows[] = [
            'no'              => (string)($i + 1),
            'standar'         => $judul,
            'hasil'           => $cleanText($d->result),
            'bukti'           => $cleanText($d->supporting_evidence),
            'faktor'          => $cleanText($d->contributing_factors),
            'melampaui'       => $flag === 'melampaui' ? '✓' : '',
            'mencapai'        => $flag === 'mencapai' ? '✓' : '',
            'tidak_mencapai'  => $flag === 'tidak mencapai' ? '✓' : '',
            'menyimpang'      => $flag === 'menyimpang' ? '✓' : '',
        ];
    }

    if (empty($rows)) {
        $rows[] = [
            'no' => '1', 'standar' => '-', 'hasil' => '-', 'bukti' => '-', 'faktor' => '-',
            'melampaui' => '', 'mencapai' => '', 'tidak_mencapai' => '', 'menyimpang' => '',
        ];
    }

    $tp->cloneRowAndSetValues('no', $rows);

        $safe = function (string $name): string {
            $name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]+/', '-', $name);
            $name = trim(preg_replace('/\\s+/', ' ', $name));
            return Str::limit($name, 120, '');
        };

        $safeTA   = $safe($taName);
        $safeUnit = $safe($unitName);
        $filename = "FED_{$safeUnit}_TA_{$safeTA}.docx";

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);
        $target = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($target)) @unlink($target);

        $tp->saveAs($target);

        return response()->download($target, $filename)->deleteFileAfterSend(true);
    }
}
