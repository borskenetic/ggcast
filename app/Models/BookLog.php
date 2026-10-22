<?php

namespace App\Models;

use App\Models\FineSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BookLog extends Model
{
    use HasFactory;

    public const CIRCULATION_CHECKOUT = 'checkout';

    public const CIRCULATION_ROOM_USE = 'room_use';

    protected $fillable = [
        'book_id',
        'student_id',
        'employee_id',
        'patron_name',
        'status',
        'circulation_type',
        'is_reserve',
        'renew_count',
        'last_renewed_at',
        'timestamp',
        'due_date',
        'returned_date',
        'fine_incurred',
        'fine_original',
        'fine_balance',
        'fine_paid_total',
        'fine_waived_total',
        'fine_cleared_at',
        'fine_clearance_type',
        'fine_clearance_note',
        'fine_cleared_by',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'due_date' => 'date',
        'returned_date' => 'datetime',
        'last_renewed_at' => 'datetime',
        'fine_incurred' => 'decimal:2',
        'fine_original' => 'decimal:2',
        'fine_balance' => 'decimal:2',
        'fine_paid_total' => 'decimal:2',
        'fine_waived_total' => 'decimal:2',
        'fine_cleared_at' => 'datetime',
        'is_reserve' => 'boolean',
    ];

    protected $appends = [
        'is_overdue',
        'days_overdue',
        'total_fine',
    ];

    /**
     * Latest log per book is Checked Out for this student (includes room use).
     */
    public static function countActiveLoansForStudent(int $studentId): int
    {
        $query = static::latestCheckedOutQuery();

        return $query === null
            ? 0
            : (int) $query->where('student_id', $studentId)->count();
    }

    public static function countActiveLoansForEmployee(int $employeeId): int
    {
        $query = static::latestCheckedOutQuery();

        return $query === null
            ? 0
            : (int) $query->where('employee_id', $employeeId)->count();
    }

    public static function countActiveReserveLoansForStudent(int $studentId): int
    {
        $query = static::latestCheckedOutQuery();

        return $query === null
            ? 0
            : (int) $query->where('student_id', $studentId)->whereReserveCopy()->count();
    }

    public static function countActiveReserveLoansForEmployee(int $employeeId): int
    {
        $query = static::latestCheckedOutQuery();

        return $query === null
            ? 0
            : (int) $query->where('employee_id', $employeeId)->whereReserveCopy()->count();
    }

    /**
     * Open loans whose copy was issued as reserve, or whose sublocation still says reserve.
     */
    public function scopeWhereReserveCopy($query)
    {
        return $query->where(function ($q) {
            $q->where('is_reserve', 1)
                ->orWhereHas('book', function ($book) {
                    $book->whereRaw('LOWER(COALESCE(section, \'\')) LIKE ?', ['%reserve%']);
                });
        });
    }

    public function usesReservePolicy(): bool
    {
        if ((bool) $this->is_reserve) {
            return true;
        }

        if ($this->status !== 'Checked Out') {
            return false;
        }

        $this->loadMissing('book');

        return (bool) $this->book?->isReserve();
    }

    protected static function latestCheckedOutQuery()
    {
        $latestIds = DB::table('book_logs')
            ->selectRaw('MAX(id) as id')
            ->groupBy('book_id')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return null;
        }

        return static::query()
            ->whereIn('id', $latestIds)
            ->where('status', 'Checked Out');
    }

    public function book()
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function clearedBy()
    {
        return $this->belongsTo(User::class, 'fine_cleared_by');
    }

    public function fineIsCleared(): bool
    {
        return $this->fine_cleared_at !== null;
    }

    /**
     * Human label for how the book was issued (only meaningful while out; check-in rows copy the closed loan type).
     */
    public function circulationLabel(): string
    {
        $type = $this->circulation_type ?? self::CIRCULATION_CHECKOUT;

        if ($this->status === 'Checked In') {
            return 'Check in';
        }

        return $type === self::CIRCULATION_ROOM_USE ? 'Room use' : 'Check out';
    }

    /**
     * One-line description for patron-facing transaction history.
     */
    public function historySummary(): string
    {
        $type = $this->circulation_type ?? self::CIRCULATION_CHECKOUT;

        $reserve = (bool) $this->is_reserve || ($this->status === 'Checked Out' && $this->usesReservePolicy());

        if ($this->status === 'Checked In') {
            if ($type === self::CIRCULATION_ROOM_USE) {
                $kind = $reserve ? 'reserve room-use loan' : 'room-use loan';
            } else {
                $kind = $reserve ? 'reserve loan' : 'outside loan';
            }

            return 'Returned ('.$kind.')';
        }

        if ($type === self::CIRCULATION_ROOM_USE) {
            return $reserve ? 'Reserve room use (in library)' : 'Room use (in library)';
        }

        return $reserve ? 'Checked out (reserve)' : 'Checked out (outside library)';
    }

    public function patronLabel(): string
    {
        if ($this->student_id) {
            $this->loadMissing('student');
            if ($this->student) {
                return "{$this->student->lastname}, {$this->student->firstname}";
            }
        }

        if ($this->employee_id) {
            $this->loadMissing('employee');
            if ($this->employee) {
                $name = "{$this->employee->lastname}, {$this->employee->firstname}";
                if ($this->employee->middle_initial) {
                    $name .= ' '.$this->employee->middle_initial.'.';
                }

                return $name;
            }
        }

        return $this->patron_name ?? '—';
    }

    public function getTimestampManilaAttribute()
    {
        return $this->timestamp
            ? $this->timestamp->timezone('Asia/Manila')->format('Y-m-d h:i A')
            : null;
    }

    public function getIsOverdueAttribute()
    {
        if (! $this->due_date) {
            return false;
        }

        $compareDate = $this->returned_date
            ? Carbon::parse($this->returned_date)
            : Carbon::now('Asia/Manila');

        return $compareDate->gt($this->due_date);
    }

    public function getDaysOverdueAttribute()
    {
        $settings = FineSetting::latest('effective_from')->first();

        if (! $this->due_date || ! $settings) {
            return 0;
        }

        $compareDate = $this->returned_date
            ? Carbon::parse($this->returned_date)
            : Carbon::now('Asia/Manila');

        $graceEnd = Carbon::parse($this->due_date)
            ->addDays($settings->graceDaysFor($this->usesReservePolicy()));

        if ($compareDate->lte($graceEnd)) {
            return 0;
        }

        return $graceEnd->diffInDays($compareDate);
    }

    public function getTotalFineAttribute()
    {
        if ($this->returned_date) {
            if ($this->fine_cleared_at !== null) {
                return 0.0;
            }

            if ($this->fine_balance !== null) {
                return (float) $this->fine_balance;
            }

            return (float) $this->fine_incurred;
        }

        $settings = FineSetting::latest('effective_from')->first();

        if (! $settings || $this->days_overdue === 0) {
            return 0;
        }

        $reserve = $this->usesReservePolicy();
        $fine = $this->days_overdue * $settings->finePerDayFor($reserve);
        $maxFine = $settings->maxFineFor($reserve);

        if ($maxFine !== null) {
            $fine = min($fine, $maxFine);
        }

        return round($fine, 2);
    }
}
