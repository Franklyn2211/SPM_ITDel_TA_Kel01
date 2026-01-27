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
use ConvertApi\ConvertApi;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditFollowUpExportController extends Controller
{
    private const TEMPLATE_PATH_F221 = 'templates/F-221_Formulir_Audit_Tindak_Lanjut.docx';
    private const FORM_STATUS_FINAL  = 'Final';

    /** cache kecil biar gak query berulang */
    private array $findingFormCache = []; // [finding_form_id => AuditFindingForm]

    /* ================= Access Helpers ================= */

    private function currentUserRole(): ?UserRole
    {
        $u = auth()->user();
        if (!$u) return null;

        if (method_exists($u, 'userRole') && $u->userRole) return $u->userRole;

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

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return trim($name);
    }

    private function ensureUserCanAccessFindingForm(AuditFindingForm $findingForm): void
    {
        if ($this->isAdmin()) return;

        // Auditor
        $myRoleId = $this->currentUserRoleId();
        if ($myRoleId) {
            if (in_array($myRoleId, [$findingForm->auditor_user_role_id, $findingForm->member_auditor_user_role_id], true)) {
                return;
            }
        }

        // Auditee (fallback lama)
        $user = auth()->user();
        abort_unless($user, 403, 'User belum login.');

        $fed = SelfEvaluationForm::findOrFail($findingForm->self_evaluation_form_id);

        if (!empty($fed->head_auditee_user_id) && (string) $fed->head_auditee_user_id === (string) $user->id) return;

        $myName = $this->normalizeName((string) ($user->name ?? ''));
        $headName = $this->normalizeName((string) ($fed->head_auditee_name ?? ''));
        if ($headName !== '' && $myName !== '' && $myName === $headName) return;

        $myUserId = (string) $user->id;
        $memberIds = array_filter([
            $fed->member_auditee_1_user_id ?? null,
            $fed->member_auditee_2_user_id ?? null,
            $fed->member_auditee_3_user_id ?? null,
        ], fn($v) => !is_null($v) && (string) $v !== '');
        $memberIds = array_map('strval', $memberIds);

        abort_unless(in_array($myUserId, $memberIds, true), 403, 'Tidak berhak mengakses dokumen audit tindak lanjut.');
    }

    /* ================= Formatting Helpers ================= */

    private function cleanText(?string $value, string $fallback = ''): string
    {
        $text = $value ?? '';
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[^\P{C}\n]+/u', '', $text);
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        return $text === '' ? $fallback : $text;
    }

    private function parseHtmlToTextRun(TextRun $run, ?string $html): void
    {
        $html = $html ?? '';
        if (trim($html) === '') return;

        $dom = new \DOMDocument();
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML("<div>{$html}</div>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $container = $dom->getElementsByTagName('div')->item(0);
        if (!$container) {
            $run->addText(strip_tags($html));
            return;
        }

        $traverse = function ($node, $style = [], $listContext = []) use (&$traverse, $run) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = $node->textContent;
                if ($text !== '') $run->addText($text, $style);
                return;
            }
            if ($node->nodeType !== XML_ELEMENT_NODE) return;

            $tag = strtolower($node->nodeName);

            if ($tag === 'b' || $tag === 'strong') $style['bold'] = true;
            elseif ($tag === 'i' || $tag === 'em') $style['italic'] = true;
            elseif ($tag === 'u') $style['underline'] = 'single';

            if ($tag === 'p' || $tag === 'div') {
                if ($node->previousSibling) $run->addTextBreak();
            }

            if ($tag === 'br') {
                $run->addTextBreak();
                return;
            }

            if ($tag === 'ul' || $tag === 'ol') {
                $idx = 1;
                $depth = ($listContext['depth'] ?? 0) + 1;
                $type = $tag === 'ol' ? ($node->getAttribute('type') ?: '1') : 'ul';

                foreach ($node->childNodes as $child) {
                    if (strtolower($child->nodeName) === 'li') {
                        $traverse($child, $style, ['depth' => $depth, 'type' => $type, 'index' => $idx++]);
                    }
                }
                return;
            }

            if ($tag === 'li') {
                $depth = $listContext['depth'] ?? 1;
                $idx = $listContext['index'] ?? 1;
                $lType = $listContext['type'] ?? 'ul';

                $marker = $lType !== 'ul' ? "{$idx}. " : '• ';

                $run->addTextBreak();
                $nbsp = "\xC2\xA0";
                $indentStr = str_repeat($nbsp . $nbsp . $nbsp, $depth);
                $run->addText($indentStr . $marker, $style);

                foreach ($node->childNodes as $child) {
                    $traverse($child, $style, $listContext);
                }
                return;
            }

            if ($tag === 'a') {
                $href = $node->getAttribute('href');
                $inner = $node->textContent;
                if ($href) {
                    $linkStyle = array_merge($style, ['color' => '0000FF', 'underline' => 'single']);
                    $run->addLink($href, $inner, $linkStyle);
                    return;
                }
            }

            foreach ($node->childNodes as $child) {
                $traverse($child, $style, $listContext);
            }
        };

        $traverse($container);
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        return Str::limit($name, 120, '');
    }

    /** "2526" -> 2025 */
    private function tahunKodeFromAcademic(?AcademicConfig $ac): string
    {
        $raw = trim((string) ($ac->academic_code ?? $ac->name ?? $ac->tahun ?? ''));
        if (preg_match('/\b(\d{2})\d{2}\b/', $raw, $m)) return '20' . $m[1];
        if (preg_match('/\b(\d{2})\s*\/\s*(\d{2})\b/', $raw, $m)) return '20' . $m[1];
        return (string) now()->year;
    }

    /** "2526" -> "2025-2026" */
    private function tahunAmiFromAcademic(?AcademicConfig $ac): string
    {
        $raw = trim((string) ($ac->academic_code ?? $ac->name ?? $ac->tahun ?? ''));

        if (preg_match('/\b(\d{2})(\d{2})\b/', $raw, $m)) {
            $y1 = 2000 + (int) $m[1];
            $y2 = 2000 + (int) $m[2];
            return "{$y1}-{$y2}";
        }
        if (preg_match('/\b(\d{2})\s*\/\s*(\d{2})\b/', $raw, $m)) {
            $y1 = 2000 + (int) $m[1];
            $y2 = 2000 + (int) $m[2];
            return "{$y1}-{$y2}";
        }

        $y = now()->year;
        return "{$y}-" . ($y + 1);
    }

    private function unitCodeFromFed(SelfEvaluationForm $fed, AuditFindingForm $findingForm): string
    {
        $code = strtoupper(trim((string) optional($fed->categoryDetail)->code));
        if ($code === '') $code = strtoupper(trim((string) ($findingForm->area ?? '')));
        return $code !== '' ? $code : 'UNIT';
    }

    private function userRoleName(?string $userRoleId): string
    {
        if (!$userRoleId) return '';
        $ur = UserRole::with('user')->find($userRoleId);
        if (!$ur) return '';
        $name = trim((string) optional($ur->user)->name);
        return $name !== '' ? $name : trim((string) ($ur->name ?? $ur->username ?? ''));
    }

    private function formatNomorTemuan(string $unitCode, string $acadShort, int $findingNo): string
    {
        $no = str_pad((string) $findingNo, 3, '0', STR_PAD_LEFT);
        return "AMI/{$unitCode}/{$acadShort}/{$no}";
    }

    /** "2526" -> "2526" (harus valid, gak boleh 0000 kecuali bener-bener gak ada data) */
    private function academicShort(?AcademicConfig $ac): string
    {
        $raw = trim((string) ($ac->academic_code ?? $ac->name ?? ''));
        if (preg_match('/\b(\d{4})\b/', $raw, $m)) return $m[1];
        if (preg_match('/(20\d{2})\s*\/\s*(20\d{2})/', $raw, $m)) {
            return substr($m[1], 2, 2) . substr($m[2], 2, 2);
        }
        return ''; // <-- PENTING: jangan balikin 0000, biar kita bisa fallback lebih pintar
    }

    /**
     * Ambil academic short dari ASAL TEMUAN.
     * Ini yang bener buat no_temuan carry-over, supaya bisa 2122, 2223, dst.
     */
    private function academicShortFromFinding(?AuditFinding $finding, ?AcademicConfig $fallbackAc): string
    {
        if (!$finding) return $this->academicShort($fallbackAc) ?: '0000';

        // 1) coba lewat relasi (kalau modelnya ada)
        try {
            $originAc = $finding?->auditFindingForm?->selfEvaluationForm?->academicConfig ?? null;
            $short = $this->academicShort($originAc);
            if ($short !== '') return $short;
        } catch (\Throwable $e) {
            // ignore, lanjut manual
        }

        // 2) manual lewat audit_finding_form_id
        $ffId = (string) ($finding->audit_finding_form_id ?? '');
        if ($ffId !== '') {
            if (!array_key_exists($ffId, $this->findingFormCache)) {
                $this->findingFormCache[$ffId] = AuditFindingForm::with('selfEvaluationForm.academicConfig')->find($ffId);
            }
            $ff = $this->findingFormCache[$ffId];
            $originAc2 = $ff?->selfEvaluationForm?->academicConfig;
            $short2 = $this->academicShort($originAc2);
            if ($short2 !== '') return $short2;
        }

        // 3) fallback terakhir: pakai tahun berjalan
        $short3 = $this->academicShort($fallbackAc);
        return $short3 !== '' ? $short3 : '0000';
    }

    private function statusToRun(?string $status, ?string $descHtml): TextRun
    {
        $run = new TextRun();
        $status = trim((string) ($status ?? ''));
        if ($status !== '') $run->addText($status, ['bold' => true]);

        $descHtml = (string) ($descHtml ?? '');
        if (trim($descHtml) !== '') {
            $run->addTextBreak();
            $this->parseHtmlToTextRun($run, $descHtml);
        }
        return $run;
    }

    /**
     * Ambil SEMUA ATL tahun-tahun lama (bukan cuma 1 tahun sebelumnya) yang statusnya Open/Toleran
     * untuk unit/prodi yang sama.
     */
    private function prevAtlDetailsOpenToleran(SelfEvaluationForm $currentFed): \Illuminate\Support\Collection
    {
        $currentAcId = (string) ($currentFed->academic_config_id ?? '');
        $catId = (string) ($currentFed->category_detail_id ?? '');
        if ($catId === '') return collect();

        $allPrevAcs = AcademicConfig::where('active', 0)
            ->when($currentAcId !== '', fn($q) => $q->where('id', '!=', $currentAcId))
            ->orderByDesc('created_at')
            ->get();

        if ($allPrevAcs->isEmpty()) return collect();

        $all = collect();

        foreach ($allPrevAcs as $prevAc) {
            $prevFed = SelfEvaluationForm::where('active', 1)
                ->where('academic_config_id', $prevAc->id)
                ->where('category_detail_id', $catId)
                ->latest('created_at')
                ->first();

            if (!$prevFed) continue;

            $prevFindingForm = AuditFindingForm::where('active', 1)
                ->where('self_evaluation_form_id', $prevFed->id)
                ->where('status', self::FORM_STATUS_FINAL)
                ->latest('audit_date')
                ->first();

            if (!$prevFindingForm) continue;

            $prevAtl = AuditFollowUpForm::where('active', 1)
                ->where('audit_finding_form_id', $prevFindingForm->id)
                ->where('status', self::FORM_STATUS_FINAL)
                ->latest('audit_date')
                ->first();

            if (!$prevAtl) continue;

            $rows = AuditFollowUpDetail::with([
                'finding.selfEvaluationDetail.indicator.standard',
                'finding.selfEvaluationDetail.indicator.pics.role',
                // penting untuk no temuan, kalau relasinya tersedia
                'finding.form.selfEvaluationForm.academicConfig',
            ])
                ->where('active', 1)
                ->where('audit_follow_up_form_id', $prevAtl->id)
                ->whereIn('status', ['Open', 'Toleran'])
                ->orderBy('id')
                ->get();

            if ($rows->isNotEmpty()) $all = $all->merge($rows);
        }

        // optional: rapihin urutan berdasarkan tahun akademik (desc) lalu id
        return $all->values();
    }

    private function picRoleNamesFromDetail($detail): string
    {
        $indicator = $detail?->finding?->selfEvaluationDetail?->indicator;
        if (!$indicator) return '-';

        $names = collect($indicator->pics ?? [])
            ->map(fn($p) => $p->role?->name ?? null)
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        return $names !== '' ? $names : '-';
    }

    private function fillPrevAtlToTemplateF221(
        TemplateProcessor $tp,
        SelfEvaluationForm $currentFed,
        string $unitCode,
        AcademicConfig $currentAc
    ): void {
        $prevDetails = $this->prevAtlDetailsOpenToleran($currentFed);

        $rows = [];
        foreach ($prevDetails as $idx => $d) {
            $i = $idx + 1;

            $finding  = $d->finding;
            $seDetail = $finding?->selfEvaluationDetail;

            $findingNo = (int) ($finding?->finding_no ?? $i);

            // ✅ FIX: short tahun ikut asal temuan (bisa 2122, 2223, dst)
            $acadShortPrev = $this->academicShortFromFinding($finding, $currentAc);
            $nomorTemuan   = $this->formatNomorTemuan($unitCode, $acadShortPrev, $findingNo);

            $plan = (string) ($finding?->corrective_action_plan ?? $finding?->follow_up_plan ?? '');
            $planText = $this->cleanText($plan, '-');
            $jadwal = !empty($finding?->due_date) ? \Carbon\Carbon::parse($finding->due_date)->format('d/m/Y') : '-';

            $rows[] = [
                'prev_no' => (string) $i,
                'prev_no_temuan' => $nomorTemuan,
                'prev_deskripsi' => $this->cleanText($seDetail?->result ?? '-', '-'),
                'prev_status' => $this->cleanText($d->status ?? '-', '-'),
                'prev_rencana' => $planText,
                'prev_penanggung_jawab' => $this->picRoleNamesFromDetail($d),
                'prev_jadwal' => $jadwal,
                'prev_realisasi' => $this->cleanText($d->follow_up_realization ?? '-', '-'),
                'prev_efektivitas' => $this->cleanText($d->effectiveness ?? '-', '-'),
                'prev_status_auditor' => $this->cleanText($d->status_description ?? '-', '-'),
            ];
        }

        if (count($rows) === 0) {
            $rows[] = [
                'prev_no' => '1',
                'prev_no_temuan' => '-',
                'prev_deskripsi' => '-',
                'prev_status' => '-',
                'prev_rencana' => '-',
                'prev_penanggung_jawab' => '-',
                'prev_jadwal' => '-',
                'prev_realisasi' => '-',
                'prev_efektivitas' => '-',
                'prev_status_auditor' => '-',
            ];
        }

        $tp->cloneRowAndSetValues('prev_no', $rows);
    }

    private function buildDocxF221(AuditFollowUpForm $form): array
    {
        $findingForm = AuditFindingForm::findOrFail($form->audit_finding_form_id);
        $this->ensureUserCanAccessFindingForm($findingForm);

        abort_unless($form->status === self::FORM_STATUS_FINAL, 403, 'Dokumen hanya tersedia setelah ATL Final.');
        abort_unless(class_exists(\ZipArchive::class), 500, 'PHP Zip extension belum aktif.');

        $fed = SelfEvaluationForm::with(['academicConfig', 'categoryDetail'])
            ->findOrFail($findingForm->self_evaluation_form_id);

        $templateAbsPath = storage_path('app/' . self::TEMPLATE_PATH_F221);
        abort_unless(is_file($templateAbsPath), 500, 'Template DOCX F-221 tidak ditemukan: ' . self::TEMPLATE_PATH_F221);

        $tp = new TemplateProcessor($templateAbsPath);

        $unitName  = optional($fed->categoryDetail)->name ?? ($findingForm->area ?? '');
        $unitCode  = $this->unitCodeFromFed($fed, $findingForm);

        // ===== tahun dinamis =====
        $tp->setValue('tahun_kode', $this->tahunKodeFromAcademic($fed->academicConfig)); // 2025
        $tp->setValue('tahun_ami', $this->tahunAmiFromAcademic($fed->academicConfig));   // 2025-2026

        // header
        $ketuaAuditee   = trim((string) ($fed->head_auditee_name ?? ''));
        $ketuaAuditor   = $this->userRoleName($form->auditor_user_role_id ?? $findingForm->auditor_user_role_id);
        $anggotaAuditor = $this->userRoleName($form->member_auditor_user_role_id ?? $findingForm->member_auditor_user_role_id);

        $tp->setValue('area', $unitName);
        $tp->setValue('auditee', $unitName);
        $tp->setValue('ketua_auditee', $ketuaAuditee);
        $tp->setValue('ketua_auditor', $ketuaAuditor);
        $tp->setValue('anggota_auditor', $anggotaAuditor);
        $tp->setValue('tanggal_audit', $form->audit_date ? \Carbon\Carbon::parse($form->audit_date)->format('d/m/Y') : '');

        // details ATL tahun berjalan
        $details = AuditFollowUpDetail::with([
            'finding.selfEvaluationDetail.standardAchievement',
            'finding.selfEvaluationDetail.indicator.standard',
            'finding.form.selfEvaluationForm.academicConfig',
        ])
            ->where('audit_follow_up_form_id', $form->id)
            ->where('active', 1)
            ->whereHas('finding', function ($q) use ($findingForm) {
                $q->where('audit_finding_form_id', $findingForm->id);
            })
            ->orderBy('id')
            ->get();


        $rows = [];
        $runsStandar = [];
        $runsDeskripsi = [];
        $runsRencana = [];
        $runsRealisasi = [];
        $runsStatus = [];

        foreach ($details as $idx => $d) {
            $i = $idx + 1;

            $finding   = $d->finding;
            $seDetail  = $finding?->selfEvaluationDetail;
            $indicator = $seDetail?->indicator;
            $standard  = $indicator?->standard;

            $findingNo = (int) ($finding?->finding_no ?? $i);

            // ✅ FIX: tahun ikut asal temuan, bukan tahun berjalan
            $acadShortRow = $this->academicShortFromFinding($finding, $fed->academicConfig);
            $nomorTemuan  = $this->formatNomorTemuan($unitCode, $acadShortRow, $findingNo);

            $sev = strtolower(trim((string) ($finding?->severity ?? '')));
            $isObs   = in_array($sev, ['obs', 'observasi'], true);
            $isMinor = in_array($sev, ['kts minor', 'kts_minor', 'minor'], true);
            $isMayor = in_array($sev, ['kts mayor', 'kts_mayor', 'mayor'], true);

            // Standar + indikator
            $stdRun = new TextRun();
            $stdName = $this->cleanText($standard?->name);
            if ($stdName !== '') {
                $stdRun->addText($stdName, ['bold' => true]);
                $stdRun->addTextBreak();
            }
            $this->parseHtmlToTextRun($stdRun, $indicator?->description ?? '');
            $runsStandar[$i] = $stdRun;

            // Deskripsi kondisi (FED)
            $descRun = new TextRun();
            $this->parseHtmlToTextRun($descRun, $seDetail?->result ?? '');
            $runsDeskripsi[$i] = $descRun;

            // Rencana (Temuan)
            $rtlRun = new TextRun();
            $this->parseHtmlToTextRun($rtlRun, (string) ($finding?->corrective_action_plan ?? $finding?->follow_up_plan ?? ''));
            $runsRencana[$i] = $rtlRun;

            // Realisasi (Auditee)
            $realRun = new TextRun();
            $this->parseHtmlToTextRun($realRun, (string) ($d->follow_up_realization ?? ''));
            $runsRealisasi[$i] = $realRun;

            // Status (Auditor)
            $runsStatus[$i] = $this->statusToRun($d->status, $d->status_description);

            $rows[] = [
                'd_no' => (string) $i,
                'd_nomor_temuan' => $nomorTemuan,
                'd_obs' => $isObs ? '✓' : '',
                'd_kts_minor' => $isMinor ? '✓' : '',
                'd_kts_mayor' => $isMayor ? '✓' : '',
                'd_jadwal' => !empty($finding?->due_date) ? \Carbon\Carbon::parse($finding->due_date)->format('d/m/Y') : '',
                'd_efektivitas' => $this->cleanText($d->effectiveness, ''),
            ];
        }

        if (count($rows) === 0) {
            $rows[] = [
                'd_no' => '1',
                'd_nomor_temuan' => '',
                'd_obs' => '',
                'd_kts_minor' => '',
                'd_kts_mayor' => '',
                'd_jadwal' => '',
                'd_efektivitas' => '',
            ];
        }

        $tp->cloneRowAndSetValues('d_no', $rows);

        foreach ($rows as $idx => $_) {
            $i = $idx + 1;
            try { $tp->setComplexBlock("d_standar#{$i}", $runsStandar[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("d_deskripsi#{$i}", $runsDeskripsi[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("d_rencana#{$i}", $runsRencana[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("d_realisasi#{$i}", $runsRealisasi[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("d_status#{$i}", $runsStatus[$i] ?? new TextRun()); } catch (\Throwable $e) {}
        }

        // tabel "ATL sebelumnya" (atas) - termasuk sebelum-sebelumnya
        $this->fillPrevAtlToTemplateF221($tp, $fed, $unitCode, $fed->academicConfig);

        // SAVE
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

        $safeUnit = $this->safeFilename($unitName ?: 'Unit');
        $docxFilename = "F-221_Audit_Tindak_Lanjut_{$safeUnit}.docx";
        $docxPath = $tmpDir . DIRECTORY_SEPARATOR . $docxFilename;

        if (file_exists($docxPath)) @unlink($docxPath);

        $tp->saveAs($docxPath);
        abort_unless(file_exists($docxPath), 500, 'Gagal membuat DOCX ATL.');

        return [$docxPath, $docxFilename];
    }

    public function exportDocx(AuditFollowUpForm $form): BinaryFileResponse
    {
        [$docxPath, $docxFilename] = $this->buildDocxF221($form);
        return response()->download($docxPath, $docxFilename)->deleteFileAfterSend(true);
    }

    public function exportPdf(AuditFollowUpForm $form): BinaryFileResponse
    {
        [$docxPath, $docxFilename] = $this->buildDocxF221($form);

        $convertApiSecret = env('CONVERTAPI_SECRET');
        abort_unless(!empty($convertApiSecret), 500, 'CONVERTAPI_SECRET belum dikonfigurasi.');

        $tmpDir = storage_path('app/tmp');
        $pdfFilename = str_replace('.docx', '.pdf', $docxFilename);
        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . $pdfFilename;

        if (file_exists($pdfPath)) @unlink($pdfPath);

        try {
            ConvertApi::setApiCredentials($convertApiSecret);

            $result = ConvertApi::convert('pdf', [
                'File' => $docxPath,
            ], 'docx');

            $result->saveFiles($tmpDir);

            if (!file_exists($pdfPath)) {
                $files = glob($tmpDir . '/*.pdf');
                if (!empty($files)) {
                    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
                    $found = $files[0];
                    if ($found !== $pdfPath) rename($found, $pdfPath);
                }
            }

            if (file_exists($docxPath)) @unlink($docxPath);
            abort_unless(file_exists($pdfPath), 500, 'Gagal mengkonversi dokumen ATL ke PDF.');

            return response()->download($pdfPath, $pdfFilename)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (file_exists($docxPath)) @unlink($docxPath);
            if (file_exists($pdfPath)) @unlink($pdfPath);
            abort(500, 'ConvertAPI Error: ' . $e->getMessage());
        }
    }
}
