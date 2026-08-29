<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UKKRecord extends Model
{
    protected $fillable = [
        'student_id',
        'competency_id',
        'final_score',
        'predicate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'final_score' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}