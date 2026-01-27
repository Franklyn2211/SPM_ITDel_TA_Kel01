{{-- Sidebar kiri utama --}}
<div class="sidebar sidebar-dark sidebar-main sidebar-expand-lg">

  {{-- Sidebar content --}}
  <div class="sidebar-content">

    {{-- Sidebar header --}}
    <div class="sidebar-section">
      <div class="sidebar-section-body d-flex justify-content-center">
        <h5 class="sidebar-resize-hide flex-grow-1 my-auto">Navigation</h5>

        <div>
          <button type="button" class="btn btn-flat-white btn-icon btn-sm rounded-pill border-transparent sidebar-control sidebar-main-resize d-none d-lg-inline-flex">
            <i class="ph-arrows-left-right"></i>
          </button>

          <button type="button" class="btn btn-flat-white btn-icon btn-sm rounded-pill border-transparent sidebar-mobile-main-toggle d-lg-none">
            <i class="ph-x"></i>
          </button>
        </div>
      </div>
    </div>

    {{-- Main navigation --}}
    <div class="sidebar-section">
      <ul class="nav nav-sidebar" data-nav-type="accordion">

        {{-- Main --}}
        <li class="nav-item-header pt-0">
          <div class="text-uppercase fs-sm lh-sm opacity-50 sidebar-resize-hide">Main</div>
          <i class="ph-dots-three sidebar-resize-show"></i>
        </li>

        <li class="nav-item">
          <a href="{{ route('auditor.dashboard') }}" class="nav-link {{ request()->is('/') ? 'active' : '' }}">
            <i class="ph-house"></i>
            <span>Dashboard</span>
          </a>
        </li>

        <li class="nav-item">
          <a href="{{ route('auditor.fed.index') }}" class="nav-link {{ request()->routeIs('auditor.fed.*') ? 'active' : '' }}">
            <i class="ph-layout"></i>
            <span>FED</span>
          </a>
        </li>


        <li class="nav-item">
          <a href="{{ route('auditor.temuan.index') }}"
              class="nav-link {{ request()->routeIs('auditor.temuan.*') ? 'active' : '' }}">
              <i class="ph-clipboard-text"></i>
              <span>Form Temuan</span>
          </a>
        </li>

        <li class="nav-item">
          <a href="{{ route('auditor.atl.index') }}"
              class="nav-link {{ request()->routeIs('auditor.atl.*') ? 'active' : '' }}">
              <i class="ph-archive-box"></i>
              <span>Audit Tindak Lanjut</span>
          </a>
        </li>



      </ul>
    </div>
    {{-- /main navigation --}}

  </div>
  {{-- /sidebar content --}}

</div>
