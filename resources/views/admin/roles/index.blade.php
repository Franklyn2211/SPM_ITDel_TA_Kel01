@extends('admin.layouts.app')

@section('title', 'Users Management - Admin Sistem Penjaminan Mutu')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex">
      <h4 class="page-title mb-0">Users Management</h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center">
        <form method="POST" action="{{ route('admin.cis.sync') }}" class="me-2">
          @csrf
          <button type="submit" class="btn btn-primary btn-sm rounded-pill">
            <i class="ph-arrows-clockwise me-2"></i> Sync CIS
          </button>
        </form>
        <form class="d-none d-lg-block" method="GET" action="{{ url()->current() }}">
          <div class="input-group input-group-sm">
            <input type="text" class="form-control" name="q" placeholder="Cari nama/username/email..."
                   value="{{ request('q', request('search')) }}">
            @if(request()->filled('q') || request()->filled('search'))
              <a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reset</a>
            @endif
            <button class="btn btn-primary" type="submit">
              <i class="ph-magnifying-glass"></i>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <span class="breadcrumb-item active">Users Management</span>
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
        <i class="ph-check-circle me-2"></i>{{ session('success') }}
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger border-0 alert-dismissible fade show">
      <div><strong>Gagal menyimpan:</strong></div>
      <ul class="mb-0 mt-1">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="card">
    <div class="card-header d-flex align-items-center">
      <h5 class="mb-0">Daftar Users</h5>
      <form class="ms-auto d-lg-none" method="GET" action="{{ url()->current() }}">
        <div class="input-group input-group-sm" style="max-width:360px;">
          <input type="text" class="form-control" name="q" placeholder="Cari nama/username/email..."
                 value="{{ request('q', request('search')) }}">
          @if(request()->filled('q') || request()->filled('search'))
            <a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reset</a>
          @endif
          <button class="btn btn-primary" type="submit"><i class="ph-magnifying-glass"></i></button>
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table text-nowrap table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:50px;">No</th>
            <th>Username</th>
            <th>Nama</th>
            <th>Email</th>
            <th>Role & Detail Kategori</th>
            <th class="text-center" style="width:120px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        @forelse ($users as $i => $u)
          @php
            $grouped = $u->roles
              ->groupBy('academic_config_id')
              ->map(function($items){
                  $first = $items->first();
                  $ac    = $first?->academicConfig;
                  return [
                      'ac_id'     => $ac?->id,
                      'ac_code'   => $ac?->academic_code,
                      'roles'     => $items->pluck('role.name')->filter()->values(),
                      'role_ids'  => $items->pluck('role_id')->values(),
                    'c_detail_id' => $items->pluck('category_detail_id')->values(),
                  ];
              })
              ->values();

            $firstRole      = $u->roles->first();
            $defaultAcId    = $firstRole?->academicConfig?->id;
            $defaultCdId    = $firstRole?->category_detail_id;
            $defaultRoleIds = $defaultAcId
              ? $u->roles->where('academic_config_id',$defaultAcId)->pluck('role_id')->values()
              : collect();
          @endphp

          <tr>
            <td class="text-center">{{ ($users->firstItem() ?? 0) + $i }}</td>
            <td><code>{{ $u->username }}</code></td>
            <td>{{ $u->name }}</td>
            <td>{{ $u->email }}</td>
            <td>
              @if($grouped->count())
                <div class="d-flex flex-column gap-1">
                  @foreach($grouped as $g)
                    <div>
                      <span class="badge bg-secondary me-1">TA {{ $g['ac_code'] ?? '-' }}</span>
                      @foreach($g['roles'] as $rn)
                        <span class="badge bg-primary">{{ $rn }}</span>
                      @endforeach
                        {{-- Detail Kategori --}}
                        @foreach($g['c_detail_id'] as $cdId)
                          <span class="badge bg-info text-dark">{{ $categoryDetail->firstWhere('id', $cdId)->name ?? '-' }}</span>
                        @endforeach
                    </div>
                  @endforeach
                </div>
              @else
                <span class="text-muted">Belum di-assign</span>
              @endif
            </td>
            <td class="text-center">
              <button
                type="button"
                class="btn btn-sm btn-primary rounded-pill"
                data-bs-toggle="modal"
                data-bs-target="#modalAssign"
                data-cis="{{ $u->cis_user_id }}"  {{-- WAJIB: hanya cis_user_id --}}
              >
                {{ $grouped->count() ? 'Ubah' : 'Assign' }}
              </button>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted">Belum ada data user</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @if($users->hasPages())
    <div class="card-footer d-flex align-items-center">
      <span class="text-muted me-auto">
        Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} entries
      </span>
      <div>{{ $users->onEachSide(1)->appends(request()->only('q','search'))->links() }}</div>
    </div>
    @endif
  </div>
</div>

{{-- Modal Assign/Ubah --}}
<div class="modal fade" id="modalAssign" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('admin.users.assign-role') }}" class="modal-content">
      @csrf
      <input type="hidden" name="cis_user_id" id="assign_cis_user_id">

      <div class="modal-header">
        <h5 class="modal-title">Assign / Ubah Role</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Tahun Akademik</label>
          <select name="academic_config_id" id="assign_academic" class="form-select" required>
            <option value="" selected disabled>Pilih tahun akademik…</option>
            @foreach($academics as $ac)
              <option value="{{ $ac->id }}">{{ $ac->academic_code }}</option>
            @endforeach
          </select>
          @error('academic_config_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
          <label class="form-label">Detail Kategori</label>
          <select name="category_detail_id" id="assign_category_detail" class="form-select" required>
            <option value="" selected disabled>Pilih detail kategori…</option>
            @foreach($categoryDetail as $cd)
              <option value="{{ $cd->id }}">{{ $cd->name }}</option>
            @endforeach
          </select>
          @error('category_detail_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
<div class="mb-3">
  <label class="form-label">Role (bisa pilih lebih dari satu)</label>
  <select name="role_ids[]" id="assign_roles" class="form-select" multiple required size="6">
    @foreach($roles as $r)
      @php $cat = $r->category?->name; @endphp
      <option value="{{ $r->id }}">
        {{ $r->name }}@if($cat) ({{ $cat }}) @endif
      </option>
    @endforeach
  </select>
  @error('role_ids') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
  @error('role_ids.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</div>


      <div class="modal-footer">
        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
{{-- Select2 opsional; form tetap jalan walau tidak ada --}}
<script>
  (function () {
    const assignModalEl = document.getElementById('modalAssign');

    if (!assignModalEl) return;

    // Helper: inisialisasi Select2 kalau tersedia
    function tryInitSelect2() {
      if (window.jQuery && window.$ && $.fn && $.fn.select2) {
        const $m = $('#modalAssign');

        // Destroy dulu biar tidak double-initialize
        $m.find('#assign_academic, #assign_roles').each(function(){
          if ($(this).hasClass('select2-hidden-accessible')) {
            $(this).select2('destroy');
          }
        });

        $('#assign_academic').select2({
          dropdownParent: $m,
          width: '100%',
          allowClear: true,
          placeholder: 'Pilih tahun akademik…',
          minimumResultsForSearch: 0
        });

        $('#assign_roles').attr('multiple','multiple').select2({
          dropdownParent: $m,
          width: '100%',
          closeOnSelect: false,
          allowClear: true,
          multiple: true,
          placeholder: 'Pilih role…',
          minimumResultsForSearch: 0
        }).on('select2:select', function(){ $(this).select2('open'); });
      }
    }

    assignModalEl.addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
      if (!btn) return;

      // 1) SET cis_user_id SEBELUM yang lain (tanpa ketergantungan select2/jQuery)
      const cis = btn.getAttribute('data-cis') || '';
      document.getElementById('assign_cis_user_id').value = cis;

      // 2) Bersihkan pilihan default (agar tidak membawa dari user lain)
      const acSel   = document.getElementById('assign_academic');
      const rolesEl = document.getElementById('assign_roles');

      if (acSel)   acSel.value = '';
      if (rolesEl) Array.from(rolesEl.options).forEach(o => o.selected = false);

      // 3) Inisialisasi Select2 jika ada (tidak wajib)
      tryInitSelect2();
    });

    // Jika ada error validasi, buka kembali modal dan restore nilai lama
    @if ($errors->any())
      document.addEventListener('DOMContentLoaded', function () {
        const m = new bootstrap.Modal(assignModalEl);
        m.show();

        // Restore nilai lama
        const oldCis = @json(old('cis_user_id', ''));
        const oldAc  = @json(old('academic_config_id', ''));
        const oldCd  = @json(old('category_detail_id', ''));
        const oldRs  = @json(old('role_ids', []));

        document.getElementById('assign_cis_user_id').value = oldCis;

        const acSel   = document.getElementById('assign_academic');
        const rolesEl = document.getElementById('assign_roles');

        if (acSel)   acSel.value = oldAc || '';
        if (rolesEl && Array.isArray(oldRs)) {
          Array.from(rolesEl.options).forEach(o => o.selected = oldRs.includes(o.value));
        }

        tryInitSelect2();
        if (window.jQuery && $.fn.select2) {
          $('#assign_academic').val(oldAc || '').trigger('change');
          $('#assign_roles').val(oldRs || []).trigger('change');
        }
      });
    @endif
  })();
</script>
@endpush
@endsection
