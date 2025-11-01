{{-- resources/views/ketuaprodi/fed/index.blade.php --}}
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

        {{-- Jika belum ada form: tombol buat --}}
        @if(!$form)
          <button type="button" class="btn btn-primary btn-sm rounded-pill"
                  data-bs-toggle="modal" data-bs-target="#modalCreateFed">
            <i class="ph-plus me-2"></i> Buat Form FED
          </button>
        @else
          {{-- Jika sudah ada form: edit header & submit --}}
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
                    default     => 'bg-secondary', // Draft
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
          @if($form && $form->tanggal_submit)
            <span class="ms-2 badge bg-success">Dikirim {{ \Illuminate\Support\Carbon::parse($form->tanggal_submit)->translatedFormat('d M Y') }}</span>
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
      <div class="ms-auto" style="max-width: 420px;">
        <div class="input-group">
          <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
          <input type="text" id="searchFed" class="form-control" placeholder="Cari butir/indikator/keterangan...">
          <button class="btn btn-outline-secondary" type="button" id="btnResetFed">Reset</button>
        </div>
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
        <table class="table text-nowrap table-hover align-middle" id="tableFed">
          <thead class="table-light">
            <tr>
              <th class="text-center" style="width: 60px;">No</th>
              <th>Standar & Butir</th>
              <th class="text-center" style="width: 260px;">Ketercapaian</th>
              <th style="width: 300px;">Hasil Pelaksanaan</th>
              <th style="width: 260px;">Bukti/Dokumen</th>
              <th style="width: 260px;">Faktor Penghambat/Pendukung</th>
              <th class="text-center" style="width: 120px;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse($details as $i => $d)
              @php $readOnly = ($form->status->name ?? '') === 'Dikirim'; @endphp
              <tr id="detail-{{ $d->id }}">
                <td class="text-center">{{ $i + 1 }}</td>

                {{-- ================== KOLOM STANDAR & DESKRIPSI (UBAH DI SINI SAJA) ================== --}}
                <td class="td-standar">
                  {{-- Nama standar tetap, tidak diubah --}}
                  <div class="fw-semibold text-primary">{{ optional($d->AmiStandardIndicator->standard)->name ?? 'Standar' }}</div>

                  {{-- Deskripsi: ringkas + tombol "Lihat selengkapnya" (modal) --}}
                  @php
                    $descHtml  = $d->AmiStandardIndicator->description ?? '';
                    $descPlain = trim(strip_tags($descHtml));
                    $descB64   = base64_encode($descHtml);
                    $hasLong   = mb_strlen($descPlain) > 160;
                  @endphp
                  <div class="text-muted small">
                    {{ \Illuminate\Support\Str::limit($descPlain, 160) }}
                    @if($hasLong)
                      <button
                        type="button"
                        class="btn btn-link p-0 small align-baseline"
                        data-bs-toggle="modal"
                        data-bs-target="#modalDesc"
                        data-title="Deskripsi Indikator"
                        data-desc-html="{{ $descB64 }}">
                        Lihat selengkapnya
                      </button>
                    @endif
                  </div>
                </td>
                {{-- ================== /KOLOM STANDAR & DESKRIPSI ================== --}}

                <form method="POST" action="{{ route('auditee.fed.updateDetail', [$form, $d]) }}">
                  @csrf @method('PUT')

                  <td class="text-center td-cap">
                    <div class="d-flex flex-column align-items-start gap-1">
                      @foreach($opsiKetercapaian as $op)
                        <label class="d-flex align-items-center gap-2">
                          <input type="radio" name="ketercapaian_standard_id" value="{{ $op->id }}"
                            @checked($d->ketercapaian_standard_id === $op->id) @disabled($readOnly)>
                          <span>{{ $op->name }}</span>
                        </label>
                      @endforeach
                    </div>
                  </td>

                  <td class="td-hasil">
                    <textarea name="hasil" class="form-control" rows="3" placeholder="Tuliskan hasil pelaksanaan..." @disabled($readOnly)>{{ old('hasil', $d->hasil) }}</textarea>
                  </td>

                  <td class="td-bukti">
                    <textarea name="bukti_pendukung" class="form-control" rows="3" placeholder="Sebutkan bukti/dokumen pendukung..." @disabled($readOnly)>{{ old('bukti_pendukung', $d->bukti_pendukung) }}</textarea>
                  </td>

                  <td class="td-faktor">
                    <textarea name="faktor_penghambat_pendukung" class="form-control" rows="3" placeholder="Sebutkan faktor penghambat/pendukung..." @disabled($readOnly)>{{ old('faktor_penghambat_pendukung', $d->faktor_penghambat_pendukung) }}</textarea>
                  </td>

                  <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary" @if($readOnly) disabled @endif>
                      <i class="ph-floppy-disk me-1"></i> Simpan
                    </button>
                  </td>
                </form>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted">Belum ada indikator untuk tahun/prodi ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>

{{-- =============== MODALS =============== --}}

{{-- Create FED: tidak kirim academic/prodi (server yang tentukan) --}}
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
          <div class="col-md-6">
            <label class="form-label">Ketua Auditee</label>
            <input type="text" name="ketua_auditee_nama" class="form-control" placeholder="Nama Ketua">
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Ketua</label>
            <input type="text" name="ketua_auditee_jabatan" class="form-control" placeholder="Jabatan">
          </div>
          <div class="col-md-6">
            <label class="form-label">Anggota 1</label>
            <input type="text" name="anggota_auditee_satu" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 1</label>
            <input type="text" name="anggota_auditee_jabatan_satu" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Anggota 2</label>
            <input type="text" name="anggota_auditee_dua" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 2</label>
            <input type="text" name="anggota_auditee_jabatan_dua" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Anggota 3</label>
            <input type="text" name="anggota_auditee_tiga" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 3</label>
            <input type="text" name="anggota_auditee_jabatan_tiga" class="form-control">
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
          <div class="col-md-6">
            <label class="form-label">Ketua Auditee</label>
            <input type="text" name="ketua_auditee_nama" class="form-control" value="{{ old('ketua_auditee_nama',$form->ketua_auditee_nama) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Ketua</label>
            <input type="text" name="ketua_auditee_jabatan" class="form-control" value="{{ old('ketua_auditee_jabatan',$form->ketua_auditee_jabatan) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Anggota 1</label>
            <input type="text" name="anggota_auditee_satu" class="form-control" value="{{ old('anggota_auditee_satu',$form->anggota_auditee_satu) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 1</label>
            <input type="text" name="anggota_auditee_jabatan_satu" class="form-control" value="{{ old('anggota_auditee_jabatan_satu',$form->anggota_auditee_jabatan_satu) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Anggota 2</label>
            <input type="text" name="anggota_auditee_dua" class="form-control" value="{{ old('anggota_auditee_dua',$form->anggota_auditee_dua) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 2</label>
            <input type="text" name="anggota_auditee_jabatan_dua" class="form-control" value="{{ old('anggota_auditee_jabatan_dua',$form->anggota_auditee_jabatan_dua) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Anggota 3</label>
            <input type="text" name="anggota_auditee_tiga" class="form-control" value="{{ old('anggota_auditee_tiga',$form->anggota_auditee_tiga) }}">
          </div>
          <div class="col-md-6">
            <label class="form-label">Jabatan Anggota 3</label>
            <input type="text" name="anggota_auditee_jabatan_tiga" class="form-control" value="{{ old('anggota_auditee_jabatan_tiga',$form->anggota_auditee_jabatan_tiga) }}">
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

{{-- MODAL: FULL DESKRIPSI INDIKATOR (BARU) --}}
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
{{-- =============== END MODALS =============== --}}

@endsection

@push('scripts')
<script>
  // Client-side search sederhana
  const inputFilter = document.getElementById('searchFed');
  const btnReset    = document.getElementById('btnResetFed');
  const table       = document.getElementById('tableFed');

  function applyFilter() {
    if (!table) return;
    const q = (inputFilter.value || '').trim().toLowerCase();
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(tr => {
      const tdStandar = (tr.querySelector('.td-standar')?.textContent || '').toLowerCase();
      const tdHasil   = (tr.querySelector('.td-hasil')?.textContent || '').toLowerCase();
      const tdBukti   = (tr.querySelector('.td-bukti')?.textContent || '').toLowerCase();
      const tdFaktor  = (tr.querySelector('.td-faktor')?.textContent || '').toLowerCase();
      tr.style.display = (tdStandar.includes(q) || tdHasil.includes(q) || tdBukti.includes(q) || tdFaktor.includes(q)) ? '' : 'none';
    });
  }

  inputFilter?.addEventListener('input', applyFilter);
  btnReset?.addEventListener('click', () => { inputFilter.value = ''; applyFilter(); });

  // Modal "Lihat selengkapnya" – decode base64 lalu inject HTML deskripsi
  (function() {
    var modal = document.getElementById('modalDesc');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (ev) {
      var btn    = ev.relatedTarget;
      var title  = btn?.getAttribute('data-title') || 'Deskripsi Indikator';
      var b64    = btn?.getAttribute('data-desc-html') || '';

      var titleEl = document.getElementById('modalDesc_title');
      var bodyEl  = document.getElementById('modalDesc_body');

      if (titleEl) titleEl.textContent = title;

      try {
        bodyEl.innerHTML = b64 ? atob(b64) : '';
      } catch (e) {
        bodyEl.textContent = '';
      }
    });
  })();
</script>
@endpush
