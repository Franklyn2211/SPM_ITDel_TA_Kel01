{{-- resources/views/auditee/ami/indicator.blade.php --}}
@extends('auditee.layouts.app')
@section('title', 'Indikator Standar AMI')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex">
      <h4 class="page-title mb-0">Indikator Standar AMI</h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center">
        <button type="button" class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCreateIndicator">
          <i class="ph-plus me-2"></i>
          Tambah Indikator
        </button>
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('auditee.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <span class="breadcrumb-item active">Indikator Standar AMI</span>
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
      <h5 class="mb-0">Daftar Indikator</h5>
      <div class="ms-auto" style="max-width: 360px;">
        <div class="input-group">
          <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
          <input type="text" id="searchIndicator" class="form-control" placeholder="Cari deskripsi / standar / kode akademik...">
          <button class="btn btn-outline-secondary" type="button" id="btnResetFilter">Reset</button>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle" id="tableIndicator">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width: 60px;">No</th>
            <th>Deskripsi Indikator</th>
            <th style="width: 280px;">Standar (TA)</th>
            <th class="text-center" style="width: 120px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
          <tr>
            <td class="text-center">{{ $loop->iteration + ($rows->currentPage()-1)*$rows->perPage() }}</td>
            <td class="td-desc">{{ $row->description }}</td>
            <td class="td-std">
              {{ $row->standard->name ?? '-' }}
              @if(optional($row->standard->academicConfig)->academic_code)
                <span class="badge bg-secondary ms-1">TA {{ $row->standard->academicConfig->academic_code }}</span>
              @endif
            </td>
            <td class="text-center">
              <div class="d-flex justify-content-center gap-2">
                <button
                  type="button"
                  class="btn btn-warning btn-icon"
                  title="Edit"
                  onclick="openEditIndicatorModal(
                    {{ Js::from($row->id) }},
                    {{ Js::from($row->description) }},
                    {{ Js::from($row->standard_id) }},
                    '{{ route('auditee.ami.indicator.update', $row->id) }}'
                  )">
                  <i class="ph-pencil"></i>
                </button>

                <button
                  type="button"
                  class="btn btn-danger btn-icon"
                  title="Hapus"
                  onclick="confirmDelete('{{ route('auditee.ami.indicator.destroy', $row->id) }}')">
                  <i class="ph-trash"></i>
                </button>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="4" class="text-center text-muted">Belum ada indikator</td>
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
<div class="modal fade" id="modalCreateIndicator" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('auditee.ami.indicator.store') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Tambah Indikator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Deskripsi</label>
          <textarea name="description" class="form-control" rows="3" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Standar (hanya TA aktif)</label>
          <select name="standard_id" class="form-select" required>
            <option value="" selected disabled>Pilih standar…</option>
            @foreach($standards as $s)
              <option value="{{ $s->id }}">
                {{ $s->name }}
                @if(optional($s->academicConfig)->academic_code)
                  (TA {{ $s->academicConfig->academic_code }})
                @endif
              </option>
            @endforeach
          </select>
          <div class="form-text">Dropdown ini hanya menampilkan standar dari tahun akademik yang aktif.</div>
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
<div class="modal fade" id="modalEditIndicator" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" id="formEditIndicator" class="modal-content">
      @csrf
      @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title">Edit Indikator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Deskripsi</label>
          <textarea name="description" id="edit_description" class="form-control" rows="3" required></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Standar (hanya TA aktif)</label>
          <select name="standard_id" id="edit_standard_id" class="form-select" required>
            <option value="" disabled>Pilih standar…</option>
            @foreach($standards as $s)
              <option value="{{ $s->id }}">
                {{ $s->name }}
                @if(optional($s->academicConfig)->academic_code)
                  (TA {{ $s->academicConfig->academic_code }})
                @endif
              </option>
            @endforeach
          </select>
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
  // Filter client-side
  const inputFilter = document.getElementById('searchIndicator');
  const btnReset    = document.getElementById('btnResetFilter');
  const table       = document.getElementById('tableIndicator');

  function applyFilter() {
    const q = (inputFilter.value || '').trim().toLowerCase();
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(tr => {
      const desc = (tr.querySelector('.td-desc')?.textContent || '').toLowerCase();
      const std  = (tr.querySelector('.td-std')?.textContent || '').toLowerCase();
      tr.style.display = (desc.includes(q) || std.includes(q)) ? '' : 'none';
    });
  }

  inputFilter?.addEventListener('input', applyFilter);
  btnReset?.addEventListener('click', () => {
    inputFilter.value = '';
    applyFilter();
  });

  // Modal edit helper
  function openEditIndicatorModal(id, description, standardId, actionUrl) {
    document.getElementById('edit_description').value = description ?? '';
    const sel = document.getElementById('edit_standard_id');
    if (sel) sel.value = standardId || '';
    const form = document.getElementById('formEditIndicator');
    form.action = actionUrl;
    new bootstrap.Modal(document.getElementById('modalEditIndicator')).show();
  }

  // Konfirmasi delete
  function confirmDelete(url) {
    if (confirm('Yakin ingin menghapus indikator ini?')) {
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
