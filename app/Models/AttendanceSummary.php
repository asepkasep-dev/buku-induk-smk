<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSummary extends Model
{
    protected $fillable = [
        'student_id',
        'semester_id',
        'sick',
        'excused',
        'absent',
        'status',
        'locked_at',
        'locked_by',
        'finalized_at',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'sick' => 'integer',
            'excused' => 'integer',
            'absent' => 'integer',
            'locked_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
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