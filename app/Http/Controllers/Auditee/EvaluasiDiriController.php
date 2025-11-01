<?php

namespace App\Http\Controllers\Auditee;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AmiStandardIndicator;
use App\Models\AmiStandardIndicatorPic;
use App\Models\EvaluasiDiri;
use App\Models\EvaluasiDiriDetail;
use App\Models\KetercapaianStandard;
use App\Models\StatusEvaluasi;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// PhpWord: kita pakai TemplateProcessor untuk nge-populate DOCX template
use PhpOffice\PhpWord\TemplateProcessor;

class EvaluasiDiriController extends Controller
{
    // ==========================================
    // KONFIG: path template DOCX
    // ==========================================
    private const TEMPLATE_PATH = 'templates/F-219_Formulir_Evaluasi_Diri_Auditee.docx';
    // Contoh lokasi file: storage/app/templates/F-219_Formulir_Evaluasi_Diri_Auditee.docx

    /* ================= Helpers ================= */

    private function currentUserRole(): ?UserRole
    {
        $u = auth()->user();
        if (!$u) return null;

        if (method_exists($u, 'userRole') && $u->userRole) return $u->userRole;

        if (!empty($u->user_role_id)) {
            if ($ur = UserRole::find($u->user_role_id)) return $ur;
        }

        if (!empty($u->cis_user_id)) {
            $ur = UserRole::where('cis_user_id', $u->cis_user_id)->where('active', 1)->first()
                ?? UserRole::where('cis_user_id', $u->cis_user_id)->latest('created_at')->first();
            if ($ur) return $ur;
        }

        return UserRole::where('id', $u->id)->where('active', 1)->first()
            ?? UserRole::where('id', $u->id)->latest('created_at')->first();
    }

    private function resolveCategoryDetailId(): ?string
    {
        return optional($this->currentUserRole())->category_detail_id;
    }

    private function resolveRoleId(): ?string
    {
        return optional($this->currentUserRole())->role_id;
    }

    private function activeAcademic(): ?AcademicConfig
    {
        return AcademicConfig::where('active', true)->first();
    }

    private function activeAcademicId(): ?string
    {
        return optional($this->activeAcademic())->id;
    }

    private function ensureFormOwnedByUser(EvaluasiDiri $form): void
    {
        $userCat = $this->resolveCategoryDetailId();
        abort_unless($userCat && $form->category_detail_id === $userCat, 403, 'Tidak berhak mengakses form ini.');
    }

    private function ensureFormOnActiveYear(EvaluasiDiri $form): void
    {
        $activeId = $this->activeAcademicId();
        abort_unless($activeId && $form->academic_config_id === $activeId, 403, 'Form ini bukan untuk tahun akademik aktif.');
    }

    /**
     * Sinkron butir berdasarkan PIC role yang login.
     * Hanya indikator & standar aktif di TA aktif yang dimasukkan.
     */
    private function syncDetailsWithPIC(EvaluasiDiri $form, string $currentRoleId): void
    {
        $academicId = $form->academic_config_id;

        $picIndicatorIds = AmiStandardIndicatorPic::where('role_id', $currentRoleId)
            ->where('active', 1)
            ->pluck('standard_indicator_id')
            ->unique();

        if ($picIndicatorIds->isEmpty()) return;

        $eligibleIndicatorIds = AmiStandardIndicator::query()
            ->whereIn('ami_standard_indicators.id', $picIndicatorIds)
            ->where('ami_standard_indicators.active', 1)
            ->whereExists(function ($q) use ($academicId) {
                $q->select(DB::raw(1))
                    ->from('ami_standards as s')
                    ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                    ->where('s.active', 1)
                    ->where('s.academic_config_id', $academicId);
            })
            ->orderBy('ami_standard_indicators.id')
            ->pluck('id');

        if ($eligibleIndicatorIds->isEmpty()) return;

        $existing = EvaluasiDiriDetail::where('form_evaluasi_diri_id', $form->id)
            ->pluck('ami_standard_indicator_id');

        $missing = $eligibleIndicatorIds->diff($existing);
        if ($missing->isEmpty()) return;

        $statusDraftId = StatusEvaluasi::where('name', 'Draft')->value('id');

        foreach ($missing as $indId) {
            EvaluasiDiriDetail::create([
                'id'                        => EvaluasiDiriDetail::generateNextId(),
                'form_evaluasi_diri_id'     => $form->id,
                'ami_standard_indicator_id' => $indId,
                'status_id'                 => $statusDraftId,
                'active'                    => true,
            ]);
        }
    }

    /* ================= Screen ================= */

    public function index(Request $request)
    {
        $academic         = $this->activeAcademic();
        $ur               = $this->currentUserRole();
        $categoryDetailId = $ur?->category_detail_id;
        $currentRoleId    = $ur?->role_id;

        $categoryDetailName = null;
        if ($categoryDetailId) {
            $categoryDetailName = DB::table('ref_category_details')
                ->where('id', $categoryDetailId)
                ->value('name');
        }

        $form = null;
        $details = collect();
        $progress = ['total' => 0, 'terisi' => 0, 'percent' => 0.0];

        if (!$academic)         session()->flash('warning', 'Tahun akademik aktif belum diset oleh admin.');
        if (!$categoryDetailId) session()->flash('warning', 'Akun Anda belum terhubung ke Unit/Prodi. Hubungi admin.');

        if ($academic && $categoryDetailId) {
            $form = EvaluasiDiri::with(['status', 'categoryDetail'])
                ->where('academic_config_id', $academic->id)
                ->where('category_detail_id', $categoryDetailId)
                ->first();

            if ($form && $currentRoleId) {
                DB::transaction(fn() => $this->syncDetailsWithPIC($form, $currentRoleId));

                $details = EvaluasiDiriDetail::with(['AmiStandardIndicator', 'KetercapaianStandard', 'updatedBy'])
                    ->where('form_evaluasi_diri_id', $form->id)
                    ->whereHas('AmiStandardIndicator', function ($q) use ($academic, $currentRoleId) {
                        $q->where('ami_standard_indicators.active', 1)
                            ->whereExists(function ($qq) use ($academic) {
                                $qq->select(DB::raw(1))
                                    ->from('ami_standards as s')
                                    ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                                    ->where('s.active', 1)
                                    ->where('s.academic_config_id', $academic->id);
                            })
                            ->whereExists(function ($qp) use ($currentRoleId) {
                                $qp->select(DB::raw(1))
                                    ->from('ami_standard_indicator_pic as p')
                                    ->whereColumn('p.standard_indicator_id', 'ami_standard_indicators.id')
                                    ->where('p.active', 1)
                                    ->where('p.role_id', $currentRoleId);
                            });
                    })
                    ->orderBy('ami_standard_indicator_id')
                    ->get();

                $total  = $details->count();
                $terisi = $details->filter(fn($d) =>
                    !is_null($d->ketercapaian_standard_id) || (isset($d->hasil) && trim($d->hasil) !== '')
                )->count();

                $progress = [
                    'total'   => $total,
                    'terisi'  => $terisi,
                    'percent' => $total ? round(100 * $terisi / $total, 1) : 0.0,
                ];
            }
        }

        $opsiKetercapaian = KetercapaianStandard::where('active', 1)->get();

        return view('auditee.fed.index', compact(
            'academic', 'categoryDetailId', 'categoryDetailName', 'form', 'details', 'progress', 'opsiKetercapaian'
        ));
    }

    /* ================= Actions ================= */

    public function store(Request $request)
    {
        $academicId       = $this->activeAcademicId();
        $ur               = $this->currentUserRole();
        $categoryDetailId = $ur?->category_detail_id;
        $currentRoleId    = $ur?->role_id;

        if (!$academicId || !$categoryDetailId) {
            return redirect()->route('auditee.fed.index')->with(
                'warning',
                !$academicId ? 'Tahun akademik aktif belum diset oleh admin.' : 'Akun belum terhubung ke Unit/Prodi.'
            );
        }

        if (EvaluasiDiri::where('academic_config_id', $academicId)->where('category_detail_id', $categoryDetailId)->exists()) {
            return redirect()->route('auditee.fed.index')->with('info', 'Form sudah ada. Lanjutkan pengisian.');
        }

        $data = $request->validate([
            'ketua_auditee_nama'           => ['nullable', 'string', 'max:255'],
            'ketua_auditee_jabatan'        => ['nullable', 'string', 'max:255'],
            'anggota_auditee_satu'         => ['nullable', 'string', 'max:255'],
            'anggota_auditee_jabatan_satu' => ['nullable', 'string', 'max:255'],
            'anggota_auditee_dua'          => ['nullable', 'string', 'max:255'],
            'anggota_auditee_jabatan_dua'  => ['nullable', 'string', 'max:255'],
            'anggota_auditee_tiga'         => ['nullable', 'string', 'max:255'],
            'anggota_auditee_jabatan_tiga' => ['nullable', 'string', 'max:255'],
        ]);

        DB::beginTransaction();
        try {
            $statusDraftId = StatusEvaluasi::where('name', 'Draft')->value('id');

            $form = new EvaluasiDiri();
            $form->id                 = EvaluasiDiri::generateNextId();
            $form->academic_config_id = $academicId;
            $form->category_detail_id = $categoryDetailId;
            $form->status_id          = $statusDraftId;
            $form->active             = true;
            $form->fill($data);
            $form->save();

            if ($currentRoleId) {
                $picIds = AmiStandardIndicator::query()
                    ->whereIn('ami_standard_indicators.id', function ($sub) use ($currentRoleId) {
                        $sub->select('standard_indicator_id')
                            ->from('ami_standard_indicator_pic')
                            ->where('active', 1)
                            ->where('role_id', $currentRoleId);
                    })
                    ->where('ami_standard_indicators.active', 1)
                    ->whereExists(function ($q) use ($academicId) {
                        $q->select(DB::raw(1))
                            ->from('ami_standards as s')
                            ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                            ->where('s.active', 1)
                            ->where('s.academic_config_id', $academicId);
                    })
                    ->orderBy('ami_standard_indicators.id')
                    ->pluck('id');

                foreach ($picIds as $indId) {
                    EvaluasiDiriDetail::create([
                        'id'                        => EvaluasiDiriDetail::generateNextId(),
                        'form_evaluasi_diri_id'     => $form->id,
                        'ami_standard_indicator_id' => $indId,
                        'status_id'                 => $statusDraftId,
                        'active'                    => true,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('auditee.fed.index')->with('success', 'Form Evaluasi Diri berhasil dibuat.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('auditee.fed.index')->with('error', 'Gagal membuat form: ' . $e->getMessage());
        }
    }

    public function updateHeader(Request $request, EvaluasiDiri $form)
    {
        $this->ensureFormOwnedByUser($form);
        $this->ensureFormOnActiveYear($form);

        $submittedId = StatusEvaluasi::where('name', 'Dikirim')->value('id');
        if ($form->status_id === $submittedId) {
            return back()->with('warning', 'Form sudah dikirim dan tidak dapat diubah.');
        }

        $data = $request->validate([
            'ketua_auditee_nama'           => ['nullable', 'string', 'max:255'],
            'ketua_auditee_jabatan'        => ['nullable', 'string', 'max:255'],
            'anggota_auditee_satu'         => ['nullable', 'string', 'max:255'],
            'anggota_auditee_jabatan_satu' => ['nullable', 'string', 'max:255'],
            'anggota_auditee_dua'          => ['nullable', 'string', 'max:255'],
            'anggota_auditee_jabatan_dua'  => ['nullable', 'string', 'max:255'],
            'anggota_auditee_tiga'         => ['nullable', 'string', 'max:255'],
            'anggota_auditee_jabatan_tiga' => ['nullable', 'string', 'max:255'],
        ]);

        $form->update($data);
        return redirect()->route('auditee.fed.index')->with('success', 'Data auditee diperbarui.');
    }

    public function updateDetail(Request $request, EvaluasiDiri $form, EvaluasiDiriDetail $detail)
    {
        $this->ensureFormOwnedByUser($form);
        $this->ensureFormOnActiveYear($form);
        if ($detail->form_evaluasi_diri_id !== $form->id) abort(404);

        $ur = $this->currentUserRole();
        $currentRoleId = $ur?->role_id;

        $isPic = AmiStandardIndicatorPic::where('standard_indicator_id', $detail->ami_standard_indicator_id)
            ->where('role_id', $currentRoleId)
            ->where('active', 1)
            ->exists();
        abort_unless($isPic, 403, 'Anda bukan PIC indikator ini.');

        $submittedId = StatusEvaluasi::where('name', 'Dikirim')->value('id');
        if ($form->status_id === $submittedId) {
            return back()->with('warning', 'Form sudah dikirim dan tidak dapat diedit.');
        }

        $data = $request->validate([
            'ketercapaian_standard_id'    => ['nullable', 'string', 'max:255'],
            'hasil'                       => ['nullable', 'string'],
            'bukti_pendukung'             => ['nullable', 'string'],
            'faktor_penghambat_pendukung' => ['nullable', 'string'],
        ]);

        $draftId = StatusEvaluasi::where('name', 'Draft')->value('id');

        $detail->update(array_merge($data, [
            'status_id'  => $draftId,
            'updated_by' => optional($ur)->id,
        ]));

        return redirect()->route('auditee.fed.index')->with('success', 'Butir tersimpan.');
    }

    public function submit(Request $request, EvaluasiDiri $form)
    {
        $this->ensureFormOwnedByUser($form);
        $this->ensureFormOnActiveYear($form);

        $submittedId = StatusEvaluasi::where('name', 'Dikirim')->value('id');
        if ($form->status_id === $submittedId) {
            return back()->with('info', 'Form sudah dikirim.');
        }

        $ur = $this->currentUserRole();
        $currentRoleId = $ur?->role_id;

        $details = EvaluasiDiriDetail::where('form_evaluasi_diri_id', $form->id)
            ->whereHas('AmiStandardIndicator', function ($q) use ($form, $currentRoleId) {
                $q->where('ami_standard_indicators.active', 1)
                    ->whereExists(function ($qq) use ($form) {
                        $qq->select(DB::raw(1))
                            ->from('ami_standards as s')
                            ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                            ->where('s.active', 1)
                            ->where('s.academic_config_id', $form->academic_config_id);
                    })
                    ->whereExists(function ($qp) use ($currentRoleId) {
                        $qp->select(DB::raw(1))
                            ->from('ami_standard_indicator_pic as p')
                            ->whereColumn('p.standard_indicator_id', 'ami_standard_indicators.id')
                            ->where('p.active', 1)
                            ->where('p.role_id', $currentRoleId);
                    });
            })
            ->get(['ketercapaian_standard_id', 'hasil']);

        $incomplete = $details->filter(fn($d) =>
            is_null($d->ketercapaian_standard_id) && (!isset($d->hasil) || trim($d->hasil) === '')
        )->count();

        if ($incomplete > 0) {
            throw ValidationException::withMessages([
                'form' => "Masih ada {$incomplete} indikator yang belum diisi untuk role Anda.",
            ]);
        }

        $form->update([
            'status_id'      => $submittedId,
            'tanggal_submit' => now()->toDateString(),
        ]);

        return redirect()->route('auditee.fed.index')->with('success', 'Form berhasil dikirim. Terima kasih!');
    }

    /**
     * Export Word menggunakan TemplateProcessor.
     * Template harus punya placeholder:
     * - Header: categoryDetail, ta, ketua, anggota1, anggota2, anggota3, tanggal
     * - Row: no, standar, melampaui, mencapai, tidak_mencapai, menyimpang, hasil, bukti, faktor
     */
    public function exportDoc(Request $request, EvaluasiDiri $form): BinaryFileResponse
    {
        $this->ensureFormOwnedByUser($form);
    $this->ensureFormOnActiveYear($form);

    $submittedId = StatusEvaluasi::where('name','Dikirim')->value('id');
    abort_unless($form->status_id === $submittedId, 403, 'Dokumen hanya tersedia setelah form dikirim.');

    if (!class_exists(\ZipArchive::class)) {
        abort(500, 'PHP Zip extension belum aktif.');
    }

    // ===== PENTING: batasi ke indikator milik role yang login =====
    $ur            = $this->currentUserRole();
    $currentRoleId = $ur?->role_id;

    $details = EvaluasiDiriDetail::with(['AmiStandardIndicator.standard','KetercapaianStandard'])
        ->where('form_evaluasi_diri_id', $form->id)
        ->whereHas('AmiStandardIndicator', function ($q) use ($form, $currentRoleId) {
            $q->where('ami_standard_indicators.active', 1)
              // standar masih aktif dan di TA form
              ->whereExists(function ($qq) use ($form) {
                  $qq->select(DB::raw(1))
                     ->from('ami_standards as s')
                     ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                     ->where('s.active', 1)
                     ->where('s.academic_config_id', $form->academic_config_id);
              })
              // hanya indikator yg PIC-nya = role yg login
              ->whereExists(function ($qp) use ($currentRoleId) {
                  $qp->select(DB::raw(1))
                     ->from('ami_standard_indicator_pic as p')
                     ->whereColumn('p.standard_indicator_id', 'ami_standard_indicators.id')
                     ->where('p.active', 1)
                     ->where('p.role_id', $currentRoleId);
              });
        })
        ->orderBy('ami_standard_indicator_id')
        ->get();

        // 2) Siapkan template
        $templateAbsPath = storage_path('app/' . self::TEMPLATE_PATH);
        if (!is_file($templateAbsPath)) {
            abort(500, 'Template DOCX tidak ditemukan: ' . self::TEMPLATE_PATH);
        }

        $tp = new TemplateProcessor($templateAbsPath);

        // 3) Set header values
        $taName   = optional($form->academicConfig)->name ?? '-';
        $unitName = optional($form->categoryDetail)->name ?? '-';

        $ketua = trim(($form->ketua_auditee_nama ?? '') . ' / ' . ($form->ketua_auditee_jabatan ?? ''), ' /');
        $angg1 = trim(($form->anggota_auditee_satu ?? '') . ' / ' . ($form->anggota_auditee_jabatan_satu ?? ''), ' /');
        $angg2 = trim(($form->anggota_auditee_dua ?? '') . ' / ' . ($form->anggota_auditee_jabatan_dua ?? ''), ' /');
        $angg3 = trim(($form->anggota_auditee_tiga ?? '') . ' / ' . ($form->anggota_auditee_jabatan_tiga ?? ''), ' /');

        $tp->setValue('categoryDetail', $unitName);
        $tp->setValue('ta', $taName);
        $tp->setValue('ketua', $ketua ?: '-');
        $tp->setValue('anggota1', $angg1 ?: '-');
        $tp->setValue('anggota2', $angg2 ?: '-');
        $tp->setValue('anggota3', $angg3 ?: '-');
        $tp->setValue('tanggal', now()->format('d/m/Y'));

        // 4) Siapkan data baris untuk cloneRow
        $rows = [];
        foreach ($details as $i => $d) {
            $stdName   = optional($d->AmiStandardIndicator?->standard)->name ?? '-';
            $descPlain = trim(strip_tags($d->AmiStandardIndicator->description ?? ''));
            $judul     = $stdName . ($descPlain ? ': ' . $descPlain : '');

            $flag = strtolower(optional($d->KetercapaianStandard)->name ?? '');

            $rows[] = [
                'no'              => (string)($i + 1),
                'standar'         => $judul,
                'hasil'           => trim((string)$d->hasil) ?: '—',
                'bukti'           => trim((string)$d->bukti_pendukung) ?: '—',
                'faktor'          => trim((string)$d->faktor_penghambat_pendukung) ?: '—',
                'melampaui'       => $flag === 'melampaui' ? '✓ Melampaui' : '',
                'mencapai'        => $flag === 'mencapai' ? '✓ Mencapai' : '',
                'tidak_mencapai'  => $flag === 'tidak mencapai' ? '✓ Tidak Mencapai' : '',
                'menyimpang'      => $flag === 'menyimpang' ? '✓ Menyimpang' : '',
            ];
        }

        if (empty($rows)) {
            // tetap clone minimal 1 baris biar gak meledak
            $rows[] = [
                'no' => '1', 'standar' => '-', 'hasil' => '-', 'bukti' => '-', 'faktor' => '-',
                'melampaui' => '', 'mencapai' => '', 'tidak_mencapai' => '', 'menyimpang' => '',
            ];
        }

        // 5) Clone row dan set values
        $tp->cloneRowAndSetValues('no', $rows);

        // 6) Simpan ke tmp dan download
        $safe = function (string $name): string {
            $name = preg_replace('/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]+/', '-', $name);
            $name = trim(preg_replace('/\\s+/', ' ', $name));
            return Str::limit($name, 120, '');
        };

        $safeTA   = $safe($taName);
        $safeUnit = $safe($unitName);
        $filename = "FED_{$safeUnit}_TA_{$safeTA}.docx";

        // simpan ke storage/app/tmp atau sys temp
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);
        $target = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        // hapus file lama kalau ada, biar gak “cannot open for writing”
        if (file_exists($target)) @unlink($target);

        $tp->saveAs($target);

        return response()->download($target, $filename)->deleteFileAfterSend(true);
    }
}
