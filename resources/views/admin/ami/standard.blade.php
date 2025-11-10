{{-- resources/views/admin/ami/standard.blade.php --}}
@extends('admin.layouts.app')
@section('title', 'Standar AMI')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex">
      <h4 class="page-title mb-0">Standar AMI</h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
  <div class="d-lg-flex align-items-center gap-2">

        {{-- Tombol tambah standar --}}
        <button type="button" class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCreateStandard">
          <i class="ph-plus me-2"></i>
          Tambah Standar
        </button>

        @php $isHistory = request()->boolean('history'); @endphp

        {{-- TOMBOL GLOBAL: submit semua / set semua draft (hanya saat TA aktif) --}}
        @unless($isHistory)
          <form method="POST" id="formGlobalSubmit"
                action="{{ route('admin.ami.standard.submit') }}">
            @csrf
            <input type="hidden" name="mode" value="{{ $anyActive ? 'deactivate' : 'activate' }}">

            <button type="button"
                    class="btn btn-sm rounded-pill {{ $anyActive ? 'btn-outline-secondary' : 'btn-success' }}"
                    data-bs-toggle="modal" data-bs-target="#modalGlobalSubmit">
              @if($anyActive)
                <i class="ph-arrow-counter-clockwise me-2"></i> Set Semua Draft
              @else
                <i class="ph-paper-plane-tilt me-2"></i> Submit Semua Standar
              @endif
            </button>
          </form>
        @endunless

        {{-- Toggle Riwayat / Tahun Aktif --}}
        @if($isHistory)
          <a href="{{ route('admin.ami.standard') }}" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="ph-clock-counter-clockwise me-2"></i> Kembali ke Tahun Aktif
          </a>
        @else
          <a href="{{ route('admin.ami.standard', ['history' => 1]) }}" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="ph-clock-afternoon me-2"></i> Riwayat
          </a>
        @endif

      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <span class="breadcrumb-item active">Standar AMI</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">

  {{-- Flash message: success / error --}}
  @if (session('success'))
    <div class="alert alert-success border-0 alert-dismissible fade show">
      <div class="d-flex align-items-center">
        <i class="ph-check-circle me-2"></i>
        {{ session('success') }}
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if (session('error'))
    <div class="alert alert-danger border-0 alert-dismissible fade show">
      <div class="d-flex align-items-center">
        <i class="ph-warning me-2"></i>
        {{ session('error') }}
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Validation errors --}}
  @if ($errors->any())
    <div class="alert alert-danger border-0 alert-dismissible fade show">
      <div class="d-flex align-items-center">
        <i class="ph-warning me-2"></i>
        <div>
          <strong>Gagal menyimpan:</strong>
          <ul class="mb-0">
            @foreach ($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="card">
    <div class="card-header d-flex align-items-center">
      <h5 class="mb-0">Daftar Standar AMI</h5>
      <div class="ms-auto" style="max-width: 320px;">
        <div class="input-group">
          <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
          <input type="text" id="searchStandard" class="form-control" placeholder="Cari standar / kode akademik...">
          <button class="btn btn-outline-secondary" type="button" id="btnResetFilter">Reset</button>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table text-nowrap table-hover align-middle" id="tableStandard">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width: 60px;">No</th>
            <th>Nama Standar</th>
            <th style="width: 220px;">Tahun Akademik</th>
            <th class="text-center" style="width: 160px;">Indikator Kinerja</th>
            <th class="text-center" style="width: 160px;">Status</th>
            <th class="text-center" style="width: 180px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            @php
              $indikatorCount = $row->indicators_count ?? 0;
            @endphp
            <tr>
              <td class="text-center">
                {{ $loop->iteration + ($rows->currentPage()-1)*$rows->perPage() }}
              </td>

              <td class="td-name">
                <div class="d-flex flex-column">
                  <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold">{{ $row->name }}</span>
                    @if($row->active)
                      <span class="badge bg-success rounded-pill">Aktif</span>
                    @else
                      <span class="badge bg-secondary rounded-pill">Draft</span>
                    @endif
                  </div>
                  <div class="text-muted fs-sm">
                    Dibuat:
                    {{ optional($row->created_at)->format('d/m/Y') ?? '-' }}
                    @if($row->createdBy)
                      · oleh {{ $row->createdBy->name }}
                    @endif
                  </div>
                </div>
              </td>

              <td class="td-ac">
                {{ $row->academicConfig->academic_code ?? '-' }}
              </td>

              <td class="text-center">
                <a href="{{ route('admin.ami.indicator', ['standard_id' => $row->id]) }}"
                   class="badge {{ $indikatorCount > 0 ? 'bg-primary' : 'bg-secondary' }}"
                   title="Lihat semua indikator standar ini">
                  {{ $indikatorCount }} indikator
                </a>
              </td>

              <td class="text-center">
                @if($row->active)
                  <span class="text-success">
                    <i class="ph-check-circle me-1"></i> Tersubmit
                  </span>
                @else
                  <span class="text-muted">
                    <i class="ph-clock-afternoon me-1"></i> Belum disubmit
                  </span>
                @endif
              </td>

              <td class="text-center">
                <div class="d-flex justify-content-center flex-wrap gap-1">

                  {{-- Edit --}}
                  <button
                    type="button"
                    class="btn btn-warning btn-icon btn-sm"
                    title="Edit"
                    onclick="openEditStandardModal(
                      {{ Js::from($row->id) }},
                      {{ Js::from($row->name) }},
                      '{{ route('admin.ami.standard.update', $row->id) }}'
                    )">
                    <i class="ph-pencil"></i>
                  </button>

                  {{-- Hapus --}}
                  <button
                    type="button"
                    class="btn btn-danger btn-icon btn-sm"
                    title="Hapus"
                    onclick="confirmDelete('{{ route('admin.ami.standard.destroy', $row->id) }}')">
                    <i class="ph-trash"></i>
                  </button>
                </div>

                @if($indikatorCount == 0)
                  <div class="text-muted fs-xs mt-1">
                    Standar ini belum punya indikator aktif.
                  </div>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted">Belum ada standar AMI</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($rows->hasPages())
      <div class="card-footer d-flex align-items-center">
        <span class="text-muted me-auto">
          Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} entri
        </span>
        <div>
          {{ $rows->onEachSide(1)->links() }}
        </div>
      </div>
    @endif
  </div>
</div>

{{-- Modal: Create --}}
<div class="modal fade" id="modalCreateStandard" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('admin.ami.standard.store') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Tambah Standar AMI</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nama Standar</label>
          <input type="text" name="name" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal: Edit --}}
<div class="modal fade" id="modalEditStandard" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" id="formEditStandard" class="modal-content">
      @csrf
      @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title">Edit Standar AMI</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nama Standar</label>
          <input type="text" name="name" id="edit_name" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

@push('styles')
<style>
  .modal .modal-body {
    max-height: calc(100vh - 200px);
    overflow-y: auto;
  }
</style>
@endpush

@push('scripts')
<script>
  // Global submit confirmation (only exists in Tahun Aktif mode)
  document.getElementById('btnConfirmGlobalSubmit')?.addEventListener('click', function () {
    document.getElementById('formGlobalSubmit')?.submit();
  });

  // Client-side filter: cari pada kolom Nama & Kode Akademik
  const inputFilter = document.getElementById('searchStandard');
  const btnReset    = document.getElementById('btnResetFilter');
  const table       = document.getElementById('tableStandard');

  function applyFilter() {
    const q = (inputFilter.value || '').trim().toLowerCase();
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(tr => {
      const name = (tr.querySelector('.td-name')?.textContent || '').toLowerCase();
      const ac   = (tr.querySelector('.td-ac')?.textContent || '').toLowerCase();
      tr.style.display = (name.includes(q) || ac.includes(q)) ? '' : 'none';
    });
  }

  inputFilter?.addEventListener('input', applyFilter);
  btnReset?.addEventListener('click', () => {
    inputFilter.value = '';
    applyFilter();
  });

  // Modal Edit helper
  function openEditStandardModal(id, name, actionUrl) {
    document.getElementById('edit_name').value = name ?? '';
    const form = document.getElementById('formEditStandard');
    form.action = actionUrl;

    new bootstrap.Modal(document.getElementById('modalEditStandard')).show();
  }

  // Konfirmasi delete
  function confirmDelete(url) {
    if (confirm('Yakin ingin menghapus standar ini?')) {
      const f = document.createElement('form');
      f.method = 'POST';
      f.action = url;
      f.innerHTML = `@csrf @method('DELETE')`;
      document.body.appendChild(f);
      f.submit();
    }
  }
</script>
@endpush

{{-- Modal: Confirm Global Submit --}}
<div class="modal fade" id="modalGlobalSubmit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          {{ $anyActive ? 'Set Semua Draft' : 'Submit Semua Standar' }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">
          {{ $anyActive
            ? 'Kembalikan SEMUA standar ke draft? Indikator tidak akan tampil di auditee.'
            : 'Submit SEMUA standar yang punya indikator aktif ke auditee?' }}
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" id="btnConfirmGlobalSubmit" class="btn {{ $anyActive ? 'btn-outline-secondary' : 'btn-success' }}">
          @if($anyActive)
            <i class="ph-arrow-counter-clockwise me-2"></i> Set Semua Draft
          @else
            <i class="ph-paper-plane-tilt me-2"></i> Submit Semua Standar
          @endif
        </button>
      </div>
    </div>
  </div>
  </div>
@endsection
