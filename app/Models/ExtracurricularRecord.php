<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtracurricularRecord extends Model
{
    protected $fillable = [
        'student_id',
        'extracurricular_id',
        'semester_id',
        'predicate',
        'description',
        'status',
        'locked_at',
        'locked_by',
        'finalized_at',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(Extracurricular::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}