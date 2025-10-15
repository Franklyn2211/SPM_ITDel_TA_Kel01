@extends('admin.layouts.app')
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
      <div class="d-lg-flex align-items-center"></div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <span class="breadcrumb-item active">Assign PIC Indikator</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">

  {{-- Alert Success --}}
  @if (session('success'))
    <div class="alert alert-success border-0 alert-dismissible fade show">
      <div class="d-flex align-items-center">
        <i class="ph-check-circle me-2"></i> {{ session('success') }}
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Alert Error --}}
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

  {{-- Tabel --}}
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
            <th class="text-center" style="width:60px;">No</th>
            <th>Indikator</th>
            <th style="width:280px;">Standar (TA)</th>
            <th style="width:360px;">PIC (Role)</th>
            <th class="text-center" style="width:260px;">Aksi</th>
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
                      class="btn btn-sm btn-warning btn-open-pic"
                      data-mode="edit"
                      data-id="{{ $row->id }}"
                      data-roles='@json($roleIds)'
                      data-action="{{ route('admin.ami.pic.update', $row->id) }}">
                      <i class="ph-pencil me-1"></i> Ubah PIC
                    </button>

                    <form method="POST" action="{{ route('admin.ami.pic.destroy', $row->id) }}"
                          onsubmit="return confirm('Hapus semua PIC untuk indikator ini?');" class="d-inline">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-danger">
                        <i class="ph-trash me-1"></i> Hapus
                      </button>
                    </form>
                  @else
                    <button type="button"
                      class="btn btn-sm btn-primary btn-open-pic"
                      data-mode="create"
                      data-id="{{ $row->id }}"
                      data-roles='[]'
                      data-action="{{ route('admin.ami.pic.store', $row->id) }}">
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

{{-- Modal --}}
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
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary" id="formPic_submit_btn">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Tambahan CSS --}}
@push('styles')
<style>
  #tablePic .td-desc { white-space: normal !important; word-break: break-word; overflow-wrap: anywhere; max-width: 560px; }
  .clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .select2-container { width: 100% !important; }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {

  // Select2 initialization
  function initSelect2($el, opts) {
    if ($.fn.select2) {
      if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
      $el.select2(Object.assign({
        width: '100%',
        minimumResultsForSearch: 0,
        closeOnSelect: false,
        dropdownParent: $('#modalPic'),
        placeholder: "Pilih role...",
      }, opts || {}));
    }
  }

  $(document).on('click', '.btn-open-pic', function () {
    var mode   = $(this).data('mode');
    var id     = $(this).data('id');
    var roles  = $(this).data('roles') || [];
    var action = $(this).data('action');

    var $form   = $('#formPic');
    var $method = $('#formPic_method');
    var $title  = $('#modalPic_title');
    var $btn    = $('#formPic_submit_btn');
    var $sel    = $('#formPic_roles');
    var $modal  = $('#modalPic');

    $form.attr('action', action || '#');
    $method.val(mode === 'edit' ? 'PUT' : 'POST');
    $title.text(mode === 'edit' ? 'Ubah PIC Indikator' : 'Tambah PIC Indikator');
    $btn.text(mode === 'edit' ? 'Simpan Perubahan' : 'Simpan');

    $sel.val(null).trigger('change'); // Clear previous selections
    $sel.find('option').prop('selected', false); // Ensure all options are unselected
    (Array.isArray(roles) ? roles : []).forEach(function (val) {
      $sel.find('option[value="' + val + '"]').prop('selected', true);
    });

    var bs = new bootstrap.Modal($modal[0]);
    bs.show();

    $modal.one('shown.bs.modal', function () {
      initSelect2($sel, { multiple: true });
      $sel.val(roles).trigger('change'); // Set selected roles
    });
  });

  $('#formPic').on('submit', function (e) {
    e.preventDefault();

    var form = this;
    var url  = form.action;
    var fd   = new FormData(form);
    var token = $('meta[name="csrf-token"]').attr('content');

    fetch(url, {
      method: 'POST',
      body: fd,
      headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      } else {
        alert(data.message || 'Gagal menyimpan data.');
      }
    })
    .catch(err => alert('Gagal menyimpan: ' + err.message));
  });

  $('#filterTable').on('input', function() {
    var q = $(this).val().toLowerCase();
    $('#tablePic tbody tr').each(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
    });
  });

  $('#btnResetTable').on('click', function() {
    $('#filterTable').val('').trigger('input');
  });

});
</script>
@endpush
@endsection
