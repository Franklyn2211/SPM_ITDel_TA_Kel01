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
          <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->is('admin.dashboard') ? 'active' : '' }}">
            <i class="ph-house"></i>
            <span>Dashboard</span>
          </a>
        </li>

        <li class="nav-item nav-item-submenu">
          <a href="#" class="nav-link">
            <i class="ph-user"></i>
            <span>Roles</span>
          </a>
          <ul class="nav-group-sub collapse">
            <li class="nav-item">
              <a href="{{ route('admin.roles.index') }}" class="nav-link">Manage Role</a>
            </li>
            <li class="nav-item">
              <a href="{{ route('admin.roles.add') }}" class="nav-link">Tambah Role</a>
            </li>
          </ul>
        </li>

        {{-- Forms (contoh ringkas) --}}
        <li class="nav-item-header">
          <div class="text-uppercase fs-sm lh-sm opacity-50 sidebar-resize-hide">Forms</div>
          <i class="ph-dots-three sidebar-resize-show"></i>
        </li>
        <li class="nav-item nav-item-submenu">
          <a href="#" class="nav-link">
            <i class="ph-note-pencil"></i>
            <span>Form components</span>
          </a>
          <ul class="nav-group-sub collapse">
            <li class="nav-item"><a href="{{ url('/forms/inputs') }}" class="nav-link">Input fields</a></li>
            <li class="nav-item"><a href="{{ url('/forms/select') }}" class="nav-link">Selects</a></li>
            <li class="nav-item"><a href="{{ url('/forms/validation') }}" class="nav-link">Validation</a></li>
          </ul>
        </li>

        {{-- Components (contoh ringkas) --}}
        <li class="nav-item-header">
          <div class="text-uppercase fs-sm lh-sm opacity-50 sidebar-resize-hide">Components</div>
          <i class="ph-dots-three sidebar-resize-show"></i>
        </li>
        {{-- Example: Menu bertingkat --}}
        <li class="nav-item nav-item-submenu">
          <a href="#" class="nav-link">
            <i class="ph-tree-structure"></i>
            <span>Kategori</span>
          </a>
          <ul class="nav-group-sub collapse">
            <li class="nav-item">
              <a href="{{ route('admin.ref_category.index') }}" class="nav-link">Kategori</a>
            </li>
            <li class="nav-item">
              <a href="{{ route('admin.ref_category.detail') }}" class="nav-link">Detail</a>
            </li>
          </ul>
        </li>

        {{-- Changelog link (opsional) --}}
        <li class="nav-item">
          <a href="{{ route('admin.academic_config.index') }}" class="nav-link">
            <i class="ph-gear-six"></i>
            <span>Konfigurasi Akademik</span>
          </a>
        </li>

      </ul>
    </div>
    {{-- /main navigation --}}

  </div>
  {{-- /sidebar content --}}

</div>
