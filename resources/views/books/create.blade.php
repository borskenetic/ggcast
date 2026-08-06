@extends('layouts.sec')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/books/create.css') }}">
    <link href="{{ asset('vendor/fontsource/martel-sans/latin-900.css') }}" rel="stylesheet">
@endsection

@section('content')

<div class="catalog-page">
    <header class="catalog-page__hero">
        <div>
            <h1 class="catalog-page__title">Add New Book</h1>
            <p class="catalog-page__subtitle">Work through each tab — bibliographic record, programs, then cover image.</p>
        </div>
        <a href="{{ route('book.index') }}" class="btn btn-back btn-sm">← Back to catalog</a>
    </header>

    @if ($errors->any())
        <div class="alert alert-danger catalog-alert" role="alert">
            <strong>Please fix the following:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="addBookForm" method="POST" action="{{ route('book.store') }}" enctype="multipart/form-data" class="catalog-form">
        @csrf

        <div class="catalog-multicopy-toggle card mb-3">
            <div class="card-body py-3">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="multiple_copies" value="1"
                           id="multiple_copies" {{ old('multiple_copies') ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="multiple_copies">
                        Multiple copies
                    </label>
                </div>
                <p class="text-muted small mb-0 mt-2">
                    Catalog several copies of the same title in one go. Shared bibliographic data; each copy gets its own
                    <strong>accession number</strong> and <strong>RFID</strong>.
                </p>
            </div>
        </div>

        <div class="catalog-tabs card">
            <ul class="nav nav-tabs catalog-tabs__nav" id="catalogMainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-bibliographic-btn"
                            data-bs-toggle="tab" data-bs-target="#tab-bibliographic"
                            type="button" role="tab" aria-controls="tab-bibliographic" aria-selected="true">
                        <span class="catalog-tabs__step">1</span>
                        Bibliographic record
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-programs-btn"
                            data-bs-toggle="tab" data-bs-target="#tab-programs"
                            type="button" role="tab" aria-controls="tab-programs" aria-selected="false">
                        <span class="catalog-tabs__step">2</span>
                        Programs &amp; course
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-cover-btn"
                            data-bs-toggle="tab" data-bs-target="#tab-cover"
                            type="button" role="tab" aria-controls="tab-cover" aria-selected="false">
                        <span class="catalog-tabs__step">3</span>
                        Cover image
                    </button>
                </li>
            </ul>

            <div class="tab-content catalog-tabs__content" id="catalogMainTabContent">
                {{-- Tab 1: MARC --}}
                <div class="tab-pane fade show active" id="tab-bibliographic" role="tabpanel"
                     aria-labelledby="tab-bibliographic-btn" tabindex="0">
                    <p class="catalog-tab-lead text-muted">
                        MARC cataloging fields — use the pills below to switch between field groups.
                    </p>
                    @php
                        $marcValues = [];
                        $excludeBookColumns = old('multiple_copies')
                            ? config('catalog.copy_unique_columns', [])
                            : [];
                    @endphp
                    @include('books.partials.marc_editor', [
                        'frameworkFields' => $frameworkFields,
                        'grouped' => true,
                        'tabbed' => true,
                        'excludeBookColumns' => $excludeBookColumns,
                    ])
                    @include('books.partials.catalog_copy_rows')
                </div>

                {{-- Tab 2: Programs --}}
                <div class="tab-pane fade" id="tab-programs" role="tabpanel"
                     aria-labelledby="tab-programs-btn" tabindex="0">
                    <p class="catalog-tab-lead text-muted">
                        Optional — link this title to prospectus programs and courses for discovery.
                    </p>
                    <div class="row g-3">
                        @include('books.partials.catalog_curriculum_field')
                        <div class="col-12">
                            <label class="form-label catalog-field-label">Program(s)</label>
                            <div id="program-container" class="program-stack">
                                <div class="program-row d-flex gap-2 align-items-start">
                                    <select name="program_ids[]" class="form-control program-select flex-grow-1">
                                        <option value="">— Select program —</option>
                                        @foreach($programs as $program)
                                            <option value="{{ $program->id }}">{{ $program->program_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button type="button" id="add-program-btn" class="btn btn-sm btn-outline-secondary mt-2">
                                + Add another program
                            </button>
                        </div>

                        <div class="col-md-6">
                            <label for="year" class="form-label catalog-field-label">
                                <span class="catalog-field-tag">996 ‡e</span>
                                <span class="catalog-field-name">Year level</span>
                            </label>
                            <input type="text" name="year" id="year" class="form-control" placeholder="e.g. 1st Year">
                        </div>

                        <div class="col-md-6">
                            <label for="book_course" class="form-label catalog-field-label">
                                <span class="catalog-field-tag">650 ‡a</span>
                                <span class="catalog-field-name">Course</span>
                            </label>
                            <select name="course" id="book_course" class="form-control" disabled>
                                <option value="">— Select program(s) first —</option>
                                @if(old('course'))
                                    <option value="{{ old('course') }}" selected>{{ old('course') }}</option>
                                @endif
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Tab 3: Cover --}}
                <div class="tab-pane fade" id="tab-cover" role="tabpanel"
                     aria-labelledby="tab-cover-btn" tabindex="0">
                    <p class="catalog-tab-lead text-muted">
                        MARC 856 — optional cover for OPAC and staff views.
                    </p>
                    <div class="catalog-cover-upload">
                        <label for="cover_image" class="form-label catalog-field-label">Cover image file</label>
                        <input type="file" name="cover_image" id="cover_image" class="form-control"
                               accept="image/jpeg,image/png,image/jpg,image/webp,image/gif">
                        <p class="small text-muted mt-2 mb-0">JPG, PNG, or WebP · max 5 MB</p>
                    </div>
                </div>
            </div>
        </div>

        <footer class="catalog-page__actions">
            <a href="{{ route('book.index') }}" class="btn btn-back">Cancel</a>
            <div class="catalog-page__actions-nav d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="catalogTabPrev" disabled>← Previous</button>
                <button type="button" class="btn btn-outline-secondary" id="catalogTabNext">Next →</button>
                <button type="submit" class="btn btn-save">{{ old('multiple_copies') ? 'Save all copies' : 'Save book' }}</button>
            </div>
        </footer>
    </form>
</div>

@endsection

@section('scripts')
<link rel="stylesheet" href="{{ asset('js/hybrid/status.css') }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="container-fluid px-3 pb-2" style="max-width:1100px;margin:0 auto;">
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between py-2">
        <span id="hybridStatus" class="hybrid-status-badge">Hybrid…</span>
        <div class="small text-muted" id="hybridCatalogHint">Offline saves queue locally and upload when online.</div>
        <details class="hybrid-pair-panel" style="margin:0;">
            <summary>Device token (optional)</summary>
            <input type="password" id="hybridTokenInput" placeholder="hyb_…" autocomplete="off">
            <button type="button" id="hybridTokenSave" class="btn btn-sm btn-outline-secondary">Save</button>
            <button type="button" id="hybridPushCatalog" class="btn btn-sm btn-outline-primary">Upload queue</button>
        </details>
    </div>
</div>
<script>
    window.HYBRID_API_BASE = @json(url('/api/hybrid'));
    window.HYBRID_CSRF = @json(csrf_token());
</script>
<script src="{{ asset('js/hybrid/hybrid.js') }}"></script>
<script src="{{ asset('js/hybrid/catalog-bridge.js') }}"></script>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
@include('books.partials.catalog_tabs_script', ['formId' => 'addBookForm'])
@include('books.partials.catalog_courses_script')
@include('books.partials.catalog_programs_script')
@include('books.partials.catalog_multicopy_script', ['formId' => 'addBookForm'])
@include('books.partials.catalog_marc_pickers_script')
@endsection
