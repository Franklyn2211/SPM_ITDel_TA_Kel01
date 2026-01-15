{{-- resources/views/auditor/temuan/show.blade.php --}}
@extends('auditor.layouts.app')

@section('title', 'Form Temuan Audit')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h4 class="page-title mb-0">
        Temuan Audit - <span class="fw-normal">{{ $fed->categoryDetail->name ?? 'Unit/Prodi' }}</span>
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
          $isFinal = (($form->status ?? '') === 'Final');
          $badge = $isFinal ? 'bg-success' : 'bg-secondary';
        @endphp

        <span class="badge {{ $badge }} rounded-pill">
          Status Temuan: {{ $form->status ?? 'Draft' }}
        </span>

        {{-- Tombol aksi header --}}
        @if(!$isFinal)
          <button type="button" class="btn btn-outline-primary btn-sm rounded-pill"
                  data-bs-toggle="modal" data-bs-target="#modalHeader">
            <i class="ph-gear me-1"></i> Edit Header / Assign Auditor
          </button>

          <form method="POST" action="{{ route('auditor.temuan.finalize', $form->id) }}">
            @csrf
            <button type="submit" class="btn btn-success btn-sm rounded-pill"
                    onclick="return confirm('Finalkan form temuan? Setelah Final, tidak bisa edit lagi.')">
              <i class="ph-lock-key me-1"></i> Finalkan
            </button>
          </form>
        @else
          {{-- Setelah final: munculkan tombol unduh --}}
          <a class="btn btn-primary btn-sm rounded-pill"
             href="{{ route('auditor.temuan.exportPdf', $form->id) }}">
            <i class="ph-download-simple me-1"></i> Unduh PDF
          </a>
        @endif
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('auditor.dashboard') }}" class="breadcrumb-item">
          <i class="ph-house"></i>
        </a>
        <a href="{{ route('auditor.temuan.index') }}" class="breadcrumb-item">Temuan Audit</a>
        <span class="breadcrumb-item active">Detail</span>
      </div>

      <div class="ms-auto d-flex align-items-center text-muted gap-3">
        @if($fed->academicConfig)
          <div><i class="ph-calendar me-1"></i> {{ $fed->academicConfig->name ?? $fed->academicConfig->tahun }}</div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">

  {{-- Flash messages --}}
  @foreach (['success','info','warning','error'] as $f)
    @if (session($f))
      @php
        $cls = $f === 'success' ? 'success' : ($f === 'warning' ? 'warning' : ($f === 'error' ? 'danger' : 'info'));
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
            @foreach ($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Ringkas --}}
  <div class="card mb-3">
    <div class="card-body row g-3 align-items-center">
      <div class="col-md-4">
        <div class="text-muted fs-sm">Tahun Akademik</div>
        <div class="fw-semibold">{{ $fed->academicConfig->name ?? $fed->academicConfig->tahun ?? '-' }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted fs-sm">Unit / Prodi</div>
        <div class="fw-semibold">{{ $fed->categoryDetail->name ?? '-' }}</div>
      </div>
      <div class="col-md-4">
        <div class="text-muted fs-sm">Progress Kelengkapan Temuan</div>
        <div class="d-flex flex-wrap gap-2 mt-1">
          <span class="badge bg-secondary rounded-pill">Total: {{ $progress['total'] ?? 0 }}</span>
          <span class="badge bg-success rounded-pill">Lengkap: {{ $progress['complete'] ?? 0 }}</span>
          <span class="badge bg-info rounded-pill">{{ $progress['percent'] ?? 0 }}%</span>
        </div>
        <div class="text-muted fs-sm mt-2">
          Catatan: baris boleh disimpan satu-satu. Final hanya bisa kalau semua lengkap.
          (NEGATIF wajib isi kategori temuan)
        </div>
      </div>
    </div>
  </div>

  @php
    $pjFed = collect([
      $fed->head_auditee_name ?? null,
      $fed->member_auditee_1_name ?? null,
      $fed->member_auditee_2_name ?? null,
      $fed->member_auditee_3_name ?? null,
    ])->filter()->values()->implode(', ');
    $pjFed = $pjFed ?: '-';
  @endphp

  {{-- =======================
       1) TEMUAN POSITIF
       ======================= --}}
  <div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h5 class="mb-0">1) Temuan Positif / Praktik Baik</h5>
      @if($isFinal)
        <span class="badge bg-success rounded-pill">
          <i class="ph-lock me-1"></i> Form sudah Final (read-only)
        </span>
      @endif
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-top mb-0">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width:60px;">No</th>
            <th style="min-width:420px;">Standar & Butir Mutu</th>
            <th style="min-width:320px;">Hasil Pelaksanaan (FED)</th>
            <th style="min-width:220px;">Faktor Pendukung (FED)</th>
            <th style="min-width:180px;">PIC Indikator (Role)</th>
            <th style="width:140px;" class="text-center">Jadwal</th>
            <th style="width:140px;" class="text-center">Status Baris</th>
            <th style="width:200px;">Aksi</th>
          </tr>
        </thead>

        <tbody>
          @forelse($rowsPositive as $r)
            @php
              $stdName = $r->selfEvaluationDetail?->indicator?->standard?->name ?? 'Standar';
              $rawIndHtml = $r->selfEvaluationDetail?->indicator?->description ?? '';

              $plainInd = trim(preg_replace('/\s+/', ' ', strip_tags($rawIndHtml)));
              $shortInd = \Illuminate\Support\Str::limit($plainInd, 220);

              $fedResult = $r->selfEvaluationDetail?->result ?? '';
              $fedFactors = $r->selfEvaluationDetail?->contributing_factors ?? '';

              $picsRole = collect($r->selfEvaluationDetail?->indicator?->pics ?? [])
                ->map(fn($p) => $p->role?->name ?? null)
                ->filter()
                ->unique()
                ->values()
                ->implode(', ');
              $picsRole = $picsRole ?: '-';

              $need = [
                $r->control,
                $r->improvement,
                $r->follow_up_plan,
                $r->auditor_recommendation,
                $r->corrective_action_plan,
                $r->due_date,
              ];
              $complete = true;
              foreach ($need as $v) { if (is_null($v) || trim((string)$v)==='') { $complete=false; break; } }

              $badgeRow = $complete ? 'bg-success' : 'bg-secondary';
              $statusText = $complete ? 'Lengkap' : 'Draft';
              $rowNo = $r->finding_no ?? $loop->iteration;
            @endphp

            <tr>
              <td class="text-center">{{ $rowNo }}</td>

              <td>
                <div class="fw-semibold">{{ $stdName }}</div>
                <div class="text-muted fs-sm mt-1">{{ $shortInd ?: '-' }}</div>

                <button type="button"
                        class="btn btn-link p-0 fs-sm mt-1"
                        data-bs-toggle="modal"
                        data-bs-target="#modalDesc"
                        data-title="{{ e($stdName) }}"
                        data-desc-html="{{ base64_encode($rawIndHtml) }}">
                  Lihat indikator lengkap
                </button>
              </td>

              <td class="fs-sm">
                <div class="text-muted fs-sm mb-1">Hasil Pelaksanaan</div>
                <div class="border rounded p-2" style="white-space: normal;">
                  {!! $fedResult ?: '<span class="text-muted">-</span>' !!}
                </div>
              </td>

              <td class="fs-sm">
                <div class="text-muted fs-sm mb-1">Faktor Pendukung</div>
                <div class="border rounded p-2" style="white-space: normal;">
                  {!! $fedFactors ?: '<span class="text-muted">-</span>' !!}
                </div>
              </td>

              <td class="fs-sm">
                <div class="text-muted fs-sm mb-1">PIC Indikator</div>
                <div>{{ $picsRole }}</div>
              </td>

              <td class="text-center">
                {{ $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('d/m/Y') : '—' }}
              </td>

              <td class="text-center">
                <span class="badge {{ $badgeRow }} rounded-pill">{{ $statusText }}</span>
              </td>

              <td>
                @if($isFinal)
                  <div class="text-muted fs-sm">Terkunci.</div>
                @else
                  <button type="button"
                          class="btn btn-sm btn-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditRow"

                          data-update-url="{{ route('auditor.temuan.row.update', [$form->id, $r->id]) }}"
                          data-is-negative="0"
                          data-row-no="{{ $rowNo }}"

                          data-std-name="{{ e($stdName) }}"
                          data-indikator-html-b64="{{ base64_encode($rawIndHtml) }}"

                          data-pj-fed="{{ e($pjFed) }}"
                          data-pic-indikator="{{ e($picsRole) }}"

                          data-fed-result-b64="{{ base64_encode($fedResult) }}"
                          data-fed-factors-b64="{{ base64_encode($fedFactors) }}"

                          data-due="{{ $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('Y-m-d') : '' }}"
                          data-severity=""

                          data-control-b64="{{ base64_encode($r->control ?? '') }}"
                          data-improvement-b64="{{ base64_encode($r->improvement ?? '') }}"
                          data-follow-b64="{{ base64_encode($r->follow_up_plan ?? '') }}"
                          data-recommend-b64="{{ base64_encode($r->auditor_recommendation ?? '') }}"
                          data-cap-b64="{{ base64_encode($r->corrective_action_plan ?? '') }}"
                  >
                    <i class="ph-pencil-simple me-1"></i> Isi/Edit
                  </button>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-4">Tidak ada temuan positif.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- =======================
       2) TEMUAN NEGATIF
       ======================= --}}
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h5 class="mb-0">2) Temuan Negatif / Praktik Buruk</h5>
      @if($isFinal)
        <span class="badge bg-success rounded-pill">
          <i class="ph-lock me-1"></i> Form sudah Final (read-only)
        </span>
      @endif
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-top mb-0">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width:60px;">No</th>
            <th style="min-width:420px;">Standar & Butir Mutu</th>
            <th style="min-width:320px;">Hasil Pelaksanaan (FED)</th>
            <th style="min-width:220px;">Faktor Penghambat (FED)</th>
            <th style="min-width:180px;">PIC Indikator (Role)</th>
            <th style="width:170px;" class="text-center">Kategori Temuan</th>
            <th style="width:140px;" class="text-center">Jadwal</th>
            <th style="width:140px;" class="text-center">Status Baris</th>
            <th style="width:200px;">Aksi</th>
          </tr>
        </thead>

        <tbody>
          @forelse($rowsNegative as $r)
            @php
              $stdName = $r->selfEvaluationDetail?->indicator?->standard?->name ?? 'Standar';
              $rawIndHtml = $r->selfEvaluationDetail?->indicator?->description ?? '';

              $plainInd = trim(preg_replace('/\s+/', ' ', strip_tags($rawIndHtml)));
              $shortInd = \Illuminate\Support\Str::limit($plainInd, 220);

              $fedResult = $r->selfEvaluationDetail?->result ?? '';
              $fedFactors = $r->selfEvaluationDetail?->contributing_factors ?? '';

              $picsRole = collect($r->selfEvaluationDetail?->indicator?->pics ?? [])
                ->map(fn($p) => $p->role?->name ?? null)
                ->filter()
                ->unique()
                ->values()
                ->implode(', ');
              $picsRole = $picsRole ?: '-';

              $severityLabel = $r->severity ? ($severityOptions[$r->severity] ?? $r->severity) : null;

              $need = [
                $r->control,
                $r->improvement,
                $r->follow_up_plan,
                $r->auditor_recommendation,
                $r->corrective_action_plan,
                $r->severity,
                $r->due_date,
              ];
              $complete = true;
              foreach ($need as $v) { if (is_null($v) || trim((string)$v)==='') { $complete=false; break; } }

              $badgeRow = $complete ? 'bg-success' : 'bg-secondary';
              $statusText = $complete ? 'Lengkap' : 'Draft';
              $rowNo = $r->finding_no ?? $loop->iteration;
            @endphp

            <tr>
              <td class="text-center">{{ $rowNo }}</td>

              <td>
                <div class="fw-semibold">{{ $stdName }}</div>
                <div class="text-muted fs-sm mt-1">{{ $shortInd ?: '-' }}</div>

                <button type="button"
                        class="btn btn-link p-0 fs-sm mt-1"
                        data-bs-toggle="modal"
                        data-bs-target="#modalDesc"
                        data-title="{{ e($stdName) }}"
                        data-desc-html="{{ base64_encode($rawIndHtml) }}">
                  Lihat indikator lengkap
                </button>
              </td>

              <td class="fs-sm">
                <div class="text-muted fs-sm mb-1">Hasil Pelaksanaan</div>
                <div class="border rounded p-2" style="white-space: normal;">
                  {!! $fedResult ?: '<span class="text-muted">-</span>' !!}
                </div>
              </td>

              <td class="fs-sm">
                <div class="text-muted fs-sm mb-1">Faktor Penghambat</div>
                <div class="border rounded p-2" style="white-space: normal;">
                  {!! $fedFactors ?: '<span class="text-muted">-</span>' !!}
                </div>
              </td>

              <td class="fs-sm">
                <div class="text-muted fs-sm mb-1">PIC Indikator</div>
                <div>{{ $picsRole }}</div>
              </td>

              <td class="text-center">
                <span class="badge {{ $r->severity ? 'bg-danger' : 'bg-secondary' }} rounded-pill">
                  {{ $severityLabel ?? '—' }}
                </span>
              </td>

              <td class="text-center">
                {{ $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('d/m/Y') : '—' }}
              </td>

              <td class="text-center">
                <span class="badge {{ $badgeRow }} rounded-pill">{{ $statusText }}</span>
              </td>

              <td>
                @if($isFinal)
                  <div class="text-muted fs-sm">Terkunci.</div>
                @else
                  <button type="button"
                          class="btn btn-sm btn-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalEditRow"

                          data-update-url="{{ route('auditor.temuan.row.update', [$form->id, $r->id]) }}"
                          data-is-negative="1"
                          data-row-no="{{ $rowNo }}"

                          data-std-name="{{ e($stdName) }}"
                          data-indikator-html-b64="{{ base64_encode($rawIndHtml) }}"

                          data-pj-fed="{{ e($pjFed) }}"
                          data-pic-indikator="{{ e($picsRole) }}"

                          data-fed-result-b64="{{ base64_encode($fedResult) }}"
                          data-fed-factors-b64="{{ base64_encode($fedFactors) }}"

                          data-due="{{ $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('Y-m-d') : '' }}"
                          data-severity="{{ e($r->severity ?? '') }}"

                          data-control-b64="{{ base64_encode($r->control ?? '') }}"
                          data-improvement-b64="{{ base64_encode($r->improvement ?? '') }}"
                          data-follow-b64="{{ base64_encode($r->follow_up_plan ?? '') }}"
                          data-recommend-b64="{{ base64_encode($r->auditor_recommendation ?? '') }}"
                          data-cap-b64="{{ base64_encode($r->corrective_action_plan ?? '') }}"
                  >
                    <i class="ph-pencil-simple me-1"></i> Isi/Edit
                  </button>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center text-muted py-4">Tidak ada temuan negatif.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

{{-- =======================
     MODAL: DESKRIPSI INDIKATOR
     ======================= --}}
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

{{-- =======================
     MODAL: EDIT HEADER / ASSIGN AUDITOR
     ======================= --}}
@if(!$isFinal)
<div class="modal fade" id="modalHeader" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="POST"
          action="{{ route('auditor.temuan.header.update', $form->id) }}"
          class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title">Edit Header & Assign Auditor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-warning py-2">
          Aksi ini hanya untuk Ketua Auditor / Admin. Kalau sudah Final, terkunci.
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Area</label>
          <input type="text" name="area" class="form-control"
                 value="{{ old('area', $form->area) }}"
                 placeholder="Area / Unit yang diaudit">
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Tanggal Audit</label>
          <input type="date" name="audit_date" class="form-control"
                 value="{{ old('audit_date', optional($form->audit_date)->format('Y-m-d')) }}">
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Ketua Auditor (User Role)</label>
            <select name="auditor_user_role_id" class="form-select">
              @foreach($auditorUserRoles as $ur)
                @php
                  $label = (optional($ur->user)->name ?: ('User#'.$ur->id))
                          .' - '.(optional($ur->role)->name ?: 'Role');
                @endphp
                <option value="{{ $ur->id }}"
                  @if(old('auditor_user_role_id', $form->auditor_user_role_id) == $ur->id) selected @endif>
                  {{ $label }}
                </option>
              @endforeach
            </select>
            <div class="text-muted fs-sm mt-1">Ini yang berhak Final-kan form.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Anggota Auditor (User Role)</label>
            <select name="member_auditor_user_role_id" class="form-select">
              <option value="">— Tidak ada —</option>
              @foreach($auditorUserRoles as $ur)
                @php
                  $label = (optional($ur->user)->name ?: ('User#'.$ur->id))
                          .' - '.(optional($ur->role)->name ?: 'Role');
                @endphp
                <option value="{{ $ur->id }}"
                  @if(old('member_auditor_user_role_id', $form->member_auditor_user_role_id) == $ur->id) selected @endif>
                  {{ $label }}
                </option>
              @endforeach
            </select>
            <div class="text-muted fs-sm mt-1">Anggota bisa edit baris, tapi tidak bisa Final-kan.</div>
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

{{-- =======================
     MODAL: EDIT BARIS TEMUAN
     ======================= --}}
@if(!$isFinal)
<div class="modal fade" id="modalEditRow" tabindex="-1" aria-hidden="true" data-bs-focus="false">
  <div class="modal-dialog modal-xl">
    <form method="POST" id="formEditRow" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title" id="editRowTitle">Isi Temuan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info py-2">
          Baris boleh disimpan satu per satu. Final hanya bisa jika semua baris lengkap.
          <span class="ms-2">Jenis Temuan: <strong id="editRowTypeLabel">-</strong></span>
        </div>

        {{-- INFO DARI FED --}}
        <div class="card border mb-3">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="text-muted fs-sm">Penanggung Jawab (FED)</div>
                <div class="fw-semibold" id="infoPjFed">-</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted fs-sm">PIC Indikator (Master - Role)</div>
                <div class="fw-semibold" id="infoPicIndikator">-</div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Standar & Butir Mutu</div>
                <div class="fw-semibold" id="infoStdName">-</div>
                <div class="text-muted fs-sm mt-1">Indikator</div>
                <div class="border rounded p-2" id="infoIndikatorHtml" style="white-space: normal;"></div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Deskripsi Kondisi (Hasil Pelaksanaan Standar - FED)</div>
                <div class="border rounded p-2" id="infoFedResult" style="white-space: normal;"></div>
              </div>
              <div class="col-12">
                <div class="text-muted fs-sm" id="labelFedFactors">Faktor (FED)</div>
                <div class="border rounded p-2" id="infoFedFactors" style="white-space: normal;"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Jadwal Penyelesaian</label>
          <input type="date" name="due_date" id="edit_due" class="form-control">
        </div>

        {{-- Severity hanya NEGATIF --}}
        <div class="mb-3" id="wrapSeverity" style="display:none;">
          <label class="form-label fw-semibold">Kategori Temuan (Negatif)</label>
          <select name="severity" id="edit_severity" class="form-select">
            <option value="">Pilih kategori…</option>
            @foreach(($severityOptions ?? []) as $val => $label)
              <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
          </select>
          <div class="text-muted fs-sm mt-1">
            Sesuai template F-220: Observasi / KTS Minor / KTS Mayor.
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Pengendalian</label>
          <textarea name="control" id="edit_control" class="form-control summernote-temuan"></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Peningkatan</label>
          <textarea name="improvement" id="edit_improvement" class="form-control summernote-temuan"></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Rencana Tindak Lanjut</label>
          <textarea name="follow_up_plan" id="edit_follow" class="form-control summernote-temuan"></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Rekomendasi Auditor</label>
          <textarea name="auditor_recommendation" id="edit_recommend" class="form-control summernote-temuan"></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Rencana Tindakan Koreksi</label>
          <textarea name="corrective_action_plan" id="edit_cap" class="form-control summernote-temuan"></textarea>
        </div>

        <div class="text-muted fs-sm mt-3">
          <b>NEGATIF</b>: kategori temuan wajib saat Final. <b>POSITIF</b>: kategori tidak ada.
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<style>
  .note-editor.note-frame { border: 1px solid #ddd; }
  .note-editing-area { min-height: 150px; }
  .modal-xl { max-width: 1140px; }
  #modalEditRow .modal-body { max-height: calc(100vh - 200px); overflow-y: auto; }
  .table td { vertical-align: top; }

  /* Summernote dialog di atas Bootstrap modal */
  .note-modal { z-index: 1065 !important; }
  .note-popover { z-index: 1065 !important; }
  .note-toolbar { z-index: 1065; }
  .note-modal-backdrop { z-index: 1064 !important; }
  .note-modal, .note-modal * { pointer-events: auto; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>

<script>
  function safeHtml(html) {
    return html && String(html).trim() !== '' ? html : '<span class="text-muted">-</span>';
  }

  function b64decode(str) {
    if (!str) return '';
    try { return atob(str); } catch (e) { return ''; }
  }

  // Modal indikator HTML (full)
  (function () {
    const modal = document.getElementById('modalDesc');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      const title = btn?.getAttribute('data-title') || 'Deskripsi Indikator';
      const b64   = btn?.getAttribute('data-desc-html') || '';
      const titleEl = document.getElementById('modalDesc_title');
      const bodyEl  = document.getElementById('modalDesc_body');

      if (titleEl) titleEl.textContent = title;
      if (!bodyEl) return;

      bodyEl.innerHTML = safeHtml(b64decode(b64));
    });
  })();

  // Kalau form final: jangan inisialisasi summernote sama sekali (ngapain, wong read-only).
  @if(!$isFinal)
  // Tombol custom: list a, b, c
  const AlphaListButton = function (context) {
    const ui = $.summernote.ui;
    const button = ui.button({
      contents: '<i class="note-icon-unorderedlist"></i> a.',
      tooltip: 'Daftar alfabet (a, b, c)',
      click: function () {
        context.invoke('editor.pasteHTML', '<ol type="a"><li></li></ol><p></p>');
      }
    });
    return button.render();
  };

  let summernoteTemuanInit = false;
  function initSummernoteTemuan() {
    if (summernoteTemuanInit) return;

    $('.summernote-temuan').summernote({
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

    summernoteTemuanInit = true;
  }

  // Modal Edit Row (ambil dari data-* seperti FED)
  (function () {
    const modal = document.getElementById('modalEditRow');
    const form  = document.getElementById('formEditRow');
    if (!modal || !form) return;

    const titleEl = document.getElementById('editRowTitle');
    const typeEl  = document.getElementById('editRowTypeLabel');

    const wrapSeverity = document.getElementById('wrapSeverity');
    const selectSeverity = document.getElementById('edit_severity');
    const inputDue = document.getElementById('edit_due');

    const infoPjFed = document.getElementById('infoPjFed');
    const infoPicInd = document.getElementById('infoPicIndikator');
    const infoFedResult = document.getElementById('infoFedResult');
    const infoFedFactors = document.getElementById('infoFedFactors');
    const labelFedFactors = document.getElementById('labelFedFactors');

    const infoStdName = document.getElementById('infoStdName');
    const infoIndikatorHtml = document.getElementById('infoIndikatorHtml');

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      if (!btn) return;

      initSummernoteTemuan();

      const updateUrl = btn.getAttribute('data-update-url') || '';
      const isNeg = (btn.getAttribute('data-is-negative') || '0') === '1';
      const rowNo = btn.getAttribute('data-row-no') || '';

      form.action = updateUrl;

      if (titleEl) titleEl.textContent = `Isi Temuan - Baris #${rowNo}`;
      if (typeEl) typeEl.textContent = isNeg ? 'NEGATIF' : 'POSITIF';

      if (labelFedFactors) labelFedFactors.textContent = isNeg ? 'Faktor Penghambat (FED)' : 'Faktor Pendukung (FED)';

      if (infoPjFed) infoPjFed.textContent = btn.getAttribute('data-pj-fed') || '-';
      if (infoPicInd) infoPicInd.textContent = btn.getAttribute('data-pic-indikator') || '-';

      if (infoStdName) infoStdName.textContent = btn.getAttribute('data-std-name') || '-';
      if (infoIndikatorHtml) infoIndikatorHtml.innerHTML = safeHtml(b64decode(btn.getAttribute('data-indikator-html-b64')));

      if (infoFedResult) infoFedResult.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-result-b64')));
      if (infoFedFactors) infoFedFactors.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-factors-b64')));

      inputDue.value = btn.getAttribute('data-due') || '';

      if (isNeg) {
        wrapSeverity.style.display = '';
        selectSeverity.disabled = false;
        selectSeverity.value = btn.getAttribute('data-severity') || '';
      } else {
        wrapSeverity.style.display = 'none';
        selectSeverity.disabled = true;
        selectSeverity.value = '';
      }

      $(modal).find('.modal-body').scrollTop(0);

      setTimeout(() => {
        $('#edit_control').summernote('code', b64decode(btn.getAttribute('data-control-b64')));
        $('#edit_improvement').summernote('code', b64decode(btn.getAttribute('data-improvement-b64')));
        $('#edit_follow').summernote('code', b64decode(btn.getAttribute('data-follow-b64')));
        $('#edit_recommend').summernote('code', b64decode(btn.getAttribute('data-recommend-b64')));
        $('#edit_cap').summernote('code', b64decode(btn.getAttribute('data-cap-b64')));
      }, 80);
    });

    modal.addEventListener('hidden.bs.modal', function () {
      form.action = '';
      inputDue.value = '';
      wrapSeverity.style.display = 'none';
      selectSeverity.disabled = false;
      selectSeverity.value = '';

      if (infoPjFed) infoPjFed.textContent = '-';
      if (infoPicInd) infoPicInd.textContent = '-';
      if (infoStdName) infoStdName.textContent = '-';
      if (infoIndikatorHtml) infoIndikatorHtml.innerHTML = '';
      if (infoFedResult) infoFedResult.innerHTML = '';
      if (infoFedFactors) infoFedFactors.innerHTML = '';
      if (labelFedFactors) labelFedFactors.textContent = 'Faktor (FED)';

      if (summernoteTemuanInit) {
        $('#edit_control').summernote('code', '');
        $('#edit_improvement').summernote('code', '');
        $('#edit_follow').summernote('code', '');
        $('#edit_recommend').summernote('code', '');
        $('#edit_cap').summernote('code', '');
      }
    });
  })();
  @endif
</script>
@endpush
