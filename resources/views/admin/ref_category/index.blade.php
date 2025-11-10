@extends('admin.layouts.app')
@section('title', 'Kategori - Admin Sistem Penjaminan Mutu')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex">
      <h4 class="page-title mb-0">Kategori</h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center">
        <button type="button" class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCreateCategory">
          <i class="ph-plus me-2"></i>
          Tambah Kategori
        </button>
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <span class="breadcrumb-item active">Kategori</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">
  {{-- ALERTS --}}
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
      <h5 class="mb-0">Daftar Kategori</h5>
      <div class="ms-auto">
        <input type="text" class="form-control" placeholder="Cari kategori..." id="searchCategory">
      </div>
    </div>

    <div class="table-responsive">
      <table class="table text-nowrap table-hover">
        <thead class="table-light">
          <tr>
            <th style="width: 50px;" class="text-center">No</th>
            <th>Nama Kategori</th>
            <th style="width: 120px;" class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($category as $cat)
          <tr>
            {{-- Nomor global sesuai pagination --}}
            <td class="text-center">{{ $category->firstItem() + $loop->index }}</td>
            <td>{{ $cat->name }}</td>
            <td>
              <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-warning btn-icon" title="Edit"
                  onclick="openEditCategoryModal('{{ $cat->id }}', '{{ e($cat->name) }}', '{{ route('admin.ref_category.update', $cat->id) }}')">
                  <i class="ph-pencil"></i>
                </button>
                <button type="button" class="btn btn-danger btn-icon" title="Hapus"
                  onclick="confirmDelete('{{ route('admin.ref_category.destroy', $cat->id) }}')">
                  <i class="ph-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="3" class="text-center text-muted">Belum ada data kategori</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($category instanceof \Illuminate\Pagination\LengthAwarePaginator && $category->hasPages())
    <div class="card-footer d-flex align-items-center">
      <span class="text-muted me-auto">
        Showing {{ $category->firstItem() }} to {{ $category->lastItem() }} of {{ $category->total() }} entries
      </span>
      <div>
        {{ $category->onEachSide(1)->links('pagination::bootstrap-5') }}
      </div>
    </div>
    @endif
  </div>
</div>

{{-- Modal Create --}}
<div class="modal fade" id="modalCreateCategory" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('admin.ref_category.store') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Tambah Kategori</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nama Kategori</label>
          <input type="text" class="form-control" name="name" required value="{{ old('name') }}">
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
<div class="modal fade" id="modalEditCategory" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" id="formEditCategory" class="modal-content">
      @csrf
      @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title">Edit Kategori</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Nama Kategori</label>
          <input type="text" class="form-control" name="name" id="editCategoryName" required>
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
  // Pencarian client-side
  document.getElementById('searchCategory').addEventListener('keyup', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
      const name = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
      row.style.display = name.includes(q) ? '' : 'none';
    });
  });

  function openEditCategoryModal(id, name, updateUrl) {
    document.getElementById('editCategoryName').value = name;
    const form = document.getElementById('formEditCategory');
    form.action = updateUrl;
    new bootstrap.Modal(document.getElementById('modalEditCategory')).show();
  }

  function confirmDelete(url) {
    Swal.fire({
      title: 'Hapus data?',
      text: 'Data yang dihapus tidak bisa dikembalikan.',
      icon: undefined,
      showCancelButton: true,
      confirmButtonText: 'Ya',
      cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-light' },
      buttonsStyling: false
    }).then((r) => {
      if (r.isConfirmed) {
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = url;
        f.innerHTML = `@csrf @method('DELETE')`;
        document.body.appendChild(f);
        f.submit();
      }
    });
  }
</script>
@endpush
