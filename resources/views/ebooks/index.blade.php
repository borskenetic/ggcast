@extends('layouts.sec')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/ebooks/ebooks.css') }}">
@endsection

@section('content')
@php
    $hasActiveFilter = request()->hasAny(['title', 'author', 'year', 'publisher', 'source', 'program_id', 'course_id']);
@endphp
<div class="ebooks-page">
    <header class="ebooks-page__hero">
        <div>
            <p class="ebooks-page__eyebrow">Digital library</p>
            <h1 class="ebooks-page__title">E-Resources collection</h1>
            <p class="ebooks-page__subtitle">Journals, e-books, and online materials linked to programs and subjects.</p>
        </div>
        <div class="ebooks-page__hero-actions">
            <a href="{{ route('book.index') }}" class="ebooks-btn ebooks-btn--outline">← Catalog</a>
            <a href="{{ route('ebooks.create') }}" class="ebooks-btn ebooks-btn--primary">+ Add e-resource</a>
        </div>
    </header>

    @include('ebooks.partials.subnav')
    @include('ebooks.partials.alerts')

    <div class="ebooks-stats">
        <div class="ebooks-stat">
            <div class="ebooks-stat__value">{{ $totalCount }}</div>
            <div class="ebooks-stat__label">Total in collection</div>
        </div>
        <div class="ebooks-stat">
            <div class="ebooks-stat__value">{{ $ebooks->total() }}</div>
            <div class="ebooks-stat__label">{{ $hasActiveFilter ? 'Matching filters' : 'On this page' }}</div>
        </div>
    </div>

    <div class="ebooks-index-layout">
        <aside class="ebooks-sidebar">
            <h2 class="ebooks-sidebar__heading">Filter collection</h2>
            <form method="GET" action="{{ route('ebooks.index') }}">
                <label class="form-label" for="filterTitle">Title</label>
                <select name="title" id="filterTitle" class="form-select">
                    <option value="">All titles</option>
                    @foreach ($allTitles as $title)
                        <option value="{{ $title }}" {{ request('title') == $title ? 'selected' : '' }}>{{ $title }}</option>
                    @endforeach
                </select>

                <label class="form-label" for="filterAuthor">Author</label>
                <select name="author" id="filterAuthor" class="form-select">
                    <option value="">All authors</option>
                    @foreach ($allAuthors as $author)
                        <option value="{{ $author }}" {{ request('author') == $author ? 'selected' : '' }}>{{ $author }}</option>
                    @endforeach
                </select>

                <label class="form-label" for="filterYear">Publication year</label>
                <select name="year" id="filterYear" class="form-select">
                    <option value="">All years</option>
                    @foreach ($allYears as $year)
                        <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                    @endforeach
                </select>

                <label class="form-label" for="filterPublisher">Publisher</label>
                <select name="publisher" id="filterPublisher" class="form-select">
                    <option value="">All publishers</option>
                    @foreach ($allPublishers as $publisher)
                        <option value="{{ $publisher }}" {{ request('publisher') == $publisher ? 'selected' : '' }}>{{ $publisher }}</option>
                    @endforeach
                </select>

                <label class="form-label" for="filterSource">Source</label>
                <select name="source" id="filterSource" class="form-select">
                    <option value="">All sources</option>
                    @foreach ($allSources as $source)
                        <option value="{{ $source }}" {{ request('source') == $source ? 'selected' : '' }}>{{ $source }}</option>
                    @endforeach
                </select>

                <label class="form-label" for="filterProgram">Program</label>
                <select name="program_id" id="filterProgram" class="form-select">
                    <option value="">All programs</option>
                    @foreach ($allPrograms as $program)
                        <option value="{{ $program->id }}" {{ request('program_id') == $program->id ? 'selected' : '' }}>
                            {{ $program->program_name }}
                        </option>
                    @endforeach
                </select>

                <label class="form-label" for="filterCourse">Subject / course</label>
                <select name="course_id" id="filterCourse" class="form-select">
                    <option value="">All subjects</option>
                    @foreach ($allCourses as $course)
                        <option value="{{ $course->id }}" {{ request('course_id') == $course->id ? 'selected' : '' }}>
                            {{ $course->course_name }}
                        </option>
                    @endforeach
                </select>

                <div class="ebooks-sidebar-actions">
                    <button type="submit" class="ebooks-btn ebooks-btn--primary w-100">Apply filters</button>
                    @if($hasActiveFilter)
                        <a href="{{ route('ebooks.index') }}" class="ebooks-btn ebooks-btn--outline w-100">Clear filters</a>
                    @endif
                </div>
            </form>
        </aside>

        <main class="ebooks-main">
            @if($hasActiveFilter)
                <p class="ebooks-results-meta">
                    Showing {{ $ebooks->total() }} {{ $ebooks->total() === 1 ? 'resource' : 'resources' }} matching your filters.
                </p>
            @endif

            @if($ebooks->count() > 0)
                <div class="ebooks-card ebooks-card--flush-table">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Title &amp; author</th>
                                    <th>Year</th>
                                    <th>Publisher</th>
                                    <th>Source</th>
                                    <th>Program</th>
                                    <th>Subject</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ebooks as $ebook)
                                    <tr>
                                        <td>
                                            <div class="ebooks-title-cell">
                                                <strong>{{ $ebook->title }}</strong>
                                                <small>{{ $ebook->author }}</small>
                                            </div>
                                        </td>
                                        <td>{{ $ebook->publication_year ?? '—' }}</td>
                                        <td>{{ $ebook->publisher ?? '—' }}</td>
                                        <td>
                                            @if($ebook->source)
                                                <span class="ebooks-tag">{{ $ebook->source }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $ebook->program->program_name ?? '—' }}</td>
                                        <td>{{ $ebook->course->course_name ?? '—' }}</td>
                                        <td class="text-end text-nowrap">
                                            <div class="dropdown1">
                                                <button type="button" class="dropdown1-button" aria-haspopup="true" aria-expanded="false">
                                                    Actions
                                                </button>
                                                <div class="dropdown1-content">
                                                    @if($ebook->link)
                                                        <a href="{{ $ebook->link }}" target="_blank" rel="noopener noreferrer" class="dropdown-item1">Open</a>
                                                    @endif
                                                    <a href="{{ route('ebooks.edit', $ebook->id) }}" class="dropdown-item2">Edit</a>
                                                    <form action="{{ route('ebooks.destroy', $ebook->id) }}" method="POST"
                                                          onsubmit="return confirm('Delete this e-resource?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item3">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-3 d-flex justify-content-center">
                    {{ $ebooks->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            @else
                <div class="ebooks-card ebooks-empty">
                    <div class="ebooks-empty__icon">📚</div>
                    @if($hasActiveFilter)
                        <p class="mb-2">No e-resources match these filters.</p>
                        <a href="{{ route('ebooks.index') }}" class="ebooks-btn ebooks-btn--outline">Clear filters</a>
                    @else
                        <p class="mb-2">No e-resources in the collection yet.</p>
                        <a href="{{ route('ebooks.create') }}" class="ebooks-btn ebooks-btn--primary">Add first e-resource</a>
                    @endif
                </div>
            @endif
        </main>
    </div>
</div>

@include('ebooks.partials.program_course_script', [
    'programSelectId' => 'filterProgram',
    'courseSelectId' => 'filterCourse',
    'selectedProgramId' => request('program_id'),
    'selectedCourseId' => request('course_id'),
])
@endsection
