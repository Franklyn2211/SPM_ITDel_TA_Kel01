<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Models\AuditFinding;
use App\Models\AuditFindingForm;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditFindingDetailController extends Controller
{
    private const FORM_STATUS_FINAL = 'Final';

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

    private function isNegativeFromFinding(AuditFinding $finding): bool
    {
        $ach = mb_strtolower(trim((string) optional($finding->selfEvaluationDetail?->standardAchievement)->name));
        // NEGATIF jika bukan mencapai/melampaui (termasuk kosong)
        return !in_array($ach, ['mencapai', 'melampaui'], true);
    }

    public function updateRow(Request $request, AuditFindingForm $form, AuditFinding $finding)
    {
        $this->ensureUserCanAccessForm($form);
        abort_unless($finding->audit_finding_form_id === $form->id, 404);

        if ($form->status === self::FORM_STATUS_FINAL) {
            return back()->with('warning', 'Form sudah Final dan tidak dapat diubah.');
        }

        // Pastikan relasi ketercapaian kebaca
        $finding->loadMissing(['selfEvaluationDetail.standardAchievement']);

        $isNegative = $this->isNegativeFromFinding($finding);

        $data = $request->validate([
            'control' => ['nullable', 'string'],
            'improvement' => ['nullable', 'string'],
            'follow_up_plan' => ['nullable', 'string'],
            'auditor_recommendation' => ['nullable', 'string'],
            'corrective_action_plan' => ['nullable', 'string'],
            'severity' => [
                'nullable',
                'string',
                Rule::in(array_keys(AuditFinding::SEVERITY_OPTIONS)),
            ],
            'due_date' => ['nullable', 'date'],
        ]);

        // POSITIF: severity harus kosong
        if (!$isNegative) {
            $data['severity'] = null;
        }

        $finding->update([
            'control' => $data['control'] ?? null,
            'improvement' => $data['improvement'] ?? null,
            'follow_up_plan' => $data['follow_up_plan'] ?? null,
            'auditor_recommendation' => $data['auditor_recommendation'] ?? null,
            'corrective_action_plan' => $data['corrective_action_plan'] ?? null,
            'severity' => $data['severity'] ?? null,
            'due_date' => $data['due_date'] ?? null,
        ]);

        return back()->with('success', 'Baris temuan tersimpan (Draft).');
    }
}
