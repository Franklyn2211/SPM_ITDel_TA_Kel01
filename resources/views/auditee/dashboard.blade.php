@extends('auditee.layouts.app')

@section('title', 'Dashboard - Sistem Penjaminan Mutu')

{{-- Page Header --}}
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
        <a href="{{ route('auditee.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <a href="#" class="breadcrumb-item">Home</a>
        <span class="breadcrumb-item active">Dashboard</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="row">
  <div class="col-xl-8">

    {{-- ====== KARTU STATUS & AKSI CEPAT FED ====== --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">
          Formulir Evaluasi Diri (AMI)
          @if($academic)
            <span class="text-muted fw-normal">— {{ $academic->name ?? ($academic->tahun ?? 'Tahun Akademik Aktif') }}</span>
          @endif
        </h5>
        <div class="ms-auto">
          @if($form)
            @php
              $statusName = $form->status->name ?? 'Draft';
              $badgeClass = $statusName === 'Dikirim' ? 'bg-success' : 'bg-secondary';
            @endphp
            <span class="badge {{ $badgeClass }} rounded-pill">{{ $statusName }}</span>
          @else
            <span class="badge bg-warning text-dark rounded-pill">Belum Ada</span>
          @endif
        </div>
      </div>

      <div class="card-body">
        @if(!$academic)
          <div class="alert alert-warning">
            Tahun akademik aktif belum diset. Silakan hubungi admin.
          </div>
        @else
          {{-- Progress bar + angka --}}
          @if($form)
            <div class="d-flex align-items-center mb-3">
              <div class="me-3">
                <div class="fw-semibold">Progress Pengisian</div>
                <div class="text-muted fs-sm">{{ $progress['terisi'] ?? 0 }} / {{ $progress['total'] ?? 0 }} butir ({{ $progress['percent'] ?? 0 }}%)</div>
              </div>
              <div class="flex-grow-1">
                <div class="progress" style="height:10px;">
                  <div class="progress-bar" style="width: {{ $progress['percent'] ?? 0 }}%"></div>
                </div>
              </div>
            </div>

            {{-- Info submit / reminder --}}
            @if(($form->status->name ?? '') === 'Dikirim')
              <div class="alert alert-success py-2">
                <i class="ph-check-circle me-2"></i>
                Form telah <strong>dikirim</strong>
                @if($form->tanggal_submit)
                  pada {{ \Illuminate\Support\Carbon::parse($form->tanggal_submit)->translatedFormat('d M Y') }}
                @endif
                .
              </div>
            @else
              <div class="alert alert-warning py-2">
                <i class="ph-warning me-2"></i>
                Harap lengkapi seluruh butir standar lalu tekan <strong>Submit</strong>.
              </div>
            @endif
          @else
            <div class="alert alert-info d-flex align-items-center">
              <i class="ph-info me-2"></i>
              Belum ada Form Evaluasi Diri untuk tahun/prodi ini. Silakan buka halaman FED untuk membuatnya.
            </div>
          @endif

          {{-- Tombol aksi cepat --}}
          <div class="d-flex flex-wrap gap-2 mt-2">
            <a href="{{ route('auditee.fed.index') }}" class="btn btn-primary">
              <i class="ph-note-pencil me-2"></i> Buka Pengisian FED
            </a>

            @if($form)
              <form method="post" action="{{ route('auditee.fed.submit', $form) }}"
                    onsubmit="return confirm('Kirim Form Evaluasi Diri sekarang? Setelah dikirim tidak dapat diedit.');">
                @csrf
                <button class="btn btn-success" @if(!$canSubmit) disabled @endif>
                  <i class="ph-paper-plane-tilt me-2"></i> Submit
                </button>
              </form>
            @endif
          </div>

          {{-- Meta kecil --}}
          @if($form && $lastUpdatedAt)
            <div class="text-muted fs-sm mt-2">
              Terakhir diperbarui: {{ \Illuminate\Support\Carbon::parse($lastUpdatedAt)->diffForHumans() }}
            </div>
          @endif
        @endif
      </div>
    </div>
    {{-- ====== END KARTU FED ====== --}}

    {{-- ====== ITEM YANG BELUM DIISI ====== --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Butir Belum Diisi</h5>
        @if($form)
          <span class="badge bg-warning text-dark rounded-pill ms-auto">
            {{ max(($progress['total'] ?? 0) - ($progress['terisi'] ?? 0), 0) }}
          </span>
        @endif
      </div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th style="width:60px">No</th>
              <th>Standar & Butir</th>
              <th class="text-end" style="width:120px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @if(!$form)
              <tr><td colspan="3" class="text-muted text-center">Belum ada form. Buka halaman FED untuk membuat.</td></tr>
            @else
              @forelse($unfilled as $i => $d)
                @php
                  $stdName   = optional($d->AmiStandardIndicator?->standard)->name ?? '-';
                  $descPlain = strip_tags($d->AmiStandardIndicator->description ?? '');
                  $shortDesc = \Illuminate\Support\Str::limit($descPlain, 140);
                @endphp
                <tr id="unfilled-{{ $d->id }}">
                  <td>{{ $i+1 }}</td>
                  <td>
                    <div class="fw-semibold text-primary">{{ $stdName }}</div>
                    <div class="text-muted fs-sm">
                      {{ $shortDesc }}
                      @if(mb_strlen($descPlain) > 140)
                        <a href="{{ route('auditee.fed.index') }}#detail-{{ $d->id }}" class="ms-1">Lihat selengkapnya</a>
                      @endif
                    </div>
                  </td>
                  <td class="text-end">
                    <a href="{{ route('auditee.fed.index') }}#detail-{{ $d->id }}" class="btn btn-sm btn-outline-primary">
                      <i class="ph-pencil me-1"></i> Isi
                    </a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-success text-center">Semua butir telah diisi</td></tr>
              @endforelse
            @endif
          </tbody>
        </table>
      </div>
    </div>
    {{-- ====== END ITEM BELUM DIISI ====== --}}

    {{-- ====== AKTIVITAS TERAKHIR ====== --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Aktivitas Terakhir</h5>
      </div>
      <div class="table-responsive">
        <table class="table text-nowrap">
          <thead>
            <tr>
              <th style="width:60px">#</th>
              <th>Butir</th>
              <th>Diubah oleh</th>
              <th>Waktu</th>
              <th class="text-end" style="width:120px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @if(!$form)
              <tr><td colspan="5" class="text-muted text-center">Tidak ada aktivitas.</td></tr>
            @else
              @forelse($recent as $i => $d)
                @php
                  $stdName   = optional($d->AmiStandardIndicator?->standard)->name ?? '-';
                  $descPlain = strip_tags($d->AmiStandardIndicator->description ?? '');
                  $shortDesc = \Illuminate\Support\Str::limit($descPlain, 120);
                  $k         = $d->KetercapaianStandard->name ?? '—';
                  $hasil     = trim((string)($d->hasil ?? ''));
                @endphp
                <tr id="recent-{{ $d->id }}">
                  <td>{{ $i+1 }}</td>
                  <td>
                    <div class="fw-semibold text-primary">{{ $stdName }}</div>
                    <div class="text-muted fs-sm">
                      {{ $shortDesc }}
                      @if(mb_strlen($descPlain) > 120)
                        <a href="{{ route('auditee.fed.index') }}#detail-{{ $d->id }}" class="ms-1">Lihat selengkapnya</a>
                      @endif
                    </div>
                    <div class="text-muted fs-sm mt-1">
                      Ketercapaian: <span class="fw-semibold">{{ $k }}</span>
                      @if($hasil) · <span class="fst-italic">"{{ \Illuminate\Support\Str::limit($hasil, 60) }}"</span> @endif
                    </div>
                  </td>
                  <td>{{ $d->updater_name ?? $d->updater_username ?? '—' }}</td>
                  <td>{{ \Illuminate\Support\Carbon::parse($d->updated_at)->diffForHumans() }}</td>
                  <td class="text-end">
                    <a href="{{ route('auditee.fed.index') }}#detail-{{ $d->id }}" class="btn btn-sm btn-outline-secondary">
                      <i class="ph-eye me-1"></i> Lihat
                    </a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-muted text-center">Belum ada aktivitas.</td></tr>
              @endforelse
            @endif
          </tbody>
        </table>
      </div>
    </div>
    {{-- ====== END AKTIVITAS TERAKHIR ====== --}}

  </div>

  {{-- ====== KOLOM KANAN: STATISTIK & NAVIGASI ====== --}}
  <div class="col-xl-4">

    {{-- Statistik ringkas ketercapaian --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Statistik Ketercapaian</h5>
      </div>
      <div class="card-body">
        @if(!$form)
          <div class="text-muted">Belum ada data.</div>
        @else
          <ul class="list-unstyled mb-0">
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Melampaui</span>
              <span class="badge bg-success bg-opacity-10 text-success">{{ $statsKetercapaian['Melampaui'] }}</span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Mencapai</span>
              <span class="badge bg-primary bg-opacity-10 text-primary">{{ $statsKetercapaian['Mencapai'] }}</span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Tidak Mencapai</span>
              <span class="badge bg-warning text-dark">{{ $statsKetercapaian['Tidak Mencapai'] }}</span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Menyimpang</span>
              <span class="badge bg-danger bg-opacity-10 text-danger">{{ $statsKetercapaian['Menyimpang'] }}</span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Kosong</span>
              <span class="badge bg-secondary">{{ $statsKetercapaian['Kosong'] }}</span>
            </li>
          </ul>
        @endif
      </div>
    </div>

    {{-- Navigasi cepat --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Navigasi Cepat</h5>
      </div>
      <div class="list-group list-group-borderless">
        <a href="{{ route('auditee.fed.index') }}" class="list-group-item list-group-item-action d-flex align-items-center">
          <i class="ph-note-pencil me-2"></i> Pengisian FED
          <span class="ms-auto text-muted">&rarr;</span>
        </a>
        {{-- Tambah link lain jika ada modul lain --}}
        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center disabled">
          <i class="ph-file-pdf me-2"></i> Cetak Laporan (segera)
        </a>
        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center disabled">
          <i class="ph-book-open me-2"></i> Panduan FED (segera)
        </a>
      </div>
    </div>

  </div>
</div>
@endsection

@push('styles')
<style>
  .letter-icon { width: 18px; height: 18px; display:block; }
</style>
@endpush

@push('scripts')
{{-- Script khusus halaman jika diperlukan --}}
@endpush
