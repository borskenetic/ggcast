<?php

namespace App\Support;

use App\Models\Book;
use App\Models\FineSetting;
use App\Models\Holiday;
use Carbon\Carbon;

class LoanDueDate
{
    public static function addBusinessDays(Carbon $start, int $days): Carbon
    {
        $holidays = Holiday::pluck('holiday_date')->map(function ($d) {
            return Carbon::parse($d)->startOfDay()->toDateString();
        });

        $date = $start->copy()->startOfDay();
        $added = 0;

        while ($added < $days) {
            $date->addDay();

            if (! $date->isWeekend() && ! $holidays->contains($date->toDateString())) {
                $added++;
            }
        }

        return $date;
    }

    /**
     * @return array{due_date: ?Carbon, loan_duration_days: ?int}
     */
    public static function resolveFromRequest(
        Carbon $borrowedAt,
        FineSetting $settings,
        ?string $dueDateInput,
        ?int $loanDaysInput,
    ): array {
        if ($dueDateInput) {
            return [
                'due_date' => Carbon::parse($dueDateInput)->startOfDay(),
                'loan_duration_days' => null,
            ];
        }

        if ($loanDaysInput !== null && $loanDaysInput > 0) {
            return [
                'due_date' => self::addBusinessDays($borrowedAt, $loanDaysInput),
                'loan_duration_days' => $loanDaysInput,
            ];
        }

        $defaultDays = (int) ($settings->loan_duration_days ?? 7);

        return [
            'due_date' => self::addBusinessDays($borrowedAt, $defaultDays),
            'loan_duration_days' => $defaultDays,
        ];
    }

    /**
     * Due date for one copy. Reserve copies use the reserve loan length unless a
     * custom period is allowed and supplied (circulation desk). Self-checkout
     * keeps the reserve due date even if the patron enters a custom date.
     *
     * @return array{due_date: Carbon, loan_duration_days: ?int, is_reserve: bool}
     */
    public static function forCopy(
        Carbon $borrowedAt,
        FineSetting $settings,
        Book $book,
        ?string $dueDateInput,
        ?int $loanDaysInput,
        bool $customAppliesToReserve = true,
    ): array {
        $isReserve = $book->isReserve();
        $allowCustom = $customAppliesToReserve || ! $isReserve;

        if ($allowCustom && $dueDateInput) {
            return [
                'due_date' => Carbon::parse($dueDateInput)->startOfDay(),
                'loan_duration_days' => null,
                'is_reserve' => $isReserve,
            ];
        }

        if ($allowCustom && $loanDaysInput !== null && $loanDaysInput > 0) {
            return [
                'due_date' => self::addBusinessDays($borrowedAt, $loanDaysInput),
                'loan_duration_days' => $loanDaysInput,
                'is_reserve' => $isReserve,
            ];
        }

        $days = $isReserve
            ? $settings->reserveLoanDays()
            : max(1, (int) ($settings->loan_duration_days ?? 7));

        return [
            'due_date' => self::addBusinessDays($borrowedAt, $days),
            'loan_duration_days' => $days,
            'is_reserve' => $isReserve,
        ];
    }
}
