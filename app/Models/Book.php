<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'control_no','date_time_stamp', 'fixed_length_data',
        'isbn', 'price','cataloging_source_a','cataloging_source_b','cataloging_source_e',
        'main_author', 'title_statement',
        'title_author','edition',
        'pub_place', 'publisher', 'pub_year',
        'pages', 'illustrations', 'size', 'volume',
        'content_type','content_code', 'media_type','media_code','carrier_type','carrier_code',
        'series_title', 'general_note', 'bibliography_note',
        'source_vendor', 'source_date',
        'subject_topic', 'subject_form', 'genre',
        'library_name', 'section', 'call_number',
        'accession_no','created_at','updated_at','barcode',
        'rfid','availability','year','course','curriculum','program','cover_image'
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function logs() {
        return $this->hasMany(BookLog::class);
    }
    
    // App\Models\Book.php
    public function programs()
    {
        return $this->belongsToMany(Program::class, 'book_program', 'book_id', 'program_id');
    }

    public function marcFields()
    {
        return $this->hasMany(BookMarcField::class, 'book_id');
    }

    /**
     * @return array<string, string> column => label
     */
    public static function copyIdentifierFields(): array
    {
        return config('catalog.copy_identifier_fields', [
            'accession_no' => 'Accession no.',
            'barcode' => 'Barcode',
            'rfid' => 'RFID',
        ]);
    }

    /**
     * Find one catalog copy by accession, barcode, or RFID (exact match).
     */
    public static function findByCopyIdentifier(string $code): ?self
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        foreach (array_keys(static::copyIdentifierFields()) as $column) {
            $book = static::query()
                ->whereNull('archived_at')
                ->where($column, $code)
                ->first();

            if ($book) {
                return $book;
            }
        }

        return null;
    }

    /**
     * Best value to scan or type at circulation (first filled field in priority order).
     */
    public function copyIdentifierForCirculation(): ?string
    {
        foreach (array_keys(static::copyIdentifierFields()) as $column) {
            $value = $this->getAttribute($column);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Human label for whichever copy identifier is set (e.g. "Accession no.").
     */
    public function copyIdentifierTypeLabel(): ?string
    {
        foreach (static::copyIdentifierFields() as $column => $label) {
            if (filled($this->getAttribute($column))) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Short line for lists, e.g. "Accession: GG-2024-0001".
     */
    public function copyIdentifierSummary(): string
    {
        foreach (static::copyIdentifierFields() as $column => $label) {
            $value = $this->getAttribute($column);
            if (filled($value)) {
                return $label.': '.$value;
            }
        }

        return 'No copy ID on file';
    }

    /**
     * Reserve copies are shelved under a sublocation (MARC 852$c, stored in section)
     * whose text contains "reserve" — for example Reserve, Reserves, or Course Reserve.
     */
    public static function isReserveSection(?string $section): bool
    {
        return preg_match('/reserve/i', (string) $section) === 1;
    }

    public function isReserve(): bool
    {
        return self::isReserveSection($this->section);
    }

}
