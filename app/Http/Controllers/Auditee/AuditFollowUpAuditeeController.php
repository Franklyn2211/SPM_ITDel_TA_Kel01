<?php

namespace App\Http\Controllers\Auditee;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AuditFindingForm;
use App\Models\AuditFollowUpDetail;
use App\Models\AuditFollowUpForm;
use App\Models\SelfEvaluationForm;
use Illuminate\Http\Request;

class AuditFollowUpAuditeeController extends Controller
{
    private const FED_APPROVED_STATUS_NAME = 'Disetujui';
    private const ATL_STATUS_FINAL = 'Final';

    /* ================== Helpers mirip Temuan ================== */

    private function activeAcademicId(): ?string
    {
        return AcademicConfig::where('active', true)->value('id');
    }

    private function normalize(?string $v): string
    {
        return trim((string) ($v ?? ''));
    }

    /**
     * AUTH yang sama persis seperti temuan:
     * - Ketua Auditee: compare user->name dengan fed->head_auditee_name
     * - Anggota Auditee: compare user->id dengan fed->member_auditee_*_user_id
     */
    private function ensureAuditeeCanAccessFed(SelfEvaluationForm $fed): void
    {
        $user = auth()->user();
        abort_unless($user, 403, 'User belum login.');

        $myUserId = (string) $user->id;
        $myName   = trim(mb_strtolower((string) ($user->name ?? '')));

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
        ], fn($v) => !is_null($v) && (string)$v !== '');

        $memberIds = array_map('strval', $memberIds);

        if (in_array($myUserId, $memberIds, true)) {
            return;
        }

        abort(403, 'Tidak berhak mengakses ATL unit ini.');
    }

    /**
     * Pastikan ATL memang milik FED yang bisa diakses auditee.
     * Caranya: atl -> findingForm -> selfEvaluationForm -> cek akses sama seperti temuan.
     */
    private function ensureAuditeeCanAccessAtl(AuditFollowUpForm $atl): SelfEvaluationForm
    {
        $findingForm = $atl->findingForm ?? AuditFindingForm::find($atl->audit_finding_form_id);
        abort_unless($findingForm, 404, 'Form temuan tidak ditemukan.');

        $fed = SelfEvaluationForm::with(['status', 'categoryDetail', 'academicConfig'])
            ->findOrFail($findingForm->self_evaluation_form_id);

        $academicId = $this->activeAcademicId();
        abort_unless($academicId && (string)$fed->academic_config_id === (string)$academicId, 403, 'FED bukan tahun aktif.');

        abort_unless(optional($fed->status)->name === self::FED_APPROVED_STATUS_NAME, 403, 'FED belum Disetujui.');

        $this->ensureAuditeeCanAccessFed($fed);

        return $fed;
    }

    /* ================== Pages ================== */

    /**
     * Index ATL auditee: mirip temuan index.
     * Bedanya: yang ditampilkan adalah ATL yang sudah dibuat (audit_follow_up_forms)
     * berdasarkan FED yang bisa diakses user.
     */
    public function index(Request $request)
    {
        $academicId = $this->activeAcademicId();
        abort_unless($academicId, 403, 'Tahun akademik aktif belum diset.');

        $user = auth()->user();
        abort_unless($user, 403, 'User belum login.');

        $myUserId = (string) $user->id;
        $myName   = trim(mb_strtolower((string) ($user->name ?? '')));

        $q = trim((string) $request->query('q', ''));

        // 1) Ambil FED tahun aktif yang disetujui dan user ini punya akses
        $feds = SelfEvaluationForm::with(['categoryDetail', 'status', 'academicConfig'])
            ->where('academic_config_id', $academicId)
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
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('categoryDetail', fn($x) => $x->where('name', 'like', "%{$q}%"));
            })
            ->orderBy('category_detail_id')
            ->get();

        // 2) Cari finding form dari FED tsb
        $findingFormIds = AuditFindingForm::whereIn('self_evaluation_form_id', $feds->pluck('id'))
            ->where('active', 1)
            ->pluck('id')
            ->toArray();

        // 3) Ambil ATL yang sudah dibuat dari finding form
        $atls = AuditFollowUpForm::with([
                'findingForm.selfEvaluationForm.categoryDetail',
                'findingForm.selfEvaluationForm.academicConfig',
            ])
            ->whereIn('audit_finding_form_id', $findingFormIds)
            ->where('active', 1)
            ->latest('updated_at')
            ->get();

        return view('auditee.atl.index', compact('atls', 'q'));
    }

    /**
     * Show ATL auditee: auditee isi realisasi + efektivitas.
     */
    public function show(AuditFollowUpForm $atl)
    {
        $fed = $this->ensureAuditeeCanAccessAtl($atl);

        $unitName = $fed->categoryDetail?->name ?? $atl->area ?? 'Unit/Prodi';
        $academicText = $fed->academicConfig?->name ?? $fed->academicConfig?->tahun ?? null;

        $details = AuditFollowUpDetail::with([
                'finding.selfEvaluationDetail.standardAchievement',
                'finding.selfEvaluationDetail.indicator.standard',
                'finding.selfEvaluationDetail.indicator.pics.role',
            ])
            ->where('audit_follow_up_form_id', $atl->id)
            ->where('active', 1)
            ->orderBy('id')
            ->get();

        $total = $details->count();
        $complete = $details->filter(function ($d) {
            return $this->normalize($d->follow_up_realization) !== ''
                && $this->normalize($d->effectiveness) !== '';
        })->count();

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

    /**
     * Auditee update row: hanya follow_up_realization + effectiveness.
     * Status & status_description milik auditor.
     */
    public function updateRow(Request $request, AuditFollowUpForm $atl, AuditFollowUpDetail $detail)
    {
        $this->ensureAuditeeCanAccessAtl($atl);

        if ((string)$detail->audit_follow_up_form_id !== (string)$atl->id) {
            abort(404);
        }

        if (($atl->status ?? '') === self::ATL_STATUS_FINAL) {
            return back()->with('error', 'ATL sudah Final. Tidak bisa diubah.');
        }

        $data = $request->validate([
            'follow_up_realization' => ['nullable', 'string'],
            'effectiveness' => ['nullable', 'string', 'max:255'],
        ]);

        $real = isset($data['follow_up_realization']) ? trim((string)$data['follow_up_realization']) : null;
        $eff  = isset($data['effectiveness']) ? trim((string)$data['effectiveness']) : null;

        $detail->follow_up_realization = ($real === '') ? null : $data['follow_up_realization'];
        $detail->effectiveness = ($eff === '') ? null : $eff;

        if (property_exists($detail, 'updated_by')) {
            $detail->updated_by = auth()->id();
        }

        $detail->save();

        return back()->with('success', 'Realisasi & Efektivitas berhasil disimpan.');
    }
}
