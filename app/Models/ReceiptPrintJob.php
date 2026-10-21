<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptPrintJob extends Model
{
    protected $fillable = [
        'payload',
        'claimed_at',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'claimed_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }
}
