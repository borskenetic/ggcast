<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ebook extends Model
{
    //
    protected $fillable = [
        'title',
        'author',
        'publication_year',
        'publisher',
        'source',
        'link',
        'resource_type',
        'publication_scope',
        'frequency',
        'subscription_type',
        'program_id',
        'course_id',
    ];
    
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }
    
    public function course()
    {
        return $this->belongsTo(ProgramCourse::class, 'course_id');
    }

}
