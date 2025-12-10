@extends('auditor.layouts.app')

@section('title', 'FED untuk Diaudit')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h3 class="page-title mb-0 fw-bold">
        Daftar FED untuk Diaudit
      </h3>
    </div>
  </div>
  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <span class="breadcrumb-item"><i class="ph-house"></i></span>
        <span class="breadcrumb-item active">FED untuk Diaudit</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="card">
  <div class="card-header d-flex align-items-center">
    <h5 class="mb-0">Daftar FED (Status: Dikirim)</h5>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>Unit / Prodi</th>
          <th>Tahun Akademik</th>
          <th>Status Form</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($forms as $i => $form)
          <tr>
            <td>{{ $i + 1 }}</td>
            <td>
              <div class="fw-semibold">{{ $form->categoryDetail->name ?? '-' }}</div>
              <div class="text-muted fs-sm">Kode: {{ $form->categoryDetail->code ?? '—' }}</div>
            </td>
            <td>{{ $form->academicConfig->name ?? $form->academicConfig->tahun ?? '—' }}</td>
            <td><span class="badge bg-info">{{ $form->status->name ?? 'Dikirim' }}</span></td>
            <td class="text-end">
              <a href="{{ route('auditor.fed.show', $form->id) }}" class="btn btn-sm btn-outline-primary">
                <i class="ph-eye me-1"></i> Lihat FED
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-4">
              Belum ada FED dengan status <strong>Dikirim</strong>.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
