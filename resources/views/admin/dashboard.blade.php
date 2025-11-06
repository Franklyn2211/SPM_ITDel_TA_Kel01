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
          <span class="badge bg-primary">TA Aktif: {{ $activeAc->academic_code ?? $activeAc->name ?? '-' }}</span>
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

    {{-- Ringkasan cepat --}}
    <div class="row g-3">
      <div class="col-sm-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted fs-sm">Standar (TA aktif)</div>
            <div class="d-flex align-items-center">
              <h3 class="mb-0">{{ $counts['standards'] ?? 0 }}</h3>
              <a class="ms-auto btn btn-sm btn-light" href="{{ route('admin.ami.standard') }}"><i class="ph-list"></i></a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted fs-sm">Indikator (TA aktif)</div>
            <div class="d-flex align-items-center">
              <h3 class="mb-0">{{ $counts['indicators'] ?? 0 }}</h3>
              <a class="ms-auto btn btn-sm btn-light" href="{{ route('admin.ami.indicator') }}"><i class="ph-list"></i></a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted fs-sm">PIC Assignment</div>
            <div class="d-flex align-items-center">
              <h3 class="mb-0">{{ $counts['pics'] ?? 0 }}</h3>
              <a class="ms-auto btn btn-sm btn-light" href="{{ route('admin.ami.indicator') }}"><i class="ph-users-three"></i></a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-3">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <div class="text-muted fs-sm">Role aktif</div>
            <div class="d-flex align-items-center">
              <h3 class="mb-0">{{ $counts['roles'] ?? 0 }}</h3>
              <a class="ms-auto btn btn-sm btn-light" href="{{ route('admin.roles.index') }}"><i class="ph-gear"></i></a>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Form Dikirim (Menunggu Audit) --}}
    <div class="card mt-3">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Form Evaluasi Diri — Dikirim (Menunggu Audit)</h5>
        <span class="badge bg-success ms-2">{{ $queueSubmitted->count() }}</span>
        <div class="ms-auto">
          <a href="{{ route('admin.ami.standard') }}" class="btn btn-sm btn-light">Kelola Standar</a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle text-nowrap">
          <thead>
            <tr>
              <th>Unit/Prodi</th>
              <th class="text-center" style="width:160px;">Progress</th>
              <th style="width:160px;">Tanggal Kirim</th>
              <th class="text-end" style="width:120px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($queueSubmitted as $f)
              @php $p = $filledByForm[$f->id] ?? ['total'=>0,'terisi'=>0,'percent'=>0]; @endphp
              <tr>
                <td>{{ optional($f->categoryDetail)->name ?? '-' }}</td>
                <td class="text-center">
                  <div class="small text-muted mb-1">{{ $p['terisi'] }}/{{ $p['total'] }} ({{ $p['percent'] }}%)</div>
                  <div class="progress" style="height:8px;">
                    <div class="progress-bar" style="width: {{ $p['percent'] }}%"></div>
                  </div>
                </td>
                <td>
                  {{-- tanggal_submit kamu simpan varchar; tetap aman diparse kalau formatnya tanggal --}}
                  {{ $f->tanggal_submit ? \Illuminate\Support\Carbon::parse($f->tanggal_submit)->translatedFormat('d M Y') : '—' }}
                </td>
                <td class="text-end">
                  <a href="#" class="btn btn-sm btn-primary" disabled><i class="ph-clipboard-text me-1"></i> Audit</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted">Belum ada form yang dikirim pada TA aktif.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Aktivitas Terbaru --}}
    <div class="card mt-3">
      <div class="card-header">
        <h5 class="mb-0">Aktivitas Terbaru</h5>
      </div>
      <div class="table-responsive">
        <table class="table text-nowrap align-middle">
          <thead>
            <tr>
              <th style="width:60px;">#</th>
              <th>Ringkasan</th>
              <th>Diubah oleh</th>
              <th>Waktu</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recent as $i => $d)
              @php
                $ind = $indicatorMap[$d->ami_standard_indicator_id] ?? null;
                $stdName = $ind?->standard?->name;
                $desc = \Illuminate\Support\Str::limit(strip_tags($ind->description ?? ''), 60);
                $butirLabel = trim(($stdName ? $stdName.' — ' : '').$desc);
              @endphp
              <tr>
                <td>{{ $i+1 }}</td>
                <td>
                  <div class="text-muted fs-sm">
                    Butir {{ $butirLabel !== '' ? $butirLabel : '-' }}
                    @if($d->hasil)
                      — <span class="fst-italic">"{{ \Illuminate\Support\Str::limit($d->hasil, 80) }}"</span>
                    @endif
                  </div>
                </td>
                <td>{{ $d->updater_name ?? $d->updater_username ?? '—' }}</td>
                <td>{{ \Illuminate\Support\Carbon::parse($d->updated_at)->diffForHumans() }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted">Belum ada aktivitas.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>

  {{-- KOLOM KANAN --}}
  <div class="col-xl-4">

    {{-- Statistik Ketercapaian --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Statistik Ketercapaian (TA aktif)</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="d-flex justify-content-between py-1">
            <span>Melampaui</span><span class="badge bg-success bg-opacity-10 text-success">{{ $statsK['Melampaui'] }}</span>
          </li>
          <li class="d-flex justify-content-between py-1">
            <span>Mencapai</span><span class="badge bg-primary bg-opacity-10 text-primary">{{ $statsK['Mencapai'] }}</span>
          </li>
          <li class="d-flex justify-content-between py-1">
            <span>Tidak Mencapai</span><span class="badge bg-warning text-dark">{{ $statsK['Tidak Mencapai'] }}</span>
          </li>
          <li class="d-flex justify-content-between py-1">
            <span>Menyimpang</span><span class="badge bg-danger bg-opacity-10 text-danger">{{ $statsK['Menyimpang'] }}</span>
          </li>
          <li class="d-flex justify-content-between py-1">
            <span>Kosong</span><span class="badge bg-secondary">{{ $statsK['Kosong'] }}</span>
          </li>
        </ul>
      </div>
    </div>

    {{-- Coverage PIC per Role --}}
    <div class="card mt-3">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">PIC Coverage (Top 8)</h5>
      </div>
      <div class="list-group list-group-borderless">
        @forelse($picCoverage as $pc)
          <div class="list-group-item d-flex align-items-center">
            <i class="ph-users-three me-2"></i>
            <div class="me-auto">{{ optional($pc->role)->name ?? ('Role #'.$pc->role_id) }}</div>
            <span class="badge bg-secondary">{{ $pc->c }}</span>
          </div>
        @empty
          <div class="list-group-item text-muted">Belum ada assignment PIC.</div>
        @endforelse
      </div>
    </div>

    {{-- Kesenjangan Data --}}
    <div class="card mt-3">
      <div class="card-header">
        <h5 class="mb-0">Kesenjangan Data</h5>
      </div>
      <div class="card-body">
        <div class="mb-3">
          <div class="fw-semibold mb-2">Standar tanpa Indikator</div>
          @forelse($standardsNoIndicators as $s)
            <div class="d-flex align-items-center mb-1">
              <i class="ph-warning-circle text-warning me-2"></i>
              <a href="{{ route('admin.ami.indicator', ['standard_id' => $s->id]) }}" class="text-body">
                {{ $s->name }}
              </a>
              <span class="ms-2 text-muted small">TA {{ optional($s->academicConfig)->academic_code }}</span>
            </div>
          @empty
            <div class="text-muted">Tidak ada.</div>
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
              <a href="{{ route('admin.ami.indicator', ['standard_id' => $ind->standard_id]) }}" class="text-body">
                {{ $snippet ?: 'Tanpa deskripsi' }}
              </a>
            </div>
          @empty
            <div class="text-muted">Tidak ada.</div>
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
        <a class="list-group-item d-flex align-items-center" href="{{ route('admin.academic_config.index') }}">
          <i class="ph-calendar-check me-2"></i> Set Tahun Akademik Aktif
          <span class="ms-auto text-muted">&rarr;</span>
        </a>
        <a class="list-group-item d-flex align-items-center" href="{{ route('admin.ami.standard') }}">
          <i class="ph-book-open me-2"></i> Kelola Standar
          <span class="ms-auto text-muted">&rarr;</span>
        </a>
        <a class="list-group-item d-flex align-items-center" href="{{ route('admin.ami.indicator') }}">
          <i class="ph-list-checks me-2"></i> Kelola Indikator & PIC
          <span class="ms-auto text-muted">&rarr;</span>
        </a>
        <a class="list-group-item d-flex align-items-center" href="{{ route('admin.roles.index') }}">
          <i class="ph-identification-badge me-2"></i> Kelola Role
          <span class="ms-auto text-muted">&rarr;</span>
        </a>
      </div>
    </div>

  </div>
</div>
@endsection

@push('styles')
<style>.letter-icon{width:18px;height:18px;display:block}</style>
@endpush
