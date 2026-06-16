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
                Generate CHED-style Report 1 and Report 2 (library collection list, per-course summary, and institution-wide holdings summary) from cataloged books.
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
                        Both reports include books linked to the selected program on the cataloging form.
                        Report 1 also requires a <strong>course</strong> on each copy.
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

                <div class="alert alert-light border small mb-4">
                    <strong>Download includes two Excel sheets:</strong>
                    <ul class="mb-0 mt-2">
                        <li><strong>Report 1</strong> — program-specific: unique titles per course with collection type, curriculum area, author, year, and copy count; per-course summary on the right</li>
                        <li><strong>Report 2</strong> — program holdings grouped by classification (General Reference, General Education, Filipiniana, Professional) with title and volume counts by print/electronic format</li>
                        <li>Footer on both sheets: signatory block (configure names in <code>REPORT_PREPARED_BY_NAME</code> / <code>REPORT_APPROVED_BY_NAME</code>)</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-save">Download Excel (Reports 1 &amp; 2)</button>
            </form>
        </div>
    </div>
</div>
@endsection
