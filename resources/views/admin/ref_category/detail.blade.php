@extends('admin.layouts.app')
@section('title', 'Detail Kategori - Admin Sistem Penjaminan Mutu')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex">
      <h4 class="page-title mb-0">Detail Kategori</h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>
    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center">
        <button type="button" class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCreateDetail">
          <i class="ph-plus me-2"></i>
          Tambah Detail
        </button>
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <a href="{{ route('admin.ref_category.index') }}" class="breadcrumb-item">Kategori</a>
        <span class="breadcrumb-item active">Detail</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">

  {{-- Alert error/sukses ala Limitless, no toast --}}
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
      <h5 class="mb-0">Daftar Detail Kategori</h5>
      <div class="ms-auto">
        <input type="text" class="form-control" placeholder="Cari detail..." id="searchDetail">
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover">
        <thead class="table-light">
          <tr>
            <th class="text-center" width="50">No</th>
            <th>Kategori</th>
            <th>Detail</th>
            <th class="text-center" width="140">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($categoryDetails as $detail)
          <tr>
            <td class="text-center">{{ $categoryDetails->firstItem() + $loop->index }}</td>
            <td>{{ $detail->category->name }}</td>
            <td>{{ $detail->name }}</td>
            <td class="text-center">
              <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-warning btn-icon" title="Edit"
                  data-bs-toggle="modal"
                  data-bs-target="#modalEditDetail"
                  data-id="{{ $detail->id }}"
                  data-name="{{ $detail->name }}"
                  data-category="{{ $detail->category_id }}"
                  data-action="{{ route('admin.ref_category.detail.update', $detail->id) }}">
                  <i class="ph-pencil"></i>
                </button>

                {{-- FIX kutip nyasar di sini --}}
                <button type="button" class="btn btn-danger btn-icon" title="Hapus"
                  onclick="confirmDelete('{{ route('admin.ref_category.detail.destroy', $detail->id) }}')">
                  <i class="ph-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="4" class="text-center text-muted">Belum ada data detail kategori</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    <div class="card-footer d-flex justify-content-between align-items-center">
      <span class="text-muted">
        Menampilkan {{ $categoryDetails->firstItem() ?? 0 }} - {{ $categoryDetails->lastItem() ?? 0 }} dari {{ $categoryDetails->total() }} data
      </span>
      {{ $categoryDetails->links() }}
    </div>
  </div>
</div>

{{-- Modal Create Detail --}}
<div class="modal fade" id="modalCreateDetail" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('admin.ref_category.detail.store') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Tambah Detail Kategori</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Pilih Kategori</label>
          <select name="category_id" class="form-select" required>
            <option value="">-- Pilih Kategori --</option>
            @foreach($category as $cat)
              <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                {{ $cat->name }}
              </option>
            @endforeach
          </select>
          @error('category_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        <div class="mb-3">
          <label class="form-label">Detail</label>
          <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
          @error('name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal Edit Detail --}}
<div class="modal fade" id="modalEditDetail" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" id="formEditDetail" class="modal-content">
      @csrf
      @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title">Edit Detail Kategori</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Pilih Kategori</label>
          <select name="category_id" id="edit_category_id" class="form-select" required>
            <option value="">-- Pilih Kategori --</option>
            @foreach($category as $cat)
              <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
          </select>
          @error('category_id') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        <div class="mb-3">
          <label class="form-label">Detail</label>
          <input type="text" class="form-control" name="name" id="edit_name" value="{{ old('name') }}" required>
          @error('name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  // Search
  document.getElementById('searchDetail').addEventListener('keyup', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
      const categoryName = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
      const detailText   = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
      row.style.display = (categoryName + detailText).includes(q) ? '' : 'none';
    });
  });

  // Fill modal edit on show
  const editDetailModal = document.getElementById('modalEditDetail');
  if (editDetailModal) {
    editDetailModal.addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
      if (!btn) return;

      const name   = btn.getAttribute('data-name') || '';
      const catId  = btn.getAttribute('data-category') || '';
      const action = btn.getAttribute('data-action') || '';

      document.getElementById('edit_name').value = name;
      document.getElementById('edit_category_id').value = catId;
      document.getElementById('formEditDetail').action = action;
    });
  }

  // Auto reopen modal when validation fails
  @if (session('open_modal') === 'create')
    new bootstrap.Modal(document.getElementById('modalCreateDetail')).show();
  @elseif (session('open_modal') === 'edit')
    // set action from session and old values
    const form = document.getElementById('formEditDetail');
    if (form) form.action = @json(session('edit_action'));
    const editName = document.getElementById('edit_name');
    if (editName) editName.value = @json(old('name'));
    const editCat = document.getElementById('edit_category_id');
    if (editCat) editCat.value = @json(old('category_id'));
    new bootstrap.Modal(document.getElementById('modalEditDetail')).show();
  @endif

  // Konfirmasi hapus (tanpa ikon gede, tanpa toast)
  function confirmDelete(url) {
    Swal.fire({
      title: 'Hapus data?',
      text: 'Data yang dihapus tidak bisa dikembalikan.',
      icon: undefined,
      showCancelButton: true,
      confirmButtonText: 'Ya',
      cancelButtonText: 'Batal',
      customClass: {
        confirmButton: 'btn btn-primary',
        cancelButton: 'btn btn-light'
      },
      buttonsStyling: false
    }).then((res) => {
      if (res.isConfirmed) {
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
