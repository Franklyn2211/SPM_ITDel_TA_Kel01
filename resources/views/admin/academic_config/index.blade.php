@extends('admin.layouts.app')
@section('title', 'Konfigurasi Akademik - Admin Sistem Penjaminan Mutu')
@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex">
      <h4 class="page-title mb-0">
        Konfigurasi Akademik
      </h4>
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
  {{-- Flash message --}}
  @if (session('success'))
    <div class="alert alert-success border-0 alert-dismissible fade show">
      <div class="d-flex align-items-center">
        <i class="ph-check-circle me-2"></i>
        {{ session('success') }}
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
                    <th style="width: 50px" class="text-center">No</th>
                    <th>Nama</th>
                    <th>Kode Akademik</th>
                    <th style="width: 120px;" class="text-center">Status</th>
                    <th style="width: 120px;" class="text-center">Aksi</th>
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
                    <td>
                        <div class="d-flex justify-content-center gap-2">
                            {{-- Toggle Active --}}
                            <form method="POST" action="{{ route('admin.academic_config.set_active', $config->id) }}">
                            @csrf
                                <input type="hidden" name="active" value="{{ $config->active ? 0 : 1 }}">
                                <button type="submit"
                                        class="btn btn-{{ $config->active ? 'secondary' : 'success' }} btn-icon"
                                        title="{{ $config->active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                    @if($config->active)
                                        <i class="ph-x-circle"></i>
                                    @else
                                        <i class="ph-check-circle"></i>
                                    @endif
                                </button>
                            </form>
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-warning btn-icon" title="Edit"
                                onclick="openEditConfigModal('{{ $config->id }}', '{{ $config->name }}', '{{ $config->academic_code }}', '{{ route('admin.academic_config.update', $config->id) }}')">
                                <i class="ph-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-icon" title="Hapus"
                                onclick="confirmDelete('{{ route('admin.academic_config.destroy', $config->id) }}')">
                                <i class="ph-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center text-muted">Belum ada data konfigurasi akademik</td>
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
                    <input type="text" class="form-control" name="name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kode Akademik</label>
                    <input type="text" class="form-control" name="academic_code" required>
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

@push('scripts')
<script>
    // Search functionality
    document.getElementById('searchAcademicConfig').addEventListener('keyup', function() {
        let searchText = this.value.toLowerCase();
        let tableRows = document.querySelectorAll('tbody tr');

        tableRows.forEach(row => {
            let name = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
            let code = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
            row.style.display = (name + code).includes(searchText) ? '' : 'none';
        });
    });

    // Edit modal handler
    function openEditConfigModal(id, name, code, updateUrl) {
        document.getElementById('editName').value = name;
        document.getElementById('editCode').value = code;
        const form = document.getElementById('formEditConfig');
        form.action = updateUrl;
        var modal = new bootstrap.Modal(document.getElementById('modalEditConfig'));
        modal.show();
    }

    // Delete confirmation
    function confirmDelete(deleteUrl) {
        if (confirm('Apakah Anda yakin ingin menghapus konfigurasi ini?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = deleteUrl;
            form.innerHTML = `@csrf @method('DELETE')`;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
@endpush
@endsection
