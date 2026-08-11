<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;

class AttendanceLogsExport implements FromCollection, WithHeadings
{
    protected $logs;

    // Accept filtered logs from controller
    public function __construct($logs)
    {
        $this->logs = $logs;
    }

    public function collection()
    {
        return $this->logs->map(function ($log) {
            return [
                'lastname'    => $log->patronLastName(),
                'firstname'   => $log->patronFirstName().($log->isEmployee() ? ' (Staff)' : ''),
                'course'      => $log->patronProgram(),
                'status'      => strtoupper($log->status),
                'scanned_at'  => $log->scanned_at 
                    ? Carbon::parse($log->scanned_at, 'UTC')->timezone('Asia/Manila')->format('Y-m-d h:i A')
                    : '—',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Last Name',
            'First Name',
            'Course',
            'Status',
            'Scanned At',
        ];
    }
}
