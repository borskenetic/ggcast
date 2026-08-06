<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HybridCatalogPush extends Model
{
    protected $fillable = [
        'client_uuid',
        'hybrid_device_id',
        'book_id',
        'copies_created',
        'accepted_book_ids',
    ];

    protected function casts(): array
    {
        return [
            'accepted_book_ids' => 'array',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(HybridDevice::class, 'hybrid_device_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
