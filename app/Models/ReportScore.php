<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportScore extends Model
{
    protected $fillable = [
        'student_id',
        'subject_offering_id',
        'pengetahuan_avg',
        'keterampilan_avg',
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
            'pengetahuan_avg' => 'decimal:2',
            'keterampilan_avg' => 'decimal:2',
            'final_score' => 'integer',
            'locked_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReportScoreItem::class)->orderBy('sort_order');
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
