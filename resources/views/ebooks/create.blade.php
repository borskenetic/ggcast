@extends('layouts.sec')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/ebooks/ebooks.css') }}">
@endsection

@section('content')
<div class="ebooks-page">
    <header class="ebooks-page__hero">
        <div>
            <p class="ebooks-page__eyebrow">Digital library</p>
            <h1 class="ebooks-page__title">Add e-resource</h1>
            <p class="ebooks-page__subtitle">Catalog a journal, e-book, or other online material with optional program link.</p>
        </div>
        <div class="ebooks-page__hero-actions">
            <a href="{{ route('ebooks.index') }}" class="ebooks-btn ebooks-btn--outline">← Collection</a>
        </div>
    </header>

    @include('ebooks.partials.subnav')
    @include('ebooks.partials.alerts')

    <div class="ebooks-card ebooks-form" style="max-width: 720px;">
        <form action="{{ route('ebooks.store') }}" method="POST">
            @csrf

            <section class="ebooks-form-section">
                <h2 class="ebooks-form-section__title">Bibliographic details</h2>
                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}" class="form-control" required>
                </div>
                <div class="ebooks-form-grid">
                    <div>
                        <label for="author" class="form-label">Author</label>
                        <input type="text" id="author" name="author" value="{{ old('author') }}" class="form-control" required>
                    </div>
                    <div>
                        <label for="publication_year" class="form-label">Publication year</label>
                        <input type="text" id="publication_year" name="publication_year"
                               value="{{ old('publication_year') }}" class="form-control">
                    </div>
                </div>
                <div class="ebooks-form-grid mt-0">
                    <div>
                        <label for="publisher" class="form-label">Publisher</label>
                        <input type="text" id="publisher" name="publisher" value="{{ old('publisher') }}" class="form-control">
                    </div>
                    <div>
                        <label for="source" class="form-label">Source</label>
                        <input type="text" id="source" name="source" value="{{ old('source') }}" class="form-control"
                               placeholder="e.g. ProQuest, Open Library">
                    </div>
                </div>
            </section>

            <section class="ebooks-form-section">
                <h2 class="ebooks-form-section__title">Access</h2>
                <div class="mb-0">
                    <label for="link" class="form-label">Supporting documents link (URL)</label>
                    <input type="url" id="link" name="link" value="{{ old('link') }}" class="form-control"
                           placeholder="https://">
                    <p class="ebooks-form-hint">Used as the supporting documents link on Report 3.</p>
                </div>
            </section>

            @include('ebooks.partials.report_fields')

            <section class="ebooks-form-section">
                <h2 class="ebooks-form-section__title">Program assignment</h2>
                <div class="ebooks-form-grid">
                    <div>
                        <label for="program" class="form-label">Program</label>
                        <select id="program" name="program_id" class="form-select">
                            <option value="">All programs</option>
                            @foreach($programs as $program)
                                <option value="{{ $program->id }}" {{ old('program_id') == $program->id ? 'selected' : '' }}>
                                    {{ $program->program_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="course" class="form-label">Subject / course</label>
                        <select id="course" name="course_id" class="form-select">
                            <option value="">— Select after program —</option>
                        </select>
                    </div>
                </div>
            </section>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" class="ebooks-btn ebooks-btn--success">Save e-resource</button>
                <a href="{{ route('ebooks.index') }}" class="ebooks-btn ebooks-btn--outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

@include('ebooks.partials.program_course_script', [
    'selectedProgramId' => old('program_id'),
    'selectedCourseId' => old('course_id'),
])
@endsection
