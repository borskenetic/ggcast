<?php

namespace App\Services;

use App\Http\Controllers\SMSController;
use App\Models\AttendanceLog;
use App\Models\HybridDevice;
use App\Models\Setting;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HybridAttendanceScanService
{
    public function __construct(
        protected AttendanceSessionService $sessions
    ) {}

    public function resolveStudent(string $raw): ?Student
    {
        $token = trim(str_replace("\r", '', $raw));
        if ($token === '') {
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

        if (! empty($parsed['full_name'])) {
            $qrName = strtoupper($parsed['full_name']);
            $qrName = preg_replace('/[^A-Z\s]/', '', $qrName);
            $qrName = preg_replace('/\b[A-Z]\b/', '', $qrName);
            $qrName = preg_replace('/\s+/', '', $qrName);

            return Student::where('normalized_name', $qrName)->first();
        }

        // Bare id_number paste
        return Student::where('id_number', $token)->first();
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

    /**
     * Record a scan that was captured offline (or online via hybrid push).
     * IN/OUT is re-derived from server history for the scanned_at timeline.
     */
    public function recordSyncedScan(
        Student $student,
        string $clientStatus,
        Carbon $scannedAt,
        string $clientUuid,
        ?HybridDevice $device = null
    ): AttendanceLog {
        if ($existing = AttendanceLog::where('client_uuid', $clientUuid)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($student, $scannedAt, $clientUuid, $device) {
            $this->sessions->closeStaleOpenInForStudent($student);

            $prior = AttendanceLog::query()
                ->where('student_id', $student->id)
                ->where(function ($q) use ($scannedAt) {
                    $q->where('scanned_at', '<', $scannedAt)
                        ->orWhere(function ($q2) use ($scannedAt) {
                            $q2->where('scanned_at', '=', $scannedAt)
                                ->where('id', '>', 0); // keep query shape
                        });
                })
                ->where(function ($q) use ($scannedAt) {
                    $q->where('scanned_at', '<', $scannedAt);
                })
                ->orderByDesc('scanned_at')
                ->orderByDesc('id')
                ->first();

            $resolved = ($prior && $this->sessions->isInStatus($prior->status)) ? 'OUT' : 'IN';

            $log = AttendanceLog::create([
                'client_uuid' => $clientUuid,
                'hybrid_device_id' => $device?->id,
                'source' => $device ? 'hybrid_sync' : 'hybrid_web',
                'student_id' => $student->id,
                'status' => $resolved,
                'scanned_at' => $scannedAt,
            ]);

            $this->maybeSendSms($student, $resolved, $scannedAt);

            return $log;
        });
    }

    protected function maybeSendSms(Student $student, string $status, Carbon $at): void
    {
        if (empty($student->mobile_number)) {
            return;
        }

        try {
            $template = Setting::where('key', 'scan_sms')->value('value')
                ?? 'Hello {name}, you scanned {status} at the library at {time}.';

            $message = str_replace(
                ['{name}', '{status}', '{time}'],
                [
                    trim($student->firstname.' '.$student->lastname),
                    $status,
                    $at->timezone(AttendanceSessionService::TZ)->format('h:i A'),
                ],
                $template
            );

            app(SMSController::class)->sendDirect(
                $student->mobile_number,
                $message
            );
        } catch (\Throwable) {
            // SMS must not block attendance acceptance
        }
    }
}
