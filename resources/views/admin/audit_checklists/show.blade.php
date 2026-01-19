@extends('admin.layouts.app')

@section('title', 'Detail Daftar Tilik')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h4 class="page-title mb-0">
        Detail Daftar Tilik - <span class="fw-normal">{{ $form->categoryDetail->name ?? 'Unit/Prodi' }}</span>
      </h4>

      <a href="#page_header"
         class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
         data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center gap-2">
        <span class="badge bg-secondary rounded-pill">
          Read-only
        </span>
        <a href="{{ route('admin.audit_checklists.index') }}" class="btn btn-outline-primary btn-sm rounded-pill">
          <i class="ph-arrow-left me-1"></i> Kembali
        </a>
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <a href="{{ route('admin.audit_checklists.index') }}" class="breadcrumb-item">Rekap Daftar Tilik</a>
        <span class="breadcrumb-item active">Detail</span>
      </div>

      <div class="ms-auto d-flex align-items-center text-muted gap-3">
        @if($form->academicConfig)
          <div><i class="ph-calendar me-1"></i> {{ $form->academicConfig->name ?? $form->academicConfig->tahun }}</div>
        @endif
        <div><i class="ph-list-checks me-1"></i> Total Checklist: <b>{{ $totalChecklist ?? 0 }}</b></div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">

  <div class="card mb-3">
    <div class="card-body row g-3">
      <div class="col-md-6">
        <div class="text-muted fs-sm">Unit / Prodi</div>
        <div class="fw-semibold">{{ $form->categoryDetail->name ?? '-' }}</div>
      </div>
      <div class="col-md-6">
        <div class="text-muted fs-sm">Tahun Akademik</div>
        <div class="fw-semibold">{{ $form->academicConfig->name ?? $form->academicConfig->tahun ?? '-' }}</div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Daftar Indikator & Daftar Tilik (Auditor)</h5>
      <div class="text-muted fs-sm mt-1">Admin hanya bisa melihat checklist yang aktif (active=1).</div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-top mb-0">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width:60px;">No</th>
            <th style="min-width:360px;">Standar & Indikator</th>
            <th style="min-width:520px;">Daftar Tilik (Read-only)</th>
            <th class="text-center" style="width:140px;">Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($details as $d)
            @php
              $std = $d->indicator?->standard?->name ?? 'Standar';
              $raw = $d->indicator?->description ?? '';
              $plain = trim(preg_replace('/\s+/', ' ', strip_tags($raw)));
              $short = \Illuminate\Support\Str::limit($plain, 180);

              $st = $d->status?->name ?? 'Draft';
              $badge = match($st) {
                'Disetujui' => 'bg-success',
                'Ditolak'   => 'bg-danger',
                'Dikirim'   => 'bg-info',
                default     => 'bg-secondary',
              };

              $cls = $d->auditChecklists ?? collect();
            @endphp
            <tr>
              <td class="text-center">{{ $loop->iteration }}</td>
              <td>
                <div class="fw-semibold">{{ $std }}</div>
                <div class="text-muted fs-sm">{{ $short ?: '-' }}</div>

                <button type="button"
                        class="btn btn-link p-0 fs-sm mt-1"
                        data-bs-toggle="modal"
                        data-bs-target="#modalDesc"
                        data-title="{{ e($std) }}"
                        data-desc-html="{{ base64_encode($raw) }}">
                  Lihat indikator lengkap
                </button>
              </td>

              <td>
                @if($cls->count() > 0)
                  <ol class="mb-0 ps-3">
                    @foreach($cls as $c)
                      <li class="mb-2">
                        <div class="fw-semibold">{{ $c->item }}</div>
                        @if($c->note)
                          <div class="text-muted fs-sm">{{ $c->note }}</div>
                        @endif
                      </li>
                    @endforeach
                  </ol>
                @else
                  <span class="text-muted">Belum ada daftar tilik.</span>
                @endif
              </td>

              <td class="text-center">
                <span class="badge {{ $badge }} rounded-pill">{{ $st }}</span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted py-4">Tidak ada detail indikator.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

{{-- MODAL indikator HTML --}}
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
@endsection

@push('scripts')
<script>
  function b64decode(str) {
    if (!str) return '';
    try { return atob(str); } catch (e) { return ''; }
  }
  function safeHtml(html) {
    return html && String(html).trim() !== '' ? html : '<span class="text-muted">-</span>';
  }

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
</script>
@endpush
