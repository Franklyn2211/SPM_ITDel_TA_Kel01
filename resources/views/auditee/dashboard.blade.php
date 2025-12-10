@extends('auditee.layouts.app')

@section('title', 'Dashboard - Sistem Penjaminan Mutu')

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
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('auditee.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <span class="breadcrumb-item active">Dashboard</span>
      </div>
      @if($academic)
        <div class="ms-auto">
          <span class="badge bg-primary">
            TA Aktif: {{ $academic->name ?? ($academic->tahun ?? '-') }}
          </span>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection

@section('content')
@php
  $formStatusName = $form?->status?->name ?? 'Draft';
  $isLocked = $form && $formStatusName === 'Dikirim';
@endphp

<div class="row">
  <div class="col-xl-8">

    {{-- CARD FED UTAMA --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <div>
          <h5 class="mb-0">Formulir Evaluasi Diri (AMI)</h5>
          @if($academic)
            <div class="text-muted fs-sm">
              {{ $academic->name ?? ($academic->tahun ?? 'Tahun Akademik Aktif') }}
              @if($isMemberForm)
                · <span class="badge bg-info bg-opacity-10 text-info">Anggota Auditee</span>
              @endif
            </div>
          @endif
        </div>

        <div class="ms-auto text-end">
          @if($form)
            @php
              $badgeClass = $formStatusName === 'Dikirim'
                ? 'bg-success'
                : ($formStatusName === 'Draft' ? 'bg-secondary' : 'bg-primary');
            @endphp
            <span class="badge {{ $badgeClass }} rounded-pill">{{ $formStatusName }}</span>
          @else
            <span class="badge bg-warning text-dark rounded-pill">Belum Ada Form</span>
          @endif
        </div>
      </div>

      <div class="card-body">
        @if(!$academic)
          <div class="alert alert-warning mb-0">
            Tahun akademik aktif belum diset. Silakan hubungi admin SPM.
          </div>
        @else
          @if($form)
            {{-- PROGRESS --}}
            <div class="d-flex flex-column flex-md-row align-items-md-center mb-3 gap-2">
              <div class="me-md-3">
                <div class="fw-semibold">Progress Pengisian</div>
                <div class="text-muted fs-sm">
                  {{ $progress['terisi'] ?? 0 }} / {{ $progress['total'] ?? 0 }} butir
                  ({{ $progress['percent'] ?? 0 }}%)
                </div>
              </div>
              <div class="flex-grow-1">
                <div class="progress" style="height:10px;">
                  <div class="progress-bar" style="width: {{ $progress['percent'] ?? 0 }}%"></div>
                </div>
              </div>
            </div>

            {{-- ALERT STATUS --}}
            @if($isLocked)
              <div class="alert alert-success py-2 mb-3">
                <i class="ph-check-circle me-2"></i>
                Form telah <strong>dikirim</strong>
                @if($form->submitted_at)
                  pada {{ \Illuminate\Support\Carbon::parse($form->submitted_at)->translatedFormat('d M Y') }}
                @endif
                . Pengisian sudah terkunci.
              </div>
            @else
              <div class="alert alert-warning py-2 mb-3">
                <i class="ph-warning me-2"></i>
                Lengkapi seluruh butir, lalu tekan <strong>Submit</strong> setelah yakin semua jawaban sudah benar.
              </div>
            @endif
          @else
            <div class="alert alert-info d-flex align-items-center mb-3">
              <i class="ph-info me-2"></i>
              Belum ada Form Evaluasi Diri untuk tahun/kategori ini. Buka halaman FED untuk membuat form baru.
            </div>
          @endif

          {{-- AKSI --}}
          <div class="d-flex flex-wrap gap-2 mt-1">
            <a href="{{ route('auditee.fed.index') }}" class="btn btn-primary">
              <i class="ph-note-pencil me-2"></i> Buka Pengisian FED
            </a>

            @if($form && !$isMemberForm)
              <form method="post"
                    action="{{ route('auditee.fed.submit', $form) }}"
                    onsubmit="return confirm('Kirim Form Evaluasi Diri sekarang? Setelah dikirim tidak dapat diedit.');">
                @csrf
                <button class="btn btn-success"
                        @if(!$canSubmit || $isLocked) disabled @endif>
                  <i class="ph-paper-plane-tilt me-2"></i> Submit
                </button>
              </form>
            @endif
          </div>

          @if($form && $lastUpdatedAt)
            <div class="text-muted fs-sm mt-3">
              Terakhir diperbarui:
              {{ \Illuminate\Support\Carbon::parse($lastUpdatedAt)->diffForHumans() }}
            </div>
          @endif
        @endif
      </div>
    </div>

    {{-- BUTIR BELUM DIISI (dibatasi, indikator lebih menonjol) --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Butir Belum Diisi</h5>
        <div class="ms-auto d-flex align-items-center gap-2">
          @if($form)
            <span class="badge bg-warning text-dark rounded-pill">
              {{ max(($progress['total'] ?? 0) - ($progress['terisi'] ?? 0), 0) }}
            </span>
          @endif
          <a href="{{ route('auditee.fed.index') }}" class="btn btn-sm btn-outline-primary">
            Buka FED
          </a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th style="width:60px" class="text-center">No</th>
              <th>Indikator & Standar</th>
            </tr>
          </thead>
          <tbody>
            @if(!$form)
              <tr>
                <td colspan="2" class="text-muted text-center">
                  Belum ada form. Buka halaman FED untuk membuat.
                </td>
              </tr>
            @else
              @forelse($unfilled as $i => $d)
                @php
                  $stdName   = optional($d->indicator?->standard)->name ?? '-';
                  $descPlain = strip_tags($d->indicator->description ?? '');
                  $shortDesc = \Illuminate\Support\Str::limit($descPlain, 150);
                @endphp
                <tr id="unfilled-{{ $d->id }}">
                  <td class="text-center">{{ $i + 1 }}</td>
                  <td>
                    <div>
                      {{ $shortDesc }}
                      @if(mb_strlen($descPlain) > 150)
                        <a href="{{ route('auditee.fed.index') }}#detail-{{ $d->id }}" class="ms-1">Lihat selengkapnya</a>
                      @endif
                    </div>
                    <div class="text-muted fs-sm">
                      Standar: {{ $stdName }}
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="2" class="text-success text-center">
                    Semua butir telah diisi.
                  </td>
                </tr>
              @endforelse
            @endif
          </tbody>
        </table>
      </div>
      @if($form && $unfilled->count() > 0)
        <div class="card-footer text-muted fs-sm">
          Menampilkan maksimal 5 butir yang belum diisi. Lengkapnya dapat dilihat pada halaman FED.
        </div>
      @endif
    </div>

    {{-- AKTIVITAS TERAKHIR (dibatasi & indikator ditekankan) --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Aktivitas Terakhir</h5>
        <div class="ms-auto">
          <a href="{{ route('auditee.fed.index') }}" class="btn btn-sm btn-outline-primary">
            Buka FED
          </a>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table text-nowrap mb-0">
          <thead>
            <tr>
              <th style="width:60px" class="text-center">No</th>
              <th>Indikator & Standar</th>
              <th>Waktu</th>
            </tr>
          </thead>
          <tbody>
            @if(!$form)
              <tr>
                <td colspan="4" class="text-muted text-center">
                  Tidak ada aktivitas.
                </td>
              </tr>
            @else
              @forelse($recent as $i => $d)
                @php
                  $stdName   = optional($d->indicator?->standard)->name ?? '-';
                  $descPlain = strip_tags($d->indicator->description ?? '');
                  $shortDesc = \Illuminate\Support\Str::limit($descPlain, 140);
                  $k         = $d->standardAchievement->name ?? '—';
                  $hasil     = trim((string)($d->result ?? ''));
                @endphp
                <tr id="recent-{{ $d->id }}">
                  <td class="text-center">{{ $i + 1 }}</td>
                  <td>
                    <div>
                      {{ $shortDesc }}
                      @if(mb_strlen($descPlain) > 140)
                        <a href="{{ route('auditee.fed.index') }}#detail-{{ $d->id }}" class="ms-1">Lihat selengkapnya</a>
                      @endif
                    </div>
                    <div class="text-muted fs-sm">
                      Standar: {{ $stdName }}
                    </div>
                    <div class="text-muted fs-sm mt-1">
                      Diubah oleh:
                      <span class="fw-semibold">{{ $d->updater_name ?? $d->updater_username ?? '—' }}</span>
                    </div>
                  </td>
                  <td>{{ \Illuminate\Support\Carbon::parse($d->updated_at)->diffForHumans() }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="text-muted text-center">
                    Belum ada aktivitas.
                  </td>
                </tr>
              @endforelse
            @endif
          </tbody>
        </table>
      </div>
      @if($form && $recent->count() > 0)
        <div class="card-footer text-muted fs-sm">
          Menampilkan 5 aktivitas terakhir yang berkaitan dengan FED.
        </div>
      @endif
    </div>

  </div>

  {{-- KOLOM KANAN --}}
  <div class="col-xl-4">
    {{-- STATISTIK KETERCAPAIAN --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Statistik Ketercapaian</h5>
      </div>
      <div class="card-body">
        @if(!$form)
          <div class="text-muted">Belum ada data ketercapaian.</div>
        @else
          <ul class="list-unstyled mb-0">
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Melampaui</span>
              <span class="badge bg-success bg-opacity-10 text-success">
                {{ $statsKetercapaian['Melampaui'] }}
              </span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Mencapai</span>
              <span class="badge bg-primary bg-opacity-10 text-primary">
                {{ $statsKetercapaian['Mencapai'] }}
              </span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Tidak Mencapai</span>
              <span class="badge bg-warning text-dark">
                {{ $statsKetercapaian['Tidak Mencapai'] }}
              </span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Menyimpang</span>
              <span class="badge bg-danger bg-opacity-10 text-danger">
                {{ $statsKetercapaian['Menyimpang'] }}
              </span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-1">
              <span>Kosong</span>
              <span class="badge bg-secondary">
                {{ $statsKetercapaian['Kosong'] }}
              </span>
            </li>
          </ul>
        @endif
      </div>
    </div>

    {{-- NAVIGASI CEPAT --}}
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0">Navigasi Cepat</h5>
      </div>
      <div class="list-group list-group-borderless">
        <a href="{{ route('auditee.fed.index') }}" class="list-group-item list-group-item-action d-flex align-items-center">
          <i class="ph-note-pencil me-2"></i> Pengisian FED
          <span class="ms-auto text-muted">&rarr;</span>
        </a>
      </div>
    </div>

  </div>
</div>
@endsection

@push('styles')
<style>
  .letter-icon {
    width: 18px;
    height: 18px;
    display: block;
  }
</style>
@endpush

@push('scripts')
{{-- no extra scripts --}}
@endpush
