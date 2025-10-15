@extends('admin.layouts.app')

@section('title', 'Tambah Role - Admin Sistem Penjaminan Mutu')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex">
      <h4 class="page-title mb-0">Tambah Role</h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center">
        <button type="button" class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCreateRole">
          <i class="ph-plus me-2"></i>
          Tambah Role
        </button>
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <a href="{{ route('admin.roles.index') }}" class="breadcrumb-item">Roles</a>
        <span class="breadcrumb-item active">Tambah</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">
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
    <div class="card-header d-flex align-items-center">
      <h5 class="mb-0">Daftar Role</h5>
      <div class="ms-auto">
        <input type="text" class="form-control" placeholder="Cari role..." id="searchRole">
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover">
        <thead class="table-light">
          <tr>
            <th class="text-center" width="50">No</th>
            <th>Nama Role</th>
            <th>Kategori</th>
            <th class="text-center" width="160">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($roles as $role)
          <tr>
            <td class="text-center">{{ $loop->iteration }}</td>
            <td>{{ $role->name }}</td>
            <td>{{ $role->category->name ?? '-' }}</td>
            <td class="text-center">
              <div class="d-flex justify-content-center gap-2">
                {{-- Tombol Edit: pakai data-attributes --}}
                <button
                  type="button"
                  class="btn btn-warning btn-icon"
                  title="Edit"
                  data-bs-toggle="modal"
                  data-bs-target="#modalEditRole"
                  data-id="{{ $role->id }}"
                  data-name="{{ $role->name }}"
                  data-cdetail="{{ $role->category_id }}"
                  data-action="{{ route('admin.roles.update', $role->id) }}"
                >
                  <i class="ph-pencil"></i>
                </button>

                {{-- Tombol Hapus --}}
                <button type="button" class="btn btn-danger btn-icon" title="Hapus"
                  onclick="confirmDelete(@json(route('admin.roles.destroy', $role->id)))">
                  <i class="ph-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="4" class="text-center text-muted">Belum ada data role</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Modal Create Role --}}
<div class="modal fade" id="modalCreateRole" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('admin.roles.store') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Tambah Role</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nama Role</label>
          <input type="text" class="form-control" name="name" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Kategori</label>
          <select name="category_id" class="form-select" required>
            <option value="" selected disabled>Pilih kategori…</option>
            @foreach($category as $cd)
              <option value="{{ $cd->id }}">{{ $cd->name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit Role --}}
<div class="modal fade" id="modalEditRole" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" id="formEditRole" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title">Edit Role</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nama Role</label>
          <input type="text" class="form-control" name="name" id="edit_name" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Kategori</label>
          <select name="category_id" class="form-select" id="edit_category_id" required>
            <option value="" disabled>Pilih kategori…</option>
            @foreach($category as $cd)
              <option value="{{ $cd->id }}">{{ $cd->name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
  // Pencarian
  document.getElementById('searchRole').addEventListener('keyup', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
      const name  = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
      const det   = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
      row.style.display = (name + det).includes(q) ? '' : 'none';
    });
  });

  // Isi form saat modal edit dibuka
  const editRoleModal = document.getElementById('modalEditRole');
  if (editRoleModal) {
    editRoleModal.addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
      if (!btn) return;

      const name   = btn.getAttribute('data-name') || '';
      const cdet   = btn.getAttribute('data-cdetail') || '';
      const action = btn.getAttribute('data-action') || '';

      const nameInput = editRoleModal.querySelector('#edit_name');
      const cdetSelect= editRoleModal.querySelector('#edit_category_id');
      const form      = document.getElementById('formEditRole');

      if (nameInput)  nameInput.value = name;
      if (cdetSelect) cdetSelect.value = cdet;
      if (form && action) form.action = action;
    });
  }

  // Hapus
  function confirmDelete(url) {
    if (confirm('Hapus role ini?')) {
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
@endsection
