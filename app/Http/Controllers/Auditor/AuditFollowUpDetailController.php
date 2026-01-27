<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Models\AuditFinding;
use App\Models\AuditFindingForm;
use App\Models\AuditFollowUpDetail;
use App\Models\AuditFollowUpForm;
use App\Models\SelfEvaluationForm;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AuditFollowUpDetailController extends Controller
{
    private const FORM_STATUS_FINAL = 'Final';
    private const STATUS_OPTIONS = ['Open', 'Toleran', 'Closed'];

    private function normalize(?string $v): string
    {
        return trim((string)($v ?? ''));
    }

    private function auditeeCompleted(AuditFollowUpDetail $detail): bool
    {
        return $this->normalize($detail->follow_up_realization) !== ''
            && $this->normalize($detail->effectiveness) !== '';
    }

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
        return (bool)$u && $u->username === 'adminspm';
    }

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

    private function isAuditorRole(AuditFollowUpForm $form, ?string $myRoleId): bool
    {
        if (!$myRoleId) return false;

        return in_array($myRoleId, [
            $form->auditor_user_role_id,
            $form->member_auditor_user_role_id,
        ], true);
    }

    private function isAuditeeRole(SelfEvaluationForm $fed, ?string $myRoleId): bool
    {
        if (!$myRoleId) return false;
        return in_array($myRoleId, $this->auditeeRoleIds($fed), true);
    }

    private function ensureUserCanAccessAtl(AuditFollowUpForm $form, AuditFindingForm $findingForm, SelfEvaluationForm $fed): void
    {
        if ($this->isAdmin()) return;

        $myRoleId = $this->currentUserRoleId();
        abort_unless($myRoleId, 403, 'User role tidak ditemukan.');

        $allowed = $this->isAuditorRole($form, $myRoleId) || $this->isAuditeeRole($fed, $myRoleId);
        abort_unless($allowed, 403, 'Tidak berhak mengakses Audit Tindak Lanjut.');
    }

    private function auditActorForColumn(string $table, string $column): mixed
    {
        $u = auth()->user();
        if (!$u) return null;
        if (!Schema::hasColumn($table, $column)) return null;

        $type = Schema::getColumnType($table, $column);
        if (in_array($type, ['integer', 'bigint', 'smallint'], true)) return $u->id;

        $roleId = $this->currentUserRoleId();
        return $roleId ?: ('u:' . (string)$u->id);
    }

    public function updateRow(Request $request, AuditFollowUpForm $form, AuditFollowUpDetail $detail)
    {
        abort_unless((string)$detail->audit_follow_up_form_id === (string)$form->id, 404);

        $findingForm = AuditFindingForm::findOrFail($form->audit_finding_form_id);
        $fed = SelfEvaluationForm::findOrFail($findingForm->self_evaluation_form_id);

        $this->ensureUserCanAccessAtl($form, $findingForm, $fed);

        if (($form->status ?? '') === self::FORM_STATUS_FINAL) {
            return back()->with('warning', 'Form ATL sudah Final dan tidak dapat diubah.');
        }

        // ✅ GUARD: detail yang diedit harus berasal dari findingForm tahun berjalan
        $finding = AuditFinding::find($detail->audit_finding_id);
        abort_unless($finding, 404, 'Temuan tidak ditemukan.');
        abort_unless(
            (string)$finding->audit_finding_form_id === (string)$findingForm->id,
            403,
            'Baris ini berasal dari ATL histori (read-only) dan tidak boleh diedit di tahun berjalan.'
        );

        $myRoleId = $this->currentUserRoleId();
        $isAdmin  = $this->isAdmin();
        $isAuditor = $this->isAuditorRole($form, $myRoleId);
        $isAuditee = $this->isAuditeeRole($fed, $myRoleId);

        $data = $request->validate([
            'follow_up_realization' => ['nullable', 'string'],
            'effectiveness'         => ['nullable', 'string', 'max:50'],
            'status'                => ['nullable', 'string', Rule::in(self::STATUS_OPTIONS)],
            'status_description'    => ['nullable', 'string'],
        ]);

        $payload = [];

        if ($isAdmin || $isAuditee) {
            if (array_key_exists('follow_up_realization', $data)) $payload['follow_up_realization'] = $data['follow_up_realization'];
            if (array_key_exists('effectiveness', $data)) $payload['effectiveness'] = $data['effectiveness'];
        }

        if ($isAdmin || $isAuditor) {
            if (!$isAdmin && $isAuditor && !$this->auditeeCompleted($detail)) {
                return back()->with('warning', 'Auditor belum dapat mengisi Status. Tunggu Auditee menyelesaikan Realisasi & Efektivitas terlebih dahulu.');
            }
            if (array_key_exists('status', $data)) $payload['status'] = $data['status'];
            if (array_key_exists('status_description', $data)) $payload['status_description'] = $data['status_description'];
        }

        if (empty($payload)) {
            return back()->with('warning', 'Tidak ada perubahan yang diizinkan untuk role kamu.');
        }

        $actor = $this->auditActorForColumn('audit_follow_up_details', 'updated_by');
        if (!is_null($actor)) $payload['updated_by'] = $actor;

        $detail->update($payload);

        return back()->with('success', 'Baris ATL tersimpan (Draft).');
    }
}
