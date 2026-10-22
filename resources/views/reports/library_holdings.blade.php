@extends('layouts.sec')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/books/create.css') }}">
@endsection

@section('content')
<div class="catalog-page">
    <header class="catalog-page__hero">
        <div>
            <h1 class="catalog-page__title">Library Holdings Report</h1>
            <p class="catalog-page__subtitle">
                Generate CHED-style Reports 1–3: book holdings, classification summary, and professional journal subscriptions from E-Resources.
            </p>
        </div>
        <a href="{{ route('book.index') }}" class="btn btn-back btn-sm">← Back to catalog</a>
    </header>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('reports.library_holdings.download') }}">
                @csrf

                <div class="mb-3">
                    <label for="program_id" class="form-label fw-semibold">Program</label>
                    <select name="program_id" id="program_id" class="form-control" required>
                        <option value="">— Select program —</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" @selected(old('program_id') == $program->id)>
                                {{ $program->program_name }} ({{ $program->program_code }})
                            </option>
                        @endforeach
                    </select>
                    <p class="small text-muted mt-1">
                        Reports 1 and 2 use books linked to the selected program on the cataloging form.
                        Report 3 uses E-Resources linked to this program (or marked for all programs).
                        Report 1 also requires a <strong>course</strong> on each book copy.
                        For a major (e.g. English), you can select the base program (BSED) and use the suffix field below
                        instead of a separate program entry.
                    </p>
                </div>

                <div class="mb-3">
                    <label for="program_suffix" class="form-label fw-semibold">Program name suffix (optional)</label>
                    <input type="text" name="program_suffix" id="program_suffix" class="form-control"
                           value="{{ old('program_suffix') }}"
                           placeholder="e.g. MAJOR IN ENGLISH">
                    <p class="small text-muted mt-1">
                        Appended to the program name on the report header, e.g.
                        <em>Bachelor of Secondary Education (MAJOR IN ENGLISH)</em>.
                    </p>
                </div>

                <div class="mb-3">
                    <label for="date_accomplished" class="form-label fw-semibold">Date accomplished (optional)</label>
                    <input type="text" name="date_accomplished" id="date_accomplished" class="form-control"
                           value="{{ old('date_accomplished') }}"
                           placeholder="Leave blank for a signature line">
                </div>

                @php
                    $recentYears = max(1, (int) config('reports.recent_publication_years', 5));
                    $recentFromYear = (int) now()->year - $recentYears + 1;
                @endphp
                <div class="alert alert-light border small mb-4">
                    <strong>Download includes three Excel sheets:</strong>
                    <ul class="mb-0 mt-2">
                        <li><strong>Report 1</strong> — program-specific titles published in the last {{ $recentYears }} years ({{ $recentFromYear }}–{{ now()->year }}), with collection type, curriculum area, author, year, and copy count. Older titles are left off the list. The summary still counts every title for the course, and separately counts those within {{ $recentYears }} years.</li>
                        <li><strong>Report 2</strong> — program holdings grouped by classification (General Reference, General Education, Filipiniana, Professional) with title and volume counts by print/electronic format</li>
                        <li><strong>Report 3</strong> — professional journal subscriptions from <a href="{{ route('ebooks.index') }}">View E-Resources</a> (type, journal name, publisher, publication scope, frequency, subscription, supporting link)</li>
                        <li>Footer on all sheets: signatory block (configure names in <code>REPORT_PREPARED_BY_NAME</code> / <code>REPORT_APPROVED_BY_NAME</code>)</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-save">Download Excel (Reports 1–3)</button>
            </form>
        </div>
    </div>
</div>
@endsection
