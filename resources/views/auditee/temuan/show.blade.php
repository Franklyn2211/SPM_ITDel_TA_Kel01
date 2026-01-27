{{-- resources/views/auditee/temuan/show.blade.php --}}
@extends('auditee.layouts.app')

@section('title', 'Tindak Lanjut Temuan Audit')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h4 class="page-title mb-0">
        Tindak Lanjut Temuan - <span class="fw-normal">{{ $fed->categoryDetail->name ?? 'Unit/Prodi' }}</span>
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

        @if($isFinal)
            <a class="btn btn-primary btn-sm rounded-pill"
                href="{{ route('auditee.temuan.exportDocx', $form->id) }}">
            <i class="ph-download-simple me-1"></i> Unduh DOCX
            <a class="btn btn-primary btn-sm rounded-pill"
             href="{{ route('auditee.temuan.exportPdf', $form->id) }}">
            <i class="ph-download-simple me-1"></i> Unduh PDF
          </a>
        @endif
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ route('auditee.dashboard') }}" class="breadcrumb-item">
          <i class="ph-house"></i>
        </a>
        <a href="{{ route('auditee.temuan.index') }}" class="breadcrumb-item">Temuan</a>
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
        <div class="text-muted fs-sm">Progress Tindak Lanjut</div>
        <div class="d-flex flex-wrap gap-2 mt-1">
          <span class="badge bg-secondary rounded-pill">Total: {{ $progress['total'] ?? 0 }}</span>
          <span class="badge bg-success rounded-pill">Lengkap: {{ $progress['complete'] ?? 0 }}</span>
          <span class="badge bg-info rounded-pill">{{ $progress['percent'] ?? 0 }}%</span>
        </div>
        <div class="text-muted fs-sm mt-2">
          Catatan: Auditee mengisi sesuai jenis temuan. Form yang bukan bagian auditee tidak ditampilkan sebagai input.
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

    $isFilled = function ($v) {
      if (is_null($v)) return false;
      $s = trim((string) $v);
      $plain = trim(preg_replace('/\s+/', ' ', strip_tags($s)));
      return $plain !== '';
    };
  @endphp

  {{-- POSITIF --}}
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
            <th style="width:140px;" class="text-center">Status</th>
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

              // POSITIF auditee wajib: due_date + control + improvement + follow_up_plan
              $complete = $isFilled($r->control) && $isFilled($r->improvement) && $isFilled($r->follow_up_plan) && !is_null($r->due_date);
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
                          data-bs-target="#modalPosAuditee"

                          data-update-url="{{ route('auditee.temuan.row.update.auditee', [$form->id, $r->id]) }}"
                          data-row-no="{{ $rowNo }}"

                          data-std-name="{{ e($stdName) }}"
                          data-indikator-html-b64="{{ base64_encode($rawIndHtml) }}"

                          data-pj-fed="{{ e($pjFed) }}"
                          data-pic-indikator="{{ e($picsRole) }}"

                          data-fed-result-b64="{{ base64_encode($fedResult) }}"
                          data-fed-factors-b64="{{ base64_encode($fedFactors) }}"

                          data-due="{{ $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('Y-m-d') : '' }}"

                          data-control-b64="{{ base64_encode($r->control ?? '') }}"
                          data-improvement-b64="{{ base64_encode($r->improvement ?? '') }}"
                          data-follow-b64="{{ base64_encode($r->follow_up_plan ?? '') }}">
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

  {{-- NEGATIF --}}
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
          <th style="width:170px;" class="text-center">Kategori Temuan (Auditor)</th>
          <th style="width:140px;" class="text-center">Jadwal</th>
          <th style="width:140px;" class="text-center">Status</th>
          <th style="width:220px;">Aksi</th>
        </tr>
      </thead>

      <tbody>
        @forelse($rowsNegative as $r)
          @php
            $stdName = $r->selfEvaluationDetail?->indicator?->standard?->name ?? 'Standar';
            $rawIndHtml = $r->selfEvaluationDetail?->indicator?->description ?? '';

            $plainInd = trim(preg_replace('/\s+/', ' ', strip_tags($rawIndHtml)));
            $shortInd = \Illuminate\Support\Str::limit($plainInd, 220);

            $fedResult  = $r->selfEvaluationDetail?->result ?? '';
            $fedFactors = $r->selfEvaluationDetail?->contributing_factors ?? '';

            $picsRole = collect($r->selfEvaluationDetail?->indicator?->pics ?? [])
              ->map(fn($p) => $p->role?->name ?? null)
              ->filter()
              ->unique()
              ->values()
              ->implode(', ');
            $picsRole = $picsRole ?: '-';

            $severityLabel = $r->severity ? ($severityOptions[$r->severity] ?? $r->severity) : null;

            // ========= GATE: AUDITEE HARUS NUNGGU AUDITOR =========
            $auditorReady = $isFilled($r->severity) && $isFilled($r->auditor_recommendation);

            // NEGATIF auditee wajib: due_date + corrective_action_plan
            $auditeeComplete = $isFilled($r->corrective_action_plan) && !is_null($r->due_date);

            $badgeRow   = $auditeeComplete ? 'bg-success' : 'bg-secondary';
            $statusText = $auditeeComplete ? 'Lengkap' : 'Draft';

            $rowNo = $r->finding_no ?? $loop->iteration;

            // kalau auditor belum siap, tampilkan row "locked" (opsional)
            $rowMuted = !$auditorReady ? 'opacity-75' : '';
          @endphp

          <tr class="{{ $rowMuted }}">
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
              @if($auditorReady)
                <span class="badge bg-danger rounded-pill">{{ $severityLabel ?? '—' }}</span>
              @else
                <span class="badge bg-secondary rounded-pill">Menunggu Auditor</span>
              @endif
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
                @if(!$auditorReady)
                  <div class="text-muted fs-sm">
                    Auditor belum mengisi <b>kategori</b> & <b>rekomendasi</b>. Auditee belum bisa isi.
                  </div>
                @else
                  <button type="button"
                          class="btn btn-sm btn-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalNegAuditee"

                          data-update-url="{{ route('auditee.temuan.row.update.auditee', [$form->id, $r->id]) }}"
                          data-row-no="{{ $rowNo }}"

                          data-std-name="{{ e($stdName) }}"
                          data-indikator-html-b64="{{ base64_encode($rawIndHtml) }}"

                          data-pj-fed="{{ e($pjFed) }}"
                          data-pic-indikator="{{ e($picsRole) }}"

                          data-fed-result-b64="{{ base64_encode($fedResult) }}"
                          data-fed-factors-b64="{{ base64_encode($fedFactors) }}"

                          data-due="{{ $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('Y-m-d') : '' }}"
                          data-severity-label="{{ e($severityLabel ?? '') }}"
                          data-recommend-b64="{{ base64_encode($r->auditor_recommendation ?? '') }}"

                          data-cap-b64="{{ base64_encode($r->corrective_action_plan ?? '') }}">
                    <i class="ph-pencil-simple me-1"></i> Isi/Edit
                  </button>
                @endif
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

{{-- MODAL DESC --}}
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
     MODAL: POSITIF (AUDITEE EDIT)
     ======================= --}}
@if(!$isFinal)
<div class="modal fade" id="modalPosAuditee" tabindex="-1" aria-hidden="true" data-bs-focus="false">
  <div class="modal-dialog modal-xl">
    <form method="POST" id="formPosAuditee" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title" id="posAudTitle">Isi Tindak Lanjut Positif</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info py-2 mb-3">
          Temuan <b>POSITIF</b>: Auditee mengisi <b>Jadwal</b>, <b>Pengendalian</b>, <b>Peningkatan</b>, dan <b>Rencana Tindak Lanjut</b>.
        </div>

        {{-- INFO DARI FED --}}
        <div class="card border mb-3">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="text-muted fs-sm">Penanggung Jawab (FED)</div>
                <div class="fw-semibold" id="posa_infoPjFed">-</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted fs-sm">PIC Indikator (Master - Role)</div>
                <div class="fw-semibold" id="posa_infoPicIndikator">-</div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Standar & Butir Mutu</div>
                <div class="fw-semibold" id="posa_infoStdName">-</div>
                <div class="text-muted fs-sm mt-1">Indikator</div>
                <div class="border rounded p-2" id="posa_infoIndikatorHtml" style="white-space: normal;"></div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Deskripsi Kondisi (FED)</div>
                <div class="border rounded p-2" id="posa_infoFedResult" style="white-space: normal;"></div>
              </div>
              <div class="col-12">
                <div class="text-muted fs-sm">Faktor Pendukung (FED)</div>
                <div class="border rounded p-2" id="posa_infoFedFactors" style="white-space: normal;"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Jadwal Penyelesaian</label>
          <input type="date" name="due_date" id="posa_due" class="form-control">
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Pengendalian</label>
          <textarea name="control" id="posa_control" class="form-control summernote-auditee"></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Peningkatan</label>
          <textarea name="improvement" id="posa_improvement" class="form-control summernote-auditee"></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Rencana Tindak Lanjut</label>
          <textarea name="follow_up_plan" id="posa_follow" class="form-control summernote-auditee"></textarea>
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

{{-- =======================
     MODAL: NEGATIF (AUDITEE EDIT)
     ======================= --}}
<div class="modal fade" id="modalNegAuditee" tabindex="-1" aria-hidden="true" data-bs-focus="false">
  <div class="modal-dialog modal-xl">
    <form method="POST" id="formNegAuditee" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title" id="negaud_title">Isi Tindak Lanjut Negatif</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-warning py-2 mb-3">
          Temuan <b>NEGATIF</b>: Auditee mengisi <b>Jadwal</b> dan <b>Rencana Tindakan Koreksi</b>.
          Kategori & rekomendasi auditor hanya ditampilkan.
        </div>

        {{-- INFO DARI FED --}}
        <div class="card border mb-3">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="text-muted fs-sm">Penanggung Jawab (FED)</div>
                <div class="fw-semibold" id="negaud_infoPjFed">-</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted fs-sm">PIC Indikator (Master - Role)</div>
                <div class="fw-semibold" id="negaud_infoPicIndikator">-</div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Standar & Butir Mutu</div>
                <div class="fw-semibold" id="negaud_infoStdName">-</div>
                <div class="text-muted fs-sm mt-1">Indikator</div>
                <div class="border rounded p-2" id="negaud_infoIndikatorHtml" style="white-space: normal;"></div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Deskripsi Kondisi (FED)</div>
                <div class="border rounded p-2" id="negaud_infoFedResult" style="white-space: normal;"></div>
              </div>
              <div class="col-12">
                <div class="text-muted fs-sm">Faktor Penghambat (FED)</div>
                <div class="border rounded p-2" id="negaud_infoFedFactors" style="white-space: normal;"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <div class="text-muted fs-sm">Kategori Temuan (Auditor)</div>
            <div class="fw-semibold" id="negaud_severity">—</div>
          </div>
        </div>

        <div class="mb-3">
          <div class="text-muted fs-sm">Rekomendasi Auditor</div>
          <div class="border rounded p-2" id="negaud_recommend" style="white-space: normal;"></div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Jadwal Penyelesaian</label>
          <input type="date" name="due_date" id="negaud_due" class="form-control">
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Rencana Tindakan Koreksi</label>
          <textarea name="corrective_action_plan" id="negaud_cap" class="form-control summernote-auditee"></textarea>
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

  #modalPosAuditee .modal-body,
  #modalNegAuditee .modal-body {
    max-height: calc(100vh - 200px);
    overflow-y: auto;
  }

  .table td { vertical-align: top; }

  .note-modal { z-index: 1065 !important; }
  .note-popover { z-index: 1065 !important; }
  .note-toolbar { z-index: 1065; }
  .note-modal-backdrop { z-index: 1064 !important; }
  .note-modal, .note-modal * { pointer-events: auto; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>

<script>
  function safeHtml(html) {
    return html && String(html).trim() !== '' ? html : '<span class="text-muted">-</span>';
  }
  function b64decode(str) {
    if (!str) return '';
    try { return atob(str); } catch (e) { return ''; }
  }

  // === Summernote button a. (SAMA KAYAK ADMIN) ===
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

  @if(!$isFinal)
  let snInit = false;
  function initSummernoteAuditee() {
    if (snInit) return;

    $('.summernote-auditee').summernote({
      placeholder: 'Tulis deskripsi di sini...',
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
      dialogsInBody: true,
      disableDragAndDrop: false
    });

    snInit = true;
  }

  // POSITIF modal
  (function () {
    const modal = document.getElementById('modalPosAuditee');
    const form = document.getElementById('formPosAuditee');
    if (!modal || !form) return;

    const titleEl = document.getElementById('posAudTitle');

    const infoPjFed = document.getElementById('posa_infoPjFed');
    const infoPicInd = document.getElementById('posa_infoPicIndikator');
    const infoStd = document.getElementById('posa_infoStdName');
    const infoIndHtml = document.getElementById('posa_infoIndikatorHtml');
    const infoFedResult = document.getElementById('posa_infoFedResult');
    const infoFedFactors = document.getElementById('posa_infoFedFactors');

    const due = document.getElementById('posa_due');

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      if (!btn) return;

      initSummernoteAuditee();

      const rowNo = btn.getAttribute('data-row-no') || '';
      const updateUrl = btn.getAttribute('data-update-url') || '';
      form.action = updateUrl;

      if (titleEl) titleEl.textContent = `Isi Tindak Lanjut Positif - Baris #${rowNo}`;

      if (infoPjFed) infoPjFed.textContent = btn.getAttribute('data-pj-fed') || '-';
      if (infoPicInd) infoPicInd.textContent = btn.getAttribute('data-pic-indikator') || '-';
      if (infoStd) infoStd.textContent = btn.getAttribute('data-std-name') || '-';
      if (infoIndHtml) infoIndHtml.innerHTML = safeHtml(b64decode(btn.getAttribute('data-indikator-html-b64')));

      if (infoFedResult) infoFedResult.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-result-b64')));
      if (infoFedFactors) infoFedFactors.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-factors-b64')));

      if (due) due.value = btn.getAttribute('data-due') || '';

      setTimeout(() => {
        $('#posa_control').summernote('code', b64decode(btn.getAttribute('data-control-b64')));
        $('#posa_improvement').summernote('code', b64decode(btn.getAttribute('data-improvement-b64')));
        $('#posa_follow').summernote('code', b64decode(btn.getAttribute('data-follow-b64')));
      }, 50);

      $(modal).find('.modal-body').scrollTop(0);
    });

    modal.addEventListener('hidden.bs.modal', function () {
      form.action = '';
      if (due) due.value = '';
      if (snInit) {
        $('#posa_control').summernote('code', '');
        $('#posa_improvement').summernote('code', '');
        $('#posa_follow').summernote('code', '');
      }
    });
  })();

  // NEGATIF modal
  (function () {
    const modal = document.getElementById('modalNegAuditee');
    const form = document.getElementById('formNegAuditee');
    if (!modal || !form) return;

    const titleEl = document.getElementById('negaud_title');

    const infoPjFed = document.getElementById('negaud_infoPjFed');
    const infoPicInd = document.getElementById('negaud_infoPicIndikator');
    const infoStd = document.getElementById('negaud_infoStdName');
    const infoIndHtml = document.getElementById('negaud_infoIndikatorHtml');
    const infoFedResult = document.getElementById('negaud_infoFedResult');
    const infoFedFactors = document.getElementById('negaud_infoFedFactors');

    const sev = document.getElementById('negaud_severity');
    const rec = document.getElementById('negaud_recommend');

    const due = document.getElementById('negaud_due');

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      if (!btn) return;

      initSummernoteAuditee();

      const rowNo = btn.getAttribute('data-row-no') || '';
      const updateUrl = btn.getAttribute('data-update-url') || '';
      form.action = updateUrl;

      if (titleEl) titleEl.textContent = `Isi Tindak Lanjut Negatif - Baris #${rowNo}`;

      if (infoPjFed) infoPjFed.textContent = btn.getAttribute('data-pj-fed') || '-';
      if (infoPicInd) infoPicInd.textContent = btn.getAttribute('data-pic-indikator') || '-';
      if (infoStd) infoStd.textContent = btn.getAttribute('data-std-name') || '-';
      if (infoIndHtml) infoIndHtml.innerHTML = safeHtml(b64decode(btn.getAttribute('data-indikator-html-b64')));

      if (infoFedResult) infoFedResult.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-result-b64')));
      if (infoFedFactors) infoFedFactors.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-factors-b64')));

      if (sev) sev.textContent = (btn.getAttribute('data-severity-label') || '').trim() || '—';
      if (rec) rec.innerHTML = safeHtml(b64decode(btn.getAttribute('data-recommend-b64')));

      if (due) due.value = btn.getAttribute('data-due') || '';

      setTimeout(() => {
        $('#negaud_cap').summernote('code', b64decode(btn.getAttribute('data-cap-b64')));
      }, 50);

      $(modal).find('.modal-body').scrollTop(0);
    });

    modal.addEventListener('hidden.bs.modal', function () {
      form.action = '';
      if (due) due.value = '';
      if (snInit) $('#negaud_cap').summernote('code', '');
      if (sev) sev.textContent = '—';
      if (rec) rec.innerHTML = '';
    });
  })();
  @endif
</script>
@endpush

