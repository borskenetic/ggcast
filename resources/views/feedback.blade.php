<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback — {{ $brand['library_name'] ?? $brand['school_name'] }}</title>

    @include('partials.branding-styles')
    <link rel="stylesheet" href="{{ asset('css/feedback.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    @if(!empty($brand['favicon_url']))
        <link rel="icon" type="{{ $brand['favicon_mime'] ?? 'image/x-icon' }}" href="{{ $brand['favicon_url'] }}">
    @endif
</head>
<body class="feedback-page">

<div class="feedback-page-wrapper">
    <header class="feedback-topbar">
        <a href="{{ $brand['school_home_url'] ?? route('home') }}" class="feedback-back-btn" aria-label="Back to home">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>
            <span>Back</span>
        </a>

        @if(!empty($brand['logo_landscape_url']))
            <img src="{{ $brand['logo_landscape_url'] }}" alt="{{ $brand['school_name'] }}" class="feedback-logo">
        @elseif(!empty($brand['logo_url']))
            <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['school_name'] }}" class="feedback-logo feedback-logo--compact">
        @endif
    </header>

    <main class="feedback-main">
        <div class="feedback-container">
            <div class="feedback-header">
                <div class="feedback-icon" aria-hidden="true">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <h1>Your Feedback Matters!</h1>
                <p class="feedback-subtitle">We'd love to hear about your experience at {{ $brand['library_name'] ?? 'our library' }}.</p>
            </div>

            <form method="POST" action="{{ route('feedback.store') }}" class="feedback-form">
                @csrf

                <div class="feedback-field">
                    <label for="name">Name <span class="optional">(optional)</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Enter your name" autocomplete="name">
                </div>

                <div class="feedback-field">
                    <label for="email">Email <span class="optional">(optional)</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="Enter your email" autocomplete="email">
                </div>

                <div class="feedback-field">
                    <label for="comments">Comments <span class="required">*</span></label>
                    <textarea id="comments" name="comments" rows="5" placeholder="Share your detailed feedback here..." required>{{ old('comments') }}</textarea>
                    @error('comments')
                        <p class="feedback-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="feedback-submit">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                    Submit Feedback
                </button>
            </form>
        </div>
    </main>
</div>

@if(session('success'))
    <div class="feedback-popup" id="feedbackPopup" role="status" aria-live="polite">
        <i class="fas fa-check-circle" aria-hidden="true"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const popup = document.getElementById('feedbackPopup');
        if (!popup) return;

        setTimeout(() => popup.classList.add('show'), 100);
        setTimeout(() => {
            popup.classList.add('fade-out');
            setTimeout(() => popup.remove(), 500);
        }, 3500);
    });
</script>

</body>
</html>
