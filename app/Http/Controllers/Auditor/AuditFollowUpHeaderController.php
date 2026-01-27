<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AuditFinding;
use App\Models\AuditFindingForm;
use App\Models\AuditFollowUpDetail;
use App\Models\AuditFollowUpForm;
use App\Models\SelfEvaluationForm;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AuditFollowUpHeaderController extends Controller
{
    private const FORM_STATUS_DRAFT = 'Draft';
    private const FORM_STATUS_FINAL = 'Final';

    /* ================= Helpers Umum ================= */

    private function activeAcademic(): ?AcademicConfig
    {
        return AcademicConfig::where('active', true)->first();
    }

    private function currentUserRole(): ?UserRole
    {
        $u = auth()->user();
        if (!$u) return null;

        if (!empty($u->user_role_id)) {
            return UserRole::find($u->user_role_id);
        }

        if (!empty($u->cis_user_id)) {
            return UserRole::where('cis_user_id', $u->cis_user_id)->where('active', 1)->first()
                ?? UserRole::where('cis_user_id', $u->cis_user_id)->latest('created_at')->first();
        }

        return null;
    }

    private function currentUserRoleId(): ?string
    {
        return optional($this->currentUserRole())->id;
    }

    private function isAdmin(): bool
    {
        $u = auth()->user();
        return (bool)$u && $u->username === 'adminspm';
    }

    private function normalize(?string $v): string
    {
        return trim((string)($v ?? ''));
    }

    /**
     * updated_by aman (kalau kolom numeric -> pakai auth()->id()
     * kalau string/char/text -> pakai role_id jika ada, fallback "u:{id}"
     */
    private function auditActorForColumn(string $table, string $column): mixed
    {
        $u = auth()->user();
        if (!$u) return null;

        if (!Schema::hasColumn($table, $column)) return null;

        $type = Schema::getColumnType($table, $column);

        if (in_array($type, ['integer', 'bigint', 'smallint'], true)) {
            return $u->id;
        }

        $roleId = $this->currentUserRoleId();
        return $roleId ?: ('u:' . (string)$u->id);
    }

    /* ================= Akses ================= */

    private function auditeeRoleIds(SelfEvaluationForm $fed): array
    {
        $candidates = [];

        foreach ([
            'head_auditee_user_role_id',
            'member_auditee_1_user_role_id',
            'member_auditee_2_user_role_id',
            'member_auditee_3_user_role_id',
        ] as $col) {
            if (!empty($fed->{$col})) $candidates[] = (string)$fed->{$col};
        }

        foreach ([
            'head_auditee_role_id',
            'member_auditee_1_role_id',
            'member_auditee_2_role_id',
            'member_auditee_3_role_id',
        ] as $col) {
            if (!empty($fed->{$col})) $candidates[] = (string)$fed->{$col};
        }

        foreach (['created_by', 'updated_by'] as $col) {
            if (!empty($fed->{$col})) $candidates[] = (string)$fed->{$col};
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function ensureUserCanAccessAtl(AuditFindingForm $findingForm, SelfEvaluationForm $fed): void
    {
        if ($this->isAdmin()) return;

        $myRoleId = $this->currentUserRoleId();
        abort_unless($myRoleId, 403, 'User role tidak ditemukan.');

        $allowedAuditor = in_array($myRoleId, [
            $findingForm->auditor_user_role_id,
            $findingForm->member_auditor_user_role_id,
        ], true);

        $allowedAuditee = in_array($myRoleId, $this->auditeeRoleIds($fed), true);

        abort_unless($allowedAuditor || $allowedAuditee, 403, 'Tidak berhak mengakses Audit Tindak Lanjut.');
    }

    private function ensureLeaderOrAdmin(AuditFindingForm $findingForm): void
    {
        if ($this->isAdmin()) return;

        $myRoleId = $this->currentUserRoleId();
        abort_unless($myRoleId, 403, 'User role tidak ditemukan.');

        abort_unless(
            (string)$findingForm->auditor_user_role_id === (string)$myRoleId,
            403,
            'Hanya Ketua Auditor yang boleh melakukan aksi ini.'
        );
    }

    /* ================= Data Builder ================= */

    /**
     * Temuan NEGATIF tahun berjalan (dari findingForm ini saja)
     */
    private function negativeFindings(AuditFindingForm $findingForm): Collection
    {
        return AuditFinding::with([
            'selfEvaluationDetail.standardAchievement',
            'selfEvaluationDetail.indicator.standard',
            'form.selfEvaluationForm.academicConfig',
        ])
            ->where('audit_finding_form_id', $findingForm->id)
            ->where('active', 1)
            ->whereNotNull('severity')
            ->orderBy('finding_no')
            ->get();
    }

    /**
     * ✅ Ambil SEMUA ATL histori (semua academic non-aktif) untuk unit/prodi sama
     * yang status detail-nya Open/Toleran.
     *
     * READ-ONLY untuk tabel atas. TIDAK akan disinkronkan ke ATL tahun berjalan.
     */
    private function prevOpenToleranDetailsAllYears(SelfEvaluationForm $currentFed): Collection
    {
        $currentAcId = (string)($currentFed->academic_config_id ?? '');
        $catId = (string)($currentFed->category_detail_id ?? '');
        if ($catId === '') return collect();

        $prevAcs = AcademicConfig::where('active', false)
            ->when($currentAcId !== '', fn($q) => $q->where('id', '!=', $currentAcId))
            ->orderByDesc('created_at')
            ->get();

        if ($prevAcs->isEmpty()) return collect();

        $all = collect();

        foreach ($prevAcs as $prevAc) {
            $prevFed = SelfEvaluationForm::where('active', 1)
                ->where('academic_config_id', $prevAc->id)
                ->where('category_detail_id', $catId)
                ->latest('created_at')
                ->first();

            if (!$prevFed) continue;

            $prevFindingForm = AuditFindingForm::where('active', 1)
                ->where('self_evaluation_form_id', $prevFed->id)
                ->where('status', self::FORM_STATUS_FINAL)
                ->latest('audit_date')
                ->first();

            if (!$prevFindingForm) continue;

            $prevAtl = AuditFollowUpForm::where('active', 1)
                ->where('audit_finding_form_id', $prevFindingForm->id)
                ->where('status', self::FORM_STATUS_FINAL)
                ->latest('audit_date')
                ->first();

            if (!$prevAtl) continue;

            $rows = AuditFollowUpDetail::with([
                'finding.selfEvaluationDetail.standardAchievement',
                'finding.selfEvaluationDetail.indicator.standard',
                'finding.selfEvaluationDetail.indicator.pics.role',
                'finding.form.selfEvaluationForm.academicConfig',
            ])
                ->where('active', 1)
                ->where('audit_follow_up_form_id', $prevAtl->id)
                ->whereIn('status', ['Open', 'Toleran'])
                ->orderBy('id')
                ->get();

            if ($rows->isNotEmpty()) $all = $all->merge($rows);
        }

        return $all->values();
    }

    /**
     * Sync detail ATL tahun berjalan:
     * - Hanya untuk temuan tahun berjalan (findingForm ini).
     * - Tidak pernah memasukkan temuan histori.
     */
    private function syncFollowUpDetailsCurrentYearOnly(AuditFollowUpForm $form, Collection $findings): void
    {
        $findingIds = $findings->pluck('id')->filter()->values();
        if ($findingIds->isEmpty()) return;

        $existing = AuditFollowUpDetail::where('audit_follow_up_form_id', $form->id)
            ->where('active', 1)
            ->pluck('audit_finding_id')
            ->map(fn($x) => (string)$x);

        $missing = $findingIds->map(fn($x) => (string)$x)->diff($existing);
        if ($missing->isEmpty()) return;

        foreach ($missing as $fid) {
            $row = [
                'id' => AuditFollowUpDetail::generateNextId(),
                'audit_follow_up_form_id' => $form->id,
                'audit_finding_id' => $fid,
                'active' => 1,
            ];

            $actor = $this->auditActorForColumn('audit_follow_up_details', 'updated_by');
            if (!is_null($actor)) $row['updated_by'] = $actor;

            AuditFollowUpDetail::create($row);
        }
    }

    private function isRowComplete(AuditFollowUpDetail $d): bool
    {
        return $this->normalize($d->follow_up_realization) !== ''
            && $this->normalize($d->effectiveness) !== ''
            && $this->normalize($d->status) !== '';
    }

    /* ================= Index ================= */

    public function index(Request $request)
    {
        $active = $this->activeAcademic();
        abort_unless($active, 403, 'Tahun akademik aktif belum diset.');

        $q = trim((string)$request->query('q', ''));

        $forms = AuditFindingForm::with(['selfEvaluationForm.categoryDetail', 'selfEvaluationForm.academicConfig'])
            ->where('active', 1)
            ->where('status', self::FORM_STATUS_FINAL)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('selfEvaluationForm.categoryDetail', fn($x) => $x->where('name', 'like', "%{$q}%"))
                    ->orWhere('area', 'like', "%{$q}%");
            })
            ->orderBy('audit_date', 'desc')
            ->paginate(10)
            ->withQueryString();

        return view('auditor.atl.index', compact('forms', 'q'));
    }

    /* ================= Show ================= */

    public function show(AuditFindingForm $findingForm)
    {
        abort_unless(
            ($findingForm->status ?? '') === self::FORM_STATUS_FINAL,
            403,
            'ATL hanya bisa dibuat dari Form Temuan yang sudah Final.'
        );

        $fed = SelfEvaluationForm::with(['categoryDetail', 'academicConfig'])
            ->findOrFail($findingForm->self_evaluation_form_id);

        $this->ensureUserCanAccessAtl($findingForm, $fed);

        // ✅ 1) prevDetails untuk tabel atas (SEMUA TAHUN HISTORI yang masih Open/Toleran)
        $prevDetails = $this->prevOpenToleranDetailsAllYears($fed);

        // ✅ 2) buat header ATL tahun berjalan
        $atl = null;
        DB::transaction(function () use ($findingForm, &$atl) {
            $atl = AuditFollowUpForm::where('audit_finding_form_id', $findingForm->id)
                ->where('active', 1)
                ->first();

            if (!$atl) {
                $data = [
                    'id' => AuditFollowUpForm::generateNextId(),
                    'audit_finding_form_id' => $findingForm->id,
                    'area' => $findingForm->area,
                    'audit_date' => now()->toDateString(),
                    'status' => self::FORM_STATUS_DRAFT,
                    'active' => 1,
                    'auditor_user_role_id' => $findingForm->auditor_user_role_id,
                    'member_auditor_user_role_id' => $findingForm->member_auditor_user_role_id,
                ];

                $actor = $this->auditActorForColumn('audit_follow_up_forms', 'updated_by');
                if (!is_null($actor)) $data['updated_by'] = $actor;

                $atl = AuditFollowUpForm::create($data);
            }
        });

        // ✅ 3) findings tahun berjalan SAJA
        $negativeFindings = $this->negativeFindings($findingForm);

        // ✅ 4) sync detail rows (HANYA tahun berjalan)
        if (($atl->status ?? '') !== self::FORM_STATUS_FINAL) {
            DB::transaction(function () use ($atl, $negativeFindings) {
                $this->syncFollowUpDetailsCurrentYearOnly($atl, $negativeFindings);
            });
        }

        // ✅ 5) detail untuk tabel bawah: HANYA yang finding-nya dari findingForm tahun berjalan
        $details = AuditFollowUpDetail::with([
            'finding.selfEvaluationDetail.standardAchievement',
            'finding.selfEvaluationDetail.indicator.standard',
            'finding.selfEvaluationDetail.indicator.pics.role',
            'finding.form.selfEvaluationForm.academicConfig',
        ])
            ->where('audit_follow_up_form_id', $atl->id)
            ->where('active', 1)
            ->whereHas('finding', function ($q) use ($findingForm) {
                $q->where('audit_finding_form_id', $findingForm->id);
            })
            ->orderBy('id')
            ->get();

        // progress hanya berdasarkan tabel bawah
        $total = $details->count();
        $complete = $details->filter(fn($d) => $this->isRowComplete($d))->count();
        $progress = [
            'total' => $total,
            'complete' => $complete,
            'percent' => $total ? round(100 * $complete / $total, 1) : 0.0,
        ];

        $unitName = optional($fed->categoryDetail)->name ?? ($findingForm->area ?? 'Unit/Prodi');
        $academicText = optional($fed->academicConfig)->name ?? optional($fed->academicConfig)->tahun ?? null;

        $severityOptions = AuditFinding::SEVERITY_OPTIONS;

        $auditorUserRoles = UserRole::with(['user', 'role'])
            ->where('active', 1)
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        return view('auditor.atl.show', compact(
            'findingForm',
            'fed',
            'atl',
            'details',
            'prevDetails',
            'progress',
            'unitName',
            'academicText',
            'severityOptions',
            'auditorUserRoles'
        ));
    }

    /* ================= Finalize ================= */

    public function finalize(AuditFollowUpForm $form)
    {
        $findingForm = AuditFindingForm::findOrFail($form->audit_finding_form_id);
        $fed = SelfEvaluationForm::findOrFail($findingForm->self_evaluation_form_id);

        $this->ensureUserCanAccessAtl($findingForm, $fed);
        $this->ensureLeaderOrAdmin($findingForm);

        if (($form->status ?? '') === self::FORM_STATUS_FINAL) {
            return back()->with('info', 'Form ATL sudah Final.');
        }

        $details = AuditFollowUpDetail::where('audit_follow_up_form_id', $form->id)
            ->where('active', 1)
            ->whereHas('finding', function ($q) use ($findingForm) {
                $q->where('audit_finding_form_id', $findingForm->id);
            })
            ->get();

        if ($details->isEmpty()) {
            throw ValidationException::withMessages([
                'form' => 'Tidak ada baris ATL tahun berjalan.',
            ]);
        }

        $incomplete = $details->filter(fn($d) => !$this->isRowComplete($d))->count();
        if ($incomplete > 0) {
            throw ValidationException::withMessages([
                'form' => "Masih ada {$incomplete} baris ATL belum lengkap (Realisasi, Efektivitas, Status).",
            ]);
        }

        $payload = ['status' => self::FORM_STATUS_FINAL];
        $actor = $this->auditActorForColumn('audit_follow_up_forms', 'updated_by');
        if (!is_null($actor)) $payload['updated_by'] = $actor;

        $form->update($payload);

        return back()->with('success', 'Form Audit Tindak Lanjut berhasil Final (terkunci).');
    }

    public function searchAuditors(Request $request)
    {
        $q = trim((string)$request->query('q', ''));

        $urs = UserRole::query()
            ->with(['user', 'role'])
            ->where('active', 1)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('user', function ($u) use ($q) {
                    $u->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('username', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json(
            $urs->map(fn($ur) => [
                'id' => $ur->id,
                'name' => optional($ur->user)->name ?? ('User#' . $ur->id),
                'role_name' => optional($ur->role)->name ?? 'Anggota',
            ])->values()
        );
    }
}
