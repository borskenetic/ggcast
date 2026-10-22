<?php

namespace App\Support;

use App\Models\Book;
use App\Models\BookLog;
use App\Models\Setting;
use Illuminate\Support\Collection;

class LoanLimits
{
    /**
     * Reserve copies count toward the patron's total loan cap and also toward
     * a smaller reserve cap. Returns a message when the checkout must be refused.
     *
     * @param  iterable<Book>  $books
     */
    public static function rejectionMessage(?int $studentId, ?int $employeeId, iterable $books): ?string
    {
        /** @var Collection<int, Book> $items */
        $items = collect($books)->filter(fn ($book) => $book instanceof Book)->values();
        $adding = $items->count();

        if ($adding < 1) {
            return null;
        }

        $addingReserve = $items->filter(fn (Book $book) => $book->isReserve())->count();
        $parts = [];

        if ($studentId) {
            $current = BookLog::countActiveLoansForStudent($studentId);
            if (Setting::wouldExceedStudentLoanLimit($current, $adding)) {
                $max = Setting::maxLoansForStudents();
                $parts[] = 'This patron may have at most '.$max.' book'.($max === 1 ? '' : 's')
                    .' on loan at a time (including room use). Reserve books count toward this total.';
            }
        } elseif ($employeeId && ! Setting::employeeLoansUnlimited()) {
            $current = BookLog::countActiveLoansForEmployee($employeeId);
            if (Setting::wouldExceedEmployeeLoanLimit($current, $adding)) {
                $max = Setting::maxLoansForEmployees();
                $parts[] = 'This patron may have at most '.$max.' book'.($max === 1 ? '' : 's')
                    .' on loan at a time (including room use). Reserve books count toward this total.';
            }
        }

        if ($addingReserve > 0) {
            $currentReserve = $studentId
                ? BookLog::countActiveReserveLoansForStudent($studentId)
                : BookLog::countActiveReserveLoansForEmployee((int) $employeeId);
            $maxReserve = Setting::maxReserveLoans();

            if ($currentReserve + $addingReserve > $maxReserve) {
                $parts[] = 'This patron may have at most '.$maxReserve.' reserve book'.($maxReserve === 1 ? '' : 's')
                    .' on loan at a time (including room use). A copy is reserve when its sublocation contains "Reserve", and that loan still counts toward the total book limit.';
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }
}
