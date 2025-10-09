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
          <a href="{{ url('/') }}" class="nav-link {{ request()->is('/') ? 'active' : '' }}">
            <i class="ph-house"></i>
            <span>Dashboard</span>
          </a>
        </li>

        {{-- Example: Menu bertingkat --}}
        <li class="nav-item nav-item-submenu">
          <a href="#" class="nav-link">
            <i class="ph-layout"></i>
            <span>AMI</span>
          </a>
          <ul class="nav-group-sub collapse">
            <li class="nav-item">
              <a href="{{ route('auditee.ami.standard') }}" class="nav-link">Standar AMI</a>
            </li>
            <li class="nav-item">
              <a href="{{ route('auditee.ami.indicator') }}" class="nav-link">Indikator Kinerja</a>
            </li>
            <li class="nav-item">
              <a href="{{ route('auditee.ami.pic') }}" class="nav-link">Assign PIC Indikator</a>
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
        <li class="nav-item">
          <a href="{{ url('/components/buttons') }}" class="nav-link">
            <i class="ph-squares-four"></i>
            <span>Buttons</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="{{ url('/components/cards') }}" class="nav-link">
            <i class="ph-article"></i>
            <span>Cards</span>
          </a>
        </li>

        {{-- Changelog link (opsional) --}}
        <li class="nav-item">
          <a href="{{ url('/changelog') }}" class="nav-link">
            <i class="ph-list-numbers"></i>
            <span>Changelog</span>
          </a>
        </li>

      </ul>
    </div>
    {{-- /main navigation --}}

  </div>
  {{-- /sidebar content --}}

</div>
