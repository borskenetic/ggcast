@extends('layouts.sec')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/ebooks/ebooks.css') }}">
@endsection

@section('content')
<div class="ebooks-page">
    <header class="ebooks-page__hero">
        <div>
            <p class="ebooks-page__eyebrow">Digital library</p>
            <h1 class="ebooks-page__title">Edit e-resource</h1>
            <p class="ebooks-page__subtitle">{{ $ebook->title }}</p>
        </div>
        <div class="ebooks-page__hero-actions">
            <a href="{{ route('ebooks.index') }}" class="ebooks-btn ebooks-btn--outline">← Collection</a>
            @if($ebook->link)
                <a href="{{ $ebook->link }}" target="_blank" rel="noopener noreferrer" class="ebooks-btn ebooks-btn--link">Open link</a>
            @endif
        </div>
    </header>

    @include('ebooks.partials.subnav')
    @include('ebooks.partials.alerts')

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ebooks-card ebooks-form">
                <form action="{{ route('ebooks.update', $ebook->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <section class="ebooks-form-section">
                        <h2 class="ebooks-form-section__title">Bibliographic details</h2>
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" name="title"
                                   value="{{ old('title', $ebook->title) }}" class="form-control" required>
                        </div>
                        <div class="ebooks-form-grid">
                            <div>
                                <label for="author" class="form-label">Author</label>
                                <input type="text" id="author" name="author"
                                       value="{{ old('author', $ebook->author) }}" class="form-control" required>
                            </div>
                            <div>
                                <label for="publication_year" class="form-label">Publication year</label>
                                <input type="text" id="publication_year" name="publication_year"
                                       value="{{ old('publication_year', $ebook->publication_year) }}" class="form-control">
                            </div>
                        </div>
                        <div class="ebooks-form-grid mt-0">
                            <div>
                                <label for="publisher" class="form-label">Publisher</label>
                                <input type="text" id="publisher" name="publisher"
                                       value="{{ old('publisher', $ebook->publisher) }}" class="form-control">
                            </div>
                            <div>
                                <label for="source" class="form-label">Source</label>
                                <input type="text" id="source" name="source"
                                       value="{{ old('source', $ebook->source) }}" class="form-control">
                            </div>
                        </div>
                    </section>

                    <section class="ebooks-form-section">
                        <h2 class="ebooks-form-section__title">Access</h2>
                        <div class="mb-0">
                            <label for="link" class="form-label">Supporting documents link (URL)</label>
                            <input type="url" id="link" name="link"
                                   value="{{ old('link', $ebook->link) }}" class="form-control" placeholder="https://">
                            <p class="ebooks-form-hint">Used as the supporting documents link on Report 3.</p>
                        </div>
                    </section>

                    @include('ebooks.partials.report_fields', ['ebook' => $ebook])

                    <section class="ebooks-form-section">
                        <h2 class="ebooks-form-section__title">Program assignment</h2>
                        <div class="ebooks-form-grid">
                            <div>
                                <label for="program" class="form-label">Program</label>
                                <select id="program" name="program_id" class="form-select">
                                    <option value="">All programs</option>
                                    @foreach($programs as $program)
                                        <option value="{{ $program->id }}"
                                            {{ (string) old('program_id', $ebook->program_id) === (string) $program->id ? 'selected' : '' }}>
                                            {{ $program->program_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="course" class="form-label">Subject / course</label>
                                <select id="course" name="course_id" class="form-select">
                                    <option value="">— Loading —</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="submit" class="ebooks-btn ebooks-btn--success">Save changes</button>
                        <a href="{{ route('ebooks.index') }}" class="ebooks-btn ebooks-btn--outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ebooks-card">
                <h2 class="ebooks-card__title">Record info</h2>
                <dl class="mb-0 small">
                    <dt class="text-muted">ID</dt>
                    <dd class="mb-2">#{{ $ebook->id }}</dd>
                    <dt class="text-muted">Added</dt>
                    <dd class="mb-2">{{ $ebook->created_at?->format('M j, Y') ?? '—' }}</dd>
                    <dt class="text-muted">Last updated</dt>
                    <dd class="mb-0">{{ $ebook->updated_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

@include('ebooks.partials.program_course_script', [
    'selectedProgramId' => old('program_id', $ebook->program_id),
    'selectedCourseId' => old('course_id', $ebook->course_id),
])
@endsection
