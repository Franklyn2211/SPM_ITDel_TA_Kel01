@extends('admin.layouts.app')

@section('title', 'Rekap Daftar Tilik')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h3 class="page-title mb-0 fw-bold">Rekap Daftar Tilik (Read-only)</h3>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <span class="breadcrumb-item"><i class="ph-house"></i></span>
        <span class="breadcrumb-item active">Rekap Daftar Tilik</span>
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

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h5 class="mb-0">Daftar FED (Unit/Prodi)</h5>
      <span class="text-muted fs-sm">Admin hanya melihat, tidak bisa edit.</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:60px;" class="text-center">#</th>
            <th>Unit / Prodi</th>
            <th style="width:220px;">Tahun Akademik</th>
            <th style="width:160px;" class="text-center">Checklist Ada?</th>
            <th class="text-end" style="width:180px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($feds as $i => $fed)
            @php
              $n = ($feds->firstItem() ?? 1) + $i;
              $has = ($fed->checklist_count ?? 0) > 0;
            @endphp
            <tr>
              <td class="text-center">{{ $n }}</td>
              <td>
                <div class="fw-semibold">{{ $fed->categoryDetail->name ?? '-' }}</div>
              </td>
              <td>{{ $fed->academicConfig->name ?? $fed->academicConfig->tahun ?? '—' }}</td>
              <td class="text-center">
                <span class="badge {{ $has ? 'bg-success' : 'bg-secondary' }} rounded-pill">
                  {{ $has ? 'Ada' : 'Tidak' }}
                </span>
              </td>
              <td class="text-end">
                <a href="{{ route('admin.audit_checklists.show', $fed->id) }}"
                   class="btn btn-sm btn-outline-primary">
                  <i class="ph-eye me-1"></i> Lihat
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">Belum ada FED.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($feds, 'links'))
      <div class="card-body">
        {{ $feds->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
