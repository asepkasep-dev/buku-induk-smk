<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportScore extends Model
{
    protected $fillable = [
        'student_id',
        'subject_offering_id',
        'final_score',
        'letter_grade',
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
            'final_score' => 'integer',
            'locked_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subjectOffering(): BelongsTo
    {
        return $this->belongsTo(SubjectOffering::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}