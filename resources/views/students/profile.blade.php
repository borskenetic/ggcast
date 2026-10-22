@extends('layouts.kiosk')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/students/students.css') }}">
@endsection

@section('content')
@php($maxRenewals = \App\Models\Setting::maxRenewalsPerLoan())
<div class="container py-4">

    <div class="mb-3">
        <a href="{{ route('kiosk.scan') }}" class="btn btn-outline-secondary btn-sm">← Back to student lookup</a>
    </div>

    {{-- PROFILE --}}
    <div class="card mb-4">
        <div class="card-body d-flex flex-column flex-md-row align-items-center align-items-md-start">
            @if($student->profile_picture)
            <img src="{{ asset($student->profile_picture) }}"
                 class="rounded-circle me-md-4 mb-3 mb-md-0"
                 width="120" height="120"
                 alt="">
            @else
            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-md-4 mb-3 mb-md-0"
                 style="width:120px;height:120px;font-size:2.5rem;">
                {{ strtoupper(substr($student->firstname, 0, 1) . substr($student->lastname, 0, 1)) }}
            </div>
            @endif

            <div class="text-center text-md-start flex-grow-1">
                <h4 class="mb-1">{{ $student->firstname }} {{ $student->lastname }}</h4>
                <p class="mb-1 text-muted">ID: {{ $student->id_number ?? '—' }}</p>
                <p class="mb-1">{{ $program?->program_name ?? 'Program not set' }}</p>
                <p class="mb-3">{{ $student->year ?? '—' }}</p>

                <button class="btn btn-sm btn-outline-primary"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#editProfileModal">
                    Request edit
                </button>
            </div>
        </div>
    </div>

    {{-- SUMMARY --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted small text-uppercase">Books checked out</div>
                    <div class="display-6 fw-semibold">{{ $booksOutCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm {{ $overdueBooksCount > 0 ? 'border-warning' : '' }}" @if($overdueBooksCount > 0) style="border: 2px solid #ffc107 !important;" @endif>
                <div class="card-body text-center">
                    <div class="text-muted small text-uppercase">Overdue (past grace)</div>
                    <div class="display-6 fw-semibold {{ $overdueBooksCount > 0 ? 'text-danger' : 'text-success' }}">{{ $overdueBooksCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-muted small text-uppercase">Estimated fine (current loans)</div>
                    <div class="display-6 fw-semibold">₱{{ number_format($totalOutstandingFine, 2) }}</div>
                    <div class="small text-muted mt-1">Based on due date, grace period, and fine settings.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- BORROWED BOOKS --}}
    <div class="card mb-4">
        <div class="card-header fw-semibold">Borrowed books</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Loan type</th>
                            <th>Due date</th>
                            <th>Renewals</th>
                            <th>Status</th>
                            <th class="text-end">Est. fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($borrowedBooks as $log)
                        @php
                            $daysLate = (int) $log->days_overdue;
                            $isLate = $daysLate > 0;
                        @endphp
                        <tr>
                            <td>{{ $log->book->title_statement ?? '—' }}</td>
                            <td>
                                @if(($log->circulation_type ?? \App\Models\BookLog::CIRCULATION_CHECKOUT) === \App\Models\BookLog::CIRCULATION_ROOM_USE)
                                    <span class="badge bg-info text-dark">Room use</span>
                                    <span class="text-muted small d-block">In library only</span>
                                @else
                                    <span class="badge bg-primary">Check out</span>
                                @endif
                                @if($log->usesReservePolicy())
                                    <span class="badge text-bg-warning">Reserve</span>
                                @endif
                            </td>
                            <td>
                                @if($log->due_date)
                                    {{ $log->due_date->format('M j, Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if(($log->circulation_type ?? \App\Models\BookLog::CIRCULATION_CHECKOUT) === \App\Models\BookLog::CIRCULATION_CHECKOUT)
                                    {{ (int) ($log->renew_count ?? 0) }}/{{ $maxRenewals }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if(($log->circulation_type ?? \App\Models\BookLog::CIRCULATION_CHECKOUT) === \App\Models\BookLog::CIRCULATION_ROOM_USE)
                                    <span class="badge bg-secondary">No outside loan</span>
                                @elseif(!$log->due_date)
                                    <span class="badge bg-secondary">No due date</span>
                                @elseif($isLate)
                                    <span class="badge bg-danger">Overdue</span>
                                    <span class="text-muted small">{{ $daysLate }} day(s) after grace</span>
                                @else
                                    <span class="badge bg-success">On time</span>
                                @endif
                            </td>
                            <td class="text-end">₱{{ number_format((float) $log->total_fine, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No books checked out.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- BOOK TRANSACTION HISTORY --}}
    <div class="card mb-4">
        <div class="card-header fw-semibold">Book transaction history</div>
        <div class="card-body p-0">
            <p class="small text-muted px-3 pt-3 mb-2">
                Recent circulation events for this patron (check out, check in, room use). Up to 75 entries, newest first.
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>When</th>
                            <th>Book</th>
                            <th>Barcode</th>
                            <th>Status</th>
                            <th>Summary</th>
                            <th>Due</th>
                            <th>Returned</th>
                            <th class="text-end">Renewals</th>
                            <th class="text-end">Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bookTransactionHistory as $hlog)
                        <tr>
                            <td class="text-nowrap small">{{ $hlog->timestamp_manila ?? '—' }}</td>
                            <td>{{ $hlog->book->title_statement ?? '—' }}</td>
                            <td class="small">{{ $hlog->book->barcode ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ $hlog->status }}</span></td>
                            <td class="small">{{ $hlog->historySummary() }}</td>
                            <td class="small">
                                @if($hlog->due_date)
                                    {{ $hlog->due_date->format('M j, Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small">
                                @if($hlog->returned_date)
                                    {{ $hlog->returned_date->timezone('Asia/Manila')->format('M j, Y g:i A') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end small">
                                @if($hlog->status === 'Checked Out' && ($hlog->circulation_type ?? \App\Models\BookLog::CIRCULATION_CHECKOUT) === \App\Models\BookLog::CIRCULATION_CHECKOUT)
                                    {{ (int) ($hlog->renew_count ?? 0) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end small">
                                @if($hlog->fine_incurred !== null && (float) $hlog->fine_incurred > 0)
                                    ₱{{ number_format((float) $hlog->fine_incurred, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No book transactions on file.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($returnedFineHistory->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Fines from returned books</span>
            @if($totalReturnedFinesOutstanding > 0)
                <span class="badge bg-danger">Still owed: ₱{{ number_format($totalReturnedFinesOutstanding, 2) }}</span>
            @else
                <span class="badge bg-success">No balance on closed loans</span>
            @endif
        </div>
        <div class="card-body p-0">
            <p class="small text-muted px-3 pt-3 mb-2">Recorded when books were checked in. Pay at the library — staff marks fines as <strong>paid</strong> or <strong>waived</strong>.</p>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Returned</th>
                            <th class="text-end">Recorded</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($returnedFineHistory as $log)
                        <tr>
                            <td>{{ $log->book->title_statement ?? '—' }}</td>
                            <td>{{ $log->returned_date ? $log->returned_date->timezone('Asia/Manila')->format('M j, Y g:i A') : '—' }}</td>
                            <td class="text-end">₱{{ number_format((float) $log->fine_incurred, 2) }}</td>
                            <td>
                                @if($log->fine_cleared_at)
                                    <span class="badge bg-success">{{ $log->fine_clearance_type === 'waived' ? 'Waived' : 'Paid' }}</span>
                                    <span class="small text-muted d-block">{{ $log->fine_cleared_at->timezone('Asia/Manila')->format('M j, Y') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">Outstanding</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    @php
        $grandDue = round($totalOutstandingFine + $totalReturnedFinesOutstanding, 2);
    @endphp
    <div class="alert {{ $grandDue > 0 || $overdueBooksCount > 0 ? 'alert-warning' : 'alert-info' }} text-center mb-0">
        <div><strong>Estimated fines on current loans:</strong> ₱{{ number_format($totalOutstandingFine, 2) }}</div>
        @if($totalReturnedFinesOutstanding > 0)
            <div class="mt-1"><strong>Still owed on returned books:</strong> ₱{{ number_format($totalReturnedFinesOutstanding, 2) }}</div>
        @endif
        <div class="mt-2 fw-semibold">Combined outstanding: ₱{{ number_format($grandDue, 2) }}</div>
        @if($booksOutCount === 0 && $totalReturnedFinesOutstanding <= 0)
            <span class="d-block small mt-2 mb-0 text-muted">No active checkouts and no uncleared return fines.</span>
        @endif
    </div>

</div>

@include('students.edit-modal')
@endsection
