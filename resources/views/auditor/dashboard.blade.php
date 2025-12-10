@extends('auditor.layouts.app')

@section('title', 'Dashboard Auditor - Sistem Penjaminan Mutu')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h4 class="page-title mb-0">
        Home - <span class="fw-normal">Dashboard Auditor</span>
      </h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center">
        <a href="#" class="d-flex align-items-center text-body py-2 me-lg-3">
          <i class="ph-lifebuoy me-2"></i> Support
        </a>

        <div class="dropdown">
          <a href="#" class="d-flex align-items-center text-body dropdown-toggle py-2" data-bs-toggle="dropdown">
            <i class="ph-gear me-2"></i> <span>Settings</span>
          </a>
          <div class="dropdown-menu dropdown-menu-end">
            <a href="#" class="dropdown-item"><i class="ph-shield-warning me-2"></i> Account security</a>
            <a href="#" class="dropdown-item"><i class="ph-lock-key me-2"></i> Privacy</a>
            <div class="dropdown-divider"></div>
            <a href="#" class="dropdown-item"><i class="ph-gear me-2"></i> All settings</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('auditor.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <a href="#" class="breadcrumb-item">Home</a>
        <span class="breadcrumb-item active">Dashboard Auditor</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="row">
  <div class="col-xl-8">

    {{-- Ringkasan Audit --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">
          Ringkasan Audit Formulir Evaluasi Diri
          @if($activeAcademic)
            <span class="text-muted fw-normal">
              — {{ $activeAcademic->name ?? ($activeAcademic->tahun ?? 'Tahun Akademik Aktif') }}
            </span>
          @endif
        </h5>
      </div>
      <div class="card-body">
        @if(($summary['total'] ?? 0) === 0)
          <div class="alert alert-info d-flex align-items-center">
            <i class="ph-info me-2"></i>
            Belum ada Form Evaluasi Diri yang perlu diaudit untuk konfigurasi saat ini.
          </div>
        @else
          <div class="row text-center mb-3">
            <div class="col-md-3 col-6 mb-2">
              <div class="fw-semibold text-muted fs-sm mb-1">Total FED</div>
              <div class="fs-4 fw-bold">{{ $summary['total'] }}</div>
            </div>
            <div class="col-md-3 col-6 mb-2">
              <div class="fw-semibold text-muted fs-sm mb-1">Dikirim</div>
              <div class="fs-4 fw-bold text-success">{{ $summary['submitted'] }}</div>
            </div>
            <div class="col-md-3 col-6 mb-2">
              <div class="fw-semibold text-muted fs-sm mb-1">Disetujui</div>
              <div class="fs-4 fw-bold text-primary">{{ $summary['approved'] }}</div>
            </div>
            <div class="col-md-3 col-6 mb-2">
              <div class="fw-semibold text-muted fs-sm mb-1">Ditolak</div>
              <div class="fs-4 fw-bold text-danger">{{ $summary['rejected'] }}</div>
            </div>
          </div>

          <div class="alert alert-secondary py-2">
            <i class="ph-info me-2"></i>
            FED dengan status <strong>Dikirim</strong> siap untuk direview. FED <strong>Ditolak</strong> biasanya menunggu tindak lanjut dari auditee.
          </div>
        @endif
      </div>
    </div>

    {{-- FED sample (sebagian rekap FED) --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Rekap Form Evaluasi Diri</h5>
        <div class="ms-auto">
          <a href="{{ route('auditor.fed.rekap.index', ['category' => 'semua']) }}" class="btn btn-sm btn-outline-primary">
            Lihat semua rekap
          </a>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Nama Unit</th>
              <th class="text-center">Indikator Terisi</th>
              <th class="text-center">Progress</th>
              <th>Tanggal Submit</th>
            </tr>
          </thead>
          <tbody>
            @forelse($fedSample as $row)
              <tr>
                <td>{{ $row['name'] }}</td>
                <td class="text-center">
                  {{ $row['filled'] ?? 0 }}/{{ $row['total'] ?? 0 }}
                </td>
                <td class="text-center">
                  {{ $row['percent'] ?? 0 }}%
                  <div class="progress mt-1" style="height:4px;">
                    <div class="progress-bar" style="width: {{ $row['percent'] ?? 0 }}%;"></div>
                  </div>
                </td>
                <td>{{ $row['submitted_at'] ?? '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted">
                  Belum ada data FED pada TA aktif.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <div class="col-xl-4">
    {{-- Statistik ringkas --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Statistik FED</h5>
      </div>
      <div class="card-body">
        @if(($summary['total'] ?? 0) === 0)
          <div class="text-muted">Belum ada data untuk diringkas.</div>
        @else
          <ul class="list-unstyled mb-0">
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Draft</span>
              <span class="badge bg-secondary">{{ $summary['draft'] }}</span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Dikirim</span>
              <span class="badge bg-info bg-opacity-10 text-info">{{ $summary['submitted'] }}</span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Disetujui</span>
              <span class="badge bg-success bg-opacity-10 text-success">{{ $summary['approved'] }}</span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Ditolak</span>
              <span class="badge bg-danger bg-opacity-10 text-danger">{{ $summary['rejected'] }}</span>
            </li>
          </ul>
        @endif
      </div>
    </div>

    {{-- Navigasi Cepat --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Navigasi Cepat</h5>
      </div>
      <div class="list-group list-group-borderless">
        {{-- ganti href sesuai route yang nanti kamu buat --}}
        <a href="{{ route('auditor.fed.index') }}" class="list-group-item list-group-item-action d-flex align-items-center">
          <i class="ph-clipboard-text me-2"></i> Daftar FED untuk diaudit
          <span class="ms-auto text-muted">&rarr;</span>
        </a>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>.letter-icon { width: 18px; height: 18px; display:block; }</style>
@endpush

@push('scripts')
{{-- no extra scripts for now --}}
@endpush
