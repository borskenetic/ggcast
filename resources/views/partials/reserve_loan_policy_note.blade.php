@php
    $loanPolicy = \App\Models\FineSetting::latest('created_at')->first();
    $regularDays = max(1, (int) ($loanPolicy?->loan_duration_days ?? 7));
    $reserveDays = $loanPolicy ? $loanPolicy->reserveLoanDays() : 1;
    $maxLoans = \App\Models\Setting::maxLoansForStudents();
    $maxReserve = \App\Models\Setting::maxReserveLoans();
@endphp
<p class="small text-muted mb-2">
    You may borrow up to <strong>{{ $maxLoans }}</strong> {{ $maxLoans === 1 ? 'book' : 'books' }} at a time,
    including at most <strong>{{ $maxReserve }}</strong> reserve {{ $maxReserve === 1 ? 'book' : 'books' }}.
    A copy is reserve when its sublocation contains “Reserve”.
    Reserve copies are due in <strong>{{ $reserveDays }}</strong> business {{ $reserveDays === 1 ? 'day' : 'days' }}
    and use a separate fine. Other copies are due in <strong>{{ $regularDays }}</strong> business days.
    Each reserve book counts toward the {{ $maxLoans }}-book total.
</p>
