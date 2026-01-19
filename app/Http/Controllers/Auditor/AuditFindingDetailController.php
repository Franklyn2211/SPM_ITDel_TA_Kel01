<?php

namespace App\Http\Controllers;

use App\Models\AuditFinding;
use App\Models\AuditFindingForm;
use App\Models\SelfEvaluationForm;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditFindingDetailController extends Controller
{
    private const FORM_STATUS_FINAL = 'Final';
    private const FED_APPROVED_STATUS_NAME = 'Disetujui';

    /* ================= Helpers ================= */

    private function currentUserRole(): ?UserRole
    {
        $u = auth()->user();
        if (!$u) return null;

        if (!empty($u->user_role_id)) return UserRole::find($u->user_role_id);

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
        abort_if(($form->status ?? '') === self::FORM_STATUS_FINAL, 403, 'Form sudah Final dan tidak bisa diubah.');
    }

    private function ensureFedApproved(SelfEvaluationForm $fed): void
    {
        // sesuai permintaan: relasi FED bernama status()
        $name = mb_strtolower(trim((string) optional($fed->status)->name));
        abort_unless($name === mb_strtolower(self::FED_APPROVED_STATUS_NAME), 403, 'FED belum Disetujui.');
    }

    private function ensureAuditorCanAccessForm(AuditFindingForm $form): void
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

    private function ensureAuditeeCanEditByFed(SelfEvaluationForm $fed): void
    {
        if ($this->isAdmin()) return;

        $myRoleId = $this->currentUserRoleId();
        abort_unless($myRoleId, 403, 'User role tidak ditemukan.');

        $allowed = in_array($myRoleId, [
            $fed->head_auditee_user_role_id,
            $fed->member_auditee_1_user_role_id,
            $fed->member_auditee_2_user_role_id,
            $fed->member_auditee_3_user_role_id,
        ], true);

        abort_unless($allowed, 403, 'Tidak berhak mengisi bagian Auditee untuk unit ini.');
    }

    private function isNegativeFromRow(AuditFinding $row): bool
    {
        $ach = mb_strtolower(trim((string) optional($row->selfEvaluationDetail?->standardAchievement)->name));
        return !in_array($ach, ['mencapai', 'melampaui'], true);
    }

    /* ================== SHOW AUDITEE ================== */
    public function showAuditee(AuditFindingForm $form)
    {
        $fed = SelfEvaluationForm::with(['academicConfig', 'categoryDetail', 'status'])
            ->findOrFail($form->self_evaluation_form_id);

        $this->ensureFedApproved($fed);
        $this->ensureAuditeeCanEditByFed($fed);

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
        $complete = $rows->filter(function ($r) {
            $isNeg = $this->isNegativeFromRow($r);
            if ($isNeg) {
                return trim((string)$r->severity) !== ''
                    && trim((string)$r->auditor_recommendation) !== ''
                    && trim((string)$r->corrective_action_plan) !== ''
                    && !is_null($r->due_date);
            }
            return trim((string)$r->control) !== ''
                && trim((string)$r->improvement) !== ''
                && trim((string)$r->follow_up_plan) !== ''
                && !is_null($r->due_date);
        })->count();

        $progress = [
            'total' => $total,
            'complete' => $complete,
            'percent' => $total ? round(100 * $complete / $total, 1) : 0.0,
        ];

        $severityOptions = \App\Models\AuditFinding::SEVERITY_OPTIONS;

        return view('auditee.temuan.show', compact(
            'fed', 'form', 'rowsPositive', 'rowsNegative', 'progress', 'severityOptions'
        ));
    }

    /* ================== UPDATE AUDITOR (NEGATIF ONLY) ================== */
    public function updateByAuditor(Request $request, AuditFindingForm $form, AuditFinding $row)
    {
        $this->ensureAuditorCanAccessForm($form);
        $this->ensureNotFinal($form);
        abort_unless($row->audit_finding_form_id === $form->id, 404);

        $fed = SelfEvaluationForm::with('status')->findOrFail($form->self_evaluation_form_id);
        $this->ensureFedApproved($fed);

        $row->loadMissing(['selfEvaluationDetail.standardAchievement']);
        abort_unless($this->isNegativeFromRow($row), 422, 'Auditor hanya mengisi temuan NEGATIF.');

        $data = $request->validate([
            'severity' => ['required', 'string', Rule::in(array_keys(\App\Models\AuditFinding::SEVERITY_OPTIONS))],
            'auditor_recommendation' => ['required', 'string'],
        ]);

        $row->update([
            'severity' => $data['severity'],
            'auditor_recommendation' => $data['auditor_recommendation'],
        ]);

        return back()->with('success', 'Kategori temuan & rekomendasi auditor tersimpan.');
    }

    /* ================== UPDATE AUDITEE ================== */
    public function updateByAuditee(Request $request, AuditFindingForm $form, AuditFinding $row)
    {
        $this->ensureNotFinal($form);
        abort_unless($row->audit_finding_form_id === $form->id, 404);

        $fed = SelfEvaluationForm::with('status')->findOrFail($form->self_evaluation_form_id);
        $this->ensureFedApproved($fed);
        $this->ensureAuditeeCanEditByFed($fed);

        $row->loadMissing(['selfEvaluationDetail.standardAchievement']);
        $isNeg = $this->isNegativeFromRow($row);

        if ($isNeg) {
            $data = $request->validate([
                'corrective_action_plan' => ['required', 'string'],
                'due_date' => ['required', 'date'],
            ]);

            $row->update([
                'corrective_action_plan' => $data['corrective_action_plan'],
                'due_date' => $data['due_date'],
            ]);
        } else {
            $data = $request->validate([
                'control' => ['required', 'string'],
                'improvement' => ['required', 'string'],
                'follow_up_plan' => ['required', 'string'],
                'due_date' => ['required', 'date'],
            ]);

            $row->update([
                'control' => $data['control'],
                'improvement' => $data['improvement'],
                'follow_up_plan' => $data['follow_up_plan'],
                'due_date' => $data['due_date'],
            ]);
        }

        return back()->with('success', 'Bagian Auditee tersimpan.');
    }

}
