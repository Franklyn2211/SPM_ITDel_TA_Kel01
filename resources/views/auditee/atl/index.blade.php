@extends('auditee.layouts.app')

@section('title', 'Audit Tindak Lanjut')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h3 class="page-title mb-0 fw-bold">Audit Tindak Lanjut (ATL)</h3>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <span class="breadcrumb-item"><i class="ph-house"></i></span>
        <span class="breadcrumb-item active">ATL</span>
      </div>

      <div class="ms-auto">
        <form method="GET" class="d-flex align-items-center gap-2">
          <input type="text" class="form-control form-control-sm" name="q"
                 value="{{ $q ?? '' }}" placeholder="Cari Unit/Prodi...">
          <button class="btn btn-sm btn-outline-primary">
            <i class="ph-magnifying-glass me-1"></i> Cari
          </button>
        </form>
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

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h5 class="mb-0">Daftar ATL</h5>
      <div class="text-muted fs-sm">Auditee mengisi: <b>Realisasi</b> dan <b>Efektivitas</b>.</div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:60px;">#</th>
            <th>Unit / Prodi</th>
            <th style="width:220px;">Tahun Akademik</th>
            <th style="width:140px;">Status ATL</th>
            <th class="text-end" style="width:180px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        @php $start = ($atls->currentPage() - 1) * $atls->perPage(); @endphp
        @forelse($atls as $i => $atl)
          @php
            $findingForm = $atl->findingForm ?? null;
            $fed  = $findingForm?->selfEvaluationForm;
            $unit = $fed?->categoryDetail?->name ?? $atl->area ?? '-';
            $acad = $fed?->academicConfig?->name ?? $fed?->academicConfig?->tahun ?? '—';
            $isFinal = (($atl->status ?? '') === 'Final');
          @endphp
          <tr>
            <td>{{ $start + $i + 1 }}</td>
            <td>
              <div class="fw-semibold">{{ $unit }}</div>
            </td>
            <td>{{ $acad }}</td>
            <td>
              <span class="badge {{ $isFinal ? 'bg-success' : 'bg-secondary' }}">
                {{ $atl->status ?? 'Draft' }}
              </span>
            </td>
            <td class="text-end">
              <a href="{{ route('auditee.atl.show', $atl->id) }}" class="btn btn-sm btn-outline-primary">
                <i class="ph-note-pencil me-1"></i> Lihat / Isi
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-4">
              Belum ada ATL yang tersedia untuk Anda.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer border-top-0">
      {{ $atls->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
  </div>

</div>
@endsection
