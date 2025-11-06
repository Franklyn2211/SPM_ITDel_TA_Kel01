@extends('admin.layouts.app')

@section('title', 'Konfigurasi Akademik - Admin Sistem Penjaminan Mutu')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex">
      <h4 class="page-title mb-0">Konfigurasi Akademik</h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center">
        <button type="button" class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCreateAcademicConfig">
          <i class="ph-plus me-2"></i>
          Tambah Konfigurasi
        </button>
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <span class="breadcrumb-item active">Konfigurasi Akademik</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">

  {{-- Error block --}}
  @if ($errors->any())
    <div class="alert alert-danger border-0 alert-dismissible fade show">
      <div class="d-flex">
        <i class="ph-x-circle me-2"></i>
        <div>
          <strong>Gagal menyimpan.</strong>
          <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">Daftar Konfigurasi Akademik</h5>
      <div class="d-flex align-items-center">
        <div class="ms-auto">
          <input type="text" class="form-control" placeholder="Cari konfigurasi..." id="searchAcademicConfig">
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table text-nowrap table-hover">
        <thead class="table-light">
          <tr>
            <th style="width:50px" class="text-center">No</th>
            <th>Nama</th>
            <th>Kode Akademik</th>
            <th style="width:120px" class="text-center">Status</th>
            <th style="width:200px" class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody>
        @forelse($academicConfigs as $config)
          <tr>
            <td class="text-center">{{ $loop->iteration }}</td>
            <td>{{ $config->name }}</td>
            <td>{{ $config->academic_code }}</td>
            <td class="text-center">
              @if($config->active)
                <span class="badge bg-success">Aktif</span>
              @else
                <span class="badge bg-secondary">Tidak Aktif</span>
              @endif
            </td>
            <td class="text-center">
              <div class="d-flex justify-content-center gap-2">

                {{-- Toggle Active (dengan SweetAlert2 confirm) --}}
                <form method="POST"
                      action="{{ route('admin.academic_config.set_active', $config->id) }}"
                      id="formToggleActive-{{ $config->id }}">
                  @csrf
                  <input type="hidden" name="active" value="{{ $config->active ? 0 : 1 }}">
                  <button type="button"
                          class="btn btn-{{ $config->active ? 'secondary' : 'success' }} btn-icon"
                          title="{{ $config->active ? 'Nonaktifkan' : 'Aktifkan' }}"
                          onclick="confirmToggleActive('formToggleActive-{{ $config->id }}', {{ $config->active ? 'false' : 'true' }})">
                    @if($config->active)
                      <i class="ph-x-circle"></i>
                    @else
                      <i class="ph-check-circle"></i>
                    @endif
                  </button>
                </form>

                {{-- Edit --}}
                <button type="button" class="btn btn-warning btn-icon" title="Edit"
                        onclick="openEditConfigModal('{{ $config->id }}','{{ e($config->name) }}','{{ e($config->academic_code) }}','{{ route('admin.academic_config.update', $config->id) }}')">
                  <i class="ph-pencil"></i>
                </button>

                {{-- Delete (dengan SweetAlert2 confirm) --}}
                <form method="POST"
                      action="{{ route('admin.academic_config.destroy', $config->id) }}"
                      id="formDelete-{{ $config->id }}">
                  @csrf
                  @method('DELETE')
                  <button type="button" class="btn btn-danger btn-icon" title="Hapus"
                          onclick="confirmDelete('formDelete-{{ $config->id }}')">
                    <i class="ph-trash"></i>
                  </button>
                </form>

              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted">Belum ada data konfigurasi akademik</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Modal Create --}}
<div class="modal fade" id="modalCreateAcademicConfig" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('admin.academic_config.store') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Tambah Konfigurasi Akademik</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nama</label>
          <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
          @error('name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        <div class="mb-3">
          <label class="form-label">Kode Akademik</label>
          <input type="text" class="form-control" name="academic_code" value="{{ old('academic_code') }}" required>
          @error('academic_code') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit --}}
<div class="modal fade" id="modalEditConfig" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" id="formEditConfig" class="modal-content">
      @csrf
      @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title">Edit Konfigurasi Akademik</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nama</label>
          <input type="text" class="form-control" name="name" id="editName" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Kode Akademik</label>
          <input type="text" class="form-control" name="academic_code" id="editCode" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // ====== EDIT MODAL (ini yang hilang tadi) ======
  function openEditConfigModal(id, name, code, updateUrl) {
    // isi field
    document.getElementById('editName').value = name;
    document.getElementById('editCode').value = code;
    // set action form PUT
    const form = document.getElementById('formEditConfig');
    form.action = updateUrl;
    // buka modal
    new bootstrap.Modal(document.getElementById('modalEditConfig')).show();
  }

  // ====== KONFIRMASI HAPUS TANPA IKON BESAR ======
  function confirmDelete(formId) {
    Swal.fire({
      title: '⚠️ Hapus data?',
      text: 'Tindakan ini tidak bisa dibatalkan.',
      // Hilangkan ikon besar SweetAlert2
      icon: undefined,      // kunci: jangan pakai 'warning' biar gak muncul ikon gede
      iconHtml: null,
      showCancelButton: true,
      confirmButtonText: 'Ya',
      cancelButtonText: 'Batal',
      customClass: {
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-light'
      },
      buttonsStyling: false
    }).then((result) => {
      if (result.isConfirmed) {
        document.getElementById(formId).submit();
      }
    });
  }

  // ====== KONFIRMASI AKTIF/NONAKTIF TANPA IKON BESAR ======
  function confirmToggleActive(formId, willActivate) {
    const title = willActivate ? '❓ Aktifkan tahun ajaran?' : '❓ Nonaktifkan tahun ajaran?';
    const text  = willActivate
      ? 'Tahun ajaran lain akan otomatis dinonaktifkan.'
      : 'Anda yakin ingin menonaktifkan tahun ajaran ini?';

    Swal.fire({
      title: title,
      text: text,
      icon: undefined,      // no big icon
      iconHtml: null,
      showCancelButton: true,
      confirmButtonText: willActivate ? 'Ya, Aktifkan' : 'Ya, Nonaktifkan',
      cancelButtonText: 'Batal',
      customClass: {
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-light'
      },
      buttonsStyling: false
    }).then((result) => {
      if (result.isConfirmed) {
        document.getElementById(formId).submit();
      }
    });
  }
</script>
@endpush
