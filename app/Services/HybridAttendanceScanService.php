<?php

namespace App\Services;

use App\Http\Controllers\SMSController;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\HybridDevice;
use App\Models\Setting;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HybridAttendanceScanService
{
    public function __construct(
        protected AttendanceSessionService $sessions
    ) {}

    /**
     * @return array{type: 'student', student: Student}|array{type: 'employee', employee: Employee}|null
     */
    public function resolvePatron(string $raw): ?array
    {
        $token = trim(str_replace("\r", '', $raw));
        if ($token === '') {
            return null;
        }

        // Prefer explicit library QR prefixes so staff/student codes never hit the wrong table's name search.
        if (preg_match('/^E-\d+/i', $token)) {
            $employee = $this->resolveEmployee($token);
            if ($employee) {
                return ['type' => 'employee', 'employee' => $employee];
            }
        }

        if (preg_match('/^S-\d+/i', $token)) {
            $student = $this->resolveStudent($token);
            if ($student) {
                return ['type' => 'student', 'student' => $student];
            }
        }

        $student = $this->resolveStudent($token);
        if ($student) {
            return ['type' => 'student', 'student' => $student];
        }

        $employee = $this->resolveEmployee($token);
        if ($employee) {
            return ['type' => 'employee', 'employee' => $employee];
        }

        return null;
    }

    public function resolveStudent(string $raw): ?Student
    {
        $token = trim(str_replace("\r", '', $raw));
        if ($token === '') {
            return null;
        }

        // Employee QR prefixes never apply to students — skip costly/failing paths.
        if (preg_match('/^E-\d+/i', $token)) {
            return null;
        }

        $student = Student::where('qrcode', $token)->first();
        if ($student) {
            return $student;
        }

        $parsed = $this->parseQr($token);

        if (! empty($parsed['student_no'])) {
            $student = Student::where('id_number', $parsed['student_no'])->first();
            if ($student) {
                return $student;
            }
        }

        if (
            ! empty($parsed['full_name'])
            && ! $this->looksLikeIdToken($parsed['full_name'])
            && Schema::hasColumn('students', 'normalized_name')
        ) {
            $qrName = $this->normalizeName($parsed['full_name']);
            if ($qrName !== '') {
                $student = Student::where('normalized_name', $qrName)->first();
                if ($student) {
                    return $student;
                }
            }
        }

        // Bare id_number paste
        return Student::where('id_number', $token)->first();
    }

    public function resolveEmployee(string $raw): ?Employee
    {
        $token = trim(str_replace("\r", '', $raw));
        if ($token === '') {
            return null;
        }

        // Student QR prefixes never apply to employees.
        if (preg_match('/^S-\d+/i', $token)) {
            return null;
        }

        $employee = Employee::where('qrcode', $token)->first();
        if ($employee) {
            return $employee;
        }

        // Bare business employee ID (FAC-…, FT-…, etc.)
        $employee = Employee::where('employee_id', $token)->first();
        if ($employee) {
            return $employee;
        }

        $parsed = $this->parseQr($token);

        if (! empty($parsed['student_no'])) {
            $employee = Employee::where('employee_id', $parsed['student_no'])->first();
            if ($employee) {
                return $employee;
            }
        }

        if (! empty($parsed['full_name']) && ! $this->looksLikeIdToken($parsed['full_name'])) {
            $qrName = $this->normalizeName($parsed['full_name']);
            if ($qrName !== '') {
                $employee = Employee::query()
                    ->whereRaw(
                        "REPLACE(REPLACE(UPPER(CONCAT(COALESCE(firstname,''), ' ', COALESCE(lastname,''))), ' ', ''), '.', '') = ?",
                        [$qrName]
                    )
                    ->first();
                if ($employee) {
                    return $employee;
                }
            }
        }

        return null;
    }

    /** True for library QR / code-like tokens, not personal names. */
    protected function looksLikeIdToken(string $value): bool
    {
        $value = trim($value);

        return (bool) preg_match('/^(E|S)-\d+/i', $value)
            || (bool) preg_match('/^[A-Z]{1,6}-\d+/i', $value);
    }

    /**
     * @return array{student_no: ?string, full_name: ?string, course: ?string}
     */
    public function parseQr(string $raw): array
    {
        $raw = trim(str_replace("\r", '', $raw));

        if (str_contains($raw, "\n")) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $raw))));

            return [
                'student_no' => $lines[0] ?? null,
                'full_name' => $lines[1] ?? null,
                'course' => $lines[2] ?? null,
            ];
        }

        $parts = array_map('trim', explode(',', $raw));

        if (preg_match('/^\d{2}-\d+$/', $parts[0] ?? '')) {
            return [
                'student_no' => $parts[0] ?? null,
                'full_name' => $parts[1] ?? null,
                'course' => $parts[2] ?? null,
            ];
        }

        return [
            'student_no' => null,
            'full_name' => $parts[0] ?? null,
            'course' => $parts[1] ?? null,
        ];
    }

    public function normalizeName(string $name): string
    {
        $qrName = strtoupper($name);
        $qrName = preg_replace('/[^A-Z\s]/', '', $qrName);
        $qrName = preg_replace('/\b[A-Z]\b/', '', $qrName);
        $qrName = preg_replace('/\s+/', '', $qrName);

        return $qrName ?? '';
    }

    /**
     * Live / online kiosk scan — toggle IN/OUT from latest log.
     */
    public function recordLiveScan(Student|Employee $patron, ?string $source = null): AttendanceLog
    {
        $isStudent = $patron instanceof Student;

        if ($isStudent) {
            $this->sessions->closeStaleOpenInForStudent($patron);
            $lastLog = AttendanceLog::where('student_id', $patron->id)
                ->orderByDesc('scanned_at')
                ->orderByDesc('id')
                ->first();
        } else {
            $this->sessions->closeStaleOpenInForEmployee($patron);
            $lastLog = AttendanceLog::where('employee_id', $patron->id)
                ->orderByDesc('scanned_at')
                ->orderByDesc('id')
                ->first();
        }

        $newStatus = ($lastLog && $this->sessions->isInStatus($lastLog->status)) ? 'OUT' : 'IN';
        $scannedAt = Carbon::now(AttendanceSessionService::TZ);

        $payload = [
            'status' => $newStatus,
            'scanned_at' => $scannedAt,
        ];
        if ($source) {
            $payload['source'] = $source;
        }
        if ($isStudent) {
            $payload['student_id'] = $patron->id;
        } else {
            $payload['employee_id'] = $patron->id;
        }

        $log = AttendanceLog::create($payload);
        $this->maybeSendSms($patron, $newStatus, $scannedAt);

        return $log;
    }

    /**
     * Record a scan that was captured offline (or online via hybrid push).
     * IN/OUT is re-derived from server history for the scanned_at timeline.
     */
    public function recordSyncedScan(
        Student|Employee $patron,
        string $clientStatus,
        Carbon $scannedAt,
        string $clientUuid,
        ?HybridDevice $device = null
    ): AttendanceLog {
        if ($existing = AttendanceLog::where('client_uuid', $clientUuid)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($patron, $scannedAt, $clientUuid, $device) {
            $isStudent = $patron instanceof Student;

            if ($isStudent) {
                $this->sessions->closeStaleOpenInForStudent($patron);
                $priorQuery = AttendanceLog::query()->where('student_id', $patron->id);
            } else {
                $this->sessions->closeStaleOpenInForEmployee($patron);
                $priorQuery = AttendanceLog::query()->where('employee_id', $patron->id);
            }

            $prior = $priorQuery
                ->where('scanned_at', '<', $scannedAt)
                ->orderByDesc('scanned_at')
                ->orderByDesc('id')
                ->first();

            $resolved = ($prior && $this->sessions->isInStatus($prior->status)) ? 'OUT' : 'IN';

            $payload = [
                'client_uuid' => $clientUuid,
                'hybrid_device_id' => $device?->id,
                'source' => $device ? 'hybrid_sync' : 'hybrid_web',
                'status' => $resolved,
                'scanned_at' => $scannedAt,
            ];

            if ($isStudent) {
                $payload['student_id'] = $patron->id;
            } else {
                $payload['employee_id'] = $patron->id;
            }

            $log = AttendanceLog::create($payload);

            $this->maybeSendSms($patron, $resolved, $scannedAt);

            return $log;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function patronJsonResponse(Student|Employee $patron, AttendanceLog $log, array $extra = []): array
    {
        $isStudent = $patron instanceof Student;
        $status = strtoupper((string) $log->status);

        $base = array_merge([
            'status' => $status,
            'logout_feedback_enabled' => Setting::logoutFeedbackEnabled(),
            'log' => [
                'scanned_at' => Carbon::parse($log->scanned_at)
                    ->timezone(AttendanceSessionService::TZ)
                    ->format('Y-m-d h:i:s A'),
            ],
        ], $extra);

        if ($isStudent) {
            return array_merge($base, [
                'type' => 'student',
                'student_id' => $patron->id,
                'student' => [
                    'firstname' => $patron->firstname,
                    'lastname' => $patron->lastname,
                    'profile_picture' => $patron->profile_picture,
                ],
            ]);
        }

        return array_merge($base, [
            'type' => 'employee',
            'employee_id' => $patron->id,
            'employee' => [
                'firstname' => $patron->firstname,
                'lastname' => $patron->lastname,
                'profile_picture' => $patron->formal_picture,
                'designation' => $patron->designation ?? $patron->department,
            ],
        ]);
    }

    protected function maybeSendSms(Student|Employee $patron, string $status, Carbon $at): void
    {
        if (empty($patron->mobile_number)) {
            return;
        }

        try {
            $template = Setting::where('key', 'scan_sms')->value('value')
                ?? 'Hello {name}, you scanned {status} at the library at {time}.';

            $message = str_replace(
                ['{name}', '{status}', '{time}'],
                [
                    trim($patron->firstname.' '.$patron->lastname),
                    $status,
                    $at->timezone(AttendanceSessionService::TZ)->format('h:i A'),
                ],
                $template
            );

            app(SMSController::class)->sendDirect(
                $patron->mobile_number,
                $message
            );
        } catch (\Throwable) {
            // SMS must not block attendance acceptance
        }
    }
}
