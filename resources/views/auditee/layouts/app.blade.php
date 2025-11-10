<!DOCTYPE html>
<html lang="{{ str_replace('_','-', app()->getLocale()) }}" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Dashboard - Sistem Penjaminan Mutu')</title>

  {{-- Icons --}}
  <link rel="icon" href="{{ asset('assets/img/logo.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('assets/images/apple-touch-icon.png') }}">

  {{-- Fonts & Icons --}}
  <link rel="stylesheet" href="{{ asset('assets/fonts/inter/inter.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/icons/phosphor/styles.min.css') }}">

  {{-- Theme CSS --}}
  <link rel="stylesheet" href="{{ asset('assets/css/ltr/all.min.css') }}" id="stylesheet">

  @stack('styles')
</head>
<body>

  {{-- Header/Navbar --}}
  @include('auditee.layouts.header')

  {{-- Page content --}}
  <div class="page-content">
    {{-- Sidebar --}}
    @include('auditee.layouts.sidebar')

    {{-- Main content area --}}
    <div class="content-wrapper">
      <div class="content-inner">

        {{-- Optional: page header slot --}}
        @yield('page-header')

        {{-- Main Content --}}
        <div class="content">
          @yield('content')
        </div>

        {{-- Optional: page footer slot --}}
        @yield('page-footer')

      </div>
    </div>
  </div>

  {{-- Core JS --}}
  <script src="{{ asset('assets/js/jquery/jquery.min.js') }}"></script>
  <script src="{{ asset('assets/js/bootstrap/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('assets/js/app.js') }}"></script>
  <script src="{{ asset('assets/js/vendor/forms/selects/bootstrap_multiselect.js') }}"></script>
  <script src="{{ asset('assets/js/vendor/forms/selects/select2.min.js') }}"></script>

  {{-- Tempat inject script per halaman --}}
  @stack('scripts')
</body>
</html>
