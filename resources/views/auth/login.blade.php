<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — {{ $brand['school_name'] ?? 'GGCAST' }}</title>

    @include('partials.branding-styles')
    <link rel="stylesheet" href="{{ asset('css/auth/login.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    @if(!empty($brand['favicon_url']))
        <link rel="icon" type="{{ $brand['favicon_mime'] ?? 'image/x-icon' }}" href="{{ $brand['favicon_url'] }}">
    @endif
</head>
<body class="login-page">

<div class="login-page-wrapper">
    <header class="login-topbar">
        <a href="{{ $brand['school_home_url'] ?? route('home') }}" class="login-back-btn" aria-label="Back to home">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            <span>Back</span>
        </a>

        @if(!empty($brand['logo_landscape_url']))
            <img src="{{ $brand['logo_landscape_url'] }}" alt="{{ $brand['school_name'] }}" class="login-topbar-logo">
        @elseif(!empty($brand['logo_url']))
            <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['school_name'] }}" class="login-topbar-logo">
        @endif
    </header>

    <main class="login-main">
        <div class="card login-card">
            <div class="login-logo-wrap">
                @if(!empty($brand['logo_url']))
                    <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['school_name'] }}" class="login-logo">
                @else
                    <img src="{{ asset('images/ggcastlogo.png') }}" alt="GGCAST Logo" class="login-logo">
                @endif
            </div>

            <h1 class="login-title">Welcome! Let's Begin</h1>
            <p class="login-subtitle">Log in to continue</p>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="login-field">
                    <input type="email" name="email" class="form-control" placeholder="Email" value="{{ old('email') }}" required autofocus autocomplete="email">
                </div>

                <div class="login-field">
                    <input type="password" name="password" class="form-control" placeholder="Password" required autocomplete="current-password">
                </div>

                <div class="login-options">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="#" class="login-forgot">Forgot password?</a>
                </div>

                @error('email')
                    <div class="login-alert">{{ $message }}</div>
                @enderror

                <button type="submit" class="login-submit">Login</button>

                <a href="{{ route('patron.register') }}" class="login-register">Register</a>
            </form>
        </div>
    </main>
</div>

</body>
</html>
