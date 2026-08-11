<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceSessionService
{
    public const TZ = 'Asia/Manila';

    public function isInStatus(?string $status): bool
    {
        return $status !== null && strtolower(trim((string) $status)) === 'in';
    }

    public function isOutStatus(?string $status): bool
    {
        return $status !== null && strtolower(trim((string) $status)) === 'out';
    }

    /**
     * If the patron's last scan is still IN from a **previous** calendar day (Manila),
     * insert one OUT at end of that IN day so the next real scan starts as IN again.
     */
    public function closeStaleOpenInForStudent(Student $student): bool
    {
        return $this->closeStaleOpenIn([
            'student_id' => $student->id,
        ]);
    }

    public function closeStaleOpenInForEmployee(Employee $employee): bool
    {
        return $this->closeStaleOpenIn([
            'employee_id' => $employee->id,
        ]);
    }

    /**
     * @param  array{student_id?: int|string|null, employee_id?: int|string|null}  $patronKeys
     */
    public function closeStaleOpenIn(array $patronKeys): bool
    {
        $query = AttendanceLog::query();
        if (! empty($patronKeys['student_id'])) {
            $query->where('student_id', $patronKeys['student_id']);
        } elseif (! empty($patronKeys['employee_id'])) {
            $query->where('employee_id', $patronKeys['employee_id']);
        } else {
            return false;
        }

        $last = $query
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->first();

        if (! $last || ! $this->isInStatus($last->status)) {
            return false;
        }

        $inDayStart = Carbon::parse($last->scanned_at)->timezone(self::TZ)->startOfDay();
        $todayStart = Carbon::now(self::TZ)->startOfDay();

        if ($inDayStart->greaterThanOrEqualTo($todayStart)) {
            return false;
        }

        $outAt = Carbon::parse($last->scanned_at)->timezone(self::TZ)->endOfDay();

        $payload = [
            'status' => 'OUT',
            'scanned_at' => $outAt,
        ];

        if (! empty($patronKeys['student_id'])) {
            $payload['student_id'] = $patronKeys['student_id'];
        }
        if (! empty($patronKeys['employee_id'])) {
            $payload['employee_id'] = $patronKeys['employee_id'];
        }

        AttendanceLog::create($payload);

        return true;
    }

    /**
     * Close every patron who is still "IN" from a prior calendar day (batch job).
     */
    public function closeAllStaleOpenIns(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('attendance_logs')) {
            return 0;
        }

        $today = Carbon::now(self::TZ)->toDateString();
        $closed = 0;

        $staleStudentIds = DB::table('attendance_logs as al')
            ->join(DB::raw('(
                SELECT student_id, MAX(id) AS max_id
                FROM attendance_logs
                WHERE student_id IS NOT NULL
                GROUP BY student_id
            ) AS last'), 'last.max_id', '=', 'al.id')
            ->whereRaw("LOWER(TRIM(al.status)) = 'in'")
            ->whereRaw('DATE(al.scanned_at) < ?', [$today])
            ->pluck('al.student_id');

        foreach ($staleStudentIds as $sid) {
            $student = Student::query()->find($sid);
            if (! $student) {
                continue;
            }
            if ($this->closeStaleOpenInForStudent($student)) {
                $closed++;
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('attendance_logs', 'employee_id')) {
            $staleEmployeeIds = DB::table('attendance_logs as al')
                ->join(DB::raw('(
                    SELECT employee_id, MAX(id) AS max_id
                    FROM attendance_logs
                    WHERE employee_id IS NOT NULL
                    GROUP BY employee_id
                ) AS last'), 'last.max_id', '=', 'al.id')
                ->whereRaw("LOWER(TRIM(al.status)) = 'in'")
                ->whereRaw('DATE(al.scanned_at) < ?', [$today])
                ->pluck('al.employee_id');

            foreach ($staleEmployeeIds as $eid) {
                $employee = Employee::query()->find($eid);
                if (! $employee) {
                    continue;
                }
                if ($this->closeStaleOpenInForEmployee($employee)) {
                    $closed++;
                }
            }
        }

        return $closed;
    }
}
