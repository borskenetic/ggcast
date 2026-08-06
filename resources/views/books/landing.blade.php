<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Library catalog — OPAC</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/books/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site-responsive.css') }}">
    <link rel="stylesheet" href="{{ asset('js/hybrid/status.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/qz-tray/qz-tray.js"></script>
</head>

<body class="opac-body">
    <div id="hybridOpacShell" class="hybrid-opac-shell">
        <button type="button" id="hybridBarToggle" class="hybrid-bar-toggle" aria-expanded="false" aria-controls="hybridBarBody">
            <span class="hybrid-bar-toggle__chevron" aria-hidden="true">▸</span>
            <span id="hybridStatus" class="hybrid-status-badge">Hybrid…</span>
            <span class="hybrid-bar-toggle__label">Hybrid / offline OPAC</span>
            <span class="hybrid-bar-toggle__hint">tap to expand</span>
        </button>
        <div id="hybridBarBody" class="hybrid-bar-body" hidden>
            <div class="hybrid-bar-body__inner">
                <p class="hybrid-bar-intro">Download the catalog while online so this kiosk can search without internet.</p>
                <div class="hybrid-pair-row">
                    <input type="password" id="hybridTokenInput" placeholder="Device token (optional)" autocomplete="off">
                    <button type="button" id="hybridTokenSave" class="btn btn-sm btn-outline-secondary">Save token</button>
                    <button type="button" id="hybridSyncNow" class="btn btn-sm btn-success">Download catalog cache</button>
                </div>
                <p class="small text-muted mb-0" id="hybridCacheHint">
                    No catalog cache yet — expand this panel and click <strong>Download catalog cache</strong> while online.
                </p>
            </div>
        </div>
    </div>
    <div id="hybridOfflineBanner" class="hybrid-offline-banner" style="display:none;">
        Offline OPAC — searching the local catalog cache. Holdings may be stale. Checkout needs connectivity.
    </div>
    <div id="hybridOfflineResults" class="container py-3" style="display:none;"></div>

    @if($searchActive)
        <header class="opac-search-header" role="banner">
            <div class="opac-search-header-inner">
                <a href="{{ route('landing') }}" class="opac-search-brand text-decoration-none">
                    <img class="opac-search-logo" src="{{ asset('images/d.png') }}" alt="Library logo">
                    <div class="opac-search-brand-text">
                        <span class="opac-search-kicker">Online Public Access Catalog</span>
                        <span class="opac-search-title">Governor Generoso College of Arts, Sciences and Technology</span>
                    </div>
                </a>

                <form method="GET" action="{{ route('landing') }}" class="opac-search-header-form" aria-label="Search">
                    <input type="hidden" name="view" value="{{ $viewMode ?? 'books' }}">
                    <input type="hidden" name="course" value="{{ request('course', 'all') }}">
                    <input type="hidden" name="content_type" value="{{ request('content_type', 'All') }}">
                    <input type="hidden" name="section" value="{{ request('section', 'All') }}">
                    <input type="hidden" name="subject_topic" value="{{ request('subject_topic', 'All') }}">
                    <div class="opac-search-header-row">
                        <input id="searchBar" type="search" name="search" value="{{ request('search') }}"
                            class="form-control opac-search-input"
                            placeholder="{{ ($viewMode ?? 'books') === 'ebooks' ? 'Search e-books by title, author, or keywords…' : 'Search books by title, author, or keywords…' }}"
                            autocomplete="off"
                            aria-label="Search catalog">
                        <button type="submit" class="btn btn-success opac-search-btn">Search</button>
                    </div>
                    <div class="opac-search-header-meta">
                        @if(request('search'))
                            <span class="opac-search-query-label">Showing results for <strong>{{ request('search') }}</strong></span>
                        @endif
                        <a href="{{ route('landing', ['view' => ($viewMode ?? 'books')]) }}" class="opac-search-clear-link">Clear search</a>
                    </div>
                </form>
            </div>
        </header>
    @else
        <header class="opac-public-header opac-header-bar">
            <div class="logo opac-logo-wrap">
                <a href="{{ route('landing') }}" class="text-decoration-none text-dark d-inline-flex align-items-center">
                    <img src="{{ asset('images/pantasLogo.png') }}" alt="Library Logo">
                </a>
            </div>
            <nav class="opac-top-nav" aria-label="Quick links">
                <a href="{{ route('home') }}" class="opac-nav-link">Home</a>
                <a href="{{ route('kiosk.scan') }}" class="opac-nav-link">Student lookup</a>
                <a href="{{ route('landing') }}" class="opac-nav-link fw-semibold">Catalog</a>
            </nav>
            <form action="{{ route('logout') }}" method="POST" class="mb-0" hidden>
                @csrf
                <button type="submit" class="logout-btn" onclick="logout()" style="margin-right: 60px;">Logout</button>
            </form>
        </header>
    @endif

    <div class="opac-page-fill flex-grow-1">
    @unless($searchActive)
        <section class="opac-hero-search" aria-labelledby="opac-search-heading">
            <div class="opac-hero-search-inner">
                <p class="opac-hero-kicker">Online Public Access Catalog</p>
                <h1 id="opac-search-heading" class="opac-hero-title">Find books in our library</h1>
                <p class="opac-hero-subtitle">Search by title, author, or keywords to browse the full collection.</p>

                <form method="GET" action="{{ route('landing') }}" class="opac-search-form opac-hero-search-form">
                    <div class="opac-hero-search-row">
                        <input id="searchBar" type="search" name="search" value="{{ request('search') }}"
                            class="form-control opac-hero-search-input"
                            placeholder="Title, author, or keywords…"
                            autocomplete="off"
                            aria-label="Search catalog">
                        <button type="submit" class="btn btn-success opac-hero-search-btn">Search</button>
                    </div>
                </form>

                <p class="opac-search-hint">New arrivals are shown below. Catalog filters appear after you search.</p>
            </div>
        </section>

        <section class="opac-new-arrivals-block" aria-labelledby="nab">
            <div class="opac-new-arrivals-inner">
                <div class="opac-section-head">
                    <h2 id="nab" class="opac-new-arrivals-title">New arrival books</h2>
                    <p class="opac-section-subtitle">Recently added titles — click a cover for details</p>
                </div>

                <div class="carousel">
                    <div class="carousel-container">
                        <div class="arrow left" onclick="slide(-1)" role="button" tabindex="0" aria-label="Previous"
                            onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();slide(-1);}">
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M12.5 3L5 10l7.5 7" stroke="#5b5e64" stroke-width="2.5" fill="none" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        </div>

                        <div class="carousel-track" id="carouselTrack">
                            @foreach ($carouselBooks as $book)
                            @php
                                $cMeta = $carouselMeta[$book->id] ?? ['copies' => 1, 'is_available' => $book->availability === 'Available'];
                                $cAvail = ($cMeta['is_available'] ?? false) ? 'Available' : 'Not Available';
                            @endphp
                            <div class="carosel"
                                data-img="{{ $book->cover_image ? asset('storage/' . $book->cover_image) : asset('images/defaultBook.png') }}"
                                data-title="{{ $book->title_statement }}"
                                data-author="{{ $book->main_author }}"
                                data-note="{{ $book->general_note }}"
                                data-call="{{ $book->call_number }}"
                                data-id="{{ $book->id }}"
                                data-year="{{ $book->pub_year }}"
                                data-availability="{{ $cAvail }}"
                                data-copies="{{ $cMeta['copies'] }}"
                                data-content="{{ $book->content_type }}"
                                data-fixed="{{ $book->fixed_length_data }}"
                                data-library="{{ $book->library_name }}"
                                data-course="{{ $book->course ?? '' }}"
                                onclick="openBookCard(this)">

                                <div class="carosel-cover">
                                    <img src="{{ $book->cover_image ? asset('storage/' . $book->cover_image) : asset('images/defaultBook.png') }}"
                                        alt="{{ $book->title_statement }}">
                                </div>
                                <p class="carosel-title">{{ $book->title_statement }}</p>
                            </div>
                            @endforeach
                        </div>

                        <div class="arrow right" onclick="slide(1)" role="button" tabindex="0" aria-label="Next"
                            onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();slide(1);}">
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M7.5 3L15 10l-7.5 7" stroke="#5b5e64" stroke-width="2.5" fill="none" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endunless

    @if($searchActive)
    <div class="layout opac-results-shell">
        <aside class="opac-facets" aria-label="Filters">
            <div class="opac-filters-panel">
                <section class="opac-filter-group">
                    <h2 class="opac-filter-heading">Library catalog</h2>
                    <div class="opac-facet-item is-active">
                        {{ ($viewMode ?? 'books') === 'ebooks' ? 'E-Books' : 'Books' }}
                    </div>
                    <a class="opac-facet-link" href="{{ route('landing', array_merge(request()->except('page'), ['view' => (($viewMode ?? 'books') === 'ebooks' ? 'books' : 'ebooks')])) }}">
                        View {{ ($viewMode ?? 'books') === 'ebooks' ? 'Books' : 'E-Books' }}
                    </a>
                </section>

                <section class="opac-filter-group">
                    <h2 class="opac-filter-heading">Results</h2>
                    <p class="opac-filter-result-count">
                        {{ ($viewMode ?? 'books') === 'ebooks' ? ($ebooks?->total() ?? 0) : $books->total() }}
                        {{ (($viewMode ?? 'books') === 'ebooks' ? ($ebooks?->total() ?? 0) : $books->total()) === 1 ? 'title' : 'titles' }}
                    </p>
                </section>

                @if(($viewMode ?? 'books') !== 'ebooks')
                <section class="opac-filter-group">
                    <h2 class="opac-filter-heading">Format</h2>
                    <form method="GET" action="{{ route('landing') }}" class="opac-facet-form">
                        <input type="hidden" name="view" value="books">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="course" value="{{ request('course', 'all') }}">
                        <input type="hidden" name="section" value="{{ request('section', 'All') }}">
                        <input type="hidden" name="subject_topic" value="{{ request('subject_topic', 'All') }}">
                        <select name="content_type" class="form-select opac-filter-select" onchange="this.form.submit()" aria-label="Format">
                            <option value="All" {{ request('content_type', 'All') === 'All' ? 'selected' : '' }}>All resources</option>
                            @foreach ($content_type as $ct)
                                <option value="{{ $ct }}" {{ request('content_type') == $ct ? 'selected' : '' }}>{{ $ct }}</option>
                            @endforeach
                        </select>
                    </form>
                </section>

                <section class="opac-filter-group">
                    <h2 class="opac-filter-heading">Section</h2>
                    <form method="GET" action="{{ route('landing') }}" class="opac-facet-form">
                        <input type="hidden" name="view" value="books">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="course" value="{{ request('course', 'all') }}">
                        <input type="hidden" name="content_type" value="{{ request('content_type', 'All') }}">
                        <input type="hidden" name="subject_topic" value="{{ request('subject_topic', 'All') }}">
                        <select name="section" class="form-select opac-filter-select" onchange="this.form.submit()" aria-label="Section">
                            <option value="All" {{ request('section', 'All') === 'All' ? 'selected' : '' }}>All sections</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section }}" {{ request('section') == $section ? 'selected' : '' }}>{{ $section }}</option>
                            @endforeach
                        </select>
                    </form>
                </section>

                <section class="opac-filter-group">
                    <h2 class="opac-filter-heading">Subject</h2>
                    <form method="GET" action="{{ route('landing') }}" class="opac-facet-form">
                        <input type="hidden" name="view" value="books">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="course" value="{{ request('course', 'all') }}">
                        <input type="hidden" name="content_type" value="{{ request('content_type', 'All') }}">
                        <input type="hidden" name="section" value="{{ request('section', 'All') }}">
                        <select name="subject_topic" class="form-select opac-filter-select" onchange="this.form.submit()" aria-label="Subject">
                            <option value="All" {{ request('subject_topic', 'All') === 'All' ? 'selected' : '' }}>All subject topics</option>
                            @foreach ($subjectTopics as $topic)
                                <option value="{{ $topic }}" {{ request('subject_topic') == $topic ? 'selected' : '' }}>
                                    {{ \Illuminate\Support\Str::limit($topic, 25, '...') }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </section>
                @endif
            </div>
        </aside>

        <main class="opac-results-panel">
            <div class="opac-results-head">
                <div class="opac-results-head-left">
                    <h2 class="opac-results-title">
                        {{ ($viewMode ?? 'books') === 'ebooks' ? 'E-books' : 'Search results' }}
                    </h2>
                    @if(request('search'))
                        <p class="opac-results-subtitle">Matches for &ldquo;{{ request('search') }}&rdquo;</p>
                    @endif
                </div>
            </div>

            @if((($viewMode ?? 'books') === 'ebooks' ? ($ebooks?->total() ?? 0) : $books->total()) === 0)
                <div class="opac-results-empty">
                    <p class="opac-results-empty-title">No titles matched your search</p>
                    <p class="opac-results-empty-text">Try different keywords, check your spelling, or clear filters.</p>
                    <a href="{{ route('landing') }}" class="btn btn-outline-primary btn-sm mt-2">Back to catalog home</a>
                </div>
            @endif

            <div class="opac-results-list" id="bookGrid">
                @if(($viewMode ?? 'books') === 'ebooks')
                    @foreach (($ebooks ?? []) as $eb)
                        <a class="opac-result-row opac-result-row--ebook"
                           href="{{ $eb->link ?: 'javascript:void(0)' }}"
                           target="_blank"
                           rel="noopener"
                           onclick="{{ $eb->link ? '' : 'return false;' }}">
                            <div class="opac-result-cover">
                                <img src="{{ asset('images/defaultBook.png') }}" alt="">
                            </div>
                            <div class="opac-result-meta">
                                <div class="opac-result-title">
                                    <span class="opac-result-title-link">{{ $eb->title }}</span>
                                    @if($eb->publication_year)
                                        <span class="text-muted">({{ $eb->publication_year }})</span>
                                    @endif
                                </div>
                                <div class="opac-result-sub small text-muted">
                                    @if($eb->author)
                                        By {{ $eb->author }}
                                    @endif
                                </div>
                                @if($eb->source)
                                    <div class="small text-muted">{{ $eb->source }}</div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                @else
                    @foreach ($books as $book)
                    <div class="opac-result-row"
                        data-img="{{ $book->cover_image ? asset('storage/' . $book->cover_image) : asset('images/defaultBook.png') }}"
                        data-title="{{ $book->title_statement }}"
                        data-author="{{ $book->main_author }}"
                        data-note="{{ $book->general_note }}"
                        data-call="{{ $book->call_number }}"
                        data-id="{{ $book->id }}"
                        data-year="{{ $book->pub_year }}"
                        data-copies="{{ $book->copies }}"
                        data-availability="{{ $book->is_available == 1 ? 'Available' : 'Not Available' }}"
                        data-content="{{ $book->content_type }}"
                        data-fixed="{{ $book->fixed_length_data }}"
                        data-library="{{ $book->library_name }}"
                        data-course="{{ $book->course ?? '' }}"
                        onclick="openBookCard(this)">
                        <div class="opac-result-cover">
                            <img src="{{ $book->cover_image ? asset('storage/' . $book->cover_image) : asset('images/defaultBook.png') }}" alt="">
                        </div>
                        <div class="opac-result-meta">
                            <div class="opac-result-title">
                                <a href="javascript:void(0)" class="opac-result-title-link">
                                    {{ $book->title_statement }}
                                </a>
                                @if($book->pub_year)
                                    <span class="text-muted">({{ $book->pub_year }})</span>
                                @endif
                            </div>
                            <div class="opac-result-sub small text-muted">
                                @if($book->main_author)
                                    By {{ $book->main_author }}
                                @endif
                            </div>
                            <div class="opac-result-availability small {{ $book->is_available == 1 ? 'text-success' : 'text-danger' }}">
                                {{ $book->is_available == 1 ? 'Available' : 'Not Available' }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>

            @if(($viewMode ?? 'books') === 'ebooks')
                @if(($ebooks?->total() ?? 0) > 0)
                <div class="d-flex justify-content-center mt-4">
                    {{ $ebooks->links('pagination::bootstrap-5') }}
                </div>
                @endif
            @else
                @if($books->total() > 0)
            <div class="d-flex justify-content-center mt-4">
                {{ $books->links('pagination::bootstrap-5') }}
            </div>
                @endif
            @endif
        </main>
    </div>
    @endif

    <div class="modal" id="bookModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-content modal-wide opac-record-modal">
            <span class="close" onclick="closeModal()" aria-label="Close">&times;</span>

            <div id="opacDetailLoading" class="opac-detail-loading py-5 text-center text-muted">Loading record…</div>

            <div id="opacDetailContent" class="opac-detail-content" style="display: none;">
                <p id="opacBreadcrumb" class="opac-breadcrumb small mb-2" aria-label="Context"></p>

                <div class="opac-detail-body modal-body-flex">
                    <div class="modal-left opac-detail-cover-col">
                        <img id="modalImg" src="" alt="Book cover">
                    </div>
                    <div class="modal-right opac-detail-main">
                        <h2 id="modalTitle" class="h4 mb-1"></h2>
                        <p id="modalAuthor" class="text-muted mb-3"></p>
                        <table class="table table-sm table-borderless opac-bib-table mb-0">
                            <tbody id="opacBibSummary"></tbody>
                        </table>
                    </div>
                </div>

                <div class="opac-tabs" role="tablist">
                    <button type="button" class="opac-tab is-active" data-tab="holdings" role="tab" aria-selected="true">Holdings</button>
                    <button type="button" class="opac-tab" data-tab="description" role="tab" aria-selected="false">Description</button>
                    <button type="button" class="opac-tab" data-tab="marc" role="tab" aria-selected="false">MARC View</button>
                </div>

                <div class="opac-tab-panels border-top">
                    <div id="opacTabHoldings" class="opac-tab-panel is-active pt-3" role="tabpanel">
                        <p class="opac-library-location small mb-2" id="opacHoldingsLibraryLine"></p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm opac-holdings-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Accession #</th>
                                        <th>Call #</th>
                                        <th>Volume / Part #</th>
                                        <th>Copy #</th>
                                        <th>Collection</th>
                                        <th>Shelving location</th>
                                        <th>Circulation type</th>
                                        <th>Circulation status</th>
                                        <th>Barcode</th>
                                        <th>RFID</th>
                                        <th>Add to cart</th>
                                    </tr>
                                </thead>
                                <tbody id="holdingsTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div id="opacTabDescription" class="opac-tab-panel pt-3" role="tabpanel">
                        <dl class="opac-desc-dl mb-0" id="descriptionDl"></dl>
                    </div>
                    <div id="opacTabMarc" class="opac-tab-panel pt-3" role="tabpanel">
                        <p class="small text-muted mb-2">Same layout as staff book view; only tags with a value that matches on every copy of this title are shown. Use <strong>Holdings</strong> when values differ by copy.</p>
                        <div class="table-responsive opac-marc-view-wrap">
                            <table class="table table-sm table-borderless opac-marc-view-table mb-0">
                                <tbody id="marcViewTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="studentModal">
        <div class="modal-content">
            <span class="close" onclick="closeStudentModal()">&times;</span>

            <h4>Self Check-Out</h4>

            <div class="mb-3">
                <label for="studentIdInput" class="form-label"><strong>Student ID</strong></label>
                <input type="text" id="studentIdInput" class="form-control" placeholder="Enter your Student ID">
            </div>

            <button type="button" class="btn btn-primary mt-3" onclick="confirmCheckout()">
                Confirm Checkout
            </button>

            <p id="studentError" class="text-danger mt-2" style="display:none;"></p>
        </div>
    </div>

    <button id="cartButton" type="button" onclick="openCartModal()" style="position:fixed; bottom:30px; right:30px; z-index:999;
                       padding:12px 20px; border-radius:50px;" class="btn btn-dark">
        Cart (<span id="cartCount">0</span>)
    </button>

    <div class="modal" id="cartModal">
        <div class="modal-content cart-modal-clean">
            <span class="close" onclick="closeCartModal()">&times;</span>

            <div class="cart-header">
                <h2>Borrow Cart</h2>
                <p>Maximum of 5 books allowed</p>
            </div>

            <div id="cartBody" class="cart-body">
                <ul id="cartList" class="cart-list"></ul>

                <div id="emptyCart" class="empty-cart" style="display:none;">
                    Your cart is empty.
                </div>
            </div>

            <div class="cart-footer">
                <div class="cart-count">
                    Total Books: <strong id="cartTotal">0</strong>
                </div>

                <button type="button" class="btn btn-dark px-5" onclick="openStudentModalFromCart()">
                    Proceed to Checkout
                </button>
            </div>
        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>
    </div>

    @include('partials.loan_terms_modal')

    <script>
        window.CHECKOUT_URL = "{{ route('checkout.process') }}";
        window.CSRF_TOKEN = "{{ csrf_token() }}";
        window.OPAC_BOOK_DETAIL_BASE = @json(url('/opac/api/book').'/');
        window.HYBRID_API_BASE = @json(url('/api/hybrid'));
        window.HYBRID_CSRF = @json(csrf_token());
        window.LOAN_DEFAULT_DAYS = @json((int) (optional(\App\Models\FineSetting::latest('created_at')->first())->loan_duration_days ?? 7));

        function logout() {
            document.querySelector('header form[action*="logout"]')?.submit();
        }
    </script>
    <script src="{{ asset('js/hybrid/hybrid.js') }}"></script>
    <script src="{{ asset('js/loan-terms.js') }}"></script>
    <script src="{{ asset('js/cart.js') }}"></script>
    <script src="{{ asset('js/landings.js') }}"></script>
    <script src="{{ asset('js/hybrid/opac-bridge.js') }}"></script>
</body>

</html>
