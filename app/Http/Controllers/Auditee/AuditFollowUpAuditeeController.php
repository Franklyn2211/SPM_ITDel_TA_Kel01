<?php

namespace App\Http\Controllers\Auditee;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AuditFinding;
use App\Models\AuditFindingForm;
use App\Models\AuditFollowUpDetail;
use App\Models\AuditFollowUpForm;
use App\Models\SelfEvaluationForm;
use Illuminate\Http\Request;

class AuditFollowUpAuditeeController extends Controller
{
    private const FED_APPROVED_STATUS_NAME = 'Disetujui';
    private const ATL_STATUS_FINAL = 'Final';

    /* ================== Helpers ================== */

    private function activeAcademicId(): ?string
    {
        return AcademicConfig::where('active', true)->value('id');
    }

    private function normalize(?string $v): string
    {
        return trim((string)($v ?? ''));
    }

    /**
     * AUTH sama seperti Temuan
     */
    private function ensureAuditeeCanAccessFed(SelfEvaluationForm $fed): void
    {
        $user = auth()->user();
        abort_unless($user, 403, 'User belum login.');

        $myUserId = (string)$user->id;
        $myName = trim(mb_strtolower((string)($user->name ?? '')));

        // Ketua auditee (NAME)
        $headName = trim(mb_strtolower((string)($fed->head_auditee_name ?? '')));
        if ($headName !== '' && $myName !== '' && $myName === $headName) {
            return;
        }

        // Anggota auditee (USER ID)
        $memberIds = array_filter([
            $fed->member_auditee_1_user_id ?? null,
            $fed->member_auditee_2_user_id ?? null,
            $fed->member_auditee_3_user_id ?? null,
        ], fn($v) => !is_null($v) && (string)$v !== '');

        if (in_array($myUserId, array_map('strval', $memberIds), true)) {
            return;
        }

        abort(403, 'Tidak berhak mengakses ATL unit ini.');
    }

    /**
     * Pastikan ATL:
     * - milik FED tahun aktif
     * - FED disetujui
     * - user auditee valid
     */
    private function ensureAuditeeCanAccessAtl(AuditFollowUpForm $atl): SelfEvaluationForm
    {
        $findingForm = AuditFindingForm::findOrFail($atl->audit_finding_form_id);

        $fed = SelfEvaluationForm::with(['status', 'categoryDetail', 'academicConfig'])
            ->findOrFail($findingForm->self_evaluation_form_id);

        abort_unless(
            (string)$fed->academic_config_id === (string)$this->activeAcademicId(),
            403,
            'ATL bukan milik tahun akademik aktif.'
        );

        abort_unless(
            optional($fed->status)->name === self::FED_APPROVED_STATUS_NAME,
            403,
            'FED belum Disetujui.'
        );

        $this->ensureAuditeeCanAccessFed($fed);

        return $fed;
    }

    /* ================== Index ================== */

    public function index(Request $request)
    {
        $academicId = $this->activeAcademicId();
        abort_unless($academicId, 403, 'Tahun akademik aktif belum diset.');

        $user = auth()->user();
        abort_unless($user, 403, 'User belum login.');

        $myUserId = (string)$user->id;
        $myName = trim(mb_strtolower((string)($user->name ?? '')));
        $q = trim((string)$request->query('q', ''));

        // FED tahun aktif & disetujui yang user punya akses
        $feds = SelfEvaluationForm::with(['categoryDetail'])
            ->where('academic_config_id', $academicId)
            ->where('active', 1)
            ->whereHas('status', fn($s) => $s->where('name', self::FED_APPROVED_STATUS_NAME))
            ->where(function ($qq) use ($myUserId, $myName) {
                if ($myName !== '') {
                    $qq->whereRaw('LOWER(TRIM(head_auditee_name)) = ?', [$myName]);
                }
                $qq->orWhere('member_auditee_1_user_id', $myUserId)
                   ->orWhere('member_auditee_2_user_id', $myUserId)
                   ->orWhere('member_auditee_3_user_id', $myUserId);
            })
            ->when($q !== '', fn($qq) =>
                $qq->whereHas('categoryDetail', fn($x) => $x->where('name', 'like', "%{$q}%"))
            )
            ->get();

        // FindingForm tahun berjalan saja
        $findingFormIds = AuditFindingForm::whereIn('self_evaluation_form_id', $feds->pluck('id'))
            ->where('active', 1)
            ->pluck('id');

        // ✅ ATL tahun berjalan SAJA
        $atls = AuditFollowUpForm::with([
            'findingForm.selfEvaluationForm.categoryDetail',
            'findingForm.selfEvaluationForm.academicConfig',
        ])
            ->whereIn('audit_finding_form_id', $findingFormIds)
            ->where('active', 1)
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('auditee.atl.index', compact('atls', 'q'));
    }

    /* ================== Show ================== */

    public function show(AuditFollowUpForm $atl)
    {
        $fed = $this->ensureAuditeeCanAccessAtl($atl);
        $findingForm = AuditFindingForm::findOrFail($atl->audit_finding_form_id);

        $unitName = $fed->categoryDetail?->name ?? $atl->area ?? 'Unit/Prodi';
        $academicText = $fed->academicConfig?->name ?? $fed->academicConfig?->tahun ?? null;

        // ✅ DETAIL HANYA DARI TEMUAN TAHUN BERJALAN
        $details = AuditFollowUpDetail::with([
            'finding.selfEvaluationDetail.standardAchievement',
            'finding.selfEvaluationDetail.indicator.standard',
            'finding.form.selfEvaluationForm.academicConfig',
        ])
            ->where('audit_follow_up_form_id', $atl->id)
            ->where('active', 1)
            ->whereHas('finding', function ($q) use ($findingForm) {
                $q->where('audit_finding_form_id', $findingForm->id);
            })
            ->paginate(10)
            ->withQueryString();

        $total = $details->total();
        $complete = collect($details->items())->filter(fn($d) =>
            $this->normalize($d->follow_up_realization) !== '' &&
            $this->normalize($d->effectiveness) !== ''
        )->count();

        $progress = [
            'total' => $total,
            'complete' => $complete,
            'percent' => $total ? round(100 * $complete / $total, 1) : 0.0,
        ];

        $isFinal = (($atl->status ?? '') === self::ATL_STATUS_FINAL);
        $severityOptions = \App\Models\AuditFinding::SEVERITY_OPTIONS ?? [];

        return view('auditee.atl.show', compact(
            'atl',
            'details',
            'unitName',
            'academicText',
            'progress',
            'isFinal',
            'severityOptions'
        ));
    }

    /* ================== Update ================== */

    public function updateRow(Request $request, AuditFollowUpForm $atl, AuditFollowUpDetail $detail)
    {
        $fed = $this->ensureAuditeeCanAccessAtl($atl);
        $findingForm = AuditFindingForm::findOrFail($atl->audit_finding_form_id);

        abort_unless((string)$detail->audit_follow_up_form_id === (string)$atl->id, 404);

        if (($atl->status ?? '') === self::ATL_STATUS_FINAL) {
            return back()->with('error', 'ATL sudah Final. Tidak bisa diubah.');
        }

        // ✅ GUARD KRITIS: DETAIL HARUS MILIK TEMUAN TAHUN BERJALAN
        $finding = AuditFinding::findOrFail($detail->audit_finding_id);
        abort_unless(
            (string)$finding->audit_finding_form_id === (string)$findingForm->id,
            403,
            'Detail ini berasal dari ATL histori dan tidak boleh diedit.'
        );

        $data = $request->validate([
            'follow_up_realization' => ['nullable', 'string'],
            'effectiveness' => ['nullable', 'string', 'max:255'],
        ]);

        $detail->follow_up_realization = $this->normalize($data['follow_up_realization'] ?? '') ?: null;
        $detail->effectiveness = $this->normalize($data['effectiveness'] ?? '') ?: null;

        if (property_exists($detail, 'updated_by')) {
            $detail->updated_by = auth()->id();
        }

        $detail->save();

        return back()->with('success', 'Realisasi & Efektivitas berhasil disimpan.');
    }
}
