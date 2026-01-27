{{-- resources/views/auditor/fed/index.blade.php --}}
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
    <h5 class="mb-0">Daftar FED</h5>
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
        @php $start = ($forms->currentPage() - 1) * $forms->perPage(); @endphp
        @forelse($forms as $i => $form)
          @php
            $details = $form->details ?? collect();
            $total = $details->count();
            $approved = $details->filter(fn($d) => ($d->status->name ?? '') === 'Disetujui')->count();
            $rejected = $details->filter(fn($d) => ($d->status->name ?? '') === 'Ditolak')->count();
            $pending = $details->filter(fn($d) => ($d->status->name ?? '') === 'Dikirim')->count();

            // Status logic
            if ($total === 0) {
              $statusText = 'Dikirim';
              $statusClass = 'bg-info';
            } elseif ($approved === $total) {
              $statusText = 'Disetujui';
              $statusClass = 'bg-success';
            } elseif ($rejected === $total) {
              $statusText = 'Ditolak';
              $statusClass = 'bg-danger';
            } elseif ($pending === $total) {
              $statusText = 'Dikirim';
              $statusClass = 'bg-info';
            } elseif ($approved > 0 && $approved + $pending === $total) {
              $statusText = 'Draft';
              $statusClass = 'bg-secondary';
            } elseif ($rejected > 0 && $rejected < $total) {
              $statusText = 'Draft';
              $statusClass = 'bg-secondary';
            } else {
              $statusText = 'Draft';
              $statusClass = 'bg-secondary';
            }
          @endphp
          <tr>
            <td>{{ $start + $i + 1 }}</td>
            <td>
              <div class="fw-semibold">{{ $form->categoryDetail->name ?? '-' }}</div>
            </td>
            <td>{{ $form->academicConfig->name ?? $form->academicConfig->tahun ?? '—' }}</td>
            <td><span class="badge {{ $statusClass }}">{{ $statusText }}</span></td>
            <td class="text-end">
              <a href="{{ route('auditor.fed.show', $form->id) }}" class="btn btn-sm btn-outline-primary">
                <i class="ph-eye me-1"></i> Lihat FED
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-4">
              Belum ada FED yang dikirim.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer border-top-0">
    {{ $forms->onEachSide(1)->links('pagination::bootstrap-5') }}
  </div>
</div>
@endsection
