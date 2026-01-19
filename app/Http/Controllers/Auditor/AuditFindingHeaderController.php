<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AuditFinding;
use App\Models\AuditFindingForm;
use App\Models\EvaluationStatus;
use App\Models\SelfEvaluationDetail;
use App\Models\SelfEvaluationForm;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditFindingHeaderController extends Controller
{
    private const FED_APPROVED_STATUS_NAME = 'Disetujui';
    private const FORM_STATUS_DRAFT = 'Draft';
    private const FORM_STATUS_FINAL = 'Final';

    // role name yang dianggap "anggota auditor"
    private const MEMBER_AUDITOR_ROLE_NAME = 'Anggota Auditor';

    /* ================= Helper umum ================= */

    private function activeAcademicId(): ?string
    {
        return AcademicConfig::where('active', true)->value('id');
    }

    private function currentUserRole(): ?UserRole
    {
        $u = auth()->user();
        if (!$u) return null;

        // kalau user sudah punya pointer ke user_role_id
        if (!empty($u->user_role_id)) {
            return UserRole::with(['user', 'role'])->find($u->user_role_id);
        }

        // fallback pakai cis_user_id (sinkron CIS)
        if (!empty($u->cis_user_id)) {
            return UserRole::with(['user', 'role'])
                ->where('cis_user_id', $u->cis_user_id)
                ->where('active', 1)
                ->first()
                ?? UserRole::with(['user', 'role'])
                    ->where('cis_user_id', $u->cis_user_id)
                    ->latest('created_at')
                    ->first();
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
        return (bool) $u && $u->username === 'adminspm';
    }

    private function fedApprovedStatusId(): ?string
    {
        return EvaluationStatus::where('name', self::FED_APPROVED_STATUS_NAME)->value('id');
    }

    private function ensureFedApproved(SelfEvaluationForm $fed): void
    {
        // lebih aman: cek status_id langsung, bukan ngandelin relation "status" selalu terload
        $approvedId = $this->fedApprovedStatusId();
        abort_unless($approvedId, 500, 'Status FED "Disetujui" tidak ditemukan.');

        abort_unless((string) $fed->status_id === (string) $approvedId, 403, 'FED belum Disetujui.');
    }

    private function ensureUserCanAccessForm(AuditFindingForm $form): void
    {
        if ($this->isAdmin()) return;

        $myRoleId = $this->currentUserRoleId();
        abort_unless($myRoleId, 403, 'User role tidak ditemukan.');

        $allowed = in_array($myRoleId, [
            $form->auditor_user_role_id,
            $form->member_auditor_user_role_id,
        ], true);

        abort_unless($allowed, 403, 'Tidak berhak mengakses form temuan ini.');
    }

    private function ensureLeaderOrAdmin(AuditFindingForm $form): void
    {
        if ($this->isAdmin()) return;

        $myRoleId = $this->currentUserRoleId();
        abort_unless($myRoleId, 403, 'User role tidak ditemukan.');

        abort_unless(
            (string) $form->auditor_user_role_id === (string) $myRoleId,
            403,
            'Hanya Ketua Auditor yang boleh melakukan aksi ini.'
        );
    }

    private function normalize(?string $v): string
    {
        return trim((string) ($v ?? ''));
    }

    private function isNegativeFromRow(AuditFinding $row): bool
    {
        $ach = mb_strtolower(trim((string) optional($row->selfEvaluationDetail?->standardAchievement)->name));
        return !in_array($ach, ['mencapai', 'melampaui'], true);
    }

    private function isRowComplete(AuditFinding $row, bool $isNegative): bool
    {
        if ($isNegative) {
            // negatif butuh: auditor(severity+rekom) + auditee(cap+due_date)
            return $this->normalize($row->severity) !== ''
                && $this->normalize($row->auditor_recommendation) !== ''
                && $this->normalize($row->corrective_action_plan) !== ''
                && !is_null($row->due_date);
        }

        // positif: auditee isi semua
        return $this->normalize($row->control) !== ''
            && $this->normalize($row->improvement) !== ''
            && $this->normalize($row->follow_up_plan) !== ''
            && !is_null($row->due_date);
    }

    private function syncFindings(AuditFindingForm $form, SelfEvaluationForm $fed): void
    {
        $detailIds = SelfEvaluationDetail::where('self_evaluation_form_id', $fed->id)
            ->where('active', 1)
            ->orderBy('ami_standard_indicator_id')
            ->pluck('id');

        if ($detailIds->isEmpty()) return;

        $existing = AuditFinding::where('audit_finding_form_id', $form->id)
            ->where('active', 1)
            ->pluck('self_evaluation_detail_id');

        $missing = $detailIds->diff($existing);
        if ($missing->isEmpty()) return;

        $maxNo = (int) AuditFinding::where('audit_finding_form_id', $form->id)->max('finding_no');

        foreach ($missing as $detailId) {
            $maxNo++;
            AuditFinding::create([
                'id' => AuditFinding::generateNextId(),
                'audit_finding_form_id' => $form->id,
                'self_evaluation_detail_id' => $detailId,
                'finding_no' => $maxNo,
                'active' => 1,
            ]);
        }
    }

    /* ================= Index ================= */

    public function index(Request $request)
    {
        $academicId = $this->activeAcademicId();
        abort_unless($academicId, 403, 'Tahun akademik aktif belum diset.');

        $approvedId = $this->fedApprovedStatusId();
        abort_unless($approvedId, 500, 'Status FED "Disetujui" tidak ditemukan.');

        $q = trim((string) $request->query('q', ''));

        $feds = SelfEvaluationForm::with(['categoryDetail', 'status'])
            ->where('academic_config_id', $academicId)
            ->where('active', 1)
            ->where('status_id', $approvedId)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('categoryDetail', fn($x) => $x->where('name', 'like', "%{$q}%"));
            })
            ->orderBy('category_detail_id')
            ->get();

        return view('auditor.temuan.index', compact('feds', 'q'));
    }

    /* ================= Show ================= */

    public function show(SelfEvaluationForm $fed)
    {
        $academicId = $this->activeAcademicId();
        abort_unless($academicId && (string) $fed->academic_config_id === (string) $academicId, 403, 'FED bukan tahun aktif.');

        $this->ensureFedApproved($fed);

        $myRoleId = $this->currentUserRoleId();
        abort_unless($myRoleId, 403, 'User role tidak ditemukan.');

        $form = null;

        DB::transaction(function () use ($fed, $myRoleId, &$form) {
            $form = AuditFindingForm::where('self_evaluation_form_id', $fed->id)
                ->where('active', 1)
                ->first();

            if (!$form) {
                $form = AuditFindingForm::create([
                    'id' => AuditFindingForm::generateNextId(),
                    'self_evaluation_form_id' => $fed->id,
                    'area' => optional($fed->categoryDetail)->name ?? null,
                    'audit_date' => now()->toDateString(),
                    'status' => self::FORM_STATUS_DRAFT,
                    'active' => 1,
                    // ketua auditor pertama = yang membuka form ini
                    'auditor_user_role_id' => $myRoleId,
                ]);
            }

            if (($form->status ?? '') !== self::FORM_STATUS_FINAL) {
                $this->syncFindings($form, $fed);
            }
        });

        $this->ensureUserCanAccessForm($form);

        // penting: load role juga biar blade bisa tampil "(Anggota Auditor)"
        $form->loadMissing([
            'auditorUserRole.user',
            'auditorUserRole.role',
            'memberAuditorUserRole.user',
            'memberAuditorUserRole.role',
        ]);

        $rows = AuditFinding::with([
                'selfEvaluationDetail.standardAchievement',
                'selfEvaluationDetail.indicator.standard',
                'selfEvaluationDetail.indicator.pics.role',
            ])
            ->where('audit_finding_form_id', $form->id)
            ->where('active', 1)
            ->orderBy('finding_no')
            ->get();

        $rowsPositive = $rows->filter(fn($r) => !$this->isNegativeFromRow($r))->values();
        $rowsNegative = $rows->filter(fn($r) =>  $this->isNegativeFromRow($r))->values();

        $total = $rows->count();
        $complete = $rows->filter(fn($r) => $this->isRowComplete($r, $this->isNegativeFromRow($r)))->count();

        $progress = [
            'total' => $total,
            'complete' => $complete,
            'percent' => $total ? round(100 * $complete / $total, 1) : 0.0,
        ];

        $severityOptions = AuditFinding::SEVERITY_OPTIONS;

        return view('auditor.temuan.show', compact(
            'fed',
            'form',
            'rowsPositive',
            'rowsNegative',
            'progress',
            'severityOptions'
        ));
    }

    /* ================= Update Header ================= */

    public function updateHeader(Request $request, AuditFindingForm $form)
    {
        $this->ensureUserCanAccessForm($form);

        if (($form->status ?? '') === self::FORM_STATUS_FINAL) {
            return back()->with('warning', 'Form sudah Final dan tidak dapat diubah.');
        }

        $this->ensureLeaderOrAdmin($form);

        $data = $request->validate([
            'area' => ['nullable', 'string', 'max:255'],
            'audit_date' => ['nullable', 'date'],
            'member_auditor_user_role_id' => ['nullable', 'string', 'exists:user_roles,id'],
        ]);

        $memberId = $data['member_auditor_user_role_id'] ?? null;

        // cegah anggota = ketua
        if ($memberId && (string) $memberId === (string) $form->auditor_user_role_id) {
            return back()->withErrors([
                'member_auditor_user_role_id' => 'Anggota auditor tidak boleh sama dengan ketua auditor.'
            ]);
        }

        // VALIDASI KERAS: member harus role "Anggota Auditor" dan aktif
        if ($memberId) {
            $ok = UserRole::where('id', $memberId)
                ->where('active', 1)
                ->whereHas('role', fn($r) => $r->where('name', self::MEMBER_AUDITOR_ROLE_NAME))
                ->exists();

            if (!$ok) {
                return back()->withErrors([
                    'member_auditor_user_role_id' => 'User yang dipilih bukan Anggota Auditor atau tidak aktif.'
                ]);
            }
        }

        $form->update([
            'area' => $data['area'] ?? $form->area,
            'audit_date' => $data['audit_date'] ?? $form->audit_date,
            'member_auditor_user_role_id' => $memberId ?: null,
        ]);

        return back()->with('success', 'Header & anggota auditor berhasil diperbarui.');
    }

    /* ================= Search Auditors (Select2 AJAX) ================= */

    public function searchAuditors(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $rows = UserRole::query()
            ->with(['user:id,name,email,username,cis_user_id', 'role:id,name'])
            ->where('active', 1)
            ->whereHas('role', fn($r) => $r->where('name', self::MEMBER_AUDITOR_ROLE_NAME))
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
            $rows->map(function ($ur) {
                $name = $ur->user?->name
                    ?? $ur->user?->username
                    ?? 'Tanpa Nama';

                $role = $ur->role?->name;

                return [
                    'id' => $ur->id,                 // user_roles.id
                    'text' => $name,                 // ini yang ditampilin Select2
                    'role_name' => $role,            // info tambahan
                ];
            })->values()
        );
    }


    /* ================= Finalize ================= */

    public function finalize(AuditFindingForm $form)
    {
        $this->ensureUserCanAccessForm($form);

        if (($form->status ?? '') === self::FORM_STATUS_FINAL) {
            return back()->with('info', 'Form sudah Final.');
        }

        $this->ensureLeaderOrAdmin($form);

        $rows = AuditFinding::with(['selfEvaluationDetail.standardAchievement'])
            ->where('audit_finding_form_id', $form->id)
            ->where('active', 1)
            ->get();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['form' => 'Tidak ada baris temuan.']);
        }

        $incomplete = $rows->filter(fn($r) => !$this->isRowComplete($r, $this->isNegativeFromRow($r)))->count();

        if ($incomplete > 0) {
            throw ValidationException::withMessages([
                'form' => "Masih ada {$incomplete} baris temuan belum lengkap. Lengkapi sebelum Final.",
            ]);
        }

        $form->update(['status' => self::FORM_STATUS_FINAL]);

        return back()->with('success', 'Form Temuan berhasil Final (terkunci).');
    }
}
