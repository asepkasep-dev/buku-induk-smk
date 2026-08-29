<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PKLRecord extends Model
{
    protected $fillable = [
        'student_id',
        'partner_name',
        'location',
        'start_date',
        'end_date',
        'competency_score',
        'competency_predicate',
        'attitude_score',
        'attitude_predicate',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'competency_score' => 'integer',
            'attitude_score' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}