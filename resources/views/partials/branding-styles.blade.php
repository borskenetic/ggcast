@php
    $brandingCssPath = config('branding.css_path');
    $brandingCssFile = public_path($brandingCssPath);
    $brandingCssVersion = is_file($brandingCssFile) ? (string) filemtime($brandingCssFile) : null;
@endphp
{{-- Per-school colors — load after Vite so tokens override bundled defaults --}}
<link rel="stylesheet" href="{{ asset($brandingCssPath) }}{{ $brandingCssVersion ? '?v='.$brandingCssVersion : '' }}">
<link rel="stylesheet" href="{{ asset('css/brand-typography.css') }}">
