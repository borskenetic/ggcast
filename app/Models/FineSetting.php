<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FineSetting extends Model
{
    protected $fillable = [
        'fine_per_day',
        'max_fine',
        'grace_period_days',
        'loan_duration_days',
        'reserve_fine_per_day',
        'reserve_max_fine',
        'reserve_grace_period_days',
        'reserve_loan_duration_days',
        'effective_from',
    ];

    public function reserveLoanDays(): int
    {
        $days = (int) ($this->reserve_loan_duration_days ?? 0);

        return $days > 0 ? $days : 1;
    }

    public function finePerDayFor(bool $reserve): float
    {
        if ($reserve && $this->reserve_fine_per_day !== null && $this->reserve_fine_per_day !== '') {
            return (float) $this->reserve_fine_per_day;
        }

        return (float) ($this->fine_per_day ?? 0);
    }

    public function maxFineFor(bool $reserve): ?float
    {
        $value = $reserve ? $this->reserve_max_fine : $this->max_fine;

        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    public function graceDaysFor(bool $reserve): int
    {
        if ($reserve) {
            return (int) ($this->reserve_grace_period_days ?? $this->grace_period_days ?? 0);
        }

        return (int) ($this->grace_period_days ?? 0);
    }

    public static function current()
    {
        return self::orderByDesc('effective_from')->first();
    }
}

