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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditFollowUpHeaderController extends Controller
{
    private const FORM_STATUS_DRAFT = 'Draft';
    private const FORM_STATUS_FINAL = 'Final';

    /* ================= Helper Umum ================= */

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

    private function normalize(?string $v): string
    {
        return trim((string) ($v ?? ''));
    }

    /* ================= Akses (AUDITOR + AUDITEE) ================= */

    /**
     * Ambil semua user_role_id auditee dari FED.
     * Fallback: created_by/updated_by biar gak blank.
     */
    private function auditeeRoleIds(SelfEvaluationForm $fed): array
    {
        $candidates = [];

        foreach ([
            'head_auditee_user_role_id',
            'member_auditee_1_user_role_id',
            'member_auditee_2_user_role_id',
            'member_auditee_3_user_role_id',
        ] as $col) {
            if (!empty($fed->{$col})) $candidates[] = (string) $fed->{$col};
        }

        // fallback common variants
        foreach ([
            'head_auditee_role_id',
            'member_auditee_1_role_id',
            'member_auditee_2_role_id',
            'member_auditee_3_role_id',
        ] as $col) {
            if (!empty($fed->{$col})) $candidates[] = (string) $fed->{$col};
        }

        foreach (['created_by', 'updated_by'] as $col) {
            if (!empty($fed->{$col})) $candidates[] = (string) $fed->{$col};
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Akses ATL:
     * - admin
     * - auditor ketua / anggota (dari finding form)
     * - auditee head / member 1-3 (dari FED)
     */
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

    /**
     * Hanya ketua auditor (atau admin) boleh Final / ubah assignment.
     */
    private function ensureLeaderOrAdmin(AuditFindingForm $findingForm): void
    {
        if ($this->isAdmin()) return;

        $myRoleId = $this->currentUserRoleId();
        abort_unless($myRoleId, 403, 'User role tidak ditemukan.');

        abort_unless(
            $findingForm->auditor_user_role_id === $myRoleId,
            403,
            'Hanya Ketua Auditor yang boleh melakukan aksi ini.'
        );
    }

    /* ================= Data Builder ================= */

    /**
     * ATL detail seharusnya hanya untuk TEMUAN NEGATIF.
     * Kamu sebelumnya pakai "severity != null" sebagai indikasi negatif.
     */
    private function negativeFindings(AuditFindingForm $findingForm)
    {
        return AuditFinding::with([
                'selfEvaluationDetail.standardAchievement',
                'selfEvaluationDetail.indicator.standard',
            ])
            ->where('audit_finding_form_id', $findingForm->id)
            ->where('active', 1)
            ->whereNotNull('severity')
            ->orderBy('finding_no')
            ->get();
    }

    /**
     * Sinkron detail ATL:
     * - Pastikan setiap temuan negatif punya 1 baris detail aktif.
     * - Tidak bikin duplikat.
     */
    private function syncFollowUpDetails(AuditFollowUpForm $form, $negativeFindings): void
    {
        $findingIds = $negativeFindings->pluck('id')->filter()->values();
        if ($findingIds->isEmpty()) return;

        $existing = AuditFollowUpDetail::where('audit_follow_up_form_id', $form->id)
            ->where('active', 1)
            ->pluck('audit_finding_id');

        $missing = $findingIds->diff($existing);
        if ($missing->isEmpty()) return;

        foreach ($missing as $fid) {
            AuditFollowUpDetail::create([
                'id' => AuditFollowUpDetail::generateNextId(),
                'audit_follow_up_form_id' => $form->id,
                'audit_finding_id' => $fid,
                'active' => 1,
            ]);
        }
    }

    private function isRowComplete(AuditFollowUpDetail $d): bool
    {
        // wajib: 3 kolom inti
        return $this->normalize($d->follow_up_realization) !== ''
            && $this->normalize($d->effectiveness) !== ''
            && $this->normalize($d->status) !== '';
        // status_description sengaja tidak diwajibkan (permintaan "tetap memilih", deskripsi optional)
    }

    /* ================= Index ================= */

    public function index(Request $request)
    {
        $academicId = $this->activeAcademicId();
        abort_unless($academicId, 403, 'Tahun akademik aktif belum diset.');

        $q = trim((string) $request->query('q', ''));

        // list finding form FINAL (sama seperti blade kamu)
        $forms = AuditFindingForm::with(['selfEvaluationForm.categoryDetail', 'selfEvaluationForm.academicConfig'])
            ->where('active', 1)
            ->where('status', self::FORM_STATUS_FINAL)
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('selfEvaluationForm.categoryDetail', fn($x) => $x->where('name', 'like', "%{$q}%"))
                   ->orWhere('area', 'like', "%{$q}%");
            })
            ->orderBy('audit_date', 'desc')
            ->get();

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

        // build header ATL
        DB::transaction(function () use ($findingForm, &$atl) {
            $atl = AuditFollowUpForm::where('audit_finding_form_id', $findingForm->id)
                ->where('active', 1)
                ->first();

            if (!$atl) {
                $atl = AuditFollowUpForm::create([
                    'id' => AuditFollowUpForm::generateNextId(),
                    'audit_finding_form_id' => $findingForm->id,
                    'area' => $findingForm->area,
                    'audit_date' => now()->toDateString(),
                    'status' => self::FORM_STATUS_DRAFT,
                    'active' => 1,
                    'auditor_user_role_id' => $findingForm->auditor_user_role_id,
                    'member_auditor_user_role_id' => $findingForm->member_auditor_user_role_id,
                ]);
            }
        });

        $negativeFindings = $this->negativeFindings($findingForm);

        // sync detail rows (kalau ATL belum final, biar aman)
        if (($atl->status ?? '') !== self::FORM_STATUS_FINAL) {
            DB::transaction(function () use ($atl, $negativeFindings) {
                $this->syncFollowUpDetails($atl, $negativeFindings);
            });
        }

        // ambil detail untuk tabel
        $details = AuditFollowUpDetail::with([
                'finding.selfEvaluationDetail.standardAchievement',
                'finding.selfEvaluationDetail.indicator.standard',
            ])
            ->where('audit_follow_up_form_id', $atl->id)
            ->where('active', 1)
            ->orderBy('id')
            ->get();

        // progress
        $total = $details->count();
        $complete = $details->filter(fn($d) => $this->isRowComplete($d))->count();
        $progress = [
            'total' => $total,
            'complete' => $complete,
            'percent' => $total ? round(100 * $complete / $total, 1) : 0.0,
        ];

        // buat header view (sesuai blade kamu yang pakai unitName/academicText)
        $unitName = optional($fed->categoryDetail)->name ?? ($findingForm->area ?? 'Unit/Prodi');
        $academicText = optional($fed->academicConfig)->name ?? optional($fed->academicConfig)->tahun ?? null;

        // options untuk badge severity (kalau blade butuh)
        $severityOptions = AuditFinding::SEVERITY_OPTIONS;

        // daftar user role auditor (buat modal header)
        $auditorUserRoles = UserRole::with(['user', 'role'])
            ->where('active', 1)
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        return view('auditor.atl.show', compact(
            'findingForm',
            'fed',
            'atl',
            'negativeFindings',
            'details',
            'progress',
            'unitName',
            'academicText',
            'severityOptions',
            'auditorUserRoles'
        ));
    }

    /* ================= Update Header ================= */

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

            // HANYA anggota auditor yang bisa dipilih
            'member_auditor_user_role_id' => ['nullable', 'exists:user_roles,id', 'different:auditor_user_role_id'],
        ]);

        $form->update([
            'area' => $data['area'] ?? $form->area,
            'audit_date' => $data['audit_date'] ?? $form->audit_date,

            // ketua tetap: jangan disentuh
            'member_auditor_user_role_id' => $data['member_auditor_user_role_id'] ?? null,
        ]);

        return back()->with('success', 'Header & anggota auditor berhasil diperbarui.');
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
            ->get();

        if ($details->isEmpty()) {
            throw ValidationException::withMessages([
                'form' => 'Tidak ada baris ATL. Pastikan ada temuan negatif untuk dibuatkan ATL.',
            ]);
        }

        $incomplete = $details->filter(fn($d) => !$this->isRowComplete($d))->count();

        if ($incomplete > 0) {
            throw ValidationException::withMessages([
                'form' => "Masih ada {$incomplete} baris ATL belum lengkap (Realisasi, Efektivitas, Status).",
            ]);
        }

        $form->update(['status' => self::FORM_STATUS_FINAL]);

        return back()->with('success', 'Form Audit Tindak Lanjut berhasil Final (terkunci).');
    }

    public function searchAuditors(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

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
                'id' => $ur->id, // ✅ user_roles.id
                'name' => optional($ur->user)->name ?? ('User#'.$ur->id),
                'role_name' => optional($ur->role)->name ?? 'Anggota',
            ])->values()
        );
    }

}
