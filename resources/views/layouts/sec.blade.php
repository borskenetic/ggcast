<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="{{ $brand['favicon_mime'] ?? 'image/x-icon' }}" href="{{ $brand['favicon_url'] ?? asset('images/pantasLogo.png') }}">
    <title>@yield('title', '📚 Book Kiosk')</title>

    {{-- Legacy page styles first; shell theme loads last so sidebar tokens win --}}
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/site-responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('css/books/index.css') }}">

    @stack('styles')
    @yield('styles')

    @vite(['resources/css/app.css', 'resources/js/admin-shell.jsx'])
    @include('partials.branding-styles')
</head>
<body class="theme font-sans antialiased admin-page">
    <script type="application/json" id="admin-shell-props">
        @json(\App\Support\AdminShell::pageProps(request()))
    </script>
    <div id="admin-shell-root" class="min-h-dvh w-full"></div>
    <div id="admin-blade-content" class="hidden">
        <div class="legacy-page-content">
            @yield('content')
        </div>
    </div>

    @yield('footer')

    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    @stack('scripts')
    @yield('scripts')
</body>
</html>
