@extends('auditee.layouts.app')
@section('title', 'Assign PIC Indikator')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex">
      <h4 class="page-title mb-0">Assign PIC Indikator</h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center">
        {{-- Removed the button here from the top --}}
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('auditee.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <span class="breadcrumb-item active">Assign PIC Indikator</span>
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
        <i class="ph-check-circle me-2"></i> {{ session('success') }}
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
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
      <h5 class="mb-0">Ringkasan PIC per Indikator (TA aktif)</h5>
      <div class="ms-auto" style="max-width:360px;">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
          <input type="text" id="filterTable" class="form-control" placeholder="Cari deskripsi / standar / TA / role…">
          <button class="btn btn-outline-secondary" type="button" id="btnResetTable">Reset</button>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle" id="tablePic">
        <thead class="table-light">
          <tr>
            <th style="width:60px;" class="text-center">No</th>
            <th>Indikator</th>
            <th style="width:280px;">Standar (TA)</th>
            <th style="width:360px;">PIC (Role)</th>
            <th style="width:260px;" class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            @php
              $desc    = \Illuminate\Support\Str::limit($row->description, 120);
              $stdName = optional($row->standard)->name ?: '-';
              $ta      = optional(optional($row->standard)->academicConfig)->academic_code;
              $roleIds = $row->pics->pluck('role_id')->values();
            @endphp
            <tr>
              <td class="text-center">{{ $loop->iteration + ($rows->currentPage()-1)*$rows->perPage() }}</td>
              <td class="td-desc"><div class="clamp-3" title="{{ $row->description }}">{{ $desc }}</div></td>
              <td>
                {{ $stdName }}
                @if($ta)
                  <span class="badge bg-secondary ms-1">TA {{ $ta }}</span>
                @endif
              </td>
              <td>
                @if($row->pics->count())
                  @foreach($row->pics as $p)
                    <span class="badge bg-primary me-1 mb-1">{{ optional($p->role)->name ?? '-' }}</span>
                  @endforeach
                @else
                  <span class="text-muted">Belum ada PIC</span>
                @endif
              </td>
              <td class="text-center">
                <div class="d-flex justify-content-center flex-wrap gap-2">
                  @if($row->pics->count())
                    <button type="button"
                      class="btn btn-sm btn-warning"
                      onclick="openPicModal({
                        mode: 'edit',
                        indicatorId: @json($row->id),
                        selectedRoleIds: @json($roleIds),
                        actionUrl: '{{ route('auditee.ami.pic.update', $row->id) }}'
                      })">
                      <i class="ph-pencil me-1"></i> Ubah PIC
                    </button>
                    <form method="POST" action="{{ route('auditee.ami.pic.destroy', $row->id) }}"
                          onsubmit="return confirm('Hapus semua PIC untuk indikator ini?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-danger">
                        <i class="ph-trash me-1"></i> Hapus
                      </button>
                    </form>
                  @else
                    <button type="button"
                      class="btn btn-sm btn-primary"
                      onclick="openPicModal({
                        mode: 'create',
                        indicatorId: @json($row->id),
                        selectedRoleIds: [],
                        actionUrl: '{{ route('auditee.ami.pic.store', $row->id) }}'
                      })">
                      <i class="ph-plus me-1"></i> Tambah PIC
                    </button>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted">Belum ada data</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($rows->hasPages())
      <div class="card-footer d-flex align-items-center">
        <span class="text-muted me-auto">
          Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} entri
        </span>
        <div>{{ $rows->onEachSide(1)->links() }}</div>
      </div>
    @endif
  </div>
</div>

<div class="modal fade" id="modalPic" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" id="formPic" class="modal-content">
      @csrf
      <input type="hidden" name="_method" id="formPic_method" value="POST">
      <div class="modal-header">
        <h5 class="modal-title" id="modalPic_title">Kelola PIC</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Role (PIC) <small class="text-muted">(bisa lebih dari 1)</small></label>
          <select name="role_ids[]" id="formPic_roles" class="form-control select" multiple required>
            @foreach($roles as $r)
              <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
          </select>
          <div class="form-text">Role terpilih akan menjadi PIC aktif untuk indikator ini.</div>
          @error('role_ids') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
          @error('role_ids.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary" id="formPic_submit_btn">Simpan</button>
      </div>
    </form>
  </div>
</div>

@push('styles')
<style>
  #tablePic .td-desc { white-space: normal !important; word-break: break-word; overflow-wrap: anywhere; max-width: 560px; }
  .clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .select2-container { width: 100% !important; }
  .select2-container .select2-selection--multiple { min-height: 38px; border: 1px solid #d8d6de; }
  .select2-container .select2-selection--multiple .select2-selection__rendered { padding: 4px; }
  .select2-container .select2-selection__choice { background-color: #007bff; color: #fff; border: none; }
  .select2-container .select2-selection__choice__remove { color: #fff; }
</style>
@endpush

@push('scripts')
<script>
  // Inisialisasi Select2
  function initSelect2($el, opts) {
    if (window.jQuery && $.fn && $.fn.select2) {
      if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
      }
      $el.select2(Object.assign({
        width: '100%',
        minimumResultsForSearch: 0,
        closeOnSelect: false
      }, opts || {}));
    } else {
      console.error('Select2 tidak dimuat. Pastikan library Select2 sudah di-include.');
    }
  }

  // Fungsi untuk membuka modal
  function openPicModal({ mode, indicatorId, selectedRoleIds, actionUrl }) {
    console.log('openPicModal called with:', { mode, indicatorId, selectedRoleIds, actionUrl });

    const form = document.getElementById('formPic');
    const method = document.getElementById('formPic_method');
    const sel = document.getElementById('formPic_roles');
    const title = document.getElementById('modalPic_title');
    const btn = document.getElementById('formPic_submit_btn');

    // Validasi elemen
    if (!form || !method || !sel || !title || !btn) {
      console.error('Elemen form tidak ditemukan:', { form, method, sel, title, btn });
      return;
    }

    // Set form action dan method
    form.action = actionUrl || '#';
    method.value = mode === 'edit' ? 'PUT' : 'POST';
    title.textContent = mode === 'edit' ? 'Ubah PIC Indikator' : 'Tambah PIC Indikator';
    btn.textContent = mode === 'edit' ? 'Simpan Perubahan' : 'Simpan';

    // Reset dan set pilihan Select2
    try {
      $(sel).val(null).trigger('change');
      if (selectedRoleIds && selectedRoleIds.length) {
        $(sel).val(selectedRoleIds).trigger('change');
      }
    } catch (e) {
      console.error('Error saat inisialisasi Select2:', e);
    }

    // Tampilkan modal
    const modalEl = document.getElementById('modalPic');
    if (modalEl) {
      try {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
      } catch (e) {
        console.error('Error saat membuka modal:', e);
      }
    } else {
      console.error('Modal tidak ditemukan!');
    }
  }

  // Fungsi filter tabel
  function setupFilter() {
    const input = document.getElementById('filterTable');
    const reset = document.getElementById('btnResetTable');
    const table = document.getElementById('tablePic');

    if (!input || !table) {
      console.error('Elemen filter atau tabel tidak ditemukan:', { input, table });
      return;
    }

    function doFilter() {
      const q = (input.value || '').toLowerCase().trim();
      const rows = table.querySelectorAll('tbody tr');
      rows.forEach(tr => {
        const text = tr.textContent.toLowerCase();
        tr.style.display = q === '' || text.includes(q) ? '' : 'none';
      });
    }

    input.addEventListener('input', doFilter);
    if (reset) {
      reset.addEventListener('click', () => {
        input.value = '';
        doFilter();
      });
    }
  }

  // Inisialisasi saat DOM selesai dimuat
  document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM loaded, inisialisasi script...');

    // Inisialisasi Select2
    const $select = $('#formPic_roles');
    if ($select.length) {
      initSelect2($select, { multiple: true });
    } else {
      console.error('Elemen #formPic_roles tidak ditemukan!');
    }

    // Setup filter
    setupFilter();

    // Handle form submission
    const form = document.getElementById('formPic');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        console.log('Form submitted:', form.action);

        const formData = new FormData(form);
        const method = formData.get('_method') || 'POST';

        fetch(form.action, {
          method: method,
          body: formData,
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
          }
        })
          .then(response => {
            console.log('Response received:', response);
            if (!response.ok) {
              return response.json().then(err => {
                throw new Error(err.message || 'Gagal menyimpan data: ' + response.statusText);
              });
            }
            return response.json();
          })
          .then(data => {
            console.log('Success response:', data);
            if (data.success) {
              window.location.reload();
            } else {
              alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
            }
          })
          .catch(error => {
            console.error('Error during fetch:', error);
            alert('Terjadi kesalahan saat menyimpan data: ' + error.message);
          });
      });
    } else {
      console.error('Form #formPic tidak ditemukan!');
    }

    // Debug tombol
    document.querySelectorAll('button').forEach(button => {
      button.addEventListener('click', () => {
        console.log('Button clicked:', button.textContent);
      });
    });
  });
</script>
@endpush
@endsection
