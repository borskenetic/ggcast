<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    protected $fillable = [
        'student_id',
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

    public function hybridDevice()
    {
        return $this->belongsTo(HybridDevice::class);
    }
}
