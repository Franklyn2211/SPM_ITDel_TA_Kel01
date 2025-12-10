@php
  $homeUrl = route('admin.dashboard'); // ganti ke route('dashboard') kalau ada
@endphp

<div class="navbar navbar-dark navbar-expand-lg navbar-static border-bottom border-bottom-white border-opacity-10">
  <div class="container-fluid">

    {{-- Left: sidebar toggler (mobile) --}}
    <div class="d-flex d-lg-none me-2">
      <button type="button" class="navbar-toggler sidebar-mobile-main-toggle rounded-pill">
        <i class="ph-list"></i>
      </button>
    </div>

    {{-- Brand --}}
    <div class="navbar-brand flex-1 flex-lg-0">
      <a href="{{ $homeUrl }}" class="d-inline-flex align-items-center">
        <img src="{{ asset('assets/img/logo.png') }}" alt="Logo" style="height: 40px; width: auto;">
        <span class="d-none d-sm-inline-block ms-3 fw-bold text-white" style="font-size: 1.1rem; letter-spacing: 1px;">SPM IT Del</span>
      </a>
    </div>

    {{-- Center: search (collapsible on mobile) --}}
    {{-- <div class="navbar-collapse justify-content-center flex-lg-1 order-2 order-lg-1 collapse" id="navbar_search">
      <div class="navbar-search flex-fill position-relative mt-2 mt-lg-0 mx-lg-3">
        <div class="form-control-feedback form-control-feedback-start flex-grow-1" data-color-theme="dark">
          <input type="text" class="form-control bg-transparent rounded-pill" placeholder="Search">
          <div class="form-control-feedback-icon">
            <i class="ph-magnifying-glass"></i>
          </div>
        </div>
      </div>
    </div> --}}

    {{-- Right cluster --}}
    <ul class="nav flex-row justify-content-end order-1 order-lg-2">

      {{-- Search toggler (mobile) --}}
      <li class="nav-item d-lg-none">
        <a href="#navbar_search" class="navbar-nav-link navbar-nav-link-icon rounded-pill" data-bs-toggle="collapse">
          <i class="ph-magnifying-glass"></i>
        </a>
      </li>

      {{-- Notifications (optional; bisa disembunyikan kalau belum dipakai) --}}
      <li class="nav-item ms-lg-2">
        <a href="#" class="navbar-nav-link navbar-nav-link-icon rounded-pill" data-bs-toggle="offcanvas" data-bs-target="#notifications">
          <i class="ph-bell"></i>
        </a>
      </li>

      {{-- User dropdown --}}
      <li class="nav-item nav-item-dropdown-lg dropdown ms-lg-2">
        <a href="#" class="navbar-nav-link align-items-center rounded-pill p-1" data-bs-toggle="dropdown">
          <div class="status-indicator-container">
            <img src="{{ asset('assets/images/demo/users/face11.jpg') }}" class="w-32px h-32px rounded-pill" alt="Avatar">
            <span class="status-indicator bg-success"></span>
          </div>
          <span class="d-none d-lg-inline-block mx-lg-2">{{ Auth::user()->name ?? 'User' }}</span>
        </a>

        <div class="dropdown-menu dropdown-menu-end">
          <a href="#" class="dropdown-item">
            <i class="ph-user-circle me-2"></i> My profile
          </a>
          <a href="#" class="dropdown-item">
            <i class="ph-gear me-2"></i> Account settings
          </a>
          <div class="dropdown-divider"></div>

          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dropdown-item">
              <i class="ph-sign-out me-2"></i> Logout
            </button>
          </form>
        </div>
      </li>
    </ul>

  </div>
</div>
