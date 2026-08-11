<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $casts = [
        'birth_date' => 'date',
    ];

    protected $fillable = [
        'user_id',
        'role_id',
        'employee_id',
        'formal_picture',
        'department',
        'firstname',
        'lastname',
        'middle_initial',
        'position',
        'designation',
        'program',
        'year_start_work',
        'employee_number',
        'birth_date',
        'mobile_number',
        'sex',
        'tin_id_number',
        'philhealth_number',
        'civil_status',
        'blood_type',
        'sss_number',
        'hdmf_number',
        'qrcode',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'address',
        'emergency_contact_number',
        'emergency_address',
        'employee_signature',
    ];

    // Optional relationships
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
