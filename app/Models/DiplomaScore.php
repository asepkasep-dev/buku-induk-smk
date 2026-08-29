<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiplomaScore extends Model
{
    protected $fillable = [
        'student_id',
        'subject_id',
        'final_score',
        'predicate',
        'status',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'final_score' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}