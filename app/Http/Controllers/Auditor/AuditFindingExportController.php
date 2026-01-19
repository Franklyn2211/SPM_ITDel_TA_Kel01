<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Models\AcademicConfig;
use App\Models\AuditFinding;
use App\Models\AuditFindingForm;
use App\Models\SelfEvaluationForm;
use App\Models\UserRole;
use ConvertApi\ConvertApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditFindingExportController extends Controller
{
    private const TEMPLATE_PATH_F220 = 'templates/F-220_Formulir_Temuan_Rencana_Tindak_Lanjut.docx';
    private const FORM_STATUS_FINAL  = 'Final';

    // === ganti kalau kolom bukti lu beda ===
    private const EVIDENCE_URL_COLUMN = 'supporting_evidence_url';

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

    private function ensureUserCanAccessForm(AuditFindingForm $form): void
    {
        if ($this->isAdmin()) return;

        $myRoleId = $this->currentUserRoleId();
        if ($myRoleId) {
            if (in_array($myRoleId, [$form->auditor_user_role_id, $form->member_auditor_user_role_id], true)) {
                return;
            }
        }

        $user = auth()->user();
        abort_unless($user, 403, 'User belum login.');

        $fed = SelfEvaluationForm::findOrFail($form->self_evaluation_form_id);

        if (!empty($fed->head_auditee_user_id) && (string) $fed->head_auditee_user_id === (string) $user->id) {
            return;
        }

        $myName   = $this->normalizeName((string) ($user->name ?? ''));
        $headName = $this->normalizeName((string) ($fed->head_auditee_name ?? ''));

        if ($headName !== '' && $myName !== '' && $myName === $headName) {
            return;
        }

        $myUserId = (string) $user->id;
        $memberIds = array_filter([
            $fed->member_auditee_1_user_id ?? null,
            $fed->member_auditee_2_user_id ?? null,
            $fed->member_auditee_3_user_id ?? null,
        ], fn ($v) => !is_null($v) && (string) $v !== '');

        $memberIds = array_map('strval', $memberIds);

        abort_unless(in_array($myUserId, $memberIds, true), 403, 'Tidak berhak mengakses dokumen temuan ini.');
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

    /**
     * HTML summernote -> TextRun (udah support <a> jadi hyperlink)
     */
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

                $marker = '• ';
                if ($lType !== 'ul') {
                    if ($lType === 'a') $marker = chr(96 + (($idx - 1) % 26 + 1)) . '. ';
                    elseif ($lType === 'A') $marker = chr(64 + (($idx - 1) % 26 + 1)) . '. ';
                    else $marker = "{$idx}. ";
                }

                $run->addTextBreak();
                $nbsp = "\xC2\xA0";
                $indentStr = str_repeat($nbsp . $nbsp . $nbsp, $depth);
                $run->addText($indentStr . $marker, $style);

                foreach ($node->childNodes as $child) $traverse($child, $style, $listContext);
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

            foreach ($node->childNodes as $child) $traverse($child, $style, $listContext);
        };

        $traverse($container);
    }

    private function addEvidenceUrlToRun(TextRun $run, ?string $url): void
    {
        $url = trim((string)($url ?? ''));
        if ($url === '') return;

        // normalize
        if (!preg_match('~^https?://~i', $url)) $url = 'https://' . $url;

        $run->addTextBreak();
        $run->addText('Bukti: ', ['bold' => true]);
        $run->addLink($url, $url, ['color' => '0000FF', 'underline' => 'single']);
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        return Str::limit($name, 120, '');
    }

    private function academicShort(?AcademicConfig $ac): string
    {
        $raw = trim((string) ($ac->academic_code ?? $ac->name ?? ''));

        if (preg_match('/(20\d{2})\s*\/\s*(20\d{2})/', $raw, $m)) {
            return substr($m[1], 2, 2) . substr($m[2], 2, 2);
        }
        if (preg_match('/\b(\d{4})\b/', $raw, $m)) return $m[1];
        return '0000';
    }

    private function formatNoTemuan(string $prefix, string $unitCode, string $acadShort, int $runningNo): string
    {
        $no = str_pad((string) $runningNo, 3, '0', STR_PAD_LEFT);
        return "{$prefix}-AMI/{$unitCode}/{$acadShort}/{$no}";
    }

    private function isNegative(AuditFinding $row): bool
    {
        $ach = mb_strtolower(trim((string) optional($row->selfEvaluationDetail?->standardAchievement)->name));
        return !in_array($ach, ['mencapai', 'melampaui'], true);
    }

    private function picRoleNames(?string $standardIndicatorId): string
    {
        if (!$standardIndicatorId) return '';
        return DB::table('ami_standard_indicator_pic as p')
            ->join('roles as r', 'r.id', '=', 'p.role_id')
            ->where('p.standard_indicator_id', $standardIndicatorId)
            ->where('p.active', 1)
            ->pluck('r.name')
            ->unique()
            ->implode(', ');
    }

    private function unitCodeFromFed(SelfEvaluationForm $fed, AuditFindingForm $form): string
    {
        $code = strtoupper(trim((string) optional($fed->categoryDetail)->code));
        if ($code === '') $code = strtoupper(trim((string) ($form->area ?? '')));
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

    /* ================= Export PDF ================= */

    public function exportPdf(AuditFindingForm $form): BinaryFileResponse
    {
        $this->ensureUserCanAccessForm($form);
        abort_unless($form->status === self::FORM_STATUS_FINAL, 403, 'Dokumen hanya tersedia setelah form Final.');

        $convertApiSecret = env('CONVERTAPI_SECRET');
        abort_unless(!empty($convertApiSecret), 500, 'CONVERTAPI_SECRET belum dikonfigurasi.');
        abort_unless(class_exists(\ZipArchive::class), 500, 'PHP Zip extension belum aktif.');

        $fed = SelfEvaluationForm::with(['academicConfig', 'categoryDetail'])
            ->findOrFail($form->self_evaluation_form_id);

        $rows = AuditFinding::with([
                'selfEvaluationDetail.standardAchievement',
                'selfEvaluationDetail.indicator.standard',
            ])
            ->where('audit_finding_form_id', $form->id)
            ->where('active', 1)
            ->orderBy('finding_no')
            ->get();

        $rowsPos = $rows->filter(fn ($r) => !$this->isNegative($r))->values();
        $rowsNeg = $rows->filter(fn ($r) =>  $this->isNegative($r))->values();

        $templateAbsPath = storage_path('app/' . self::TEMPLATE_PATH_F220);
        abort_unless(is_file($templateAbsPath), 500, 'Template DOCX F-220 tidak ditemukan: ' . self::TEMPLATE_PATH_F220);

        $tp = new TemplateProcessor($templateAbsPath);

        $unitName  = optional($fed->categoryDetail)->name ?? ($form->area ?? '');
        $acadShort = $this->academicShort($fed->academicConfig);
        $unitCode  = $this->unitCodeFromFed($fed, $form);

        $ketuaAuditee   = trim((string) ($fed->head_auditee_name ?? ''));
        $ketuaAuditor   = $this->userRoleName($form->auditor_user_role_id);
        $anggotaAuditor = $this->userRoleName($form->member_auditor_user_role_id);

        $tp->setValue('area', $unitName);
        $tp->setValue('auditee', $unitName);
        $tp->setValue('ketua_auditee', $ketuaAuditee);
        $tp->setValue('ketua_auditor', $ketuaAuditor);
        $tp->setValue('anggota_auditor', $anggotaAuditor);
        $tp->setValue('tanggal_audit', $form->audit_date ? \Carbon\Carbon::parse($form->audit_date)->format('d/m/Y') : '');

        $tp->setValue('nama_ketua_auditee', $ketuaAuditee);
        $tp->setValue('nama_ketua_auditor', $ketuaAuditor);

        $evidenceCol = self::EVIDENCE_URL_COLUMN;

        /* ================= POSITIF ================= */
        $pRows = [];
        $pStandarBlocks      = [];
        $pDeskripsiBlocks    = [];
        $pFaktorBlocks       = [];
        $pPengendalianBlocks = [];
        $pPeningkatanBlocks  = [];
        $pRencanaBlocks      = [];

        foreach ($rowsPos as $idx => $r) {
            $i = $idx + 1;

            $detail    = $r->selfEvaluationDetail;
            $indicator = $detail?->indicator;
            $standard  = $indicator?->standard;

            $noTemuan = $this->formatNoTemuan('TP', $unitCode, $acadShort, $i);
            $ach = mb_strtolower(trim((string) optional($detail?->standardAchievement)->name));

            $stdRun = new TextRun();
            $stdName = $this->cleanText($standard?->name);
            if ($stdName !== '') {
                $stdRun->addText($stdName, ['bold' => true]);
                $stdRun->addTextBreak();
            }
            $this->parseHtmlToTextRun($stdRun, $indicator?->description ?? '');
            $pStandarBlocks[$i] = $stdRun;

            // ===== Deskripsi kondisi (FED) + BUKTI URL jadi hyperlink =====
            $descRun = new TextRun();
            $this->parseHtmlToTextRun($descRun, $detail?->result ?? '');
            $this->addEvidenceUrlToRun($descRun, $detail?->{$evidenceCol} ?? null);
            $pDeskripsiBlocks[$i] = $descRun;

            $fakRun = new TextRun();
            $this->parseHtmlToTextRun($fakRun, $detail?->contributing_factors ?? '');
            $pFaktorBlocks[$i] = $fakRun;

            $pengRun = new TextRun();
            $this->parseHtmlToTextRun($pengRun, $r->control ?? '');
            $pPengendalianBlocks[$i] = $pengRun;

            $peningRun = new TextRun();
            $this->parseHtmlToTextRun($peningRun, $r->improvement ?? '');
            $pPeningkatanBlocks[$i] = $peningRun;

            $rencanaRun = new TextRun();
            $this->parseHtmlToTextRun($rencanaRun, $r->follow_up_plan ?? '');
            $pRencanaBlocks[$i] = $rencanaRun;

            $pRows[] = [
                'p_no'               => (string) $i,
                'p_no_temuan'        => $noTemuan,
                'p_mencapai'         => $ach === 'mencapai' ? '✓' : '',
                'p_melampaui'        => $ach === 'melampaui' ? '✓' : '',
                'p_jadwal'           => $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('d/m/Y') : '',
                'p_penanggung_jawab' => $this->cleanText($this->picRoleNames($indicator?->id), ''),
            ];
        }

        if (count($pRows) === 0) {
            $pRows[] = [
                'p_no' => '1',
                'p_no_temuan' => '',
                'p_mencapai' => '',
                'p_melampaui' => '',
                'p_jadwal' => '',
                'p_penanggung_jawab' => '',
            ];
        }

        $tp->cloneRowAndSetValues('p_no', $pRows);

        foreach ($pRows as $idx => $_) {
            $i = $idx + 1;
            try { $tp->setComplexBlock("p_standar#{$i}", $pStandarBlocks[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("p_deskripsi#{$i}", $pDeskripsiBlocks[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("p_faktor#{$i}", $pFaktorBlocks[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("p_pengendalian#{$i}", $pPengendalianBlocks[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("p_peningkatan#{$i}", $pPeningkatanBlocks[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("p_rencana#{$i}", $pRencanaBlocks[$i] ?? new TextRun()); } catch (\Throwable $e) {}
        }

        /* ================= NEGATIF ================= */
        $nRows = [];
        $nStandarBlocks        = [];
        $nDeskripsiBlocks      = [];
        $nFaktorBlocks         = [];
        $nRekomendasiBlocks    = [];
        $nRencanaKoreksiBlocks = [];

        foreach ($rowsNeg as $idx => $r) {
            $i = $idx + 1;

            $detail    = $r->selfEvaluationDetail;
            $indicator = $detail?->indicator;
            $standard  = $indicator?->standard;

            $noTemuan = $this->formatNoTemuan('TN', $unitCode, $acadShort, $i);
            $sev = strtolower(trim((string) $r->severity));

            $stdRun = new TextRun();
            $stdName = $this->cleanText($standard?->name);
            if ($stdName !== '') {
                $stdRun->addText($stdName, ['bold' => true]);
                $stdRun->addTextBreak();
            }
            $this->parseHtmlToTextRun($stdRun, $indicator?->description ?? '');
            $nStandarBlocks[$i] = $stdRun;

            // ===== Deskripsi kondisi (FED) + BUKTI URL jadi hyperlink =====
            $descRun = new TextRun();
            $this->parseHtmlToTextRun($descRun, $detail?->result ?? '');
            $this->addEvidenceUrlToRun($descRun, $detail?->{$evidenceCol} ?? null);
            $nDeskripsiBlocks[$i] = $descRun;

            $fakRun = new TextRun();
            $this->parseHtmlToTextRun($fakRun, $detail?->contributing_factors ?? '');
            $nFaktorBlocks[$i] = $fakRun;

            $rekRun = new TextRun();
            $this->parseHtmlToTextRun($rekRun, $r->auditor_recommendation ?? '');
            $nRekomendasiBlocks[$i] = $rekRun;

            $korRun = new TextRun();
            $this->parseHtmlToTextRun($korRun, $r->corrective_action_plan ?? '');
            $nRencanaKoreksiBlocks[$i] = $korRun;

            $nRows[] = [
                'n_no'               => (string) $i,
                'n_no_temuan'        => $noTemuan,
                'n_obs'              => in_array($sev, ['obs', 'observasi'], true) ? '✓' : '',
                'n_kts_minor'        => in_array($sev, ['kts minor', 'kts_minor', 'minor'], true) ? '✓' : '',
                'n_kts_mayor'        => in_array($sev, ['kts mayor', 'kts_mayor', 'mayor'], true) ? '✓' : '',
                'n_jadwal'           => $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('d/m/Y') : '',
                'n_penanggung_jawab' => $this->cleanText($this->picRoleNames($indicator?->id), ''),
            ];
        }

        if (count($nRows) === 0) {
            $nRows[] = [
                'n_no' => '1',
                'n_no_temuan' => '',
                'n_obs' => '',
                'n_kts_minor' => '',
                'n_kts_mayor' => '',
                'n_jadwal' => '',
                'n_penanggung_jawab' => '',
            ];
        }

        $tp->cloneRowAndSetValues('n_no', $nRows);

        foreach ($nRows as $idx => $_) {
            $i = $idx + 1;
            try { $tp->setComplexBlock("n_standar#{$i}", $nStandarBlocks[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("n_deskripsi#{$i}", $nDeskripsiBlocks[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("n_faktor#{$i}", $nFaktorBlocks[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("n_rekomendasi#{$i}", $nRekomendasiBlocks[$i] ?? new TextRun()); } catch (\Throwable $e) {}
            try { $tp->setComplexBlock("n_rencana_koreksi#{$i}", $nRencanaKoreksiBlocks[$i] ?? new TextRun()); } catch (\Throwable $e) {}
        }

        /* ================= SAVE DOCX TEMP ================= */
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

        $safeUnit = $this->safeFilename($unitName ?: 'Unit');
        $docxFilename = "F-220_Temuan_{$safeUnit}.docx";
        $pdfFilename  = "F-220_Temuan_{$safeUnit}.pdf";

        $docxPath = $tmpDir . DIRECTORY_SEPARATOR . $docxFilename;
        $pdfPath  = $tmpDir . DIRECTORY_SEPARATOR . $pdfFilename;

        if (file_exists($docxPath)) @unlink($docxPath);
        if (file_exists($pdfPath))  @unlink($pdfPath);

        $tp->saveAs($docxPath);

        /* ================= CONVERT DOCX -> PDF ================= */
        try {
            ConvertApi::setApiCredentials($convertApiSecret);

            $result = ConvertApi::convert('pdf', [
                'File' => $docxPath,
            ], 'docx');

            $result->saveFiles($tmpDir);

            if (!file_exists($pdfPath)) {
                $files = glob($tmpDir . '/*.pdf');
                if (!empty($files)) {
                    usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));
                    $found = $files[0];
                    if ($found !== $pdfPath) rename($found, $pdfPath);
                }
            }

            if (file_exists($docxPath)) @unlink($docxPath);
            abort_unless(file_exists($pdfPath), 500, 'Gagal mengkonversi dokumen ke PDF.');

            return response()->download($pdfPath, $pdfFilename)->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            if (file_exists($docxPath)) @unlink($docxPath);
            if (file_exists($pdfPath))  @unlink($pdfPath);
            abort(500, 'ConvertAPI Error: ' . $e->getMessage());
        }
    }
}
