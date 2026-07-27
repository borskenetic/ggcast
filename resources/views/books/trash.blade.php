@extends('layouts.main')

@section('content')
<div class="head">
    <img src="{{ $brand['banner_url'] ?? asset('images/Bannernew.jpg') }}" alt="Banner" class="banner-img">
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 custom-margin">
    <div>
        <a href="{{ route('book.index') }}" class="btn btn-all">← Back to Books</a>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('books.archived') }}" class="btn btn-secondary">Archived</a>
    </div>
</div>

<div class="card p-4">
    <h4 class="mb-3 text-danger">Trash</h4>

    <div class="alert alert-warning">
        Items in Trash are <strong>soft-deleted</strong>. You can restore them or permanently delete them.
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle table-book-list">
            <thead class="table-dark">
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Year Published</th>
                    <th>Deleted At</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($books as $book)
                    <tr>
                        <td>{{ $book->title_statement }}</td>
                        <td>{{ $book->main_author }}</td>
                        <td>{{ $book->pub_year }}</td>
                        <td>{{ optional($book->deleted_at)->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                <form action="{{ route('books.restore', $book->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">Restore</button>
                                </form>

                                <form action="{{ route('books.forceDelete', $book->id) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Permanently delete this book? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Delete Forever</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">Trash is empty.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center mt-3">
        {{ $books->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

