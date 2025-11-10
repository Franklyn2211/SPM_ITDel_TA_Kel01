{{-- resources/views/auditee/fed/index.blade.php --}}
@extends('auditee.layouts.app')
@section('title', 'Formulir Evaluasi Diri (AMI)')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex">
      <h4 class="page-title mb-0">Formulir Evaluasi Diri (AMI)</h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center gap-2">
        @if(!$form)
          <button type="button" class="btn btn-primary btn-sm rounded-pill"
                  data-bs-toggle="modal" data-bs-target="#modalCreateFed">
            <i class="ph-plus me-2"></i> Buat Form FED
          </button>
        @else
          <button type="button" class="btn btn-warning btn-sm rounded-pill"
                  data-bs-toggle="modal" data-bs-target="#modalEditHeader"
                  @if(($form->status->name ?? '') === 'Dikirim') disabled @endif>
            <i class="ph-pencil me-2"></i> Edit Data Auditee
          </button>

          <form method="POST" action="{{ route('auditee.fed.submit', $form) }}"
                onsubmit="return confirm('Kirim Form Evaluasi Diri sekarang? Setelah dikirim tidak dapat diedit.');" class="d-inline">
            @csrf
            <button class="btn btn-success btn-sm rounded-pill"
              @if(($form->status->name ?? '') === 'Dikirim' || ($progress['total'] ?? 0) === 0 || ($progress['terisi'] ?? 0) < ($progress['total'] ?? 0))
                disabled
              @endif>
              <i class="ph-paper-plane-tilt me-2"></i> Submit
            </button>
          </form>

          @if($form && ($form->status->name ?? '') === 'Dikirim')
          <a href="{{ route('auditee.fed.export', $form) }}" class="btn btn-outline-secondary btn-sm rounded-pill">
              <i class="ph-file-doc me-2"></i> Unduh Dokumen FED (DOCX)
          </a>
          @endif
        @endif
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('auditee.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <span class="breadcrumb-item active">Formulir Evaluasi Diri</span>
      </div>
      <div class="ms-auto d-flex align-items-center text-muted">
        @if($academic)
          <div class="me-3"><i class="ph-calendar me-1"></i> {{ $academic->name ?? ($academic->tahun ?? '-') }}</div>
        @endif
        @if($form)
            @php
                $statusName = $form->status->name ?? 'Draft';
                $badgeClass = match($statusName) {
                    'Disetujui' => 'bg-success',
                    'Dikirim'   => 'bg-info',
                    default     => 'bg-secondary',
                };
            @endphp
          <span class="badge {{ $badgeClass }} rounded-pill">{{ $statusName }}</span>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">

  {{-- Flash & errors --}}
  @foreach (['success','info','warning'] as $f)
    @if (session($f))
      <div class="alert alert-{{ $f === 'success' ? 'success' : ($f === 'warning' ? 'warning' : 'info') }} border-0 alert-dismissible fade show">
        <div class="d-flex align-items-center">
          <i class="ph-{{ $f === 'success' ? 'check-circle' : ($f === 'warning' ? 'warning' : 'info') }} me-2"></i>
          {{ session($f) }}
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif
  @endforeach

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

  {{-- Ringkas atas --}}
  <div class="card mb-3">
    <div class="card-body row g-3 align-items-center">
      <div class="col-md-4">
        <div class="text-muted fs-sm">Tahun Akademik</div>
        <div class="fw-semibold">{{ $academic->name ?? ($academic->tahun ?? '-') }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted fs-sm">Unit/Prodi</div>
        <div class="fw-semibold">{{ $categoryDetailName ?? ($form->categoryDetail->name ?? '-') }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted fs-sm d-flex align-items-center">
          Progress
          @if($form && $form->submitted_at)
            <span class="ms-2 badge bg-success">Dikirim {{ \Illuminate\Support\Carbon::parse($form->submitted_at)->translatedFormat('d M Y') }}</span>
          @endif
        </div>
        <div class="d-flex align-items-center">
          <div class="flex-grow-1 me-3">
            <div class="progress" style="height:10px;">
              <div class="progress-bar" style="width: {{ $progress['percent'] ?? 0 }}%"></div>
            </div>
          </div>
          <div class="fw-semibold">{{ $progress['terisi'] ?? 0 }}/{{ $progress['total'] ?? 0 }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Tabel butir + search --}}
  <div class="card">
    <div class="card-header d-flex align-items-center">
      <h5 class="mb-0">Daftar Butir Evaluasi Diri</h5>
      <div class="ms-auto">
        <input type="text" id="searchFed" class="form-control form-control-sm" placeholder="Cari butir..." style="max-width: 320px;">
      </div>
    </div>

    @if(!$form)
      <div class="card-body">
        <div class="alert alert-info mb-0">
          Belum ada Form Evaluasi Diri untuk tahun/prodi ini. Klik <strong>Buat Form FED</strong> di kanan atas.
        </div>
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover align-middle" id="tableFed">
          <thead class="table-light">
            <tr>
              <th class="text-center" style="width: 50px;">No</th>
              <th style="min-width: 300px;">Standar & Indikator</th>
              <th class="text-center" style="width: 100px;">Status</th>
              <th class="text-center" style="width: 100px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($details as $d)
              @php
                $readOnly = ($form->status->name ?? '') === 'Dikirim';
                $isFilled = !is_null($d->standard_achievement_id) || (isset($d->result) && trim($d->result) !== '');
              @endphp
              <tr id="detail-{{ $d->id }}">
                <td class="text-center align-top">{{ ($details->currentPage() - 1) * $details->perPage() + $loop->iteration }}</td>

                <td class="td-standar">
                  <div class="fw-semibold text-primary mb-1">
                    {{ optional($d->indicator->standard)->name ?? 'Standar' }}
                  </div>
                  @php $descHtml = $d->indicator->description ?? ''; @endphp
                  <div class="text-muted small" style="white-space: normal;">{!! $descHtml !!}</div>
                </td>

                <td class="text-center align-top">
                  @if($isFilled)
                    <span class="badge bg-success">Terisi</span>
                  @else
                    <span class="badge bg-secondary">Kosong</span>
                  @endif
                </td>

                <td class="text-center align-top">
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#modalIsiFed"
                    data-detail-id="{{ $d->id }}"
                    data-form-id="{{ $form->id }}"
                    data-update-url="{{ route('auditee.fed.updateDetail', [$form, $d]) }}"
                    data-ketercapaian="{{ $d->standard_achievement_id ?? '' }}"
                    data-hasil="{{ e($d->result ?? '') }}"
                    data-bukti="{{ e($d->supporting_evidence ?? '') }}"
                    data-faktor="{{ e($d->contributing_factors ?? '') }}"
                    data-pos-template="{{ e($d->indicator->positive_result_template ?? '') }}"
                    data-neg-template="{{ e($d->indicator->negative_result_template ?? '') }}"
                    @if($readOnly) disabled @endif>
                    <i class="ph-pencil me-1"></i> {{ $isFilled ? 'Edit' : 'Isi' }}
                  </button>
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted">Belum ada indikator untuk tahun/prodi ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($details->hasPages())
        <div class="card-footer d-flex align-items-center">
          <span class="text-muted me-auto">
            Menampilkan {{ $details->firstItem() }} - {{ $details->lastItem() }} dari {{ $details->total() }} indikator
          </span>
          <div>
            {{ $details->onEachSide(1)->links() }}
          </div>
        </div>
      @endif
    @endif
  </div>
</div>

{{-- =============== MODALS =============== --}}

{{-- Create FED --}}
<div class="modal fade" id="modalCreateFed" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="{{ route('auditee.fed.store') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Buat Form Evaluasi Diri</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          {{-- Ketua --}}
          <div class="col-md-6">
            <label class="form-label">Ketua Auditee (opsional)</label>
            <input type="text"
                   name="ketua_auditee_nama"
                   class="form-control"
                   placeholder="Nama Ketua"
                   value="{{ old('ketua_auditee_nama', $defaultHeadName ?? '') }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Ketua (opsional)</label>
            <input type="text"
                   name="ketua_auditee_jabatan"
                   class="form-control"
                   placeholder="Jabatan"
                   value="{{ old('ketua_auditee_jabatan', $defaultHeadPosition ?? '') }}">
          </div>

          {{-- Anggota 1 --}}
          <div class="col-md-6">
            <label class="form-label">Anggota 1 (Search Users)</label>
            <select name="member_auditee_1_user_id"
                    id="member_auditee_1_user_id"
                    class="form-control form-control-select2 select-user-ajax"
                    data-placeholder="Cari nama user..."
                    data-url="{{ route('auditee.fed.searchUsers') }}">
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 1</label>
            <select name="anggota_auditee_jabatan_satu"
                    class="form-select">
              <option value="">Pilih jabatan…</option>
              @foreach($roles as $r)
                <option value="{{ $r->name }}"
                  {{ old('anggota_auditee_jabatan_satu') == $r->name ? 'selected' : '' }}>
                  {{ $r->name }}
                </option>
              @endforeach
            </select>
          </div>

          {{-- Anggota 2 --}}
          <div class="col-md-6">
            <label class="form-label">Anggota 2 (Search Users)</label>
            <select name="member_auditee_2_user_id"
                    id="member_auditee_2_user_id"
                    class="form-control form-control-select2 select-user-ajax"
                    data-placeholder="Cari nama user..."
                    data-url="{{ route('auditee.fed.searchUsers') }}">
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 2</label>
            <select name="anggota_auditee_jabatan_dua"
                    class="form-select">
              <option value="">Pilih jabatan…</option>
              @foreach($roles as $r)
                <option value="{{ $r->name }}"
                  {{ old('anggota_auditee_jabatan_dua') == $r->name ? 'selected' : '' }}>
                  {{ $r->name }}
                </option>
              @endforeach
            </select>
          </div>

          {{-- Anggota 3 --}}
          <div class="col-md-6">
            <label class="form-label">Anggota 3 (Search Users)</label>
            <select name="member_auditee_3_user_id"
                    id="member_auditee_3_user_id"
                    class="form-control form-control-select2 select-user-ajax"
                    data-placeholder="Cari nama user..."
                    data-url="{{ route('auditee.fed.searchUsers') }}">
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 3</label>
            <select name="anggota_auditee_jabatan_tiga"
                    class="form-select">
              <option value="">Pilih jabatan…</option>
              @foreach($roles as $r)
                <option value="{{ $r->name }}"
                  {{ old('anggota_auditee_jabatan_tiga') == $r->name ? 'selected' : '' }}>
                  {{ $r->name }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="col-12">
            <div class="alert alert-info mb-0">
              Sistem otomatis menggunakan <strong>Unit/Prodi</strong> akun Anda dan <strong>Tahun Akademik aktif</strong> dari Admin.
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan & Buat Butir</button>
      </div>
    </form>
  </div>
</div>

{{-- Edit header --}}
@if($form)
<div class="modal fade" id="modalEditHeader" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="{{ route('auditee.fed.updateHeader', $form) }}" class="modal-content">
      @csrf @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title">Edit Data Auditee</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          {{-- Ketua --}}
          <div class="col-md-6">
            <label class="form-label">Ketua Auditee (opsional)</label>
            <input type="text"
                   name="ketua_auditee_nama"
                   class="form-control"
                   placeholder="Nama Ketua"
                   value="{{ old('ketua_auditee_nama', $form->head_auditee_name) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Ketua (opsional)</label>
            <input type="text"
                   name="ketua_auditee_jabatan"
                   class="form-control"
                   placeholder="Jabatan"
                   value="{{ old('ketua_auditee_jabatan', $form->head_auditee_position) }}">
          </div>

          {{-- Anggota 1 --}}
          <div class="col-md-6">
            <label class="form-label">Anggota 1 (Search Users)</label>
            <select name="member_auditee_1_user_id"
                    id="edit_member_auditee_1_user_id"
                    class="form-control form-control-select2 select-user-ajax"
                    data-placeholder="Cari nama user..."
                    data-url="{{ route('auditee.fed.searchUsers') }}">
            </select>
            <small class="text-muted">Saat ini: {{ $form->member_auditee_1_name ?? '-' }}</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 1</label>
            <select name="anggota_auditee_jabatan_satu"
                    class="form-select">
              <option value="">Pilih jabatan…</option>
              @foreach($roles as $r)
                <option value="{{ $r->name }}"
                  {{ old('anggota_auditee_jabatan_satu',$form->member_auditee_1_position) == $r->name ? 'selected' : '' }}>
                  {{ $r->name }}
                </option>
              @endforeach
            </select>
          </div>

          {{-- Anggota 2 --}}
          <div class="col-md-6">
            <label class="form-label">Anggota 2 (Search Users)</label>
            <select name="member_auditee_2_user_id"
                    id="edit_member_auditee_2_user_id"
                    class="form-control form-control-select2 select-user-ajax"
                    data-placeholder="Cari nama user..."
                    data-url="{{ route('auditee.fed.searchUsers') }}">
            </select>
            <small class="text-muted">Saat ini: {{ $form->member_auditee_2_name ?? '-' }}</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 2</label>
            <select name="anggota_auditee_jabatan_dua"
                    class="form-select">
              <option value="">Pilih jabatan…</option>
              @foreach($roles as $r)
                <option value="{{ $r->name }}"
                  {{ old('anggota_auditee_jabatan_dua',$form->member_auditee_2_position) == $r->name ? 'selected' : '' }}>
                  {{ $r->name }}
                </option>
              @endforeach
            </select>
          </div>

          {{-- Anggota 3 --}}
          <div class="col-md-6">
            <label class="form-label">Anggota 3 (Search Users)</label>
            <select name="member_auditee_3_user_id"
                    id="edit_member_auditee_3_user_id"
                    class="form-control form-control-select2 select-user-ajax"
                    data-placeholder="Cari nama user..."
                    data-url="{{ route('auditee.fed.searchUsers') }}">
            </select>
            <small class="text-muted">Saat ini: {{ $form->member_auditee_3_name ?? '-' }}</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 3</label>
            <select name="anggota_auditee_jabatan_tiga"
                    class="form-select">
              <option value="">Pilih jabatan…</option>
              @foreach($roles as $r)
                <option value="{{ $r->name }}"
                  {{ old('anggota_auditee_jabatan_tiga',$form->member_auditee_3_position) == $r->name ? 'selected' : '' }}>
                  {{ $r->name }}
                </option>
              @endforeach
            </select>
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>
@endif

{{-- MODAL: FULL DESKRIPSI INDIKATOR --}}
<div class="modal fade" id="modalDesc" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDesc_title">Deskripsi Indikator</h5>
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

{{-- MODAL: ISI/EDIT DETAIL FED (WIZARD) --}}
<div class="modal fade" id="modalIsiFed" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <form method="POST" id="formIsiFed" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title">Isi/Edit Butir Evaluasi Diri</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        {{-- indikator step --}}
        <div class="mb-3">
          <div class="fw-semibold mb-2">Step <span id="fedCurrentStep">1</span> / 4</div>
          <div class="progress mb-3" style="height: 6px;">
            <div class="progress-bar" id="fedStepProgress" style="width: 25%;"></div>
          </div>
        </div>

        <div class="fed-steps">
          {{-- STEP 1: Ketercapaian --}}
          <div class="fed-step" data-step="1">
            <div class="mb-3">
              <label class="form-label fw-semibold">Ketercapaian Standard</label>
              <div class="d-flex flex-column gap-2">
                @foreach($opsiKetercapaian as $op)
                  @php
                    $nameLower = strtolower($op->name);
                    $templateType = in_array($nameLower, ['melampaui','mencapai']) ? 'pos' : 'neg';
                  @endphp
                  <label class="d-flex align-items-center gap-2">
                    <input type="radio"
                           name="ketercapaian_standard_id"
                           value="{{ $op->id }}"
                           id="ketercapaian_{{ $op->id }}"
                           data-template-type="{{ $templateType }}">
                    <span>{{ $op->name }}</span>
                  </label>
                @endforeach
              </div>
              <div class="text-muted small mt-2">
                Pilih ketercapaian dulu. Kalau pilih “Melampaui/Mencapai”, hasil pelaksanaan otomatis pakai template positif; kalau “Tidak Mencapai/Menyimpang” pakai template negatif.
              </div>
            </div>
          </div>

          {{-- STEP 2: Hasil --}}
          <div class="fed-step d-none" data-step="2">
            <div class="mb-3">
              <label class="form-label fw-semibold">Hasil Pelaksanaan</label>
              <textarea name="hasil" id="modal_hasil" class="form-control summernote-fed"></textarea>
            </div>
          </div>

          {{-- STEP 3: Bukti --}}
          <div class="fed-step d-none" data-step="3">
            <div class="mb-3">
              <label class="form-label fw-semibold">Bukti/Dokumen Pendukung</label>
              <textarea name="bukti_pendukung" id="modal_bukti" class="form-control summernote-fed"></textarea>
            </div>
          </div>

          {{-- STEP 4: Faktor --}}
          <div class="fed-step d-none" data-step="4">
            <div class="mb-3">
              <label class="form-label fw-semibold">Faktor Penghambat/Pendukung</label>
              <textarea name="faktor_penghambat_pendukung" id="modal_faktor" class="form-control summernote-fed"></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light d-none" id="btnPrevStep">
          <i class="ph-arrow-left me-1"></i> Sebelumnya
        </button>
        <button type="button" class="btn btn-primary" id="btnNextStep">
          Berikutnya <i class="ph-arrow-right ms-1"></i>
        </button>
        <button type="submit" class="btn btn-primary d-none" id="btnSaveFed">
          <i class="ph-floppy-disk me-1"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>
{{-- =============== END MODALS =============== --}}

@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<style>
  .note-editor.note-frame { border: 1px solid #ddd; }
  .note-editing-area { min-height: 150px; }
  .modal-xl { max-width: 1140px; }
  #modalIsiFed .modal-body { max-height: calc(100vh - 200px); overflow-y: auto; }
  .table-responsive { overflow-x: visible !important; }
  .td-standar { white-space: normal; word-wrap: break-word; max-width: none; }
  .td-standar ol, .td-standar ul { margin-bottom: 0.5rem; padding-left: 1.5rem; }
  .td-standar ol[type="a"] { list-style-type: lower-alpha; }
  .td-standar p { margin-bottom: 0.5rem; }
  .td-standar p:last-child { margin-bottom: 0; }
  .badge { white-space: normal; word-break: break-word; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>

<script>
  // ================== SELECT2 USER AJAX (pakai style Limitless) ==================
  $(function () {
    $('.select-user-ajax').each(function () {
      const $el = $(this);
      const url = $el.data('url');
      if (!url) return;

      const $modalParent = $el.closest('.modal');
      const dropdownParent = $modalParent.length ? $modalParent : $(document.body);

      $el.select2({
        width: '100%',
        dropdownParent: dropdownParent,
        placeholder: $el.data('placeholder') || 'Cari user...',
        minimumInputLength: 1,
        ajax: {
          url: url,
          dataType: 'json',
          delay: 250,
          data: function (params) {
            return { q: params.term || '' };
          },
          processResults: function (data) {
            return {
              results: data.map(function (item) {
                return {
                  id: item.id,
                  text: item.name,
                  role: item.role_name
                };
              })
            };
          },
          cache: true
        },
        templateResult: function (data) {
          if (!data.id) return data.text;
          const $wrap = $('<div class="d-flex flex-column"></div>');
          $('<div class="fw-semibold"></div>').text(data.text).appendTo($wrap);
          if (data.role) {
            $('<div class="text-muted small"></div>').text(data.role).appendTo($wrap);
          }
          return $wrap;
        },
        templateSelection: function (data) {
          if (!data.id) {
            return $el.data('placeholder') || 'Pilih user';
          }
          return data.text + (data.role ? ' (' + data.role + ')' : '');
        }
      });
    });
  });

  // ====== CUSTOM BUTTON: Alpha ordered list for Summernote
  const AlphaListButton = function (context) {
    const ui = $.summernote.ui;
    const button = ui.button({
      contents: '<i class="note-icon-unorderedlist"></i> a.',
      tooltip: 'Insert alphabetic list (a., b., c.)',
      click: function () {
        const template = '<ol type="a"><li></li></ol><p></p>';
        context.invoke('editor.pasteHTML', template);
      }
    });
    return button.render();
  };

  let summernoteInitialized = false;
  function initSummernote() {
    if (summernoteInitialized) return;
    $('.summernote-fed').summernote({
      height: 180,
      toolbar: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['fontname', ['fontname']],
        ['fontsize', ['fontsize']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['height', ['height']],
        ['table', ['table']],
        ['insert', ['link', 'picture', 'video']],
        ['custom', ['alphaList']],
        ['view', ['fullscreen', 'codeview', 'help']]
      ],
      buttons: { alphaList: AlphaListButton },
      placeholder: 'Tuliskan di sini...',
      tabsize: 2,
      dialogsInBody: true
    });
    summernoteInitialized = true;
  }

  // helper: decode & buang tag HTML -> plain text
  function stripHtmlToPlainText(html) {
    const txt = document.createElement('textarea');
    txt.innerHTML = html || '';
    const decoded = txt.value;
    const div = document.createElement('div');
    div.innerHTML = decoded;
    return (div.textContent || div.innerText || '').trim();
  }

  // MODAL ISI/EDIT FED (WIZARD + TEMPLATE POS/NEG)
  (function() {
    const modalEl = document.getElementById('modalIsiFed');
    const formEl  = document.getElementById('formIsiFed');
    if (!modalEl || !formEl) return;

    let currentStep = 1;
    const totalSteps = 4;

    const btnPrev   = document.getElementById('btnPrevStep');
    const btnNext   = document.getElementById('btnNextStep');
    const btnSave   = document.getElementById('btnSaveFed');
    const lblStep   = document.getElementById('fedCurrentStep');
    const barStep   = document.getElementById('fedStepProgress');

    function showStep(step) {
      if (step < 1) step = 1;
      if (step > totalSteps) step = totalSteps;
      currentStep = step;

      const stepEls = modalEl.querySelectorAll('.fed-step');
      stepEls.forEach(function(el) {
        const s = parseInt(el.getAttribute('data-step'), 10);
        el.classList.toggle('d-none', s !== currentStep);
      });

      if (lblStep) lblStep.textContent = currentStep;
      if (barStep) {
        const percent = (currentStep / totalSteps) * 100;
        barStep.style.width = percent + '%';
      }

      if (btnPrev) btnPrev.classList.toggle('d-none', currentStep === 1);
      if (btnNext) btnNext.classList.toggle('d-none', currentStep === totalSteps);
      if (btnSave) btnSave.classList.toggle('d-none', currentStep !== totalSteps);
    }

    if (btnPrev) {
      btnPrev.addEventListener('click', function () {
        showStep(currentStep - 1);
      });
    }

    if (btnNext) {
      btnNext.addEventListener('click', function () {
        if ((currentStep === 1 || currentStep === 2) && !summernoteInitialized) {
          initSummernote();
        }
        showStep(currentStep + 1);
      });
    }

    modalEl.addEventListener('show.bs.modal', function(ev) {
      const btn = ev.relatedTarget; if (!btn) return;

      const updateUrl    = btn.getAttribute('data-update-url') || '';
      const ketercapaian = btn.getAttribute('data-ketercapaian') || '';
      const hasil        = btn.getAttribute('data-hasil') || '';
      const bukti        = btn.getAttribute('data-bukti') || '';
      const faktor       = btn.getAttribute('data-faktor') || '';
      const posTemplate  = btn.getAttribute('data-pos-template') || '';
      const negTemplate  = btn.getAttribute('data-neg-template') || '';

      formEl.action = updateUrl;

      modalEl.dataset.posTemplate = posTemplate;
      modalEl.dataset.negTemplate = negTemplate;

      const radios = modalEl.querySelectorAll('input[name="ketercapaian_standard_id"]');
      radios.forEach(r => r.checked = false);
      if (ketercapaian) {
        const targetRadio = modalEl.querySelector(`input[name="ketercapaian_standard_id"][value="${ketercapaian}"]`);
        if (targetRadio) targetRadio.checked = true;
      }

      if (!summernoteInitialized) initSummernote();

      setTimeout(function() {
        const hasilPlain  = stripHtmlToPlainText(hasil);
        const buktiPlain  = stripHtmlToPlainText(bukti);
        const faktorPlain = stripHtmlToPlainText(faktor);

        $('#modal_hasil').summernote('code', $('<p/>').text(hasilPlain).html());
        $('#modal_bukti').summernote('code', $('<p/>').text(buktiPlain).html());
        $('#modal_faktor').summernote('code', $('<p/>').text(faktorPlain).html());

        $('.modal-body').scrollTop(0);
      }, 100);

      showStep(1);
    });

    modalEl.addEventListener('hidden.bs.modal', function() {
      formEl.reset();
      if (summernoteInitialized) {
        $('#modal_hasil').summernote('code', '');
        $('#modal_bukti').summernote('code', '');
        $('#modal_faktor').summernote('code', '');
      }
      showStep(1);
    });

    // auto-isi hasil pelaksanaan ketika ketercapaian diubah
    modalEl.addEventListener('change', function (ev) {
      const target = ev.target;
      if (target.name === 'ketercapaian_standard_id') {
        const type = target.getAttribute('data-template-type');
        let tpl = '';
        if (type === 'pos') {
          tpl = modalEl.dataset.posTemplate || '';
        } else if (type === 'neg') {
          tpl = modalEl.dataset.negTemplate || '';
        }
        if (tpl) {
          if (!summernoteInitialized) initSummernote();
          const plain = stripHtmlToPlainText(tpl);
          $('#modal_hasil').summernote('code', $('<p/>').text(plain).html());
        }
      }
    });
  })();

  // Client-side search tabel
  (function(){
    const inputFilter = document.getElementById('searchFed');
    const table = document.getElementById('tableFed');
    function applyFilter() {
      if (!table) return;
      const q = (inputFilter.value || '').trim().toLowerCase();
      const rows = table.querySelectorAll('tbody tr');
      rows.forEach(tr => {
        const tdStandar = (tr.querySelector('.td-standar')?.textContent || '').toLowerCase();
        tr.style.display = tdStandar.includes(q) ? '' : 'none';
      });
    }
    inputFilter?.addEventListener('input', applyFilter);
  })();

  // Modal Desc
  (function() {
    var modal = document.getElementById('modalDesc');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (ev) {
      var btn = ev.relatedTarget;
      var title  = btn?.getAttribute('data-title') || 'Deskripsi Indikator';
      var b64    = btn?.getAttribute('data-desc-html') || '';
      var titleEl = document.getElementById('modalDesc_title');
      var bodyEl  = document.getElementById('modalDesc_body');
      if (titleEl) titleEl.textContent = title;
      try { bodyEl.innerHTML = b64 ? atob(b64) : ''; } catch (e) { bodyEl.textContent = ''; }
    });
  })();
</script>
@endpush
