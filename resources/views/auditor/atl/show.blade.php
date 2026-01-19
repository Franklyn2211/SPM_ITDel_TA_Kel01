@extends('auditor.layouts.app')

@section('title', 'Form Audit Tindak Lanjut')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h4 class="page-title mb-0">
        Audit Tindak Lanjut - <span class="fw-normal">{{ $unitName ?? 'Unit/Prodi' }}</span>
      </h4>

      <a href="#page_header"
         class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
         data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center gap-2">
        @php
          $isFinal = (($atl->status ?? '') === 'Final');
          $badge = $isFinal ? 'bg-success' : 'bg-secondary';
        @endphp

        <span class="badge {{ $badge }} rounded-pill">
          Status ATL: {{ $atl->status ?? 'Draft' }}
        </span>

        @if(!$isFinal)
          <button type="button" class="btn btn-outline-primary btn-sm rounded-pill"
                  data-bs-toggle="modal" data-bs-target="#modalHeader">
            <i class="ph-gear me-1"></i> Edit Header
          </button>

          <form method="POST" action="{{ route('auditor.atl.finalize', $atl->id) }}">
            @csrf
            <button type="submit" class="btn btn-success btn-sm rounded-pill"
                    onclick="return confirm('Finalkan ATL? Setelah Final, tidak bisa edit lagi.')">
              <i class="ph-lock-key me-1"></i> Finalkan
            </button>
          </form>
        @else
          <a class="btn btn-primary btn-sm rounded-pill"
             href="{{ route('auditor.atl.exportPdf', $atl->id) }}">
            <i class="ph-download-simple me-1"></i> Unduh PDF
          </a>
        @endif
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('auditor.dashboard') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <a href="{{ route('auditor.atl.index') }}" class="breadcrumb-item">Audit Tindak Lanjut</a>
        <span class="breadcrumb-item active">Detail</span>
      </div>

      <div class="ms-auto d-flex align-items-center text-muted gap-3">
        @if(!empty($academicText))
          <div><i class="ph-calendar me-1"></i> {{ $academicText }}</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">

  {{-- Flash --}}
  @foreach (['success','info','warning','error'] as $f)
    @if (session($f))
      @php
        $cls  = $f === 'success' ? 'success' : ($f === 'warning' ? 'warning' : ($f === 'error' ? 'danger' : 'info'));
        $icon = $f === 'success' ? 'check-circle' : ($f === 'warning' ? 'warning' : ($f === 'error' ? 'x-circle' : 'info'));
      @endphp
      <div class="alert alert-{{ $cls }} border-0 alert-dismissible fade show">
        <div class="d-flex align-items-center">
          <i class="ph-{{ $icon }} me-2"></i>
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
            @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
          </ul>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Ringkasan --}}
  <div class="card mb-3">
    <div class="card-body row g-3 align-items-center">
      <div class="col-md-4">
        <div class="text-muted fs-sm">Tahun Akademik</div>
        <div class="fw-semibold">{{ $academicText ?? '-' }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted fs-sm">Unit / Prodi</div>
        <div class="fw-semibold">{{ $unitName ?? '-' }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted fs-sm">Progress Kelengkapan ATL</div>
        <div class="d-flex flex-wrap gap-2 mt-1">
          <span class="badge bg-secondary rounded-pill">Total: {{ $progress['total'] ?? 0 }}</span>
          <span class="badge bg-success rounded-pill">Lengkap: {{ $progress['complete'] ?? 0 }}</span>
          <span class="badge bg-info rounded-pill">{{ $progress['percent'] ?? 0 }}%</span>
        </div>
        <div class="text-muted fs-sm mt-2">
          Auditee isi: <b>Realisasi</b>, <b>Efektivitas</b>. Auditor isi: <b>Status</b> + <b>Catatan Status</b>.
        </div>
      </div>
    </div>
  </div>

  {{-- Tabel --}}
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h5 class="mb-0">Daftar Tindak Lanjut (berdasarkan Temuan Negatif)</h5>
      @if($isFinal)
        <span class="badge bg-success rounded-pill"><i class="ph-lock me-1"></i> Form sudah Final (read-only)</span>
      @endif
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-top mb-0">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width:60px;">No</th>
            <th style="min-width:420px;">Standar & Butir Mutu</th>
            <th style="min-width:320px;">Deskripsi Kondisi (FED)</th>
            <th style="width:170px;" class="text-center">Kategori Temuan</th>
            <th style="min-width:260px;">Rencana Tindak Lanjut (Temuan)</th>
            <th style="min-width:180px;">PIC Indikator (Role)</th>
            <th style="width:140px;" class="text-center">Jadwal</th>

            <th style="min-width:260px;">Realisasi (Auditee)</th>
            <th style="min-width:160px;">Efektivitas (Auditee)</th>
            <th style="min-width:200px;">Status + Catatan (Auditor)</th>

            <th style="width:200px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        @forelse($details as $d)
          @php
            $finding = $d->finding;
            $detailFed = $finding?->selfEvaluationDetail;
            $indicator = $detailFed?->indicator;
            $standard = $indicator?->standard;

            $stdName = $standard?->name ?? 'Standar';
            $rawIndHtml = $indicator?->description ?? '';
            $plainInd = trim(preg_replace('/\s+/', ' ', strip_tags($rawIndHtml)));
            $shortInd = \Illuminate\Support\Str::limit($plainInd, 220);

            $fedResult = $detailFed?->result ?? '';
            $sev = $finding?->severity;
            $severityLabel = $sev ? ($severityOptions[$sev] ?? $sev) : '—';

            $planHtml = $finding?->corrective_action_plan ?? $finding?->follow_up_plan ?? '';
            $jadwal = $finding?->due_date ? \Carbon\Carbon::parse($finding->due_date)->format('d/m/Y') : '—';

            $picsRole = collect($indicator?->pics ?? [])
              ->map(fn($p) => $p->role?->name ?? null)->filter()->unique()->values()->implode(', ');
            $picsRole = $picsRole ?: '-';

            $completeAuditee = trim((string)$d->follow_up_realization) !== '' && trim((string)$d->effectiveness) !== '';
            $completeAuditor = trim((string)$d->status) !== '';
            $badgeRow = ($completeAuditee && $completeAuditor) ? 'bg-success' : 'bg-secondary';
            $statusText = ($completeAuditee && $completeAuditor) ? 'Lengkap' : 'Draft';
          @endphp

          <tr>
            <td class="text-center">{{ $loop->iteration }}</td>

            <td>
              <div class="fw-semibold">{{ $stdName }}</div>
              <div class="text-muted fs-sm mt-1">{{ $shortInd ?: '-' }}</div>

              <button type="button" class="btn btn-link p-0 fs-sm mt-1"
                      data-bs-toggle="modal" data-bs-target="#modalDesc"
                      data-title="{{ e($stdName) }}"
                      data-desc-html="{{ base64_encode($rawIndHtml) }}">
                Lihat indikator lengkap
              </button>
            </td>

            <td class="fs-sm">
              <div class="border rounded p-2" style="white-space: normal;">
                {!! $fedResult ?: '<span class="text-muted">-</span>' !!}
              </div>
            </td>

            <td class="text-center">
              <span class="badge {{ $sev ? 'bg-danger' : 'bg-secondary' }} rounded-pill">{{ $severityLabel }}</span>
            </td>

            <td class="fs-sm">
              <div class="border rounded p-2" style="white-space: normal;">
                {!! $planHtml ?: '<span class="text-muted">-</span>' !!}
              </div>
            </td>

            <td class="fs-sm"><div>{{ $picsRole }}</div></td>
            <td class="text-center">{{ $jadwal }}</td>

            <td class="fs-sm">
              <div class="border rounded p-2" style="white-space: normal;">
                {!! $d->follow_up_realization ?: '<span class="text-muted">-</span>' !!}
              </div>
            </td>

            <td class="fs-sm">{{ $d->effectiveness ?: '—' }}</td>

            <td class="fs-sm">
              <div class="mb-1">
                <span class="fw-semibold">{{ $d->status ?: '—' }}</span>
                <span class="badge {{ $badgeRow }} rounded-pill ms-1">{{ $statusText }}</span>
              </div>
              @if(!empty($d->status_description))
                <div class="text-muted" style="white-space: pre-line;">{{ $d->status_description }}</div>
              @endif
            </td>

            <td>
              @if($isFinal)
                <div class="text-muted fs-sm">Terkunci.</div>
              @else
                <button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="modal" data-bs-target="#modalEditRowAuditor"
                        data-update-url="{{ route('auditor.atl.row.update', [$atl->id, $d->id]) }}"
                        data-std-name="{{ e($stdName) }}"
                        data-indikator-html-b64="{{ base64_encode($rawIndHtml) }}"
                        data-fed-result-b64="{{ base64_encode($fedResult) }}"
                        data-plan-b64="{{ base64_encode($planHtml) }}"
                        data-pic-indikator="{{ e($picsRole) }}"
                        data-jadwal="{{ $finding?->due_date ? \Carbon\Carbon::parse($finding->due_date)->format('Y-m-d') : '' }}"
                        data-severity="{{ e($severityLabel) }}"
                        data-realisasi-b64="{{ base64_encode($d->follow_up_realization ?? '') }}"
                        data-efektivitas="{{ e($d->effectiveness ?? '') }}"
                        data-status="{{ e($d->status ?? '') }}"
                        data-status-desc-b64="{{ base64_encode($d->status_description ?? '') }}">
                  <i class="ph-pencil-simple me-1"></i> Isi Status
                </button>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="11" class="text-center text-muted py-4">Tidak ada detail ATL.</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

{{-- MODAL: DESKRIPSI INDIKATOR --}}
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

{{-- MODAL: EDIT HEADER --}}
@if(!$isFinal)
<div class="modal fade" id="modalHeader" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST" action="{{ route('auditor.atl.header.update', $atl->id) }}" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title">Edit Header ATL</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-warning py-2">Kalau sudah Final, terkunci.</div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Area</label>
          <input type="text" name="area" class="form-control"
                 value="{{ old('area', $atl->area) }}" placeholder="Area / Unit yang diaudit">
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Tanggal Audit</label>
          <input type="date" name="audit_date" class="form-control"
                 value="{{ old('audit_date', \Carbon\Carbon::parse($atl->audit_date)->format('Y-m-d')) }}">
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Ketua Auditor</label>
            <input type="text" class="form-control"
                   value="{{ optional(optional($atl->auditorUserRole)->user)->name ?? '—' }}"
                   readonly>
            <div class="text-muted fs-sm mt-1">Ditentukan oleh Admin (tidak bisa diubah).</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Anggota Auditor</label>
            <select name="member_auditor_user_role_id"
                    class="form-control form-control-select2 select-user-ajax"
                    data-placeholder="Cari auditor..."
                    data-url="{{ route('auditor.atl.searchAuditors') }}">
              @if(old('member_auditor_user_role_id', $atl->member_auditor_user_role_id))
                <option value="{{ old('member_auditor_user_role_id', $atl->member_auditor_user_role_id) }}" selected>
                  {{ optional(optional($atl->memberAuditorUserRole)->user)->name ?? 'Anggota terpilih' }}
                </option>
              @endif
            </select>
            <div class="text-muted fs-sm mt-1">Anggota bisa isi status baris, tapi tidak bisa Final.</div>
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
        <button type="submit" class="btn btn-primary">
          <i class="ph-floppy-disk me-1"></i> Simpan Header
        </button>
      </div>
    </form>
  </div>
</div>
@endif

{{-- MODAL: EDIT ROW (AUDITOR) --}}
@if(!$isFinal)
<div class="modal fade" id="modalEditRowAuditor" tabindex="-1" aria-hidden="true" data-bs-focus="false">
  <div class="modal-dialog modal-xl">
    <form method="POST" id="formEditRowAuditor" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title">Isi Status (Auditor)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info py-2">
          Auditee isi: <b>Realisasi</b>, <b>Efektivitas</b>. Auditor isi: <b>Status</b> + <b>Catatan Status</b>.
        </div>

        {{-- INFO --}}
        <div class="card border mb-3">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="text-muted fs-sm">PIC Indikator (Role)</div>
                <div class="fw-semibold" id="infoPicIndikator">-</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted fs-sm">Kategori Temuan</div>
                <div class="fw-semibold" id="infoSeverity">-</div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Standar & Butir Mutu</div>
                <div class="fw-semibold" id="infoStdName">-</div>
                <div class="text-muted fs-sm mt-1">Indikator</div>
                <div class="border rounded p-2" id="infoIndikatorHtml" style="white-space: normal;"></div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Deskripsi Kondisi (FED)</div>
                <div class="border rounded p-2" id="infoFedResult" style="white-space: normal;"></div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Rencana Tindak Lanjut (Temuan)</div>
                <div class="border rounded p-2" id="infoPlan" style="white-space: normal;"></div>
              </div>

              <div class="col-md-4">
                <div class="text-muted fs-sm">Jadwal (Temuan)</div>
                <div class="fw-semibold" id="infoJadwal">-</div>
              </div>

              <div class="col-md-4">
                <div class="text-muted fs-sm">Realisasi (Auditee)</div>
                <div class="border rounded p-2" id="infoRealisasi" style="white-space: normal;"></div>
              </div>

              <div class="col-md-4">
                <div class="text-muted fs-sm">Efektivitas (Auditee)</div>
                <div class="fw-semibold" id="infoEfektivitas">-</div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" id="edit_status" class="form-select">
              <option value="">— Pilih Status —</option>
              <option value="Open">Open</option>
              <option value="Toleran">Toleran</option>
              <option value="Closed">Closed</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Catatan Status (multi-line)</label>
            <textarea name="status_description" id="edit_status_description" class="form-control" rows="4"></textarea>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
        <button type="submit" class="btn btn-primary">
          <i class="ph-floppy-disk me-1"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection

@push('styles')
<style>
  .modal-xl { max-width: 1140px; }
  #modalEditRowAuditor .modal-body { max-height: calc(100vh - 200px); overflow-y: auto; }
  .table td { vertical-align: top; }
</style>
@endpush

@push('scripts')
<script>
  function safeHtml(html) {
    return html && String(html).trim() !== '' ? html : '<span class="text-muted">-</span>';
  }
  function b64decode(str) {
    if (!str) return '';
    try { return atob(str); } catch (e) { return ''; }
  }

  // Select2 AJAX (anggota auditor)
  $(function () {
    $('.select-user-ajax').each(function () {
      const $el = $(this);
      const url = $el.data('url');
      if (!url) return;

      const parent = $el.closest('.modal').length ? $el.closest('.modal') : $(document.body);

      $el.select2({
        width: '100%',
        dropdownParent: parent,
        placeholder: $el.data('placeholder') || 'Cari auditor...',
        minimumInputLength: 1,
        ajax: {
          url: url,
          dataType: 'json',
          delay: 250,
          data: params => ({ q: params.term || '' }),
          processResults: data => ({
            results: (data || []).map(item => ({
              id: item.id,
              text: item.name,
              role: item.role_name
            }))
          }),
          cache: true
        },
        templateResult: data => {
          if (!data.id) return data.text;
          return $(`
            <div class="d-flex flex-column">
              <div class="fw-semibold">${data.text}</div>
              <div class="text-muted small">${data.role || ''}</div>
            </div>
          `);
        },
        templateSelection: data => data.text || $el.data('placeholder') || 'Pilih auditor'
      });
    });
  });

  // Modal indikator HTML (full)
  (function () {
    const modal = document.getElementById('modalDesc');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      const title = btn?.getAttribute('data-title') || 'Deskripsi Indikator';
      const b64 = btn?.getAttribute('data-desc-html') || '';
      document.getElementById('modalDesc_title').textContent = title;
      document.getElementById('modalDesc_body').innerHTML = safeHtml(b64decode(b64));
    });
  })();

  // Modal edit auditor
  @if(!$isFinal)
  (function () {
    const modal = document.getElementById('modalEditRowAuditor');
    const form  = document.getElementById('formEditRowAuditor');
    if (!modal || !form) return;

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      if (!btn) return;

      form.action = btn.getAttribute('data-update-url') || '';

      document.getElementById('infoStdName').textContent = btn.getAttribute('data-std-name') || '-';
      document.getElementById('infoIndikatorHtml').innerHTML = safeHtml(b64decode(btn.getAttribute('data-indikator-html-b64')));
      document.getElementById('infoFedResult').innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-result-b64')));
      document.getElementById('infoPlan').innerHTML = safeHtml(b64decode(btn.getAttribute('data-plan-b64')));
      document.getElementById('infoPicIndikator').textContent = btn.getAttribute('data-pic-indikator') || '-';
      document.getElementById('infoSeverity').textContent = btn.getAttribute('data-severity') || '-';
      document.getElementById('infoJadwal').textContent = btn.getAttribute('data-jadwal') || '-';

      document.getElementById('infoRealisasi').innerHTML = safeHtml(b64decode(btn.getAttribute('data-realisasi-b64')));
      document.getElementById('infoEfektivitas').textContent = btn.getAttribute('data-efektivitas') || '—';

      document.getElementById('edit_status').value = btn.getAttribute('data-status') || '';
      document.getElementById('edit_status_description').value = b64decode(btn.getAttribute('data-status-desc-b64')) || '';

      $(modal).find('.modal-body').scrollTop(0);
    });

    modal.addEventListener('hidden.bs.modal', function () {
      form.action = '';
      document.getElementById('edit_status').value = '';
      document.getElementById('edit_status_description').value = '';
    });
  })();
  @endif
</script>
@endpush
