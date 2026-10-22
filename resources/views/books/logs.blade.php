@extends('layouts.sec')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/books/logs.css') }}">
@endsection

@section('content')
@php
    $hasFilters = request()->filled('student_id')
        || request()->filled('filter_patron')
        || request()->filled('book_title')
        || request()->filled('accession_no')
        || request()->filled('start_date')
        || request()->filled('end_date')
        || request()->filled('circulation_type');
    $maxRenewals = \App\Models\Setting::maxRenewalsPerLoan();
@endphp

<div class="circulation-page">

    <header class="circulation-page__hero">
        <div>
            <p class="circulation-page__eyebrow">Circulation</p>
            <h1 class="circulation-page__title">Book transactions</h1>
            <p class="circulation-page__lead">Check books in or out, then search and review the transaction history.</p>
        </div>
        <a href="{{ route('book.index') }}" class="btn btn-outline-secondary btn-sm">← Catalog</a>
    </header>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @error('student_id')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror
    @error('employee_id')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <section class="circulation-card">
        <div class="circulation-card__head">
            <h2 class="circulation-card__title">Record transaction</h2>
            <p class="circulation-card__hint text-muted mb-0">
                Scan or type the copy <strong>accession number</strong> (recommended), barcode, or RFID — then select patron and circulation type.
                Reserve copies (sublocation contains “Reserve”) use the reserve due date and fine, and count toward both the reserve limit and the total loan limit.
            </p>
        </div>
        <form action="{{ route('logs.store') }}" method="POST" id="logTransactionForm" class="circulation-card__body">
            @csrf
            <input type="hidden" name="due_date" id="loan_due_date" value="">
            <input type="hidden" name="loan_duration_days" id="loan_duration_days" value="">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="copy_identifier_input" class="form-label">Copy ID</label>
                    <div class="circulation-autocomplete-wrap">
                        <input type="text" class="form-control circulation-input" name="copy_identifier" id="copy_identifier_input"
                               value="{{ $prefillCopyIdentifier ?? request('copy_identifier', request('rfid')) }}"
                               placeholder="Accession, barcode, or RFID" autocomplete="off" required>
                        <ul id="bookSuggestions" class="circulation-suggest list-group"></ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="patron_name" class="form-label">Patron</label>
                    <input type="hidden" name="student_id" id="student_id" value="{{ request('student_id') }}">
                    <input type="hidden" name="employee_id" id="employee_id" value="{{ request('employee_id') }}">
                    <div class="circulation-autocomplete-wrap">
                        <input type="text" id="patron_name" class="form-control circulation-input" autocomplete="off"
                               placeholder="Search name or ID…" value="{{ $prefillPatronLabel ?? '' }}">
                        <ul id="patronSuggestions" class="circulation-suggest list-group"></ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="status_select" class="form-label">Circulation</label>
                    <select name="status" id="status_select" class="form-select circulation-input" required>
                        <option value="checked_out" {{ request('status', 'checked_out') === 'checked_out' ? 'selected' : '' }}>Check out (outside library)</option>
                        <option value="room_use" {{ request('status') === 'room_use' ? 'selected' : '' }}>Room use (in library)</option>
                        <option value="checked_in" {{ request('status') === 'checked_in' ? 'selected' : '' }}>Check in</option>
                    </select>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary" id="record">Record transaction</button>
            </div>
        </form>
    </section>

    <section class="circulation-card">
        <div class="circulation-card__head">
            <h2 class="circulation-card__title">Transaction logs</h2>
            <p class="circulation-card__hint text-muted mb-0">Search by patron, book title, or accession number; narrow by date and loan type.</p>
        </div>
        <form method="GET" action="{{ route('logs.index') }}" id="logFilterForm" class="circulation-card__body">
            <div class="row g-3">
                <div class="col-md-6 col-lg-4">
                    <label for="filter_patron" class="form-label">Patron</label>
                    <input type="hidden" name="student_id" id="filter_student_id" value="{{ request('student_id') }}">
                    <input type="hidden" name="employee_id" id="filter_employee_id" value="{{ request('employee_id') }}">
                    <div class="circulation-autocomplete-wrap">
                        <input type="text" name="filter_patron" id="filter_patron" class="form-control circulation-input"
                               value="{{ $prefillPatronLabel ?? '' }}" autocomplete="off"
                               placeholder="Search patron name or ID…">
                        <ul id="filterPatronSuggestions" class="circulation-suggest list-group"></ul>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <label for="filter_book_title" class="form-label">Book title</label>
                    <div class="circulation-autocomplete-wrap">
                        <input type="text" name="book_title" id="filter_book_title" class="form-control circulation-input"
                               value="{{ $filterBookTitle ?? '' }}" autocomplete="off"
                               placeholder="Search book title…">
                        <ul id="filterBookTitleSuggestions" class="circulation-suggest list-group"></ul>
                    </div>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label for="start_date" class="form-label">From</label>
                    <input type="date" class="form-control circulation-input" name="start_date" id="start_date"
                           value="{{ request('start_date') }}">
                </div>
                <div class="col-md-6 col-lg-2">
                    <label for="end_date" class="form-label">To</label>
                    <input type="date" class="form-control circulation-input" name="end_date" id="end_date"
                           value="{{ request('end_date') }}">
                </div>
                <div class="col-md-6 col-lg-4">
                    <label for="filter_accession_no" class="form-label">Accession number</label>
                    <div class="circulation-autocomplete-wrap">
                        <input type="text" name="accession_no" id="filter_accession_no" class="form-control circulation-input"
                               value="{{ request('accession_no') }}" autocomplete="off"
                               placeholder="Search accession number…">
                        <ul id="filterAccessionSuggestions" class="circulation-suggest list-group"></ul>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <label for="circulation_type" class="form-label">Loan type</label>
                    <select class="form-select circulation-input" name="circulation_type" id="circulation_type">
                        <option value="">All types</option>
                        <option value="checkout" {{ request('circulation_type') === 'checkout' ? 'selected' : '' }}>Check out</option>
                        <option value="room_use" {{ request('circulation_type') === 'room_use' ? 'selected' : '' }}>Room use</option>
                    </select>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mt-3">
                <p class="text-muted small mb-0">
                    @if($hasFilters)
                        Filters active — {{ $logs->total() }} {{ $logs->total() === 1 ? 'match' : 'matches' }}
                    @else
                        Showing latest transactions
                    @endif
                </p>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="apply">Apply filters</button>
                    <a href="{{ route('logs.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="circulation-table-wrap">
            <table class="table table-hover circulation-table mb-0">
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Author</th>
                        <th>Copy ID</th>
                        <th>Patron</th>
                        <th>Status</th>
                        <th>Loan type</th>
                        <th>When</th>
                        <th>Due</th>
                        <th>Renewals</th>
                        <th>Returned</th>
                        <th>Overdue</th>
                        <th>Days late</th>
                        <th>Fine</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    @php
                        $isCheckIn = $log->status === 'Checked In';
                        $daysLate = 0;
                        $isOverdue = false;
                        $tooltip = '';
                        $effectiveLate = 0;

                        if ($isCheckIn && $log->due_date && $log->returned_date) {
                            $daysLate = max(0, $log->due_date->diffInDays($log->returned_date, false));
                            $daysLateWhole = floor($daysLate);
                            $settings = \App\Models\FineSetting::latest('created_at')->first();
                            $usesReserveRates = $log->usesReservePolicy();
                            $gracePeriod = $settings ? $settings->graceDaysFor($usesReserveRates) : 0;
                            $finePerDay = $settings ? $settings->finePerDayFor($usesReserveRates) : 0;
                            $effectiveLate = max(0, $daysLateWhole - $gracePeriod);
                            $isOverdue = $effectiveLate > 0;
                            if ($isOverdue) {
                                $rateName = $usesReserveRates ? 'reserve fine' : 'fine';
                                $tooltip = "{$effectiveLate} day(s) × ₱".number_format($finePerDay, 2)." ({$rateName}) = ₱".number_format($log->fine_incurred, 2);
                            }
                        }
                    @endphp
                    <tr>
                        <td class="circulation-table__title">{{ $log->book->title_statement ?? 'N/A' }}</td>
                        <td class="circulation-table__author">{{ filled($log->book?->main_author) ? $log->book->main_author : '—' }}</td>
                        <td class="small">
                            @if($log->book)
                                <code>{{ $log->book->copyIdentifierForCirculation() ?? '—' }}</code>
                                @if($log->book->copyIdentifierTypeLabel())
                                    <span class="text-muted d-block">{{ $log->book->copyIdentifierTypeLabel() }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $log->patronLabel() }}</td>
                        <td>
                            @if($log->status === 'Checked Out')
                                <span class="badge text-bg-warning">Out</span>
                            @else
                                <span class="badge text-bg-success">In</span>
                            @endif
                        </td>
                        <td>
                            {{ $log->circulationLabel() }}
                            @if($log->usesReservePolicy())
                                <span class="badge text-bg-warning">Reserve</span>
                            @endif
                        </td>
                        <td class="text-nowrap small">{{ $log->timestamp_manila ?? '—' }}</td>
                        <td class="text-nowrap small">{{ $log->due_date ?? '—' }}</td>
                        <td class="small">
                            @if(($log->circulation_type ?? \App\Models\BookLog::CIRCULATION_CHECKOUT) === \App\Models\BookLog::CIRCULATION_CHECKOUT)
                                {{ (int) ($log->renew_count ?? 0) }}/{{ $maxRenewals }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-nowrap small">{{ $log->returned_date ?? '—' }}</td>
                        <td>
                            @if($isCheckIn)
                                @if($isOverdue)
                                    <span class="badge text-bg-danger">Overdue</span>
                                @else
                                    <span class="badge text-bg-success">On time</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $isCheckIn && $isOverdue ? $effectiveLate : '—' }}</td>
                        <td class="text-nowrap">
                            @if($isCheckIn && $isOverdue)
                                <span data-bs-toggle="tooltip" title="{{ $tooltip }}">₱{{ number_format($log->fine_incurred, 2) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-nowrap">
                            @if($log->status === 'Checked Out'
                                && (($log->circulation_type ?? \App\Models\BookLog::CIRCULATION_CHECKOUT) === \App\Models\BookLog::CIRCULATION_CHECKOUT)
                                && $log->due_date
                                && (int) ($log->renew_count ?? 0) < $maxRenewals)
                                <form method="POST" action="{{ route('logs.renew', $log->book_id) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="student_id" value="{{ $log->student_id }}">
                                    <button type="submit" class="btn btn-sm btn-outline-primary"
                                        onclick="return confirm('Renew this loan and extend the due date?');">Renew</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="14" class="text-center text-muted py-5">No transactions match your filters.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top">
            {{ $logs->withQueryString()->links('pagination::bootstrap-5') }}
            <a href="{{ route('transactions.export') }}" class="btn btn-outline-secondary btn-sm">Download report</a>
        </div>
    </section>
</div>

@if(session('overdue_modal'))
<div class="modal fade" id="overdueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Overdue book notice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Book:</strong> {{ session('overdue_modal.book_title') }}</p>
                <p><strong>Patron:</strong> {{ session('overdue_modal.patron_name') }}</p>
                <p><strong>Days late:</strong> {{ session('overdue_modal.days_late') }}</p>
                <p><strong>Fine:</strong> ₱{{ number_format(session('overdue_modal.fine'), 2) }}</p>
                <p class="text-muted mb-0"><em>{{ session('overdue_modal.breakdown') }}</em></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
@endif

@include('partials.loan_terms_modal', ['loanDefaultDays' => $loanDefaultDays ?? 7])

@endsection

@section('scripts')
<script>
    @include('partials.loan_policy_vars')
    window.LOAN_TERMS_RESERVE_HONORS_CUSTOM = true;
</script>
<script src="{{ asset('js/loan-terms.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const patronSuggestUrl = @json(route('patron.suggestions'));
    const bookSuggestUrl = @json(route('book.suggestions'));
    @php
        $bookTitleLogSuggestUrl = \Illuminate\Support\Facades\Route::has('book.title.log.suggestions')
            ? route('book.title.log.suggestions')
            : url('/book-title-log-suggestions');
    @endphp
    const bookTitleLogSuggestUrl = @json($bookTitleLogSuggestUrl);
    const accessionLogSuggestUrl = @json(route('book.accession.log.suggestions'));

    function wireAutocomplete({ input, list, fetchUrl, onSelect, minChars = 1, mapItems }) {
        if (!input || !list) return;

        let debounce = null;

        function hide() {
            list.innerHTML = '';
            list.classList.remove('is-open');
        }

        function showItems(items) {
            list.innerHTML = '';
            if (!items.length) {
                hide();
                return;
            }
            items.forEach(function (item) {
                const li = document.createElement('li');
                li.className = 'list-group-item list-group-item-action';
                li.textContent = item.label;
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    onSelect(item, input, list);
                    hide();
                });
                list.appendChild(li);
            });
            list.classList.add('is-open');
        }

        input.addEventListener('input', function () {
            const query = this.value.trim();
            clearTimeout(debounce);
            if (query.length < minChars) {
                hide();
                return;
            }
            debounce = setTimeout(function () {
                fetch(fetchUrl + '?query=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json' },
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        const items = mapItems(data);
                        showItems(items);
                    })
                    .catch(function () { hide(); });
            }, 200);
        });

        input.addEventListener('blur', function () {
            setTimeout(hide, 150);
        });

        document.addEventListener('click', function (e) {
            if (!list.contains(e.target) && e.target !== input) {
                hide();
            }
        });

        return { hide };
    }

    // Record form — patron
    const studentIdInput = document.getElementById('student_id');
    const employeeIdInput = document.getElementById('employee_id');
    const logForm = document.getElementById('logTransactionForm');

    function applyPatronSelection(patron) {
        if (!patron) return;
        const isEmployee = patron.type === 'employee';
        if (studentIdInput) studentIdInput.value = isEmployee ? '' : (patron.id || '');
        if (employeeIdInput) employeeIdInput.value = isEmployee ? (patron.id || '') : '';
    }

    function clearPatronSelection() {
        if (studentIdInput) studentIdInput.value = '';
        if (employeeIdInput) employeeIdInput.value = '';
    }

    let loanTermsConfirmed = false;

    if (logForm) {
        logForm.addEventListener('submit', function (e) {
            const hasStudent = studentIdInput && String(studentIdInput.value || '').trim();
            const hasEmployee = employeeIdInput && String(employeeIdInput.value || '').trim();
            if (!hasStudent && !hasEmployee) {
                e.preventDefault();
                alert('Select a patron from the suggestions list.');
                return;
            }

            const status = document.getElementById('status_select')?.value;
            if (status === 'checked_out' && !loanTermsConfirmed) {
                e.preventDefault();
                if (typeof promptLoanTerms !== 'function') {
                    logForm.submit();
                    return;
                }
                promptLoanTerms().then(function (terms) {
                    const dueField = document.getElementById('loan_due_date');
                    const daysField = document.getElementById('loan_duration_days');
                    if (dueField) dueField.value = terms.due_date || '';
                    if (daysField) daysField.value = terms.loan_duration_days || '';
                    loanTermsConfirmed = true;
                    if (typeof logForm.requestSubmit === 'function') {
                        logForm.requestSubmit();
                    } else {
                        logForm.submit();
                    }
                });
                return;
            }

            loanTermsConfirmed = false;
        });
    }

    wireAutocomplete({
        input: document.getElementById('patron_name'),
        list: document.getElementById('patronSuggestions'),
        fetchUrl: patronSuggestUrl,
        onSelect: function (item) {
            document.getElementById('patron_name').value = item.raw.name;
            applyPatronSelection(item.raw);
        },
        mapItems: function (data) {
            return (data || []).map(function (p) {
                return { label: p.name, raw: p };
            });
        },
    });

    document.getElementById('patron_name')?.addEventListener('input', clearPatronSelection);

    // Record form — copy ID (accession / barcode / RFID)
    wireAutocomplete({
        input: document.getElementById('copy_identifier_input'),
        list: document.getElementById('bookSuggestions'),
        fetchUrl: bookSuggestUrl,
        onSelect: function (item) {
            const copyInput = document.getElementById('copy_identifier_input');
            const statusSelect = document.getElementById('status_select');
            const patronInput = document.getElementById('patron_name');
            const b = item.raw;
            copyInput.value = b.copy_identifier || b.accession_no || b.barcode || b.rfid || '';
            if ((b.availability || '') === 'Borrowed') {
                if (statusSelect) statusSelect.value = 'checked_in';
                if (patronInput) patronInput.value = b.last_patron || '';
                applyPatronSelection({
                    type: b.last_employee_id ? 'employee' : 'student',
                    id: b.last_employee_id || b.last_student_id || '',
                });
            } else {
                if (statusSelect) statusSelect.value = 'checked_out';
                clearPatronSelection();
            }
        },
        mapItems: function (data) {
            return (data || []).map(function (b) {
                const idPart = b.copy_identifier_summary || 'No copy ID';
                const reserve = b.is_reserve ? ' · Reserve' : '';
                const label = (b.title || 'Untitled') + ' — ' + (b.author || '') + ' · ' + idPart + reserve;
                return { label: label, raw: b };
            });
        },
    });

    // Filter — patron
    const filterStudentId = document.getElementById('filter_student_id');
    const filterEmployeeId = document.getElementById('filter_employee_id');
    wireAutocomplete({
        input: document.getElementById('filter_patron'),
        list: document.getElementById('filterPatronSuggestions'),
        fetchUrl: patronSuggestUrl,
        onSelect: function (item) {
            document.getElementById('filter_patron').value = item.raw.name;
            const isEmployee = item.raw.type === 'employee';
            if (filterStudentId) filterStudentId.value = isEmployee ? '' : item.raw.id;
            if (filterEmployeeId) filterEmployeeId.value = isEmployee ? item.raw.id : '';
        },
        mapItems: function (data) {
            return (data || []).map(function (p) {
                return { label: p.name, raw: p };
            });
        },
    });

    document.getElementById('filter_patron')?.addEventListener('input', function () {
        if (filterStudentId) filterStudentId.value = '';
        if (filterEmployeeId) filterEmployeeId.value = '';
    });

    // Filter — book title
    wireAutocomplete({
        input: document.getElementById('filter_book_title'),
        list: document.getElementById('filterBookTitleSuggestions'),
        fetchUrl: bookTitleLogSuggestUrl,
        onSelect: function (item) {
            document.getElementById('filter_book_title').value = item.raw.title;
        },
        mapItems: function (data) {
            return (data || []).map(function (row) {
                return { label: row.title, raw: row };
            });
        },
    });

    wireAutocomplete({
        input: document.getElementById('filter_accession_no'),
        list: document.getElementById('filterAccessionSuggestions'),
        fetchUrl: accessionLogSuggestUrl,
        onSelect: function (item) {
            document.getElementById('filter_accession_no').value = item.raw.accession_no;
        },
        mapItems: function (data) {
            return (data || []).map(function (row) {
                return { label: row.label, raw: row };
            });
        },
    });

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    const overdueModal = document.getElementById('overdueModal');
    if (overdueModal) {
        bootstrap.Modal.getOrCreateInstance(overdueModal).show();
    }
});
</script>
@endsection
