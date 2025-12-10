@extends('admin.layouts.app')

@section('title', $title)


@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h3 class="page-title mb-0 fw-bold">
        Rekap Form Evaluasi Diri
      </h3>
    </div>
  </div>
  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <span class="breadcrumb-item"><i class="ph-house"></i></span>
        <span class="breadcrumb-item active">Rekap Form Evaluasi Diri</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="card">
  <div class="card-header d-flex align-items-center">
    <h5 class="mb-0">{{ $title }}</h5>

    <div class="ms-auto d-flex align-items-center gap-2">

      <form method="GET"
            action="{{ route('admin.fed.rekap.index') }}"
            class="d-flex gap-2 align-items-center">

        {{-- Filter kategori --}}
        <select name="category"
                class="form-select form-select-sm me-2"
                onchange="this.form.submit()">
              <option value="semua" {{ $category === 'semua' ? 'selected' : '' }}>Semua</option>
              <option value="fakultas" {{ $category === 'fakultas' ? 'selected' : '' }}>Fakultas</option>
              <option value="prodi" {{ $category === 'prodi' ? 'selected' : '' }}>Program Studi</option>
              <option value="unit" {{ $category === 'unit' ? 'selected' : '' }}>Unit</option>
        </select>

        {{-- Urut --}}
        <select name="sort"
                class="form-select form-select-sm me-2"
                onchange="this.form.submit()">
          <option value="name" {{ $sort === 'name' ? 'selected' : '' }}>Urut Nama</option>
          <option value="percent" {{ $sort === 'percent' ? 'selected' : '' }}>Urut Progress</option>
        </select>

        {{-- Arah --}}
        <select name="dir"
                class="form-select form-select-sm"
                onchange="this.form.submit()">
          <option value="asc" {{ $dir === 'asc' ? 'selected' : '' }}>Naik</option>
          <option value="desc" {{ $dir === 'desc' ? 'selected' : '' }}>Turun</option>
        </select>

      </form>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover table-striped mb-0 align-middle">
      <thead>
        <tr>
          <th class="text-center" style="width: 50px;">No</th>
          <th>Nama Unit</th>
          <th>Penanggung Jawab</th>
          <th class="text-center" style="width: 110px;">Indikator</th>
          <th class="text-center" style="width: 170px;">Progress</th>
          <th class="text-center" style="width: 80px;">Melampaui</th>
          <th class="text-center" style="width: 80px;">Mencapai</th>
          <th class="text-center" style="width: 80px;">Tidak</th>
          <th class="text-center" style="width: 90px;">Menyimpang</th>
          <th style="width: 130px;">Tanggal Submit</th>
        </tr>
      </thead>
      <tbody>
        @forelse($recapItems as $row)
          @php
            $ach = $row['achievements'] ?? [];
          @endphp
          <tr>
            {{-- PENOMORAN PAGINATED --}}
            <td class="text-center">
              {{ ($recapItems->firstItem() ?? 1) + $loop->index }}
            </td>

            {{-- Nama unit --}}
            <td>
              <div class="fw-semibold">{{ $row['name'] }}</div>
              <div class="small text-muted">
                @if($category === 'semua')
                  @php
                    // Deteksi kategori asli dari nama unit
                    $cat = null;
                    if (isset($row['primary_role'])) {
                      $role = strtolower($row['primary_role']);
                      if (str_contains($role, 'dekan')) {
                        $cat = 'Fakultas';
                      } elseif (str_contains($role, 'kaprodi') || str_contains($role, 'ketua program studi') || str_contains($role, 'kepala program studi')) {
                        $cat = 'Program Studi';
                      } else {
                        $cat = 'Unit';
                      }
                    } else {
                      $cat = 'Unit';
                    }
                  @endphp
                  {{ $cat }}
                @else
                  {{ ucfirst($category) }}
                @endif
              </div>
            </td>

            {{-- Penanggung jawab --}}
            <td>
              <div>{{ $row['primary_role'] ?? '-' }}</div>
              <div class="small text-muted">{{ $row['primary_name'] ?? '-' }}</div>
            </td>

            {{-- Jumlah indikator --}}
            <td class="text-center">
              <div class="fw-semibold">{{ $row['indicators'] ?? 0 }}</div>
              <div class="small text-muted">indikator</div>
            </td>

            {{-- Progress --}}
            <td class="text-center">
              <div class="fw-semibold">
                {{ $row['percent'] ?? 0 }}%
              </div>
              <div class="progress mt-1" style="height:4px;">
                <div class="progress-bar" style="width: {{ $row['percent'] ?? 0 }}%;"></div>
              </div>
              <div class="small text-muted">
                {{ $row['filled'] ?? 0 }}/{{ $row['total'] ?? 0 }} indikator
              </div>
            </td>

            {{-- Ketercapaian --}}
            <td class="text-center">{{ $ach['Melampaui'] ?? 0 }}</td>
            <td class="text-center">{{ $ach['Mencapai'] ?? 0 }}</td>
            <td class="text-center">{{ $ach['Tidak Mencapai'] ?? 0 }}</td>
            <td class="text-center">{{ $ach['Menyimpang'] ?? 0 }}</td>

            {{-- Tanggal submit --}}
            <td>
              {{ $row['submitted_at'] ?? '—' }}
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="text-center text-muted">
              Belum ada data rekap untuk kategori ini.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer d-flex justify-content-between align-items-center">
    <div class="text-muted small">
      @if($recapItems->total())
        Menampilkan
        <span class="fw-semibold">{{ $recapItems->firstItem() }}</span>
        &ndash;
        <span class="fw-semibold">{{ $recapItems->lastItem() }}</span>
        dari
        <span class="fw-semibold">{{ $recapItems->total() }}</span>
        unit
      @else
        Tidak ada data.
      @endif
    </div>
    <div>
      {{-- Bootstrap 5 pagination --}}
      {{ $recapItems->appends(request()->query())->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
  </div>
</div>
@endsection
