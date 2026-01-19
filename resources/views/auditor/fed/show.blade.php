{{-- resources/views/auditor/fed/show.blade.php --}}
@extends('auditor.layouts.app')

@section('title', 'Review Form Evaluasi Diri')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h4 class="page-title mb-0">
        Audit FED - <span class="fw-normal">{{ $form->categoryDetail->name ?? 'Unit/Prodi' }}</span>
      </h4>
      <a href="#page_header"
         class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
         data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    @php
      $allApproved = $form->details->count() > 0
          && $form->details->every(fn($d) => (($d->status->name ?? '') === 'Disetujui'));
    @endphp
    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center gap-2">
        @if($allApproved)
          <a href="{{ route('auditor.fed.exportPdf', $form->id) }}" class="btn btn-outline-danger btn-sm rounded-pill">
            <i class="ph-download-simple me-2"></i> Unduh PDF
          </a>
        @endif
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('auditor.dashboard') }}" class="breadcrumb-item">
          <i class="ph-house"></i>
        </a>
        <a href="{{ route('auditor.fed.index') }}" class="breadcrumb-item">Daftar FED</a>
        <span class="breadcrumb-item active">Review FED</span>
      </div>

      <div class="ms-auto d-flex align-items-center text-muted gap-3">
        @if($form->academicConfig)
          <div><i class="ph-calendar me-1"></i> {{ $form->academicConfig->name ?? $form->academicConfig->tahun }}</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">

  {{-- Flash messages --}}
  @foreach (['success','info','warning','error'] as $f)
    @if (session($f))
      @php
        $cls = $f === 'success' ? 'success' : ($f === 'warning' ? 'warning' : ($f === 'error' ? 'danger' : 'info'));
        $icon = $f === 'success' ? 'check-circle' : ($f === 'warning' ? 'warning' : ($f === 'error' ? 'x-circle' : 'info'));
      @endphp
      <div class="alert alert-{{ $cls }} border-0 alert-dismissible fade show">
        <div class="d-flex align-items-center">
          <i class="ph-{{ $icon }} me-2"></i>
          {{ session($f) }}
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
  @endforeach

  @if ($errors->any())
    <div class="alert alert-danger border-0 alert-dismissible fade show">
      <div class="d-flex align-items-center">
        <i class="ph-warning me-2"></i>
        <div>
          <strong>Gagal menyimpan:</strong>
          <ul class="mb-0">
            @foreach ($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Info singkat FED --}}
  <div class="card mb-3">
    <div class="card-body row g-3 align-items-center">
      <div class="col-md-4">
        <div class="text-muted fs-sm">Tahun Akademik</div>
        <div class="fw-semibold">
          {{ $form->academicConfig->name ?? $form->academicConfig->tahun ?? '-' }}
        </div>
      </div>
      <div class="col-md-4">
        <div class="text-muted fs-sm">Unit / Prodi</div>
        <div class="fw-semibold">
          {{ $form->categoryDetail->name ?? '-' }}
        </div>
      </div>
      <div class="col-md-4">
        <div class="text-muted fs-sm">Ringkasan Status Indikator</div>
        @php
          $total    = $form->details->count();
          $approved = $form->details->filter(fn($d) => ($d->status->name ?? '') === 'Disetujui')->count();
          $rejected = $form->details->filter(fn($d) => ($d->status->name ?? '') === 'Ditolak')->count();
          $pending  = $form->details->filter(fn($d) => ($d->status->name ?? '') === 'Dikirim')->count();
        @endphp
        <div class="d-flex flex-wrap gap-2 mt-1">
          <span class="badge bg-secondary rounded-pill">Total: {{ $total }}</span>
          <span class="badge bg-success rounded-pill">Disetujui: {{ $approved }}</span>
          <span class="badge bg-danger rounded-pill">Ditolak: {{ $rejected }}</span>
          <span class="badge bg-info rounded-pill">Menunggu: {{ $pending }}</span>
        </div>
      </div>
    </div>
  </div>

  {{-- Tabel indikator --}}
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h5 class="mb-0">Daftar Indikator untuk Diaudit</h5>
      <form method="GET" class="d-flex align-items-center" style="gap:8px;">
        <label for="filter_status" class="mb-0 me-2 fw-normal">Filter Status:</label>
        <select name="status" id="filter_status" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">Semua</option>
          <option value="Disetujui" @if(request('status')=='Disetujui') selected @endif>Disetujui</option>
          <option value="Ditolak" @if(request('status')=='Ditolak') selected @endif>Ditolak</option>
          <option value="Dikirim" @if(request('status')=='Dikirim') selected @endif>Dikirim</option>
          <option value="Draft" @if(request('status')=='Draft') selected @endif>Draft</option>
        </select>
      </form>
    </div>

    <div class="table-responsive">
      @php $filteredDetails = $details ?? collect(); @endphp

      <table class="table table-hover align-top mb-0" id="tableFedAuditor">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width: 60px;">No</th>
            <th style="min-width: 320px;">Standar & Indikator</th>
            <th style="min-width: 560px;">Isi FED (Auditee)</th>
            <th style="width: 140px;" class="text-center">Status</th>
            <th style="width: 320px;">Aksi Auditor</th>
          </tr>
        </thead>

        <tbody>
          @forelse($filteredDetails as $detail)
            @php
              $statusNameDetail = $detail->status->name ?? 'Draft';
              $badgeDetail = match($statusNameDetail) {
                'Disetujui' => 'bg-success',
                'Ditolak'   => 'bg-danger',
                'Dikirim'   => 'bg-info',
                default     => 'bg-secondary',
              };

              $stdName = optional($detail->indicator->standard)->name ?? 'Standar';
              $rawDesc = $detail->indicator->description ?? '';
              $plainDesc = trim(preg_replace('/\s+/', ' ', strip_tags($rawDesc)));
              $shortDesc = \Illuminate\Support\Str::limit($plainDesc, 160);

              $achName = $detail->standardAchievement->name ?? 'Belum diisi';

              $plainResult = trim(preg_replace('/\s+/', ' ', strip_tags($detail->result ?? '')));
              $plainResult = $plainResult ?: 'Belum diisi.';
              $snippet = \Illuminate\Support\Str::limit($plainResult, 240);

              $collapseId = "fedDetail_{$detail->id}";

              $activeCls = collect($detail->auditChecklists ?? [])
                ->filter(fn($c) => (bool)($c->active ?? true))
                ->values();

              $checklists = $activeCls->map(fn($c) => [
                'id' => $c->id,
                'item' => $c->item,
                'note' => $c->note,
              ]);

              $checklistsJsonB64 = base64_encode($checklists->toJson());

              // html -> base64 untuk modal edit (biar aman di attr)
              $hasilB64  = base64_encode($detail->result ?? '');
              $faktorB64 = base64_encode($detail->contributing_factors ?? '');

              // URL bukti (sesuaikan nama kolom lu di DB)
              $buktiUrl = $detail->supporting_evidence_url
                ?? $detail->evidence_url
                ?? $detail->bukti_pendukung_url
                ?? null;

              $buktiUrlB64 = base64_encode((string)($buktiUrl ?? ''));

              $posTemplate = $detail->indicator->positive_result_template ?? '';
              $negTemplate = $detail->indicator->negative_result_template ?? '';
            @endphp

            <tr id="detail-{{ $detail->id }}">
              <td class="text-center">{{ $loop->iteration }}</td>

              {{-- Standar & Indikator --}}
              <td>
                <div class="fw-semibold mb-1">{{ $stdName }}</div>
                <div class="text-muted fs-sm">{{ $shortDesc }}</div>

                <button type="button"
                        class="btn btn-link p-0 fs-sm mt-1"
                        data-bs-toggle="modal"
                        data-bs-target="#modalDesc"
                        data-title="{{ e($stdName) }}"
                        data-desc-html="{{ base64_encode($rawDesc) }}">
                  Lihat indikator lengkap
                </button>
              </td>

              {{-- Isi FED (Auditee) --}}
              <td>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                  <span class="badge bg-primary rounded-pill">
                    Ketercapaian: {{ $achName }}
                  </span>

                  <span class="text-muted fs-sm">
                    Checklist aktif: <strong>{{ $activeCls->count() }}</strong>
                  </span>

                  @if(!empty($buktiUrl))
                    <a class="badge bg-dark rounded-pill text-decoration-none"
                       href="{{ $buktiUrl }}"
                       target="_blank"
                       rel="noopener noreferrer">
                      Bukti: buka link
                    </a>
                  @else
                    <span class="badge bg-light text-muted rounded-pill">Bukti: -</span>
                  @endif
                </div>

                <div class="text-muted fs-sm mb-1">Hasil (ringkas)</div>
                <div class="fs-sm">{{ $snippet }}</div>

                <button class="btn btn-link p-0 fs-sm mt-1"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#{{ $collapseId }}"
                        aria-expanded="false"
                        aria-controls="{{ $collapseId }}">
                  Lihat detail hasil
                </button>

                <div class="collapse mt-2" id="{{ $collapseId }}">
                  <div class="border rounded p-3 bg-light">
                    <div class="text-muted fs-sm mb-1">Hasil (lengkap)</div>
                    <div class="fs-sm">
                      {!! $detail->result ?: '<span class="text-muted">Belum diisi.</span>' !!}
                    </div>

                    <hr class="my-3">

                    <div class="text-muted fs-sm mb-1">Bukti Pendukung</div>
                    <div class="fs-sm">
                      @if(!empty($buktiUrl))
                        <a href="{{ $buktiUrl }}" target="_blank" rel="noopener noreferrer">{{ $buktiUrl }}</a>
                      @else
                        <span class="text-muted">Belum diisi.</span>
                      @endif
                    </div>

                    <hr class="my-3">

                    <div class="text-muted fs-sm mb-1">Faktor Penghambat / Pendukung</div>
                    <div class="fs-sm">
                      {!! $detail->contributing_factors ?: '<span class="text-muted">Belum diisi.</span>' !!}
                    </div>
                  </div>
                </div>
              </td>

              {{-- Status --}}
              <td class="text-center">
                <span class="badge {{ $badgeDetail }} rounded-pill">{{ $statusNameDetail }}</span>
              </td>

              {{-- Aksi --}}
              <td>
                @if($statusNameDetail === 'Dikirim')
                  <div class="d-flex flex-wrap gap-2 mb-2">
                    <form method="POST" action="{{ route('auditor.fed.details.approve', [$form->id, $detail->id]) }}">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-success">Terima</button>
                    </form>

                    <form method="POST" action="{{ route('auditor.fed.details.reject', [$form->id, $detail->id]) }}">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-danger">Tolak</button>
                    </form>
                  </div>
                  <div class="text-muted fs-sm">Terima jika sudah sesuai. Tolak jika perlu tindak lanjut.</div>

                @elseif($statusNameDetail === 'Ditolak')
                  <div class="d-flex flex-wrap gap-2 mb-2">
                    {{-- Checklist modal (EDITABLE) --}}
                    <button type="button"
                      class="btn btn-sm btn-outline-primary"
                      data-bs-toggle="modal"
                      data-bs-target="#modalChecklist"
                      data-existing-json-b64="{{ $checklistsJsonB64 }}"
                      data-action="{{ route('auditor.checklists.bulkUpsert', $detail->id) }}">
                      Daftar Tilik
                    </button>

                    {{-- Edit FED auditor --}}
                    <button type="button"
                      class="btn btn-sm btn-primary"
                      data-bs-toggle="modal"
                      data-bs-target="#modalEditFedAuditor"
                      data-update-url="{{ route('auditor.fed.details.update', [$form->id, $detail->id]) }}"
                      data-ketercapaian="{{ $detail->standard_achievement_id ?? '' }}"
                      data-hasil-b64="{{ $hasilB64 }}"
                      data-faktor-b64="{{ $faktorB64 }}"
                      data-bukti-url-b64="{{ $buktiUrlB64 }}"
                      data-pos-template="{{ base64_encode($posTemplate) }}"
                      data-neg-template="{{ base64_encode($negTemplate) }}">
                      Isi/Edit FED
                    </button>
                  </div>

                  <div class="text-muted fs-sm">
                    Checklist <strong>bisa diedit</strong> selama status indikator masih <strong>Ditolak</strong>.
                    Setelah simpan isi FED auditor, status indikator jadi <strong>Disetujui</strong>.
                  </div>
                @else
                  <div class="text-muted fs-sm">Tidak ada aksi.</div>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">
                Belum ada butir indikator untuk FED ini.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>

      {{-- Pagination --}}
      @if(method_exists($filteredDetails, 'links'))
        <div class="mt-3">
          {{ $filteredDetails->withQueryString()->links() }}
        </div>
      @endif
    </div>
  </div>
</div>

{{-- ================== MODAL: DESKRIPSI INDIKATOR (HTML ASLI) ================== --}}
<div class="modal fade" id="modalDesc" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDesc_title">Indikator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div id="modalDesc_body" style="white-space: normal;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

{{-- ================== MODAL: CHECKLIST (EDITABLE) ================== --}}
<div class="modal fade" id="modalChecklist" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" id="formChecklist" class="modal-content">
      @csrf

      <div class="modal-header">
        <h5 class="modal-title">Daftar Tilik Auditor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div id="checklistHint" class="alert alert-info py-2 mb-3">
          Buat/ubah daftar tilik. Hanya bisa saat status indikator <b>Ditolak</b>.
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="fw-semibold">Item Checklist</div>
          <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddChecklistRow">
            <i class="ph-plus me-1"></i> Tambah Baris
          </button>
        </div>

        <div id="checklistRows" class="d-flex flex-column gap-2"></div>

        <div class="text-muted fs-sm mt-2">
          Tombol “Hapus” akan menonaktifkan item (active=0). Item baru akan hilang jika dihapus sebelum disimpan.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
        <button type="submit" class="btn btn-primary" id="btnSaveChecklist">
          <i class="ph-floppy-disk me-1"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>

{{-- ================== MODAL: EDIT FED AUDITOR (DENGAN BUKTI URL) ================== --}}
<div class="modal fade" id="modalEditFedAuditor" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <form method="POST" id="formEditFedAuditor" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title">Isi/Edit Butir Evaluasi Diri (Auditor)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="mb-4">
          <label class="form-label fw-semibold">Ketercapaian Standar</label>
          <div class="d-flex flex-column gap-2">
            @foreach($opsiKetercapaian as $op)
              @php
                $lower = strtolower($op->name);
                $templateType = in_array($lower, ['melampaui', 'mencapai']) ? 'pos' : 'neg';
              @endphp
              <label class="d-flex align-items-center gap-2">
                <input type="radio"
                       name="ketercapaian_standard_id"
                       value="{{ $op->id }}"
                       data-template-type="{{ $templateType }}">
                <span>{{ $op->name }}</span>
              </label>
            @endforeach
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold">Hasil Pelaksanaan</label>
          <textarea name="hasil" id="modal_auditor_hasil" class="form-control summernote-fed"></textarea>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold">Bukti Pendukung (URL)</label>
          <input type="url"
                 name="bukti_pendukung"
                 id="modal_auditor_bukti_url"
                 class="form-control"
                 placeholder="https://drive.google.com/..."
                 autocomplete="off">
          <div class="invalid-feedback">URL tidak valid. Contoh: https://drive.google.com/...</div>
          <div class="form-text">Simpan URL saja. Jangan tempel HTML.</div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Faktor Penghambat / Pendukung</label>
          <textarea name="faktor_penghambat_pendukung" id="modal_auditor_faktor" class="form-control summernote-fed"></textarea>
        </div>

        <p class="text-muted fs-sm mb-0">
          Setelah disimpan, status indikator berubah menjadi <strong>Disetujui</strong>.
        </p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-success">
          <i class="ph-floppy-disk me-1"></i> Simpan & Setujui
        </button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<style>
  .note-editor.note-frame { border: 1px solid #ddd; }
  .note-editing-area { min-height: 150px; }
  .modal-xl { max-width: 1140px; }
  #modalEditFedAuditor .modal-body { max-height: calc(100vh - 200px); overflow-y: auto; }
  .table td { vertical-align: top; }

  /* Summernote dialog di atas Bootstrap modal */
  .note-modal { z-index: 1065 !important; }
  .note-popover { z-index: 1065 !important; }
  .note-toolbar { z-index: 1065; }
  .note-modal-backdrop { z-index: 1064 !important; }
  .note-modal, .note-modal * { pointer-events: auto; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>

<script>
  function safeHtml(html) {
    return html && String(html).trim() !== '' ? html : '<span class="text-muted">-</span>';
  }

  function b64decode(str) {
    if (!str) return '';
    try { return atob(str); } catch (e) { return ''; }
  }

  function b64jsonDecode(str) {
    if (!str) return [];
    try { return JSON.parse(atob(str)); } catch (e) { return []; }
  }

  function normalizeUrl(url) {
    url = (url || '').trim();
    if (!url) return '';
    if (/^https?:\/\//i.test(url)) return url;
    return 'https://' + url;
  }

  function isProbablyUrl(url) {
    url = (url || '').trim();
    if (!url) return true; // allow kosong kalau rules backend ngebolehin
    try { new URL(normalizeUrl(url)); return true; } catch(e) { return false; }
  }

  // Auto-close collapse detail saat buka detail lain
  document.addEventListener('show.bs.collapse', function (e) {
    const target = e.target;
    if (!target.id || !target.id.startsWith('fedDetail_')) return;
    document.querySelectorAll('.collapse[id^="fedDetail_"]').forEach(el => {
      if (el !== target) {
        const bs = bootstrap.Collapse.getInstance(el);
        if (bs) bs.hide();
      }
    });
  });

  // ===== Modal indikator (HTML asli) =====
  (function () {
    const modal = document.getElementById('modalDesc');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      const title = btn?.getAttribute('data-title') || 'Indikator';
      const b64   = btn?.getAttribute('data-desc-html') || '';
      const titleEl = document.getElementById('modalDesc_title');
      const bodyEl  = document.getElementById('modalDesc_body');

      if (titleEl) titleEl.textContent = title;
      if (!bodyEl) return;

      bodyEl.innerHTML = safeHtml(b64decode(b64));
    });
  })();

  // ===== Summernote =====
  const AlphaListButton = function (context) {
    const ui = $.summernote.ui;
    const button = ui.button({
      contents: '<i class="note-icon-unorderedlist"></i> a.',
      tooltip: 'Daftar alfabet (a, b, c)',
      click: function () {
        context.invoke('editor.pasteHTML', '<ol type="a"><li></li></ol><p></p>');
      }
    });
    return button.render();
  };

  let auditorSummernoteInit = false;
  function initAuditorSummernote() {
    if (auditorSummernoteInit) return;
    $('.summernote-fed').summernote({
      height: 180,
      toolbar: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['fontname', ['fontname']],
        ['fontsize', ['fontsize']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['height', ['height']],
        ['table', ['table']],
        ['insert', ['link', 'picture', 'video']],
        ['custom', ['alphaList']],
        ['view', ['fullscreen', 'codeview', 'help']]
      ],
      buttons: { alphaList: AlphaListButton },
      placeholder: 'Tuliskan di sini...',
      tabsize: 2,
      dialogsInBody: true
    });
    auditorSummernoteInit = true;
  }

  // ===== Modal Edit FED Auditor =====
  (function () {
    const modalEdit = document.getElementById('modalEditFedAuditor');
    const formEdit  = document.getElementById('formEditFedAuditor');
    if (!modalEdit || !formEdit) return;

    const buktiInput = document.getElementById('modal_auditor_bukti_url');

    modalEdit.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      if (!btn) return;

      initAuditorSummernote();

      const updateUrl    = btn.getAttribute('data-update-url') || '';
      const ketercapaian = btn.getAttribute('data-ketercapaian') || '';
      const hasilB64     = btn.getAttribute('data-hasil-b64') || '';
      const faktorB64    = btn.getAttribute('data-faktor-b64') || '';
      const buktiUrlB64  = btn.getAttribute('data-bukti-url-b64') || '';
      const posTplB64    = btn.getAttribute('data-pos-template') || '';
      const negTplB64    = btn.getAttribute('data-neg-template') || '';

      formEdit.action = updateUrl;

      modalEdit.dataset.posTemplate = b64decode(posTplB64);
      modalEdit.dataset.negTemplate = b64decode(negTplB64);

      // reset radio
      const radios = modalEdit.querySelectorAll('input[name="ketercapaian_standard_id"]');
      radios.forEach(r => r.checked = false);
      if (ketercapaian) {
        const r = modalEdit.querySelector(`input[name="ketercapaian_standard_id"][value="${ketercapaian}"]`);
        if (r) r.checked = true;
      }

      setTimeout(function () {
        $('#modal_auditor_hasil').summernote('code', b64decode(hasilB64));
        $('#modal_auditor_faktor').summernote('code', b64decode(faktorB64));

        if (buktiInput) {
          buktiInput.value = b64decode(buktiUrlB64);
          buktiInput.classList.remove('is-invalid');
        }
      }, 80);
    });

    modalEdit.addEventListener('hidden.bs.modal', function () {
      formEdit.reset();
      if (auditorSummernoteInit) {
        $('#modal_auditor_hasil').summernote('code', '');
        $('#modal_auditor_faktor').summernote('code', '');
      }
      if (buktiInput) {
        buktiInput.value = '';
        buktiInput.classList.remove('is-invalid');
      }
    });

    modalEdit.addEventListener('change', function (ev) {
      const target = ev.target;
      if (target.name !== 'ketercapaian_standard_id') return;

      const type = target.getAttribute('data-template-type');
      let tpl = '';
      if (type === 'pos') tpl = modalEdit.dataset.posTemplate || '';
      else if (type === 'neg') tpl = modalEdit.dataset.negTemplate || '';

      if (tpl) $('#modal_auditor_hasil').summernote('code', tpl);
    });

    formEdit.addEventListener('submit', function(e) {
      if (!buktiInput) return;

      const val = buktiInput.value || '';
      if (!isProbablyUrl(val)) {
        e.preventDefault();
        buktiInput.classList.add('is-invalid');
        buktiInput.focus();
        return;
      }

      buktiInput.value = normalizeUrl(val);
      buktiInput.classList.remove('is-invalid');
    });

    if (buktiInput) {
      buktiInput.addEventListener('input', function() {
        buktiInput.classList.remove('is-invalid');
      });
    }
  })();

  // ===== Modal Checklist (EDITABLE) =====
  (function () {
    const modal = document.getElementById('modalChecklist');
    const form  = document.getElementById('formChecklist');
    const rows  = document.getElementById('checklistRows');
    const addBtn= document.getElementById('btnAddChecklistRow');
    const hint  = document.getElementById('checklistHint');

    if (!modal || !form || !rows || !addBtn || !hint) return;

    let rowIndex = 0;

    function esc(str) {
      return (str ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function rowTemplate(idx, data = {}) {
      const id   = data.id || '';
      const item = data.item || '';
      const note = data.note || '';

      return `
        <div class="border rounded p-2" data-index="${idx}">
          <div class="d-flex gap-2 align-items-start">
            <div class="flex-fill">
              <input type="hidden" name="items[${idx}][id]" value="${esc(id)}">
              <input type="hidden" name="items[${idx}][delete]" value="0">

              <label class="form-label fw-semibold mb-1">Item</label>
              <input class="form-control form-control-sm"
                name="items[${idx}][item]"
                value="${esc(item)}"
                placeholder="Item yang dicek (wajib)" required>

              <label class="form-label fw-semibold mt-2 mb-1">Catatan (opsional)</label>
              <textarea class="form-control form-control-sm"
                name="items[${idx}][note]" rows="2"
                placeholder="Catatan">${esc(note)}</textarea>
            </div>

            <div class="d-flex flex-column gap-2">
              <button type="button" class="btn btn-sm btn-outline-danger btnRemoveRow">
                <i class="ph-trash me-1"></i> Hapus
              </button>
            </div>
          </div>
        </div>
      `;
    }

    function addRow(data = {}) {
      const idx = rowIndex++;
      const wrap = document.createElement('div');
      wrap.innerHTML = rowTemplate(idx, data);

      const node = wrap.firstElementChild;
      const btnRemove = node.querySelector('.btnRemoveRow');

      btnRemove.addEventListener('click', () => {
        const idInput  = node.querySelector(`input[name="items[${idx}][id]"]`);
        const delInput = node.querySelector(`input[name="items[${idx}][delete]"]`);
        const itemInp  = node.querySelector(`input[name="items[${idx}][item]"]`);
        const noteInp  = node.querySelector(`textarea[name="items[${idx}][note]"]`);

        if (idInput && idInput.value) {
          delInput.value = "1";
          node.style.opacity = "0.5";
          itemInp.disabled = true;
          noteInp.disabled = true;
          btnRemove.disabled = true;
          btnRemove.textContent = "Ditandai hapus";
        } else {
          node.remove();
        }
      });

      rows.appendChild(node);
    }

    function resetRows(list = []) {
      rows.innerHTML = '';
      rowIndex = 0;
      if (list.length) list.forEach(x => addRow(x));
      else addRow();
    }

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      if (!btn) return;

      form.action = btn.getAttribute('data-action') || '';

      const existingB64 = btn.getAttribute('data-existing-json-b64') || '';
      const list = b64jsonDecode(existingB64);

      hint.classList.remove('alert-warning','alert-danger');
      hint.classList.add('alert-info');
      hint.innerHTML = `Buat/ubah daftar tilik. Hanya bisa saat status indikator <b>Ditolak</b>.`;

      resetRows(list);
    });

    modal.addEventListener('hidden.bs.modal', function () {
      form.action = '';
      resetRows([]);
    });

    addBtn.addEventListener('click', () => addRow());
  })();
</script>
@endpush
