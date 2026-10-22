@php
    $loanPolicy = \App\Models\FineSetting::latest('created_at')->first();
    $loanDefaultDays = max(1, (int) ($loanPolicy?->loan_duration_days ?? 7));
    $reserveLoanDays = $loanPolicy ? $loanPolicy->reserveLoanDays() : 1;
@endphp
window.LOAN_DEFAULT_DAYS = @json($loanDefaultDays);
window.RESERVE_LOAN_DAYS = @json($reserveLoanDays);
window.MAX_LOANS = @json(\App\Models\Setting::maxLoansForStudents());
window.MAX_RESERVE_LOANS = @json(\App\Models\Setting::maxReserveLoans());
