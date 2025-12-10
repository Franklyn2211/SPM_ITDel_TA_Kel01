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

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center gap-2">
        <a href="{{ route('auditor.dashboard') }}" class="btn btn-light btn-sm rounded-pill">
          <i class="ph-arrow-left me-2"></i> Kembali ke Dashboard
        </a>
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

        @php
          $statusName = $form->status->name ?? 'Draft';
          $badgeClass = match($statusName) {
              'Disetujui' => 'bg-success',
              'Dikirim'   => 'bg-info',
              'Ditolak'   => 'bg-danger',
              default     => 'bg-secondary',
          };
        @endphp

        <span class="badge {{ $badgeClass }} rounded-pill">{{ $statusName }}</span>
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
          <span class="badge bg-secondary">Total: {{ $total }}</span>
          <span class="badge bg-success">Disetujui: {{ $approved }}</span>
          <span class="badge bg-danger">Ditolak: {{ $rejected }}</span>
          <span class="badge bg-info">Menunggu: {{ $pending }}</span>
        </div>
      </div>
    </div>
  </div>

  {{-- Tabel indikator --}}
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
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
      @php
        $filteredDetails = $form->details;
        $filterStatus = request('status');
        if($filterStatus) {
          $filteredDetails = $filteredDetails->filter(fn($d) => ($d->status->name ?? 'Draft') === $filterStatus);
        }
      @endphp
      <table class="table table-hover align-middle mb-0" id="tableFedAuditor">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width: 40px;">No</th>
            <th style="min-width: 260px;">Standar & Indikator</th>
            <th style="min-width: 320px;">Isi FED (Auditee)</th>
            <th style="width: 120px;" class="text-center">Status</th>
            <th style="width: 320px;">Aksi Auditor</th>
          </tr>
        </thead>
        <tbody>
          @forelse($filteredDetails as $detail)
            @php
              $statusNameDetail = $detail->status->name ?? 'Draft';
              $badgeClassDetail = match($statusNameDetail) {
                  'Disetujui' => 'bg-success',
                  'Ditolak'   => 'bg-danger',
                  'Dikirim'   => 'bg-info',
                  default     => 'bg-secondary',
              };

              $stdName = optional($detail->indicator->standard)->name ?? 'Standar';
              $rawDesc = $detail->indicator->description ?? '';
              $plainDesc = trim(preg_replace('/\s+/', ' ', strip_tags($rawDesc)));
              $shortDesc = \Illuminate\Support\Str::limit($plainDesc, 150);
            @endphp

            <tr id="detail-{{ $detail->id }}">
              <td class="text-center align-top">{{ $loop->iteration }}</td>

              {{-- Kolom standar & indikator (nama standar + ringkasan indikator) --}}
              <td class="align-top td-standar">
                <div class="fw-semibold mb-1">{{ $stdName }}</div>
                <div class="text-muted fs-sm">
                  {!! e($shortDesc) !!}
                </div>
                @if(strlen($plainDesc) > strlen($shortDesc))
                  <button type="button"
                          class="btn btn-link btn-xs p-0 mt-1"
                          data-bs-toggle="modal"
                          data-bs-target="#modalDesc"
                          data-title="{{ $stdName }}"
                          data-desc-html="{{ base64_encode($rawDesc) }}">
                    Lihat deskripsi lengkap
                  </button>
                @endif
              </td>

              {{-- Kolom isi FED auditee --}}
              <td class="align-top">
                <div class="fw-semibold fs-sm mb-1">Ketercapaian Standar</div>
                <div class="mb-2">
                  @if($detail->standardAchievement)
                    <span class="badge bg-primary">{{ $detail->standardAchievement->name }}</span>
                  @else
                    <span class="text-muted">Belum diisi.</span>
                  @endif
                </div>

                <div class="fw-semibold fs-sm mb-1">Hasil Pelaksanaan</div>
                <div class="border rounded p-2 bg-light mb-2 fs-sm" style="max-height: 160px; overflow:auto;">
                  @if($detail->result)
                    {!! $detail->result !!}
                  @else
                    <span class="text-muted">Belum diisi.</span>
                  @endif
                </div>

                @if($detail->supporting_evidence)
                  <div class="fw-semibold fs-sm mb-1">Bukti / Dokumen Pendukung</div>
                  <div class="border rounded p-2 bg-light mb-2 fs-sm" style="max-height: 140px; overflow:auto;">
                    {!! nl2br(e($detail->supporting_evidence)) !!}
                  </div>
                @endif

                @if($detail->contributing_factors)
                  <div class="fw-semibold fs-sm mb-1">Faktor Penghambat / Pendukung</div>
                  <div class="border rounded p-2 bg-light fs-sm" style="max-height: 140px; overflow:auto;">
                    {!! nl2br(e($detail->contributing_factors)) !!}
                  </div>
                @endif
              </td>

              {{-- Status --}}
              <td class="text-center align-top">
                <span class="badge {{ $badgeClassDetail }}">{{ $statusNameDetail }}</span>
              </td>

              {{-- Aksi --}}
              <td class="align-top">
                {{-- ========== STATUS: DIKIRIM -> boleh Terima/Tolak ========== --}}
                @if($statusNameDetail === 'Dikirim')
                  <div class="d-flex flex-wrap gap-1 mb-2">
                    <form method="POST"
                          action="{{ route('auditor.fed.details.approve', [$form->id, $detail->id]) }}">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-success">
                        Terima
                      </button>
                    </form>

                    <form method="POST"
                          action="{{ route('auditor.fed.details.reject', [$form->id, $detail->id]) }}">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-danger">
                        Tolak
                      </button>
                    </form>
                  </div>
                  <div class="small text-muted">
                    Terima jika isi sudah sesuai. Tolak jika perlu koreksi dan tindak lanjut.
                  </div>

                {{-- ========== STATUS: DITOLAK -> checklist + popup edit FED ========== --}}
                @elseif($statusNameDetail === 'Ditolak')
                  <div class="mb-2">
                    <span class="text-danger fw-semibold fs-sm">Indikator ditolak, perlu tindak lanjut.</span>
                  </div>

                  {{-- Daftar Tilik --}}
                  <div class="border rounded p-2 mb-2 bg-light">
                    <div class="fw-semibold fs-sm mb-1">Daftar Tilik Auditor</div>
                    <ul class="mb-2 fs-sm">
                      @forelse($detail->auditChecklists as $cl)
                        <li>
                          <strong>{{ $cl->item }}</strong>
                          @if($cl->note)
                            <br><span class="text-muted">{{ $cl->note }}</span>
                          @endif
                          <form method="POST"
                                action="{{ route('auditor.checklists.destroy', $cl->id) }}"
                                class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="btn btn-xs btn-outline-danger ms-1">Hapus</button>
                          </form>
                        </li>
                      @empty
                        <li class="text-muted">Belum ada daftar tilik.</li>
                      @endforelse
                    </ul>

                    <form method="POST" action="{{ route('auditor.checklists.store', $detail->id) }}">
                      @csrf
                      <input type="text"
                             name="item"
                             class="form-control form-control-sm mb-1"
                             placeholder="Item pertanyaan / hal yang dicek"
                             required>
                      <textarea name="note"
                                rows="2"
                                class="form-control form-control-sm mb-1"
                                placeholder="Catatan (opsional)"></textarea>
                      <button class="btn btn-xs btn-primary">Tambah Daftar Tilik</button>
                    </form>
                  </div>

                  {{-- Tombol popup edit FED ala auditee --}}
                  <button type="button"
                          class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditFedAuditor"
                          data-update-url="{{ route('auditor.fed.details.update', [$form->id, $detail->id]) }}"
                          data-ketercapaian="{{ $detail->standard_achievement_id ?? '' }}"
                          data-hasil="{{ e($detail->result ?? '') }}"
                          data-bukti="{{ e($detail->supporting_evidence ?? '') }}"
                          data-faktor="{{ e($detail->contributing_factors ?? '') }}"
                          data-pos-template="{{ e($detail->indicator->positive_result_template ?? '') }}"
                          data-neg-template="{{ e($detail->indicator->negative_result_template ?? '') }}">
                    Isi/Edit FED (hasil akhir)
                  </button>

                  <div class="mt-1 small text-muted">
                    Setelah disimpan, indikator akan otomatis <strong>Disetujui</strong>.
                  </div>

                {{-- ========== STATUS LAIN (Disetujui / Draft / apapun) -> read only ========== --}}
                @else
                  <div class="small text-muted">
                    Tidak ada aksi. Indikator sudah {{ strtolower($statusNameDetail) }}.
                  </div>
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
    </div>
  </div>
</div>

{{-- ================== MODAL: DESKRIPSI LENGKAP INDIKATOR ================== --}}
<div class="modal fade" id="modalDesc" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDesc_title">Deskripsi Indikator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div id="modalDesc_body" class="mb-0" style="white-space: normal;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

{{-- ================== MODAL: ISI/EDIT FED AUDITOR ================== --}}
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
        {{-- Ketercapaian Standar --}}
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
                       id="ketercapaian_auditor_{{ $op->id }}"
                       data-template-type="{{ $templateType }}">
                <span>{{ $op->name }}</span>
              </label>
            @endforeach
          </div>
        </div>

        {{-- Hasil Pelaksanaan --}}
        <div class="mb-4">
          <label class="form-label fw-semibold">Hasil Pelaksanaan</label>
          <textarea name="hasil" id="modal_auditor_hasil" class="form-control summernote-fed"></textarea>
        </div>

        {{-- Bukti / Dokumen Pendukung --}}
        <div class="mb-4">
          <label class="form-label fw-semibold">Bukti / Dokumen Pendukung</label>
          <textarea name="bukti_pendukung" id="modal_auditor_bukti" class="form-control summernote-fed"></textarea>
        </div>

        {{-- Faktor Penghambat / Pendukung --}}
        <div class="mb-3">
          <label class="form-label fw-semibold">Faktor Penghambat / Pendukung</label>
          <textarea name="faktor_penghambat_pendukung" id="modal_auditor_faktor" class="form-control summernote-fed"></textarea>
        </div>

        <p class="text-muted fs-sm mb-0">
          Setelah disimpan, status indikator akan berubah menjadi <strong>Disetujui</strong> dan tidak dapat diubah lagi dari halaman ini.
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
  #modalEditFedAuditor .modal-body { max-height: calc(100vh - 200px); overflow-y: auto; }
  .td-standar { white-space: normal; word-wrap: break-word; }
  .td-standar ol, .td-standar ul { margin-bottom: 0.5rem; padding-left: 1.3rem; }
  .td-standar p { margin-bottom: .4rem; }
  .td-standar p:last-child { margin-bottom: 0; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script>
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
        ['view', ['fullscreen', 'codeview', 'help']]
      ],
      placeholder: 'Tuliskan di sini...',
      tabsize: 2,
      dialogsInBody: true
    });
    auditorSummernoteInit = true;
  }

  function stripHtmlToPlainText(html) {
    const txt = document.createElement('textarea');
    txt.innerHTML = html || '';
    const decoded = txt.value;
    const div = document.createElement('div');
    div.innerHTML = decoded;
    return (div.textContent || div.innerText || '').trim();
  }

  (function () {
    const modalEdit = document.getElementById('modalEditFedAuditor');
    const formEdit  = document.getElementById('formEditFedAuditor');
    if (!modalEdit || !formEdit) return;

    initAuditorSummernote();

    modalEdit.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      if (!btn) return;

      const updateUrl    = btn.getAttribute('data-update-url') || '';
      const ketercapaian = btn.getAttribute('data-ketercapaian') || '';
      const hasil        = btn.getAttribute('data-hasil') || '';
      const bukti        = btn.getAttribute('data-bukti') || '';
      const faktor       = btn.getAttribute('data-faktor') || '';
      const posTemplate  = btn.getAttribute('data-pos-template') || '';
      const negTemplate  = btn.getAttribute('data-neg-template') || '';

      formEdit.action = updateUrl;

      modalEdit.dataset.posTemplate = posTemplate;
      modalEdit.dataset.negTemplate = negTemplate;

      // radio
      const radios = modalEdit.querySelectorAll('input[name="ketercapaian_standard_id"]');
      radios.forEach(r => r.checked = false);
      if (ketercapaian) {
        const r = modalEdit.querySelector(
          `input[name="ketercapaian_standard_id"][value="${ketercapaian}"]`
        );
        if (r) r.checked = true;
      }

      // isi summernote
      setTimeout(function () {
        $('#modal_auditor_hasil').summernote(
          'code',
          $('<p/>').text(stripHtmlToPlainText(hasil)).html()
        );
        $('#modal_auditor_bukti').summernote(
          'code',
          $('<p/>').text(stripHtmlToPlainText(bukti)).html()
        );
        $('#modal_auditor_faktor').summernote(
          'code',
          $('<p/>').text(stripHtmlToPlainText(faktor)).html()
        );
      }, 80);
    });

    modalEdit.addEventListener('hidden.bs.modal', function () {
      formEdit.reset();
      if (auditorSummernoteInit) {
        $('#modal_auditor_hasil').summernote('code', '');
        $('#modal_auditor_bukti').summernote('code', '');
        $('#modal_auditor_faktor').summernote('code', '');
      }
    });

    // auto template ketika radio ketercapaian diganti
    modalEdit.addEventListener('change', function (ev) {
      const target = ev.target;
      if (target.name !== 'ketercapaian_standard_id') return;

      const type = target.getAttribute('data-template-type');
      let tpl = '';
      if (type === 'pos') tpl = modalEdit.dataset.posTemplate || '';
      else if (type === 'neg') tpl = modalEdit.dataset.negTemplate || '';

      if (tpl) {
        $('#modal_auditor_hasil').summernote(
          'code',
          $('<p/>').text(stripHtmlToPlainText(tpl)).html()
        );
      }
    });
  })();

  // Modal deskripsi lengkap indikator
  (function () {
    const modal = document.getElementById('modalDesc');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      const title = btn?.getAttribute('data-title') || 'Deskripsi Indikator';
      const b64   = btn?.getAttribute('data-desc-html') || '';
      const titleEl = document.getElementById('modalDesc_title');
      const bodyEl  = document.getElementById('modalDesc_body');

      if (titleEl) titleEl.textContent = title;
      if (!bodyEl) return;

      try {
        bodyEl.innerHTML = b64 ? atob(b64) : '';
      } catch (e) {
        bodyEl.textContent = '';
      }
    });
  })();
</script>
@endpush
