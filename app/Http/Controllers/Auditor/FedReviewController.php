<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Models\SelfEvaluationForm;
use App\Models\SelfEvaluationDetail;
use App\Models\StandardAchievement;
use App\Models\EvaluationStatus;
use ConvertApi\ConvertApi;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\TemplateProcessor;
use Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FedReviewController extends Controller
{
    private const TEMPLATE_PATH = 'templates/F-219_Formulir_Evaluasi_Diri_Auditee.docx';

    public function index()
    {
        $forms = SelfEvaluationForm::with(['categoryDetail', 'academicConfig', 'status', 'details.status'])
            ->where('active', 1)
            ->whereHas('status', function ($q) {
                $q->whereIn('name', ['Dikirim', 'Disetujui', 'Ditolak']);
            })
            ->orderBy('category_detail_id')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('auditor.fed.index', compact('forms'));
    }

    public function show($formId)
    {
        $form = SelfEvaluationForm::with([
            'categoryDetail',
            'academicConfig',
            'status',
        ])->findOrFail($formId);

        $opsiKetercapaian = StandardAchievement::where('active', 1)->get();

        // Ambil status filter dari request
        $filterStatus = request('status');

        // Query details dengan relasi dan filter status jika ada
        $detailsQuery = SelfEvaluationDetail::with(['indicator.standard', 'status', 'standardAchievement', 'auditChecklists', 'indicator'])
            ->where('self_evaluation_form_id', $form->id)
            ->orderBy('ami_standard_indicator_id');
        if ($filterStatus) {
            $detailsQuery->whereHas('status', function ($q) use ($filterStatus) {
                $q->where('name', $filterStatus);
            });
        }
        $details = $detailsQuery->paginate(10)->withQueryString();

        // Untuk ringkasan status, ambil semua details (tanpa filter/pagination)
        $form->load(['details.status', 'details.auditChecklists', 'details.indicator.standard', 'details.standardAchievement']);

        return view('auditor.fed.show', [
            'form' => $form,
            'opsiKetercapaian' => $opsiKetercapaian,
            'details' => $details,
        ]);
    }

    public function approveDetail($formId, $detailId)
    {
        $detail = SelfEvaluationDetail::where('self_evaluation_form_id', $formId)
            ->with('status')
            ->findOrFail($detailId);

        // cuma boleh approve kalau sekarang "Dikirim"
        if (($detail->status->name ?? null) !== 'Dikirim') {
            return back()->with('error', 'Indikator ini sudah diproses dan tidak bisa diubah lagi.');
        }

        $approvedStatusId = EvaluationStatus::where('name', 'Disetujui')->value('id');
        $draftStatusId = EvaluationStatus::where('name', 'Draft')->value('id');
        $rejectedStatusId = EvaluationStatus::where('name', 'Ditolak')->value('id');

        $detail->status_id = $approvedStatusId;
        $detail->updated_by = Auth::user()->userRole->id ?? null;
        $detail->save();

        // Update status form sesuai status seluruh detail
        $form = SelfEvaluationForm::findOrFail($formId);
        $details = SelfEvaluationDetail::where('self_evaluation_form_id', $formId)->get();
        $total = $details->count();
        $approved = $details->where('status_id', $approvedStatusId)->count();
        $rejected = $details->where('status_id', $rejectedStatusId)->count();

        if ($total > 0 && $approved === $total) {
            $form->status_id = $approvedStatusId;
        } elseif ($total > 0 && $rejected === $total) {
            $form->status_id = $rejectedStatusId;
        } else {
            $form->status_id = $draftStatusId;
        }
        $form->save();

        return back()->with('success', 'Indikator berhasil disetujui.');
    }

    public function rejectDetail($formId, $detailId)
    {
        $detail = SelfEvaluationDetail::where('self_evaluation_form_id', $formId)
            ->with('status')
            ->findOrFail($detailId);

        // cuma boleh reject kalau sekarang "Dikirim"
        if (($detail->status->name ?? null) !== 'Dikirim') {
            return back()->with('error', 'Indikator ini sudah diproses dan tidak bisa diubah lagi.');
        }

        $rejectedStatusId = EvaluationStatus::where('name', 'Ditolak')->value('id');
        $approvedStatusId = EvaluationStatus::where('name', 'Disetujui')->value('id');
        $draftStatusId = EvaluationStatus::where('name', 'Draft')->value('id');

        $detail->status_id = $rejectedStatusId;
        $detail->updated_by = Auth::user()->userRole->id ?? null;
        $detail->save();

        // Update status form sesuai status seluruh detail
        $form = SelfEvaluationForm::findOrFail($formId);
        $details = SelfEvaluationDetail::where('self_evaluation_form_id', $formId)->get();
        $total = $details->count();
        $approved = $details->where('status_id', $approvedStatusId)->count();
        $rejected = $details->where('status_id', $rejectedStatusId)->count();

        if ($total > 0 && $approved === $total) {
            $form->status_id = $approvedStatusId;
        } elseif ($total > 0 && $rejected === $total) {
            $form->status_id = $rejectedStatusId;
        } else {
            $form->status_id = $draftStatusId;
        }
        $form->save();

        return back()->with('success', 'Indikator ditolak. Silakan isi daftar tilik dan lakukan perbaikan pada isi FED.');
    }

    // dipanggil dari popup; hanya boleh kalau status sekarang "Ditolak"
    // setelah disimpan -> auto Disetujui
    public function updateDetail(Request $request, $formId, $detailId)
    {
        $request->validate([
            'ketercapaian_standard_id' => 'nullable|string',
            'hasil' => 'required|string',
            'bukti_pendukung' => 'nullable|string',
            'faktor_penghambat_pendukung' => 'nullable|string',
        ]);

        $detail = SelfEvaluationDetail::where('self_evaluation_form_id', $formId)
            ->with('status')
            ->findOrFail($detailId);

        if (($detail->status->name ?? null) !== 'Ditolak') {
            return back()->with('error', 'Isi FED hanya bisa diubah saat status indikator Ditolak.');
        }

        $approvedStatusId = EvaluationStatus::where('name', 'Disetujui')->value('id');
        $draftStatusId = EvaluationStatus::where('name', 'Draft')->value('id');
        $rejectedStatusId = EvaluationStatus::where('name', 'Ditolak')->value('id');

        $detail->standard_achievement_id = $request->ketercapaian_standard_id;
        $detail->result = $request->hasil;
        $detail->contributing_factors = $request->faktor_penghambat_pendukung;
        $detail->status_id = $approvedStatusId;
        $detail->updated_by = Auth::user()->userRole->id ?? null;
        $detail->save();

        // Update status form sesuai status seluruh detail
        $form = SelfEvaluationForm::findOrFail($formId);
        $details = SelfEvaluationDetail::where('self_evaluation_form_id', $formId)->get();
        $total = $details->count();
        $approved = $details->where('status_id', $approvedStatusId)->count();
        $rejected = $details->where('status_id', $rejectedStatusId)->count();

        if ($total > 0 && $approved === $total) {
            $form->status_id = $approvedStatusId;
        } elseif ($total > 0 && $rejected === $total) {
            $form->status_id = $rejectedStatusId;
        } else {
            $form->status_id = $draftStatusId;
        }
        $form->save();

        return back()->with('success', 'Isi FED telah diperbarui dan indikator dinyatakan disetujui.');
    }

    public function exportPdf(Request $request, SelfEvaluationForm $form): BinaryFileResponse
    {
        $form->load(['academicConfig', 'categoryDetail']);

        // WAJIB: semua indikator detail sudah Disetujui
        $detailsStatus = SelfEvaluationDetail::with(['status'])
            ->where('self_evaluation_form_id', $form->id)
            ->get();

        if ($detailsStatus->isEmpty()) {
            abort(403, 'Tidak ada indikator pada form ini.');
        }

        $allApproved = $detailsStatus->every(fn($d) => (($d->status->name ?? '') === 'Disetujui'));
        abort_unless($allApproved, 403, 'PDF hanya tersedia setelah seluruh indikator disetujui.');

        // ConvertAPI secret wajib
        $convertApiSecret = env('CONVERTAPI_SECRET');
        if (empty($convertApiSecret)) {
            abort(500, 'CONVERTAPI_SECRET belum dikonfigurasi. Silakan set di file .env');
        }

        if (!class_exists(\ZipArchive::class)) {
            abort(500, 'PHP Zip extension belum aktif.');
        }

        // Ambil detail lengkap untuk isi template
        $details = SelfEvaluationDetail::with(['indicator.standard', 'standardAchievement'])
            ->where('self_evaluation_form_id', $form->id)
            ->whereHas('indicator', function ($q) use ($form) {
                $q->where('ami_standard_indicators.active', 1)
                    ->whereExists(function ($qq) use ($form) {
                        $qq->select(DB::raw(1))
                            ->from('ami_standards as s')
                            ->whereColumn('s.id', 'ami_standard_indicators.standard_id')
                            ->where('s.active', 1)
                            ->where('s.academic_config_id', $form->academic_config_id);
                    });
            })
            ->orderBy('ami_standard_indicator_id')
            ->get();

        // ==== LOAD TEMPLATE ====
        $templateAbsPath = storage_path('app/' . self::TEMPLATE_PATH);
        if (!is_file($templateAbsPath)) {
            abort(500, 'Template DOCX tidak ditemukan: ' . self::TEMPLATE_PATH);
        }

        $tp = new TemplateProcessor($templateAbsPath);

        // ==== HEADER ====
        $taName = optional($form->academicConfig)->name ?? '';
        $unitName = optional($form->categoryDetail)->name ?? '';

        $ketua = trim(($form->head_auditee_position ?? '') . ' / ' . ($form->head_auditee_name ?? ''), ' /');
        $namaketua = trim($form->head_auditee_name ?? '');

        $angg1 = trim(($form->member_auditee_1_position ?? '') . ' / ' . ($form->member_auditee_1_name ?? ''), ' /');
        $angg2 = trim(($form->member_auditee_2_position ?? '') . ' / ' . ($form->member_auditee_2_name ?? ''), ' /');
        $angg3 = trim(($form->member_auditee_3_position ?? '') . ' / ' . ($form->member_auditee_3_name ?? ''), ' /');

        $tp->setValue('categoryDetail', $unitName);
        $tp->setValue('ta', $taName);
        $tp->setValue('ketua', $ketua ?: '');
        $tp->setValue('namaketua', $namaketua ?: '');
        $tp->setValue('anggota1', $angg1 ?: '');
        $tp->setValue('anggota2', $angg2 ?: '');
        $tp->setValue('anggota3', $angg3 ?: '');
        $tp->setValue('tanggal', now()->format('d/m/Y'));

        // ==== HELPERS ====
        $cleanText = function (?string $value, string $fallback = ''): string {
            $text = $value ?? '';
            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $text = preg_replace('/[^\P{C}\n]+/u', '', $text);
            $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
            $text = preg_replace('/\s+/u', ' ', $text);
            $text = trim($text);
            return $text === '' ? $fallback : $text;
        };

        $parseHtmlToTextRun = function (TextRun $run, ?string $html) {
            $html = $html ?? '';
            if (trim($html) === '')
                return;

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
                    if ($text !== '')
                        $run->addText($text, $style);
                    return;
                }
                if ($node->nodeType !== XML_ELEMENT_NODE)
                    return;

                $tag = strtolower($node->nodeName);

                if ($tag === 'b' || $tag === 'strong')
                    $style['bold'] = true;
                elseif ($tag === 'i' || $tag === 'em')
                    $style['italic'] = true;
                elseif ($tag === 'u')
                    $style['underline'] = 'single';

                if ($tag === 'p' || $tag === 'div') {
                    if ($node->previousSibling)
                        $run->addTextBreak();
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
                        if ($lType === 'a')
                            $marker = chr(96 + (($idx - 1) % 26 + 1)) . '. ';
                        elseif ($lType === 'A')
                            $marker = chr(64 + (($idx - 1) % 26 + 1)) . '. ';
                        else
                            $marker = "{$idx}. ";
                    }

                    $run->addTextBreak();
                    $nbsp = "\xC2\xA0";
                    $indentStr = str_repeat($nbsp . $nbsp . $nbsp, $depth);
                    $run->addText($indentStr . $marker, $style);

                    foreach ($node->childNodes as $child)
                        $traverse($child, $style, $listContext);
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

                foreach ($node->childNodes as $child)
                    $traverse($child, $style, $listContext);
            };

            $traverse($container);
        };

        // ==== BUILD ROWS ====
        $rows = [];
        $standarBlocks = [];
        $hasilBlocks = [];
        $faktorBlocks = [];

        foreach ($details as $i => $d) {
            $index = $i + 1;

            $stdRun = new TextRun();
            $stdName = trim(strip_tags($d->indicator->standard->name ?? ''));
            if ($stdName) {
                $stdRun->addText($stdName, ['bold' => true]);
                $stdRun->addTextBreak();
            }
            $parseHtmlToTextRun($stdRun, $d->indicator->description ?? '');
            $standarBlocks[$index] = $stdRun;

            $resRun = new TextRun();
            $parseHtmlToTextRun($resRun, $d->result ?? '');
            $hasilBlocks[$index] = $resRun;

            $fakRun = new TextRun();
            $parseHtmlToTextRun($fakRun, $d->contributing_factors ?? '');
            $faktorBlocks[$index] = $fakRun;

            $flag = strtolower(optional($d->standardAchievement)->name ?? '');

            $picRoleNames = DB::table('ami_standard_indicator_pic as p')
                ->join('roles as r', 'r.id', '=', 'p.role_id')
                ->where('p.standard_indicator_id', $d->ami_standard_indicator_id)
                ->where('p.active', 1)
                ->pluck('r.name')
                ->unique()
                ->implode(', ');

            $rows[] = [
                'no' => (string) $index,
                'sumber' => $cleanText($picRoleNames, ''),
                'melampaui' => $flag === 'melampaui' ? '✓' : '',
                'mencapai' => $flag === 'mencapai' ? '✓' : '',
                'tidak_mencapai' => $flag === 'tidak mencapai' ? '✓' : '',
                'menyimpang' => $flag === 'menyimpang' ? '✓' : '',
            ];
        }

        $tp->cloneRowAndSetValues('no', $rows);

        foreach ($rows as $idx => $_) {
            $i = $idx + 1;
            if (isset($standarBlocks[$i]))
                $tp->setComplexBlock("standar#{$i}", $standarBlocks[$i]);
            if (isset($hasilBlocks[$i]))
                $tp->setComplexBlock("hasil#{$i}", $hasilBlocks[$i]);
            if (isset($faktorBlocks[$i]))
                $tp->setComplexBlock("faktor#{$i}", $faktorBlocks[$i]);
        }

        // ==== SAVE TEMP DOCX ====
        $safe = function (string $name): string {
            $name = preg_replace('/[\\\\\\/:*?"<>|]+/', '', $name);
            $name = trim(preg_replace('/\s+/', ' ', $name));
            return Str::limit($name, 120, '');
        };

        $safeUnit = $safe($unitName ?: 'Unit');
        $docxFilename = "F-219_Formulir_Evaluasi_Diri_{$safeUnit}.docx";
        $pdfFilename = "F-219_Formulir_Evaluasi_Diri_{$safeUnit}.pdf";

        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir))
            @mkdir($tmpDir, 0775, true);

        $docxPath = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $docxFilename;
        $pdfPath = rtrim($tmpDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pdfFilename;

        if (file_exists($docxPath))
            @unlink($docxPath);
        if (file_exists($pdfPath))
            @unlink($pdfPath);

        $tp->saveAs($docxPath);

        // ==== CONVERT TO PDF ====
        try {
            ConvertApi::setApiCredentials($convertApiSecret);
            $result = ConvertApi::convert('pdf', ['File' => $docxPath], 'docx');
            $result->saveFiles($tmpDir);

            // ConvertAPI kadang nyimpan dengan nama lain, cari pdf terbaru
            if (!file_exists($pdfPath)) {
                $files = glob($tmpDir . '/*.pdf');
                if (!empty($files)) {
                    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
                    $latest = $files[0];
                    if ($latest !== $pdfPath) {
                        @rename($latest, $pdfPath);
                    }
                }
            }

            if (file_exists($docxPath))
                @unlink($docxPath);

            abort_unless(file_exists($pdfPath), 500, 'Gagal mengkonversi dokumen ke PDF.');

            return response()->download($pdfPath, $pdfFilename)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (file_exists($docxPath))
                @unlink($docxPath);
            if (file_exists($pdfPath))
                @unlink($pdfPath);
            abort(500, 'Gagal export PDF: ' . $e->getMessage());
        }
    }

}
