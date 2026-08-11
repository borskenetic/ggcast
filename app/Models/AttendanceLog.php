<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    protected $fillable = [
        'student_id',
        'employee_id',
        'status',
        'scanned_at',
        'client_uuid',
        'hybrid_device_id',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function hybridDevice()
    {
        return $this->belongsTo(HybridDevice::class);
    }

    public function isEmployee(): bool
    {
        return $this->employee_id !== null && $this->employee_id !== '';
    }

    public function patronLastName(): string
    {
        if ($this->employee) {
            return (string) $this->employee->lastname;
        }
        if ($this->student) {
            return (string) $this->student->lastname;
        }

        return 'Unknown';
    }

    public function patronFirstName(): string
    {
        if ($this->employee) {
            return (string) $this->employee->firstname;
        }
        if ($this->student) {
            return (string) $this->student->firstname;
        }

        return 'Unknown';
    }

    public function patronProgram(): string
    {
        if ($this->employee) {
            return (string) ($this->employee->program
                ?: $this->employee->department
                ?: 'Faculty/Staff');
        }
        if ($this->student) {
            return (string) ($this->student->course ?? 'Unknown');
        }

        return 'Unknown';
    }

    public function patronYear(): string
    {
        if ($this->employee) {
            return (string) ($this->employee->designation ?: 'Staff');
        }
        if ($this->student) {
            return (string) ($this->student->year ?? 'Unknown');
        }

        return 'Unknown';
    }
}
