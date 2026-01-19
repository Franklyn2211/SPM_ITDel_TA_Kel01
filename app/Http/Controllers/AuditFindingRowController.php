<?php

namespace App\Http\Controllers;

use App\Models\AuditFinding;
use App\Models\AuditFindingForm;
use App\Models\SelfEvaluationForm;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditFindingRowController extends Controller
{
    private const FORM_STATUS_FINAL = 'Final';
    private const FED_APPROVED_STATUS_NAME = 'Disetujui';

    /* ================= Auth Helpers ================= */

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

    private function ensureNotFinal(AuditFindingForm $form): void
    {
        abort_unless($form->status !== self::FORM_STATUS_FINAL, 403, 'Form sudah Final dan tidak dapat diubah.');
    }

    private function ensureFedApproved(SelfEvaluationForm $fed): void
    {
        $fed->loadMissing('status');
        abort_unless(optional($fed->status)->name === self::FED_APPROVED_STATUS_NAME, 403, 'FED belum Disetujui.');
    }

    private function ensureAuditorCanAccess(AuditFindingForm $form): void
    {
        if ($this->isAdmin()) return;

        $myRoleId = $this->currentUserRoleId();
        abort_unless($myRoleId, 403, 'User role tidak ditemukan.');

        $allowed = in_array($myRoleId, [
            $form->auditor_user_role_id,
            $form->member_auditor_user_role_id,
        ], true);

        abort_unless($allowed, 403, 'Tidak berhak mengubah bagian auditor.');
    }

    /**
     * AUTH yang sesuai DB:
     * - Ketua Auditee: compare user->name dengan fed->head_auditee_name
     * - Anggota Auditee: compare user->id dengan fed->member_auditee_*_user_id
     */
    private function ensureAuditeeCanAccess(SelfEvaluationForm $fed): void
    {
        if ($this->isAdmin()) return;

        $user = auth()->user();
        abort_unless($user, 403, 'User belum login.');

        $myUserId = (string) $user->id;
        $myName   = trim(mb_strtolower((string) ($user->name ?? '')));

        $headName = trim(mb_strtolower((string) ($fed->head_auditee_name ?? '')));
        if ($headName !== '' && $myName !== '' && $myName === $headName) {
            return;
        }

        $memberIds = array_filter([
            $fed->member_auditee_1_user_id ?? null,
            $fed->member_auditee_2_user_id ?? null,
            $fed->member_auditee_3_user_id ?? null,
        ], fn($v) => !is_null($v) && (string)$v !== '');

        $memberIds = array_map('strval', $memberIds);

        if (in_array($myUserId, $memberIds, true)) {
            return;
        }

        abort(403, 'Tidak berhak mengubah bagian auditee.');
    }

    /* ================= Row Type Helpers ================= */

    private function isNegative(AuditFinding $row): bool
    {
        $row->loadMissing('selfEvaluationDetail.standardAchievement');

        $ach = mb_strtolower(trim((string) optional($row->selfEvaluationDetail?->standardAchievement)->name));
        return !in_array($ach, ['mencapai', 'melampaui'], true);
    }

    /* ================== Updates ================== */

    // Auditee: POSITIF isi control/improvement/follow_up_plan/due_date
    //         NEGATIF isi corrective_action_plan/due_date
    public function updateByAuditee(Request $request, AuditFindingForm $form, AuditFinding $row)
    {
        $this->ensureNotFinal($form);
        abort_unless($row->audit_finding_form_id === $form->id, 404);

        $fed = SelfEvaluationForm::findOrFail($form->self_evaluation_form_id);
        $this->ensureFedApproved($fed);
        $this->ensureAuditeeCanAccess($fed);

        $isNeg = $this->isNegative($row);

        if ($isNeg) {
            $data = $request->validate([
                'corrective_action_plan' => ['nullable', 'string'],
                'due_date' => ['nullable', 'date'],
            ]);

            $row->update([
                'corrective_action_plan' => $data['corrective_action_plan'] ?? null,
                'due_date' => $data['due_date'] ?? null,
            ]);

            return back()->with('success', 'Bagian Auditee (Negatif) tersimpan.');
        }

        $data = $request->validate([
            'control' => ['nullable', 'string'],
            'improvement' => ['nullable', 'string'],
            'follow_up_plan' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);

        $row->update([
            'control' => $data['control'] ?? null,
            'improvement' => $data['improvement'] ?? null,
            'follow_up_plan' => $data['follow_up_plan'] ?? null,
            'due_date' => $data['due_date'] ?? null,
        ]);

        return back()->with('success', 'Bagian Auditee (Positif) tersimpan.');
    }

    // Auditor: NEGATIF isi severity + auditor_recommendation saja
    public function updateByAuditor(Request $request, AuditFindingForm $form, AuditFinding $row)
    {
        $this->ensureNotFinal($form);
        abort_unless($row->audit_finding_form_id === $form->id, 404);

        $this->ensureAuditorCanAccess($form);

        $fed = SelfEvaluationForm::findOrFail($form->self_evaluation_form_id);
        $this->ensureFedApproved($fed);

        $isNeg = $this->isNegative($row);
        abort_unless($isNeg, 403, 'Temuan POSITIF tidak memiliki input auditor.');

        $data = $request->validate([
            'severity' => [
                'nullable',
                'string',
                Rule::in(array_keys(AuditFinding::SEVERITY_OPTIONS)),
            ],
            'auditor_recommendation' => ['nullable', 'string'],
        ]);

        $row->update([
            'severity' => $data['severity'] ?? null,
            'auditor_recommendation' => $data['auditor_recommendation'] ?? null,
        ]);

        return back()->with('success', 'Bagian Auditor tersimpan.');
    }
}
