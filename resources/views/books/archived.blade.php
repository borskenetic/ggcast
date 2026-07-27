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
        <a href="{{ route('books.trash') }}" class="btn btn-outline-danger">Trash</a>
    </div>
</div>

<div class="card p-4">
    <h4 class="mb-3">Archived Books</h4>

    <div class="table-responsive">
        <table class="table table-hover align-middle table-book-list">
            <thead class="table-dark">
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Year Published</th>
                    <th>Archived At</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($books as $book)
                    <tr>
                        <td>{{ $book->title_statement }}</td>
                        <td>{{ $book->main_author }}</td>
                        <td>{{ $book->pub_year }}</td>
                        <td>{{ optional($book->archived_at)->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-2">
                                <a href="{{ route('book.show', $book->id) }}" class="btn btn-sm btn-primary">View</a>
                                <form action="{{ route('books.unarchive', $book->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-secondary">Unarchive</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">No archived books.</td>
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

