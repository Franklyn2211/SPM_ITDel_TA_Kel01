@extends('admin.layouts.app')

@section('title', 'Dashboard - Admin Sistem Penjaminan Mutu')

{{-- Page Header (di-render di dalam content-wrapper) --}}
@section('page-header')
<div class="page-header page-header-light shadow">
  <div class="page-header-content d-lg-flex">
    <div class="d-flex align-items-center">
      <h4 class="page-title mb-0">
        Home - <span class="fw-normal">Dashboard</span>
      </h4>
      <a href="#page_header" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
      </a>
    </div>

    <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
      <div class="d-lg-flex align-items-center">
        <a href="#" class="d-flex align-items-center text-body py-2 me-lg-3">
          <i class="ph-lifebuoy me-2"></i> Support
        </a>

        <div class="dropdown">
          <a href="#" class="d-flex align-items-center text-body dropdown-toggle py-2" data-bs-toggle="dropdown">
            <i class="ph-gear me-2"></i> <span>Settings</span>
          </a>
          <div class="dropdown-menu dropdown-menu-end">
            <a href="#" class="dropdown-item"><i class="ph-shield-warning me-2"></i> Account security</a>
            <a href="#" class="dropdown-item"><i class="ph-lock-key me-2"></i> Privacy</a>
            <div class="dropdown-divider"></div>
            <a href="#" class="dropdown-item"><i class="ph-gear me-2"></i> All settings</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="page-header-content border-top">
    <div class="d-flex align-items-center">
      <div class="breadcrumb py-2">
        <a href="{{ url('/') }}" class="breadcrumb-item"><i class="ph-house"></i></a>
        <a href="#" class="breadcrumb-item">Home</a>
        <span class="breadcrumb-item active">Dashboard</span>
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
  {{-- MAIN ROW --}}
  <div class="row">
    <div class="col-xl-8">

      {{-- Marketing summary (ringkas) --}}
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h5 class="mb-0">Marketing campaigns</h5>
          <div class="ms-auto">
            <span class="badge bg-success rounded-pill">28 active</span>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table text-nowrap">
            <thead>
              <tr>
                <th>Campaign</th>
                <th>Client</th>
                <th>Changes</th>
                <th>Budget</th>
                <th>Status</th>
                <th class="text-center" style="width: 20px;">
                  <i class="ph-dots-three"></i>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr class="table-light">
                <td colspan="5">Today</td>
                <td class="text-end">
                  <div class="progress" style="height:6px;">
                    <div class="progress-bar bg-primary" style="width:30%"></div>
                  </div>
                </td>
              </tr>

              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <a href="#" class="d-block me-3">
                      <img src="{{ asset('assets/images/brands/facebook.svg') }}" class="rounded-circle" width="36" height="36" alt="">
                    </a>
                    <div>
                      <a href="#" class="text-body fw-semibold">Facebook</a>
                      <div class="text-muted fs-sm">
                        <span class="d-inline-block bg-primary rounded-pill p-1 me-1"></span>
                        02:00 - 03:00
                      </div>
                    </div>
                  </div>
                </td>
                <td><span class="text-muted">Mintlime</span></td>
                <td><span class="text-success"><i class="ph-trend-up me-2"></i> 2.43%</span></td>
                <td><h6 class="mb-0">$5,489</h6></td>
                <td><span class="badge bg-primary bg-opacity-10 text-primary">Active</span></td>
                <td class="text-center">
                  <button class="btn btn-sm btn-light"><i class="ph-list"></i></button>
                </td>
              </tr>

              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <a href="#" class="d-block me-3">
                      <img src="{{ asset('assets/images/brands/youtube.svg') }}" class="rounded-circle" width="36" height="36" alt="">
                    </a>
                    <div>
                      <a href="#" class="text-body fw-semibold">YouTube</a>
                      <div class="text-muted fs-sm">
                        <span class="d-inline-block bg-danger rounded-pill p-1 me-1"></span>
                        13:00 - 14:00
                      </div>
                    </div>
                  </div>
                </td>
                <td><span class="text-muted">CDsoft</span></td>
                <td><span class="text-success"><i class="ph-trend-up me-2"></i> 3.12%</span></td>
                <td><h6 class="mb-0">$2,592</h6></td>
                <td><span class="badge bg-danger bg-opacity-10 text-danger">Closed</span></td>
                <td class="text-center">
                  <button class="btn btn-sm btn-light"><i class="ph-list"></i></button>
                </td>
              </tr>

              <tr class="table-light">
                <td colspan="5">Yesterday</td>
                <td class="text-end">
                  <div class="progress" style="height:6px;">
                    <div class="progress-bar bg-success" style="width:65%"></div>
                  </div>
                </td>
              </tr>

              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <a href="#" class="d-block me-3">
                      <img src="{{ asset('assets/images/brands/amazon.svg') }}" class="rounded-circle" width="36" height="36" alt="">
                    </a>
                    <div>
                      <a href="#" class="text-body fw-semibold">Amazon ads</a>
                      <div class="text-muted fs-sm">
                        <span class="d-inline-block bg-danger rounded-pill p-1 me-1"></span>
                        18:00 - 19:00
                      </div>
                    </div>
                  </div>
                </td>
                <td><span class="text-muted">Blueish</span></td>
                <td><span class="text-success"><i class="ph-trend-up me-2"></i> 6.79%</span></td>
                <td><h6 class="mb-0">$1,540</h6></td>
                <td><span class="badge bg-primary bg-opacity-10 text-primary">Active</span></td>
                <td class="text-center">
                  <button class="btn btn-sm btn-light"><i class="ph-list"></i></button>
                </td>
              </tr>

            </tbody>
          </table>
        </div>
      </div>

      {{-- Support tickets (ringkas) --}}
      <div class="card">
        <div class="card-header d-sm-flex align-items-sm-center py-sm-0">
          <h5 class="py-sm-2 my-sm-1">Support tickets</h5>
          <div class="mt-2 mt-sm-0 ms-sm-auto">
            <select class="form-select">
              <option selected>Aug, 24 - Aug, 30</option>
              <option>Aug, 17 - Aug, 23</option>
              <option>Aug, 10 - Aug, 16</option>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table text-nowrap">
            <thead>
              <tr>
                <th style="width: 60px">Due</th>
                <th style="width: 280px;">User</th>
                <th>Description</th>
                <th class="text-center" style="width: 20px;">
                  <i class="ph-dots-three"></i>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr class="table-light">
                <td colspan="3">Active tickets</td>
                <td class="text-end"><span class="badge bg-primary rounded-pill">24</span></td>
              </tr>

              <tr>
                <td class="text-center">
                  <h6 class="mb-0">12</h6>
                  <div class="fs-sm text-muted lh-1">hours</div>
                </td>
                <td>
                  <div class="d-flex align-items-center">
                    <a href="#" class="d-inline-flex align-items-center justify-content-center bg-teal text-white lh-1 rounded-pill w-40px h-40px me-3">
                      <span class="letter-icon"></span>
                    </a>
                    <div>
                      <a href="#" class="text-body fw-semibold letter-icon-title">Annabelle Doney</a>
                      <div class="d-flex align-items-center text-muted fs-sm">
                        <span class="bg-danger rounded-pill p-1 me-2"></span> Blocker
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <a href="#" class="text-body">
                    <div class="fw-semibold">[#1183] Workaround for OS X selects printing bug</div>
                    <span class="text-muted">Chrome fixed the bug several versions ago, thus rendering this...</span>
                  </a>
                </td>
                <td class="text-center">
                  <div class="dropdown">
                    <a href="#" class="text-body" data-bs-toggle="dropdown"><i class="ph-list"></i></a>
                    <div class="dropdown-menu dropdown-menu-end">
                      <a href="#" class="dropdown-item"><i class="ph-arrow-bend-up-left me-2"></i> Quick reply</a>
                      <a href="#" class="dropdown-item"><i class="ph-checks text-success me-2"></i> Resolve</a>
                      <a href="#" class="dropdown-item"><i class="ph-x text-danger me-2"></i> Close</a>
                    </div>
                  </div>
                </td>
              </tr>

              <tr>
                <td class="text-center">
                  <h6 class="mb-0">16</h6>
                  <div class="fs-sm text-muted lh-1">hours</div>
                </td>
                <td>
                  <div class="d-flex align-items-center">
                    <a href="#" class="d-inline-block me-3">
                      <img src="{{ asset('assets/images/demo/users/face15.jpg') }}" class="rounded-circle" width="40" height="40" alt="">
                    </a>
                    <div>
                      <a href="#" class="text-body fw-semibold">Chris Macintyre</a>
                      <div class="d-flex align-items-center text-muted fs-sm">
                        <span class="bg-primary rounded-pill p-1 me-2"></span> Medium
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <a href="#" class="text-body">
                    <div class="fw-semibold">[#1249] Vertically center carousel controls</div>
                    <span class="text-muted">Try any carousel control and reduce the screen width below...</span>
                  </a>
                </td>
                <td class="text-center">
                  <button class="btn btn-sm btn-light"><i class="ph-list"></i></button>
                </td>
              </tr>

              <tr class="table-light">
                <td colspan="3">Resolved tickets</td>
                <td class="text-end"><span class="badge bg-success rounded-pill">42</span></td>
              </tr>

              <tr>
                <td class="text-center">
                  <i class="ph-check text-success"></i>
                </td>
                <td>
                  <div class="d-flex align-items-center">
                    <a href="#" class="d-inline-flex align-items-center justify-content-center bg-success text-white lh-1 rounded-pill w-40px h-40px me-3">
                      <span class="letter-icon"></span>
                    </a>
                    <div>
                      <a href="#" class="text-body fw-semibold letter-icon-title">Alan Macedo</a>
                      <div class="d-flex align-items-center text-muted fs-sm">
                        <span class="bg-danger rounded-pill p-1 me-2"></span> Blocker
                      </div>
                    </div>
                  </div>
                </td>
                <td>
                  <a href="#" class="text-body">
                    <div>[#1046] Avoid some unnecessary HTML string</div>
                    <span class="text-muted">Rather than building a string of HTML and then parsing it...</span>
                  </a>
                </td>
                <td class="text-center">
                  <button class="btn btn-sm btn-light"><i class="ph-list"></i></button>
                </td>
              </tr>

            </tbody>
          </table>
        </div>
      </div>

    </div>

    <div class="col-xl-4">

      {{-- Quick stats --}}
      <div class="row g-3">
        <div class="col-lg-12">
          <div class="card bg-teal text-white">
            <div class="card-body">
              <div class="d-flex">
                <h3 class="mb-0">3,450</h3>
                <span class="badge bg-black bg-opacity-50 rounded-pill align-self-center ms-auto">+53.6%</span>
              </div>
              <div>Members online <div class="fs-sm opacity-75">489 avg</div></div>
              <div class="progress mt-3" style="height:6px;">
                <div class="progress-bar" style="width:72%"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-12">
          <div class="card bg-pink text-white">
            <div class="card-body">
              <div class="d-flex">
                <h3 class="mb-0">49.4%</h3>
                <a href="#" class="text-white ms-auto"><i class="ph-gear"></i></a>
              </div>
              <div>Current server load <div class="fs-sm opacity-75">34.6% avg</div></div>
              <div class="progress mt-3" style="height:6px;">
                <div class="progress-bar" style="width:49.4%"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-12">
          <div class="card bg-primary text-white">
            <div class="card-body">
              <div class="d-flex">
                <h3 class="mb-0">$18,390</h3>
                <a class="text-white ms-auto"><i class="ph-arrows-clockwise"></i></a>
              </div>
              <div>Today's revenue <div class="fs-sm opacity-75">$37,578 avg</div></div>
              <div class="progress mt-3" style="height:6px;">
                <div class="progress-bar" style="width:48%"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Daily sales (ringkas) --}}
      <div class="card mt-3">
        <div class="card-header d-flex align-items-center">
          <h5 class="mb-0">Daily sales stats</h5>
          <div class="ms-auto fw-bold text-success">$4,378</div>
        </div>

        <div class="table-responsive">
          <table class="table text-nowrap">
            <thead>
              <tr>
                <th class="w-100">Application</th>
                <th>Time</th>
                <th>Price</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <a href="#" class="d-inline-block me-3">
                      <img src="{{ asset('assets/images/demo/logos/1.svg') }}" alt="" height="36">
                    </a>
                    <div>
                      <a href="#" class="text-body fw-semibold letter-icon-title">Sigma application</a>
                      <div class="text-muted fs-sm">New order</div>
                    </div>
                  </div>
                </td>
                <td><span class="text-muted">06:28 pm</span></td>
                <td><strong>$49.90</strong></td>
              </tr>

              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <a href="#" class="d-inline-block me-3">
                      <img src="{{ asset('assets/images/demo/logos/2.svg') }}" alt="" height="36">
                    </a>
                    <div>
                      <a href="#" class="text-body fw-semibold letter-icon-title">Alpha application</a>
                      <div class="text-muted fs-sm">Renewal</div>
                    </div>
                  </div>
                </td>
                <td><span class="text-muted">04:52 pm</span></td>
                <td><strong>$90.50</strong></td>
              </tr>

            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
@endsection

@push('styles')
{{-- Tambahan styling kecil jika perlu --}}
<style>
  .letter-icon { width: 18px; height: 18px; display:block; }
</style>
@endpush

@push('scripts')
{{-- Tempatkan script khusus halaman di sini. Hindari load demo charts global. --}}
{{-- Contoh: jika nanti pakai ECharts / D3, include hanya di halaman ini. --}}
@endpush
