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
          Catatan: Auditor cuma isi <b>NEGATIF</b> (Kategori + Rekomendasi). Input auditee tidak ditampilkan sebagai form di sini.
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

  {{-- =======================
       1) TEMUAN POSITIF (view only)
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
            <th style="width:220px;" class="text-center">Status Baris</th>
            <th style="width:160px;">Aksi</th>
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

              $auditeeComplete = $isFilled($r->control) && $isFilled($r->improvement) && $isFilled($r->follow_up_plan) && !is_null($r->due_date);
              $badgeRow = $auditeeComplete ? 'bg-success' : 'bg-secondary';
              $statusText = 'Auditee: ' . ($auditeeComplete ? 'Lengkap' : 'Draft');

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
                <button type="button"
                        class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#modalPosView"

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
                  <i class="ph-eye me-1"></i> Lihat
                </button>
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
            <th style="width:240px;" class="text-center">Status Baris</th>
            <th style="width:170px;">Aksi</th>
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

              $auditorComplete = $isFilled($r->severity) && $isFilled($r->auditor_recommendation);
              $auditeeComplete = $isFilled($r->corrective_action_plan) && !is_null($r->due_date);

              $overallComplete = $auditorComplete && $auditeeComplete;
              $badgeRow = $overallComplete ? 'bg-success' : 'bg-secondary';
              $statusText = 'Auditor: '.($auditorComplete?'Lengkap':'Draft').' | Auditee: '.($auditeeComplete?'Lengkap':'Draft');

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
                  <button type="button"
                          class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalNegView"
                          data-row-no="{{ $rowNo }}"
                          data-std-name="{{ e($stdName) }}"
                          data-indikator-html-b64="{{ base64_encode($rawIndHtml) }}"
                          data-pj-fed="{{ e($pjFed) }}"
                          data-pic-indikator="{{ e($picsRole) }}"
                          data-fed-result-b64="{{ base64_encode($fedResult) }}"
                          data-fed-factors-b64="{{ base64_encode($fedFactors) }}"
                          data-due="{{ $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('Y-m-d') : '' }}"
                          data-severity="{{ e($r->severity ?? '') }}"
                          data-recommend-b64="{{ base64_encode($r->auditor_recommendation ?? '') }}"
                          data-cap-b64="{{ base64_encode($r->corrective_action_plan ?? '') }}">
                    <i class="ph-eye me-1"></i> Lihat
                  </button>
                @else
                  <button type="button"
                          class="btn btn-sm btn-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalNegAuditor"

                          data-update-url="{{ route('auditor.temuan.row.update.auditor', [$form->id, $r->id]) }}"
                          data-row-no="{{ $rowNo }}"

                          data-std-name="{{ e($stdName) }}"
                          data-indikator-html-b64="{{ base64_encode($rawIndHtml) }}"

                          data-pj-fed="{{ e($pjFed) }}"
                          data-pic-indikator="{{ e($picsRole) }}"

                          data-fed-result-b64="{{ base64_encode($fedResult) }}"
                          data-fed-factors-b64="{{ base64_encode($fedFactors) }}"

                          data-due="{{ $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('Y-m-d') : '' }}"
                          data-severity="{{ e($r->severity ?? '') }}"

                          data-recommend-b64="{{ base64_encode($r->auditor_recommendation ?? '') }}"
                          data-cap-b64="{{ base64_encode($r->corrective_action_plan ?? '') }}">
                    <i class="ph-pencil-simple me-1"></i> Isi Auditor
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
          Ketua auditor ditentukan oleh Admin (tidak bisa diubah). Di sini hanya pilih anggota auditor.
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
            <label class="form-label fw-semibold">Ketua Auditor</label>
            <input type="text" class="form-control"
                   value="{{ $form->auditorUserRole?->user?->name ?? '—' }}"
                   readonly>
            <div class="text-muted fs-sm mt-1">Tidak dapat diubah.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Anggota Auditor</label>

            {{-- Select2 AJAX --}}
            <select name="member_auditor_user_role_id"
                    id="member_auditor_user_role_id"
                    class="form-control form-control-select2"
                    data-placeholder="Cari nama auditor..."
                    data-url="{{ route('auditor.temuan.searchAuditors') }}">
              @php
                $selected = old('member_auditor_user_role_id', $form->member_auditor_user_role_id);
                // butuh relasi memberAuditorUserRole = UserRole (bukan User)
                $selectedName = $form->memberAuditorUserRole?->user?->name
                             ?? $form->memberAuditorUserRole?->user?->username;
                $selectedRole = $form->memberAuditorUserRole?->role?->name;
              @endphp

              @if($selected)
                <option value="{{ $selected }}" selected>
                  {{ $selectedName ?? 'Tanpa Nama' }}{{ $selectedRole ? ' (' . $selectedRole . ')' : '' }}
                </option>
              @endif
            </select>

            <div class="text-muted fs-sm mt-1">Anggota bisa edit baris, tapi tidak bisa Final.</div>
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
     MODAL: POSITIF (VIEW ONLY)
     ======================= --}}
<div class="modal fade" id="modalPosView" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="posViewTitle">Detail Temuan Positif</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info py-2 mb-3">
          Ini temuan <b>POSITIF</b>. Auditor hanya melihat.
        </div>

        <div class="card border mb-3">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="text-muted fs-sm">Penanggung Jawab (FED)</div>
                <div class="fw-semibold" id="pos_infoPjFed">-</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted fs-sm">PIC Indikator (Master - Role)</div>
                <div class="fw-semibold" id="pos_infoPicIndikator">-</div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Standar & Butir Mutu</div>
                <div class="fw-semibold" id="pos_infoStdName">-</div>
                <div class="text-muted fs-sm mt-1">Indikator</div>
                <div class="border rounded p-2" id="pos_infoIndikatorHtml" style="white-space: normal;"></div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Deskripsi Kondisi (FED)</div>
                <div class="border rounded p-2" id="pos_infoFedResult" style="white-space: normal;"></div>
              </div>
              <div class="col-12">
                <div class="text-muted fs-sm">Faktor Pendukung (FED)</div>
                <div class="border rounded p-2" id="pos_infoFedFactors" style="white-space: normal;"></div>
              </div>
            </div>
          </div>
        </div>

        <h6 class="mb-2">Input Auditee</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="text-muted fs-sm">Jadwal Penyelesaian</div>
            <div class="fw-semibold" id="pos_due">—</div>
          </div>

          <div class="col-12">
            <div class="text-muted fs-sm">Pengendalian</div>
            <div class="border rounded p-2" id="pos_control" style="white-space: normal;"></div>
          </div>

          <div class="col-12">
            <div class="text-muted fs-sm">Peningkatan</div>
            <div class="border rounded p-2" id="pos_improvement" style="white-space: normal;"></div>
          </div>

          <div class="col-12">
            <div class="text-muted fs-sm">Rencana Tindak Lanjut</div>
            <div class="border rounded p-2" id="pos_follow" style="white-space: normal;"></div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

{{-- =======================
     MODAL: NEGATIF (VIEW ONLY)
     ======================= --}}
<div class="modal fade" id="modalNegView" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="negViewTitle">Detail Temuan Negatif</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-info py-2 mb-3">
          Form sudah Final. Tampilan hanya baca.
        </div>

        <div class="card border mb-3">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="text-muted fs-sm">Penanggung Jawab (FED)</div>
                <div class="fw-semibold" id="negv_infoPjFed">-</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted fs-sm">PIC Indikator (Master - Role)</div>
                <div class="fw-semibold" id="negv_infoPicIndikator">-</div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Standar & Butir Mutu</div>
                <div class="fw-semibold" id="negv_infoStdName">-</div>
                <div class="text-muted fs-sm mt-1">Indikator</div>
                <div class="border rounded p-2" id="negv_infoIndikatorHtml" style="white-space: normal;"></div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Deskripsi Kondisi (FED)</div>
                <div class="border rounded p-2" id="negv_infoFedResult" style="white-space: normal;"></div>
              </div>
              <div class="col-12">
                <div class="text-muted fs-sm">Faktor Penghambat (FED)</div>
                <div class="border rounded p-2" id="negv_infoFedFactors" style="white-space: normal;"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <div class="text-muted fs-sm">Jadwal (Auditee)</div>
            <div class="fw-semibold" id="negv_due">—</div>
          </div>
          <div class="col-md-4">
            <div class="text-muted fs-sm">Kategori (Auditor)</div>
            <div class="fw-semibold" id="negv_severity">—</div>
          </div>
        </div>

        <div class="mt-3">
          <div class="text-muted fs-sm">Rekomendasi Auditor</div>
          <div class="border rounded p-2" id="negv_recommend" style="white-space: normal;"></div>
        </div>

        <div class="mt-3">
          <div class="text-muted fs-sm">Rencana Tindakan Koreksi (Auditee)</div>
          <div class="border rounded p-2" id="negv_cap" style="white-space: normal;"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

{{-- =======================
     MODAL: NEGATIF (AUDITOR EDIT ONLY)
     ======================= --}}
@if(!$isFinal)
<div class="modal fade" id="modalNegAuditor" tabindex="-1" aria-hidden="true" data-bs-focus="false">
  <div class="modal-dialog modal-xl">
    <form method="POST" id="formNegAuditor" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header">
        <h5 class="modal-title" id="negAudTitle">Isi Auditor - Temuan Negatif</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>

      <div class="modal-body">
        <div class="alert alert-warning py-2 mb-3">
          Auditor <b>hanya</b> mengisi: <b>Kategori Temuan</b> dan <b>Rekomendasi Auditor</b>.
          Input auditee ditampilkan read-only biar konteksnya ada.
        </div>

        <div class="card border mb-3">
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="text-muted fs-sm">Penanggung Jawab (FED)</div>
                <div class="fw-semibold" id="nega_infoPjFed">-</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted fs-sm">PIC Indikator (Master - Role)</div>
                <div class="fw-semibold" id="nega_infoPicIndikator">-</div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Standar & Butir Mutu</div>
                <div class="fw-semibold" id="nega_infoStdName">-</div>
                <div class="text-muted fs-sm mt-1">Indikator</div>
                <div class="border rounded p-2" id="nega_infoIndikatorHtml" style="white-space: normal;"></div>
              </div>

              <div class="col-12">
                <div class="text-muted fs-sm">Deskripsi Kondisi (FED)</div>
                <div class="border rounded p-2" id="nega_infoFedResult" style="white-space: normal;"></div>
              </div>
              <div class="col-12">
                <div class="text-muted fs-sm">Faktor Penghambat (FED)</div>
                <div class="border rounded p-2" id="nega_infoFedFactors" style="white-space: normal;"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-2">
          <div class="col-md-4">
            <div class="text-muted fs-sm">Jadwal (Auditee)</div>
            <div class="fw-semibold" id="nega_due">—</div>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Kategori Temuan (Negatif)</label>
          <select name="severity" id="nega_severity" class="form-select">
            <option value="">Pilih kategori…</option>
            @foreach(($severityOptions ?? []) as $val => $label)
              <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Rekomendasi Auditor</label>
          <textarea name="auditor_recommendation" id="nega_recommend" class="form-control summernote-auditor"></textarea>
        </div>

        <div class="mb-3">
          <div class="text-muted fs-sm">Rencana Tindakan Koreksi (Auditee)</div>
          <div class="border rounded p-2" id="nega_cap" style="white-space: normal;"></div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
        <button type="submit" class="btn btn-primary">
          <i class="ph-floppy-disk me-1"></i> Simpan (Auditor)
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

  #modalNegAuditor .modal-body,
  #modalPosView .modal-body,
  #modalNegView .modal-body {
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>

<script>
  // Utils
  function safeHtml(html) {
    return html && String(html).trim() !== '' ? html : '<span class="text-muted">-</span>';
  }
  function b64decode(str) {
    if (!str) return '';
    try { return atob(str); } catch (e) { return ''; }
  }
  function dashIfEmpty(v) {
    const s = (v ?? '').toString().trim();
    return s === '' ? '—' : s;
  }

  // ================== SELECT2 AJAX ANGGOTA AUDITOR ==================
  (function () {
    const modalEl = document.getElementById('modalHeader');
    if (!modalEl) return;

    modalEl.addEventListener('shown.bs.modal', function () {
      const $el = $('#member_auditor_user_role_id');
      if (!$el.length) return;

      const url = $el.data('url');
      if (!url) return;

      if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
      }

      $el.select2({
        width: '100%',
        dropdownParent: $('#modalHeader'),
        placeholder: $el.data('placeholder') || 'Cari nama auditor...',
        allowClear: true,
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
              results: (data || []).map(function (item) {
                // backend idealnya ngirim {id, text, role_name}
                return {
                  id: item.id,
                  text: item.text || item.name || 'Tanpa Nama',
                  role: item.role_name || item.role || null
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
          if (data.role) $('<div class="text-muted small"></div>').text(data.role).appendTo($wrap);
          return $wrap;
        },
        templateSelection: function (data) {
          if (!data.id) return $el.data('placeholder') || 'Pilih auditor';
          return data.text + (data.role ? ' (' + data.role + ')' : '');
        }
      });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      const $el = $('#member_auditor_user_role_id');
      if ($el.hasClass('select2-hidden-accessible')) {
        $el.select2('destroy');
      }
    });
  })();

  // === Summernote custom button ===
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
      if (bodyEl) bodyEl.innerHTML = safeHtml(b64decode(b64));
    });
  })();

  // POSITIF VIEW modal
  (function () {
    const modal = document.getElementById('modalPosView');
    if (!modal) return;

    const titleEl = document.getElementById('posViewTitle');

    const infoPjFed = document.getElementById('pos_infoPjFed');
    const infoPicInd = document.getElementById('pos_infoPicIndikator');
    const infoStd = document.getElementById('pos_infoStdName');
    const infoIndHtml = document.getElementById('pos_infoIndikatorHtml');
    const infoFedResult = document.getElementById('pos_infoFedResult');
    const infoFedFactors = document.getElementById('pos_infoFedFactors');

    const due = document.getElementById('pos_due');
    const control = document.getElementById('pos_control');
    const improvement = document.getElementById('pos_improvement');
    const follow = document.getElementById('pos_follow');

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      const rowNo = btn?.getAttribute('data-row-no') || '';

      if (titleEl) titleEl.textContent = `Detail Temuan Positif - Baris #${rowNo}`;

      if (infoPjFed) infoPjFed.textContent = btn.getAttribute('data-pj-fed') || '-';
      if (infoPicInd) infoPicInd.textContent = btn.getAttribute('data-pic-indikator') || '-';
      if (infoStd) infoStd.textContent = btn.getAttribute('data-std-name') || '-';
      if (infoIndHtml) infoIndHtml.innerHTML = safeHtml(b64decode(btn.getAttribute('data-indikator-html-b64')));

      if (infoFedResult) infoFedResult.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-result-b64')));
      if (infoFedFactors) infoFedFactors.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-factors-b64')));

      if (due) due.textContent = dashIfEmpty(btn.getAttribute('data-due'));
      if (control) control.innerHTML = safeHtml(b64decode(btn.getAttribute('data-control-b64')));
      if (improvement) improvement.innerHTML = safeHtml(b64decode(btn.getAttribute('data-improvement-b64')));
      if (follow) follow.innerHTML = safeHtml(b64decode(btn.getAttribute('data-follow-b64')));
    });
  })();

  // NEGATIF VIEW modal (final)
  (function () {
    const modal = document.getElementById('modalNegView');
    if (!modal) return;

    const titleEl = document.getElementById('negViewTitle');

    const infoPjFed = document.getElementById('negv_infoPjFed');
    const infoPicInd = document.getElementById('negv_infoPicIndikator');
    const infoStd = document.getElementById('negv_infoStdName');
    const infoIndHtml = document.getElementById('negv_infoIndikatorHtml');
    const infoFedResult = document.getElementById('negv_infoFedResult');
    const infoFedFactors = document.getElementById('negv_infoFedFactors');

    const due = document.getElementById('negv_due');
    const sev = document.getElementById('negv_severity');
    const rec = document.getElementById('negv_recommend');
    const cap = document.getElementById('negv_cap');

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      const rowNo = btn?.getAttribute('data-row-no') || '';
      if (titleEl) titleEl.textContent = `Detail Temuan Negatif - Baris #${rowNo}`;

      if (infoPjFed) infoPjFed.textContent = btn.getAttribute('data-pj-fed') || '-';
      if (infoPicInd) infoPicInd.textContent = btn.getAttribute('data-pic-indikator') || '-';
      if (infoStd) infoStd.textContent = btn.getAttribute('data-std-name') || '-';
      if (infoIndHtml) infoIndHtml.innerHTML = safeHtml(b64decode(btn.getAttribute('data-indikator-html-b64')));

      if (infoFedResult) infoFedResult.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-result-b64')));
      if (infoFedFactors) infoFedFactors.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-factors-b64')));

      if (due) due.textContent = dashIfEmpty(btn.getAttribute('data-due'));
      if (sev) sev.textContent = dashIfEmpty(btn.getAttribute('data-severity'));
      if (rec) rec.innerHTML = safeHtml(b64decode(btn.getAttribute('data-recommend-b64')));
      if (cap) cap.innerHTML = safeHtml(b64decode(btn.getAttribute('data-cap-b64')));
    });
  })();

  // NEGATIF AUDITOR modal (edit)
  @if(!$isFinal)
  let snAudInit = false;
  function initSummernoteAuditor() {
    if (snAudInit) return;

    $('.summernote-auditor').summernote({
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
      dialogsInBody: true
    });

    snAudInit = true;
  }

  (function () {
    const modal = document.getElementById('modalNegAuditor');
    const form = document.getElementById('formNegAuditor');
    if (!modal || !form) return;

    const titleEl = document.getElementById('negAudTitle');

    const infoPjFed = document.getElementById('nega_infoPjFed');
    const infoPicInd = document.getElementById('nega_infoPicIndikator');
    const infoStd = document.getElementById('nega_infoStdName');
    const infoIndHtml = document.getElementById('nega_infoIndikatorHtml');
    const infoFedResult = document.getElementById('nega_infoFedResult');
    const infoFedFactors = document.getElementById('nega_infoFedFactors');

    const due = document.getElementById('nega_due');
    const sevSel = document.getElementById('nega_severity');
    const cap = document.getElementById('nega_cap');

    modal.addEventListener('show.bs.modal', function (ev) {
      const btn = ev.relatedTarget;
      if (!btn) return;

      initSummernoteAuditor();

      const rowNo = btn.getAttribute('data-row-no') || '';
      const updateUrl = btn.getAttribute('data-update-url') || '';
      form.action = updateUrl;

      if (titleEl) titleEl.textContent = `Isi Auditor - Temuan Negatif (Baris #${rowNo})`;

      if (infoPjFed) infoPjFed.textContent = btn.getAttribute('data-pj-fed') || '-';
      if (infoPicInd) infoPicInd.textContent = btn.getAttribute('data-pic-indikator') || '-';
      if (infoStd) infoStd.textContent = btn.getAttribute('data-std-name') || '-';
      if (infoIndHtml) infoIndHtml.innerHTML = safeHtml(b64decode(btn.getAttribute('data-indikator-html-b64')));

      if (infoFedResult) infoFedResult.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-result-b64')));
      if (infoFedFactors) infoFedFactors.innerHTML = safeHtml(b64decode(btn.getAttribute('data-fed-factors-b64')));

      if (due) due.textContent = dashIfEmpty(btn.getAttribute('data-due'));
      if (sevSel) sevSel.value = btn.getAttribute('data-severity') || '';
      if (cap) cap.innerHTML = safeHtml(b64decode(btn.getAttribute('data-cap-b64')));

      setTimeout(() => {
        $('#nega_recommend').summernote('code', b64decode(btn.getAttribute('data-recommend-b64')));
      }, 50);

      $(modal).find('.modal-body').scrollTop(0);
    });

    modal.addEventListener('hidden.bs.modal', function () {
      form.action = '';
      if (sevSel) sevSel.value = '';
      if (snAudInit) $('#nega_recommend').summernote('code', '');
    });
  })();
  @endif
</script>
@endpush
