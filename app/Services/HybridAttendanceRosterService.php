<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class HybridAttendanceRosterService
{
    public function __construct(
        protected AttendanceSessionService $sessions
    ) {}

    /** @return array<string, mixed> */
    public function build(?Carbon $since = null): array
    {
        $serverTime = now()->toIso8601String();

        $studentsQuery = Student::query()->orderBy('id');
        if ($since) {
            $studentsQuery->where(function ($q) use ($since) {
                $q->where('updated_at', '>=', $since)
                    ->orWhere('created_at', '>=', $since);
            });
        }

        $students = $studentsQuery->get();

        $studentIds = $since
            ? $students->pluck('id')
            : Student::query()->pluck('id');

        $lastLogs = $this->lastLogsForStudents($studentIds);

        $roster = $students->map(function (Student $student) use ($lastLogs) {
            $last = $lastLogs[$student->id] ?? null;

            return [
                'id' => $student->id,
                'id_number' => $student->id_number,
                'firstname' => $student->firstname,
                'lastname' => $student->lastname,
                'qrcode' => $student->qrcode,
                'course' => $student->course,
                'year' => $student->year,
                'normalized_name' => $student->normalized_name ?? null,
                'profile_picture' => $student->profile_picture,
                'mobile_number' => $student->mobile_number,
                'last_log' => $last ? [
                    'status' => strtoupper((string) $last->status),
                    'scanned_at' => $last->scanned_at
                        ? Carbon::parse($last->scanned_at)->toIso8601String()
                        : null,
                ] : null,
            ];
        })->values();

        $employees = collect();
        if (Schema::hasTable('employees')) {
            $employeesQuery = Employee::query()->orderBy('id');
            if ($since) {
                $employeesQuery->where(function ($q) use ($since) {
                    $q->where('updated_at', '>=', $since)
                        ->orWhere('created_at', '>=', $since);
                });
            }
            $employeeModels = $employeesQuery->get();
            $employeeIds = $since
                ? $employeeModels->pluck('id')
                : Employee::query()->pluck('id');
            $lastEmployeeLogs = $this->lastLogsForEmployees($employeeIds);

            $employees = $employeeModels->map(function (Employee $employee) use ($lastEmployeeLogs) {
                $last = $lastEmployeeLogs[$employee->id] ?? null;

                return [
                    'id' => $employee->id,
                    'employee_id' => $employee->employee_id,
                    'firstname' => $employee->firstname,
                    'lastname' => $employee->lastname,
                    'qrcode' => $employee->qrcode,
                    'department' => $employee->department,
                    'designation' => $employee->designation,
                    'program' => $employee->program,
                    'profile_picture' => $employee->formal_picture,
                    'mobile_number' => $employee->mobile_number,
                    'last_log' => $last ? [
                        'status' => strtoupper((string) $last->status),
                        'scanned_at' => $last->scanned_at
                            ? Carbon::parse($last->scanned_at)->toIso8601String()
                            : null,
                    ] : null,
                ];
            })->values();
        }

        $logsSince = null;
        if ($since) {
            $logsSince = AttendanceLog::query()
                ->where('scanned_at', '>=', $since)
                ->orderBy('scanned_at')
                ->get()
                ->map(fn (AttendanceLog $log) => [
                    'student_id' => $log->student_id,
                    'employee_id' => $log->employee_id,
                    'status' => strtoupper((string) $log->status),
                    'scanned_at' => $log->scanned_at
                        ? Carbon::parse($log->scanned_at)->toIso8601String()
                        : null,
                    'client_uuid' => $log->client_uuid,
                ])
                ->values();
        }

        return [
            'server_time' => $serverTime,
            'since' => $since?->toIso8601String(),
            'full_snapshot' => $since === null,
            'settings' => [
                'logout_feedback_enabled' => Setting::logoutFeedbackEnabled(),
                'timezone' => AttendanceSessionService::TZ,
            ],
            'students' => $roster,
            'employees' => $employees,
            'logs_since' => $logsSince,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $studentIds
     * @return array<int, AttendanceLog>
     */
    protected function lastLogsForStudents($studentIds): array
    {
        $ids = collect($studentIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $map = [];
        foreach ($ids->chunk(500) as $chunk) {
            $logs = AttendanceLog::query()
                ->whereIn('student_id', $chunk->all())
                ->orderByDesc('scanned_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy('student_id');

            foreach ($logs as $studentId => $group) {
                $map[(int) $studentId] = $group->first();
            }
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $employeeIds
     * @return array<int, AttendanceLog>
     */
    protected function lastLogsForEmployees($employeeIds): array
    {
        if (! Schema::hasColumn('attendance_logs', 'employee_id')) {
            return [];
        }

        $ids = collect($employeeIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $map = [];
        foreach ($ids->chunk(500) as $chunk) {
            $logs = AttendanceLog::query()
                ->whereIn('employee_id', $chunk->all())
                ->orderByDesc('scanned_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy('employee_id');

            foreach ($logs as $employeeId => $group) {
                $map[(int) $employeeId] = $group->first();
            }
        }

        return $map;
    }
}
