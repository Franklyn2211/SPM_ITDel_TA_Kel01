@extends('admin.layouts.app')

@section('title', 'Dashboard - Admin Sistem Penjaminan Mutu')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h4 class="page-title mb-0">
        Home - <span class="fw-normal">Dashboard</span>
      </h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center">
        <a href="{{ route('admin.academic_config.index') }}" class="d-flex align-items-center text-body py-2 me-lg-3">
          <i class="ph-gear me-2"></i> Konfigurasi
        </a>
        <a href="#" class="d-flex align-items-center text-body py-2">
          <i class="ph-lifebuoy me-2"></i> Support
        </a>
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <span class="breadcrumb-item active">Dashboard</span>
      </div>
      <div class="ms-auto">
        @if($activeAc)
          <span class="badge bg-primary">
            TA Aktif: {{ $activeAc->academic_code ?? $activeAc->name ?? '-' }}
          </span>
        @else
          <span class="badge bg-warning text-dark">TA aktif belum dipilih</span>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="row">
  {{-- KOLOM KIRI --}}
  <div class="col-xl-8">

    {{-- Ringkasan indikator per kategori --}}
    <div class="row g-3 mb-3">
      <div class="col-sm-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between mb-1">
              <div class="text-muted fs-sm">Fakultas</div>
              <i class="ph-buildings text-muted"></i>
            </div>
            <div class="small text-muted">Jumlah Indikator</div>
            <h3 class="mb-0 mt-1">{{ $indicatorSummary['faculty'] ?? 0 }}</h3>
          </div>
        </div>
      </div>

      <div class="col-sm-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between mb-1">
              <div class="text-muted fs-sm">Prodi</div>
              <i class="ph-graduation-cap text-muted"></i>
            </div>
            <div class="small text-muted">Jumlah Indikator</div>
            <h3 class="mb-0 mt-1">{{ $indicatorSummary['prodi'] ?? 0 }}</h3>
          </div>
        </div>
      </div>

      <div class="col-sm-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between mb-1">
              <div class="text-muted fs-sm">Unit</div>
              <i class="ph-squares-four text-muted"></i>
            </div>
            <div class="small text-muted">Jumlah Indikator</div>
            <h3 class="mb-0 mt-1">{{ $indicatorSummary['unit'] ?? 0 }}</h3>
          </div>
        </div>
      </div>

      <div class="col-sm-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between mb-1">
              <div class="text-muted fs-sm">Total Indikator (TA aktif)</div>
              <i class="ph-target text-muted"></i>
            </div>
            <h3 class="mb-0 mt-3">{{ $indicatorSummary['total'] ?? 0 }}</h3>
          </div>
        </div>
      </div>
    </div>

        {{-- FED sample (sebagian rekap FED) --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Rekap Form Evaluasi Diri</h5>
        <div class="ms-auto">
          <a href="{{ route('admin.fed.rekap.index', ['category' => 'semua']) }}" class="btn btn-sm btn-outline-primary">
            Lihat semua rekap
          </a>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Nama Unit</th>
              <th>Penanggung Jawab</th>
              <th class="text-center">Indikator Terisi</th>
              <th class="text-center">Progress</th>
              <th>Tanggal Submit</th>
            </tr>
          </thead>
          <tbody>
            @forelse($fedSample as $row)
              <tr>
                <td>{{ $row['name'] }}</td>
                <td>
                  {{ $row['primary_role'] ?? '-' }}<br>
                  <span class="text-muted small">{{ $row['primary_name'] ?? '-' }}</span>
                </td>
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

  {{-- KOLOM KANAN --}}
  <div class="col-xl-4">

    {{-- Kesenjangan Data --}}
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Kesenjangan Data</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="fw-semibold mb-2">Standar tanpa Indikator</div>
          @forelse($standardsNoIndicators as $s)
            <div class="d-flex align-items-center mb-1">
              <i class="ph-warning-circle text-warning me-2"></i>
              <a href="{{ route('admin.ami.indicator', ['standard_id' => $s->id]) }}"
                 class="text-body">
                {{ $s->name }}
              </a>
              <span class="ms-2 text-muted small">
                TA {{ optional($s->academicConfig)->academic_code }}
              </span>
            </div>
          @empty
            <div class="text-muted small">Tidak ada.</div>
          @endforelse
        </div>

        <div>
          <div class="fw-semibold mb-2">Indikator tanpa PIC</div>
          @forelse($indicatorsNoPic as $ind)
            @php
              $snippet = \Illuminate\Support\Str::limit(strip_tags($ind->description ?? ''), 60);
            @endphp
            <div class="d-flex align-items-center mb-1">
              <i class="ph-user-circle-gear text-warning me-2"></i>
              <a href="{{ route('admin.ami.indicator', ['standard_id' => $ind->standard_id]) }}"
                 class="text-body">
                {{ $snippet ?: 'Tanpa deskripsi' }}
              </a>
            </div>
          @empty
            <div class="text-muted small">Tidak ada.</div>
          @endforelse
        </div>
      </div>
    </div>

    {{-- Aksi Cepat --}}
    <div class="card mt-3">
      <div class="card-header">
        <h5 class="mb-0">Aksi Cepat</h5>
      </div>
      <div class="list-group list-group-borderless">
        <a class="list-group-item d-flex align-items-center"
           href="{{ route('admin.academic_config.index') }}">
          <i class="ph-calendar-check me-2"></i>
          Set Tahun Akademik Aktif
          <span class="ms-auto text-muted">&rarr;</span>
        </a>
        <a class="list-group-item d-flex align-items-center"
           href="{{ route('admin.ami.standard') }}">
          <i class="ph-book-open me-2"></i>
          Kelola Standar
          <span class="ms-auto text-muted">&rarr;</span>
        </a>
        <a class="list-group-item d-flex align-items-center"
           href="{{ route('admin.ami.indicator') }}">
          <i class="ph-list-checks me-2"></i>
          Kelola Indikator & PIC
          <span class="ms-auto text-muted">&rarr;</span>
        </a>
        <a class="list-group-item d-flex align-items-center"
           href="{{ route('admin.roles.index') }}">
          <i class="ph-identification-badge me-2"></i>
          Kelola Role
          <span class="ms-auto text-muted">&rarr;</span>
        </a>
      </div>
    </div>

  </div>
</div>
@endsection

@push('styles')
<style>
  .letter-icon{
    width:18px;
    height:18px;
    display:block;
  }
  .fed-card{
    border:1px solid #e5e7eb !important;
  }
  .fed-card:hover{
    background:#fafafa;
  }
</style>
@endpush
