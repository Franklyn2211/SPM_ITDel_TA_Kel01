@extends('auditee.layouts.app')

@section('title', 'Form Audit Tindak Lanjut')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h4 class="page-title mb-0">
        Audit Tindak Lanjut - <span class="fw-normal">{{ $unitName ?? 'Unit/Prodi' }}</span>
      </h4>

      <a href="#page_header"
         class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
         data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center gap-2">
        @php
          $isFinal = (($atl->status ?? '') === 'Final');
          $badge = $isFinal ? 'bg-success' : 'bg-secondary';
        @endphp

        <span class="badge {{ $badge }} rounded-pill">
          Status ATL: {{ $atl->status ?? 'Draft' }}
        </span>

        @if($isFinal)
          <span class="badge bg-success rounded-pill"><i class="ph-lock me-1"></i> Terkunci</span>
            <a class="btn btn-primary btn-sm rounded-pill"
                 href="{{ route('auditee.atl.exportDocx', $atl->id) }}">
                <i class="ph-download-simple me-1"></i> Unduh DOCX
            </a>
            <a class="btn btn-primary btn-sm rounded-pill"
                 href="{{ route('auditee.atl.exportPdf', $atl->id) }}">
                <i class="ph-download-simple me-1"></i> Unduh PDF
            </a>
        @endif
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('auditee.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <a href="{{ route('auditee.atl.index') }}" class="breadcrumb-item">ATL</a>
        <span class="breadcrumb-item active">Detail</span>
      </div>

      <div class="ms-auto d-flex align-items-center text-muted gap-3">
        @if(!empty($academicText))
          <div><i class="ph-calendar me-1"></i> {{ $academicText }}</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">

  @foreach (['success','info','warning','error'] as $f)
    @if (session($f))
      @php
        $cls  = $f === 'success' ? 'success' : ($f === 'warning' ? 'warning' : ($f === 'error' ? 'danger' : 'info'));
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
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
          </ul>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Ringkasan --}}
  <div class="card mb-3">
    <div class="card-body row g-3 align-items-center">
      <div class="col-md-4">
        <div class="text-muted fs-sm">Tahun Akademik</div>
        <div class="fw-semibold">{{ $academicText ?? '-' }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted fs-sm">Unit / Prodi</div>
        <div class="fw-semibold">{{ $unitName ?? '-' }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted fs-sm">Yang Anda isi</div>
        <div class="text-muted fs-sm mt-1">
          <b>Realisasi</b> dan <b>Efektivitas</b>.
          @if($isFinal) <span class="text-danger">Form sudah Final, tidak bisa edit.</span> @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Tabel --}}
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h5 class="mb-0">Daftar Tindak Lanjut</h5>
      @if($isFinal)
        <span class="badge bg-success rounded-pill"><i class="ph-lock me-1"></i> Read-only</span>
      @endif
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-top mb-0">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width:60px;">No</th>
            <th style="min-width:420px;">Standar & Butir Mutu</th>
            <th style="min-width:320px;">Deskripsi Kondisi (FED)</th>
            <th style="width:170px;" class="text-center">Kategori Temuan</th>
            <th style="min-width:260px;">Rencana Tindak Lanjut (Temuan)</th>
            <th style="width:140px;" class="text-center">Jadwal</th>

            <th style="min-width:260px;">Realisasi (Anda)</th>
            <th style="min-width:160px;">Efektivitas (Anda)</th>
            <th style="min-width:180px;">Status (Auditor)</th>

            <th style="width:180px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        @php $start = ($details->currentPage() - 1) * $details->perPage(); @endphp
        @forelse($details as $i => $d)
          @php
            $finding = $d->finding;
            $detailFed = $finding?->selfEvaluationDetail;
            $indicator = $detailFed?->indicator;
            $standard = $indicator?->standard;

            $stdName = $standard?->name ?? 'Standar';
            $rawIndHtml = $indicator?->description ?? '';
            $plainInd = trim(preg_replace('/\s+/', ' ', strip_tags($rawIndHtml)));
            $shortInd = \Illuminate\Support\Str::limit($plainInd, 220);

            $fedResult = $detailFed?->result ?? '';
            $sev = $finding?->severity;
            $severityLabel = $sev ? ($severityOptions[$sev] ?? $sev) : '—';

            $planHtml = $finding?->corrective_action_plan ?? $finding?->follow_up_plan ?? '';
            $jadwal = $finding?->due_date ? \Carbon\Carbon::parse($finding->due_date)->format('d/m/Y') : '—';

            $complete = trim((string)$d->follow_up_realization) !== '' && trim((string)$d->effectiveness) !== '';
            $badgeRow = $complete ? 'bg-success' : 'bg-secondary';
            $statusText = $complete ? 'Lengkap' : 'Draft';
          @endphp

          <tr>
            <td class="text-center">{{ $start + $i + 1 }}</td>

            <td>
              <div class="fw-semibold">{{ $stdName }}</div>
              <div class="text-muted fs-sm mt-1">{{ $shortInd ?: '-' }}</div>
            </td>

            <td class="fs-sm">
              <div class="border rounded p-2" style="white-space: normal;">
                {!! $fedResult ?: '<span class="text-muted">-</span>' !!}
              </div>
            </td>

            <td class="text-center">
              <span class="badge {{ $sev ? 'bg-danger' : 'bg-secondary' }} rounded-pill">{{ $severityLabel }}</span>
            </td>

            <td class="fs-sm">
              <div class="border rounded p-2" style="white-space: normal;">
                {!! $planHtml ?: '<span class="text-muted">-</span>' !!}
              </div>
            </td>

            <td class="text-center">{{ $jadwal }}</td>

            <td class="fs-sm">
              <div class="border rounded p-2" style="white-space: normal;">
                {!! $d->follow_up_realization ?: '<span class="text-muted">-</span>' !!}
              </div>
            </td>

            <td class="fs-sm">
              <div class="d-flex align-items-center gap-2">
                <span>{{ $d->effectiveness ?: '—' }}</span>
                <span class="badge {{ $badgeRow }} rounded-pill">{{ $statusText }}</span>
              </div>
            </td>

            <td class="fs-sm">
              <span class="fw-semibold">{{ $d->status ?: '—' }}</span>
              @if(!empty($d->status_description))
                <div class="text-muted" style="white-space: pre-line;">{{ $d->status_description }}</div>
              @endif
            </td>

            <td>
              @if($isFinal)
                <div class="text-muted fs-sm">Terkunci.</div>
              @else
                <button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="modal" data-bs-target="#modalEditRowAuditee"
                        data-update-url="{{ route('auditee.atl.row.update', [$atl->id, $d->id]) }}"
                        data-realisasi-b64="{{ base64_encode($d->follow_up_realization ?? '') }}"
                        data-efektivitas="{{ e($d->effectiveness ?? '') }}"
                >
                  <i class="ph-pencil-simple me-1"></i> Isi/Edit
                </button>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="text-center text-muted py-4">Tidak ada detail ATL.</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer border-top-0">
      {{ $details->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
  </div>

</div>

{{-- MODAL: EDIT ROW (AUDITEE) --}}
@if(!$isFinal)
<div class="modal fade" id="modalEditRowAuditee" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" id="formEditRowAuditee" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title">Isi Realisasi & Efektivitas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Realisasi Tindak Lanjut</label>
          <textarea name="follow_up_realization" id="edit_realisasi" class="form-control summernote-realisasi" rows="5"
                    placeholder="Tuliskan realisasi tindak lanjut..."></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Efektivitas</label>
          <input type="text" name="effectiveness" id="edit_efektivitas" class="form-control"
                 placeholder="Contoh: 50%">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
        <button type="submit" class="btn btn-primary">
          <i class="ph-floppy-disk me-1"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script>
  function b64decode(str) {
    if (!str) return '';
    try { return atob(str); } catch (e) { return ''; }
  }

  @if(!$isFinal)
  let snRealisasiInit = false;
  function initSummernoteRealisasi() {
    if (snRealisasiInit) return;
    $('.summernote-realisasi').summernote({
      placeholder: 'Tuliskan realisasi tindak lanjut...',
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
      dialogsInBody: true,
      disableDragAndDrop: false
    });
    snRealisasiInit = true;
  }

  (function () {
    const modal = document.getElementById('modalEditRowAuditee');
    const form  = document.getElementById('formEditRowAuditee');
    if (!modal || !form) return;

    const editRealisasi = document.getElementById('edit_realisasi');
    const editEfektivitas = document.getElementById('edit_efektivitas');

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      if (!btn) return;

      initSummernoteRealisasi();
      form.action = btn.getAttribute('data-update-url') || '';
      $('.summernote-realisasi').summernote('code', b64decode(btn.getAttribute('data-realisasi-b64')) || '');
      editEfektivitas.value = btn.getAttribute('data-efektivitas') || '';
    });

    modal.addEventListener('hidden.bs.modal', function () {
      form.action = '';
      if (snRealisasiInit) $('.summernote-realisasi').summernote('code', '');
      editEfektivitas.value = '';
    });
  })();
  @endif
</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
@endpush
