@extends('auditee.layouts.app')

@section('title', 'Tindak Lanjut Temuan')

@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h3 class="page-title mb-0 fw-bold">
        Daftar Temuan untuk Ditindaklanjuti
      </h3>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <span class="breadcrumb-item"><i class="ph-house"></i></span>
        <span class="breadcrumb-item active">Temuan Audit</span>
      </div>

      <div class="ms-auto w-100 w-lg-auto">
        <form method="GET" class="row g-2 align-items-end justify-content-end">
          <div class="col-12 col-md-auto">
            <label class="form-label mb-1">Tahun Akademik</label>
            <select name="academic_id" class="form-select form-select-sm">
              <option value="">Semua Tahun Akademik</option>
              @foreach($academicOptions as $ac)
                <option value="{{ $ac->id }}" @if(($academicId ?? '') == $ac->id) selected @endif>{{ $ac->name ?? $ac->academic_code }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-md-auto">
            <label class="form-label mb-1">Unit/Prodi</label>
            <select name="prodi_id" class="form-select form-select-sm">
              <option value="">Semua Unit/Prodi</option>
              @foreach($prodiOptions as $p)
                <option value="{{ $p->id }}" @if(($prodiId ?? '') == $p->id) selected @endif>{{ $p->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-md-auto">
            <label class="form-label mb-1">Status</label>
            <select name="status_id" class="form-select form-select-sm">
              <option value="">Semua Status</option>
              @foreach($statusOptions as $s)
                <option value="{{ $s->id }}" @if(($statusId ?? '') == $s->id) selected @endif>{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-12 col-md-auto">
            <label class="form-label mb-1">Cari</label>
            <div class="input-group input-group-sm">
              <input type="text" class="form-control" name="q" value="{{ $q ?? '' }}" placeholder="Cari Unit/Prodi...">
              <button class="btn btn-outline-primary" type="submit">
                <i class="ph-magnifying-glass me-1"></i> Cari
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
<div class="content pt-0">

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
          <strong>Terjadi error:</strong>
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

  <div class="card mt-3">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h5 class="mb-0">Daftar FED</h5>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:60px;">#</th>
            <th>Unit / Prodi</th>
            <th style="width:220px;">Tahun Akademik</th>
            <th style="width:160px;">Status</th>
            <th class="text-end" style="width:200px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse($feds as $i => $fed)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td>
                <div class="fw-semibold">{{ $fed->categoryDetail->name ?? '-' }}</div>
              </td>
              <td>
                {{ $fed->academicConfig->name ?? $fed->academicConfig->tahun ?? '—' }}
              </td>
              <td>
                <span class="badge bg-success">Disetujui</span>
              </td>
              <td class="text-end">
                <a href="{{ route('auditee.temuan.show', $fed->id) }}"
                   class="btn btn-sm btn-outline-primary">
                  <i class="ph-note-pencil me-1"></i> Isi Tindak Lanjut
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center text-muted py-4">
                Belum ada temuan.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
