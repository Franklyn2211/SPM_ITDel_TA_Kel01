{{-- resources/views/admin/ami/indicator.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'AMI · Indikator')

@section('content')
@php $perPage = (int) request('per_page', $rows->perPage() ?: 10); @endphp

<div class="content">

  {{-- Header + Filter + Action --}}
  <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h4 mb-0">Indikator AMI</h1>

    <div class="ms-auto d-flex align-items-center flex-wrap gap-2">
      <form method="GET" action="{{ route('admin.ami.indicator') }}" class="d-flex align-items-center gap-2">
        <select class="form-select" name="standard_id" onchange="this.form.submit()" style="min-width: 320px;">
          <option value="">Semua Standar (TA aktif)</option>
          @foreach($standards as $s)
            <option value="{{ $s->id }}" {{ request('standard_id') == $s->id ? 'selected' : '' }}>
              {{ $s->name }}
              @if(optional($s->academicConfig)->academic_code)
                (TA {{ $s->academicConfig->academic_code }})
              @endif
            </option>
          @endforeach
        </select>

        <select name="per_page" class="form-select" onchange="this.form.submit()">
          @foreach([10,25,50,100] as $pp)
            <option value="{{ $pp }}" {{ $perPage == $pp ? 'selected' : '' }}>{{ $pp }}/hal</option>
          @endforeach
        </select>

        @if(request()->has('standard_id') || request()->has('per_page'))
          <a href="{{ route('admin.ami.indicator') }}" class="btn btn-light">Reset</a>
        @endif
      </form>

      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">
        <i class="ph-plus me-2"></i>Tambah Indikator
      </button>
    </div>
  </div>

  {{-- Info filter aktif --}}
  @if($selectedStandard)
    <div class="alert alert-info d-flex align-items-center">
      <i class="ph-info me-2"></i>
      <div>
        <strong>Filter:</strong> Standar {{ $selectedStandard->name }}
        @if(optional($selectedStandard->academicConfig)->academic_code)
          <span class="badge bg-secondary ms-1">TA {{ $selectedStandard->academicConfig->academic_code }}</span>
        @endif
      </div>
    </div>
  @endif

  {{-- Tabel Indikator --}}
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width:70px;">No</th>
            <th style="width:320px;">Standar</th>
            <th>Deskripsi Indikator</th>
            <th style="width:260px;">PIC (Role)</th>
            <th class="text-center" style="width:210px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
          @php $desc = $row->description ?? ''; @endphp
          <tr>
            <td class="text-center align-top">{{ $rows->firstItem() + $loop->index }}</td>

            <td class="align-top">
              <div class="fw-semibold">{{ $row->standard->name ?? '-' }}</div>
              @if(optional($row->standard?->academicConfig)->academic_code)
                <div class="text-muted small">TA {{ $row->standard->academicConfig->academic_code }}</div>
              @endif
            </td>

            <td class="align-top">
              <div class="mb-1">{{ \Illuminate\Support\Str::limit($desc, 280) }}</div>
              @if(mb_strlen($desc) > 280)
                <button type="button"
                        class="btn btn-link p-0 small"
                        data-bs-toggle="modal"
                        data-bs-target="#modalDesc"
                        data-desc="{{ e($desc) }}">
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
              <div class="btn-group">
                <button
                  type="button"
                  class="btn btn-teal btn-sm"
                  title="Kelola PIC"
                  data-bs-toggle="modal"
                  data-bs-target="#modalPic"
                  data-update="{{ route('admin.ami.pic.update', $row->id) }}"
                  data-store="{{ route('admin.ami.pic.store', $row->id) }}"
                  data-roles='@json(($row->pics ?? collect())->pluck("role_id")->values())'>
                  <i class="ph-users me-1"></i> PIC
                </button>

                <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                  <span class="visually-hidden">Toggle</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li>
                    <a href="#" class="dropdown-item"
                       data-bs-toggle="modal"
                       data-bs-target="#modalEdit"
                       data-update="{{ route('admin.ami.indicator.update', $row->id) }}"
                       data-desc="{{ e($desc) }}"
                       data-standard="{{ $row->standard_id }}">
                      <i class="ph-pencil-simple-line me-2"></i>Edit
                    </a>
                  </li>
                  <li><hr class="dropdown-divider"></li>
                  <li>
                    <form method="POST" action="{{ route('admin.ami.indicator.destroy', $row->id) }}"
                          onsubmit="return confirm('Hapus indikator ini?');">
                      @csrf @method('DELETE')
                      <button type="submit" class="dropdown-item text-danger">
                        <i class="ph-trash me-2"></i>Hapus
                      </button>
                    </form>
                  </li>
                </ul>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted py-4">Belum ada data.</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="text-muted small">
        Menampilkan {{ $rows->count() }} dari total {{ $rows->total() }} data
      </div>
      <div>
        {{ $rows->onEachSide(1)->links() }}
      </div>
    </div>
  </div>
</div>

{{-- ========================= MODAL: CREATE ========================= --}}
<div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="{{ route('admin.ami.indicator.store') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Tambah Indikator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">

        @if($selectedStandard)
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
          <textarea name="description" class="form-control" rows="4" required placeholder="Tulis deskripsi indikator..."></textarea>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- ========================= MODAL: EDIT ========================= --}}
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
          <textarea name="description" id="edit_description" class="form-control" rows="4" required></textarea>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

{{-- ========================= MODAL: KELOLA PIC ========================= --}}
<div class="modal fade" id="modalPic" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" id="formPic" class="modal-content">
      @csrf
      <input type="hidden" name="_method" id="formPic_method" value="POST">
      <div class="modal-header">
        <h5 class="modal-title">Kelola PIC Indikator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Role (PIC) <small class="text-muted">(bisa lebih dari 1)</small></label>
          <select name="role_ids[]" id="formPic_roles" class="form-control" multiple required>
            @foreach($roles as $r)
              <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
          </select>
          <div class="form-text">Pilih satu atau beberapa role yang menjadi PIC aktif untuk indikator ini.</div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" id="formPic_submit_btn" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- ========================= MODAL: FULL DESKRIPSI ========================= --}}
<div class="modal fade" id="modalDesc" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Deskripsi Indikator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <pre class="mb-0" id="modalDesc_body" style="white-space: pre-wrap;"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

</div>
@endsection

@push('scripts')
<script>
(function () {
  // EDIT modal
  var modalEdit = document.getElementById('modalEdit');
  var formEdit  = document.getElementById('formEdit');
  var editDesc  = document.getElementById('edit_description');
  var editStd   = document.getElementById('edit_standard_id');

  modalEdit.addEventListener('show.bs.modal', function (ev) {
    var btn = ev.relatedTarget;
    formEdit.action = btn.getAttribute('data-update') || '#';
    editDesc.value  = btn.getAttribute('data-desc') || '';
    var std = btn.getAttribute('data-standard') || '';
    if (std) {
      Array.from(editStd.options).forEach(function(opt){ opt.selected = (opt.value == std); });
    }
  });

  // PIC modal
  var modalPic = document.getElementById('modalPic');
  var formPic  = document.getElementById('formPic');
  var methodEl = document.getElementById('formPic_method');
  var rolesEl  = document.getElementById('formPic_roles');

  modalPic.addEventListener('show.bs.modal', function (ev) {
    var btn = ev.relatedTarget;
    var updateUrl = btn.getAttribute('data-update');
    var storeUrl  = btn.getAttribute('data-store');
    var current   = [];
    try { current = JSON.parse(btn.getAttribute('data-roles') || '[]'); } catch(e) { current = []; }

    if (current.length > 0) { formPic.action = updateUrl; methodEl.value = 'PUT'; }
    else { formPic.action = storeUrl; methodEl.value = 'POST'; }

    Array.from(rolesEl.options).forEach(function(opt){
      opt.selected = current.includes(parseInt(opt.value));
    });
  });

  // Full description modal
  var modalDesc = document.getElementById('modalDesc');
  var modalDescBody = document.getElementById('modalDesc_body');
  modalDesc.addEventListener('show.bs.modal', function (ev) {
    var btn = ev.relatedTarget;
    modalDescBody.textContent = btn.getAttribute('data-desc') || '';
  });
})();
</script>
@endpush
