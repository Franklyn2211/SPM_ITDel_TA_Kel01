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

    private function activeAcademicId(): ?string
    {
        return AcademicConfig::where('active', true)->value('id');
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
        return (bool) $u && $u->username === 'adminspm';
    }

    private function fedApprovedStatusId(): ?string
    {
        return EvaluationStatus::where('name', self::FED_APPROVED_STATUS_NAME)->value('id');
    }

    private function ensureFedApproved(SelfEvaluationForm $fed): void
    {
        $approvedId = $this->fedApprovedStatusId();
        abort_unless($approvedId && $fed->status_id === $approvedId, 403, 'FED belum Disetujui.');
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

        abort_unless($form->auditor_user_role_id === $myRoleId, 403, 'Hanya Ketua Auditor yang boleh melakukan aksi ini.');
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
        $ok = $this->normalize($row->control) !== ''
            && $this->normalize($row->improvement) !== ''
            && $this->normalize($row->follow_up_plan) !== ''
            && $this->normalize($row->auditor_recommendation) !== ''
            && $this->normalize($row->corrective_action_plan) !== ''
            && !is_null($row->due_date);

        if (!$ok) return false;

        // NEGATIF: severity wajib saat final
        if ($isNegative) {
            return $this->normalize($row->severity) !== '';
        }

        return true;
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

    public function show(SelfEvaluationForm $fed)
    {
        $academicId = $this->activeAcademicId();
        abort_unless($academicId && $fed->academic_config_id === $academicId, 403, 'FED bukan tahun aktif.');

        $this->ensureFedApproved($fed);

        $myRoleId = $this->currentUserRoleId();

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
                    'auditor_user_role_id' => $myRoleId,
                ]);
            }

            if ($form->status !== self::FORM_STATUS_FINAL) {
                $this->syncFindings($form, $fed);
            }
        });

        $this->ensureUserCanAccessForm($form);

        // Eager load: FED data + standardAchievement + PIC indikator (sesuaikan relasi "pics" kalau beda)
        $rows = AuditFinding::with([
                'selfEvaluationDetail.standardAchievement',
                'selfEvaluationDetail.indicator.standard',
                'selfEvaluationDetail.indicator.pics.role', // <- asumsi
            ])
            ->where('audit_finding_form_id', $form->id)
            ->where('active', 1)
            ->orderBy('finding_no')
            ->get();

        // split
        $rowsPositive = $rows->filter(fn($r) => !$this->isNegativeFromRow($r))->values();
        $rowsNegative = $rows->filter(fn($r) =>  $this->isNegativeFromRow($r))->values();

        // progress lengkap (berdasarkan tipe otomatis)
        $total = $rows->count();
        $complete = $rows->filter(function ($r) {
            return $this->isRowComplete($r, $this->isNegativeFromRow($r));
        })->count();

        $progress = [
            'total' => $total,
            'complete' => $complete,
            'percent' => $total ? round(100 * $complete / $total, 1) : 0.0,
        ];

        $auditorUserRoles = UserRole::with(['user', 'role'])
            ->where('active', 1)
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        $severityOptions = AuditFinding::SEVERITY_OPTIONS;

        return view('auditor.temuan.show', compact(
            'fed',
            'form',
            'rowsPositive',
            'rowsNegative',
            'progress',
            'auditorUserRoles',
            'severityOptions'
        ));
    }

    public function updateHeader(Request $request, AuditFindingForm $form)
    {
        $this->ensureUserCanAccessForm($form);

        if ($form->status === self::FORM_STATUS_FINAL) {
            return back()->with('warning', 'Form sudah Final dan tidak dapat diubah.');
        }

        $this->ensureLeaderOrAdmin($form);

        $data = $request->validate([
            'area' => ['nullable', 'string', 'max:255'],
            'audit_date' => ['nullable', 'date'],
            'auditor_user_role_id' => ['required', 'exists:user_roles,id'],
            'member_auditor_user_role_id' => ['nullable', 'exists:user_roles,id', 'different:auditor_user_role_id'],
        ]);

        $form->update([
            'area' => $data['area'] ?? $form->area,
            'audit_date' => $data['audit_date'] ?? $form->audit_date,
            'auditor_user_role_id' => $data['auditor_user_role_id'],
            'member_auditor_user_role_id' => $data['member_auditor_user_role_id'] ?? null,
        ]);

        return back()->with('success', 'Header & assignment auditor berhasil diperbarui.');
    }

    public function finalize(AuditFindingForm $form)
    {
        $this->ensureUserCanAccessForm($form);

        if ($form->status === self::FORM_STATUS_FINAL) {
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

        $incomplete = $rows->filter(function ($r) {
            $isNeg = $this->isNegativeFromRow($r);
            return !$this->isRowComplete($r, $isNeg);
        })->count();

        if ($incomplete > 0) {
            throw ValidationException::withMessages([
                'form' => "Masih ada {$incomplete} baris temuan belum lengkap. Lengkapi sebelum Final.",
            ]);
        }

        $form->update(['status' => self::FORM_STATUS_FINAL]);

        return back()->with('success', 'Form Temuan berhasil Final (terkunci).');
    }
}
