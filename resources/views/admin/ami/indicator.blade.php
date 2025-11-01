{{-- resources/views/admin/ami/indicator.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'AMI · Indikator')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex">
      <h4 class="page-title mb-0">Indikator AMI</h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center gap-2">
        <button type="button" class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalCreate">
          <i class="ph-plus me-2"></i> Tambah Indikator
        </button>
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <a href="{{ route('admin.ami.standard') }}" class="breadcrumb-item">Standar AMI</a>
        <span class="breadcrumb-item active">Indikator AMI</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
@php
  $perPage = (int) request('per_page', $rows->perPage() ?: 10);
  $selectedRoleId = request('role_id');
  $selectedStandardId = request('standard_id');
@endphp

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
    {{-- HEADER: judul + toolbar filter + search (compact) --}}
    <div class="card-header py-2">
      <div class="d-flex align-items-center flex-wrap w-100 gap-2">
        <div class="d-flex align-items-center gap-2">
          <h5 class="mb-0">Daftar Indikator AMI</h5>
          <div class="vr d-none d-lg-block"></div>

          {{-- Toggle filter utk mobile --}}
          <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button"
                  data-bs-toggle="collapse" data-bs-target="#filtersBar" aria-expanded="false">
            <i class="ph-funnel"></i> Filter
          </button>
        </div>

        {{-- FILTER BAR (collapse di mobile, inline di desktop) --}}
        <div class="collapse d-lg-block" id="filtersBar">
          <form id="filtersForm" method="GET" action="{{ route('admin.ami.indicator') }}"
                class="row row-cols-lg-auto g-2 align-items-center ms-lg-2">

            <div class="col">
              <select class="form-select form-select-sm shadow-none" name="standard_id" onchange="this.form.submit()" style="min-width:260px;">
                <option value="">Semua Standar (TA aktif)</option>
                @foreach($standards as $s)
                  <option value="{{ $s->id }}" {{ (string)$selectedStandardId === (string)$s->id ? 'selected' : '' }}>
                    {{ $s->name }}@if(optional($s->academicConfig)->academic_code) (TA {{ $s->academicConfig->academic_code }})@endif
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col">
              <select class="form-select form-select-sm shadow-none" name="role_id" onchange="this.form.submit()" style="min-width:180px;">
                <option value="">Semua PIC</option>
                @foreach($roles as $r)
                  <option value="{{ $r->id }}" {{ (string)$selectedRoleId === (string)$r->id ? 'selected' : '' }}>
                    {{ $r->name }}
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Per page pakai dropdown biar hemat ruang --}}
            <input type="hidden" name="per_page" id="perPageInput" value="{{ $perPage }}">
            <div class="col">
              <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  {{ $perPage }}/hal
                </button>
                <ul class="dropdown-menu">
                  @foreach([10,25,50,100] as $pp)
                    <li>
                      <button type="button" class="dropdown-item {{ $perPage==$pp?'active':'' }}"
                              onclick="setPerPage({{ $pp }})">{{ $pp }}/hal</button>
                    </li>
                  @endforeach
                </ul>
              </div>
            </div>

            <div class="col">
              @if(request()->has('standard_id') || request()->has('per_page') || request()->has('role_id'))
                <a href="{{ route('admin.ami.indicator') }}" class="btn btn-link btn-sm text-decoration-none">
                  Reset
                </a>
              @endif
            </div>
          </form>
        </div>


      </div>
    </div>

    {{-- TABEL --}}
    <div class="table-responsive">
      <table class="table text-nowrap table-hover align-middle mb-0" id="tableIndicator">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width:60px;">No</th>
            <th style="width:320px;">Standar</th>
            <th style="width:220px;">Tahun Akademik</th>
            <th>Ringkasan Indikator</th>
            <th style="width:260px;">PIC (Role)</th>
            <th class="text-center" style="width:120px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
@forelse($rows as $row)
  @php
    $desc = $row->description ?? '';
    $descPlain = strip_tags($desc);
    $descB64 = base64_encode($desc);
    $ta = optional($row->standard?->academicConfig)->academic_code;
  @endphp
  <tr>
    <td class="text-center align-top">{{ $rows->firstItem() + $loop->index }}</td>

    <td class="align-top td-std">
      <div class="fw-semibold">{{ $row->standard->name ?? '-' }}</div>
      <span class="d-none td-desc">{{ $descPlain }}</span>
      <span class="d-none td-pic">
        @if(($row->pics ?? collect())->count())
          {{ implode(', ', ($row->pics->map(fn($p)=>$p->role->name ?? '')->filter()->values()->all())) }}
        @endif
      </span>
    </td>

    <td class="align-top td-ac">
      @if($ta)
        <span class="badge bg-secondary">TA {{ $ta }}</span>
      @else
        <span class="text-muted">-</span>
      @endif
    </td>

    <td class="align-top">
      <div class="mb-1">{{ \Illuminate\Support\Str::limit($descPlain, 100) }}</div>
      @if(mb_strlen($descPlain) > 100)
        <button type="button"
                class="btn btn-link p-0 small"
                data-bs-toggle="modal"
                data-bs-target="#modalDesc"
                data-desc-html="{{ $descB64 }}">
          Lihat selengkapnya
        </button>
      @endif
    </td>

    <td class="align-top">
      @if(($row->pics ?? collect())->count())
        <div class="d-flex flex-wrap gap-1">
          @foreach($row->pics as $pic)
            <span class="badge bg-secondary">{{ $pic->role->name ?? ('Role #' . $pic->role_id) }}</span>
          @endforeach
        </div>
      @else
        <span class="text-muted">Belum ada</span>
      @endif
    </td>

    <td class="text-center align-top">
      <div class="d-flex justify-content-center gap-2">
        <button
          type="button"
          class="btn btn-warning btn-icon"
          title="Edit"
          data-bs-toggle="modal"
          data-bs-target="#modalEdit"
          data-update="{{ route('admin.ami.indicator.update', $row->id) }}"
          data-desc-html="{{ $descB64 }}"
          data-standard="{{ $row->standard_id }}"
          data-roles='@json(($row->pics ?? collect())->pluck("role_id")->values())'>
          <i class="ph-pencil"></i>
        </button>
        <form method="POST" action="{{ route('admin.ami.indicator.destroy', $row->id) }}" onsubmit="return confirm('Hapus indikator ini?');">
          @csrf @method('DELETE')
          <button type="submit" class="btn btn-danger btn-icon" title="Hapus">
            <i class="ph-trash"></i>
          </button>
        </form>
      </div>
    </td>
  </tr>
@empty
  <tr>
    <td colspan="6" class="text-center text-muted py-4">Belum ada data.</td>
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
    @else
      <div class="card-footer d-flex align-items-center">
        <span class="text-muted">Total {{ $rows->total() }} entri</span>
      </div>
    @endif
  </div>
</div>

{{-- MODAL: CREATE --}}
<div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="{{ route('admin.ami.indicator.store') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Tambah Indikator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        @if(isset($selectedStandard) && $selectedStandard)
          <input type="hidden" name="standard_id" value="{{ $selectedStandard->id }}">
          <div class="mb-3">
            <label class="form-label">Standar</label>
            <input type="text" class="form-control"
                   value="{{ $selectedStandard->name }}@if(optional($selectedStandard->academicConfig)->academic_code) (TA {{ $selectedStandard->academicConfig->academic_code }})@endif"
                   disabled>
          </div>
        @else
          <div class="mb-3">
            <label class="form-label">Standar (TA aktif)</label>
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
            <div class="form-text">Daftar hanya menampilkan standar dari tahun akademik aktif.</div>
          </div>
        @endif

        <div class="mb-3">
          <label class="form-label">Deskripsi Indikator</label>
          <textarea name="description" id="create_description" class="form-control summernote" rows="6" required placeholder="Tulis deskripsi indikator..."></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Role PIC <small class="text-muted">(pilih satu atau lebih)</small></label>
          <select name="role_ids[]" class="form-select" multiple required>
            @foreach($roles as $r)
              <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
          </select>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- MODAL: EDIT --}}
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" id="formEdit" class="modal-content">
      @csrf
      @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title">Edit Indikator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">

        <div class="mb-3">
          <label class="form-label">Standar (TA aktif)</label>
          <select name="standard_id" id="edit_standard_id" class="form-select" required>
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

        <div class="mb-3">
          <label class="form-label">Deskripsi Indikator</label>
          <textarea name="description" id="edit_description" class="form-control summernote" rows="6" required></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Role PIC <small class="text-muted">(pilih satu atau lebih)</small></label>
          <select name="role_ids[]" id="edit_role_ids" class="form-select" multiple required>
            @foreach($roles as $r)
              <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
          </select>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

{{-- MODAL: FULL DESKRIPSI --}}
<div class="modal fade" id="modalDesc" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Deskripsi Indikator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div id="modalDesc_body" class="mb-0" style="white-space: normal;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
  <style>
    /* Biar header makin rapih */
    .card-header .form-select,
    .card-header .form-control { min-height: 34px; }
    .card-header .btn { --bs-btn-padding-y: .25rem; --bs-btn-padding-x: .6rem; }
    .vr { height: 24px; }
  </style>
@endpush

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>

  <script>
  (function () {
    function initEditors() {
      const $editors = $('.summernote');
      if ($editors.data('summernote')) return;
      $editors.summernote({
        placeholder: 'Tulis deskripsi indikator...',
        height: 260,
        toolbar: [
          ['style', ['bold', 'italic', 'underline', 'clear']],
          ['para',  ['ul', 'ol', 'paragraph']],
          ['insert',['link']],
          ['view',  ['codeview']]
        ]
      });
    }
    document.addEventListener('DOMContentLoaded', initEditors);

    // Dropdown per page
    window.setPerPage = function(pp) {
      document.getElementById('perPageInput').value = pp;
      document.getElementById('filtersForm').submit();
    };

    // Client-side search
    const inputFilter = document.getElementById('searchIndicator');
    const btnReset    = document.getElementById('btnResetFilterI');
    const table       = document.getElementById('tableIndicator');

    function applyFilter() {
      const q = (inputFilter.value || '').trim().toLowerCase();
      const rows = table.querySelectorAll('tbody tr');

      rows.forEach(tr => {
        const std = (tr.querySelector('.td-std')?.textContent || '').toLowerCase();
        const ac  = (tr.querySelector('.td-ac')?.textContent || '').toLowerCase();
        const ds  = (tr.querySelector('.td-desc')?.textContent || '').toLowerCase();
        const pc  = (tr.querySelector('.td-pic')?.textContent || '').toLowerCase();
        tr.style.display = (std.includes(q) || ac.includes(q) || ds.includes(q) || pc.includes(q)) ? '' : 'none';
      });
    }

    inputFilter?.addEventListener('input', applyFilter);
    btnReset?.addEventListener('click', () => {
      inputFilter.value = '';
      applyFilter();
    });

    // Modal Edit
    var modalEdit = document.getElementById('modalEdit');
    var formEdit  = document.getElementById('formEdit');

    modalEdit.addEventListener('show.bs.modal', function (ev) {
      initEditors();
      var btn = ev.relatedTarget;
      formEdit.action = btn.getAttribute('data-update') || '#';

      var std = btn.getAttribute('data-standard') || '';
      var sel = document.getElementById('edit_standard_id');
      Array.from(sel.options).forEach(function(opt){ opt.selected = (opt.value == std); });

      var b64 = btn.getAttribute('data-desc-html') || '';
      var html = '';
      try { html = b64 ? atob(b64) : ''; } catch(e) { html = ''; }
      $('#edit_description').summernote('code', html);

      var editRoles = document.getElementById('edit_role_ids');
      var current = [];
      try { current = JSON.parse(btn.getAttribute('data-roles') || '[]'); } catch(e) { current = []; }
      Array.from(editRoles.options).forEach(function(opt){
        opt.selected = current.includes(opt.value) || current.includes(parseInt(opt.value));
      });
    });

    // Modal full deskripsi
    var modalDesc = document.getElementById('modalDesc');
    var modalDescBody = document.getElementById('modalDesc_body');
    modalDesc.addEventListener('show.bs.modal', function (ev) {
      var btn = ev.relatedTarget;
      var b64 = btn.getAttribute('data-desc-html') || '';
      try { modalDescBody.innerHTML = b64 ? atob(b64) : ''; } catch(e) { modalDescBody.textContent = ''; }
    });
  })();
  </script>
@endpush
