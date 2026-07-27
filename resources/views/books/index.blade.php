@extends('layouts.main')

@section('content')

<div class="head">
    <img src="{{ $brand['banner_url'] ?? asset('images/Bannernew.jpg') }}" alt="Banner" class="banner-img">
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@php
    $hasActiveQuery = $hasActiveQuery ?? false;
    $showAll = request()->boolean('show_all');
    $statusFilter = request('status');
@endphp

<div class="books-index-layout">

    {{-- Left sidebar: search, filters, actions --}}
    <aside class="books-index-sidebar card p-3">

        <h6 class="books-sidebar-heading">Find books</h6>

        <a href="{{ route('book.index', ['show_all' => 1]) }}"
           class="btn btn-primary w-100 mb-3 {{ $showAll && !request('search') && !request('program') && !request('year1') && !$statusFilter ? 'active' : '' }}">
            Show all books
        </a>

        <form action="{{ route('book.index') }}" method="GET" class="books-sidebar-form">
            @if($statusFilter)
                <input type="hidden" name="status" value="{{ $statusFilter }}">
            @endif

            <label class="form-label small text-muted mb-1">Search</label>
            <input type="text" name="search" class="form-control mb-2"
                   placeholder="Title, author, accession…"
                   value="{{ request('search') }}">

            <label class="form-label small text-muted mb-1 mt-2">Program</label>
            <select name="program" class="form-select mb-2">
                <option value="">All programs</option>
                @foreach($programs as $program)
                    <option value="{{ $program->id }}" {{ request('program') == $program->id ? 'selected' : '' }}>
                        {{ $program->program_name }}
                    </option>
                @endforeach
            </select>

            <label class="form-label small text-muted mb-1">Publication year</label>
            <select name="year_filter" class="form-select mb-2">
                <option value="">Year filter</option>
                <option value="exact" {{ request('year_filter') == 'exact' ? 'selected' : '' }}>Exact</option>
                <option value="before" {{ request('year_filter') == 'before' ? 'selected' : '' }}>Before</option>
                <option value="after" {{ request('year_filter') == 'after' ? 'selected' : '' }}>After</option>
                <option value="between" {{ request('year_filter') == 'between' ? 'selected' : '' }}>Between</option>
            </select>

            <input type="number" name="year1" class="form-control mb-2" placeholder="Year"
                   value="{{ request('year1') }}">

            <div id="year2Field" class="mb-2" style="{{ request('year_filter') == 'between' ? '' : 'display:none;' }}">
                <input type="number" name="year2" class="form-control" placeholder="Year (end)"
                       value="{{ request('year2') }}">
            </div>

            <button type="submit" class="btn btn-search w-100 mb-2">Search / Apply filters</button>

            @if($hasActiveQuery)
                <a href="{{ route('book.index') }}" class="btn btn-outline-secondary w-100 btn-sm">Clear &amp; start over</a>
            @endif
        </form>

        <hr class="my-3">

        <h6 class="books-sidebar-heading">Availability</h6>
        <nav class="books-sidebar-nav">
            <a href="{{ route('book.index', array_merge(request()->except('status', 'page'), ['status' => 'Available'])) }}"
               class="btn btn-available w-100 {{ $statusFilter === 'Available' ? 'active' : '' }}">Available</a>
            <a href="{{ route('book.index', array_merge(request()->except('status', 'page'), ['status' => 'Borrowed'])) }}"
               class="btn btn-borrowed w-100 {{ $statusFilter === 'Borrowed' ? 'active' : '' }}">Borrowed</a>
        </nav>

        <hr class="my-3">

        <h6 class="books-sidebar-heading">Catalog &amp; collections</h6>
        <nav class="books-sidebar-nav">
            <a href="{{ route('book.create') }}" class="btn btn-addbook w-100">Cataloging</a>
            <a href="{{ route('ebooks.index') }}" class="btn btn-e-book w-100">View E-Resources</a>
            <a href="{{ route('books.archived') }}" class="btn btn-secondary w-100">Archived</a>
            <a href="{{ route('books.trash') }}" class="btn btn-outline-danger w-100">Trash</a>
        </nav>

        <hr class="my-3">

        <h6 class="books-sidebar-heading">Import / export</h6>
        <form action="{{ route('books.import') }}" method="POST" enctype="multipart/form-data" class="books-sidebar-form">
            @csrf
            <input type="file" name="file" class="form-control form-control-sm mb-2" required accept=".csv,.xlsx">
            <button type="submit" class="btn btn-import w-100 mb-2">Import books</button>
            @if($hasActiveQuery)
                <a href="{{ route('export.books', request()->query()) }}" class="btn btn-export w-100">Export results</a>
            @else
                <span class="btn btn-export w-100 disabled" title="Search or filter first to export">Export books</span>
            @endif
        </form>

    </aside>

    {{-- Main: table only after search/filter --}}
    <main class="books-index-main">

        @if($hasActiveQuery)

            <div class="books-results-summary mb-3">
                <span class="text-muted">
                    Showing {{ $books->total() }} {{ $books->total() === 1 ? 'title' : 'titles' }}
                    @if($showAll && !request('search') && !request('program') && !request('year1') && !$statusFilter)
                        (entire catalog)
                    @endif
                    @if(request('search'))
                        matching “{{ request('search') }}”
                    @endif
                    @if($statusFilter)
                        · {{ $statusFilter }} only
                    @endif
                </span>
            </div>

            <div class="card p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle table-book-list">
                        <thead class="table-dark">
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Year Published</th>
                                <th>Resource Type</th>
                                <th>Copies</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($books as $book)
                                <tr>
                                    <td>{{ $book->title_statement }}</td>
                                    <td>{{ $book->main_author }}</td>
                                    <td>{{ $book->pub_year }}</td>
                                    <td>{{ $book->content_type }}</td>
                                    <td>{{ $book->copies }}</td>

                                    @if($book->copies == 1)
                                        @php $copy = \App\Models\Book::find($book->sample_id); @endphp
                                        <td class="{{ $copy->availability === 'Available' ? 'text-success' : 'text-danger' }}">
                                            {{ $copy->availability }}
                                        </td>
                                        <td class="text-end">
                                            <div class="dropdown1">
                                                <button type="button" class="dropdown1-button">Actions</button>
                                                <div class="dropdown1-content">
                                                    <a href="{{ route('book.show', $copy->id) }}" class="dropdown-item1">View</a>
                                                    <a href="{{ route('book.edit', $copy->id) }}" class="dropdown-item2">Edit</a>
                                                    <form action="{{ route('books.archive', $copy->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item2" style="background: none; border: none; width: 100%; text-align:left;">
                                                            Archive
                                                        </button>
                                                    </form>
                                                    <button class="dropdown-item3" type="button" data-bs-toggle="modal"
                                                        data-bs-target="#deleteModal{{ $copy->id }}">
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="modal fade" id="deleteModal{{ $copy->id }}" tabindex="-1"
                                                aria-labelledby="deleteModalLabel{{ $copy->id }}" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content rounded-3 shadow-lg">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title" id="deleteModalLabel{{ $copy->id }}">Confirm Delete</h5>
                                                            <button type="button" class="btn-close btn-close-white"
                                                                data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Are you sure you want to delete <strong>{{ $copy->title_statement }}</strong>?
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Cancel</button>
                                                            <form action="{{ route('books.destroy', $copy->id) }}" method="POST">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-danger">Yes, Delete</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    @else
                                        <td></td>
                                        <td class="text-end">
                                            <div class="dropdown1">
                                                <button type="button" class="dropdown1-button">Actions</button>
                                                <div class="dropdown1-content">
                                                    <a href="{{ route('books.copies.staff', [
                                                        'title' => $book->title_statement,
                                                        'author' => $book->main_author,
                                                        'year' => $book->pub_year
                                                    ]) }}" class="dropdown-item1">View Copies</a>
                                                </div>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No books match your search or filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-center mt-3">
                    {{ $books->withQueryString()->links('pagination::bootstrap-5') }}
                </div>
            </div>

        @else

            <div class="card books-index-welcome p-5 text-center">
                <div class="books-index-welcome-icon mb-3" aria-hidden="true">📚</div>
                <h5 class="mb-2">Search or filter to view the catalog</h5>
                <p class="text-muted mb-3">
                    Use the panel on the left to search by title or author, filter by program or publication year,
                    or choose <strong>Available</strong> / <strong>Borrowed</strong> to load results here.
                </p>
                <a href="{{ route('book.index', ['show_all' => 1]) }}" class="btn btn-primary btn-lg">
                    Show all books
                </a>
            </div>

        @endif

    </main>

</div>

<script>
    document.querySelector('[name="year_filter"]')?.addEventListener('change', function () {
        const el = document.getElementById('year2Field');
        if (el) {
            el.style.display = (this.value === 'between') ? '' : 'none';
        }
    });
</script>
@endsection

@section('footer')
    <footer>
        <div class="a51-footer">
            <h4 style="color: white; font-size:15px">Pantas © 2025. All Rights Reserved.</h4>
        </div>
    </footer>
@endsection
