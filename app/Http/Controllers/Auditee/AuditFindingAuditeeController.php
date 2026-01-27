<?php

namespace App\Http\Controllers\Auditee;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AuditFinding;
use App\Models\AuditFindingForm;
use App\Models\SelfEvaluationForm;
use Illuminate\Http\Request;

class AuditFindingAuditeeController extends Controller
{
    private const FED_APPROVED_STATUS_NAME = 'Disetujui';

    private function activeAcademicId(): ?string
    {
        return AcademicConfig::where('active', true)->value('id');
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

    private function auditeeComplete(AuditFinding $row, bool $isNeg): bool
    {
        if ($isNeg) {
            return $this->normalize($row->corrective_action_plan) !== '' && !is_null($row->due_date);
        }

        return $this->normalize($row->control) !== ''
            && $this->normalize($row->improvement) !== ''
            && $this->normalize($row->follow_up_plan) !== ''
            && !is_null($row->due_date);
    }

    /**
     * AUTH yang sesuai DB:
     * - Ketua Auditee: compare user->name dengan fed->head_auditee_name
     * - Anggota Auditee: compare user->id dengan fed->member_auditee_*_user_id
     */
    private function ensureAuditeeCanAccess(SelfEvaluationForm $fed): void
    {
        $user = auth()->user();
        abort_unless($user, 403, 'User belum login.');

        $myUserId = (string) $user->id;
        $myName = trim(mb_strtolower((string) ($user->name ?? '')));

        // 1) Ketua auditee (STRING NAME)
        $headName = trim(mb_strtolower((string) ($fed->head_auditee_name ?? '')));
        if ($headName !== '' && $myName !== '' && $myName === $headName) {
            return;
        }

        // 2) Anggota auditee (USER ID)
        $memberIds = array_filter([
            $fed->member_auditee_1_user_id ?? null,
            $fed->member_auditee_2_user_id ?? null,
            $fed->member_auditee_3_user_id ?? null,
        ], fn($v) => !is_null($v) && (string) $v !== '');

        $memberIds = array_map('strval', $memberIds);

        if (in_array($myUserId, $memberIds, true)) {
            return;
        }

        abort(403, 'Tidak berhak mengakses temuan unit ini.');
    }

    public function index(Request $request)
    {

        $user = auth()->user();
        abort_unless($user, 403, 'User belum login.');

        $myUserId = (string) $user->id;
        $myName = trim(mb_strtolower((string) ($user->name ?? '')));

        $q = trim((string) $request->query('q', ''));

        $academicOptions = AcademicConfig::orderByDesc('active')->orderBy('name')->get();
        $prodiOptions = \App\Models\RefCategoryDetail::orderBy('name')->get();
        $statusOptions = \App\Models\EvaluationStatus::orderBy('name')->get();

        $academicId = $request->query('academic_id');
        $prodiId = $request->query('prodi_id');
        $statusId = $request->query('status_id');

        $feds = SelfEvaluationForm::with(['categoryDetail', 'status', 'academicConfig'])
            ->where('active', 1)
            ->whereHas('status', fn($s) => $s->where('name', self::FED_APPROVED_STATUS_NAME))
            ->where(function ($qq) use ($myUserId, $myName) {
                // Ketua (berdasarkan NAMA)
                if ($myName !== '') {
                    $qq->whereRaw('LOWER(TRIM(head_auditee_name)) = ?', [$myName]);
                }
                // Anggota (berdasarkan USER ID)
                $qq->orWhere('member_auditee_1_user_id', $myUserId)
                    ->orWhere('member_auditee_2_user_id', $myUserId)
                    ->orWhere('member_auditee_3_user_id', $myUserId);
            })
            ->when($academicId, fn($qq) => $qq->where('academic_config_id', $academicId))
            ->when($prodiId, fn($qq) => $qq->where('category_detail_id', $prodiId))
            ->when($statusId, fn($qq) => $qq->where('status_id', $statusId))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('categoryDetail', fn($x) => $x->where('name', 'like', "%{$q}%"));
            })
            ->orderBy('category_detail_id')
            ->get();

        return view('auditee.temuan.index', compact('feds', 'q', 'academicOptions', 'prodiOptions', 'statusOptions', 'academicId', 'prodiId', 'statusId'));
    }

    public function show(SelfEvaluationForm $fed)
    {
        $academicId = $this->activeAcademicId();
        abort_unless($academicId && $fed->academic_config_id === $academicId, 403, 'FED bukan tahun aktif.');

        $fed->loadMissing(['status', 'categoryDetail', 'academicConfig']);
        abort_unless(optional($fed->status)->name === self::FED_APPROVED_STATUS_NAME, 403, 'FED belum Disetujui.');

        $this->ensureAuditeeCanAccess($fed);

        $form = AuditFindingForm::where('self_evaluation_form_id', $fed->id)
            ->where('active', 1)
            ->first();

        abort_unless($form, 404, 'Form temuan belum dibuat auditor.');

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
        $rowsNegative = $rows->filter(fn($r) => $this->isNegativeFromRow($r))->values();

        $total = $rows->count();
        $complete = $rows->filter(function ($r) {
            $isNeg = $this->isNegativeFromRow($r);
            return $this->auditeeComplete($r, $isNeg);
        })->count();

        $progress = [
            'total' => $total,
            'complete' => $complete,
            'percent' => $total ? round(100 * $complete / $total, 1) : 0.0,
        ];

        $severityOptions = \App\Models\AuditFinding::SEVERITY_OPTIONS;

        return view('auditee.temuan.show', compact(
            'fed',
            'form',
            'rowsPositive',
            'rowsNegative',
            'progress',
            'severityOptions'
        ));
    }
}
